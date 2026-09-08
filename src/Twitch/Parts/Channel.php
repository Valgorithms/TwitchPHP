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
 * A channel's broadcast information (`GET /helix/channels`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-channel-information
 *
 * @property string        $broadcaster_id
 * @property string        $broadcaster_login
 * @property string        $broadcaster_name
 * @property string        $broadcaster_language
 * @property string        $game_id
 * @property string        $game_name
 * @property string        $title
 * @property int           $delay
 * @property list<string>  $tags
 * @property list<array<string, mixed>> $content_classification_labels
 * @property bool          $is_branded_content
 */
final class Channel extends Part
{
    protected array $fillable = [
        'broadcaster_id', 'broadcaster_login', 'broadcaster_name', 'broadcaster_language',
        'game_id', 'game_name', 'title', 'delay', 'tags',
        'content_classification_labels', 'is_branded_content',
    ];

    protected array $fillableAfterSave = [
        'game_id', 'title', 'broadcaster_language', 'delay', 'tags',
        'content_classification_labels', 'is_branded_content',
    ];

    protected string $discrim = 'broadcaster_id';
}
