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
use Twitch\Parts\HypeTrainEvent;

/**
 * The `hypetrain/status` resource (`channel:read:hype_train`) — whether a Hype
 * Train is running on a channel right now, and the records it is measured
 * against. Read-only.
 *
 * Twitch withdrew the older `hypetrain/events` history endpoint; it answers
 * `410 Gone`. {@see forBroadcaster()} and {@see latest()} remain only so
 * existing callers get a useful error instead of a bare HTTP failure.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-hype-train-status
 *
 * @extends AbstractRepository<HypeTrainEvent>
 */
class HypeTrainRepository extends AbstractRepository
{
    protected string $part = HypeTrainEvent::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::HYPE_TRAIN_STATUS];

    /**
     * The current Hype Train status for a broadcaster, or `null` when the
     * channel has no Hype Train data at all.
     *
     * Returns the decoded row rather than a Part: the payload is a nested
     * `{current, all_time_high, shared_all_time_high}` record whose shape has
     * nothing in common with the retired {@see HypeTrainEvent}.
     *
     * `current` is `null` whenever no train is running, which is the normal
     * case — check it rather than the outer result to ask "is one live?".
     *
     * @return PromiseInterface<array{current: array<string, mixed>|null, all_time_high: array<string, mixed>|null, shared_all_time_high: array<string, mixed>|null}|null>
     */
    public function status(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::HYPE_TRAIN_STATUS))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(static fn (?array $body) => $body['data'][0] ?? null);
    }

    /**
     * Whether a Hype Train is running on the channel right now.
     *
     * @return PromiseInterface<bool>
     */
    public function isActive(string $broadcasterId): PromiseInterface
    {
        return $this->status($broadcasterId)->then(static fn (?array $row) => ($row['current'] ?? null) !== null);
    }

    /**
     * @deprecated Twitch withdrew `hypetrain/events`; it answers 410. Use {@see status()}.
     *
     * @return PromiseInterface<never>
     */
    public function forBroadcaster(string $broadcasterId, int $first = 1, ?string $after = null): PromiseInterface
    {
        return \React\Promise\reject(new \RuntimeException(
            'Get Hype Train Events was withdrawn by Twitch (410). Use HypeTrainRepository::status() instead.',
        ));
    }

    /**
     * @deprecated Twitch withdrew `hypetrain/events`; it answers 410. Use {@see status()}.
     *
     * @return PromiseInterface<never>
     */
    public function latest(string $broadcasterId): PromiseInterface
    {
        return $this->forBroadcaster($broadcasterId);
    }
}
