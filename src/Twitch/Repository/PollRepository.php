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
use Twitch\Parts\Poll;

/**
 * The `polls` resource (`channel:read:polls` / `channel:manage:polls`) — list,
 * open and end a channel poll.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-polls
 *
 * @extends AbstractRepository<Poll>
 */
class PollRepository extends AbstractRepository
{
    protected string $part = Poll::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::POLLS];

    /**
     * Recent polls for a broadcaster (newest first).
     *
     * @return PromiseInterface<\Discord\Helpers\Collection<Poll>>
     */
    public function forBroadcaster(string $broadcasterId, int $first = 20, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::POLLS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Starts a poll. `$choices` is a list of 2-5 titles.
     *
     * @param list<string>         $choices
     * @param array<string, mixed> $options `duration` (15-1800, default 300),
     *                                      `channel_points_voting_enabled`,
     *                                      `channel_points_per_vote`.
     *
     * @return PromiseInterface<Poll>
     */
    public function open(string $broadcasterId, string $title, array $choices, array $options = []): PromiseInterface
    {
        $body = [
            'broadcaster_id' => $broadcasterId,
            'title' => $title,
            'choices' => array_map(static fn (string $c) => ['title' => $c], $choices),
            'duration' => $options['duration'] ?? 300,
        ];
        foreach (['channel_points_voting_enabled', 'channel_points_per_vote'] as $key) {
            if (isset($options[$key])) {
                $body[$key] = $options[$key];
            }
        }

        return $this->twitch->request('POST', Endpoint::POLLS, $body)
            ->then(fn (?array $b) => $this->factory->part(Poll::class, $this->rows($b)[0] ?? [], true));
    }

    /**
     * Ends a poll. `$status` is `TERMINATED` (show the result) or `ARCHIVED`
     * (hide it).
     *
     * @return PromiseInterface<Poll>
     */
    public function end(string $broadcasterId, string $pollId, string $status = 'TERMINATED'): PromiseInterface
    {
        return $this->twitch->request('PATCH', Endpoint::POLLS, [
            'broadcaster_id' => $broadcasterId,
            'id' => $pollId,
            'status' => $status,
        ])->then(fn (?array $b) => $this->factory->part(Poll::class, $this->rows($b)[0] ?? [], true));
    }
}
