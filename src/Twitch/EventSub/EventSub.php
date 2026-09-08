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

    /**
     * The subscription set to (re)create on every `session_welcome`, keyed by
     * `type|condition` so repeat calls and reconnects do not pile up duplicates.
     *
     * @var array<string, array{type: string, condition: array<string, mixed>, version: string}>
     */
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
        $this->desired = [];
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * The subscriptions this client will (re)create on the next welcome, as
     * `type => condition`. Useful for asserting what a bot has asked for.
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

    /** Forgets a queued/desired subscription so a reconnect will not recreate it. */
    public function forget(string $type, array $condition): void
    {
        unset($this->desired[$type . '|' . json_encode($condition)]);
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
        $key = $type . '|' . json_encode($condition);
        $this->desired[$key] = compact('type', 'condition', 'version');

        if ($this->sessionId === null) {
            return reject(new \RuntimeException('EventSub session not ready yet — subscription queued for the next welcome'));
        }

        return $this->twitch->request('POST', Endpoint::EVENTSUB_SUBSCRIPTIONS, [
            'type' => $type,
            'version' => $version,
            'condition' => $condition,
            'transport' => ['method' => 'websocket', 'session_id' => $this->sessionId],
        ])->then(static fn (?array $body) => $body['data'][0] ?? []);
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
        foreach ($this->desired as $sub) {
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
