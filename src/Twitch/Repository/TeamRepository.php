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
use Twitch\Parts\Team;

/**
 * The `teams` resource — a team by name or id (with its full member list), and
 * the teams a channel belongs to (without members).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-teams
 *
 * @extends AbstractRepository<Team>
 */
class TeamRepository extends AbstractRepository
{
    protected string $part = Team::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::TEAMS];

    /**
     * A team by its URL name, with members.
     *
     * @return PromiseInterface<Team|null>
     */
    public function fetchByName(string $name): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::TEAMS))->addQuery('name', $name))
            ->then(fn (?array $body) => $this->firstOrNull($body));
    }

    /**
     * A team by id, with members.
     *
     * @return PromiseInterface<Team|null>
     */
    public function fetchById(string $id): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::TEAMS))->addQuery('id', $id))
            ->then(fn (?array $body) => $this->firstOrNull($body));
    }

    /**
     * The teams a channel is a member of (summary rows — no member lists).
     *
     * @return PromiseInterface<Collection<Team>>
     */
    public function forChannel(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHANNEL_TEAMS))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function firstOrNull(?array $body): ?Team
    {
        $row = $this->rows($body)[0] ?? null;
        if ($row === null) {
            return null;
        }
        $part = $this->factory->part(Team::class, $row, true);
        $this->items->pushItem($part);

        return $part;
    }
}
