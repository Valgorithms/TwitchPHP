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
use Twitch\Parts\Conduit;

/**
 * The `eventsub/conduits` resource — conduits and their shards. App-access-token
 * only. A conduit lets one durable transport back many EventSub subscriptions;
 * shards spread the delivery load.
 *
 * @link https://dev.twitch.tv/docs/eventsub/handling-conduit-events/
 *
 * @extends AbstractRepository<Conduit>
 */
class ConduitRepository extends AbstractRepository
{
    protected string $part = Conduit::class;

    /** @var array<string, string> */
    protected array $endpoints = [
        'all' => Endpoint::CONDUITS,
        'create' => Endpoint::CONDUITS,
        'update' => Endpoint::CONDUITS,
        'delete' => Endpoint::CONDUITS,
    ];

    /**
     * Every conduit owned by this Client-ID.
     *
     * @return PromiseInterface<Collection<Conduit>>
     */
    public function list(): PromiseInterface
    {
        return $this->twitch->request('GET', new Endpoint(Endpoint::CONDUITS))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Creates a conduit with `$shardCount` shards (1-20 000).
     *
     * @return PromiseInterface<Conduit>
     */
    public function create(int $shardCount): PromiseInterface
    {
        return $this->twitch->request('POST', Endpoint::CONDUITS, ['shard_count' => $shardCount])
            ->then(fn (?array $b) => $this->factory->part(Conduit::class, $this->rows($b)[0] ?? [], true));
    }

    /**
     * Resizes a conduit.
     *
     * @return PromiseInterface<Conduit>
     */
    public function resize(string $conduitId, int $shardCount): PromiseInterface
    {
        return $this->twitch->request('PATCH', Endpoint::CONDUITS, ['id' => $conduitId, 'shard_count' => $shardCount])
            ->then(fn (?array $b) => $this->factory->part(Conduit::class, $this->rows($b)[0] ?? [], true));
    }

    /**
     * Deletes a conduit (and drops every subscription bound to it).
     *
     * @return PromiseInterface<null>
     */
    public function remove(string $conduitId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::CONDUITS))->addQuery('id', $conduitId))
            ->then(function () use ($conduitId) {
                $this->items->offsetUnset($conduitId);

                return null;
            });
    }

    /**
     * A conduit's shards, optionally filtered by `$status`.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function shards(string $conduitId, ?string $status = null, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CONDUIT_SHARDS))->withQuery([
            'conduit_id' => $conduitId,
            'status' => $status,
            'after' => $after,
        ]))->then(static fn (?array $body) => $body ?? []);
    }

    /**
     * Assigns transports to shards. `$shards` is a list of
     * `{ id, transport: { method, callback?, secret?, session_id? } }`.
     *
     * @param list<array<string, mixed>> $shards
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function updateShards(string $conduitId, array $shards): PromiseInterface
    {
        return $this->twitch->request('PATCH', Endpoint::CONDUIT_SHARDS, [
            'conduit_id' => $conduitId,
            'shards' => $shards,
        ])->then(static fn (?array $body) => $body ?? []);
    }
}
