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
 * A channel-points custom reward (`GET/POST/PATCH/DELETE /helix/channel_points/custom_rewards`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-custom-reward
 *
 * @property string $id
 * @property string $broadcaster_id
 * @property string $broadcaster_login
 * @property string $broadcaster_name
 * @property string $title
 * @property string $prompt
 * @property int    $cost
 * @property array{background_color?: string} $image
 * @property array<string, string> $default_image
 * @property string $background_color
 * @property bool   $is_enabled
 * @property bool   $is_user_input_required
 * @property array{is_enabled: bool, max_per_stream: int} $max_per_stream_setting
 * @property array{is_enabled: bool, max_per_user_per_stream: int} $max_per_user_per_stream_setting
 * @property array{is_enabled: bool, global_cooldown_seconds: int} $global_cooldown_setting
 * @property bool   $is_paused
 * @property bool   $is_in_stock
 * @property bool   $should_redemptions_skip_request_queue
 * @property int    $redemptions_redeemed_current_stream
 * @property \Carbon\CarbonImmutable $cooldown_expires_at
 */
final class CustomReward extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_id', 'broadcaster_login', 'broadcaster_name', 'title', 'prompt',
        'cost', 'image', 'default_image', 'background_color', 'is_enabled',
        'is_user_input_required', 'max_per_stream_setting', 'max_per_user_per_stream_setting',
        'global_cooldown_setting', 'is_paused', 'is_in_stock',
        'should_redemptions_skip_request_queue', 'redemptions_redeemed_current_stream',
        'cooldown_expires_at',
    ];

    protected array $fillableAfterSave = [
        'title', 'prompt', 'cost', 'background_color', 'is_enabled', 'is_user_input_required',
        'is_max_per_stream_enabled', 'max_per_stream', 'is_max_per_user_per_stream_enabled',
        'max_per_user_per_stream', 'is_global_cooldown_enabled', 'global_cooldown_seconds',
        'is_paused', 'should_redemptions_skip_request_queue',
    ];

    protected array $dates = ['cooldown_expires_at'];
}
