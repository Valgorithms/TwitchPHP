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
use Twitch\Parts\CharityCampaign;
use Twitch\Parts\CharityDonation;

/**
 * The `charity/*` resource (`channel:read:charity`) — the broadcaster's active
 * charity campaign and its donations.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-charity-campaign
 *
 * @extends AbstractRepository<CharityCampaign>
 */
class CharityRepository extends AbstractRepository
{
    protected string $part = CharityCampaign::class;

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::CHARITY_CAMPAIGN];

    /**
     * The broadcaster's currently running charity campaign, or `null` when
     * there isn't one.
     *
     * @return PromiseInterface<CharityCampaign|null>
     */
    public function campaign(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHARITY_CAMPAIGN))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(function (?array $body): ?CharityCampaign {
                $row = $this->rows($body)[0] ?? null;
                if ($row === null) {
                    return null;
                }
                $part = $this->factory->part(CharityCampaign::class, $row, true);
                $this->items->pushItem($part);

                return $part;
            });
    }

    /**
     * Donations to the running campaign (paginated).
     *
     * @return PromiseInterface<Collection<CharityDonation>>
     */
    public function donations(string $broadcasterId, int $first = 100, ?string $after = null): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::CHARITY_DONATIONS))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'first' => $first, 'after' => $after]))
            ->then(fn (?array $body) => $this->collectRows($body, CharityDonation::class, 'id'));
    }
}
