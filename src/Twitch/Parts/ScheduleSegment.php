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
 * One entry in a channel's stream schedule
 * (`POST/PATCH/DELETE /helix/schedule/segment`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#create-channel-stream-schedule-segment
 *
 * @property string $id
 * @property \Carbon\CarbonImmutable $start_time
 * @property \Carbon\CarbonImmutable $end_time
 * @property string $title
 * @property \Carbon\CarbonImmutable|null $canceled_until
 * @property array{id: string, name: string}|null $category
 * @property bool $is_recurring
 */
final class ScheduleSegment extends Part
{
    protected array $fillable = [
        'id', 'start_time', 'end_time', 'title', 'canceled_until', 'category', 'is_recurring',
    ];

    protected array $fillableAfterSave = [
        'start_time', 'timezone', 'duration', 'category_id', 'title', 'is_canceled', 'is_recurring',
    ];

    protected array $dates = ['start_time', 'end_time', 'canceled_until'];
}
