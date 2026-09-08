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
use Twitch\Parts\ChannelSearchResult;
use Twitch\Parts\Game;

/**
 * The `search/*` resource — search categories (games) and channels by name. No
 * scope required.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#search-categories
 *
 * @extends AbstractRepository<ChannelSearchResult>
 */
class SearchRepository extends AbstractRepository
{
    protected string $part = ChannelSearchResult::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::SEARCH_CHANNELS];

    /**
     * Categories (games) whose name matches `$query`.
     *
     * @return PromiseInterface<Collection<Game>>
     */
    public function categories(string $query, int $first = 20, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SEARCH_CATEGORIES))
            ->withQuery(['query' => $query, 'first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => new Collection(
                $this->factory->parts(Game::class, $this->rows($body)),
                'id',
                Game::class,
            ));
    }

    /**
     * Channels whose name matches `$query`. `$liveOnly` restricts to channels
     * that are streaming right now.
     *
     * @return PromiseInterface<Collection<ChannelSearchResult>>
     */
    public function channels(string $query, bool $liveOnly = false, int $first = 20, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SEARCH_CHANNELS))->withQuery([
            'query' => $query,
            'live_only' => $liveOnly,
            'first' => $first,
            'after' => $after,
        ]))->then(fn (?array $body) => $this->collectRows($body));
    }
}
