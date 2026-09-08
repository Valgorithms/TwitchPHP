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
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
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
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class Irc implements EventEmitterInterface
{
    use EventEmitterTrait;

    private const GATEWAY = 'wss://irc-ws.chat.twitch.tv:443';

    private ?WebSocket $conn = null;

    private ?Deferred $ready = null;

    /** @var array<string, callable(ChatMessage, list<string>): void> */
    private array $commands = [];

    private bool $closing = false;

    /**
     * @param list<string> $channels Channel logins, each with a leading `#`.
     */
    public function __construct(
        private readonly Twitch $twitch,
        private readonly string $nick,
        private string $token,
        private array $channels = [],
        private readonly string $prefix = '!',
    ) {
    }

    /** @return PromiseInterface<self> */
    public function connect(): PromiseInterface
    {
        $this->ready = new Deferred();
        $connector = new Connector($this->twitch->getLoop());

        $connector(self::GATEWAY)->then(
            function (WebSocket $conn): void {
                $this->conn = $conn;
                $conn->on('message', fn (MessageInterface $msg) => $this->onMessage((string) $msg));
                $conn->on('close', fn ($code = null, $reason = null) => $this->onClose((int) $code, (string) $reason));
                $conn->on('error', fn (\Throwable $e) => $this->twitch->emit('error', [$e, $this->twitch]));

                $this->raw('CAP REQ :twitch.tv/tags twitch.tv/commands twitch.tv/membership');
                $this->raw('PASS oauth:' . $this->token);
                $this->raw('NICK ' . strtolower($this->nick));
            },
            fn (\Throwable $e) => $this->ready?->reject($e),
        );

        return $this->ready->promise();
    }

    public function close(): void
    {
        $this->closing = true;
        $this->conn?->close();
        $this->conn = null;
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

    // ── Incoming ───────────────────────────────────────────────────────

    private function onMessage(string $payload): void
    {
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
        if (! preg_match('/^(?::(\S+)\s)?(\S+)\s(.*)$/', $rest, $m)) {
            return;
        }
        [, $prefix, $command, $params] = $m;

        switch ($command) {
            case '001': // welcome
                foreach ($this->channels as $channel) {
                    $this->raw('JOIN ' . $channel);
                }
                $this->twitch->getLogger()->info('irc connected as ' . $this->nick);
                $this->ready?->resolve($this);
                $this->twitch->emit('chat.connected', [$this->twitch]);
                break;

            case 'PRIVMSG':
                $this->handlePrivmsg($tags, $prefix, $params);
                break;

            case 'JOIN':
            case 'PART':
                $this->twitch->emit('chat.' . strtolower($command), [self::nickOf($prefix), self::channelOf($params), $this->twitch]);
                break;

            case 'NOTICE':
            case 'CLEARCHAT':
            case 'CLEARMSG':
            case 'USERNOTICE':
            case 'ROOMSTATE':
                $this->twitch->emit('chat.' . strtolower($command), [$tags, $params, $this->twitch]);
                break;
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
        $this->twitch->emit('chat.disconnected', [$code, $reason, $this->twitch]);

        if (! $this->closing) {
            $this->twitch->getLogger()->warning("irc closed ({$code} {$reason}) — reconnecting in 3s");
            $this->twitch->getLoop()->addTimer(3.0, fn () => $this->connect());
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
