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
 * An EventSub conduit (`GET/POST/PATCH/DELETE /helix/eventsub/conduits`) — a
 * durable, shardable transport that many subscriptions can point at.
 *
 * @link https://dev.twitch.tv/docs/eventsub/handling-conduit-events/
 *
 * @property string $id
 * @property int    $shard_count
 */
final class Conduit extends Part
{
    protected array $fillable = ['id', 'shard_count'];

    protected array $fillableAfterSave = ['shard_count'];
}
