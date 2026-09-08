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
 * A Twitch team (`GET /helix/teams`, `GET /helix/teams/channel`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-teams
 *
 * @property string $id
 * @property string $team_name
 * @property string $team_display_name
 * @property string $info
 * @property string $thumbnail_url
 * @property string $banner
 * @property string $background_image_url
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property list<array{user_id: string, user_login: string, user_name: string}> $users
 */
final class Team extends Part
{
    protected array $fillable = [
        'id', 'team_name', 'team_display_name', 'info', 'thumbnail_url', 'banner',
        'background_image_url', 'created_at', 'updated_at', 'users',
        'broadcaster_id', 'broadcaster_login', 'broadcaster_name',
    ];

    protected array $dates = ['created_at', 'updated_at'];
}
