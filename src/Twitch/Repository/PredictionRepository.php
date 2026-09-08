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
use Twitch\Parts\Prediction;

/**
 * The `predictions` resource (`channel:read:predictions` /
 * `channel:manage:predictions`) — list, open, lock and resolve a channel-points
 * prediction.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-predictions
 *
 * @extends AbstractRepository<Prediction>
 */
class PredictionRepository extends AbstractRepository
{
    protected string $part = Prediction::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::PREDICTIONS];

    /**
     * Recent predictions for a broadcaster (newest first).
     *
     * @return PromiseInterface<\Discord\Helpers\Collection<Prediction>>
     */
    public function forBroadcaster(string $broadcasterId, int $first = 20, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::PREDICTIONS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => $this->collectRows($body));
    }

    /**
     * Opens a prediction. `$outcomes` is a list of 2-10 titles.
     *
     * @param list<string> $outcomes
     *
     * @return PromiseInterface<Prediction>
     */
    public function open(string $broadcasterId, string $title, array $outcomes, int $predictionWindow = 120): PromiseInterface
    {
        return $this->twitch->request('POST', Endpoint::PREDICTIONS, [
            'broadcaster_id' => $broadcasterId,
            'title' => $title,
            'outcomes' => array_map(static fn (string $o) => ['title' => $o], $outcomes),
            'prediction_window' => $predictionWindow,
        ])->then(fn (?array $b) => $this->factory->part(Prediction::class, $this->rows($b)[0] ?? [], true));
    }

    /**
     * Locks a prediction — no more entries, awaiting a resolution.
     *
     * @return PromiseInterface<Prediction>
     */
    public function lock(string $broadcasterId, string $predictionId): PromiseInterface
    {
        return $this->patch($broadcasterId, $predictionId, 'LOCKED');
    }

    /**
     * Resolves a prediction, paying out `$winningOutcomeId`.
     *
     * @return PromiseInterface<Prediction>
     */
    public function resolve(string $broadcasterId, string $predictionId, string $winningOutcomeId): PromiseInterface
    {
        return $this->patch($broadcasterId, $predictionId, 'RESOLVED', $winningOutcomeId);
    }

    /**
     * Cancels a prediction and refunds every entrant.
     *
     * @return PromiseInterface<Prediction>
     */
    public function cancel(string $broadcasterId, string $predictionId): PromiseInterface
    {
        return $this->patch($broadcasterId, $predictionId, 'CANCELED');
    }

    /**
     * @return PromiseInterface<Prediction>
     */
    private function patch(string $broadcasterId, string $predictionId, string $status, ?string $winningOutcomeId = null): PromiseInterface
    {
        $body = ['broadcaster_id' => $broadcasterId, 'id' => $predictionId, 'status' => $status];
        if ($winningOutcomeId !== null) {
            $body['winning_outcome_id'] = $winningOutcomeId;
        }

        return $this->twitch->request('PATCH', Endpoint::PREDICTIONS, $body)
            ->then(fn (?array $b) => $this->factory->part(Prediction::class, $this->rows($b)[0] ?? [], true));
    }
}
