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
use Twitch\Parts\Clip;

/**
 * The `clips` resource — look clips up by id / broadcaster / game, and create
 * one from the running stream.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-clips
 *
 * @extends AbstractRepository<Clip>
 */
class ClipRepository extends AbstractRepository
{
    protected string $part = Clip::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::CLIPS];

    /**
     * Clips for a broadcaster, newest first. Optional `started_at` / `ended_at`
     * (RFC3339), `first`, `before`, `after`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Collection<Clip>>
     */
    public function forBroadcaster(string $broadcasterId, array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CLIPS))
            ->withQuery($filters + ['broadcaster_id' => $broadcasterId, 'first' => 20]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Clips for a game, newest first.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Collection<Clip>>
     */
    public function forGame(string $gameId, array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CLIPS))
            ->withQuery($filters + ['game_id' => $gameId, 'first' => 20]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Specific clips by id (up to 100).
     *
     * @param list<string> $ids
     *
     * @return PromiseInterface<Collection<Clip>>
     */
    public function byIds(array $ids): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CLIPS))->addQuery('id', $ids))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * A single clip by id.
     *
     * @return PromiseInterface<Clip|null>
     */
    public function fetchById(string $id): PromiseInterface
    {
        return $this->byIds([$id])->then(static fn (Collection $c) => $c->first());
    }

    /**
     * Creates a clip from the broadcaster's running stream (`clips:edit`).
     * Returns `{ id, edit_url }` — the clip needs a few seconds before it can be
     * fetched.
     *
     * @return PromiseInterface<array{id: string, edit_url: string}>
     */
    public function create(string $broadcasterId, bool $hasDelay = false): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::CLIPS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'has_delay' => $hasDelay]))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }
}
