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
 * An AutoMod blocked term (`GET`/`POST`/`DELETE /helix/moderation/blocked_terms`).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-blocked-terms
 *
 * @property string                      $id
 * @property string                      $broadcaster_id
 * @property string                      $moderator_id
 * @property string                      $text
 * @property \Carbon\CarbonImmutable      $created_at
 * @property \Carbon\CarbonImmutable      $updated_at
 * @property \Carbon\CarbonImmutable|null $expires_at
 */
final class BlockedTerm extends Part
{
    protected array $fillable = [
        'id', 'broadcaster_id', 'moderator_id', 'text',
        'created_at', 'updated_at', 'expires_at',
    ];

    protected array $fillableAfterSave = ['text'];

    protected array $dates = ['created_at', 'updated_at', 'expires_at'];
}
