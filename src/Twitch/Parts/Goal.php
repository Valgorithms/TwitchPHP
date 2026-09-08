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
 * A creator goal (`GET /helix/goals`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-creator-goals
 *
 * @property string $id
 * @property string $broadcaster_id
 * @property string $broadcaster_name
 * @property string $broadcaster_login
 * @property string $type   'follower' | 'subscription' | 'subscription_count' | 'new_subscription' | 'new_subscription_count'
 * @property string $description
 * @property int    $current_amount
 * @property int    $target_amount
 * @property \Carbon\CarbonImmutable $created_at
 */
final class Goal extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_id', 'broadcaster_name', 'broadcaster_login', 'type',
        'description', 'current_amount', 'target_amount', 'created_at',
    ];

    protected array $dates = ['created_at'];
}
