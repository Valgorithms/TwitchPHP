<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Auth;

/**
 * Signs the JSON Web Tokens an extension backend service (EBS) sends to the
 * Helix extension endpoints in place of an OAuth token.
 *
 * Twitch checks the signature against the extension's shared secret. Every
 * token names the extension's owner (`user_id`), claims the `external` role
 * and expires shortly; an endpoint may want more claims on top, which
 * {@see sign()} and {@see headers()} take as `$claims`. PubSub, for one,
 * wants `channel_id` and `pubsub_perms`.
 *
 * The secret is a signing key, so it never appears in debug output.
 *
 * @link https://dev.twitch.tv/docs/extensions/building/#signing-the-jwt
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class ExtensionJwt
{
    /** The decoded signing key. */
    private readonly string $key;

    /**
     * @param string $extensionId The extension's client id. It goes out as the `Client-Id` header and as `extension_id`.
     * @param string $secret      A shared secret, base64-encoded as the developer console and Get Extension Secrets give it.
     * @param string $ownerId     The user id of the extension's owner.
     * @param int    $ttl         How many seconds each token stays valid.
     *
     * @throws \InvalidArgumentException If the secret is not base64 or the lifetime is not positive.
     */
    public function __construct(
        public readonly string $extensionId,
        #[\SensitiveParameter]
        string $secret,
        public readonly string $ownerId,
        public readonly int $ttl = 60,
    ) {
        $key = base64_decode($secret, true);
        if ($key === false || $key === '') {
            throw new \InvalidArgumentException('The extension secret must be the base64-encoded string Twitch issued.');
        }
        if ($ttl < 1) {
            throw new \InvalidArgumentException('A token must stay valid for at least one second.');
        }

        $this->key = $key;
    }

    /**
     * A signed token carrying `exp`, `user_id` and `role` `external`, plus `$claims`.
     *
     * @param array<string, mixed> $claims Extra claims; they may also override the defaults.
     */
    public function sign(array $claims = []): string
    {
        $segments = [
            self::encode(['alg' => 'HS256', 'typ' => 'JWT']),
            self::encode($claims + [
                'exp' => time() + $this->ttl,
                'user_id' => $this->ownerId,
                'role' => 'external',
            ]),
        ];
        $segments[] = self::base64Url(hash_hmac('sha256', implode('.', $segments), $this->key, true));

        return implode('.', $segments);
    }

    /**
     * The headers that authenticate one request as the extension.
     *
     * @param array<string, mixed> $claims Extra claims for the token.
     *
     * @return array{Authorization: string, Client-Id: string}
     */
    public function headers(array $claims = []): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->sign($claims),
            'Client-Id' => $this->extensionId,
        ];
    }

    /** @return array{extensionId: string, ownerId: string, ttl: int} */
    public function __debugInfo(): array
    {
        return ['extensionId' => $this->extensionId, 'ownerId' => $this->ownerId, 'ttl' => $this->ttl];
    }

    /** @param array<string, mixed> $segment */
    private static function encode(array $segment): string
    {
        return self::base64Url(json_encode($segment, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
