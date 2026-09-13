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

use React\Promise\PromiseInterface;

/**
 * A strategy for obtaining a brand-new token when the existing grant can no
 * longer be recovered by refreshing.
 *
 * {@see \Twitch\Twitch} calls this as a last resort — after a refresh has
 * been tried and failed, or when there is no refresh token to try. Because
 * every real Twitch re-authorization flow needs a human at a browser, an
 * implementation is free to block for as long as that takes; the client
 * simply waits on the returned promise.
 *
 * Reject the promise to give up and let the original 401 surface.
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
interface ReauthorizerInterface
{
    /**
     * @return PromiseInterface<array{access_token: string, refresh_token?: string|null, expires_in?: int|null, scope?: list<string>|null, token_type?: string|null}>
     */
    public function reauthorize(): PromiseInterface;
}
