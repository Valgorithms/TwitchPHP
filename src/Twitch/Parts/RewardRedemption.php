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
 * A redemption of a custom reward
 * (`GET/PATCH /helix/channel_points/custom_rewards/redemptions`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-custom-reward-redemption
 *
 * @property string $id
 * @property string $broadcaster_id
 * @property string $broadcaster_login
 * @property string $broadcaster_name
 * @property string $user_id
 * @property string $user_login
 * @property string $user_name
 * @property string $user_input
 * @property string $status   'UNFULFILLED' | 'FULFILLED' | 'CANCELED'
 * @property \Carbon\CarbonImmutable $redeemed_at
 * @property array{id: string, title: string, prompt: string, cost: int} $reward
 */
final class RewardRedemption extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_id', 'broadcaster_login', 'broadcaster_name', 'user_id',
        'user_login', 'user_name', 'user_input', 'status', 'redeemed_at', 'reward',
    ];

    protected array $fillableAfterSave = ['status'];

    protected array $dates = ['redeemed_at'];
}
