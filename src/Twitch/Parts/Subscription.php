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
 * A broadcaster subscription (`GET /helix/subscriptions`) or the authenticated
 * user's subscription to a channel (`GET /helix/subscriptions/user`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-broadcaster-subscriptions
 *
 * @property string $broadcaster_id
 * @property string $broadcaster_login
 * @property string $broadcaster_name
 * @property string $gifter_id
 * @property string $gifter_login
 * @property string $gifter_name
 * @property bool   $is_gift
 * @property string $tier    '1000' | '2000' | '3000'
 * @property string $plan_name
 * @property string $user_id
 * @property string $user_login
 * @property string $user_name
 */
final class Subscription extends Part
{
    protected array $fillable = [
        'broadcaster_id', 'broadcaster_login', 'broadcaster_name', 'gifter_id',
        'gifter_login', 'gifter_name', 'is_gift', 'tier', 'plan_name',
        'user_id', 'user_login', 'user_name',
    ];
}
