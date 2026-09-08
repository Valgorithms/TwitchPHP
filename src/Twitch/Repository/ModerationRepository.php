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
use Twitch\Parts\BannedUser;
use Twitch\Parts\BlockedTerm;
use Twitch\Parts\Moderator;

/**
 * The Helix `moderation/*` and related resources — bans and timeouts,
 * moderators, VIPs, blocked terms, message deletion, Shield Mode, warnings.
 *
 * Every call takes an explicit `$broadcasterId` and (where the API requires it)
 * a `$moderatorId`; the authenticated user must be that moderator.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#ban-user
 *
 * @extends AbstractRepository<BannedUser>
 */
class ModerationRepository extends AbstractRepository
{
    protected string $part = BannedUser::class;

    protected string $discrim = 'user_id';

    protected string $idParam = 'user_id';

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::BANNED_USERS];

    // ── Bans / timeouts ────────────────────────────────────────────────

    /**
     * Permanently bans a user (`moderator:manage:banned_users`).
     *
     * @return PromiseInterface<BannedUser>
     */
    public function ban(string $broadcasterId, string $moderatorId, string $userId, ?string $reason = null): PromiseInterface
    {
        return $this->banRequest($broadcasterId, $moderatorId, ['user_id' => $userId, 'reason' => $reason ?? '']);
    }

    /**
     * Times a user out for `$seconds` (1–1_209_600).
     *
     * @return PromiseInterface<BannedUser>
     */
    public function timeout(string $broadcasterId, string $moderatorId, string $userId, int $seconds, ?string $reason = null): PromiseInterface
    {
        return $this->banRequest($broadcasterId, $moderatorId, [
            'user_id' => $userId,
            'duration' => max(1, min(1_209_600, $seconds)),
            'reason' => $reason ?? '',
        ]);
    }

    /** Lifts a ban or timeout. @return PromiseInterface<null> */
    public function unban(string $broadcasterId, string $moderatorId, string $userId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::BANS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'user_id' => $userId,
        ]));
    }

    /**
     * The channel's banned / timed-out users (`moderator:read:banned_users`).
     *
     * @param list<string> $userIds Optionally narrow to these users.
     *
     * @return PromiseInterface<Collection<BannedUser>>
     */
    public function bannedUsers(string $broadcasterId, array $userIds = [], int $first = 100, ?string $after = null): PromiseInterface
    {
        $endpoint = (new Endpoint(Endpoint::BANNED_USERS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
            'after' => $after,
        ]);
        if ($userIds !== []) {
            $endpoint->addQuery('user_id', $userIds);
        }

        return $this->twitch->request('GET', $endpoint)
            ->then(fn (?array $body) => $this->hydrate($body, BannedUser::class, 'user_id'));
    }

    // ── Moderators ─────────────────────────────────────────────────────

    /**
     * @param list<string> $userIds
     *
     * @return PromiseInterface<Collection<Moderator>>
     */
    public function moderators(string $broadcasterId, array $userIds = [], int $first = 100, ?string $after = null): PromiseInterface
    {
        $endpoint = (new Endpoint(Endpoint::MODERATORS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
            'after' => $after,
        ]);
        if ($userIds !== []) {
            $endpoint->addQuery('user_id', $userIds);
        }

        return $this->twitch->request('GET', $endpoint)
            ->then(fn (?array $body) => $this->hydrate($body, Moderator::class, 'user_id'));
    }

    /** Grants moderator (`channel:manage:moderators`). @return PromiseInterface<null> */
    public function addModerator(string $broadcasterId, string $userId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::MODERATORS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'user_id' => $userId]));
    }

    /** Revokes moderator. @return PromiseInterface<null> */
    public function removeModerator(string $broadcasterId, string $userId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::MODERATORS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'user_id' => $userId]));
    }

    // ── VIPs ───────────────────────────────────────────────────────────

    /**
     * @param list<string> $userIds
     *
     * @return PromiseInterface<Collection<Moderator>>
     */
    public function vips(string $broadcasterId, array $userIds = [], int $first = 100, ?string $after = null): PromiseInterface
    {
        $endpoint = (new Endpoint(Endpoint::CHANNEL_VIPS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
            'after' => $after,
        ]);
        if ($userIds !== []) {
            $endpoint->addQuery('user_id', $userIds);
        }

        return $this->twitch->request('GET', $endpoint)
            ->then(fn (?array $body) => $this->hydrate($body, Moderator::class, 'user_id'));
    }

    /** Grants VIP (`channel:manage:vips`). @return PromiseInterface<null> */
    public function addVip(string $broadcasterId, string $userId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::CHANNEL_VIPS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'user_id' => $userId]));
    }

    /** Revokes VIP. @return PromiseInterface<null> */
    public function removeVip(string $broadcasterId, string $userId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::CHANNEL_VIPS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'user_id' => $userId]));
    }

    // ── Blocked terms ─────────────────────────────────────────────────

    /** @return PromiseInterface<Collection<BlockedTerm>> */
    public function blockedTerms(string $broadcasterId, string $moderatorId, int $first = 100, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::BLOCKED_TERMS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'first' => $first,
            'after' => $after,
        ]))->then(fn (?array $body) => $this->hydrate($body, BlockedTerm::class, 'id'));
    }

    /** @return PromiseInterface<BlockedTerm> */
    public function addBlockedTerm(string $broadcasterId, string $moderatorId, string $text): PromiseInterface
    {
        return $this->twitch->request(
            'POST',
            (new Endpoint(Endpoint::BLOCKED_TERMS))->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]),
            ['text' => $text],
        )->then(fn (?array $body) => $this->factory->part(BlockedTerm::class, $this->rows($body)[0] ?? [], true));
    }

    /** @return PromiseInterface<null> */
    public function removeBlockedTerm(string $broadcasterId, string $moderatorId, string $id): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::BLOCKED_TERMS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'id' => $id,
        ]));
    }

    // ── Message deletion / Shield Mode / warnings ─────────────────────

    /**
     * Deletes a single chat message, or (with `$messageId === null`) clears the
     * whole chat (`moderator:manage:chat_messages`).
     *
     * @return PromiseInterface<null>
     */
    public function deleteMessages(string $broadcasterId, string $moderatorId, ?string $messageId = null): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::MODERATION_CHAT))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'message_id' => $messageId,
        ]));
    }

    /** @return PromiseInterface<array<string, mixed>> */
    public function shieldMode(string $broadcasterId, string $moderatorId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SHIELD_MODE))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /** @return PromiseInterface<array<string, mixed>> */
    public function setShieldMode(string $broadcasterId, string $moderatorId, bool $active): PromiseInterface
    {
        return $this->twitch->request(
            'PUT',
            (new Endpoint(Endpoint::SHIELD_MODE))->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]),
            ['is_active' => $active],
        )->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Warns a user (`moderator:manage:warnings`).
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function warn(string $broadcasterId, string $moderatorId, string $userId, string $reason): PromiseInterface
    {
        return $this->twitch->request(
            'POST',
            (new Endpoint(Endpoint::WARNINGS))->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]),
            ['data' => ['user_id' => $userId, 'reason' => $reason]],
        )->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    // ── Unban requests ─────────────────────────────────────────────────

    /**
     * Pending or resolved unban requests for a channel
     * (`moderator:read:unban_requests`). `$status` is `pending`, `approved` or
     * `denied`.
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function unbanRequests(string $broadcasterId, string $moderatorId, string $status = 'pending', int $first = 100, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::UNBAN_REQUESTS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'status' => $status,
            'first' => $first,
            'after' => $after,
        ]))->then(fn (?array $body) => $this->rows($body));
    }

    /**
     * Approves or denies an unban request
     * (`moderator:manage:unban_requests`). `$status` is `approved` or `denied`.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function resolveUnbanRequest(string $broadcasterId, string $moderatorId, string $requestId, string $status, ?string $resolutionText = null): PromiseInterface
    {
        return $this->twitch->request('PATCH', (new Endpoint(Endpoint::UNBAN_REQUESTS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'unban_request_id' => $requestId,
            'status' => $status,
            'resolution_text' => $resolutionText,
        ]))->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * The channels where `$userId` has moderator privileges
     * (`user:read:moderated_channels`).
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function moderatedChannels(string $userId, int $first = 100, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::MODERATED_CHANNELS))->withQuery([
            'user_id' => $userId,
            'first' => $first,
            'after' => $after,
        ]))->then(fn (?array $body) => $this->rows($body));
    }

    // ── Internals ──────────────────────────────────────────────────────

    /**
     * @param array<string, string|int> $payload
     *
     * @return PromiseInterface<BannedUser>
     */
    private function banRequest(string $broadcasterId, string $moderatorId, array $payload): PromiseInterface
    {
        return $this->twitch->request(
            'POST',
            (new Endpoint(Endpoint::BANS))->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]),
            ['data' => $payload],
        )->then(fn (?array $body) => $this->factory->part(BannedUser::class, $this->rows($body)[0] ?? [], true));
    }

    /**
     * @param array<string, mixed>|null $body
     * @param class-string             $class
     *
     * @return Collection<mixed>
     */
    private function hydrate(?array $body, string $class, string $discrim): Collection
    {
        $c = new Collection([], $discrim, $class);
        foreach ($this->rows($body) as $row) {
            $c->pushItem($this->factory->part($class, $row, true));
        }

        return $c;
    }
}
