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
 * A channel moderator or VIP (`GET /helix/moderation/moderators`,
 * `GET /helix/channels/vips`) — both share this shape.
 *
 * @property string $user_id
 * @property string $user_login
 * @property string $user_name
 */
final class Moderator extends Part
{
    protected array $fillable = ['user_id', 'user_login', 'user_name'];

    protected string $discrim = 'user_id';
}
