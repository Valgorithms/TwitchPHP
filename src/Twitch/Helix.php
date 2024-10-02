<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
Use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Twitch\Exception\RateLimitException;
use Twitch\Exception\QueryException;

use function React\Promise\resolve;
use function React\Promise\reject;

/**
 * Class Helix
 * 
 * This class provides methods to interact with the Twitch Helix API.
 * 
 * @package TwitchPHP
 */
class Helix
{
    public const SCHEME               = 'https://';
    public const GET_USER             = 'api.twitch.tv/helix/users?login=:nick';
    public const START_RAID           = 'api.twitch.tv/helix/raids?from_broadcaster_id=:from_id&to_broadcaster_id=:to_id';
    public const CANCEL_RAID          = 'api.twitch.tv/helix/raids?broadcaster_id=:broadcaster_id';
    public const GET_CREATOR_GOALS    = 'api.twitch.tv/helix/goals?broadcaster_id=:broadcaster_id';
    public const CREATE_POLL          = 'api.twitch.tv/helix/polls';
    public const CREATE_PREDICTION    = 'api.twitch.tv/helix/predictions';
    public const GET_PREDICTIONS      = 'api.twitch.tv/helix/predictions';
    public const CLIPS                = 'api.twitch.tv/helix/clips';
    public const CREATE_STREAM_MARKER = 'api.twitch.tv/helix/streams/markers';
    public const GET_STREAM_MARKERS   = 'api.twitch.tv/helix/streams/markers';    
    public const VIDEOS               = 'api.twitch.tv/helix/videos';
    public const GET_SCHEDULE         = 'api.twitch.tv/helix/schedule';
    public const UPDATE_SCHEDULE      = 'api.twitch.tv/helix/schedule/settings';
    public const SEGMENT              = 'api.twitch.tv/helix/schedule/segment';
   
    public function __construct(public Twitch|\PHPUnit\Framework\MockObject\MockObject &$twitch){}
    
    /**
     * Executes a cURL request.
     * 
     * @param string $url The URL to send the request to.
     * @param string $method The HTTP method to use ('GET' or 'POST').
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function query(string $url, string $method = 'GET', ?string $data = null): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($url, $method, $data) {
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::SCHEME . $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . getenv('bot_token'),
                    'Client-Id: ' . getenv('client_id'),
                ]);
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
                $result = curl_exec($ch);
                if ($error = curl_errno($ch)) {
                    $response = (object)[
                        'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                        'headers' => curl_getinfo($ch),
                    ];
                    $reject(new QueryException('Curl error: ' . curl_errno($ch), $error, null, $response));
                } else {
                    $resolve($result);
                }
                curl_close($ch);
            } else {
                $options = [
                    'http' => [
                        'header' => [
                            'Authorization: Bearer ' . getenv('bot_token'),
                            'Client-Id: ' . getenv('client_id'),
                        ],
                        'method' => $method,
                        'content' => $data,
                    ],
                ];
                $context = stream_context_create($options);
                $result = file_get_contents(self::SCHEME . $url, false, $context);
                if ($result === FALSE) {
                    $response = (object)[
                        'status' => http_response_code(),
                        'headers' => $http_response_header,
                    ];
                    $reject(new QueryException('File get contents error', 0, null, $response));
                } else {
                    $resolve($result);
                }
            }
        });
    }

    /**
     * Handles rate limits by checking the relevant headers and managing retries.
     * 
     * @param string $url The URL to query.
     * @param string $method The HTTP method to use ('GET', 'POST', 'PATCH', 'DELETE').
     * @param string|null $data The data to send with the request (for POST, PATCH).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public function queryWithRateLimitHandling(string $url, string $method = 'GET', ?string $data = null): PromiseInterface
    {
        error_log("Starting queryWithRateLimitHandling for URL: $url");

        return self::query($url, $method, $data)->then(
            function ($response) use ($url) {
                error_log("Query successful for URL: $url");
                return $response;
            },
            function ($error) use ($url, $method, $data) {
                error_log("Query failed for URL: $url with error: " . $error->getMessage());

                if ($error instanceof QueryException && $error->getResponse()->status === 429) {
                    $resetTime = $error->getResponse()->headers['Ratelimit-Reset'];
                    $waitTime = $resetTime - time();
                    error_log("Rate limit exceeded for URL: $url. Retrying after $waitTime seconds.");

                    return new Promise(function ($resolve) use ($url, $method, $data, $waitTime) {
                        $this->twitch->getLoop()->addTimer($waitTime, function () use ($resolve, $url, $method, $data) {
                            error_log("Retrying query for URL: $url");
                            $resolve($this->query($url, $method, $data));
                        });
                    });
                }

                throw new RateLimitException('Rate limit exceeded', 429, null, $error->getResponse()->headers);
            }
        );
    }
   
    /**
     * Binds parameters to a URL. Placeholders in the URL should be prefixed with a colon.
     * 
     * @param string $url The URL with placeholders.
     * @param array $params An associative array of parameters to bind to the URL.
     * @return string The URL with bound parameters.
     */
    public static function bindParams(string $url, array $params): string
    {
        return str_replace(array_map(fn($key) => ':' . $key, array_keys($params)), array_values($params), $url);
    }

