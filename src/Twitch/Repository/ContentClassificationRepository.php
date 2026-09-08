<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Repository;

use Discord\Helpers\Collection;
use React\Promise\PromiseInterface;
use Twitch\Http\Endpoint;
use Twitch\Parts\ContentClassificationLabel;

/**
 * The `content_classification_labels` resource — the CCL catalogue, localised.
 * No scope required. Read-only; a channel's applied labels are set through
 * {@see ChannelRepository::modify()} with `content_classification_labels`.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-content-classification-labels
 *
 * @extends AbstractRepository<ContentClassificationLabel>
 */
class ContentClassificationRepository extends AbstractRepository
{
    protected string $part = ContentClassificationLabel::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::CCLS];

    /**
     * The full CCL catalogue for `$locale` (BCP-47, e.g. `en-US`, `de-DE`).
     *
     * @return PromiseInterface<Collection<ContentClassificationLabel>>
     */
    public function catalogue(string $locale = 'en-US'): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CCLS))->addQuery('locale', $locale))
            ->then(fn (?array $body) => $this->collectRows($body));
    }
}
