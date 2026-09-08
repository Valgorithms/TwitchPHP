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
 * A Content Classification Label
 * (`GET /helix/content_classification_labels`) — the tags a broadcaster can
 * apply to a stream (`DrugsIntoxication`, `Gambling`, …).
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-content-classification-labels
 *
 * @property string $id
 * @property string $name
 * @property string $description
 */
final class ContentClassificationLabel extends Part
{
    protected array $fillable = ['id', 'name', 'description'];
}
