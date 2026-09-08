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
use Twitch\Parts\Schedule;
use Twitch\Parts\ScheduleSegment;

/**
 * The stream-schedule resource — read a channel's schedule
 * (`GET /helix/schedule`), and, as the broadcaster
 * (`channel:manage:schedule`), toggle vacation mode and add / edit / remove
 * individual segments.
 *
 * @link https://dev.twitch.tv/docs/api/reference/#get-channel-stream-schedule
 *
 * @extends AbstractRepository<Schedule>
 */
class ScheduleRepository extends AbstractRepository
{
    protected string $part = Schedule::class;

    protected string $discrim = 'broadcaster_id';

    /** @var array<string, string> */
    protected array $endpoints = ['all' => Endpoint::SCHEDULE];

    /**
     * A channel's schedule. Optional `start_time` (RFC3339), `id` (one or more
     * segment ids), `first`.
     *
     * @param array<string, mixed> $filters
     *
     * @return PromiseInterface<Schedule>
     */
    public function forBroadcaster(string $broadcasterId, array $filters = []): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SCHEDULE))
            ->withQuery($filters + ['broadcaster_id' => $broadcasterId]))
            ->then(function (?array $body) use ($broadcasterId): Schedule {
                $data = $body['data'] ?? [];
                $data['broadcaster_id'] ??= $broadcasterId;
                $part = $this->factory->part(Schedule::class, $data, true);
                $this->items->pushItem($part);

                return $part;
            });
    }

    /**
     * The iCalendar (`text/calendar`) feed for a channel's schedule.
     *
     * @return PromiseInterface<string>
     */
    public function iCalendar(string $broadcasterId): PromiseInterface
    {
        return $this->twitch->request('GET', (new Endpoint(Endpoint::SCHEDULE_ICALENDAR))
            ->addQuery('broadcaster_id', $broadcasterId))
            ->then(static fn ($body) => is_string($body) ? $body : (string) ($body['data'] ?? ''));
    }

    /**
     * Turns vacation mode on (pass a start/end, RFC3339 + IANA timezone) or off
     * (pass `$enabled = false`).
     *
     * @return PromiseInterface<null>
     */
    public function setVacation(string $broadcasterId, bool $enabled, ?string $start = null, ?string $end = null, ?string $timezone = null): PromiseInterface
    {
        $query = ['broadcaster_id' => $broadcasterId, 'is_vacation_enabled' => $enabled];
        if ($enabled) {
            $query += ['vacation_start_time' => $start, 'vacation_end_time' => $end, 'timezone' => $timezone];
        }

        return $this->twitch->request('PATCH', (new Endpoint(Endpoint::SCHEDULE_SETTINGS))->withQuery($query));
    }

    /**
     * Adds a schedule segment. `$fields`: `start_time` (RFC3339), `timezone`,
     * `duration` (minutes, string), optional `is_recurring`, `category_id`,
     * `title`.
     *
     * @param array<string, mixed> $fields
     *
     * @return PromiseInterface<ScheduleSegment>
     */
    public function addSegment(string $broadcasterId, array $fields): PromiseInterface
    {
        return $this->twitch->request('POST', (new Endpoint(Endpoint::SCHEDULE_SEGMENT))
            ->addQuery('broadcaster_id', $broadcasterId), $fields)
            ->then(fn (?array $b) => $this->segmentFrom($b));
    }

    /**
     * Edits a schedule segment.
     *
     * @param array<string, mixed> $fields
     *
     * @return PromiseInterface<ScheduleSegment>
     */
    public function editSegment(string $broadcasterId, string $segmentId, array $fields): PromiseInterface
    {
        return $this->twitch->request('PATCH', (new Endpoint(Endpoint::SCHEDULE_SEGMENT))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'id' => $segmentId]), $fields)
            ->then(fn (?array $b) => $this->segmentFrom($b));
    }

    /**
     * Removes a schedule segment.
     *
     * @return PromiseInterface<null>
     */
    public function removeSegment(string $broadcasterId, string $segmentId): PromiseInterface
    {
        return $this->twitch->request('DELETE', (new Endpoint(Endpoint::SCHEDULE_SEGMENT))
            ->withQuery(['broadcaster_id' => $broadcasterId, 'id' => $segmentId]));
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function segmentFrom(?array $body): ScheduleSegment
    {
        $data = $body['data'] ?? [];
        $rows = $data['segments'] ?? (array_is_list($data) ? $data : [$data]);

        return $this->factory->part(ScheduleSegment::class, $rows[0] ?? [], true);
    }
}
