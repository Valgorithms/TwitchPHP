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
 * Somewhere durable to keep the OAuth token pair.
 *
 * Twitch rotates the refresh token on every refresh and invalidates the old
 * one, so a client that refreshes without persisting the result works until
 * the process exits and then locks itself out. {@see \Twitch\Twitch} writes
 * through to a store on every token change when one is configured.
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
interface TokenStoreInterface
{
    /**
     * The stored pair, or an empty array when nothing has been saved yet.
     *
     * @return array{access_token?: string, refresh_token?: string}
     */
    public function load(): array;

    /**
     * Persists a token payload as returned by any OAuth grant.
     *
     * Implementations must tolerate partial payloads: `refresh_token`,
     * `expires_in`, `scope` and `token_type` are all optional (an app access
     * token carries no refresh token at all).
     *
     * @param array{access_token: string, refresh_token?: string|null, expires_in?: int|null, scope?: list<string>|null, token_type?: string|null} $token
     */
    public function save(array $token): void;
}
