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
use Twitch\Parts\CustomReward;
use Twitch\Parts\RewardRedemption;

/**
 * The Channel Points resource — custom rewards
 * (`channel:read:redemptions` / `channel:manage:redemptions`) and their
 * redemption queue. Only rewards this Client-ID created are manageable.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-custom-reward
 *
 * @extends AbstractRepository<CustomReward>
 */
class ChannelPointsRepository extends AbstractRepository
{
    protected string $part = CustomReward::class;

    /** @var array<string, string> */
    protected array $endpoints = [
        'all' => Endpoint::CUSTOM_REWARDS,
        'create' => Endpoint::CUSTOM_REWARDS,
        'update' => Endpoint::CUSTOM_REWARDS,
        'delete' => Endpoint::CUSTOM_REWARDS,
    ];

    /**
     * The broadcaster's custom rewards. `$onlyManageable` limits the list to
     * rewards this Client-ID created.
     *
     * @return PromiseInterface<Collection<CustomReward>>
     */
    public function forBroadcaster(string $broadcasterId, bool $onlyManageable = false): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CUSTOM_REWARDS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'only_manageable_rewards' => $onlyManageable]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Creates a custom reward. `$fields` mirrors the API body — at minimum
     * `title` and `cost`.
     *
     * @param array<string, mixed> $fields
     *
     * @return PromiseInterface<CustomReward>
     */
    public function add(string $broadcasterId, array $fields): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::CUSTOM_REWARDS))
            ->addQuery('broadcaster_id', $broadcasterId), $fields)
            ->then(fn (?array $b) => $this->factory->part(CustomReward::class, $this->rows($b)[0] ?? [], true));
    }

    /**
     * Updates a custom reward this Client-ID owns.
     *
     * @param array<string, mixed> $fields
     *
     * @return PromiseInterface<CustomReward>
     */
    public function modify(string $broadcasterId, string $rewardId, array $fields): PromiseInterface
    {
        return $this->twitch->request('PATCH', (new Endpoint(Endpoint::CUSTOM_REWARDS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'id' => $rewardId]), $fields)
            ->then(fn (?array $b) => $this->factory->part(CustomReward::class, $this->rows($b)[0] ?? [], true));
    }

    /**
     * Deletes a custom reward this Client-ID owns.
     *
     * @return PromiseInterface<null>
     */
    public function remove(string $broadcasterId, string $rewardId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::CUSTOM_REWARDS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'id' => $rewardId]))
            ->then(function () use ($rewardId) {
                $this->items->offsetUnset($rewardId);

                return null;
            });
    }

    /**
     * Redemptions for one reward, filtered by `$status`
     * (`UNFULFILLED` | `FULFILLED` | `CANCELED`). Optional `sort` (`OLDEST` |
     * `NEWEST`), `first`, `after`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Collection<RewardRedemption>>
     */
    public function redemptions(string $broadcasterId, string $rewardId, string $status = 'UNFULFILLED', array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CUSTOM_REWARD_REDEMPTIONS))
            ->withQuery($filters + [
                'broadcaster_id' => $broadcasterId,
                'reward_id' => $rewardId,
                'status' => $status,
                'first' => 50,
            ]))
            ->then(fn (?array $body) => $this->collectRows($body, RewardRedemption::class, 'id'));
    }

    /**
     * Marks redemptions `FULFILLED` or `CANCELED` (a cancel refunds the points).
     *
     * @param list<string> $redemptionIds
     *
     * @return PromiseInterface<Collection<RewardRedemption>>
     */
    public function resolveRedemptions(string $broadcasterId, string $rewardId, array $redemptionIds, string $status): PromiseInterface
    {
        return $this->twitch->request('PATCH', (new Endpoint(Endpoint::CUSTOM_REWARD_REDEMPTIONS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'reward_id' => $rewardId])
            ->addQuery('id', $redemptionIds), ['status' => $status])
            ->then(fn (?array $body) => $this->collectRows($body, RewardRedemption::class, 'id'));
    }
}
