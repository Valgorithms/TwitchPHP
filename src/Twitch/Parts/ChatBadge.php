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
 * A chat badge set and its versions (channel or global badges).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-channel-chat-badges
 *
 * @property string                     $set_id
 * @property list<array<string, mixed>> $versions
 */
final class ChatBadge extends Part
{
    protected array $fillable = ['set_id', 'versions'];

    protected string $discrim = 'set_id';
}
