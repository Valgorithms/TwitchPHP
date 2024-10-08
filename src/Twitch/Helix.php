<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
use Discord\Http\Http;
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
class Helix //extends Http
{
    // Base URLs
    public const SCHEME                        = 'https://';
    public const TOKEN                         = 'id.twitch.tv/oauth2/token';
    public const HELIX                         = 'api.twitch.tv/helix/';

    // GET
    public const USER                          = 'api.twitch.tv/helix/users?login=:nick';
    // PUT
    public const UPDATE_USER                   = 'api.twitch.tv/helix/users';
    // POST
    public const START_RAID                    = 'api.twitch.tv/helix/raids?from_broadcaster_id=:from_id&to_broadcaster_id=:to_id';
    // DELETE
    public const CANCEL_RAID                   = 'api.twitch.tv/helix/raids?broadcaster_id=:broadcaster_id';
    // GET
    public const CREATOR_GOALS                 = 'api.twitch.tv/helix/goals?broadcaster_id=:broadcaster_id';
    // POST, PATCH
    public const POLLS                         = 'api.twitch.tv/helix/polls';
    // GET, POST, PATCH
    public const PREDICTIONS                   = 'api.twitch.tv/helix/predictions';
    // GET, POST
    public const CLIPS                         = 'api.twitch.tv/helix/clips';
    // GET, POST
    public const MARKERS                       = 'api.twitch.tv/helix/streams/markers';
    // GET, DELETE
    public const VIDEOS                        = 'api.twitch.tv/helix/videos';
    // GET
    public const SCHEDULE                      = 'api.twitch.tv/helix/schedule';
    // PATCH
    public const UPDATE_SCHEDULE               = 'api.twitch.tv/helix/schedule/settings';
    // POST, PATCH, DELETE
    public const SEGMENT                       = 'api.twitch.tv/helix/schedule/segment';
    // POST
    public const START_COMMERCIAL              = 'api.twitch.tv/helix/channels/commercial';
    // GET
    public const AD_SCHEDULE                   = 'api.twitch.tv/helix/channels/ads';
    // POST
    public const SNOOZE_NEXT_AD                = 'api.twitch.tv/helix/channels/ads/schedule/snooze';
    // GET
    public const EXTENSION_ANALYTICS           = 'api.twitch.tv/helix/analytics/extensions';
    // GET
    public const GAME_ANALYTICS                = 'api.twitch.tv/helix/analytics/games';
    // GET
    public const BITS_LEADERBOARD              = 'api.twitch.tv/helix/bits/leaderboard';
    // GET
    public const CHEERMOTES                    = 'api.twitch.tv/helix/bits/cheermotes';
    // GET
    public const EXTENSION_TRANSACTIONS        = 'api.twitch.tv/helix/extensions/transactions';
    // GET, PATCH
    public const CHANNELS                      = 'api.twitch.tv/helix/channels';
    // GET
    public const CHANNEL_EDITORS               = 'api.twitch.tv/helix/channels/editors';
    // GET
    public const FOLLOWED_CHANNELS             = 'api.twitch.tv/helix/channels/followed';
    // GET
    public const CHANNEL_FOLLOWERS             = 'api.twitch.tv/helix/channels/followers';
    // GET, PATCH, POST, DELETE
    public const CUSTOM_REWARDS                = 'api.twitch.tv/helix/channel_points/custom_rewards';
    // GET, PATCH
    public const CUSTOM_REWARD_REDEMPTIONS     = 'api.twitch.tv/helix/channel_points/custom_rewards/redemptions';
    // GET
    public const CHARITY_CAMPAIGN              = 'api.twitch.tv/helix/charity/campaigns';
    // GET
    public const CHARITY_CAMPAIGN_DONATIONS    = 'api.twitch.tv/helix/charity/donations';
    // GET
    public const CHATTERS                      = 'api.twitch.tv/helix/chat/chatters';
    // GET
    public const CHANNEL_EMOTES                = 'api.twitch.tv/helix/chat/emotes';
    // GET
    public const GLOBAL_EMOTES                 = 'api.twitch.tv/helix/chat/emotes/global';
    // GET
    public const EMOTE_SETS                    = 'api.twitch.tv/helix/chat/emotes/set';
    // GET
    public const CHANNEL_CHAT_BADGES           = 'api.twitch.tv/helix/chat/badges';
    // GET
    public const GLOBAL_CHAT_BADGES            = 'api.twitch.tv/helix/chat/badges/global';
    // GET, PATCH
    public const CHAT_SETTINGS                 = 'api.twitch.tv/helix/chat/settings';
    // GET
    public const SHARED_CHAT_SESSION           = 'api.twitch.tv/helix/shared_chat/session';
    // GET
    public const USER_EMOTES                   = 'api.twitch.tv/helix/chat/emotes/user';
    // POST
    public const CHAT_ANNOUNCEMENTS            = 'api.twitch.tv/helix/chat/announcements';
    // POST
    public const SHOUTOUTS                     = 'api.twitch.tv/helix/chat/shoutouts';
    // POST
    public const SEND_CHAT_MESSAGE             = 'api.twitch.tv/helix/chat/messages';
    // GET, PUT
    public const USER_CHAT_COLOR               = 'api.twitch.tv/helix/chat/color';
    // GET, POST, PATCH, DELETE
    public const CONDUITS                      = 'api.twitch.tv/helix/eventsub/conduits';
    // GET, PATCH
    public const CONDUIT_SHARDS                = 'api.twitch.tv/helix/eventsub/conduits/shards';
    // GET
    public const CONTENT_CLASSIFICATION_LABELS = 'api.twitch.tv/helix/content_classification_labels';
    // GET, PATH
    public const DROPS_ENTITLEMENTS               = 'api.twitch.tv/helix/entitlements/drops';
    // GET, PUT
    public const EXTENSION_CONFIGURATION_SEGMENT  = 'api.twitch.tv/helix/extensions/configurations';
    // PUT
    public const EXTENSION_REQUIRED_CONFIGURATION = 'api.twitch.tv/helix/extensions/required_configuration';
    // POST
    public const EXTENSION_PUBSUB_MESSAGE         = 'api.twitch.tv/helix/extensions/pubsub';
    // GET
    public const EXTENSION_LIVE_CHANNELS          = 'api.twitch.tv/helix/extensions/live';
    // GET, POST
    public const EXTENSION_SECRETS                = 'api.twitch.tv/helix/extensions/jwt/secrets';
    // POST
    public const EXTENSION_CHAT_MESSAGE           = 'api.twitch.tv/helix/extensions/chat';
    // GET
    public const EXTENSIONS                       = 'api.twitch.tv/helix/extensions';
    // GET
    public const RELEASED_EXTENSIONS              = 'api.twitch.tv/helix/extensions/released';
    // GET, PUT
    public const EXTENSION_BITS_PRODUCTS          = 'api.twitch.tv/helix/bits/extensions';
    // POST, DELETE
    public const EVENTSUB_SUBSCRIPTIONS           = 'api.twitch.tv/helix/eventsub/subscriptions';
    // GET
    public const TOP_GAMES                        = 'api.twitch.tv/helix/games/top';
    // GET
    public const GAMES                            = 'api.twitch.tv/helix/games';

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
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json',]);

