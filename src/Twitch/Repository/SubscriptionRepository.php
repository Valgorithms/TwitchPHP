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
use Twitch\Http\Exceptions\NotFoundException;
use Twitch\Parts\Subscription;

/**
 * The `subscriptions` resource — a broadcaster's subscriber list
 * (`channel:read:subscriptions`) and the "does this user sub to that channel?"
 * check (`user:read:subscriptions`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-broadcaster-subscriptions
 *
 * @extends AbstractRepository<Subscription>
 */
class SubscriptionRepository extends AbstractRepository
{
    protected string $part = Subscription::class;

    protected string $discrim = 'user_id';

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::SUBSCRIPTIONS];

    /**
     * One page of a broadcaster's subscribers (newest first). The envelope also
     * carries `total` and `points`, returned via {@see summary()}.
     *
     * @return PromiseInterface<Collection<Subscription>>
     */
    public function forBroadcaster(string $broadcasterId, int $first = 100, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SUBSCRIPTIONS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * The subscriber `total` and sub-`points` for a broadcaster, without pulling
     * the whole list.
     *
     * @return PromiseInterface<array{total: int, points: int}>
     */
    public function summary(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SUBSCRIPTIONS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'first' => 1]))
            ->then(static fn (?array $body) => [
                'total' => (int) ($body['total'] ?? 0),
                'points' => (int) ($body['points'] ?? 0),
            ]);
    }

    /**
     * Whether `$userId` subscribes to `$broadcasterId`, and at what tier.
     * Resolves to `null` when there is no subscription.
     *
     * @return PromiseInterface<Subscription|null>
     */
    public function check(string $broadcasterId, string $userId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SUBSCRIPTION_USER))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'user_id' => $userId]))
            ->then(
                fn (?array $body) => ($row = $this->rows($body)[0] ?? null) === null
                    ? null
                    : $this->factory->part(Subscription::class, $row, true),
                static fn (\Throwable $e) => $e instanceof NotFoundException ? null : throw $e,
            );
    }
}
