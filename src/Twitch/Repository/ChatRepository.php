<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Repository;

use Discord\Helpers\Collection;
use React\Promise\PromiseInterface;
use Twitch\Http\Endpoint;
use Twitch\Parts\ChatBadge;
use Twitch\Parts\ChatSettings;
use Twitch\Parts\Chatter;
use Twitch\Parts\Emote;

/**
 * The Helix `chat/*` resource group — settings, chatters, emotes, badges,
 * announcements, shoutouts, sending a message, and per-user colour.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-chat-settings
 *
 * @extends AbstractRepository<Chatter>
 */
class ChatRepository extends AbstractRepository
{
    protected string $part = Chatter::class;

    protected string $discrim = 'user_id';

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::CHATTERS];

    // ── Settings ────────────────────────────────────────────────────────

    /**
     * A channel's chat settings. `$moderatorId` is required to see the
     * moderator-only fields (`non_moderator_chat_delay*`).
     *
     * @return PromiseInterface<ChatSettings>
     */
    public function settings(string $broadcasterId, ?string $moderatorId = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHAT_SETTINGS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
        ]))->then(fn (?array $body) => $this->factory->part(ChatSettings::class, $this->rows($body)[0] ?? [], true));
    }

    /**
     * Updates a channel's chat settings (`moderator:manage:chat_settings`).
     *
     * @param array<string, mixed> $fields Any of the settable {@see ChatSettings} fields.
     *
     * @return PromiseInterface<ChatSettings>
     */
    public function updateSettings(string $broadcasterId, string $moderatorId, array $fields): PromiseInterface
    {
        return $this->twitch->request(
            'PATCH',
            (new Endpoint(Endpoint::CHAT_SETTINGS))->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]),
            $fields,
        )->then(fn (?array $body) => $this->factory->part(ChatSettings::class, $this->rows($body)[0] ?? [], true));
    }

    // ── Chatters ────────────────────────────────────────────────────────

    /**
     * Users currently in the channel's chat (`moderator:read:chatters`).
     *
     * @return PromiseInterface<Collection<Chatter>>
     */
    public function chatters(string $broadcasterId, string $moderatorId, int $first = 1000, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHATTERS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'first' => $first,
            'after' => $after,
        ]))->then(function (?array $body): Collection {
            $c = new Collection([], 'user_id', Chatter::class);
            foreach ($this->rows($body) as $row) {
                $c->pushItem($this->factory->part(Chatter::class, $row, true));
            }

            return $c;
        });
    }

    // ── Emotes ──────────────────────────────────────────────────────────

    /** @return PromiseInterface<Collection<Emote>> */
    public function channelEmotes(string $broadcasterId): PromiseInterface
    {
        return $this->emotes((new Endpoint(Endpoint::CHANNEL_EMOTES))->addQuery('broadcaster_id', $broadcasterId));
    }

    /** @return PromiseInterface<Collection<Emote>> */
    public function globalEmotes(): PromiseInterface
    {
        return $this->emotes(new Endpoint(Endpoint::GLOBAL_EMOTES));
    }

    /** @param list<string> $setIds @return PromiseInterface<Collection<Emote>> */
    public function emoteSets(array $setIds): PromiseInterface
    {
        return $this->emotes((new Endpoint(Endpoint::EMOTE_SETS))->addQuery('emote_set_id', $setIds));
    }

    /** @return PromiseInterface<Collection<Emote>> */
    public function userEmotes(string $userId, ?string $broadcasterId = null, ?string $after = null): PromiseInterface
    {
        return $this->emotes((new Endpoint(Endpoint::USER_EMOTES))->withQuery([
            'user_id' => $userId,
            'broadcaster_id' => $broadcasterId,
            'after' => $after,
        ]));
    }

    // ── Badges ──────────────────────────────────────────────────────────

    /** @return PromiseInterface<Collection<ChatBadge>> */
    public function channelBadges(string $broadcasterId): PromiseInterface
    {
        return $this->badges((new Endpoint(Endpoint::CHANNEL_CHAT_BADGES))->addQuery('broadcaster_id', $broadcasterId));
    }

    /** @return PromiseInterface<Collection<ChatBadge>> */
    public function globalBadges(): PromiseInterface
    {
        return $this->badges(new Endpoint(Endpoint::GLOBAL_CHAT_BADGES));
    }

    // ── Messages / announcements / shoutouts ───────────────────────────

    /**
     * Sends a chat message via Helix (`user:write:chat`, plus
     * `user:bot`/`channel:bot` for app tokens).
     *
     * @return PromiseInterface<array<string, mixed>> `{ message_id, is_sent, drop_reason? }`
     */
    public function sendMessage(string $broadcasterId, string $senderId, string $message, ?string $replyParentMessageId = null): PromiseInterface
    {
        return $this->twitch->request('POST', Endpoint::SEND_CHAT_MESSAGE, array_filter([
            'broadcaster_id' => $broadcasterId,
            'sender_id' => $senderId,
            'message' => $message,
            'reply_parent_message_id' => $replyParentMessageId,
        ], static fn ($v) => $v !== null))->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Sends an announcement (`moderator:manage:announcements`). `$color` is one
     * of `blue`, `green`, `orange`, `purple`, `primary`.
     *
     * @return PromiseInterface<null>
     */
    public function announce(string $broadcasterId, string $moderatorId, string $message, string $color = 'primary'): PromiseInterface
    {
        return $this->twitch->request(
            'POST',
            (new Endpoint(Endpoint::CHAT_ANNOUNCEMENTS))->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]),
            ['message' => $message, 'color' => $color],
        );
    }

    /**
     * Sends a shoutout (`moderator:manage:shoutouts`).
     *
     * @return PromiseInterface<null>
     */
    public function shoutout(string $fromBroadcasterId, string $toBroadcasterId, string $moderatorId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::SHOUTOUTS))->withQuery([
            'from_broadcaster_id' => $fromBroadcasterId,
            'to_broadcaster_id' => $toBroadcasterId,
            'moderator_id' => $moderatorId,
        ]));
    }

    // ── Colour ──────────────────────────────────────────────────────────

    /**
     * The chat name colour for one or more users.
     *
     * @param list<string> $userIds
     *
     * @return PromiseInterface<array<string, string>> user_id => hex colour
     */
    public function colors(array $userIds): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::USER_CHAT_COLOR))->addQuery('user_id', $userIds))
            ->then(function (?array $body): array {
                $out = [];
                foreach ($this->rows($body) as $row) {
                    $out[$row['user_id']] = $row['color'] ?? '';
                }

                return $out;
            });
    }

    /**
     * Sets the authenticated user's chat colour (`user:manage:chat_color`).
     * `$color` is a Twitch preset or, for Turbo/Prime users, a `#RRGGBB` hex.
     *
     * @return PromiseInterface<null>
     */
    public function setColor(string $userId, string $color): PromiseInterface
    {
        return $this->twitch->request('PUT', (new Endpoint(Endpoint::USER_CHAT_COLOR))
            ->withQuery(['user_id' => $userId, 'color' => $color]));
    }

    // ── Internals ──────────────────────────────────────────────────────

    /** @return PromiseInterface<Collection<Emote>> */
    private function emotes(Endpoint $endpoint): PromiseInterface
    {
        return $this->twitch->request('GET', $endpoint)->then(function (?array $body): Collection {
            $c = new Collection([], 'id', Emote::class);
            $template = $body['template'] ?? null;
            foreach ($this->rows($body) as $row) {
                if ($template !== null && ! isset($row['template'])) {
                    $row['template'] = $template;
                }
                $c->pushItem($this->factory->part(Emote::class, $row, true));
            }

            return $c;
        });
    }

    /** @return PromiseInterface<Collection<ChatBadge>> */
    private function badges(Endpoint $endpoint): PromiseInterface
    {
        return $this->twitch->request('GET', $endpoint)->then(function (?array $body): Collection {
            $c = new Collection([], 'set_id', ChatBadge::class);
            foreach ($this->rows($body) as $row) {
                $c->pushItem($this->factory->part(ChatBadge::class, $row, true));
            }

            return $c;
        });
    }
}
