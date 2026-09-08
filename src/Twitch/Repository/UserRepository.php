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
use Twitch\Parts\User;

/**
 * The `users` resource — lookups by id / login, the authenticated user, the
 * description update, and the block list.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-users
 *
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    protected string $part = User::class;

    /** @var array<string, string> */
    protected array $endpoints = [
        'all'    => Endpoint::USERS,
        'update' => Endpoint::USERS,
    ];

    /**
     * The user for the current access token (no `id`/`login` filter).
     *
     * @return PromiseInterface<User>
     */
    public function me(): PromiseInterface
    {
        return $this->twitch->request('GET', Endpoint::USERS)
            ->then(fn (?array $body) => $this->factory->part(User::class, $this->rows($body)[0] ?? [], true));
    }

    /**
     * @param list<string> $ids Up to 100.
     *
     * @return PromiseInterface<Collection<User>>
     */
    public function byIds(array $ids): PromiseInterface
    {
        return $this->lookup('id', $ids);
    }

    /**
     * @param list<string> $logins Up to 100.
     *
     * @return PromiseInterface<Collection<User>>
     */
    public function byLogins(array $logins): PromiseInterface
    {
        return $this->lookup('login', array_map(static fn (string $l) => strtolower(ltrim($l, '@')), $logins));
    }

    /**
     * A single user by login name.
     *
     * @return PromiseInterface<User|null>
     */
    public function fetchByLogin(string $login): PromiseInterface
    {
        return $this->byLogins([$login])->then(static fn (Collection $c) => $c->first());
    }

    /**
     * Updates the authenticated user's channel description (`user:edit` scope).
     *
     * @return PromiseInterface<User>
     */
    public function updateDescription(string $description): PromiseInterface
    {
        return $this->twitch->request('PUT', (new Endpoint(Endpoint::USERS))->addQuery('description', $description))
            ->then(fn (?array $body) => $this->factory->part(User::class, $this->rows($body)[0] ?? [], true));
    }

    /**
     * The users blocked by the authenticated user (`user:read:blocked_users`).
     *
     * @return PromiseInterface<Collection<User>>
     */
    public function blocks(string $broadcasterId, int $first = 20): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::USER_BLOCKS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'first' => $first]))
            ->then(function (?array $body): Collection {
                $c = new Collection([], 'user_id', User::class);
                foreach ($this->rows($body) as $row) {
                    $c->pushItem($this->factory->part(User::class, $row, true));
                }

                return $c;
            });
    }

    /** Blocks a user (`user:manage:blocked_users`). @return PromiseInterface<null> */
    public function block(string $targetUserId, ?string $sourceContext = null, ?string $reason = null): PromiseInterface
    {
        return $this->twitch->request('PUT', (new Endpoint(Endpoint::USER_BLOCKS))->withQuery([
            'target_user_id' => $targetUserId,
            'source_context' => $sourceContext,
            'reason' => $reason,
        ]));
    }

    /** Unblocks a user. @return PromiseInterface<null> */
    public function unblock(string $targetUserId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::USER_BLOCKS))->addQuery('target_user_id', $targetUserId));
    }

    /**
     * @param list<string> $values
     *
     * @return PromiseInterface<Collection<User>>
     */
    private function lookup(string $param, array $values): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::USERS))->addQuery($param, $values))
            ->then(function (?array $body): Collection {
                $c = new Collection([], 'id', User::class);
                foreach ($this->rows($body) as $row) {
                    $part = $this->factory->part(User::class, $row, true);
                    $this->items->pushItem($part);
                    $c->pushItem($part);
                }

                return $c;
            });
    }
}
