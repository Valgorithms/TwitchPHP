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
 * A channel's chat settings (`GET`/`PATCH /helix/chat/settings`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-chat-settings
 *
 * @property string    $broadcaster_id
 * @property string    $moderator_id
 * @property bool      $slow_mode
 * @property int|null  $slow_mode_wait_time
 * @property bool      $follower_mode
 * @property int|null  $follower_mode_duration
 * @property bool      $subscriber_mode
 * @property bool      $emote_mode
 * @property bool      $unique_chat_mode
 * @property bool      $non_moderator_chat_delay
 * @property int|null  $non_moderator_chat_delay_duration
 */
final class ChatSettings extends Part
{
    protected array $fillable = [
        'broadcaster_id', 'moderator_id',
        'slow_mode', 'slow_mode_wait_time',
        'follower_mode', 'follower_mode_duration',
        'subscriber_mode', 'emote_mode', 'unique_chat_mode',
        'non_moderator_chat_delay', 'non_moderator_chat_delay_duration',
    ];

    protected array $fillableAfterSave = [
        'slow_mode', 'slow_mode_wait_time',
        'follower_mode', 'follower_mode_duration',
        'subscriber_mode', 'emote_mode', 'unique_chat_mode',
        'non_moderator_chat_delay', 'non_moderator_chat_delay_duration',
    ];

    protected string $discrim = 'broadcaster_id';
}
