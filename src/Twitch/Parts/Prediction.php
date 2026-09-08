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
 * A channel-points prediction (`GET/POST/PATCH /helix/predictions`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-predictions
 *
 * @property string $id
 * @property string $broadcaster_id
 * @property string $broadcaster_name
 * @property string $broadcaster_login
 * @property string $title
 * @property string $winning_outcome_id
 * @property list<array<string, mixed>> $outcomes
 * @property int    $prediction_window seconds
 * @property string $status  'ACTIVE' | 'CANCELED' | 'LOCKED' | 'RESOLVED'
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $ended_at
 * @property \Carbon\CarbonImmutable $locked_at
 */
final class Prediction extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_id', 'broadcaster_name', 'broadcaster_login', 'title',
        'winning_outcome_id', 'outcomes', 'prediction_window', 'status',
        'created_at', 'ended_at', 'locked_at',
    ];

    protected array $dates = ['created_at', 'ended_at', 'locked_at'];
}
