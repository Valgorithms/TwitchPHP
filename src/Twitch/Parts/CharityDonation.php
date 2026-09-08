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
 * A single donation to a charity campaign (`GET /helix/charity/donations`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-charity-campaign-donations
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $user_id
 * @property string $user_login
 * @property string $user_name
 * @property array{value: int, decimal_places: int, currency: string} $amount
 */
final class CharityDonation extends Part
{
    protected array $fillable = ['id', 'campaign_id', 'user_id', 'user_login', 'user_name', 'amount'];
}
