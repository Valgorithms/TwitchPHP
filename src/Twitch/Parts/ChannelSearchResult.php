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

use Carbon\CarbonImmutable;

/**
 * A hit from `GET /helix/search/channels` — a channel that matches the query,
 * live or not.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#search-channels
 *
 * @property string        $id
 * @property string        $broadcaster_login
 * @property string        $display_name
 * @property string        $broadcaster_language
 * @property string        $game_id
 * @property string        $game_name
 * @property bool          $is_live
 * @property list<string>  $tags
 * @property string        $thumbnail_url
 * @property string        $title
 * @property CarbonImmutable|null $started_at
 */
final class ChannelSearchResult extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_login', 'display_name', 'broadcaster_language', 'game_id',
        'game_name', 'is_live', 'tags', 'tag_ids', 'thumbnail_url', 'title', 'started_at',
    ];

    /** `started_at` comes back as an empty string when the channel is offline. */
    protected function setStartedAtAttribute(mixed $value): void
    {
        $this->setRawAttribute('started_at', ($value === '' || $value === null) ? null : CarbonImmutable::parse($value));
    }
}
