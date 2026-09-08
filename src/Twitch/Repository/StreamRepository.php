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
use Twitch\Parts\Stream;

/**
 * The `streams` resource — live streams, followed streams, the stream key, and
 * stream markers.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-streams
 *
 * @extends AbstractRepository<Stream>
 */
class StreamRepository extends AbstractRepository
{
    protected string $part = Stream::class;

    protected string $idParam = 'user_id';

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::STREAMS];

    /**
     * Live streams, filtered. Recognised keys: `user_id`, `user_login`,
     * `game_id`, `type`, `language` (each a string or a list), `first`, `before`,
     * `after`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Collection<Stream>>
     */
    public function live(array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::STREAMS))->withQuery($filters + ['first' => 20]))
            ->then(fn (?array $body) => $this->hydrate($body));
    }

    /**
     * The streams a user follows that are live (`user:read:follows`).
     *
     * @return PromiseInterface<Collection<Stream>>
     */
    public function followed(string $userId, int $first = 100): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::STREAMS_FOLLOWED))
            ->withQuery(['user_id' => $userId, 'first' => $first]))
            ->then(fn (?array $body) => $this->hydrate($body));
    }

    /**
     * The broadcaster's stream key (`channel:read:stream_key`).
     *
     * @return PromiseInterface<string>
     */
    public function key(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::STREAM_KEY))->addQuery('broadcaster_id', $broadcasterId))
            ->then(fn (?array $body) => $this->rows($body)[0]['stream_key'] ?? '');
    }

    /**
     * Stream markers for the given user or video (`user:read:broadcast`).
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function markers(?string $userId = null, ?string $videoId = null, int $first = 20): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::STREAM_MARKERS))->withQuery([
            'user_id' => $userId,
            'video_id' => $videoId,
            'first' => $first,
        ]))->then(static fn (?array $body) => $body ?? []);
    }

    /**
     * Adds a marker to the running stream (`channel:manage:broadcast`).
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function createMarker(string $userId, ?string $description = null): PromiseInterface
    {
        return $this->twitch->request('POST', Endpoint::STREAM_MARKERS, array_filter([
            'user_id' => $userId,
            'description' => $description,
        ], static fn ($v) => $v !== null))->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return Collection<Stream>
     */
    private function hydrate(?array $body): Collection
    {
        $c = new Collection([], 'id', Stream::class);
        foreach ($this->rows($body) as $row) {
            $part = $this->factory->part(Stream::class, $row, true);
            $this->items->pushItem($part);
            $c->pushItem($part);
        }

        return $c;
    }
}
