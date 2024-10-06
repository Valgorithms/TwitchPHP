<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
Use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Twitch\Exception\RateLimitException;
use Twitch\Exception\RetryRateLimitException;
use Twitch\Exception\QueryException;

use function React\Async\await;
use function React\Promise\resolve;
use function React\Promise\reject;

/**
 * Class Helix
 * 
 * This class provides methods to interact with the Twitch Helix API.
 * 
 * @link https://dev.twitch.tv/docs/api/get-started
 * @link https://dev.twitch.tv/docs/api/reference
 * 
 * @package TwitchPHP
 */
class Helix
{
    //
    public const SCHEME               = 'https://';
    //
    public const TOKEN                = 'id.twitch.tv/oauth2/token';
    //
    public const HELIX                = 'api.twitch.tv/helix/';
    // GET
    public const GET_USER             = 'api.twitch.tv/helix/users?login=:nick';
    //
    public const START_RAID           = 'api.twitch.tv/helix/raids?from_broadcaster_id=:from_id&to_broadcaster_id=:to_id';
    //
    public const CANCEL_RAID          = 'api.twitch.tv/helix/raids?broadcaster_id=:broadcaster_id';
    //
    public const GET_CREATOR_GOALS    = 'api.twitch.tv/helix/goals?broadcaster_id=:broadcaster_id';
    //
    public const CREATE_POLL          = 'api.twitch.tv/helix/polls';
    //
    public const PREDICTIONS          = 'api.twitch.tv/helix/predictions';
    //
    public const CLIPS                = 'api.twitch.tv/helix/clips';
    //
    public const MARKERS              = 'api.twitch.tv/helix/streams/markers';
    //
    public const VIDEOS               = 'api.twitch.tv/helix/videos';
    //
    public const GET_SCHEDULE         = 'api.twitch.tv/helix/schedule';
    //
    public const UPDATE_SCHEDULE      = 'api.twitch.tv/helix/schedule/settings';
    //
    public const SEGMENT              = 'api.twitch.tv/helix/schedule/segment';

    public function __construct(
        public Twitch|MockObject &$twitch
    ){}

