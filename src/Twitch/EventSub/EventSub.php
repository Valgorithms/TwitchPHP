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
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\reject;

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

    private ?WebSocket $conn = null;

    private ?string $sessionId = null;

    private ?Deferred $ready = null;

    private bool $closing = false;

    /** Pending `{type, condition, version}` subscriptions to (re)create on welcome. */
    private array $desired = [];

    public function __construct(private readonly Twitch $twitch)
    {
    }

    /** @return PromiseInterface<self> */
    public function connect(?string $url = null): PromiseInterface
    {
        $this->ready ??= new Deferred();
        $connector = new Connector($this->twitch->getLoop());

        $connector($url ?? self::GATEWAY)->then(
            function (WebSocket $conn): void {
                $this->conn = $conn;
                $conn->on('message', fn (MessageInterface $m) => $this->onMessage((string) $m));
                $conn->on('close', fn ($code = null, $reason = null) => $this->onClose((int) $code, (string) $reason));
                $conn->on('error', fn (\Throwable $e) => $this->twitch->emit('error', [$e, $this->twitch]));
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

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Creates an EventSub subscription bound to this WebSocket session.
     *
     * @param array<string, mixed> $condition
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function subscribe(string $type, array $condition, string $version = '1'): PromiseInterface
    {
        if ($this->sessionId === null) {
            $this->desired[] = compact('type', 'condition', 'version');

            return reject(new \RuntimeException('EventSub session not ready yet — subscription queued for the next welcome'));
        }

        return $this->twitch->request('POST', Endpoint::EVENTSUB_SUBSCRIPTIONS, [
            'type' => $type,
            'version' => $version,
            'condition' => $condition,
            'transport' => ['method' => 'websocket', 'session_id' => $this->sessionId],
        ])->then(function (?array $body) use ($type, $condition, $version) {
            $this->desired[] = compact('type', 'condition', 'version');

            return $body['data'][0] ?? [];
        });
    }

    /** @return PromiseInterface<null> */
    public function unsubscribe(string $subscriptionId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::EVENTSUB_SUBSCRIPTIONS))->addQuery('id', $subscriptionId));
    }

    /** @return PromiseInterface<array<string, mixed>> */
    public function list(?string $status = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::EVENTSUB_SUBSCRIPTIONS))->withQuery(['status' => $status]))
            ->then(static fn (?array $body) => $body ?? []);
    }

    // ── Incoming ───────────────────────────────────────────────────────

    private function onMessage(string $payload): void
    {
        $frame = json_decode($payload, true);
        if (! is_array($frame)) {
            return;
        }

        $messageType = $frame['metadata']['message_type'] ?? '';
        $data = $frame['payload'] ?? [];

        switch ($messageType) {
            case 'session_welcome':
                $this->sessionId = $data['session']['id'] ?? null;
                $this->twitch->getLogger()->info('eventsub session ' . $this->sessionId);
                $this->twitch->emit('eventsub.ready', [$this->sessionId, $this->twitch]);
                $this->ready?->resolve($this);
                $this->ready = null;
                $this->replaySubscriptions();
                break;

            case 'session_keepalive':
                $this->twitch->emit('eventsub.keepalive', [$this->twitch]);
                break;

            case 'session_reconnect':
                $newUrl = $data['session']['reconnect_url'] ?? null;
                $this->twitch->getLogger()->info('eventsub reconnect requested');
                if ($newUrl !== null) {
                    $old = $this->conn;
                    $this->conn = null;
                    $this->ready = new Deferred();
                    $this->connect($newUrl)->then(fn () => $old?->close());
                }
                break;

            case 'revocation':
                $this->twitch->emit('eventsub.revoked', [$data['subscription'] ?? [], $this->twitch]);
                break;

            case 'notification':
                $type = $frame['metadata']['subscription_type'] ?? ($data['subscription']['type'] ?? '');
                $event = $data['event'] ?? [];
                $this->twitch->emit('eventsub', [$type, $event, $frame['metadata'] ?? [], $this->twitch]);
                $this->twitch->emit('eventsub.' . $type, [$event, $frame['metadata'] ?? [], $this->twitch]);
                break;
        }
    }

    private function replaySubscriptions(): void
    {
        $pending = $this->desired;
        $this->desired = [];
        foreach ($pending as $sub) {
            $this->subscribe($sub['type'], $sub['condition'], $sub['version']);
        }
    }

    private function onClose(int $code, string $reason): void
    {
        $this->conn = null;
        $this->sessionId = null;
        $this->twitch->emit('eventsub.disconnected', [$code, $reason, $this->twitch]);

        if (! $this->closing) {
            $this->twitch->getLogger()->warning("eventsub closed ({$code} {$reason}) — reconnecting in 3s");
            $this->ready = new Deferred();
            $this->twitch->getLoop()->addTimer(3.0, fn () => $this->connect());
        }
    }
}
