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
use Twitch\Parts\EventSubSubscription;

/**
 * The `eventsub/subscriptions` REST resource — the durable subscription list for
 * both webhook and WebSocket transports. The {@see \Twitch\EventSub\EventSub}
 * WebSocket client uses `subscribe()` for its own session; this repository is
 * for listing, auditing and deleting subscriptions out of band.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-eventsub-subscriptions
 *
 * @extends AbstractRepository<EventSubSubscription>
 */
class EventSubSubscriptionRepository extends AbstractRepository
{
    protected string $part = EventSubSubscription::class;

    /** @var array<string, string> */
    protected array $endpoints = [
        'all' => Endpoint::EVENTSUB_SUBSCRIPTIONS,
        'create' => Endpoint::EVENTSUB_SUBSCRIPTIONS,
        'delete' => Endpoint::EVENTSUB_SUBSCRIPTIONS,
    ];

    /**
     * Subscriptions for this Client-ID, optionally filtered by `status`, `type`
     * or `user_id` (mutually exclusive per the API). Follows pagination.
     *
     * @param array<string, string|null> $filter
     *
     * @return PromiseInterface<Collection<EventSubSubscription>>
     */
    public function list(array $filter = []): PromiseInterface
    {
        return $this->all($filter)->then(function (Collection $c) {
            foreach ($c as $part) {
                $this->items->pushItem($part);
            }

            return $c;
        });
    }

    /**
     * The current `total`, `total_cost` and `max_total_cost` for this Client-ID.
     *
     * @return PromiseInterface<array{total: int, total_cost: int, max_total_cost: int}>
     */
    public function cost(): PromiseInterface
    {
        return $this->twitch->request('GET', new Endpoint(Endpoint::EVENTSUB_SUBSCRIPTIONS))
            ->then(static fn (?array $body) => [
                'total' => (int) ($body['total'] ?? 0),
                'total_cost' => (int) ($body['total_cost'] ?? 0),
                'max_total_cost' => (int) ($body['max_total_cost'] ?? 0),
            ]);
    }

    /**
     * Creates a webhook subscription. For a WebSocket subscription use
     * {@see \Twitch\EventSub\EventSub::subscribe()} instead.
     *
     * @param array<string, mixed> $condition
     *
     * @return PromiseInterface<EventSubSubscription>
     */
    public function createWebhook(string $type, array $condition, string $callback, string $secret, string $version = '1'): PromiseInterface
    {
        return $this->twitch->request('POST', Endpoint::EVENTSUB_SUBSCRIPTIONS, [
            'type' => $type,
            'version' => $version,
            'condition' => $condition,
            'transport' => ['method' => 'webhook', 'callback' => $callback, 'secret' => $secret],
        ])->then(fn (?array $b) => $this->factory->part(EventSubSubscription::class, $this->rows($b)[0] ?? [], true));
    }

    /**
     * Deletes a subscription by id.
     *
     * @return PromiseInterface<null>
     */
    public function remove(string $subscriptionId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::EVENTSUB_SUBSCRIPTIONS))
            ->addQuery('id', $subscriptionId))
            ->then(function () use ($subscriptionId) {
                $this->items->offsetUnset($subscriptionId);

                return null;
            });
    }
}
