<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Twitch\Chat\Irc;
use Twitch\EventSub\EventSub;
use Twitch\Factory\Factory;
use Twitch\Http\Endpoint;
use Twitch\Http\Exceptions\InvalidTokenException;
use Twitch\Http\Http;
use Twitch\Http\HttpInterface;
use Twitch\Http\OAuth;
use Twitch\Repository\AbstractRepository;

/**
 * The Twitch client — the same role `Discord\Discord` plays under DiscordPHP.
 *
 * Owns the OAuth token lifecycle, the {@see Http} transport, the {@see Factory},
 * the resource repositories (`$twitch->users`, `$twitch->streams`, …), and,
 * when configured, the {@see EventSub} WebSocket and the {@see Irc} chat client.
 * Emits `init` / `ready` once authenticated and set up.
 *
 * @property-read \Twitch\Repository\UserRepository                 $users
 * @property-read \Twitch\Repository\ChannelRepository              $channels
 * @property-read \Twitch\Repository\StreamRepository               $streams
 * @property-read \Twitch\Repository\GameRepository                 $games
 * @property-read \Twitch\Repository\ChatRepository                 $chat
 * @property-read \Twitch\Repository\ModerationRepository           $moderation
 * @property-read \Twitch\Repository\ClipRepository                 $clips
 * @property-read \Twitch\Repository\VideoRepository                $videos
 * @property-read \Twitch\Repository\PollRepository                 $polls
 * @property-read \Twitch\Repository\PredictionRepository           $predictions
 * @property-read \Twitch\Repository\ChannelPointsRepository        $channelPoints
 * @property-read \Twitch\Repository\SubscriptionRepository         $subscriptions
 * @property-read \Twitch\Repository\EventSubSubscriptionRepository $eventSubscriptions
 * @property-read \Twitch\Repository\TeamRepository                 $teams
 * @property-read \Twitch\Repository\ScheduleRepository             $schedule
 * @property-read \Twitch\Repository\CharityRepository              $charity
 * @property-read \Twitch\Repository\GoalRepository                 $goals
 * @property-read \Twitch\Repository\RaidRepository                 $raids
 * @property-read \Twitch\Repository\BitsRepository                 $bits
 * @property-read \Twitch\Repository\HypeTrainRepository            $hypeTrain
 * @property-read \Twitch\Repository\SearchRepository               $search
 * @property-read \Twitch\Repository\WhisperRepository              $whispers
 * @property-read \Twitch\Repository\AdsRepository                  $ads
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
class Twitch implements EventEmitterInterface
{
    use EventEmitterTrait;

    public const VERSION = '2.0.0-dev';

    /** Repository accessors: property name → class. */
    private const REPOSITORIES = [
        'users'              => Repository\UserRepository::class,
        'channels'           => Repository\ChannelRepository::class,
        'streams'            => Repository\StreamRepository::class,
        'games'              => Repository\GameRepository::class,
        'chat'               => Repository\ChatRepository::class,
        'moderation'         => Repository\ModerationRepository::class,
        'clips'              => Repository\ClipRepository::class,
        'videos'             => Repository\VideoRepository::class,
        'polls'              => Repository\PollRepository::class,
        'predictions'        => Repository\PredictionRepository::class,
        'channelPoints'      => Repository\ChannelPointsRepository::class,
        'subscriptions'      => Repository\SubscriptionRepository::class,
        'eventSubscriptions' => Repository\EventSubSubscriptionRepository::class,
        'teams'              => Repository\TeamRepository::class,
        'schedule'           => Repository\ScheduleRepository::class,
        'charity'            => Repository\CharityRepository::class,
        'goals'              => Repository\GoalRepository::class,
        'raids'              => Repository\RaidRepository::class,
        'bits'               => Repository\BitsRepository::class,
        'hypeTrain'          => Repository\HypeTrainRepository::class,
        'search'             => Repository\SearchRepository::class,
        'whispers'           => Repository\WhisperRepository::class,
        'ads'                => Repository\AdsRepository::class,
    ];

    /** @var array<string, mixed> */
    private array $options;

    private LoopInterface $loop;

    private LoggerInterface $logger;

    private OAuth $oauth;

    private HttpInterface $http;

    private Factory $factory;

    private string $token = '';

    private ?string $refreshToken = null;

    private ?string $userId = null;

    private ?string $login = null;

    /** @var list<string> */
    private array $scopes = [];

    private bool $ready = false;

    private bool $refreshing = false;

    /** @var array<string, AbstractRepository> */
    private array $repositories = [];

    private ?EventSub $eventSub = null;

    private ?Irc $irc = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $this->options = $this->resolveOptions($options);

        $this->loop = $this->options['loop'];
        $this->logger = $this->options['logger'];
        $this->token = (string) ($this->options['token'] ?? '');
        $this->refreshToken = $this->options['refresh_token'];

        $this->oauth = new OAuth(
            $this->options['client_id'],
            $this->options['client_secret'],
            $this->loop,
            $this->options['socket_options'],
        );
        $this->factory = new Factory($this);
    }

    /**
     * Authenticates, wires everything up, emits `ready`, then runs the loop.
     */
    public function run(): void
    {
        $this->bootstrap()->then(
            function (): void {
                $this->ready = true;
                $this->logger->info('twitch client ready', ['user' => $this->login, 'user_id' => $this->userId]);
                $this->emit('init', [$this]);
                $this->emit('ready', [$this]);
            },
            function (\Throwable $e): void {
                $this->logger->error('twitch client failed to start: ' . $e->getMessage());
                $this->emit('error', [$e, $this]);
            },
        );

        $this->loop->run();
    }

    /** Resolves once the client holds a valid token and the transport is up. */
    public function bootstrap(): PromiseInterface
    {
        return $this->acquireToken()
            ->then(function (): PromiseInterface {
                $this->http = Http::create($this->token, $this->options['client_id'], $this->logger, $this->loop, $this->options['socket_options']);

                $connections = [];
                if ($this->options['eventsub']) {
                    $connections[] = $this->connectEventSub();
                }
                if ($this->options['nick'] !== null) {
                    $connections[] = $this->connectIrc();
                }

                return $connections === [] ? resolve(null) : \React\Promise\all($connections);
            });
    }

    /** Tears down the WebSocket connections and stops the loop. */
    public function close(): void
    {
        $this->eventSub?->close();
        $this->irc?->close();
        $this->emit('closed', [$this]);
        $this->loop->stop();
    }

    // ── Auth ────────────────────────────────────────────────────────────

    private function acquireToken(): PromiseInterface
    {
        if ($this->token !== '') {
            return $this->oauth->validate($this->token)->then(
                fn (array $info) => $this->applyValidation($info),
                function (\Throwable $e): PromiseInterface {
                    if ($e instanceof InvalidTokenException && $this->refreshToken !== null) {
                        $this->logger->info('access token invalid — refreshing');

                        return $this->refreshAccessToken()->then(fn () => $this->oauth->validate($this->token))
                            ->then(fn (array $info) => $this->applyValidation($info));
                    }

                    throw $e;
                },
            );
        }

        if ($this->options['client_secret'] !== '') {
            $this->logger->info('no token supplied — requesting an app access token');

            return $this->oauth->clientCredentials()->then(function (array $token): void {
                $this->token = $token['access_token'];
            });
        }

        return reject(new \RuntimeException('Provide a "token" (with optional "refresh_token"), or a "client_secret" for an app token.'));
    }

    /** @param array<string, mixed> $info */
    private function applyValidation(array $info): void
    {
        $this->userId = isset($info['user_id']) ? (string) $info['user_id'] : null;
        $this->login = $info['login'] ?? null;
        $this->scopes = $info['scopes'] ?? [];
    }

    /**
     * Trades the refresh token for a fresh access token and updates the
     * transport. Emits `token_refreshed`.
     *
     * @return PromiseInterface<string> The new access token.
     */
    public function refreshAccessToken(): PromiseInterface
    {
        if ($this->refreshToken === null) {
            return reject(new \RuntimeException('No refresh_token available'));
        }
        if ($this->refreshing) {
            return reject(new \RuntimeException('A token refresh is already in progress'));
        }

        $this->refreshing = true;

        return $this->oauth->refreshToken($this->refreshToken)->then(
            function (array $token): string {
                $this->refreshing = false;
                $this->token = $token['access_token'];
                $this->refreshToken = $token['refresh_token'] ?? $this->refreshToken;
                if (isset($this->http)) {
                    $this->http->setToken($this->token);
                }
                $this->emit('token_refreshed', [$this->token, $this]);

                return $this->token;
            },
            function (\Throwable $e) {
                $this->refreshing = false;

                throw $e;
            },
        );
    }

    /**
     * A Helix request that transparently refreshes the token once on a 401.
     * Repositories go through here.
     *
     * @param array<string, mixed>|null $content
     */
    public function request(string $method, Endpoint|string $endpoint, ?array $content = null): PromiseInterface
    {
        return $this->http->request($method, $endpoint, $content)->catch(function (\Throwable $e) use ($method, $endpoint, $content) {
            if ($e instanceof InvalidTokenException && $this->refreshToken !== null && ! $this->refreshing) {
                $this->logger->info('401 on ' . $endpoint . ' — refreshing token and retrying');

                return $this->refreshAccessToken()->then(fn () => $this->http->request($method, $endpoint, $content));
            }

            throw $e;
        });
    }

    // ── Connections ────────────────────────────────────────────────────

    private function connectEventSub(): PromiseInterface
    {
        $this->eventSub = new EventSub($this);

        return $this->eventSub->connect();
    }

    private function connectIrc(): PromiseInterface
    {
        $this->irc = new Irc(
            $this,
            (string) $this->options['nick'],
            $this->token,
            $this->options['channels'],
            (string) $this->options['command_prefix'],
        );

        return $this->irc->connect();
    }

    // ── Accessors ──────────────────────────────────────────────────────

    public function getHttp(): HttpInterface
    {
        return $this->http;
    }

    public function getOAuth(): OAuth
    {
        return $this->oauth;
    }

    public function getFactory(): Factory
    {
        return $this->factory;
    }

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getClientId(): string
    {
        return $this->options['client_id'];
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getEventSub(): ?EventSub
    {
        return $this->eventSub;
    }

    public function getIrc(): ?Irc
    {
        return $this->irc;
    }

    public function __get(string $name): AbstractRepository
    {
        if (! isset(self::REPOSITORIES[$name])) {
            throw new \OutOfBoundsException("Unknown repository or property: {$name}");
        }

        return $this->repositories[$name] ??= $this->factory->repository(self::REPOSITORIES[$name]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setRequired('client_id')
            ->setDefaults([
                'client_secret'  => '',
                'token'          => null,
                'refresh_token'  => null,
                'scopes'         => [],
                'loop'           => null,
                'logger'         => null,
                'socket_options' => [],
                'nick'           => null,
                'channels'       => [],
                'command_prefix' => '!',
                'eventsub'       => false,
            ])
            ->setAllowedTypes('client_id', 'string')
            ->setAllowedTypes('client_secret', 'string')
            ->setAllowedTypes('token', ['null', 'string'])
            ->setAllowedTypes('refresh_token', ['null', 'string'])
            ->setAllowedTypes('scopes', 'string[]')
            ->setAllowedTypes('socket_options', 'array')
            ->setAllowedTypes('nick', ['null', 'string'])
            ->setAllowedTypes('channels', 'string[]')
            ->setAllowedTypes('command_prefix', 'string')
            ->setAllowedTypes('eventsub', 'bool')
            ->setNormalizer('loop', static fn ($o, $v) => $v ?? Loop::get())
            ->setNormalizer('logger', static fn ($o, $v) => $v ?? new NullLogger())
            ->setNormalizer('channels', static fn ($o, $v) => array_map(
                static fn (string $c) => '#' . ltrim(strtolower($c), '#'),
                $v,
            ));

        return $resolver->resolve($options);
    }
}