            /** @var string|false $result */
            $result = curl_exec($ch);
            if ($result === false) throw new \Exception('Failed to refresh access token: ' . curl_error($ch));
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $postData,
                ],
            ]);
    
            /** @var string|false $result */
            $result = file_get_contents($url, false, $context);
            if ($result === false) throw new \Exception('Failed to refresh access token using file_contents');
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
        ?array $data = null
    ): PromiseInterface
    {
        $promise = new Promise(function ($resolve, $reject) use ($url, $method, $data) {
            $json_data = json_encode($data);
            error_log("[QUERY] $url - $method - $json_data");
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::SCHEME . $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . getenv('twitch_access_token'),
                    'Client-Id' => getenv('twitch_client_id'),
                ]);
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                } else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
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
                        'header' => "Content-Type: application/json\r\n" .
                                    "Authorization: Bearer " . getenv('twitch_access_token') . "\r\n" .
                                    "Client-Id: " . getenv('twitch_client_id') . "\r\n",
                        'method' => $method,
                        'content' => $json_data,
                    ],
                ];
                $context = stream_context_create($options);
                $result = @file_get_contents(self::SCHEME . $url, false, $context);
                if ($result === FALSE) {
                    $error = error_get_last();
                    $response = (object)[
                        'status' => http_response_code(),
                        'headers' => $http_response_header,
                        'error' => $error,
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
        $promise = $promise->then(
            fn ($response) => isset(json_decode($response, true)['error']) ? reject(new \Exception(json_decode($response, true)['message'], json_decode($response, true)['status'])) : $response,
            fn (\Throwable $error) => $error
        );
        return $promise;
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
        ?array $data = null
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
        $url = self::bindParams(self::USER, ['nick' => $nick]);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Updates the specified user's information.
     *
     * @param string $description The string to update the channel’s description to. The description is limited to a maximum of 300 characters.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateUser(
        ?string $description = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        if (strlen($description) > 300) return reject(new \Exception('Description is limited to a maximum of 300 characters'));
        $url = self::UPDATE_USER;
        $method = 'PUT';
        $data = [];
        $data['description'] = $description ?? '';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);

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
        $url = self::bindParams(self::CREATOR_GOALS, ['broadcaster_id' => $broadcasterId]);
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
        $url = self::POLLS;
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
        $url = self::POLLS;
        $method = 'PATCH';
        $data = [
            'broadcaster_id' => $broadcasterId,
            'id' => $pollId,
            'status' => $status,
        ];
        
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
        $url = self::bindParams(self::POLLS, ['broadcaster_id' => $broadcasterId]);
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
                    fn ($response) => $response,
                    function (\Throwable $error) {
                        if (isset($error->response->status) && $error->response->status === 404) throw $error = new \Exception('No VODs found for the specified broadcaster.');
                        return $error;
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
        $url = self::bindParams(self::SCHEDULE, $params);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Sets the broadcaster's vacation schedule.
     *
     * @param string $broadcasterId The ID of the broadcaster who wants to set their vacation schedule.
     * @param bool $isVacationEnabled Whether the vacation is enabled.
     * @param string|null $vacationStartTime The start time of the vacation in RFC3339 format (optional).
     * @param string|null $vacationEndTime The end time of the vacation in RFC3339 format (optional).
     * @param string|null $timezone The time zone where the broadcaster is located (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function setVacationSchedule(
        string $broadcasterId,
        bool $isVacationEnabled,
        ?string $vacationStartTime = null,
        ?string $vacationEndTime = null,
        ?string $timezone = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::UPDATE_SCHEDULE . '?broadcaster_id=' . $broadcasterId;
        $method = 'PATCH';
        $data = ['is_vacation_enabled' => $isVacationEnabled,];

        if ($isVacationEnabled) {
            if ($vacationStartTime !== null) $data['vacation_start_time'] = $vacationStartTime;
            if ($vacationEndTime !== null) $data['vacation_end_time'] = $vacationEndTime;
            if ($timezone !== null) $data['timezone'] = $timezone;
        }
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
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
        $data = ['is_canceled' => true];
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

    /**
     * Starts a commercial on the specified channel.
     *
     * @param string $broadcasterId The ID of the partner or affiliate broadcaster that wants to run the commercial. This ID must match the user ID found in the OAuth token.
     * @param int $length The length of the commercial to run, in seconds. Maximum length is 180 seconds.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function startCommercial(
        string $broadcasterId,
        int $length,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        if ($length > 180) $length = 180;
        $url = self::START_COMMERCIAL;
        $method = 'POST';
        $data = [
            'broadcaster_id' => $broadcasterId,
            'length' => $length,
        ];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the ad schedule information for the specified channel.
     *
     * @param string $broadcasterId The ID of the broadcaster whose ad schedule information is to be retrieved.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getAdSchedule(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::AD_SCHEDULE . '?broadcaster_id=' . $broadcasterId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);

        return $promise;
    }

    /**
     * Snoozes the next ad for the specified channel.
     *
     * @param string $broadcasterId The ID of the broadcaster whose next ad is to be snoozed.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function snoozeNextAd(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SNOOZE_NEXT_AD . '?broadcaster_id=' . $broadcasterId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets an analytics report for one or more extensions.
     *
     * @param string|null $extensionId The extension's client ID (optional).
     * @param string|null $type The type of analytics report to get (optional).
     * @param string|null $startedAt The reporting window's start date in RFC3339 format (optional).
     * @param string|null $endedAt The reporting window's end date in RFC3339 format (optional).
     * @param int|null $first The maximum number of report URLs to return per page in the response (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getExtensionAnalytics(
        ?string $extensionId = null,
        ?string $type = null,
        ?string $startedAt = null,
        ?string $endedAt = null,
        ?int $first = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_ANALYTICS;
        $queryParams = [];
        if ($extensionId !== null) $queryParams['extension_id'] = $extensionId;
        if ($type !== null) $queryParams['type'] = $type;
        if ($startedAt !== null) $queryParams['started_at'] = $startedAt;
        if ($endedAt !== null) $queryParams['ended_at'] = $endedAt;
        if ($first !== null) $queryParams['first'] = $first;
        if ($after !== null) $queryParams['after'] = $after;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets an analytics report for one or more games.
     *
     * @param string|null $gameId The game's client ID (optional).
     * @param string|null $type The type of analytics report to get (optional).
     * @param string|null $startedAt The reporting window's start date in RFC3339 format (optional).
     * @param string|null $endedAt The reporting window's end date in RFC3339 format (optional).
     * @param int|null $first The maximum number of report URLs to return per page in the response (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getGameAnalytics(
        ?string $gameId = null,
        ?string $type = null,
        ?string $startedAt = null,
        ?string $endedAt = null,
        ?int $first = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GAME_ANALYTICS;
        $queryParams = [];
        if ($gameId !== null) $queryParams['game_id'] = $gameId;
        if ($type !== null) $queryParams['type'] = $type;
        if ($startedAt !== null) $queryParams['started_at'] = $startedAt;
        if ($endedAt !== null) $queryParams['ended_at'] = $endedAt;
        if ($first !== null) $queryParams['first'] = $first;
        if ($after !== null) $queryParams['after'] = $after;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the Bits leaderboard for the authenticated broadcaster.
     *
     * @param int|null $count The number of results to return (optional).
     * @param string|null $period The time period over which data is aggregated (optional).
     * @param string|null $startedAt The start date in RFC3339 format (optional).
     * @param string|null $userId An ID that identifies a user that cheered bits in the channel (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getBitsLeaderboard(
        ?int $count = null,
        ?string $period = null,
        ?string $startedAt = null,
        ?string $userId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::BITS_LEADERBOARD;
        $queryParams = [];
        if ($count !== null) $queryParams['count'] = $count;
        if ($period !== null) $queryParams['period'] = $period;
        if ($startedAt !== null) $queryParams['started_at'] = $startedAt;
        if ($userId !== null) $queryParams['user_id'] = $userId;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of Cheermotes that users can use to cheer Bits in any Bits-enabled channel’s chat room.
     *
     * @param string|null $broadcasterId The ID of the broadcaster whose custom Cheermotes you want to get (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getCheermotes(
        ?string $broadcasterId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHEERMOTES;
        $queryParams = [];
        if ($broadcasterId !== null) $queryParams['broadcaster_id'] = $broadcasterId;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets an extension’s list of transactions.
     *
     * @param string $extensionId The ID of the extension whose list of transactions you want to get.
     * @param array|null $transactionIds A list of transaction IDs to filter the list of transactions (optional).
     * @param int|null $first The maximum number of items to return per page in the response (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getExtensionTransactions(
        string $extensionId,
        ?array $transactionIds = null,
        ?int $first = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_TRANSACTIONS;
        $queryParams = ['extension_id' => $extensionId];
        if ($transactionIds !== null) foreach ($transactionIds as $id) $queryParams['id'][] = $id;
        if ($first !== null) $queryParams['first'] = $first;
        if ($after !== null) $queryParams['after'] = $after;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets information about one or more channels.
     *
     * @param array $broadcasterIds The IDs of the broadcasters whose channels you want to get.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getChannelInformation(
        array $broadcasterIds,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNELS;
        $queryParams = [];
        foreach ($broadcasterIds as $id) $queryParams['broadcaster_id'][] = $id;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Modifies a channel’s properties.
     *
     * @param string $broadcasterId The ID of the broadcaster whose channel you want to update.
     * @param array $data The data to update the channel with.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function modifyChannelInformation(
        string $broadcasterId,
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNELS . '?broadcaster_id=' . $broadcasterId;
        $method = 'PATCH';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the broadcaster’s list of editors.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the channel.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getChannelEditors(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNEL_EDITORS . '?broadcaster_id=' . $broadcasterId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of broadcasters that the specified user follows.
     *
     * @param string $userId The ID of the user whose followed channels you want to get.
     * @param string|null $broadcasterId A broadcaster’s ID to check if the user follows this broadcaster (optional).
     * @param int|null $first The maximum number of items to return per page in the response (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getFollowedChannels(
        string $userId,
        ?string $broadcasterId = null,
        ?int $first = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::FOLLOWED_CHANNELS;
        $queryParams = ['user_id' => $userId];
        if ($broadcasterId !== null) $queryParams['broadcaster_id'] = $broadcasterId;
        if ($first !== null) $queryParams['first'] = $first;
        if ($after !== null) $queryParams['after'] = $after;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of users that follow the specified broadcaster.
     *
     * @param string $broadcasterId The ID of the broadcaster whose followers you want to get.
     * @param string|null $userId A user’s ID to check if they follow the broadcaster (optional).
     * @param int|null $first The maximum number of items to return per page in the response (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getChannelFollowers(
        string $broadcasterId,
        ?string $userId = null,
        ?int $first = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNEL_FOLLOWERS;
        $queryParams = ['broadcaster_id' => $broadcasterId];
        if ($userId !== null) $queryParams['user_id'] = $userId;
        if ($first !== null) $queryParams['first'] = $first;
        if ($after !== null) $queryParams['after'] = $after;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Creates a Custom Reward in the broadcaster’s channel.
     *
     * @param string $broadcasterId The ID of the broadcaster to add the custom reward to.
     * @param array $data The data for the custom reward.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createCustomReward(
        string $broadcasterId,
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CUSTOM_REWARDS . '?broadcaster_id=' . $broadcasterId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Deletes a custom reward that the broadcaster created.
     *
     * @param string $broadcasterId The ID of the broadcaster that created the custom reward.
     * @param string $rewardId The ID of the custom reward to delete.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function deleteCustomReward(
        string $broadcasterId,
        string $rewardId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CUSTOM_REWARDS . '?broadcaster_id=' . $broadcasterId . '&id=' . $rewardId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of custom rewards that the specified broadcaster created.
     *
     * @param string $broadcasterId The ID of the broadcaster whose custom rewards you want to get.
     * @param array|null $rewardIds A list of IDs to filter the rewards by (optional).
     * @param bool|null $onlyManageableRewards A Boolean value to get only the custom rewards that the app may manage (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getCustomRewards(
        string $broadcasterId,
        ?array $rewardIds = null,
        ?bool $onlyManageableRewards = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CUSTOM_REWARDS;
        $queryParams = ['broadcaster_id' => $broadcasterId];
        if ($rewardIds !== null) foreach ($rewardIds as $id) $queryParams['id'][] = $id;
        if ($onlyManageableRewards !== null) $queryParams['only_manageable_rewards'] = $onlyManageableRewards ? 'true' : 'false';
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);        
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of redemptions for the specified custom reward.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the custom reward.
     * @param string $rewardId The ID that identifies the custom reward whose redemptions you want to get.
     * @param string|null $status The status of the redemptions to return (optional).
     * @param array|null $redemptionIds A list of IDs to filter the redemptions by (optional).
     * @param string|null $sort The order to sort redemptions by (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param int|null $first The maximum number of redemptions to return per page in the response (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getCustomRewardRedemptions(
        string $broadcasterId,
        string $rewardId,
        ?string $status = null,
        ?array $redemptionIds = null,
        ?string $sort = null,
        ?string $after = null,
        ?int $first = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CUSTOM_REWARD_REDEMPTIONS;
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'reward_id' => $rewardId
        ];
        if ($status !== null) $queryParams['status'] = $status;
        if ($redemptionIds !== null) foreach ($redemptionIds as $id) $queryParams['id'][] = $id;
        if ($sort !== null) $queryParams['sort'] = $sort;
        if ($after !== null) $queryParams['after'] = $after;
        if ($first !== null) $queryParams['first'] = $first;
        if (!empty($queryParams)) $url .= '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates a custom reward.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s updating the reward.
     * @param string $rewardId The ID of the reward to update.
     * @param array $data The data to update the reward with.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateCustomReward(
        string $broadcasterId,
        string $rewardId,
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CUSTOM_REWARDS . '?broadcaster_id=' . $broadcasterId . '&id=' . $rewardId;
        $method = 'PATCH';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Updates a redemption’s status.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s updating the redemption.
     * @param string $rewardId The ID that identifies the reward that’s been redeemed.
     * @param array $redemptionIds A list of IDs that identify the redemptions to update.
     * @param string $status The status to set the redemption to. Possible values are: CANCELED, FULFILLED.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateRedemptionStatus(
        string $broadcasterId,
        string $rewardId,
        array $redemptionIds,
        string $status,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CUSTOM_REWARD_REDEMPTIONS . '?broadcaster_id=' . $broadcasterId . '&reward_id=' . $rewardId;
        foreach ($redemptionIds as $id) $url .= '&id=' . $id;
        $method = 'PATCH';
        $data = ['status' => $status];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets information about the charity campaign that a broadcaster is running.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s currently running a charity campaign.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getCharityCampaign(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHARITY_CAMPAIGN . '?broadcaster_id=' . $broadcasterId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the list of donations that users have made to the broadcaster’s active charity campaign.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s currently running a charity campaign.
     * @param int|null $first The maximum number of items to return per page in the response (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getCharityCampaignDonations(
        string $broadcasterId,
        ?int $first = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHARITY_CAMPAIGN_DONATIONS . '?broadcaster_id=' . $broadcasterId;
        if ($first !== null) $url .= '&first=' . $first;
        if ($after !== null) $url .= '&after=' . $after;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the list of users that are connected to the broadcaster’s chat session.
     *
     * @param string $broadcasterId The ID of the broadcaster whose list of chatters you want to get.
     * @param string $moderatorId The ID of the broadcaster or one of the broadcaster’s moderators.
     * @param int|null $first The maximum number of items to return per page in the response (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getChatters(
        string $broadcasterId,
        string $moderatorId,
        ?int $first = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHATTERS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        if ($first !== null) $url .= '&first=' . $first;
        if ($after !== null) $url .= '&after=' . $after;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the broadcaster’s list of custom emotes.
     *
     * @param string $broadcasterId An ID that identifies the broadcaster whose emotes you want to get.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getChannelEmotes(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNEL_EMOTES . '?broadcaster_id=' . $broadcasterId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the list of global emotes.
     *
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getGlobalEmotes(
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GLOBAL_EMOTES;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets emotes for one or more specified emote sets.
     *
     * @param array $emoteSetIds An array of IDs that identify the emote sets to get.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getEmoteSets(
        array $emoteSetIds,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EMOTE_SETS . '?' . http_build_query(['emote_set_id' => $emoteSetIds]);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the broadcaster’s list of custom chat badges.
     *
     * @param string $broadcasterId The ID of the broadcaster whose chat badges you want to get.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getChannelChatBadges(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNEL_CHAT_BADGES . '?broadcaster_id=' . $broadcasterId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets Twitch’s list of global chat badges.
     *
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getGlobalChatBadges(
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GLOBAL_CHAT_BADGES;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the broadcaster’s chat settings.
     *
     * @param string $broadcasterId The ID of the broadcaster whose chat settings you want to get.
     * @param string|null $moderatorId The ID of the broadcaster or one of the broadcaster’s moderators (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getChatSettings(
        string $broadcasterId,
        ?string $moderatorId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHAT_SETTINGS . '?broadcaster_id=' . $broadcasterId;
        if ($moderatorId !== null) $url .= '&moderator_id=' . $moderatorId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Retrieves the active shared chat session for a channel.
     *
     * @param string $broadcasterId The User ID of the channel broadcaster.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getSharedChatSession(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SHARED_CHAT_SESSION . '?broadcaster_id=' . $broadcasterId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Retrieves emotes available to the user across all channels.
     *
     * @param string $userId The ID of the user. This ID must match the user ID in the user access token.
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param string|null $broadcasterId The User ID of a broadcaster you wish to get follower emotes of (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getUserEmotes(
        string $userId,
        ?string $after = null,
        ?string $broadcasterId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::USER_EMOTES . '?user_id=' . $userId;
        if ($after !== null) $url .= '&after=' . $after;
        if ($broadcasterId !== null) $url .= '&broadcaster_id=' . $broadcasterId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Sends an announcement to the broadcaster’s chat room.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the chat room to send the announcement to.
     * @param string $moderatorId The ID of a user who has permission to moderate the broadcaster’s chat room, or the broadcaster’s ID if they’re sending the announcement.
     * @param string $message The announcement to make in the broadcaster’s chat room. Announcements are limited to a maximum of 500 characters.
     * @param string|null $color The color used to highlight the announcement (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function sendChatAnnouncement(
        string $broadcasterId,
        string $moderatorId,
        string $message,
        ?string $color = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHAT_ANNOUNCEMENTS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'POST';
        $data = ['message' => $message, 'color' => $color];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Sends a Shoutout to the specified broadcaster.
     *
     * @param string $fromBroadcasterId The ID of the broadcaster that’s sending the Shoutout.
     * @param string $toBroadcasterId The ID of the broadcaster that’s receiving the Shoutout.
     * @param string $moderatorId The ID of the broadcaster or a user that is one of the broadcaster’s moderators.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function sendShoutout(
        string $fromBroadcasterId,
        string $toBroadcasterId,
        string $moderatorId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SHOUTOUTS . '?from_broadcaster_id=' . $fromBroadcasterId . '&to_broadcaster_id=' . $toBroadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the broadcaster’s chat settings.
     *
     * @param string $broadcasterId The ID of the broadcaster whose chat settings you want to update.
     * @param string $moderatorId The ID of a user that has permission to moderate the broadcaster’s chat room, or the broadcaster’s ID if they’re making the update.
     * @param array $data The chat settings to update.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateChatSettings(
        string $broadcasterId,
        string $moderatorId,
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHAT_SETTINGS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'PATCH';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Sends a message to the broadcaster's chat room.
     *
     * @param string $broadcasterId The ID of the broadcaster whose chat room the message will be sent to.
     * @param string $senderId The ID of the user sending the message. This ID must match the user ID in the user access token.
     * @param string $message The message to send. The message is limited to a maximum of 500 characters.
     * @param string|null $replyParentMessageId The ID of the chat message being replied to (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function sendMessageToChat(
        string $broadcasterId,
        string $senderId,
        string $message,
        ?string $replyParentMessageId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        if (strlen($message) > 500) return reject(new \Exception('Message is too long. Maximum length is 500 characters.'));
        $url = self::SEND_CHAT_MESSAGE;
        $method = 'POST';
        $data = [
            'broadcaster_id' => $broadcasterId,
            'sender_id' => $senderId,
            'message' => $message,
        ];
        if ($replyParentMessageId !== null) $data['reply_parent_message_id'] = $replyParentMessageId;
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the color used for the user’s name in chat.
     *
     * @param array $userIds The IDs of the users whose username color you want to get.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getUserChatColor(
        array $userIds,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::USER_CHAT_COLOR . '?' . http_build_query(['user_id' => $userIds]);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the color used for the user’s name in chat.
     *
     * @param string $userId The ID of the user whose chat color you want to update. This ID must match the user ID in the access token.
     * @param string $color The color to use for the user's name in chat.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateUserChatColor(
        string $userId,
        string $color,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::USER_CHAT_COLOR . '?user_id=' . $userId . '&color=' . urlencode($color);
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the conduits for a client ID.
     *
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getConduits(
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CONDUITS;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Creates a new conduit.
     *
     * @param int $shardCount The number of shards to create for this conduit.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createConduit(
        int $shardCount,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CONDUITS;
        $method = 'POST';
        $data = ['shard_count' => $shardCount];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Updates a conduit’s shard count.
     *
     * @param string $conduitId The ID of the conduit to update.
     * @param int $shardCount The new number of shards for this conduit.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateConduit(
        string $conduitId,
        int $shardCount,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CONDUITS;
        $method = 'PATCH';
        $data = ['id' => $conduitId, 'shard_count' => $shardCount];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Deletes a specified conduit.
     *
     * @param string $conduitId The ID of the conduit to delete.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function deleteConduit(
        string $conduitId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CONDUITS . '?id=' . $conduitId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of all shards for a conduit.
     *
     * @param string $conduitId The ID of the conduit.
     * @param string|null $status The status to filter by (optional).
     * @param string|null $after The cursor used to get the next page of results (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getConduitShards(
        string $conduitId,
        ?string $status = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['conduit_id' => $conduitId];
        if ($status !== null) $queryParams['status'] = $status;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::CONDUIT_SHARDS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates shard(s) for a conduit.
     *
     * @param string $conduitId The ID of the conduit.
     * @param array $shards The list of shards to update.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateConduitShards(
        string $conduitId,
        array $shards,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CONDUIT_SHARDS;
        $method = 'PATCH';
        $data = ['conduit_id' => $conduitId, 'shards' => $shards];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets information about Twitch content classification labels.
     *
     * @param string|null $locale The locale for the Content Classification Labels (optional).
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getContentClassificationLabels(
        ?string $locale = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $supportedLocales = [
            "bg-BG", "cs-CZ", "da-DK", "de-DE", "el-GR", "en-GB", "en-US", "es-ES", "es-MX", 
            "fi-FI", "fr-FR", "hu-HU", "it-IT", "ja-JP", "ko-KR", "nl-NL", "no-NO", "pl-PL", 
            "pt-BT", "pt-PT", "ro-RO", "ru-RU", "sk-SK", "sv-SE", "th-TH", "tr-TR", "vi-VN", 
            "zh-CN", "zh-TW"
        ];
        $queryParams = [];
        if ($locale !== null) {
            if (! in_array($locale, $supportedLocales)) return reject(new \Exception('Unsupported locale.'));
            $queryParams['locale'] = $locale;
        }
        $url = self::CONTENT_CLASSIFICATION_LABELS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets an organization’s list of entitlements that have been granted to a game, a user, or both.
     *
     * @param array $queryParams The query parameters to filter the entitlements.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getDropsEntitlements(
        array $queryParams = [],
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::DROPS_ENTITLEMENTS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the Drop entitlement’s fulfillment status.
     *
     * @param array $data The data to update the entitlements.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateDropsEntitlements(
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::DROPS_ENTITLEMENTS;
        $method = 'PATCH';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the specified configuration segment from the specified extension.
     *
     * @param array $queryParams The query parameters to filter the configuration segments.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getExtensionConfigurationSegment(
        array $queryParams,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_CONFIGURATION_SEGMENT . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates a configuration segment.
     *
     * @param array $data The data to update the configuration segment.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function setExtensionConfigurationSegment(
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_CONFIGURATION_SEGMENT;
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Updates the extension’s required_configuration string.
     *
     * @param array $data The data to update the required configuration.
     * @param string $broadcasterId The ID of the broadcaster that installed the extension.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function setExtensionRequiredConfiguration(
        array $data,
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_REQUIRED_CONFIGURATION . '?broadcaster_id=' . $broadcasterId;
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Sends a message to one or more viewers.
     *
     * @param array $data The data to send the PubSub message.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function sendExtensionPubSubMessage(
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_PUBSUB_MESSAGE;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets a list of broadcasters that are streaming live and have installed or activated the extension.
     *
     * @param array $queryParams The query parameters to filter the live channels.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getExtensionLiveChannels(
        array $queryParams,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_LIVE_CHANNELS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets an extension’s list of shared secrets.
     *
     * @param array $queryParams The query parameters to filter the secrets.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getExtensionSecrets(
        array $queryParams,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_SECRETS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Creates a shared secret used to sign and verify JWT tokens.
     *
     * @param array $queryParams The query parameters to create the secret.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createExtensionSecret(
        array $queryParams,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_SECRETS . '?' . http_build_query($queryParams);
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Sends a message to the specified broadcaster’s chat room.
     *
     * @param array $data The data to send the chat message.
     * @param string $broadcasterId The ID of the broadcaster that has activated the extension.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function sendExtensionChatMessage(
        array $data,
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_CHAT_MESSAGE . '?broadcaster_id=' . $broadcasterId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets information about an extension.
     *
     * @param array $queryParams The query parameters to get the extension information.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getExtensions(
        array $queryParams,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSIONS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets information about a released extension.
     *
     * @param array $queryParams The query parameters to get the released extension information.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getReleasedExtensions(
        array $queryParams,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::RELEASED_EXTENSIONS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the list of Bits products that belongs to the extension.
     *
     * @param array $queryParams The query parameters to filter the Bits products.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getExtensionBitsProducts(
        array $queryParams = [],
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_BITS_PRODUCTS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Adds or updates a Bits product that the extension created.
     *
     * @param array $data The data to add or update the Bits product.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function updateExtensionBitsProduct(
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EXTENSION_BITS_PRODUCTS;
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Creates an EventSub subscription.
     *
     * @param array $data The data to create the EventSub subscription.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function createEventSubSubscription(
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EVENTSUB_SUBSCRIPTIONS;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Deletes an EventSub subscription.
     *
     * @param string $id The ID of the subscription to delete.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function deleteEventSubSubscription(
        string $id,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EVENTSUB_SUBSCRIPTIONS . '?id=' . $id;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of EventSub subscriptions that the client in the access token created.
     *
     * @param array $queryParams The query parameters to filter the subscriptions.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getEventSubSubscriptions(
        array $queryParams = [],
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::EVENTSUB_SUBSCRIPTIONS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets information about all broadcasts on Twitch.
     *
     * @param array $queryParams The query parameters to filter the top games.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getTopGames(
        array $queryParams = [],
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::TOP_GAMES . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets information about specified categories or games.
     *
     * @param array $queryParams The query parameters to filter the games.
     * @param LoopInterface|null $loop The event loop instance (optional).
     * @return PromiseInterface<string> A promise that resolves with the result or rejects with an error.
     */
    public static function getGames(
        array $queryParams = [],
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GAMES . '?' . http_build_query($queryParams);
        $method = 'GET';
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