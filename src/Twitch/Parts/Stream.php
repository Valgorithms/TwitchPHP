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
 * A live stream (`GET /helix/streams`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-streams
 *
 * @property string        $id
 * @property string        $user_id
 * @property string        $user_login
 * @property string        $user_name
 * @property string        $game_id
 * @property string        $game_name
 * @property string        $type          'live' | ''
 * @property string        $title
 * @property int           $viewer_count
 * @property \Carbon\CarbonImmutable $started_at
 * @property string        $language
 * @property string        $thumbnail_url
 * @property list<string>  $tags
 * @property bool          $is_mature
 */
final class Stream extends Part
{
    protected array $fillable = [
        'id', 'user_id', 'user_login', 'user_name', 'game_id', 'game_name', 'type',
        'title', 'viewer_count', 'started_at', 'language', 'thumbnail_url',
        'tag_ids', 'tags', 'is_mature',
    ];

    protected array $dates = ['started_at'];

    /** Builds the thumbnail URL at a concrete size. */
    public function thumbnail(int $width = 320, int $height = 180): string
    {
        return str_replace(['{width}', '{height}'], [(string) $width, (string) $height], (string) $this->getAttribute('thumbnail_url'));
    }
}
