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
 * One row of the Bits leaderboard (`GET /helix/bits/leaderboard`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-bits-leaderboard
 *
 * @property string $user_id
 * @property string $user_login
 * @property string $user_name
 * @property int    $rank
 * @property int    $score
 */
final class BitsLeaderboardEntry extends Part
{
    protected string $discrim = 'user_id';

    protected array $fillable = ['user_id', 'user_login', 'user_name', 'rank', 'score'];
}
