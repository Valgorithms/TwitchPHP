<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Chat;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Ratchet\Client\Connector;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

use React\Socket\Connector as SocketConnector;
use Twitch\Parts\ChatMessage;
use Twitch\Twitch;

/**
 * The Twitch chat client over the WebSocket IRC gateway
 * (`wss://irc-ws.chat.twitch.tv`).
 *
 * Handles the `CAP`/`PASS`/`NICK` handshake, `PING`/`PONG`, channel membership,
 * and IRCv3 tag parsing. Chat messages surface on the {@see Twitch} client as
 * the `chat` event carrying a {@see ChatMessage}; a message beginning with the
 * command prefix also fires `command` and any handler registered with
 * {@see registerCommand()} — the small equivalent of DiscordPHP's
 * `MessageCommandClient`.
 *
 * Once connected, it stays connected. A connection that closes, or goes quiet
 * and then does not answer a `PING` (what a dropped network looks like: no
 * close ever arrives), is replaced, and so is one Twitch asks to replace with
 * `RECONNECT`. Attempts back off along `retry_delays`; when those run out it
 * fires `chat.reconnect_failed` once and keeps trying every
 * `keep_trying_every` seconds, and {@see reconnect()} tries at once. Only the
 * first connection is the caller's to retry: {@see connect()} rejects if it
 * fails.
 *
 * Events, on the {@see Twitch} client:
 * - `chat.connected` (Twitch) — logged in, including after a reconnect.
 * - `chat.disconnected` (int $code, string $reason, Twitch) — a connection that
 *   was up is gone. The code is the close frame's, or 0 when the connection
 *   was given up on.
 * - `chat.reconnecting` (int $attempt, float $delay, string $reason, Twitch) —
 *   attempt `$attempt` of this outage starts in `$delay` seconds.
 * - `chat.reconnect_failed` (int $attempts, string $reason, Twitch) — the retry
 *   schedule ran out; once per outage.
 * - `chat.auth_failed` (string $notice, Twitch) — Twitch refused the token;
 *   once per outage. {@see Twitch} recovers the token when it sees this.
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class Irc implements EventEmitterInterface
{
    use EventEmitterTrait;

    private const GATEWAY = 'wss://irc-ws.chat.twitch.tv:443';

    /**
     * Seconds before each reconnect attempt of an outage, first to last; the
     * outage counts as failed once the last one has.
     */
    public const RETRY_DELAYS = [1, 2, 5, 10, 20, 30, 60, 60, 60, 60];

    private const DEFAULTS = [
        'retry_delays' => self::RETRY_DELAYS,
        // Once retry_delays has run out.
        'keep_trying_every' => 300.0,
        // Each delay is moved by up to this fraction either way, so a bot that
        // lost the network with everyone else does not return in step with them.
        'jitter' => 0.2,
        // Quiet for this long, and a PING goes out; Twitch's own come every ~5
        // minutes, far too slowly to notice a dead connection by.
        'ping_after' => 60.0,
        // No reply to that PING for this long, and the connection is dead.
        'pong_timeout' => 15.0,
        // From the socket opening to Twitch's welcome.
        'welcome_timeout' => 15.0,
        // For DNS, TCP, TLS and the WebSocket handshake together.
        'connect_timeout' => 15.0,
        // callable(string $url): PromiseInterface resolving to the socket. For
        // tests; the real one is Pawl's.
        'connector' => null,
    ];

    /** @var object|null The WebSocket: Pawl's, or a test's stand-in with on(), send() and close(). */
    private ?object $conn = null;

    /**
     * Which connection's events count. Bumped whenever one is replaced, so a
     * socket that was given up on cannot close or speak for its successor.
     */
    private int $generation = 0;

    private ?Deferred $ready = null;

    /** The attempt in flight, shared by everything that asks for one meanwhile. */
    private ?Deferred $attempt = null;

    /** @var array<string, callable(ChatMessage, list<string>): void> */
    private array $commands = [];

    /** @var array<string, mixed> */
    private array $options;

    private bool $closing = false;

    private bool $connected = false;

    private bool $everConnected = false;

    /** Failed attempts in this outage. */
    private int $failures = 0;

    private bool $gaveUp = false;

    private bool $authFailed = false;

    private float $lastHeard = 0.0;

    private ?float $pingSentAt = null;

    private ?TimerInterface $retryTimer = null;

    private ?TimerInterface $welcomeTimer = null;

    private ?TimerInterface $watchdog = null;

    /**
     * @param list<string>         $channels Channel logins, each with a leading `#`.
     * @param array<string, mixed> $options  Overrides for the timing above, by key.
     */
    public function __construct(
        private readonly Twitch $twitch,
        private readonly string $nick,
        private string $token,
        private array $channels = [],
        private readonly string $prefix = '!',
        array $options = [],
    ) {
        $this->options = $options + self::DEFAULTS;
    }

    /**
     * Makes the first connection.
     *
     * @return PromiseInterface<self> Resolves once logged in; rejects if this
     *                                first attempt fails, without retrying.
     */
    public function connect(): PromiseInterface
    {
        $this->closing = false;
        $this->ready = new Deferred();
        $this->attempt()->then(null, static fn () => null);

        return $this->ready->promise();
    }

    /**
     * Tries to connect again now, whatever the retrying is doing: for someone
     * who has fixed the network, or pressed a button.
     *
     * @return PromiseInterface<self> Resolves once logged in, at once when
     *                                already connected. Rejects with why this
     *                                attempt failed; the retrying carries on.
     */
    public function reconnect(): PromiseInterface
    {
        if ($this->connected) {
            return resolve($this);
        }

        $this->closing = false;

        return $this->attempt();
    }

    /** Whether logged in over a connection that is still answering. */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Uses a new access token from the next login on.
     *
     * A connection that is up keeps going: Twitch checks the token only when
     * logging in. A retry waiting to log in with the old one goes now.
     */
    public function setToken(string $token): void
    {
        if ($token === $this->token) {
            return;
        }

        $this->token = $token;

        if ($this->everConnected && ! $this->connected && ! $this->closing && $this->retryTimer !== null) {
            $this->attempt()->then(null, static fn () => null);
        }
    }

    /** Disconnects for good: nothing is retried after this. */
    public function close(): void
    {
        $this->closing = true;
        $this->cancel($this->retryTimer);
        $this->cancel($this->welcomeTimer);
        $this->cancel($this->watchdog);

        $hadConnection = $this->conn !== null;
        $this->connected = false;
        // Also stops a socket still opening from logging in once it does.
        $this->abandon();

        $attempt = $this->attempt;
        $this->attempt = null;
        $attempt?->reject(new \RuntimeException('closed'));

        if ($hadConnection) {
            $this->twitch->emit('chat.disconnected', [1000, 'closed', $this->twitch]);
        }
    }

    /** Sends a message; pass `$replyTo` to thread it under another message id. */
    public function say(string $channel, string $message, ?string $replyTo = null): void
    {
        $channel = '#' . ltrim(strtolower($channel), '#');
        $tags = $replyTo !== null ? "@reply-parent-msg-id={$replyTo} " : '';
        $this->raw("{$tags}PRIVMSG {$channel} :{$message}");
    }

    public function join(string $channel): void
    {
        $channel = '#' . ltrim(strtolower($channel), '#');
        $this->channels[] = $channel;
        $this->raw('JOIN ' . $channel);
    }

    public function part(string $channel): void
    {
        $channel = '#' . ltrim(strtolower($channel), '#');
        $this->channels = array_values(array_diff($this->channels, [$channel]));
        $this->raw('PART ' . $channel);
    }

    /**
     * Registers a handler for `<prefix><name> [args...]`.
     *
     * @param callable(ChatMessage, list<string>): void $handler
     */
    public function registerCommand(string $name, callable $handler): void
    {
        $this->commands[strtolower($name)] = $handler;
    }

    /** The raw IRC line, without the trailing CRLF. */
    public function raw(string $line): void
    {
        $this->conn?->send($line . "\r\n");
    }

    // ── Connecting ─────────────────────────────────────────────────────

    /** @return PromiseInterface<self> */
    private function attempt(): PromiseInterface
    {
        if ($this->attempt !== null) {
            return $this->attempt->promise();
        }

        $this->cancel($this->retryTimer);
        $this->abandon();

        $deferred = $this->attempt = new Deferred();
        $generation = ++$this->generation;

        $this->open(self::GATEWAY)->then(
            function (object $conn) use ($generation): void {
                if ($generation !== $this->generation) {
                    $conn->close();

                    return;
                }

                $this->conn = $conn;
                $conn->on('message', function ($message) use ($generation): void {
                    if ($generation === $this->generation) {
                        $this->onMessage((string) $message);
                    }
                });
                $conn->on('close', function ($code = null, $reason = null) use ($generation): void {
                    if ($generation === $this->generation) {
                        $this->onClose((int) $code, (string) $reason);
                    }
                });
                $conn->on('error', function (\Throwable $e) use ($generation): void {
                    if ($generation === $this->generation) {
                        $this->twitch->emit('error', [$e, $this->twitch]);
                    }
                });

                $this->heard();
                $this->welcomeTimer = $this->twitch->getLoop()->addTimer(
                    (float) $this->options['welcome_timeout'],
                    function () use ($generation): void {
                        if ($generation === $this->generation) {
                            $this->failAttempt('Twitch did not finish logging in');
                        }
                    },
                );

                $this->raw('CAP REQ :twitch.tv/tags twitch.tv/commands twitch.tv/membership');
                $this->raw('PASS oauth:' . $this->token);
                $this->raw('NICK ' . strtolower($this->nick));
            },
            function (\Throwable $e) use ($generation): void {
                if ($generation === $this->generation) {
                    $this->failAttempt($e->getMessage());
                }
            },
        );

        return $deferred->promise();
    }

    /** @return PromiseInterface<object> */
    private function open(string $url): PromiseInterface
    {
        if (is_callable($this->options['connector'])) {
            return ($this->options['connector'])($url);
        }

        $loop = $this->twitch->getLoop();

        return (new Connector($loop, new SocketConnector(['timeout' => (float) $this->options['connect_timeout']], $loop)))($url);
    }

    private function welcomed(): void
    {
        $this->cancel($this->welcomeTimer);

        $reconnected = $this->everConnected;
        $this->connected = true;
        $this->everConnected = true;
        $this->failures = 0;
        $this->gaveUp = false;
        $this->authFailed = false;
        $this->heard();

        $check = max(0.05, min(5.0, (float) $this->options['ping_after'] / 4));
        $this->watchdog = $this->twitch->getLoop()->addPeriodicTimer($check, fn () => $this->checkAlive());

        foreach ($this->channels as $channel) {
            $this->raw('JOIN ' . $channel);
        }
        $this->twitch->getLogger()->info(($reconnected ? 'irc reconnected as ' : 'irc connected as ') . $this->nick);

        $attempt = $this->attempt;
        $this->attempt = null;
        $attempt?->resolve($this);
        $this->ready?->resolve($this);
        $this->twitch->emit('chat.connected', [$this->twitch]);
    }

    /** One attempt is over without a login; retries, unless it was the first connection. */
    private function failAttempt(string $reason): void
    {
        $this->cancel($this->welcomeTimer);
        $this->abandon();

        $attempt = $this->attempt;
        $this->attempt = null;
        $error = new \RuntimeException($reason);
        $attempt?->reject($error);

        if (! $this->everConnected) {
            $this->ready?->reject($error);

            return;
        }

        if ($this->closing) {
            return;
        }

        ++$this->failures;
        $this->twitch->getLogger()->warning(sprintf('irc reconnect attempt %d failed: %s', $this->failures, $reason));
        $this->scheduleRetry($reason);
    }

    /** A connection that was logged in is gone. */
    private function lost(string $reason, int $code = 0, bool $now = false): void
    {
        if (! $this->connected) {
            return;
        }

        $this->connected = false;
        $this->cancel($this->watchdog);
        $this->abandon();

        $this->twitch->getLogger()->warning("irc connection lost ({$reason}) — reconnecting");
        $this->twitch->emit('chat.disconnected', [$code, $reason, $this->twitch]);

        if ($this->closing) {
            return;
        }

        $this->failures = 0;
        $this->gaveUp = false;

        if ($now) {
            $this->attempt()->then(null, static fn () => null);
        } else {
            $this->scheduleRetry($reason);
        }
    }

    private function scheduleRetry(string $reason): void
    {
        $delays = array_values($this->options['retry_delays']);

        if ($this->failures >= count($delays)) {
            $delay = (float) $this->options['keep_trying_every'];

            if (! $this->gaveUp) {
                $this->gaveUp = true;
                $this->twitch->getLogger()->error(sprintf(
                    'irc could not reconnect after %d attempts (%s); trying again every %ds',
                    $this->failures,
                    $reason,
                    (int) $delay,
                ));
                $this->twitch->emit('chat.reconnect_failed', [$this->failures, $reason, $this->twitch]);
            }
        } else {
            $jitter = (float) $this->options['jitter'];
            $delay = (float) $delays[$this->failures] * (1 + $jitter * (2 * mt_rand() / mt_getrandmax() - 1));
        }

        $this->twitch->emit('chat.reconnecting', [$this->failures + 1, $delay, $reason, $this->twitch]);

        $this->cancel($this->retryTimer);
        $this->retryTimer = $this->twitch->getLoop()->addTimer($delay, function (): void {
            $this->retryTimer = null;
            $this->attempt()->then(null, static fn () => null);
        });
    }

    /**
     * Lets go of the current socket: its events stop counting, and it is closed
     * as far as it still can be. One whose network has gone may never report
     * closing, so nothing waits for it to.
     */
    private function abandon(): void
    {
        ++$this->generation;
        $conn = $this->conn;
        $this->conn = null;

        try {
            $conn?->close();
        } catch (\Throwable) {
            // Closing a dead socket can fail; it is being thrown away either way.
        }
    }

    /**
     * Notices a connection that has stopped answering. Twitch sends nothing
     * while a channel is quiet, so silence alone proves nothing; silence after
     * a PING of our own does.
     */
    private function checkAlive(): void
    {
        if (! $this->connected) {
            return;
        }

        $now = microtime(true);

        if ($this->pingSentAt !== null) {
            if ($now - $this->pingSentAt >= (float) $this->options['pong_timeout']) {
                $this->lost(sprintf('no reply to PING in %ds', (int) round($now - $this->pingSentAt)));
            }

            return;
        }

        if ($now - $this->lastHeard >= (float) $this->options['ping_after']) {
            $this->pingSentAt = $now;
            $this->raw('PING :tmi.twitch.tv');
        }
    }

    private function heard(): void
    {
        $this->lastHeard = microtime(true);
        $this->pingSentAt = null;
    }

    private function cancel(?TimerInterface &$timer): void
    {
        if ($timer !== null) {
            $this->twitch->getLoop()->cancelTimer($timer);
            $timer = null;
        }
    }

    // ── Incoming ───────────────────────────────────────────────────────

    private function onMessage(string $payload): void
    {
        $this->heard();

        foreach (preg_split('/\r\n|\n/', trim($payload)) as $line) {
            if ($line !== '') {
                $this->onLine($line);
            }
        }
    }

    private function onLine(string $line): void
    {
        [$tags, $rest] = str_starts_with($line, '@')
            ? [self::parseTags(substr($line, 1, strpos($line, ' ') - 1)), substr($line, strpos($line, ' ') + 1)]
            : [[], $line];

        if (str_starts_with($rest, 'PING')) {
            $this->raw('PONG' . substr($rest, 4));

            return;
        }

        // :nick!nick@nick.tmi.twitch.tv COMMAND #channel :message
        if (! preg_match('/^(?::(\S+)\s)?(\S+)(?:\s(.*))?$/', $rest, $m)) {
            return;
        }
        $prefix = $m[1];
        $command = $m[2];
        $params = $m[3] ?? '';

        switch ($command) {
            case '001': // welcome
                $this->welcomed();
                break;

            case 'RECONNECT': // Twitch is about to restart the server this connection is on
                $this->lost('Twitch asked for a reconnect', 0, true);
                break;

            case 'PRIVMSG':
                $this->handlePrivmsg($tags, $prefix, $params);
                break;

            case 'JOIN':
            case 'PART':
                $this->twitch->emit('chat.' . strtolower($command), [self::nickOf($prefix), self::channelOf($params), $this->twitch]);
                break;

            case 'NOTICE':
                if (! $this->connected && preg_match('/authentication failed|improperly formatted auth|invalid nick/i', $params)) {
                    $this->loginRefused($params);
                    break;
                }
                // Otherwise an ordinary notice.
                // no break
            case 'CLEARCHAT':
            case 'CLEARMSG':
            case 'USERNOTICE':
            case 'ROOMSTATE':
                $this->twitch->emit('chat.' . strtolower($command), [$tags, $params, $this->twitch]);
                break;
        }
    }

    /** Twitch refused the login, which retrying with the same token cannot fix. */
    private function loginRefused(string $params): void
    {
        $notice = str_contains($params, ' :') ? substr($params, strpos($params, ' :') + 2) : $params;
        $first = ! $this->authFailed;
        $this->authFailed = true;

        // The retry is scheduled first, so a token recovered by chat.auth_failed's
        // listeners, even at once, finds it waiting and brings it forward.
        $this->failAttempt('Twitch refused the login: ' . $notice);

        if ($first) {
            $this->twitch->emit('chat.auth_failed', [$notice, $this->twitch]);
        }
    }

    /**
     * @param array<string, string> $tags
     */
    private function handlePrivmsg(array $tags, string $prefix, string $params): void
    {
        $channel = self::channelOf($params);
        $content = str_contains($params, ' :') ? substr($params, strpos($params, ' :') + 2) : '';
        $badges = self::parseBadges($tags['badges'] ?? '');

        $message = $this->twitch->getFactory()->part(ChatMessage::class, [
            'id' => $tags['id'] ?? '',
            'channel' => $channel,
            'user' => self::nickOf($prefix),
            'user_id' => $tags['user-id'] ?? '',
            'display_name' => $tags['display-name'] ?? self::nickOf($prefix),
            'content' => $content,
            'color' => $tags['color'] ?? '',
            'is_mod' => ($tags['mod'] ?? '0') === '1' || isset($badges['broadcaster']),
            'is_subscriber' => ($tags['subscriber'] ?? '0') === '1',
            'is_vip' => isset($badges['vip']),
            'is_broadcaster' => isset($badges['broadcaster']),
            'is_first_message' => ($tags['first-msg'] ?? '0') === '1',
            'bits' => (int) ($tags['bits'] ?? 0),
            'badges' => $badges,
            'emotes' => $tags['emotes'] ?? '',
            'reply_parent_msg_id' => $tags['reply-parent-msg-id'] ?? null,
            'tags' => $tags,
            'timestamp' => isset($tags['tmi-sent-ts'])
                ? \Carbon\CarbonImmutable::createFromTimestampMs((int) $tags['tmi-sent-ts'])
                : \Carbon\CarbonImmutable::now(),
        ], true);

        $this->twitch->emit('chat', [$message, $this->twitch]);

        if (str_starts_with($content, $this->prefix) && strlen($content) > strlen($this->prefix)) {
            $parts = preg_split('/\s+/', substr($content, strlen($this->prefix))) ?: [];
            $name = strtolower(array_shift($parts) ?? '');
            $args = array_values($parts);
            $this->twitch->emit('command', [$name, $args, $message, $this->twitch]);
            if (isset($this->commands[$name])) {
                ($this->commands[$name])($message, $args);
            }
        }
    }

    private function onClose(int $code, string $reason): void
    {
        $this->conn = null;

        $why = trim("closed by Twitch ({$code} {$reason})");

        if ($this->connected) {
            $this->lost($why, $code);
        } else {
            $this->failAttempt($why);
        }
    }

    // ── Parsing helpers ────────────────────────────────────────────────

    /** @return array<string, string> */
    private static function parseTags(string $raw): array
    {
        $tags = [];
        foreach (explode(';', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $tags[$k] = strtr($v, ['\\s' => ' ', '\\:' => ';', '\\\\' => '\\', '\\r' => "\r", '\\n' => "\n"]);
        }

        return $tags;
    }

    /** @return array<string, string> */
    private static function parseBadges(string $raw): array
    {
        $badges = [];
        foreach (explode(',', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('/', $pair, 2), 2, '1');
            $badges[$k] = $v;
        }

        return $badges;
    }

    private static function nickOf(string $prefix): string
    {
        return str_contains($prefix, '!') ? substr($prefix, 0, strpos($prefix, '!')) : $prefix;
    }

    private static function channelOf(string $params): string
    {
        return preg_match('/#(\S+)/', $params, $m) ? $m[1] : '';
    }
}
