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
 * A running charity campaign (`GET /helix/charity/campaigns`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-charity-campaign
 *
 * @property string $id
 * @property string $broadcaster_id
 * @property string $broadcaster_login
 * @property string $broadcaster_name
 * @property string $charity_name
 * @property string $charity_description
 * @property string $charity_logo
 * @property string $charity_website
 * @property array{value: int, decimal_places: int, currency: string} $current_amount
 * @property array{value: int, decimal_places: int, currency: string} $target_amount
 */
final class CharityCampaign extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_id', 'broadcaster_login', 'broadcaster_name', 'charity_name',
        'charity_description', 'charity_logo', 'charity_website', 'current_amount', 'target_amount',
    ];
}
