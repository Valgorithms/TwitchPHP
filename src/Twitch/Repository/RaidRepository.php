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
 * The `raids` resource (`channel:manage:raids`) — start or cancel a raid from
 * the broadcaster's channel to another. There is no list endpoint, so this
 * repository is action-only.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#start-a-raid
 *
 * @extends AbstractRepository<Part>
 */
class RaidRepository extends AbstractRepository
{
    protected string $part = Part::class;

    /** @var array<string, string> */
    protected array $endpoints = [];

    /**
     * Sends the broadcaster's viewers to `$toBroadcasterId`. Returns
     * `{ created_at, is_mature }` — the raid still needs the broadcaster to
     * confirm on-stream within 90 seconds.
     *
     * @return PromiseInterface<array{created_at: string, is_mature: bool}>
     */
    public function start(string $fromBroadcasterId, string $toBroadcasterId): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::RAIDS))->withQuery([
            'from_broadcaster_id' => $fromBroadcasterId,
            'to_broadcaster_id' => $toBroadcasterId,
        ]))->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Cancels a pending raid.
     *
     * @return PromiseInterface<null>
     */
    public function cancel(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::RAIDS))
            ->addQuery('broadcaster_id', $broadcasterId));
    }
}
