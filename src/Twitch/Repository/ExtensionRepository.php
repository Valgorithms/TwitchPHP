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
use Twitch\Auth\ExtensionJwt;
use Twitch\Http\Endpoint;
use Twitch\Parts\Part;

/**
 * The `extensions/*`, `bits/extensions` and `users/extensions` resources — a
 * user's installed extensions and their active slots, released extensions,
 * live channels running an extension and Bits transactions, plus what an
 * extension's backend does as the extension: configuration segments, PubSub,
 * chat, shared secrets and Bits products.
 *
 * Methods that take an {@see ExtensionJwt} authenticate with a token it signs
 * instead of the client's OAuth token. They skip the client's token recovery,
 * because a rejected JWT is not something refreshing an OAuth token can fix.
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
     * Version metadata for an extension you develop. Twitch requires a signed
     * JWT here; without `$jwt` the call goes out with the client's OAuth token,
     * as it did before the parameter existed.
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function metadata(string $extensionId, ?string $version = null, ?ExtensionJwt $jwt = null): PromiseInterface
    {
        $endpoint = (new Endpoint(Endpoint::EXTENSIONS))
            ->withQuery(['extension_id' => $extensionId, 'extension_version' => $version]);

        $request = $jwt === null
            ? $this->twitch->request('GET', $endpoint)
            : $this->twitch->getHttp()->request('GET', $endpoint, null, $jwt->headers());

        return $request->then(fn (?array $body) => $this->rows($body));
    }

    /**
     * A released extension's public listing: its views, icons, state and
     * support details. The latest release unless `$version` names one.
     *
     * @return PromiseInterface<array<string, mixed>|null>
     */
    public function released(string $extensionId, ?string $version = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::RELEASED_EXTENSIONS))
            ->withQuery(['extension_id' => $extensionId, 'extension_version' => $version]))
            ->then(fn (?array $body) => $this->rows($body)[0] ?? null);
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

    // ── Bits products (app access token) ──────────────────────────────

    /**
     * The extension's Bits products. The client needs an app access token
     * issued to the extension's own client id. Disabled and expired products
     * are left out unless `$includeAll`.
     *
     * @return PromiseInterface<list<array<string, mixed>>>
     */
    public function bitsProducts(bool $includeAll = false): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::EXTENSION_BITS_PRODUCTS))
            ->addQuery('should_include_all', $includeAll))
            ->then(fn (?array $body) => $this->rows($body));
    }

    /**
     * Adds a Bits product, or updates the one with the same `sku`. Same token
     * requirement as {@see bitsProducts()}.
     *
     * @param array<string, mixed> $product `sku`, `cost` (`{ amount, type: "bits" }`) and `display_name`,
     *                                      plus optional `in_development`, `expiration` and `is_broadcast`.
     *
     * @return PromiseInterface<array<string, mixed>> The product as saved.
     */
    public function saveBitsProduct(array $product): PromiseInterface
    {
        return $this->twitch->request('PUT', Endpoint::EXTENSION_BITS_PRODUCTS, $product)
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    // ── As the extension (signed JWT) ─────────────────────────────────

    /**
     * Configuration segments. `$segments` is any of `broadcaster`, `developer`
     * and `global`; the first two belong to one channel, named by
     * `$broadcasterId`.
     *
     * @param list<string>|string $segments
     *
     * @return PromiseInterface<list<array<string, mixed>>> `{ segment, broadcaster_id?, content, version }` rows.
     */
    public function configurationSegments(ExtensionJwt $jwt, array|string $segments, ?string $broadcasterId = null): PromiseInterface
    {
        return $this->twitch->getHttp()->request('GET', (new Endpoint(Endpoint::EXTENSION_CONFIGURATION))
            ->withQuery(['extension_id' => $jwt->extensionId, 'broadcaster_id' => $broadcasterId])
            ->addQuery('segment', (array) $segments), null, $jwt->headers())
            ->then(fn (?array $body) => $this->rows($body));
    }

    /**
     * Sets a configuration segment. A `broadcaster` or `developer` segment
     * needs `$broadcasterId`; `global` takes none.
     *
     * @return PromiseInterface<null>
     */
    public function setConfigurationSegment(ExtensionJwt $jwt, string $segment, ?string $broadcasterId = null, ?string $content = null, ?string $version = null): PromiseInterface
    {
        return $this->twitch->getHttp()->request('PUT', Endpoint::EXTENSION_CONFIGURATION, array_filter([
            'extension_id' => $jwt->extensionId,
            'segment' => $segment,
            'broadcaster_id' => $broadcasterId,
            'content' => $content,
            'version' => $version,
        ], static fn ($v) => $v !== null), $jwt->headers());
    }

    /**
     * Sets the `required_configuration` string a broadcaster's installation
     * must match before they can activate the extension.
     *
     * @return PromiseInterface<null>
     */
    public function setRequiredConfiguration(ExtensionJwt $jwt, string $broadcasterId, string $extensionVersion, string $requiredConfiguration): PromiseInterface
    {
        return $this->twitch->getHttp()->request('PUT', (new Endpoint(Endpoint::EXTENSION_REQUIRED_CONFIG))
            ->addQuery('broadcaster_id', $broadcasterId), [
                'extension_id' => $jwt->extensionId,
                'extension_version' => $extensionVersion,
                'required_configuration' => $requiredConfiguration,
            ], $jwt->headers());
    }

    /**
     * Sends a PubSub message to the extension's viewers on one channel.
     * `$targets` is `broadcast` (everyone watching) and/or `whisper-<user-id>`
     * (one viewer). The token is scoped to exactly those targets.
     *
     * @param list<string>|string $targets
     *
     * @return PromiseInterface<null>
     */
    public function sendPubSubMessage(ExtensionJwt $jwt, string $broadcasterId, string $message, array|string $targets = 'broadcast'): PromiseInterface
    {
        $targets = array_values((array) $targets);

        return $this->twitch->getHttp()->request('POST', Endpoint::EXTENSION_PUBSUB_MESSAGE, [
            'target' => $targets,
            'broadcaster_id' => $broadcasterId,
            'is_global_broadcast' => false,
            'message' => $message,
        ], $jwt->headers(['channel_id' => $broadcasterId, 'pubsub_perms' => ['send' => $targets]]));
    }

    /**
     * Sends a PubSub message to the extension's viewers on every channel where
     * it is active.
     *
     * @return PromiseInterface<null>
     */
    public function broadcastPubSubMessage(ExtensionJwt $jwt, string $message): PromiseInterface
    {
        return $this->twitch->getHttp()->request('POST', Endpoint::EXTENSION_PUBSUB_MESSAGE, [
            'target' => ['global'],
            'is_global_broadcast' => true,
            'message' => $message,
        ], $jwt->headers(['channel_id' => 'all', 'pubsub_perms' => ['send' => ['global']]]));
    }

    /**
     * Sends a message to a channel's chat, under the extension's name. Twitch
     * caps it at 280 characters, and 12 messages a minute per channel.
     *
     * @return PromiseInterface<null>
     */
    public function sendChatMessage(ExtensionJwt $jwt, string $broadcasterId, string $extensionVersion, string $text): PromiseInterface
    {
        return $this->twitch->getHttp()->request('POST', (new Endpoint(Endpoint::EXTENSION_CHAT_MESSAGE))
            ->addQuery('broadcaster_id', $broadcasterId), [
                'text' => $text,
                'extension_id' => $jwt->extensionId,
                'extension_version' => $extensionVersion,
            ], $jwt->headers());
    }

    /**
     * The extension's shared secrets.
     *
     * @return PromiseInterface<array<string, mixed>> `{ format_version, secrets: [{ content, active_at, expires_at }] }`
     */
    public function secrets(ExtensionJwt $jwt): PromiseInterface
    {
        return $this->twitch->getHttp()->request('GET', Endpoint::EXTENSION_SECRETS, null, $jwt->headers())
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }

    /**
     * Creates a shared secret, which retires the current ones. It becomes
     * active after `$delay` seconds (Twitch's minimum and default is 300),
     * time for every instance of the backend to switch over to it.
     *
     * @return PromiseInterface<array<string, mixed>> `{ format_version, secrets }`
     */
    public function createSecret(ExtensionJwt $jwt, ?int $delay = null): PromiseInterface
    {
        return $this->twitch->getHttp()->request('POST', (new Endpoint(Endpoint::EXTENSION_SECRETS))
            ->withQuery(['extension_id' => $jwt->extensionId, 'delay' => $delay]), null, $jwt->headers())
            ->then(fn (?array $body) => $this->rows($body)[0] ?? []);
    }
}
