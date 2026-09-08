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
 * A chat emote (channel, global, set, or user emotes).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-channel-emotes
 *
 * @property string                 $id
 * @property string                 $name
 * @property array<string, string>  $images
 * @property list<string>           $format
 * @property list<string>           $scale
 * @property list<string>           $theme_mode
 * @property string|null            $tier
 * @property string|null            $emote_type
 * @property string|null            $emote_set_id
 * @property string|null            $owner_id
 */
final class Emote extends Part
{
    protected array $fillable = [
        'id', 'name', 'images', 'format', 'scale', 'theme_mode',
        'tier', 'emote_type', 'emote_set_id', 'owner_id',
    ];
}
