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
 * A video / VOD (`GET /helix/videos`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-videos
 *
 * @property string $id
 * @property string $stream_id
 * @property string $user_id
 * @property string $user_login
 * @property string $user_name
 * @property string $title
 * @property string $description
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $published_at
 * @property string $url
 * @property string $thumbnail_url
 * @property string $viewable
 * @property int    $view_count
 * @property string $language
 * @property string $type       'archive' | 'highlight' | 'upload'
 * @property string $duration   e.g. '3h8m33s'
 * @property list<array<string, mixed>> $muted_segments
 */
final class Video extends Part
{
    protected array $fillable = [
        'id', 'stream_id', 'user_id', 'user_login', 'user_name', 'title', 'description',
        'created_at', 'published_at', 'url', 'thumbnail_url', 'viewable', 'view_count',
        'language', 'type', 'duration', 'muted_segments',
    ];

    protected array $dates = ['created_at', 'published_at'];

    /** Builds the thumbnail URL at a concrete size. */
    public function thumbnail(int $width = 320, int $height = 180): string
    {
        return str_replace(['%{width}', '%{height}'], [(string) $width, (string) $height], (string) $this->getAttribute('thumbnail_url'));
    }
}
