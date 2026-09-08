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
use Twitch\Parts\DropsEntitlement;

/**
 * The `entitlements/drops` resource — list a game's / user's Drops entitlements
 * and mark them fulfilled. App or user token (a user token scopes the results
 * to that user).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-drops-entitlements
 *
 * @extends AbstractRepository<DropsEntitlement>
 */
class EntitlementRepository extends AbstractRepository
{
    protected string $part = DropsEntitlement::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::DROPS_ENTITLEMENTS];

    /**
     * Entitlements, filtered. Recognised keys: `user_id`, `game_id`,
     * `fulfillment_status` (`CLAIMED` | `FULFILLED`), `first`, `after`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Collection<DropsEntitlement>>
     */
    public function query(array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::DROPS_ENTITLEMENTS))
            ->withQuery($filters + ['first' => 100]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Sets the fulfillment status for up to 100 entitlement ids. `$status` is
     * `CLAIMED` or `FULFILLED`.
     *
     * @param list<string> $entitlementIds
     *
     * @return PromiseInterface<array<string, mixed>> `{ status, ids }[]` per Twitch's report.
     */
    public function updateStatus(array $entitlementIds, string $status): PromiseInterface
    {
        return $this->twitch->request('PATCH', Endpoint::DROPS_ENTITLEMENTS, [
            'entitlement_ids' => $entitlementIds,
            'fulfillment_status' => $status,
        ])->then(static fn (?array $body) => $body ?? []);
    }
}
