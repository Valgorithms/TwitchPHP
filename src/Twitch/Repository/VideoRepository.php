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
use Twitch\Parts\Video;

/**
 * The `videos` resource — VODs / highlights / uploads by id, user or game, and
 * deletion.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-videos
 *
 * @extends AbstractRepository<Video>
 */
class VideoRepository extends AbstractRepository
{
    protected string $part = Video::class;

    /** @var array<string, string> */
    protected array $endpoints = [
        'all' => Endpoint::VIDEOS,
        'delete' => Endpoint::VIDEOS,
    ];

    /**
     * Videos for a user. Optional `type` (`all`|`archive`|`highlight`|`upload`),
     * `sort` (`time`|`trending`|`views`), `period`, `language`, `first`,
     * `before`, `after`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Collection<Video>>
     */
    public function forUser(string $userId, array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::VIDEOS))
            ->withQuery($filters + ['user_id' => $userId, 'first' => 20]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Videos for a game.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Collection<Video>>
     */
    public function forGame(string $gameId, array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::VIDEOS))
            ->withQuery($filters + ['game_id' => $gameId, 'first' => 20]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * @param list<string> $ids
     *
     * @return PromiseInterface<Collection<Video>>
     */
    public function byIds(array $ids): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::VIDEOS))->addQuery('id', $ids))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * A single video by id.
     *
     * @return PromiseInterface<Video|null>
     */
    public function fetchById(string $id): PromiseInterface
    {
        return $this->byIds([$id])->then(static fn (Collection $c) => $c->first());
    }

    /**
     * Deletes up to 5 of the authenticated user's videos
     * (`channel:manage:videos`).
     *
     * @param list<string> $ids
     *
     * @return PromiseInterface<list<string>> The ids Twitch reports as deleted.
     */
    public function deleteMany(array $ids): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::VIDEOS))->addQuery('id', $ids))
            ->then(function (?array $body) use ($ids): array {
                foreach ($ids as $id) {
                    $this->items->offsetUnset($id);
                }

                return $this->rows($body)[0]['data'] ?? $ids;
            });
    }
}
