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
 * The `whispers` resource (`user:manage:whispers`) — send a whisper as the
 * authenticated user. Send-only; there is no read endpoint. The sender must
 * have a verified phone number and (for the first whisper to a user) they must
 * not have whispers blocked.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#send-whisper
 *
 * @extends AbstractRepository<Part>
 */
class WhisperRepository extends AbstractRepository
{
    protected string $part = Part::class;

    /** @var array<string, string> */
    protected array $endpoints = [];

    /**
     * Whispers `$message` from `$fromUserId` (the authenticated user) to
     * `$toUserId`. Messages are capped at 500 characters for a first whisper,
     * 10 000 thereafter.
     *
     * @return PromiseInterface<null>
     */
    public function send(string $fromUserId, string $toUserId, string $message): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::WHISPERS))->withQuery([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
        ]), ['message' => $message]);
    }
}
