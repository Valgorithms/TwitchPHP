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

use React\Promise\PromiseInterface;
use Twitch\Http\Endpoint;
use Twitch\Parts\Part;

/**
 * The `analytics/*` resource — signed download URLs for extension and game
 * analytics CSV reports (`analytics:read:extensions` / `analytics:read:games`).
 * Report-only, so this repository is action-only.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-extension-analytics
 *
 * @extends AbstractRepository<Part>
 */
class AnalyticsRepository extends AbstractRepository
{
    protected string $part = Part::class;

    /** @var array<string, string> */
    protected array $endpoints = [];

    /**
     * Extension analytics report URLs. Optional `extension_id`, `type`
     * (`overview_v2`), `started_at` / `ended_at` (RFC3339), `first`, `after`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function extensions(array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::EXTENSION_ANALYTICS))->withQuery($filters))
            ->then(static fn (?array $body) => $body ?? []);
    }

    /**
     * Game analytics report URLs. Optional `game_id`, `type`, `started_at` /
     * `ended_at`, `first`, `after`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    public function games(array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::GAME_ANALYTICS))->withQuery($filters))
            ->then(static fn (?array $body) => $body ?? []);
    }
}
