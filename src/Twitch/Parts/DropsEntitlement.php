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
 * A Drops entitlement (`GET/PATCH /helix/entitlements/drops`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-drops-entitlements
 *
 * @property string $id
 * @property string $benefit_id
 * @property \Carbon\CarbonImmutable $timestamp
 * @property string $user_id
 * @property string $game_id
 * @property string $fulfillment_status  'CLAIMED' | 'FULFILLED'
 * @property \Carbon\CarbonImmutable $last_updated
 */
final class DropsEntitlement extends Part
{
    protected array $fillable = [
        'id', 'benefit_id', 'timestamp', 'user_id', 'game_id', 'fulfillment_status', 'last_updated',
    ];

    protected array $dates = ['timestamp', 'last_updated'];
}
