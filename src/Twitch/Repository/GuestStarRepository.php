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

use React\Promise\PromiseInterface;
use Twitch\Http\Endpoint;
use Twitch\Parts\Part;

/**
 * The `guest_star/*` resource (**beta**) — channel settings, the live session,
 * invites and slots. Scopes are `channel:read:guest_star` /
 * `channel:manage:guest_star` (or the `moderator:` equivalents).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-channel-guest-star-settings
 *
 * @extends AbstractRepository<Part>
 */
class GuestStarRepository extends AbstractRepository
{
    protected string $part = Part::class;

    /** @var array<string, string> */
    protected array $endpoints = [];

    /**
     * A channel's Guest Star settings.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function settings(string $broadcasterId, string $moderatorId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::GUEST_STAR_CHANNEL_SETTINGS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Updates a channel's Guest Star settings.
     *
     * @param array<string, mixed> $fields
     *
     * @return PromiseInterface<null>
     */
    public function updateSettings(string $broadcasterId, array $fields): PromiseInterface
    {
        return $this->twitch->request('PUT', (new Endpoint(Endpoint::GUEST_STAR_CHANNEL_SETTINGS))
            ->addQuery('broadcaster_id', $broadcasterId), $fields);
    }

    /**
     * The broadcaster's active Guest Star session (guests + slots), or `[]`.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function session(string $broadcasterId, string $moderatorId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::GUEST_STAR_SESSION))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'moderator_id' => $moderatorId]))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Starts a Guest Star session for the broadcaster.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function startSession(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::GUEST_STAR_SESSION))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Ends a Guest Star session.
     *
     * @return PromiseInterface<null>
     */
    public function endSession(string $broadcasterId, string $sessionId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::GUEST_STAR_SESSION))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'session_id' => $sessionId]));
    }

    /**
     * Invites a user to the session.
     *
     * @return PromiseInterface<null>
     */
    public function invite(string $broadcasterId, string $moderatorId, string $sessionId, string $guestId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::GUEST_STAR_INVITES))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'session_id' => $sessionId,
            'guest_id' => $guestId,
        ]));
    }

    /**
     * Revokes an invite.
     *
     * @return PromiseInterface<null>
     */
    public function revokeInvite(string $broadcasterId, string $moderatorId, string $sessionId, string $guestId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::GUEST_STAR_INVITES))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'session_id' => $sessionId,
            'guest_id' => $guestId,
        ]));
    }

    /**
     * Assigns an invited guest to a slot.
     *
     * @return PromiseInterface<null>
     */
    public function assignSlot(string $broadcasterId, string $moderatorId, string $sessionId, string $guestId, string $slotId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::GUEST_STAR_SLOT))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'session_id' => $sessionId,
            'guest_id' => $guestId,
            'slot_id' => $slotId,
        ]));
    }

    /**
     * Removes a guest from their slot.
     *
     * @return PromiseInterface<null>
     */
    public function removeSlot(string $broadcasterId, string $moderatorId, string $sessionId, string $slotId, ?string $guestId = null): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::GUEST_STAR_SLOT))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'session_id' => $sessionId,
            'slot_id' => $slotId,
            'guest_id' => $guestId,
        ]));
    }
}