    /**
     * Retrieves user information from Twitch.
     * 
     * @param string $nick The Twitch username to retrieve information for.
     * @return PromiseInterface<string> A promise that resolves with the user information or rejects with an error.
     */
    public static function getUser(string $nick): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::GET_USER, ['nick' => $nick]));
    }

    /**
     * Starts a raid from one broadcaster to another.
     * 
     * @param string $fromId The ID of the broadcaster initiating the raid.
     * @param string $toId The ID of the broadcaster being raided.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function startRaid(string $fromId, string $toId): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::START_RAID, ['from_id' => $fromId, 'to_id' => $toId]), 'POST');
    }

    /**
     * Cancels a raid initiated by a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster that initiated the raid.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function cancelRaid(string $broadcasterId): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::CANCEL_RAID, ['broadcaster_id' => $broadcasterId]), 'DELETE');
    }

    /**
     * Gets a broadcaster's creator goals.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose goals you want to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getCreatorGoals(string $broadcasterId): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::GET_CREATOR_GOALS, ['broadcaster_id' => $broadcasterId]));
    }

     /**
     * Creates a poll for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster creating the poll.
     * @param string $title The title of the poll.
     * @param array $choices An array of choices for the poll. Each choice should be an associative array with a 'title' key.
     * @param int $duration The duration of the poll in seconds.
     * @param bool $channelPointsVotingEnabled (optional) Whether Channel Points voting is enabled.
     * @param int $channelPointsPerVote (optional) The number of Channel Points required per additional vote.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createPoll(
        string $broadcasterId,
        string $title,
        array $choices,
        int $duration,
        bool $channelPointsVotingEnabled = false,
        int $channelPointsPerVote = 0
    ): PromiseInterface {
        $data = [
            'broadcaster_id' => $broadcasterId,
            'title' => $title,
            'choices' => $choices,
            'duration' => $duration,
        ];
    
        if ($channelPointsVotingEnabled) {
            $data['channel_points_voting_enabled'] = true;
            $data['channel_points_per_vote'] = $channelPointsPerVote;
        }
    
        return self::queryWithRateLimitHandling(self::CREATE_POLL, 'POST', json_encode($data));
    }

    /**
     * Ends a poll for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster ending the poll.
     * @param string $pollId The ID of the poll to end.
     * @param string $status The status to set for the poll ('TERMINATED' or 'ARCHIVED').
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function endPoll(
        string $broadcasterId,
        string $pollId,
        string $status
    ): PromiseInterface {
        $data = [
            'broadcaster_id' => $broadcasterId,
            'id' => $pollId,
            'status' => $status,
        ];

        return self::queryWithRateLimitHandling(self::CREATE_POLL, 'PATCH', json_encode($data));
    }

    /**
     * Gets the current state of polls for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose polls you want to get.
     * @param string|null $pollId (optional) The ID of a specific poll to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getPolls(string $broadcasterId, ?string $pollId = null): PromiseInterface
    {
        $url = self::bindParams(self::CREATE_POLL, ['broadcaster_id' => $broadcasterId]);
        if ($pollId !== null) {
            $url .= '&id=' . $pollId;
        }
        return self::queryWithRateLimitHandling($url);
    }

    /**
     * Creates a prediction for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster creating the prediction.
     * @param string $title The title of the prediction.
     * @param array $outcomes An array of outcomes for the prediction. Each outcome should be an associative array with a 'title' key.
     * @param int $predictionWindow The duration of the prediction in seconds.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createPrediction(
        string $broadcasterId,
        string $title,
        array $outcomes,
        int $predictionWindow
    ): PromiseInterface {
        $data = [
            'broadcaster_id' => $broadcasterId,
            'title' => $title,
            'outcomes' => $outcomes,
            'prediction_window' => $predictionWindow,
        ];

        return self::queryWithRateLimitHandling(self::CREATE_PREDICTION, 'POST', json_encode($data));
    }

    /**
     * Ends, cancels, or locks a prediction for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster ending the prediction.
     * @param string $predictionId The ID of the prediction to end.
     * @param string $status The status to set for the prediction ('RESOLVED', 'CANCELED', or 'LOCKED').
     * @param string|null $winningOutcomeId (optional) The ID of the winning outcome. Required if status is 'RESOLVED'.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function endPrediction(
        string $broadcasterId,
        string $predictionId,
        string $status,
        ?string $winningOutcomeId = null
    ): PromiseInterface {
        $data = [
            'broadcaster_id' => $broadcasterId,
            'id' => $predictionId,
            'status' => $status,
        ];

        if ($status === 'RESOLVED' && $winningOutcomeId !== null) $data['winning_outcome_id'] = $winningOutcomeId;

        return self::queryWithRateLimitHandling(self::CREATE_PREDICTION, 'PATCH', json_encode($data));
    }

    /**
     * Gets the current state of predictions for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose predictions you want to get.
     * @param string|array|null $predictionIds (optional) A specific prediction ID or an array of prediction IDs to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getPredictions(string $broadcasterId, string|array|null $predictionIds = null): PromiseInterface
    {
        $url = self::bindParams(self::GET_PREDICTIONS, ['broadcaster_id' => $broadcasterId]);
        
        if ($predictionIds !== null) {
            if (is_string($predictionIds)) $predictionIds = [$predictionIds];
            if (is_array($predictionIds) && count($predictionIds) > 0) $url .= '&id=' . implode('&id=', $predictionIds);
        }
        
        return self::queryWithRateLimitHandling($url);
    }

    /**
     * Creates a clip from a broadcaster's stream.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose stream you want to create a clip from.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createClip(string $broadcasterId): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::CLIPS, ['broadcaster_id' => $broadcasterId]), 'POST');
    }

    /**
     * Gets clips captured from a specific broadcaster's streams.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose clips you want to get.
     * @param string|null $startedAt (optional) The start date for the date range filter in ISO 8601 format.
     * @param string|null $endedAt (optional) The end date for the date range filter in ISO 8601 format.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getClips(string $broadcasterId, ?string $startedAt = null, ?string $endedAt = null): PromiseInterface
    {
        $params = ['broadcaster_id' => $broadcasterId];
        if ($startedAt !== null) {
            $params['started_at'] = $startedAt;
            if ($endedAt === null) {
                $endDate = Carbon::parse($startedAt)->addWeek()->toIso8601String();
                $params['ended_at'] = $endDate;
            } else $params['ended_at'] = $endedAt;
        }
        return self::queryWithRateLimitHandling(self::bindParams(self::CLIPS, $params));
    }

    /**
     * Gets clips captured from a specific game.
     * 
     * @param string $gameId The ID of the game whose clips you want to get.
     * @param string|null $startedAt (optional) The start date for the date range filter in ISO 8601 format.
     * @param string|null $endedAt (optional) The end date for the date range filter in ISO 8601 format.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getGameClips(string $gameId, ?string $startedAt = null, ?string $endedAt = null): PromiseInterface
    {
        $params = ['game_id' => $gameId];
        if ($startedAt !== null) {
            $params['started_at'] = $startedAt;
            if ($endedAt === null) {
                $endDate = Carbon::parse($startedAt)->addWeek()->toIso8601String();
                $params['ended_at'] = $endDate;
            } else $params['ended_at'] = $endedAt;
        }
        return self::queryWithRateLimitHandling(self::bindParams(self::CLIPS, $params));
    }

    /**
     * Gets specific clips by their IDs.
     * 
     * @param array $clipIds An array of clip IDs to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getSpecificClips(array $clipIds): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::CLIPS . '?id=' . implode('&id=', $clipIds));
    }

    /**
     * Creates a stream marker for a broadcaster's live stream.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose stream you want to mark.
     * @param string|null $description (optional) A short description to help remind you why you created the marker.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createStreamMarker(string $broadcasterId, ?string $description = null): PromiseInterface
    {
        $data = ['user_id' => $broadcasterId];
        if ($description !== null) $data['description'] = $description;

        return self::queryWithRateLimitHandling(self::CREATE_STREAM_MARKER, 'POST', json_encode($data))
            ->then(
                fn ($response) => $response,
                function ($error) {
                    if (isset($error->response->status) && $error->response->status === 400)
                        throw new \Exception('Bad Request: The request was invalid or cannot be otherwise served.');
                    throw $error;
                }
            );
    }

    /**
     * Gets stream markers from the most recent VOD or a specific VOD for a specific broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose markers you want to get.
     * @param string|null $videoId (optional) The ID of the specific VOD whose markers you want to get.
     * @param int|null $first (optional) The number of objects to return. Maximum: 100.
     * @param string|null $after (optional) The cursor used to fetch the next page of data.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getStreamMarkers(string $broadcasterId, ?string $videoId = null, ?int $first = null, ?string $after = null): PromiseInterface
    {
        $params = ['user_id' => $broadcasterId];
        if ($videoId !== null) $params['video_id'] = $videoId;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;

        return self::queryWithRateLimitHandling(self::bindParams(self::GET_STREAM_MARKERS, $params))
            ->then(
                function ($response) {
                    // Handle successful response
                    return $response;
                },
                function ($error) {
                    // Handle error response
                    if (isset($error->response->status) && $error->response->status === 404) {
                        throw new \Exception('No VODs found for the specified broadcaster.');
                    }
                    // Re-throw the error if it's not a known issue
                    throw $error;
                }
            );
    }

    /**
     * Gets videos by their IDs.
     * 
     * @param array $videoIds An array of video IDs to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getVideosById(array $videoIds): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::VIDEOS, ['id' => $videoIds]));
    }

    /**
     * Gets videos by broadcaster ID.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose videos you want to get.
     * @param string|null $type (optional) The type of videos to get (archive, highlight, upload).
     * @param int|null $first (optional) The number of objects to return. Maximum: 100.
     * @param string|null $after (optional) The cursor used to fetch the next page of data.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getVideosByBroadcaster(string $broadcasterId, ?string $type = null, ?int $first = null, ?string $after = null): PromiseInterface
    {
        $params = ['user_id' => $broadcasterId];
        if ($type !== null) $params['type'] = $type;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;
        return self::queryWithRateLimitHandling(self::bindParams(self::VIDEOS, $params));
    }

    /**
     * Gets videos by game ID.
     * 
     * @param string $gameId The ID of the game whose videos you want to get.
     * @param string|null $type (optional) The type of videos to get (archive, highlight, upload).
     * @param string|null $language (optional) The language of the videos to get.
     * @param string|null $period (optional) The period of the videos to get (day, week, month, all).
     * @param string|null $sort (optional) The sort order of the videos (time, trending, views).
     * @param int|null $first (optional) The number of objects to return. Maximum: 100.
     * @param string|null $after (optional) The cursor used to fetch the next page of data.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getVideosByGame(string $gameId, ?string $type = null, ?string $language = null, ?string $period = null, ?string $sort = null, ?int $first = null, ?string $after = null): PromiseInterface
    {
        $params = ['game_id' => $gameId];
        if ($type !== null) $params['type'] = $type;
        if ($language !== null) $params['language'] = $language;
        if ($period !== null) $params['period'] = $period;
        if ($sort !== null) $params['sort'] = $sort;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;
        return self::queryWithRateLimitHandling(self::bindParams(self::VIDEOS, $params));
    }

    /**
     * Deletes videos by their IDs.
     * 
     * @param array $videoIds An array of video IDs to delete.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function deleteVideos(array $videoIds): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::VIDEOS, ['id' => $videoIds]), 'DELETE');
    }

    /**
     * Gets the broadcaster's streaming schedule.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose schedule you want to get.
     * @param string|null $startTime (optional) The start time to get segments from.
     * @param string|null $id (optional) The ID of the segment to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getSchedule(string $broadcasterId, ?string $startTime = null, ?string $id = null): PromiseInterface
    {
        $params = ['broadcaster_id' => $broadcasterId];
        if ($startTime !== null) $params['start_time'] = $startTime;
        if ($id !== null) $params['id'] = $id;
        return self::queryWithRateLimitHandling(self::bindParams(self::GET_SCHEDULE, $params));
    }

    /**
     * Creates a new streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to add the segment.
     * @param array $data The data for the new segment.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createSegment(string $broadcasterId, array $data): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId]), 'POST', json_encode($data));
    }

    /**
     * Updates an existing streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to update the segment.
     * @param string $segmentId The ID of the segment to update.
     * @param array $data The data to update the segment with.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateSegment(string $broadcasterId, string $segmentId, array $data): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId, 'id' => $segmentId]), 'PATCH', json_encode($data));
    }

    /**
     * Cancels a streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to cancel the segment.
     * @param string $segmentId The ID of the segment to cancel.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function cancelSegment(string $broadcasterId, string $segmentId): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId, 'id' => $segmentId]), 'PATCH', json_encode(['is_canceled' => true]));
    }

    /**
     * Deletes a streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to delete the segment.
     * @param string $segmentId The ID of the segment to delete.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function deleteSegment(string $broadcasterId, string $segmentId): PromiseInterface
    {
        return self::queryWithRateLimitHandling(self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId, 'id' => $segmentId]), 'DELETE');
    }
}

namespace Twitch\Exception;

class RateLimitException extends \Exception
{
    private $headers;

    public function __construct($message, $code = 0, \Exception $previous = null, array $headers = [])
    {
        parent::__construct($message, $code, $previous);
        $this->headers = $headers;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}

class QueryException extends \Exception
{
    private $response;

    public function __construct($message, $code = 0, \Exception $previous = null, $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}