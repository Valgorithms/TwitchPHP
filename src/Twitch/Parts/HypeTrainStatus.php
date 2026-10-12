<?php

namespace Twitch\Parts;

/**
 * The current Hype Train and channel records returned by hypetrain/status.
 *
 * @property string $broadcaster_id
 * @property array<string, mixed>|null $current
 * @property array<string, mixed>|null $all_time_high
 * @property array<string, mixed>|null $shared_all_time_high
 */
final class HypeTrainStatus extends Part
{
    protected array $fillable = ['broadcaster_id', 'current', 'all_time_high', 'shared_all_time_high'];
}
