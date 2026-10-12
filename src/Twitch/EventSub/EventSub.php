<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\EventSub;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Ratchet\Client\Connector;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

use React\Socket\Connector as SocketConnector;
use Twitch\Http\Endpoint;
use Twitch\Twitch;

/**
 * The EventSub WebSocket transport (`wss://eventsub.wss.twitch.tv/ws`).
 *
 * Handles `session_welcome` (captures the session id), `session_keepalive`,
 * `session_reconnect` (transparent hand-off to the new URL), `revocation`, and
 * `notification`. A notification fires two events on the {@see Twitch} client:
 * `eventsub` with `[type, event, metadata]`, and `eventsub.<type>` with
 * `[event, metadata]` (e.g. `eventsub.channel.chat.message`).
 *
 * {@see subscribe()} registers a subscription against the current session.
 *
 * @link https://dev.twitch.tv/docs/eventsub/handling-websocket-events/
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class EventSub implements EventEmitterInterface
{
    use EventEmitterTrait;

    private const GATEWAY = 'wss://eventsub.wss.twitch.tv/ws';

    private ?object $conn = null;

    private ?object $candidate = null;

    private int $generation = 0;

    private bool $everConnected = false;

    private int $failures = 0;

    private ?TimerInterface $retryTimer = null;

    private ?TimerInterface $welcomeTimer = null;

    private ?TimerInterface $watchdog = null;

    /** @var array<string, PromiseInterface> */
    private array $active = [];

    /** @var array<string, string> Subscription id => desired key. */
    private array $subscriptionIds = [];

    /** @var array<string, float> Recently delivered message ids. */
    private array $seen = [];

    private array $options;

    private ?string $sessionId = null;

    private ?Deferred $ready = null;

    private bool $closing = false;

    /**
     * The subscription set to recreate on fresh sessions, keyed by type,
     * version and canonical condition. Handoffs retain existing subscriptions.
     *
     * @var array<string, array{type: string, condition: array<string, mixed>, version: string}>
     */
    private array $desired = [];

    public function __construct(private readonly Twitch $twitch, array $options = [])
    {
        $this->options = $options + [
            'retry_delays' => [1, 2, 5, 10, 20, 30, 60],
            'keep_trying_every' => 300,
            'welcome_timeout' => 15,
            'connect_timeout' => 15,
            'connector' => null,
        ];
    }

    /** @return PromiseInterface<self> */
    public function connect(?string $url = null): PromiseInterface
    {
        if ($this->ready !== null) {
            return $this->ready->promise();
        }
        if ($url === null && $this->conn !== null && $this->sessionId !== null) {
            return resolve($this);
        }
        $this->closing = false;
        $this->cancel($this->retryTimer);
        $ready = $this->ready = new Deferred();
        $generation = ++$this->generation;
        $handoff = $url !== null && $this->conn !== null;
        $this->welcomeTimer = $this->twitch->getLoop()->addTimer(
            (float) $this->options['connect_timeout'] + (float) $this->options['welcome_timeout'],
            fn () => $this->failed(new \RuntimeException('EventSub welcome timed out')),
        );
        $connector = $this->options['connector'] ?? new Connector(
            $this->twitch->getLoop(),
            new SocketConnector($this->twitch->getSocketOptions() + ['timeout' => $this->options['connect_timeout']], $this->twitch->getLoop()),
        );
        resolve(null)->then(fn () => $connector($url ?? self::GATEWAY))->then(
            function (object $conn) use ($generation, $handoff): void {
                if ($this->closing || $generation !== $this->generation) {
                    $this->closeSocket($conn);

                    return;
                }
                $this->candidate = $conn;
                $conn->on('message', fn ($m) => $this->onMessage((string) $m, $conn, $handoff));
                $conn->on('close', fn ($code = null, $reason = null) => $this->onClose((int) $code, (string) $reason, $conn));
                $conn->on('error', function (\Throwable $e) use ($conn): void {
                    if ($conn === $this->conn || $conn === $this->candidate) {
                        $this->twitch->emit('error', [$e, $this->twitch]);
                    }
                });
                $this->cancel($this->welcomeTimer);
                $this->welcomeTimer = $this->twitch->getLoop()->addTimer(
                    (float) $this->options['welcome_timeout'],
                    fn () => $this->failed(new \RuntimeException('EventSub welcome timed out')),
                );
            },
            function (\Throwable $e) use ($generation): void {
                if ($generation === $this->generation && ! $this->closing) {
                    $this->failed($e);
                }
            },
        );

        return $ready->promise();
    }

    public function close(): void
    {
        $hadSocket = $this->conn !== null || $this->candidate !== null;
        $this->closing = true;
        $this->abandon();
        $this->cancel($this->retryTimer);
        $ready = $this->ready;
        $this->ready = null;
        $ready?->reject(new \RuntimeException('EventSub closed'));
        $this->desired = [];
        $this->seen = [];
        if ($hadSocket) {
            $this->twitch->emit('eventsub.disconnected', [1000, 'closed', $this->twitch]);
        }
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Legacy type => condition view; the last condition for each type wins.
     * Use desiredSubscriptionSpecs() to inspect every channel and version.
     *
     * @return array<string, array<string, mixed>>
     */
    public function desiredSubscriptions(): array
    {
        $out = [];
        foreach ($this->desired as $sub) {
            $out[$sub['type']] = $sub['condition'];
        }

        return $out;
    }

    /** @return list<array{type: string, condition: array<string, mixed>, version: string}> */
    public function desiredSubscriptionSpecs(): array
    {
        return array_values($this->desired);
    }

    /** Forgets a queued/desired subscription so a reconnect will not recreate it. */
    public function forget(string $type, array $condition): void
    {
        ksort($condition);
        foreach ($this->desired as $key => $sub) {
            if ($sub['type'] === $type && $sub['condition'] === $condition) {
                unset($this->desired[$key]);
            }
        }
    }

    /**
     * Creates an EventSub subscription bound to this WebSocket session.
     *
     * `$version` defaults to the current version for `$type`
     * ({@see SubscriptionTypes::version()}), so `channel.follow` gets `2`,
     * Guest Star types get `beta`, and everything else `1` — without the caller
     * tracking it.
     *
     * @param array<string, mixed> $condition
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function subscribe(string $type, array $condition, ?string $version = null): PromiseInterface
    {
        $version ??= SubscriptionTypes::version($type);
        ksort($condition);
        $key = $type . '|' . $version . '|' . json_encode($condition, JSON_THROW_ON_ERROR);
        $this->desired[$key] = compact('type', 'condition', 'version');

        if ($this->sessionId === null) {
            return reject(new \RuntimeException('EventSub session not ready yet — subscription queued for the next welcome'));
        }
        if (isset($this->active[$key])) {
            return $this->active[$key];
        }
        $session = $this->sessionId;
        $deferred = new Deferred();
        $promise = $this->active[$key] = $deferred->promise();
        $this->twitch->request('POST', Endpoint::EVENTSUB_SUBSCRIPTIONS, [
            'type' => $type,
            'version' => $version,
            'condition' => $condition,
            'transport' => ['method' => 'websocket', 'session_id' => $this->sessionId],
        ])->then(function (?array $body) use ($key, $session, $deferred): void {
            $row = $body['data'][0] ?? [];
            if ($session === $this->sessionId && isset($row['id'])) {
                $this->subscriptionIds[$row['id']] = $key;
            }
            $deferred->resolve($row);
        }, function (\Throwable $e) use ($key, $promise, $deferred): void {
            if (($this->active[$key] ?? null) === $promise) {
                unset($this->active[$key]);
            }
            $deferred->reject($e);
        });

        return $promise;
    }

    /**
     * Subscribes to many `[type, condition]` (or `[type, condition, version]`)
     * pairs at once. Resolves once every request has settled, with a list of
     * the created subscription rows (failed ones resolve to `null`).
     *
     * @param list<array{0: string, 1: array<string, mixed>, 2?: string}> $specs
     *
     * @return PromiseInterface<list<array<string, mixed>|null>>
     */
    public function subscribeMany(array $specs): PromiseInterface
    {
        return \React\Promise\all(array_map(
            fn (array $s) => $this->subscribe($s[0], $s[1], $s[2] ?? null)->then(null, static fn () => null),
            $specs,
        ));
    }

    // ── Typed helpers for the common bot subscriptions ─────────────────

    /** `channel.chat.message` — needs `user:read:chat`. `$asUserId` is the reading user (usually the bot). */
    public function onChatMessage(string $broadcasterId, string $asUserId): PromiseInterface
    {
        return $this->subscribe(SubscriptionTypes::CHANNEL_CHAT_MESSAGE, [
            'broadcaster_user_id' => $broadcasterId,
            'user_id' => $asUserId,
        ]);
    }

    /** `channel.follow` v2 — needs `moderator:read:followers`. */
    public function onFollow(string $broadcasterId, string $moderatorId): PromiseInterface
    {
        return $this->subscribe(SubscriptionTypes::CHANNEL_FOLLOW, [
            'broadcaster_user_id' => $broadcasterId,
            'moderator_user_id' => $moderatorId,
        ]);
    }

    /** `channel.subscribe`, `.subscription.gift`, `.subscription.message` in one call. */
    public function onSubscriptions(string $broadcasterId): PromiseInterface
    {
        return $this->subscribeMany([
            [SubscriptionTypes::CHANNEL_SUBSCRIBE, ['broadcaster_user_id' => $broadcasterId]],
            [SubscriptionTypes::CHANNEL_SUBSCRIPTION_GIFT, ['broadcaster_user_id' => $broadcasterId]],
            [SubscriptionTypes::CHANNEL_SUBSCRIPTION_MESSAGE, ['broadcaster_user_id' => $broadcasterId]],
        ]);
    }

    /** `channel.cheer` — needs `bits:read`. */
    public function onCheer(string $broadcasterId): PromiseInterface
    {
        return $this->subscribe(SubscriptionTypes::CHANNEL_CHEER, ['broadcaster_user_id' => $broadcasterId]);
    }

    /** `channel.raid` to this channel. */
    public function onRaid(string $broadcasterId): PromiseInterface
    {
        return $this->subscribe(SubscriptionTypes::CHANNEL_RAID, ['to_broadcaster_user_id' => $broadcasterId]);
    }

    /** `stream.online` + `stream.offline`. */
    public function onStreamChange(string $broadcasterId): PromiseInterface
    {
        return $this->subscribeMany([
            [SubscriptionTypes::STREAM_ONLINE, ['broadcaster_user_id' => $broadcasterId]],
            [SubscriptionTypes::STREAM_OFFLINE, ['broadcaster_user_id' => $broadcasterId]],
        ]);
    }

    /** `channel.update` v2 — category / title / content-label changes. */
    public function onChannelUpdate(string $broadcasterId): PromiseInterface
    {
        return $this->subscribe(SubscriptionTypes::CHANNEL_UPDATE, ['broadcaster_user_id' => $broadcasterId]);
    }

    /** `channel.ad_break.begin` — needs `channel:read:ads`. */
    public function onAdBreakBegin(string $broadcasterId): PromiseInterface
    {
        return $this->subscribe(SubscriptionTypes::CHANNEL_AD_BREAK_BEGIN, ['broadcaster_user_id' => $broadcasterId]);
    }

    /**
     * `channel.channel_points_custom_reward_redemption.add` — needs
     * `channel:read:redemptions`. Pass `$rewardId` to scope to one reward.
     */
    public function onPointsRedemption(string $broadcasterId, ?string $rewardId = null): PromiseInterface
    {
        $condition = ['broadcaster_user_id' => $broadcasterId];
        if ($rewardId !== null) {
            $condition['reward_id'] = $rewardId;
        }

        return $this->subscribe(SubscriptionTypes::CHANNEL_POINTS_CUSTOM_REWARD_REDEMPTION_ADD, $condition);
    }

    /** `channel.ban` + `channel.unban` — needs `channel:moderate`. */
    public function onBans(string $broadcasterId): PromiseInterface
    {
        return $this->subscribeMany([
            [SubscriptionTypes::CHANNEL_BAN, ['broadcaster_user_id' => $broadcasterId]],
            [SubscriptionTypes::CHANNEL_UNBAN, ['broadcaster_user_id' => $broadcasterId]],
        ]);
    }

    /** @return PromiseInterface<null> */
    public function unsubscribe(string $subscriptionId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::EVENTSUB_SUBSCRIPTIONS))->addQuery('id', $subscriptionId))
            ->then(function () use ($subscriptionId): void {
                $key = $this->subscriptionIds[$subscriptionId] ?? null;
                if ($key !== null) {
                    unset($this->desired[$key], $this->active[$key]);
                }
                unset($this->subscriptionIds[$subscriptionId]);
            });
    }

    /** @return PromiseInterface<array<string, mixed>> */
    public function list(?string $status = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::EVENTSUB_SUBSCRIPTIONS))->withQuery(['status' => $status]))
            ->then(static fn (?array $body) => $body ?? []);
    }

    // ── Incoming ───────────────────────────────────────────────────────

    private function onMessage(string $payload, ?object $source = null, bool $handoff = false): void
    {
        if ($this->closing || ($source !== null && $source !== $this->conn && $source !== $this->candidate)) {
            return;
        }
        $frame = json_decode($payload, true);
        if (! is_array($frame)) {
            return;
        }

        $messageType = $frame['metadata']['message_type'] ?? '';
        $data = $frame['payload'] ?? [];

        switch ($messageType) {
            case 'session_welcome':
                if ($source !== null && $source !== $this->candidate) {
                    return;
                }
                if (! isset($data['session']['id']) || ! is_string($data['session']['id']) || $data['session']['id'] === '') {
                    $this->failed(new \RuntimeException('EventSub welcome did not contain a session id'));

                    return;
                }
                $old = $this->conn;
                $this->conn = $source ?? $this->candidate;
                $this->candidate = null;
                $this->cancel($this->welcomeTimer);
                $this->sessionId = $data['session']['id'] ?? null;
                $this->everConnected = true;
                $this->failures = 0;
                $this->resetWatchdog($data['session']['keepalive_timeout_seconds'] ?? 10);
                $ready = $this->ready;
                $this->ready = null;
                if (! $handoff) {
                    $this->active = [];
                    $this->subscriptionIds = [];
                    $this->replaySubscriptions();
                }
                if ($old !== null && $old !== $this->conn) {
                    $this->closeSocket($old);
                }
                $this->twitch->getLogger()->info('eventsub session ' . $this->sessionId);
                $this->twitch->emit('eventsub.ready', [$this->sessionId, $this->twitch]);
                $ready?->resolve($this);
                break;

            case 'session_keepalive':
                if ($source === null || $source === $this->conn) {
                    $this->resetWatchdog();
                }
                $this->twitch->emit('eventsub.keepalive', [$this->twitch]);
                break;

            case 'session_reconnect':
                $newUrl = $data['session']['reconnect_url'] ?? null;
                $this->twitch->getLogger()->info('eventsub reconnect requested');
                if ($newUrl !== null) {
                    $this->connect($newUrl)->then(null, static fn () => null);
                }
                break;

            case 'revocation':
                $sub = $data['subscription'] ?? [];
                if (isset($sub['type'], $sub['condition'])) {
                    $this->forget($sub['type'], $sub['condition']);
                }
                $key = $this->subscriptionIds[$sub['id'] ?? ''] ?? null;
                if ($key !== null) {
                    unset($this->active[$key], $this->subscriptionIds[$sub['id']]);
                }
                $this->twitch->emit('eventsub.revoked', [$data['subscription'] ?? [], $this->twitch]);
                break;

            case 'notification':
                if ($source === null || $source === $this->conn) {
                    $this->resetWatchdog();
                }
                $now = microtime(true);
                $this->seen = array_filter($this->seen, static fn (float $at) => $now - $at < 600);
                $id = $frame['metadata']['message_id'] ?? null;
                if ($id !== null) {
                    if (isset($this->seen[$id])) {
                        return;
                    }
                    $this->seen[$id] = $now;
                    if (count($this->seen) > 10000) {
                        array_shift($this->seen);
                    }
                }
                $type = $frame['metadata']['subscription_type'] ?? ($data['subscription']['type'] ?? '');
                $event = $data['event'] ?? [];
                $this->twitch->emit('eventsub', [$type, $event, $frame['metadata'] ?? [], $this->twitch]);
                $this->twitch->emit('eventsub.' . $type, [$event, $frame['metadata'] ?? [], $this->twitch]);
                break;
        }
    }

    private function replaySubscriptions(): void
    {
        foreach ($this->desired as $sub) {
            $this->subscribe($sub['type'], $sub['condition'], $sub['version'])->then(null, function (\Throwable $e) use ($sub): void {
                $this->twitch->emit('eventsub.subscription_failed', [$sub, $e, $this->twitch]);
            });
        }
    }

    private function onClose(int $code, string $reason, ?object $source = null): void
    {
        if ($source !== null && $source !== $this->conn && $source !== $this->candidate) {
            return;
        }
        $this->twitch->emit('eventsub.disconnected', [$code, $reason, $this->twitch]);
        $this->failed(new \RuntimeException("EventSub closed ({$code} {$reason})"));
    }

    private function failed(\Throwable $error): void
    {
        $this->abandon();
        $ready = $this->ready;
        $this->ready = null;
        if (! $this->closing && $this->everConnected) {
            $delays = $this->options['retry_delays'];
            $delay = (float) ($delays[$this->failures++] ?? $this->options['keep_trying_every']);
            $this->cancel($this->retryTimer);
            $this->retryTimer = $this->twitch->getLoop()->addTimer(max(0.0, $delay), function (): void {
                $this->retryTimer = null;
                if (! $this->closing) {
                    $this->connect()->then(null, static fn () => null);
                }
            });
            $this->twitch->getLogger()->warning($error->getMessage() . ' - retrying');
        }
        $ready?->reject($error);
    }

    private float $keepaliveTimeout = 10;

    private function resetWatchdog(int|float|null $timeout = null): void
    {
        $this->keepaliveTimeout = $timeout === null ? $this->keepaliveTimeout : max(0.01, (float) $timeout);
        $this->cancel($this->watchdog);
        $this->watchdog = $this->twitch->getLoop()->addTimer($this->keepaliveTimeout, fn () => $this->failed(new \RuntimeException('EventSub keepalive timed out')));
    }

    private function abandon(): void
    {
        ++$this->generation;
        $old = $this->conn;
        $candidate = $this->candidate;
        $this->conn = $this->candidate = null;
        $this->sessionId = null;
        $this->active = $this->subscriptionIds = [];
        $this->cancel($this->welcomeTimer);
        $this->cancel($this->watchdog);
        $this->closeSocket($old);
        if ($candidate !== $old) {
            $this->closeSocket($candidate);
        }
    }

    private function closeSocket(?object $socket): void
    {
        try {
            $socket?->close();
        } catch (\Throwable) {
            // A retired socket must not interfere with its replacement.
        }
    }

    private function cancel(?TimerInterface &$timer): void
    {
        if ($timer !== null) {
            $this->twitch->getLoop()->cancelTimer($timer);
            $timer = null;
        }
    }
}
