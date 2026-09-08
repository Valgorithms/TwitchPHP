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
 * The `extensions/*` and `users/extensions` resources — a user's installed
 * extensions and their active slots, an extension developer's own version
 * metadata, live channels running an extension, and Bits transactions.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-user-extensions
 *
 * @extends AbstractRepository<Part>
 */
class ExtensionRepository extends AbstractRepository
{
    protected string $part = Part::class;

    /** @var array<string, string> */
    protected array $endpoints = [];

    /**
     * Every extension the authenticated user has installed
     * (`user:read:broadcast` or `user:edit:broadcast`).
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function userExtensions(): PromiseInterface
    {
        return $this->twitch->request('GET', new Endpoint(Endpoint::USER_EXTENSIONS_LIST))
            ->then(fn (?array $body) => $this->rows($body));
    }

    /**
     * The user's active extension slots by type (`panel` / `overlay` /
     * `component`).
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function activeExtensions(?string $userId = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::USER_EXTENSIONS))->withQuery(['user_id' => $userId]))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Updates the user's active extension slots.
     *
     * @param array<string, mixed> $data The `data` payload from the API docs.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function updateActiveExtensions(array $data): PromiseInterface
    {
        return $this->twitch->request('PUT', Endpoint::USER_EXTENSIONS, ['data' => $data])
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Version metadata for an extension you develop
     * (requires an extension client id / JWT).
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function metadata(string $extensionId, ?string $version = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::EXTENSIONS))
            ->withQuery(['extension_id' => $extensionId, 'extension_version' => $version]))
            ->then(fn (?array $body) => $this->rows($body));
    }

    /**
     * Channels currently live with `$extensionId` active.
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function liveChannels(string $extensionId, int $first = 100, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::EXTENSION_LIVE_CHANNELS))
            ->withQuery(['extension_id' => $extensionId, 'first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => $this->rows($body));
    }

    /**
     * Bits-in-Extensions transactions for `$extensionId`.
     *
     * @param list<string>|string|null $transactionIds
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function transactions(string $extensionId, array|string|null $transactionIds = null, int $first = 100): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::EXTENSION_TRANSACTIONS))
            ->withQuery(['extension_id' => $extensionId, 'first' => $first])
            ->addQuery('id', (array) ($transactionIds ?? [])))
            ->then(fn (?array $body) => $this->rows($body));
    }
}
