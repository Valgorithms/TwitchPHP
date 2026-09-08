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
 * A poll (`GET/POST/PATCH /helix/polls`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-polls
 *
 * @property string $id
 * @property string $broadcaster_id
 * @property string $broadcaster_name
 * @property string $broadcaster_login
 * @property string $title
 * @property list<array{id: string, title: string, votes: int, channel_points_votes: int, bits_votes: int}> $choices
 * @property bool   $bits_voting_enabled
 * @property int    $bits_per_vote
 * @property bool   $channel_points_voting_enabled
 * @property int    $channel_points_per_vote
 * @property string $status   'ACTIVE' | 'COMPLETED' | 'TERMINATED' | 'ARCHIVED' | 'MODERATED' | 'INVALID'
 * @property int    $duration seconds
 * @property \Carbon\CarbonImmutable $started_at
 * @property \Carbon\CarbonImmutable $ended_at
 */
final class Poll extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_id', 'broadcaster_name', 'broadcaster_login', 'title', 'choices',
        'bits_voting_enabled', 'bits_per_vote', 'channel_points_voting_enabled',
        'channel_points_per_vote', 'status', 'duration', 'started_at', 'ended_at',
    ];

    protected array $dates = ['started_at', 'ended_at'];
}
