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
use Twitch\Parts\Part;

/**
 * The channel-ads resource — start a commercial (`channel:edit:commercial`),
 * read the ad schedule and snooze the next mid-roll (`channel:manage:ads` /
 * `channel:read:ads`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#start-commercial
 *
 * @extends AbstractRepository<Part>
 */
class AdsRepository extends AbstractRepository
{
    protected string $part = Part::class;

    /** @var array<string, string> */
    protected array $endpoints = [];

    /**
     * Runs a commercial on the broadcaster's live stream. `$length` is seconds
     * (Twitch rounds to the nearest 30, max 180). Returns
     * `{ length, message, retry_after }`.
     *
     * @return PromiseInterface<array{length: int, message: string, retry_after: int}>
     */
    public function startCommercial(string $broadcasterId, int $length = 60): PromiseInterface
    {
        return $this->twitch->request('POST', Endpoint::COMMERCIAL, [
            'broadcaster_id' => $broadcasterId,
            'length' => $length,
        ])->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * The broadcaster's ad schedule — `{ snooze_count, snooze_refresh_at,
     * next_ad_at, duration, last_ad_at, preroll_free_time }`.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function schedule(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::AD_SCHEDULE))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Pushes the next scheduled mid-roll back by five minutes, spending one
     * snooze. Returns the updated `{ snooze_count, snooze_refresh_at,
     * next_ad_at }`.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function snooze(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::AD_SNOOZE))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }
}
