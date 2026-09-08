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
 * A channel's stream schedule (`GET /helix/schedule`) — a single object with a
 * list of {@see ScheduleSegment}-shaped rows and an optional vacation window.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-channel-stream-schedule
 *
 * @property string $broadcaster_id
 * @property string $broadcaster_name
 * @property string $broadcaster_login
 * @property list<array<string, mixed>> $segments
 * @property array{start_time: string, end_time: string}|null $vacation
 */
final class Schedule extends Part
{
    protected array $fillable = [
        'broadcaster_id', 'broadcaster_name', 'broadcaster_login', 'segments', 'vacation',
    ];

    /** @return list<ScheduleSegment> */
    public function segments(): array
    {
        return $this->factory()->parts(ScheduleSegment::class, (array) ($this->getAttribute('segments') ?? []));
    }
}
