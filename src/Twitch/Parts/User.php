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
 * A Twitch user (`GET /helix/users`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-users
 *
 * @property string             $id
 * @property string             $login
 * @property string             $display_name
 * @property string             $type              '' | 'admin' | 'global_mod' | 'staff'
 * @property string             $broadcaster_type  '' | 'affiliate' | 'partner'
 * @property string             $description
 * @property string             $profile_image_url
 * @property string             $offline_image_url
 * @property string|null        $email             Only with the `user:read:email` scope.
 * @property \Carbon\CarbonImmutable $created_at
 */
final class User extends Part
{
    protected array $fillable = [
        'id', 'login', 'display_name', 'type', 'broadcaster_type', 'description',
        'profile_image_url', 'offline_image_url', 'view_count', 'email', 'created_at',
    ];

    protected array $fillableAfterSave = ['description'];

    protected array $dates = ['created_at'];
}
