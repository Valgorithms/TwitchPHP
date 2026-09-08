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
use Twitch\Parts\Goal;

/**
 * The `goals` resource (`channel:read:goals`) — a broadcaster's active creator
 * goals (follower / subscriber / …). Read-only via the API.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-creator-goals
 *
 * @extends AbstractRepository<Goal>
 */
class GoalRepository extends AbstractRepository
{
    protected string $part = Goal::class;

    protected string $idParam = 'broadcaster_id';

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::GOALS];

    /**
     * The broadcaster's current goals (usually zero or one).
     *
     * @return PromiseInterface<Collection<Goal>>
     */
    public function forBroadcaster(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::GOALS))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(fn (?array $body) => $this->collectRows($body));
    }
}
