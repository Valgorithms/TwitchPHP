<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Parts;

/**
 * A banned or timed-out user (`GET /helix/moderation/banned`,
 * response of `POST /helix/moderation/bans`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-banned-users
 *
 * @property string                      $user_id
 * @property string                      $user_login
 * @property string                      $user_name
 * @property \Carbon\CarbonImmutable|null $expires_at  Null for a permanent ban.
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property string                      $reason
 * @property string                      $moderator_id
 * @property string                      $moderator_login
 * @property string                      $moderator_name
 * @property string|null                 $broadcaster_id
 * @property string|null                 $end_time    Alias returned by POST bans.
 */
final class BannedUser extends Part
{
    protected array $fillable = [
        'user_id', 'user_login', 'user_name',
        'expires_at', 'created_at', 'end_time', 'reason',
        'moderator_id', 'moderator_login', 'moderator_name', 'broadcaster_id',
    ];

    protected array $dates = ['expires_at', 'created_at', 'end_time'];

    protected string $discrim = 'user_id';

    public function isPermanent(): bool
    {
        return $this->getAttribute('expires_at') === null && $this->getAttribute('end_time') === null;
    }
}
