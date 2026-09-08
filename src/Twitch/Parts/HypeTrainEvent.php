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
 * A Hype Train event (`GET /helix/hypetrain/events`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-hype-train-events
 *
 * @property string $id
 * @property string $event_type
 * @property \Carbon\CarbonImmutable $event_timestamp
 * @property string $version
 * @property array<string, mixed> $event_data
 */
final class HypeTrainEvent extends Part
{
    protected array $fillable = ['id', 'event_type', 'event_timestamp', 'version', 'event_data'];

    protected array $dates = ['event_timestamp'];
}
