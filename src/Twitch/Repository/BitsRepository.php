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
use Twitch\Parts\BitsLeaderboardEntry;
use Twitch\Parts\Cheermote;

/**
 * The `bits/*` resource — the Bits leaderboard (`bits:read`) and the Cheermote
 * catalogue (global + a channel's custom set).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-bits-leaderboard
 *
 * @extends AbstractRepository<BitsLeaderboardEntry>
 */
class BitsRepository extends AbstractRepository
{
    protected string $part = BitsLeaderboardEntry::class;

    protected string $discrim = 'user_id';

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::BITS_LEADERBOARD];

    /**
     * The Bits leaderboard for the authenticated broadcaster. `$period` is one
     * of `day`, `week`, `month`, `year`, `all`; `$startedAt` (RFC3339) anchors
     * the period; `$userId` narrows it to one user's rank.
     *
     * @return PromiseInterface<Collection<BitsLeaderboardEntry>>
     */
    public function leaderboard(int $count = 10, string $period = 'all', ?string $startedAt = null, ?string $userId = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::BITS_LEADERBOARD))->withQuery([
            'count' => $count,
            'period' => $period,
            'started_at' => $startedAt,
            'user_id' => $userId,
        ]))->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Cheermotes usable in chat — the global set, plus `$broadcasterId`'s custom
     * Cheermotes when given.
     *
     * @return PromiseInterface<Collection<Cheermote>>
     */
    public function cheermotes(?string $broadcasterId = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHEERMOTES))
            ->withQuery(['broadcaster_id' => $broadcasterId]))
            ->then(fn (?array $body) => $this->collectRows($body, Cheermote::class, 'prefix'));
    }
}
