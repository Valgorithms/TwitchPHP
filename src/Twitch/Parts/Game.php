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
 * A game / category (`GET /helix/games`, `GET /helix/games/top`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-games
 *
 * @property string $id
 * @property string $name
 * @property string $box_art_url
 * @property string $igdb_id
 */
final class Game extends Part
{
    protected array $fillable = ['id', 'name', 'box_art_url', 'igdb_id'];

    /** Builds the box-art URL at a concrete size. */
    public function boxArt(int $width = 285, int $height = 380): string
    {
        return str_replace(['{width}', '{height}'], [(string) $width, (string) $height], (string) $this->getAttribute('box_art_url'));
    }
}
