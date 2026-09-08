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
 * An EventSub subscription record (`GET/POST/DELETE /helix/eventsub/subscriptions`).
 * Covers both WebSocket and webhook transports.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-eventsub-subscriptions
 *
 * @property string $id
 * @property string $status
 * @property string $type
 * @property string $version
 * @property array<string, string> $condition
 * @property \Carbon\CarbonImmutable $created_at
 * @property array{method: string, callback?: string, session_id?: string, connected_at?: string} $transport
 * @property int $cost
 */
final class EventSubSubscription extends Part
{
    protected array $fillable = [
        'id', 'status', 'type', 'version', 'condition', 'created_at', 'transport', 'cost',
    ];

    protected array $dates = ['created_at'];
}