    /**
     * Refreshes the access token using the provided refresh token.
     *
     * @param string $refreshToken The refresh token issued to the client.
     * @return array An array containing the new access token and refresh token.
     * @throws \Exception If the refresh request fails.
     */
    public static function refreshAccessToken(
        string $refreshToken = null
    ): ?array
    {
        if ($refreshToken === null) $refreshToken = getenv('twitch_refresh_token');
        $clientId = getenv('twitch_client_id');
        $clientSecret = getenv('twitch_client_secret');
        $url = self::TOKEN;
        $postData = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::SCHEME . $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
            ]);

            /** @var string|false $result */
            $result = curl_exec($ch);
            if ($result === false) throw new \Exception('Failed to refresh access token: ' . curl_error($ch));
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postData,
                ],
            ]);
    
            /** @var string|false $result */
            $result = file_get_contents($url, false, $context);
            if ($result === false) throw new \Exception('Failed to refresh access token using file_get_contents');
        }
        $data = json_decode($result, true);
        if ($data === null) throw new \Exception('Failed to decode JSON response from token endpoint');
        assert(is_array($data));
        if (! isset($data['access_token'], $data['refresh_token'])) throw new \Exception('Invalid response from token endpoint');
        // Store the new tokens securely
        // Update the .env file with the new tokens
        $envFile = getenv('env_path');
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            $envContent = preg_replace('/^twitch_access_token=.*$/m', 'twitch_access_token=' . $data['access_token'], $envContent);
            $envContent = preg_replace('/^twitch_refresh_token=.*$/m', 'twitch_refresh_token=' . $data['refresh_token'], $envContent);
            file_put_contents($envFile, $envContent);
        }

        self::updateTokens($data['access_token'], $data['refresh_token']);
        error_log('Access token refreshed');
        return $data;
    }

    /**
     * Stores the provided access and refresh tokens as environment variables.
     *
     * @param string $accessToken The access token to be stored.
     * @param string $refreshToken The refresh token to be stored.
     *
     * @return void
     */
    private static function updateTokens(string $accessToken, string $refreshToken): void
    {
        putenv("twitch_access_token=$accessToken");
        putenv("twitch_refresh_token=$refreshToken");
    }
    
    /**
     * Executes a cURL request.
     * 
     * @param string $url The URL to send the request to.
     * @param string $method The HTTP method to use ('GET' or 'POST').
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function query(
        string $url,
        string $method = 'GET',
        ?string $data = null
    ): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($url, $method, $data) {
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::SCHEME . $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . getenv('twitch_access_token'),
                    'Client-Id: ' . getenv('twitch_client_id'),
                ]);
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
                /** @var string|false $result */
                $result = curl_exec($ch);
                if ($result === false) {
                    $response = (object)[
                        'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                        'headers' => curl_getinfo($ch),
                    ];
                    $reject(new QueryException('Curl error: ' . curl_error($ch), curl_errno($ch), null, $response));
                    return;
                }
            } else {
                $options = [
                    'http' => [
                        'header' => [
                            'Authorization: Bearer ' . getenv('twitch_access_token'),
                            'Client-Id: ' . getenv('twitch_client_id'),
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
                    return;
                }
            }
            if ($result === '{"error":"Unauthorized","status":401,"message":"OAuth token is missing"}') {
                error_log("Oauth token is missing");
                $reject($result);
                return;
            }
            if ($result === '{"error":"Unauthorized","status":401,"message":"Invalid OAuth token"}') {
                error_log("Oauth token expired, attempting to refresh...");
                self::refreshAccessToken();
                $result = await(self::query($url, $method, $data));
                if ($result === '{"error":"Unauthorized","status":401,"message":"Invalid OAuth token"}') {
                    error_log("Failed to refresh Oauth token");
                    $reject($result);
                    return;
                }
            }
            $resolve($result);
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
    public static function queryWithRateLimitHandling(
        LoopInterface $loop,
        string $url,
        string $method = 'GET',
        ?string $data = null
    ): PromiseInterface
    {
        error_log("Starting queryWithRateLimitHandling for URL: $url");
        return self::query($url, $method, $data)->then(
            function ($response) use ($loop, $url, $method, $data) {
                error_log("Query successful for URL: $url");
                if ($response === '{"error":"Unauthorized","status":401,"message":"Invalid OAuth token"}') {
                    error_log("Unauthorized error for URL: $url. Updating token.");
                    self::refreshAccessToken();
                    return await(self::queryWithRateLimitHandling($loop, $url, $method, $data));
                }
                return $response;
            },
            function (\Throwable $error) use ($loop, $url, $method, $data) {
                error_log("Query failed for URL: $url with error: " . $error->getMessage());

                if ($error instanceof QueryException && $error->getResponse()->status === 429) {
                    $resetTime = $error->getResponse()->headers['Ratelimit-Reset'];
                    $waitTime = $resetTime - time();
                    $loop->addTimer($waitTime, fn () => self::queryWithRateLimitHandling($loop, $url, $method, $data));
                    throw $err = new RetryRateLimitException('Rate limit exceeded', 429, null, $error->getResponse()->headers);
                    error_log($err = "Rate limit exceeded for URL: $url. Retrying after $waitTime seconds.");
                    return $err;
                }

                error_log('Rate limit exceeded');
                
                return throw new \Exception('Rate limit exceeded', 429, null);
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
    public static function bindParams(
        string $url,
        array $params
    ): string
    {
        return str_replace(array_map(fn($key) => ':' . $key, array_keys($params)), array_values($params), $url);
    }

    /**
     * Retrieves user information from Twitch.
     * 
     * @param string $nick The Twitch username to retrieve information for.
     * @return PromiseInterface<string> A promise that resolves with the user information or rejects with an error.
     */
    public static function getUser(
        string $nick,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::GET_USER, ['nick' => $nick]);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Starts a raid from one broadcaster to another.
     * 
     * @param string $fromId The ID of the broadcaster initiating the raid.
     * @param string $toId The ID of the broadcaster being raided.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function startRaid(
        string $fromId,
        string $toId,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::START_RAID, ['from_id' => $fromId, 'to_id' => $toId]);
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Cancels a raid initiated by a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster that initiated the raid.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function cancelRaid(
        string $broadcasterId,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::CANCEL_RAID, ['broadcaster_id' => $broadcasterId]);
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a broadcaster's creator goals.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose goals you want to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getCreatorGoals(
        string $broadcasterId,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::GET_CREATOR_GOALS, ['broadcaster_id' => $broadcasterId]);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
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
        int $channelPointsPerVote = 0,
        ?loopInterFace $loop = null
    ): PromiseInterface {
        $url = self::CREATE_POLL;
        $method = 'POST';
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
        $data = json_encode($data);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
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
        string $status,
        ?loopInterFace $loop = null
    ): PromiseInterface {
        $url = self::CREATE_POLL;
        $method = 'PATCH';
        $data = [
            'broadcaster_id' => $broadcasterId,
            'id' => $pollId,
            'status' => $status,
        ];
        $data = json_encode($data);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the current state of polls for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose polls you want to get.
     * @param string|null $pollId (optional) The ID of a specific poll to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getPolls(
        string $broadcasterId,
        ?string $pollId = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::CREATE_POLL, ['broadcaster_id' => $broadcasterId]);
        if ($pollId !== null) $url .= '&id=' . $pollId;
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
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
        int $predictionWindow,
        ?loopInterFace $loop = null
    ): PromiseInterface {
        $url = self::PREDICTIONS;
        $method = 'POST';
        $data = [
            'broadcaster_id' => $broadcasterId,
            'title' => $title,
            'outcomes' => $outcomes,
            'prediction_window' => $predictionWindow,
        ];
        $data = json_encode($data);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
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
        ?string $winningOutcomeId = null,
        ?loopInterFace $loop = null
    ): PromiseInterface {
        $url = self::PREDICTIONS;
        $method = 'PATCH';
        $data = [
            'broadcaster_id' => $broadcasterId,
            'id' => $predictionId,
            'status' => $status,
        ];
        if ($status === 'RESOLVED' && $winningOutcomeId !== null) $data['winning_outcome_id'] = $winningOutcomeId;
        $data = json_encode($data);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the current state of predictions for a broadcaster.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose predictions you want to get.
     * @param string|array|null $predictionIds (optional) A specific prediction ID or an array of prediction IDs to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getPredictions(
        string $broadcasterId,
        string|array|null $predictionIds = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::PREDICTIONS, ['broadcaster_id' => $broadcasterId]);
        if ($predictionIds !== null) {
            if (is_string($predictionIds)) $predictionIds = [$predictionIds];
            if (is_array($predictionIds) && count($predictionIds) > 0) $url .= '&id=' . implode('&id=', $predictionIds);
        }
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Creates a clip from a broadcaster's stream.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose stream you want to create a clip from.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createClip(
        string $broadcasterId,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::CLIPS, ['broadcaster_id' => $broadcasterId]);
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url);
        return $promise;
    }

    /**
     * Gets clips captured from a specific broadcaster's streams.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose clips you want to get.
     * @param string|null $startedAt (optional) The start date for the date range filter in ISO 8601 format.
     * @param string|null $endedAt (optional) The end date for the date range filter in ISO 8601 format.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getClips(
        string $broadcasterId,
        ?string $startedAt = null,
        ?string $endedAt = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['broadcaster_id' => $broadcasterId];
        if ($startedAt !== null) {
            $params['started_at'] = $startedAt;
            if ($endedAt === null) {
                $endDate = Carbon::parse($startedAt)->addWeek()->toIso8601String();
                $params['ended_at'] = $endDate;
            } else $params['ended_at'] = $endedAt;
        }
        $url = self::bindParams(self::CLIPS, $params);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Gets clips captured from a specific game.
     * 
     * @param string $gameId The ID of the game whose clips you want to get.
     * @param string|null $startedAt (optional) The start date for the date range filter in ISO 8601 format.
     * @param string|null $endedAt (optional) The end date for the date range filter in ISO 8601 format.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getGameClips(
        string $gameId,
        ?string $startedAt = null,
        ?string $endedAt = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['game_id' => $gameId];
        if ($startedAt !== null) {
            $params['started_at'] = $startedAt;
            if ($endedAt === null) {
                $endDate = Carbon::parse($startedAt)->addWeek()->toIso8601String();
                $params['ended_at'] = $endDate;
            } else $params['ended_at'] = $endedAt;
        }
        $url = self::bindParams(self::CLIPS, $params);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Gets specific clips by their IDs.
     * 
     * @param array $clipIds An array of clip IDs to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getSpecificClips(
        array $clipIds,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::CLIPS . '?id=' . implode('&id=', $clipIds);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Creates a stream marker for a broadcaster's live stream.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose stream you want to mark.
     * @param string|null $description (optional) A short description to help remind you why you created the marker.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createStreamMarker(
        string $broadcasterId,
        ?string $description = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::MARKERS;
        $method = 'POST';
        $data = ['user_id' => $broadcasterId];
        if ($description !== null) $data['description'] = $description;
        $data = json_encode($data);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
                ->then(
                    fn ($response) => $response,
                    function (\Throwable $error) {
                        if (isset($error->response->status) && $error->response->status === 400)
                            throw new \Exception('Bad Request: The request was invalid or cannot be otherwise served.');
                        throw $error;
                    }
                )
            : self::query($url, $method, $data);
        return $promise;
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
    public static function getStreamMarkers(
        string $broadcasterId,
        ?string $videoId = null,
        ?int $first = null,
        ?string $after = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['user_id' => $broadcasterId];
        if ($videoId !== null) $params['video_id'] = $videoId;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;
        $url = self::bindParams(self::MARKERS, $params);

        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
                ->then(
                    function ($response) {
                        // Handle successful response
                        return $response;
                    },
                    function (\Throwable $error) {
                        // Handle error response
                        if (isset($error->response->status) && $error->response->status === 404) {
                            throw new \Exception('No VODs found for the specified broadcaster.');
                        }
                        // Re-throw the error if it's not a known issue
                        throw $error;
                    }
                )
            : self::query($url);
        return $promise;
    }

    /**
     * Gets videos by their IDs.
     * 
     * @param array $videoIds An array of video IDs to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getVideosById(
        array $videoIds,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::VIDEOS, ['id' => $videoIds]);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
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
    public static function getVideosByBroadcaster(
        string $broadcasterId,
        ?string $type = null,
        ?int $first = null,
        ?string $after = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['user_id' => $broadcasterId];
        if ($type !== null) $params['type'] = $type;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;
        $url = self::bindParams(self::VIDEOS, $params);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
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
    public static function getVideosByGame(
        string $gameId,
        ?string $type = null,
        ?string $language = null,
        ?string $period = null,
        ?string $sort = null,
        ?int $first = null,
        ?string $after = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['game_id' => $gameId];
        if ($type !== null) $params['type'] = $type;
        if ($language !== null) $params['language'] = $language;
        if ($period !== null) $params['period'] = $period;
        if ($sort !== null) $params['sort'] = $sort;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;
        $url = self::bindParams(self::VIDEOS, $params);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Deletes videos by their IDs.
     * 
     * @param array $videoIds An array of video IDs to delete.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function deleteVideos(
        array $videoIds,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::VIDEOS, ['id' => $videoIds]);
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the broadcaster's streaming schedule.
     * 
     * @param string $broadcasterId The ID of the broadcaster whose schedule you want to get.
     * @param string|null $startTime (optional) The start time to get segments from.
     * @param string|null $id (optional) The ID of the segment to get.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getSchedule(        
        string $broadcasterId,
        ?string $startTime = null,
        ?string $id = null,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['broadcaster_id' => $broadcasterId];
        if ($startTime !== null) $params['start_time'] = $startTime;
        if ($id !== null) $params['id'] = $id;
        $url = self::bindParams(self::GET_SCHEDULE, $params);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Creates a new streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to add the segment.
     * @param array $data The data for the new segment.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createSegment(
        string $broadcasterId,
        array $data,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId]);
        $method = 'POST';
        $data = json_encode($data);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Updates an existing streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to update the segment.
     * @param string $segmentId The ID of the segment to update.
     * @param array $data The data to update the segment with.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateSegment(
        string $broadcasterId,
        string $segmentId,
        array $data,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId, 'id' => $segmentId]);
        $method = 'PATCH';
        $data = json_encode($data);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Cancels a streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to cancel the segment.
     * @param string $segmentId The ID of the segment to cancel.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function cancelSegment(
        string $broadcasterId,
        string $segmentId,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId, 'id' => $segmentId]);
        $method = 'PATCH';
        $data = json_encode(['is_canceled' => true]);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, 'PATCH', $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Deletes a streaming segment.
     * 
     * @param string $broadcasterId The ID of the broadcaster who wants to delete the segment.
     * @param string $segmentId The ID of the segment to delete.
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function deleteSegment(
        string $broadcasterId,
        string $segmentId,
        ?loopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::bindParams(self::SEGMENT, ['broadcaster_id' => $broadcasterId, 'id' => $segmentId]);
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }
}

namespace Twitch\Exception;

class RateLimitException extends QueryException
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
class RetryRateLimitException extends RateLimitException {}

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