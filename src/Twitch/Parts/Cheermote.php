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
 * A Cheermote set (`GET /helix/bits/cheermotes`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-cheermotes
 *
 * @property string $prefix
 * @property list<array<string, mixed>> $tiers
 * @property string $type   'global_first_party' | 'global_third_party' | 'channel_custom' | 'display_only' | 'sponsored'
 * @property int    $order
 * @property \Carbon\CarbonImmutable $last_updated
 * @property bool   $is_charitable
 */
final class Cheermote extends Part
{
    protected string $discrim = 'prefix';

    protected array $fillable = ['prefix', 'tiers', 'type', 'order', 'last_updated', 'is_charitable'];

    protected array $dates = ['last_updated'];
}
