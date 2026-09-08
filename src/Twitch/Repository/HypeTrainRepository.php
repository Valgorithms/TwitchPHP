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
use Twitch\Parts\HypeTrainEvent;

/**
 * The `hypetrain/events` resource (`channel:read:hype_train`) — the recent Hype
 * Train history for a broadcaster. Read-only.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-hype-train-events
 *
 * @extends AbstractRepository<HypeTrainEvent>
 */
class HypeTrainRepository extends AbstractRepository
{
    protected string $part = HypeTrainEvent::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::HYPE_TRAIN_EVENTS];

    /**
     * Recent Hype Train events for a broadcaster (newest first).
     *
     * @return PromiseInterface<Collection<HypeTrainEvent>>
     */
    public function forBroadcaster(string $broadcasterId, int $first = 1, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::HYPE_TRAIN_EVENTS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * The most recent Hype Train event, or `null` when the channel has never
     * had one.
     *
     * @return PromiseInterface<HypeTrainEvent|null>
     */
    public function latest(string $broadcasterId): PromiseInterface
    {
        return $this->forBroadcaster($broadcasterId, 1)->then(static fn (Collection $c) => $c->first());
    }
}
