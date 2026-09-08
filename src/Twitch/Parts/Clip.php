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
 * A clip (`GET /helix/clips`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-clips
 *
 * @property string $id
 * @property string $url
 * @property string $embed_url
 * @property string $broadcaster_id
 * @property string $broadcaster_name
 * @property string $creator_id
 * @property string $creator_name
 * @property string $video_id
 * @property string $game_id
 * @property string $language
 * @property string $title
 * @property int    $view_count
 * @property \Carbon\CarbonImmutable $created_at
 * @property string $thumbnail_url
 * @property float  $duration
 * @property int    $vod_offset
 * @property bool   $is_featured
 */
final class Clip extends Part
{
    protected array $fillable = [
        'id', 'url', 'embed_url', 'broadcaster_id', 'broadcaster_name', 'creator_id',
        'creator_name', 'video_id', 'game_id', 'language', 'title', 'view_count',
        'created_at', 'thumbnail_url', 'duration', 'vod_offset', 'is_featured',
    ];

    protected array $dates = ['created_at'];

    /** Builds the thumbnail URL at a concrete size. */
    public function thumbnail(int $width = 480, int $height = 272): string
    {
        return str_replace(['{width}', '{height}'], [(string) $width, (string) $height], (string) $this->getAttribute('thumbnail_url'));
    }
}
