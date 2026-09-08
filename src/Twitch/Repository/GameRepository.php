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
use Twitch\Parts\Game;

/**
 * The `games` resource — lookup by id / name / IGDB id, and the top-games chart.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-games
 *
 * @extends AbstractRepository<Game>
 */
class GameRepository extends AbstractRepository
{
    protected string $part = Game::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::GAMES];

    /** @param list<string> $ids @return PromiseInterface<Collection<Game>> */
    public function byIds(array $ids): PromiseInterface
    {
        return $this->lookup('id', $ids);
    }

    /** @param list<string> $names @return PromiseInterface<Collection<Game>> */
    public function byNames(array $names): PromiseInterface
    {
        return $this->lookup('name', $names);
    }

    /** @param list<string> $igdbIds @return PromiseInterface<Collection<Game>> */
    public function byIgdbIds(array $igdbIds): PromiseInterface
    {
        return $this->lookup('igdb_id', $igdbIds);
    }

    /** A single game by exact name. @return PromiseInterface<Game|null> */
    public function fetchByName(string $name): PromiseInterface
    {
        return $this->byNames([$name])->then(static fn (Collection $c) => $c->first());
    }

    /**
     * The most-watched games right now.
     *
     * @return PromiseInterface<Collection<Game>>
     */
    public function top(int $first = 20, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::TOP_GAMES))
            ->withQuery(['first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => new Collection(
                $this->factory->parts(Game::class, $this->rows($body)),
                'id',
                Game::class,
            ));
    }

    /**
     * @param list<string> $values
     *
     * @return PromiseInterface<Collection<Game>>
     */
    private function lookup(string $param, array $values): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::GAMES))->addQuery($param, $values))
            ->then(function (?array $body): Collection {
                $c = new Collection([], 'id', Game::class);
                foreach ($this->rows($body) as $row) {
                    $part = $this->factory->part(Game::class, $row, true);
                    $this->items->pushItem($part);
                    $c->pushItem($part);
                }

                return $c;
            });
    }
}
