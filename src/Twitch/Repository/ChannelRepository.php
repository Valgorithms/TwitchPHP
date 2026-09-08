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
use Twitch\Parts\Channel;

/**
 * The `channels` resource — broadcast info, the modify call, editors, and the
 * followers / followed lists.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-channel-information
 *
 * @extends AbstractRepository<Channel>
 */
class ChannelRepository extends AbstractRepository
{
    protected string $part = Channel::class;

    protected string $discrim = 'broadcaster_id';

    protected string $idParam = 'broadcaster_id';

    /** @var array<string, string> */
    protected array $endpoints = [
        'all'    => Endpoint::CHANNELS,
        'update' => Endpoint::CHANNELS,
    ];

    /**
     * @param list<string> $broadcasterIds Up to 100.
     *
     * @return PromiseInterface<Collection<Channel>>
     */
    public function getMany(array $broadcasterIds): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHANNELS))->addQuery('broadcaster_id', $broadcasterIds))
            ->then(function (?array $body): Collection {
                $c = new Collection([], 'broadcaster_id', Channel::class);
                foreach ($this->rows($body) as $row) {
                    $part = $this->factory->part(Channel::class, $row, true);
                    $this->items->pushItem($part);
                    $c->pushItem($part);
                }

                return $c;
            });
    }

    /**
     * Updates the channel (`channel:manage:broadcast`). Accepts any of
     * `game_id`, `title`, `broadcaster_language`, `delay`, `tags`,
     * `content_classification_labels`, `is_branded_content`.
     *
     * @param array<string, mixed> $fields
     *
     * @return PromiseInterface<null>
     */
    public function modify(string $broadcasterId, array $fields): PromiseInterface
    {
        return $this->twitch->request(
            'PATCH',
            (new Endpoint(Endpoint::CHANNELS))->addQuery('broadcaster_id', $broadcasterId),
            $fields,
        );
    }

    /**
     * The users with editor permission on a channel (`channel:read:editors`).
     *
     * @return PromiseInterface<Collection<array<string, mixed>>>
     */
    public function editors(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHANNEL_EDITORS))->addQuery('broadcaster_id', $broadcasterId))
            ->then(fn (?array $body) => new Collection($this->rows($body), 'user_id'));
    }

    /**
     * A channel's followers, or a single follow relationship when `$userId`
     * is given (`moderator:read:followers`, or the broadcaster's own token).
     *
     * @return PromiseInterface<array<string, mixed>> `{ total, data, pagination }`
     */
    public function followers(string $broadcasterId, ?string $userId = null, int $first = 20, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHANNEL_FOLLOWERS))->withQuery([
            'broadcaster_id' => $broadcasterId,
            'user_id' => $userId,
            'first' => $first,
            'after' => $after,
        ]))->then(static fn (?array $body) => $body ?? []);
    }

    /**
     * The channels a user follows (`user:read:follows`).
     *
     * @return PromiseInterface<array<string, mixed>> `{ total, data, pagination }`
     */
    public function followed(string $userId, ?string $broadcasterId = null, int $first = 20, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHANNELS_FOLLOWED))->withQuery([
            'user_id' => $userId,
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
            'after' => $after,
        ]))->then(static fn (?array $body) => $body ?? []);
    }
}
