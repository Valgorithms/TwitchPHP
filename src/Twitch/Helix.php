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
use function React\Async\delay;
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
    public const SCHEME                           = 'https://';
    public const TOKEN                            = 'id.twitch.tv/oauth2/token';
    public const HELIX                            = 'api.twitch.tv/helix/';

    // GET, PUT
    public const USERS                            = 'api.twitch.tv/helix/users';
    // POST, DELETE
    public const RAIDS                            = 'api.twitch.tv/helix/raids';
    // GET
    public const GOALS                            = 'api.twitch.tv/helix/goals';
    // GET, POST, PATCH
    public const POLLS                            = 'api.twitch.tv/helix/polls';
    // GET, POST, PATCH
    public const PREDICTIONS                      = 'api.twitch.tv/helix/predictions';
    // GET, POST
    public const CLIPS                            = 'api.twitch.tv/helix/clips';
    // GET, POST
    public const STREAM_MARKERS                   = 'api.twitch.tv/helix/streams/markers';
    // GET, DELETE
    public const VIDEOS                           = 'api.twitch.tv/helix/videos';
    // GET
    public const SCHEDULE                         = 'api.twitch.tv/helix/schedule';
    // POST, PATCH, DELTE
    public const SCHEDULE_SEGMENT                 = 'api.twitch.tv/helix/schedule/segment';
    // PATCH
    public const SCHEDULE_SETTINGS                = 'api.twitch.tv/helix/schedule/settings';
    // POST
    public const START_COMMERCIAL                 = 'api.twitch.tv/helix/channels/commercial';
    // GET
    public const AD_SCHEDULE                      = 'api.twitch.tv/helix/channels/ads';
    // POST
    public const SNOOZE_NEXT_AD                   = 'api.twitch.tv/helix/channels/ads/schedule/snooze';
    // GET
    public const EXTENSION_ANALYTICS              = 'api.twitch.tv/helix/analytics/extensions';
    // GET
    public const GAME_ANALYTICS                   = 'api.twitch.tv/helix/analytics/games';
    // GET
    public const BITS_LEADERBOARD                 = 'api.twitch.tv/helix/bits/leaderboard';
    // GET
    public const CHEERMOTES                       = 'api.twitch.tv/helix/bits/cheermotes';
    // GET
    public const EXTENSION_TRANSACTIONS           = 'api.twitch.tv/helix/extensions/transactions';
    // GET, PATCH
    public const CHANNELS                         = 'api.twitch.tv/helix/channels';
    // GET
    public const CHANNEL_EDITORS                  = 'api.twitch.tv/helix/channels/editors';
    // GET
    public const FOLLOWED_CHANNELS                = 'api.twitch.tv/helix/channels/followed';
    // GET
    public const CHANNEL_FOLLOWERS                = 'api.twitch.tv/helix/channels/followers';
    // GET, PATCH, POST, DELETE
    public const CUSTOM_REWARDS                   = 'api.twitch.tv/helix/channel_points/custom_rewards';
    // GET, PATCH
    public const CUSTOM_REWARD_REDEMPTIONS        = 'api.twitch.tv/helix/channel_points/custom_rewards/redemptions';
    // GET
    public const CHARITY_CAMPAIGN                 = 'api.twitch.tv/helix/charity/campaigns';
    // GET
    public const CHARITY_CAMPAIGN_DONATIONS       = 'api.twitch.tv/helix/charity/donations';
    // GET
    public const CHATTERS                         = 'api.twitch.tv/helix/chat/chatters';
    // GET
    public const CHANNEL_EMOTES                   = 'api.twitch.tv/helix/chat/emotes';
    // GET
    public const GLOBAL_EMOTES                    = 'api.twitch.tv/helix/chat/emotes/global';
    // GET
    public const EMOTE_SETS                       = 'api.twitch.tv/helix/chat/emotes/set';
    // GET
    public const CHANNEL_CHAT_BADGES              = 'api.twitch.tv/helix/chat/badges';
    // GET
    public const GLOBAL_CHAT_BADGES               = 'api.twitch.tv/helix/chat/badges/global';
    // GET, PATCH
    public const CHAT_SETTINGS                    = 'api.twitch.tv/helix/chat/settings';
    // GET
    public const SHARED_CHAT_SESSION              = 'api.twitch.tv/helix/shared_chat/session';
    // GET
    public const USER_EMOTES                      = 'api.twitch.tv/helix/chat/emotes/user';
    // POST
    public const CHAT_ANNOUNCEMENTS               = 'api.twitch.tv/helix/chat/announcements';
    // POST
    public const SHOUTOUTS                        = 'api.twitch.tv/helix/chat/shoutouts';
    // POST
    public const SEND_CHAT_MESSAGE                = 'api.twitch.tv/helix/chat/messages';
    // GET, PUT
    public const USER_CHAT_COLOR                  = 'api.twitch.tv/helix/chat/color';
    // GET, POST, PATCH, DELETE
    public const CONDUITS                         = 'api.twitch.tv/helix/eventsub/conduits';
    // GET, PATCH
    public const CONDUIT_SHARDS                   = 'api.twitch.tv/helix/eventsub/conduits/shards';
    // GET
    public const CONTENT_CLASSIFICATION_LABELS    = 'api.twitch.tv/helix/content_classification_labels';
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
    // GET, PUT
    public const GUEST_STAR_CHANNEL_SETTINGS      = 'api.twitch.tv/helix/guest_star/channel_settings';
    // GET, POST, DELETE
    public const GUEST_STAR_SESSION               = 'api.twitch.tv/helix/guest_star/session';
    // GET, POST, DELETE
    public const GUEST_STAR_INVITES               = 'api.twitch.tv/helix/guest_star/invites';
    // POST, PATCH, DELETE
    public const GUEST_STAR_SLOT                  = 'api.twitch.tv/helix/guest_star/slot';
    // PATCH
    public const GUEST_STAR_SLOT_SETTINGS         = 'api.twitch.tv/helix/guest_star/slot_settings';
    // GET
    public const HYPE_TRAIN_EVENTS                = 'api.twitch.tv/helix/hypetrain/events';
    // POST
    public const AUTOMOD_STATUS                   = 'api.twitch.tv/helix/moderation/enforcements/status';
    // POST
    public const HELD_AUTOMOD_MESSAGES            = 'api.twitch.tv/helix/moderation/automod/message';
    // GET, PUT
    public const AUTOMOD_SETTINGS                 = 'api.twitch.tv/helix/moderation/automod/settings';
    // GET
    public const BANNED_USERS                     = 'api.twitch.tv/helix/moderation/banned';
    // POST, DELETE
    public const BANS                             = 'api.twitch.tv/helix/moderation/bans';
    // GET, PATCH
    public const UNBAN_REQUESTS                   = 'api.twitch.tv/helix/moderation/unban_requests';
    // GET, POST, DELETE
    public const BLOCKED_TERMS                    = 'api.twitch.tv/helix/moderation/blocked_terms';
    // DELETE
    public const CHAT_MESSAGES                    = 'api.twitch.tv/helix/moderation/chat';
    // GET
    public const MODERATED_CHANNELS               = 'api.twitch.tv/helix/moderation/channels';
    // GET, POST, DELETE
    public const MODERATION_MODERATORS            = 'api.twitch.tv/helix/moderation/moderators';
    // GET, POST, DELETE
    public const CHANNEL_VIPS                     = 'api.twitch.tv/helix/channels/vips';
    // GET, PUT
    public const SHIELD_MODE                      = 'api.twitch.tv/helix/moderation/shield_mode';
    // POST
    public const WARNINGS                         = 'api.twitch.tv/helix/moderation/warnings';
    // GET
    public const SEARCH_CATEGORIES                = 'api.twitch.tv/helix/search/categories';
    // GET
    public const SEARCH_CHANNELS                  = 'api.twitch.tv/helix/search/channels';
    // GET
    public const STREAM_KEY                       = 'api.twitch.tv/helix/streams/key';
    // GET
    public const STREAMS                          = 'api.twitch.tv/helix/streams';
    // GET
    public const FOLLOWED_STREAMS                 = 'api.twitch.tv/helix/streams/followed';
    // GET
    public const BROADCASTER_SUBSCRIPTIONS        = 'api.twitch.tv/helix/subscriptions';
    // GET
    public const USER_SUBSCRIPTION                = 'api.twitch.tv/helix/subscriptions/user';
    // GET
    public const ALL_STREAM_TAGS                  = 'api.twitch.tv/helix/tags/streams';
    // GET
    public const STREAM_TAGS                      = 'api.twitch.tv/helix/streams/tags';
    // GET
    public const CHANNEL_TEAMS                    = 'api.twitch.tv/helix/teams/channel';
    // GET
    public const TEAMS                            = 'api.twitch.tv/helix/teams';
    // GET, PUT, DELETE
    public const USER_BLOCKS                      = 'api.twitch.tv/helix/users/blocks';
    // GET
    public const USERS_EXTENSIONS_LIST            = 'api.twitch.tv/helix/users/extensions/list';
    // GET, PUT
    public const USERS_EXTENSIONS                 = 'api.twitch.tv/helix/users/extensions';

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
        //error_log('Access token refreshed');
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
            //error_log("[QUERY] $url - $method - $json_data");
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::SCHEME . $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . getenv('twitch_access_token'),
                    'Client-Id: ' . getenv('twitch_client_id'),
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
                    error_log($err = "Rate limit exceeded for URL: $url. Retrying after $waitTime seconds.");
                    delay($waitTime);
                    return self::queryWithRateLimitHandling($loop, $url, $method, $data);
                    //$loop->addTimer($waitTime, fn () => self::queryWithRateLimitHandling($loop, $url, $method, $data));
                    //throw $err = new RetryRateLimitException('Rate limit exceeded', 429, null, $error->getResponse()->headers);
                    //return $err;
                }
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
     * Retrieves user information based on the provided login name.
     *
     * @param string $login The login name of the user to retrieve.
     * @param ?LoopInterface $loop Optional event loop interface for handling asynchronous operations.
     * 
     * @return PromiseInterface A promise that resolves to the user information.
     */
    public function getUser(
        string $login,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::USERS . '?' . http_build_query(['login' => $login]);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Starts a raid by sending the broadcaster’s viewers to the targeted channel.
     *
     * @param string $fromBroadcasterId The ID of the broadcaster that’s sending the raiding party. This ID must match the user ID in the user access token.
     * @param string $toBroadcasterId The ID of the broadcaster to raid.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the raid information.
     * @throws QueryException If the query fails.
     */
    public function startRaid(
        string $fromBroadcasterId,
        string $toBroadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'from_broadcaster_id' => $fromBroadcasterId,
            'to_broadcaster_id' => $toBroadcasterId,
        ];
        $url = self::RAIDS . '?' . http_build_query($queryParams);
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Cancels a pending raid.
     *
     * @param string $broadcasterId The ID of the broadcaster that initiated the raid. This ID must match the user ID in the user access token.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the raid is canceled.
     * @throws QueryException If the query fails.
     */
    public function cancelRaid(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['broadcaster_id' => $broadcasterId];
        $url = self::RAIDS . '?' . http_build_query($queryParams);
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
    public function getCreatorGoals(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['broadcaster_id' => $broadcasterId];
        $url = self::GOALS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
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
        ?LoopInterface $loop = null
    ): PromiseInterface
    {
        $url = self::CLIPS . '?' . http_build_query(['broadcaster_id' => $broadcasterId]);
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
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
        ?LoopInterFace $loop = null
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
        $url = self::CLIPS . '?' . http_build_query($params);
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
        ?LoopInterFace $loop = null
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
        $url = self::CLIPS . '?' . http_build_query($params);
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
        ?LoopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::CLIPS . '?id=' . implode('&id=', $clipIds);
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url)
            : self::query($url);
        return $promise;
    }

    /**
     * Gets information about one or more published videos.
     *
     * @param ?array $ids A list of IDs that identify the videos you want to get. You may specify a maximum of 100 IDs.
     * @param ?string $userId The ID of the user whose list of videos you want to get.
     * @param ?string $gameId A category or game ID. The response contains a maximum of 500 videos that show this content.
     * @param ?string $language A filter used to filter the list of videos by the language that the video owner broadcasts in.
     * @param ?string $period A filter used to filter the list of videos by when they were published. Possible values are: all, day, month, week.
     * @param ?string $sort The order to sort the returned videos in. Possible values are: time, trending, views.
     * @param ?string $type A filter used to filter the list of videos by the video's type. Possible values are: all, archive, highlight, upload.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?string $before The cursor used to get the previous page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of published videos.
     * @throws QueryException If the query fails.
     */
    public function getVideos(
        ?array $ids = null,
        ?string $userId = null,
        ?string $gameId = null,
        ?string $language = null,
        ?string $period = 'all',
        ?string $sort = 'time',
        ?string $type = 'all',
        ?int $first = 20,
        ?string $after = null,
        ?string $before = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'period' => $period,
            'sort' => $sort,
            'type' => $type,
            'first' => $first,
        ];
        if ($ids !== null) foreach ($ids as $id) $queryParams['id'][] = $id;
        if ($userId !== null) $queryParams['user_id'] = $userId;
        if ($gameId !== null) $queryParams['game_id'] = $gameId;
        if ($language !== null) $queryParams['language'] = $language;
        if ($after !== null) $queryParams['after'] = $after;
        if ($before !== null) $queryParams['before'] = $before;        
        $url = self::VIDEOS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Deletes one or more videos.
     *
     * @param array $ids The list of videos to delete. You can delete a maximum of 5 videos per request.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of IDs of the videos that were deleted.
     * @throws QueryException If the query fails.
     */
    public function deleteVideos(
        array $ids,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [];
        foreach ($ids as $id) $queryParams['id'][] = $id;
        $url = self::VIDEOS . '?' . http_build_query($queryParams);
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
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
        ?LoopInterface $loop = null
    ): PromiseInterface
    {
        $url = self::VIDEOS . '?' . http_build_query(['id' => $videoIds]);
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
        ?LoopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['user_id' => $broadcasterId];
        if ($type !== null) $params['type'] = $type;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;
        $url = self::VIDEOS . '?' . http_build_query($params);
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
        ?LoopInterFace $loop = null
    ): PromiseInterface
    {
        $params = ['game_id' => $gameId];
        if ($type !== null) $params['type'] = $type;
        if ($language !== null) $params['language'] = $language;
        if ($period !== null) $params['period'] = $period;
        if ($sort !== null) $params['sort'] = $sort;
        if ($first !== null) $params['first'] = $first;
        if ($after !== null) $params['after'] = $after;
        $url = self::VIDEOS . '?' . http_build_query($params);
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
        $url = self::SCHEDULE . '?broadcaster_id=' . $broadcasterId;
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
        ?LoopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::SCHEDULE_SEGMENT . '?' . http_build_query(['broadcaster_id' => $broadcasterId]);
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
        ?LoopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::SCHEDULE_SEGMENT . '?' . http_build_query(['broadcaster_id' => $broadcasterId, 'id' => $segmentId]);
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
        ?LoopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::SCHEDULE_SEGMENT . '?' . http_build_query(['broadcaster_id' => $broadcasterId, 'id' => $segmentId]);
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
        ?LoopInterFace $loop = null
    ): PromiseInterface
    {
        $url = self::SCHEDULE_SEGMENT . '?' . http_build_query(['broadcaster_id' => $broadcasterId, 'id' => $segmentId]);
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

    /**
     * Gets the channel settings for configuration of the Guest Star feature for a particular host.
     *
     * @param string $broadcasterId The ID of the broadcaster you want to get guest star settings for.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @return PromiseInterface A promise that resolves to the guest star settings.
     * @throws QueryException If the query fails.
     */
    public function getGuestStarChannelSettings(
        string $broadcasterId,
        string $moderatorId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_CHANNEL_SETTINGS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the channel settings for configuration of the Guest Star feature for a particular host.
     *
     * @param string $broadcasterId The ID of the broadcaster you want to update Guest Star settings for.
     * @param array $settings The settings to update for the Guest Star feature.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the settings are updated.
     * @throws QueryException If the query fails.
     */
    public function updateGuestStarChannelSettings(
        string $broadcasterId,
        array $settings,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_CHANNEL_SETTINGS . '?broadcaster_id=' . $broadcasterId;
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $settings)
            : self::query($url, $method, $settings);
        return $promise;
    }

    /**
     * Gets information about an ongoing Guest Star session for a particular channel.
     *
     * @param string $broadcasterId The ID of the broadcaster hosting the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the session details.
     * @throws QueryException If the query fails.
     */
    public function getGuestStarSession(
        string $broadcasterId,
        string $moderatorId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_SESSION . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Programmatically creates a Guest Star session on behalf of the broadcaster.
     *
     * @param string $broadcasterId The ID of the broadcaster you want to create a Guest Star session for. Provided broadcaster_id must match the user_id in the auth token.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the session details.
     * @throws QueryException If the query fails.
     */
    public function createGuestStarSession(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_SESSION . '?broadcaster_id=' . $broadcasterId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Programmatically ends a Guest Star session on behalf of the broadcaster.
     *
     * @param string $broadcasterId The ID of the broadcaster you want to end a Guest Star session for. Provided broadcaster_id must match the user_id in the auth token.
     * @param string $sessionId The ID of the session to end on behalf of the broadcaster.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the session details when the session was ended.
     * @throws QueryException If the query fails.
     */
    public function endGuestStarSession(
        string $broadcasterId,
        string $sessionId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_SESSION . '?broadcaster_id=' . $broadcasterId . '&session_id=' . $sessionId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Provides the caller with a list of pending invites to a Guest Star session, including the invitee’s ready status while joining the waiting room.
     *
     * @param string $broadcasterId The ID of the broadcaster running the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user_id in the user access token.
     * @param string $sessionId The session ID to query for invite status.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of pending invites.
     * @throws QueryException If the query fails.
     */
    public function getGuestStarInvites(
        string $broadcasterId,
        string $moderatorId,
        string $sessionId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_INVITES . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&session_id=' . $sessionId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Sends an invite to a specified guest on behalf of the broadcaster for a Guest Star session in progress.
     *
     * @param string $broadcasterId The ID of the broadcaster running the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user_id in the user access token.
     * @param string $sessionId The session ID for the invite to be sent on behalf of the broadcaster.
     * @param string $guestId Twitch User ID for the guest to invite to the Guest Star session.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the invite is sent.
     * @throws QueryException If the query fails.
     */
    public function sendGuestStarInvite(
        string $broadcasterId,
        string $moderatorId,
        string $sessionId,
        string $guestId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_INVITES . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&session_id=' . $sessionId . '&guest_id=' . $guestId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Revokes a previously sent invite for a Guest Star session.
     *
     * @param string $broadcasterId The ID of the broadcaster running the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user_id in the user access token.
     * @param string $sessionId The ID of the session for the invite to be revoked on behalf of the broadcaster.
     * @param string $guestId Twitch User ID for the guest to revoke the Guest Star session invite from.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the invite is revoked.
     * @throws QueryException If the query fails.
     */
    public function deleteGuestStarInvite(
        string $broadcasterId,
        string $moderatorId,
        string $sessionId,
        string $guestId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_INVITES . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&session_id=' . $sessionId . '&guest_id=' . $guestId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Allows a previously invited user to be assigned a slot within the active Guest Star session, once that guest has indicated they are ready to join.
     *
     * @param string $broadcasterId The ID of the broadcaster running the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user_id in the user access token.
     * @param string $sessionId The ID of the Guest Star session in which to assign the slot.
     * @param string $guestId The Twitch User ID corresponding to the guest to assign a slot in the session. This user must already have an invite to this session, and have indicated that they are ready to join.
     * @param string $slotId The slot assignment to give to the user. Must be a numeric identifier between “1” and “N” where N is the max number of slots for the session.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the guest is assigned to the slot.
     * @throws QueryException If the query fails.
     */
    public function assignGuestStarSlot(
        string $broadcasterId,
        string $moderatorId,
        string $sessionId,
        string $guestId,
        string $slotId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_SLOT . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&session_id=' . $sessionId . '&guest_id=' . $guestId . '&slot_id=' . $slotId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Allows a user to update the assigned slot for a particular user within the active Guest Star session.
     *
     * @param string $broadcasterId The ID of the broadcaster running the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user_id in the user access token.
     * @param string $sessionId The ID of the Guest Star session in which to update slot settings.
     * @param string $sourceSlotId The slot assignment previously assigned to a user.
     * @param ?string $destinationSlotId The slot to move this user assignment to. If the destination slot is occupied, the user assigned will be swapped into source_slot_id.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the slot is updated.
     * @throws QueryException If the query fails.
     */
    public function updateGuestStarSlot(
        string $broadcasterId,
        string $moderatorId,
        string $sessionId,
        string $sourceSlotId,
        ?string $destinationSlotId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_SLOT . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&session_id=' . $sessionId . '&source_slot_id=' . $sourceSlotId;
        if ($destinationSlotId !== null) $url .= '&destination_slot_id=' . $destinationSlotId;
        $method = 'PATCH';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Allows a caller to remove a slot assignment from a user participating in an active Guest Star session.
     * This revokes their access to the session immediately and disables their access to publish or subscribe to media within the session.
     *
     * @param string $broadcasterId The ID of the broadcaster running the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param string $sessionId The ID of the Guest Star session in which to remove the slot assignment.
     * @param string $guestId The Twitch User ID corresponding to the guest to remove from the session.
     * @param string $slotId The slot ID representing the slot assignment to remove from the session.
     * @param ?string $shouldReinviteGuest Flag signaling that the guest should be reinvited to the session, sending them back to the invite queue.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the slot assignment is removed.
     * @throws QueryException If the query fails.
     */
    public function deleteGuestStarSlot(
        string $broadcasterId,
        string $moderatorId,
        string $sessionId,
        string $guestId,
        string $slotId,
        ?string $shouldReinviteGuest = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_SLOT . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&session_id=' . $sessionId . '&guest_id=' . $guestId . '&slot_id=' . $slotId;
        if ($shouldReinviteGuest !== null) $url .= '&should_reinvite_guest=' . $shouldReinviteGuest;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Allows a user to update slot settings for a particular guest within a Guest Star session.
     *
     * @param string $broadcasterId The ID of the broadcaster running the Guest Star session.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param string $sessionId The ID of the Guest Star session in which to update a slot’s settings.
     * @param string $slotId The slot assignment that has previously been assigned to a user.
     * @param array $settings The settings to update for the slot. Possible keys: 'is_audio_enabled', 'is_video_enabled', 'is_live', 'volume'.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the slot settings are updated.
     * @throws QueryException If the query fails.
     */
    public function updateGuestStarSlotSettings(
        string $broadcasterId,
        string $moderatorId,
        string $sessionId,
        string $slotId,
        array $settings,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::GUEST_STAR_SLOT_SETTINGS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&session_id=' . $sessionId . '&slot_id=' . $slotId;
        $method = 'PATCH';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $settings)
            : self::query($url, $method, $settings);
        return $promise;
    }

    /**
     * Gets information about the broadcaster’s current or most recent Hype Train event.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s running the Hype Train. This ID must match the User ID in the user access token.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 1.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of Hype Train events.
     * @throws QueryException If the query fails.
     */
    public function getHypeTrainEvents(
        string $broadcasterId,
        ?int $first = 1,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::HYPE_TRAIN_EVENTS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Checks whether AutoMod would flag the specified message for review.
     *
     * @param string $broadcasterId The ID of the broadcaster whose AutoMod settings and list of blocked terms are used to check the message. This ID must match the user ID in the access token.
     * @param array $messages The list of messages to check. Each message should be an associative array with 'msg_id' and 'msg_text' keys.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of messages and whether Twitch would approve them for chat.
     * @throws QueryException If the query fails.
     */
    public function checkAutoModStatus(
        string $broadcasterId,
        array $messages,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::AUTOMOD_STATUS . '?broadcaster_id=' . $broadcasterId;
        $method = 'POST';
        $data = ['data' => $messages];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Allows or denies the message that AutoMod flagged for review.
     *
     * @param string $userId The moderator who is approving or denying the held message. This ID must match the user ID in the access token.
     * @param string $msgId The ID of the message to allow or deny.
     * @param string $action The action to take for the message. Possible values are: 'ALLOW', 'DENY'.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the message is approved or denied.
     * @throws QueryException If the query fails.
     */
    public function manageHeldAutoModMessages(
        string $userId,
        string $msgId,
        string $action,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::HELD_AUTOMOD_MESSAGES;
        $method = 'POST';
        $data = [
            'user_id' => $userId,
            'msg_id' => $msgId,
            'action' => $action,
        ];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the broadcaster’s AutoMod settings.
     *
     * @param string $broadcasterId The ID of the broadcaster whose AutoMod settings you want to get.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the AutoMod settings.
     * @throws QueryException If the query fails.
     */
    public function getAutoModSettings(
        string $broadcasterId,
        string $moderatorId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::AUTOMOD_SETTINGS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the broadcaster’s AutoMod settings.
     *
     * @param string $broadcasterId The ID of the broadcaster whose AutoMod settings you want to update.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param array $settings The AutoMod settings to update. Possible keys: 'aggression', 'bullying', 'disability', 'misogyny', 'overall_level', 'race_ethnicity_or_religion', 'sex_based_terms', 'sexuality_sex_or_gender', 'swearing'.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the updated AutoMod settings.
     * @throws QueryException If the query fails.
     */
    public function updateAutoModSettings(
        string $broadcasterId,
        string $moderatorId,
        array $settings,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::AUTOMOD_SETTINGS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $settings)
            : self::query($url, $method, $settings);
        return $promise;
    }

    /**
     * Gets all users that the broadcaster banned or put in a timeout.
     *
     * @param string $broadcasterId The ID of the broadcaster whose list of banned users you want to get. This ID must match the user ID in the access token.
     * @param ?array $userIds A list of user IDs used to filter the results. To specify more than one ID, include this parameter for each user you want to get. You may specify a maximum of 100 IDs.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?string $before The cursor used to get the previous page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of banned users.
     * @throws QueryException If the query fails.
     */
    public function getBannedUsers(
        string $broadcasterId,
        ?array $userIds = null,
        ?int $first = 20,
        ?string $after = null,
        ?string $before = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($userIds !== null) foreach ($userIds as $userId) $queryParams['user_id'][] = $userId;
        if ($after !== null) $queryParams['after'] = $after;
        if ($before !== null) $queryParams['before'] = $before;
        $url = self::BANNED_USERS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Bans a user from participating in the specified broadcaster’s chat room or puts them in a timeout.
     *
     * @param string $broadcasterId The ID of the broadcaster whose chat room the user is being banned from.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param string $userId The ID of the user to ban or put in a timeout.
     * @param ?int $duration To ban a user indefinitely, don’t include this field. To put a user in a timeout, include this field and specify the timeout period, in seconds.
     * @param ?string $reason The reason you’re banning the user or putting them in a timeout. The text is user defined and is limited to a maximum of 500 characters.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of banned or timed-out users.
     * @throws QueryException If the query fails.
     */
    public function banUser(
        string $broadcasterId,
        string $moderatorId,
        string $userId,
        ?int $duration = null,
        ?string $reason = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::BANS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'POST';
        $data = [
            'data' => [
                'user_id' => $userId,
                'duration' => $duration,
                'reason' => $reason,
            ],
        ];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Removes the ban or timeout that was placed on the specified user.
     *
     * @param string $broadcasterId The ID of the broadcaster whose chat room the user is banned from chatting in.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param string $userId The ID of the user to remove the ban or timeout from.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the ban or timeout is removed.
     * @throws QueryException If the query fails.
     */
    public function unbanUser(
        string $broadcasterId,
        string $moderatorId,
        string $userId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::BANS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&user_id=' . $userId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of unban requests for a broadcaster’s channel.
     *
     * @param string $broadcasterId The ID of the broadcaster whose channel is receiving unban requests.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s unban requests. This ID must match the user ID in the user access token.
     * @param string $status Filter by a status. Possible values: 'pending', 'approved', 'denied', 'acknowledged', 'canceled'.
     * @param ?string $userId The ID used to filter what unban requests are returned.
     * @param ?string $after Cursor used to get next page of results.
     * @param ?int $first The maximum number of items to return per page in response.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of unban requests.
     * @throws QueryException If the query fails.
     */
    public function getUnbanRequests(
        string $broadcasterId,
        string $moderatorId,
        string $status,
        ?string $userId = null,
        ?string $after = null,
        ?int $first = 20,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'status' => $status,
            'first' => $first,
        ];
        if ($userId !== null) $queryParams['user_id'] = $userId;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::UNBAN_REQUESTS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Resolves an unban request by approving or denying it.
     *
     * @param string $broadcasterId The ID of the broadcaster whose channel is approving or denying the unban request.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s unban requests. This ID must match the user ID in the user access token.
     * @param string $unbanRequestId The ID of the unban request to resolve.
     * @param string $status Resolution status. Possible values: 'approved', 'denied'.
     * @param ?string $resolutionText Message supplied by the unban request resolver. The message is limited to a maximum of 500 characters.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the resolved unban request.
     * @throws QueryException If the query fails.
     */
    public function resolveUnbanRequests(
        string $broadcasterId,
        string $moderatorId,
        string $unbanRequestId,
        string $status,
        ?string $resolutionText = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::UNBAN_REQUESTS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&unban_request_id=' . $unbanRequestId;
        $method = 'PATCH';
        $data = ['status' => $status,];
        if ($resolutionText !== null) $data['resolution_text'] = $resolutionText;
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the broadcaster’s list of non-private, blocked words or phrases.
     *
     * @param string $broadcasterId The ID of the broadcaster whose blocked terms you’re getting.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of blocked terms.
     * @throws QueryException If the query fails.
     */
    public function getBlockedTerms(
        string $broadcasterId,
        string $moderatorId,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
            'first' => $first,
        ];
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::BLOCKED_TERMS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Adds a word or phrase to the broadcaster’s list of blocked terms.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the list of blocked terms.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param string $text The word or phrase to block from being used in the broadcaster’s chat room. The term must contain a minimum of 2 characters and may contain up to a maximum of 500 characters.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the added blocked term.
     * @throws QueryException If the query fails.
     */
    public function addBlockedTerm(
        string $broadcasterId,
        string $moderatorId,
        string $text,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::BLOCKED_TERMS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'POST';
        $data = ['text' => $text];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Removes the word or phrase from the broadcaster’s list of blocked terms.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the list of blocked terms.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param string $id The ID of the blocked term to remove from the broadcaster’s list of blocked terms.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the blocked term is removed.
     * @throws QueryException If the query fails.
     */
    public function removeBlockedTerm(
        string $broadcasterId,
        string $moderatorId,
        string $id,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::BLOCKED_TERMS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId . '&id=' . $id;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Removes a single chat message or all chat messages from the broadcaster’s chat room.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the chat room to remove messages from.
     * @param string $moderatorId The ID of the broadcaster or a user that has permission to moderate the broadcaster’s chat room. This ID must match the user ID in the user access token.
     * @param ?string $messageId The ID of the message to remove. If not specified, the request removes all messages in the broadcaster’s chat room.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the messages are removed.
     * @throws QueryException If the query fails.
     */
    public function deleteChatMessages(
        string $broadcasterId,
        string $moderatorId,
        ?string $messageId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
        ];
        if ($messageId !== null) $queryParams['message_id'] = $messageId;
        $url = self::CHAT_MESSAGES . '?' . http_build_query($queryParams);
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of channels that the specified user has moderator privileges in.
     *
     * @param string $userId A user’s ID. Returns the list of channels that this user has moderator privileges in. This ID must match the user ID in the user OAuth token.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?int $first The maximum number of items to return per page in the response. Minimum page size is 1 item per page and the maximum is 100. The default is 20.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of channels that the user has moderator privileges in.
     * @throws QueryException If the query fails.
     */
    public function getModeratedChannels(
        string $userId,
        ?string $after = null,
        ?int $first = 20,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'user_id' => $userId,
            'first' => $first,
        ];
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::MODERATED_CHANNELS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets all users allowed to moderate the broadcaster’s chat room.
     *
     * @param string $broadcasterId The ID of the broadcaster whose list of moderators you want to get. This ID must match the user ID in the access token.
     * @param ?array $userIds A list of user IDs used to filter the results. To specify more than one ID, include this parameter for each moderator you want to get. You may specify a maximum of 100 IDs.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of moderators.
     * @throws QueryException If the query fails.
     */
    public function getModerators(
        string $broadcasterId,
        ?array $userIds = null,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($userIds !== null) foreach ($userIds as $userId) $queryParams['user_id'][] = $userId;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::MODERATION_MODERATORS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Adds a moderator to the broadcaster’s chat room.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the chat room. This ID must match the user ID in the access token.
     * @param string $userId The ID of the user to add as a moderator in the broadcaster’s chat room.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the moderator is added.
     * @throws QueryException If the query fails.
     */
    public function addChannelModerator(
        string $broadcasterId,
        string $userId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::MODERATION_MODERATORS . '?broadcaster_id=' . $broadcasterId . '&user_id=' . $userId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Removes a moderator from the broadcaster’s chat room.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the chat room. This ID must match the user ID in the access token.
     * @param string $userId The ID of the user to remove as a moderator from the broadcaster’s chat room.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the moderator is removed.
     * @throws QueryException If the query fails.
     */
    public function removeChannelModerator(
        string $broadcasterId,
        string $userId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::MODERATION_MODERATORS . '?broadcaster_id=' . $broadcasterId . '&user_id=' . $userId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of the broadcaster’s VIPs.
     *
     * @param string $broadcasterId The ID of the broadcaster whose list of VIPs you want to get. This ID must match the user ID in the access token.
     * @param ?array $userIds Filters the list for specific VIPs. To specify more than one user, include the user_id parameter for each user to get. The maximum number of IDs that you may specify is 100.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of VIPs.
     * @throws QueryException If the query fails.
     */
    public function getVIPs(
        string $broadcasterId,
        ?array $userIds = null,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($userIds !== null) foreach ($userIds as $userId) $queryParams['user_id'][] = $userId;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::CHANNEL_VIPS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Adds the specified user as a VIP in the broadcaster’s channel.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s adding the user as a VIP. This ID must match the user ID in the access token.
     * @param string $userId The ID of the user to give VIP status to.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the VIP is added.
     * @throws QueryException If the query fails.
     */
    public function addChannelVIP(
        string $broadcasterId,
        string $userId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNEL_VIPS . '?broadcaster_id=' . $broadcasterId . '&user_id=' . $userId;
        $method = 'POST';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Removes the specified user as a VIP in the broadcaster’s channel.
     *
     * @param string $broadcasterId The ID of the broadcaster who owns the channel where the user has VIP status.
     * @param string $userId The ID of the user to remove VIP status from.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the VIP status is removed.
     * @throws QueryException If the query fails.
     */
    public function removeChannelVIP(
        string $broadcasterId,
        string $userId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::CHANNEL_VIPS . '?broadcaster_id=' . $broadcasterId . '&user_id=' . $userId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the broadcaster’s Shield Mode status.
     *
     * @param string $broadcasterId The ID of the broadcaster whose Shield Mode you want to activate or deactivate.
     * @param string $moderatorId The ID of the broadcaster or a user that is one of the broadcaster’s moderators. This ID must match the user ID in the access token.
     * @param bool $isActive A Boolean value that determines whether to activate Shield Mode. Set to true to activate Shield Mode; otherwise, false to deactivate Shield Mode.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the updated Shield Mode status.
     * @throws QueryException If the query fails.
     */
    public function updateShieldModeStatus(
        string $broadcasterId,
        string $moderatorId,
        bool $isActive,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SHIELD_MODE . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'PUT';
        $data = ['is_active' => $isActive,];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the broadcaster’s Shield Mode activation status.
     *
     * @param string $broadcasterId The ID of the broadcaster whose Shield Mode activation status you want to get.
     * @param string $moderatorId The ID of the broadcaster or a user that is one of the broadcaster’s moderators. This ID must match the user ID in the access token.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the Shield Mode status.
     * @throws QueryException If the query fails.
     */
    public function getShieldModeStatus(
        string $broadcasterId,
        string $moderatorId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'moderator_id' => $moderatorId,
        ];
        $url = self::SHIELD_MODE . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Warns a user in the specified broadcaster’s chat room, preventing them from chat interaction until the warning is acknowledged.
     *
     * @param string $broadcasterId The ID of the channel in which the warning will take effect.
     * @param string $moderatorId The ID of the twitch user who requested the warning.
     * @param string $userId The ID of the twitch user to be warned.
     * @param string $reason A custom reason for the warning. Max 500 chars.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the warning information.
     * @throws QueryException If the query fails.
     */
    public function warnChatUser(
        string $broadcasterId,
        string $moderatorId,
        string $userId,
        string $reason,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::WARNINGS . '?broadcaster_id=' . $broadcasterId . '&moderator_id=' . $moderatorId;
        $method = 'POST';
        $data = [
            'data' => [
                'user_id' => $userId,
                'reason' => $reason,
            ],
        ];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets a list of polls that the broadcaster created.
     *
     * @param string $broadcasterId The ID of the broadcaster that created the polls. This ID must match the user ID in the user access token.
     * @param ?array $ids A list of IDs that identify the polls to return. You may specify a maximum of 20 IDs.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 20 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of polls.
     * @throws QueryException If the query fails.
     */
    public function getPolls(
        string $broadcasterId,
        ?array $ids = null,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($ids !== null) foreach ($ids as $id) $queryParams['id'][] = $id;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::POLLS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Creates a poll that viewers in the broadcaster’s channel can vote on.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s running the poll. This ID must match the user ID in the user access token.
     * @param string $title The question that viewers will vote on. The question may contain a maximum of 60 characters.
     * @param array $choices A list of choices that viewers may choose from. The list must contain a minimum of 2 choices and up to a maximum of 5 choices.
     * @param int $duration The length of time (in seconds) that the poll will run for. The minimum is 15 seconds and the maximum is 1800 seconds (30 minutes).
     * @param ?bool $channelPointsVotingEnabled A Boolean value that indicates whether viewers may cast additional votes using Channel Points.
     * @param ?int $channelPointsPerVote The number of points that the viewer must spend to cast one additional vote.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the created poll.
     * @throws QueryException If the query fails.
     */
    public function createPoll(
        string $broadcasterId,
        string $title,
        array $choices,
        int $duration,
        ?bool $channelPointsVotingEnabled = false,
        ?int $channelPointsPerVote = 0,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::POLLS;
        $method = 'POST';
        $data = [
            'broadcaster_id' => $broadcasterId,
            'title' => $title,
            'choices' => $choices,
            'duration' => $duration,
            'channel_points_voting_enabled' => $channelPointsVotingEnabled,
            'channel_points_per_vote' => $channelPointsPerVote,
        ];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Ends an active poll.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s running the poll. This ID must match the user ID in the user access token.
     * @param string $pollId The ID of the poll to update.
     * @param string $status The status to set the poll to. Possible values are: TERMINATED, ARCHIVED.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the ended poll.
     * @throws QueryException If the query fails.
     */
    public function endPoll(
        string $broadcasterId,
        string $pollId,
        string $status,
        ?LoopInterface $loop = null
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
     * Gets a list of Channel Points Predictions that the broadcaster created.
     *
     * @param string $broadcasterId The ID of the broadcaster whose predictions you want to get. This ID must match the user ID in the user access token.
     * @param ?array $ids A list of IDs that identify the predictions to return. You may specify a maximum of 25 IDs.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 25 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of predictions.
     * @throws QueryException If the query fails.
     */
    public function getPredictions(
        string $broadcasterId,
        ?array $ids = null,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($ids !== null) foreach ($ids as $id) $queryParams['id'][] = $id;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::PREDICTIONS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Creates a Channel Points Prediction.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s running the prediction. This ID must match the user ID in the user access token.
     * @param string $title The question that the broadcaster is asking. The title is limited to a maximum of 45 characters.
     * @param array $outcomes The list of possible outcomes that the viewers may choose from. The list must contain a minimum of 2 choices and up to a maximum of 10 choices.
     * @param int $predictionWindow The length of time (in seconds) that the prediction will run for. The minimum is 30 seconds and the maximum is 1800 seconds (30 minutes).
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the created prediction.
     * @throws QueryException If the query fails.
     */
    public function createPrediction(
        string $broadcasterId,
        string $title,
        array $outcomes,
        int $predictionWindow,
        ?LoopInterface $loop = null
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
     * Locks, resolves, or cancels a Channel Points Prediction.
     *
     * @param string $broadcasterId The ID of the broadcaster that’s running the prediction. This ID must match the user ID in the user access token.
     * @param string $predictionId The ID of the prediction to update.
     * @param string $status The status to set the prediction to. Possible values are: RESOLVED, CANCELED, LOCKED.
     * @param ?string $winningOutcomeId The ID of the winning outcome. Required if status is RESOLVED.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the updated prediction.
     * @throws QueryException If the query fails.
     */
    public function endPrediction(
        string $broadcasterId,
        string $predictionId,
        string $status,
        ?string $winningOutcomeId = null,
        ?LoopInterface $loop = null
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
     * Gets the broadcaster’s streaming schedule.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the streaming schedule you want to get.
     * @param ?array $ids The IDs of the scheduled segments to return. You may specify a maximum of 100 IDs.
     * @param ?string $startTime The UTC date and time that identifies when in the broadcaster’s schedule to start returning segments. Specify the date and time in RFC3339 format.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 25 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the broadcaster’s streaming schedule.
     * @throws QueryException If the query fails.
     */
    public function getSchedule(
        string $broadcasterId,
        ?array $ids = null,
        ?string $startTime = null,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($ids !== null) foreach ($ids as $id) $queryParams['id'][] = $id;
        if ($startTime !== null) $queryParams['start_time'] = $startTime;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::SCHEDULE . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Adds a single or recurring broadcast to the broadcaster’s streaming schedule.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the schedule to add the broadcast segment to. This ID must match the user ID in the user access token.
     * @param string $startTime The date and time that the broadcast segment starts. Specify the date and time in RFC3339 format.
     * @param string $timezone The time zone where the broadcast takes place. Specify the time zone using IANA time zone database format.
     * @param int $duration The length of time, in minutes, that the broadcast is scheduled to run. The duration must be in the range 30 through 1380 (23 hours).
     * @param bool $isRecurring A Boolean value that determines whether the broadcast recurs weekly.
     * @param ?string $categoryId The ID of the category that best represents the broadcast’s content.
     * @param ?string $title The broadcast’s title. The title may contain a maximum of 140 characters.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the added broadcast segment.
     * @throws QueryException If the query fails.
     */
    public function createScheduleSegment(
        string $broadcasterId,
        string $startTime,
        string $timezone,
        int $duration,
        bool $isRecurring,
        ?string $categoryId = null,
        ?string $title = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SCHEDULE_SEGMENT . '?broadcaster_id=' . $broadcasterId;
        $method = 'POST';
        $data = [
            'start_time' => $startTime,
            'timezone' => $timezone,
            'duration' => $duration,
            'is_recurring' => $isRecurring,
        ];
        if ($categoryId !== null) $data['category_id'] = $categoryId;
        if ($title !== null) $data['title'] = $title;
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Updates a scheduled broadcast segment.
     *
     * @param string $broadcasterId The ID of the broadcaster who owns the broadcast segment to update. This ID must match the user ID in the user access token.
     * @param string $segmentId The ID of the broadcast segment to update.
     * @param ?string $startTime The date and time that the broadcast segment starts. Specify the date and time in RFC3339 format.
     * @param ?int $duration The length of time, in minutes, that the broadcast is scheduled to run. The duration must be in the range 30 through 1380 (23 hours).
     * @param ?string $categoryId The ID of the category that best represents the broadcast’s content.
     * @param ?string $title The broadcast’s title. The title may contain a maximum of 140 characters.
     * @param ?bool $isCanceled A Boolean value that indicates whether the broadcast is canceled.
     * @param ?string $timezone The time zone where the broadcast takes place. Specify the time zone using IANA time zone database format.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the updated broadcast segment.
     * @throws QueryException If the query fails.
     */
    public function updateScheduleSegment(
        string $broadcasterId,
        string $segmentId,
        ?string $startTime = null,
        ?int $duration = null,
        ?string $categoryId = null,
        ?string $title = null,
        ?bool $isCanceled = null,
        ?string $timezone = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SCHEDULE_SEGMENT . '?broadcaster_id=' . $broadcasterId . '&id=' . $segmentId;
        $method = 'PATCH';
        $data = [];
        if ($startTime !== null) $data['start_time'] = $startTime;
        if ($duration !== null) $data['duration'] = $duration;
        if ($categoryId !== null) $data['category_id'] = $categoryId;
        if ($title !== null) $data['title'] = $title;
        if ($isCanceled !== null) $data['is_canceled'] = $isCanceled;
        if ($timezone !== null) $data['timezone'] = $timezone;
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Removes a broadcast segment from the broadcaster’s streaming schedule.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the streaming schedule. This ID must match the user ID in the user access token.
     * @param string $segmentId The ID of the broadcast segment to remove.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the broadcast segment is removed.
     * @throws QueryException If the query fails.
     */
    public function deleteScheduleSegment(
        string $broadcasterId,
        string $segmentId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SCHEDULE_SEGMENT . '?broadcaster_id=' . $broadcasterId . '&id=' . $segmentId;
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the broadcaster’s schedule settings, such as scheduling a vacation.
     *
     * @param string $broadcasterId The ID of the broadcaster whose schedule settings you want to update. The ID must match the user ID in the user access token.
     * @param ?bool $isVacationEnabled A Boolean value that indicates whether the broadcaster has scheduled a vacation.
     * @param ?string $vacationStartTime The UTC date and time of when the broadcaster’s vacation starts. Specify the date and time in RFC3339 format.
     * @param ?string $vacationEndTime The UTC date and time of when the broadcaster’s vacation ends. Specify the date and time in RFC3339 format.
     * @param ?string $timezone The time zone that the broadcaster broadcasts from. Specify the time zone using IANA time zone database format.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the schedule settings are updated.
     * @throws QueryException If the query fails.
     */
    public function updateScheduleSettings(
        string $broadcasterId,
        ?bool $isVacationEnabled = null,
        ?string $vacationStartTime = null,
        ?string $vacationEndTime = null,
        ?string $timezone = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::SCHEDULE_SEGMENT . '?broadcaster_id=' . $broadcasterId;
        $method = 'PATCH';
        $data = [];
        if ($isVacationEnabled !== null) $data['is_vacation_enabled'] = $isVacationEnabled;
        if ($vacationStartTime !== null) $data['vacation_start_time'] = $vacationStartTime;
        if ($vacationEndTime !== null) $data['vacation_end_time'] = $vacationEndTime;
        if ($timezone !== null) $data['timezone'] = $timezone;
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets the games or categories that match the specified query.
     *
     * @param string $query The URI-encoded search string.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of games or categories that match the query.
     * @throws QueryException If the query fails.
     */
    public function searchCategories(
        string $query,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'query' => $query,
            'first' => $first,
        ];
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::SEARCH_CATEGORIES . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the channels that match the specified query and have streamed content within the past 6 months.
     *
     * @param string $query The URI-encoded search string.
     * @param ?bool $liveOnly A Boolean value that determines whether the response includes only channels that are currently streaming live.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of channels that match the query.
     * @throws QueryException If the query fails.
     */
    public function searchChannels(
        string $query,
        ?bool $liveOnly = false,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'query' => $query,
            'live_only' => $liveOnly,
            'first' => $first,
        ];
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::SEARCH_CHANNELS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the channel’s stream key.
     *
     * @param string $broadcasterId The ID of the broadcaster that owns the channel. The ID must match the user ID in the access token.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the channel’s stream key.
     * @throws QueryException If the query fails.
     */
    public function getStreamKey(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['broadcaster_id' => $broadcasterId,];
        $url = self::STREAM_KEY . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of all streams.
     *
     * @param ?array $userIds A list of user IDs used to filter the list of streams. Returns only the streams of those users that are broadcasting. You may specify a maximum of 100 IDs.
     * @param ?array $userLogins A list of user login names used to filter the list of streams. Returns only the streams of those users that are broadcasting. You may specify a maximum of 100 login names.
     * @param ?array $gameIds A list of game (category) IDs used to filter the list of streams. Returns only the streams that are broadcasting the game (category). You may specify a maximum of 100 IDs.
     * @param ?string $type The type of stream to filter the list of streams by. Possible values are 'all' and 'live'. The default is 'all'.
     * @param ?array $languages A list of language codes used to filter the list of streams. Returns only streams that broadcast in the specified languages. You may specify a maximum of 100 language codes.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $before The cursor used to get the previous page of results.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of streams.
     * @throws QueryException If the query fails.
     */
    public function getStreams(
        ?array $userIds = null,
        ?array $userLogins = null,
        ?array $gameIds = null,
        ?string $type = 'all',
        ?array $languages = null,
        ?int $first = 20,
        ?string $before = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'type' => $type,
            'first' => $first,
        ];
        if ($userIds !== null) {
            foreach ($userIds as $userId) {
                $queryParams['user_id'][] = $userId;
            }
        }
        if ($userLogins !== null) foreach ($userLogins as $userLogin) $queryParams['user_login'][] = $userLogin;
        if ($gameIds !== null) foreach ($gameIds as $gameId) $queryParams['game_id'][] = $gameId;
        if ($languages !== null) foreach ($languages as $language) $queryParams['language'][] = $language;
        if ($before !== null) $queryParams['before'] = $before;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::STREAMS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }
    
    /**
     * Gets the list of broadcasters that the user follows and that are streaming live.
     *
     * @param string $userId The ID of the user whose list of followed streams you want to get. This ID must match the user ID in the access token.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 100.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of live streams of broadcasters that the specified user follows.
     * @throws QueryException If the query fails.
     */
    public function getFollowedStreams(
        string $userId,
        ?int $first = 100,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'user_id' => $userId,
            'first' => $first,
        ];
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::FOLLOWED_STREAMS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Adds a marker to a live stream.
     *
     * @param string $userId The ID of the broadcaster that’s streaming content. This ID must match the user ID in the access token or the user in the access token must be one of the broadcaster’s editors.
     * @param ?string $description A short description of the marker to help the user remember why they marked the location. The maximum length of the description is 140 characters.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the marker that was added.
     * @throws QueryException If the query fails.
     */
    public function createStreamMarker(
        string $userId,
        ?string $description = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::STREAM_MARKERS;
        $method = 'POST';
        $data = ['user_id' => $userId,];
        if ($description !== null) $data['description'] = $description;
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
        return $promise;
    }

    /**
     * Gets a list of markers from the user’s most recent stream or from the specified VOD/video.
     *
     * @param ?string $userId A user ID. The request returns the markers from this user’s most recent video. This ID must match the user ID in the access token or the user in the access token must be one of the broadcaster’s editors.
     * @param ?string $videoId A video on demand (VOD)/video ID. The request returns the markers from this VOD/video. The user in the access token must own the video or the user must be one of the broadcaster’s editors.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $before The cursor used to get the previous page of results.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of markers.
     * @throws QueryException If the query fails.
     */
    public function getStreamMarkers(
        ?string $userId = null,
        ?string $videoId = null,
        ?int $first = 20,
        ?string $before = null,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['first' => $first,];
        if ($userId !== null) $queryParams['user_id'] = $userId;
        if ($videoId !== null) $queryParams['video_id'] = $videoId;
        if ($before !== null) $queryParams['before'] = $before;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::STREAM_MARKERS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of users that subscribe to the specified broadcaster.
     *
     * @param string $broadcasterId The broadcaster’s ID. This ID must match the user ID in the access token.
     * @param ?array $userIds Filters the list to include only the specified subscribers. You may specify a maximum of 100 subscribers.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100 items per page. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?string $before The cursor used to get the previous page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of users that subscribe to the broadcaster.
     * @throws QueryException If the query fails.
     */
    public function getBroadcasterSubscriptions(
        string $broadcasterId,
        ?array $userIds = null,
        ?int $first = 20,
        ?string $after = null,
        ?string $before = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($userIds !== null) foreach ($userIds as $userId) $queryParams['user_id'][] = $userId;
        if ($after !== null) $queryParams['after'] = $after;
        if ($before !== null) $queryParams['before'] = $before;
        $url = self::BROADCASTER_SUBSCRIPTIONS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Checks whether the user subscribes to the broadcaster’s channel.
     *
     * @param string $broadcasterId The ID of a partner or affiliate broadcaster.
     * @param string $userId The ID of the user that you’re checking to see whether they subscribe to the broadcaster.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the user's subscription status.
     * @throws QueryException If the query fails.
     */
    public function checkUserSubscription(
        string $broadcasterId,
        string $userId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'user_id' => $userId,
        ];
        $url = self::USER_SUBSCRIPTION . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of all stream tags that Twitch defines.
     *
     * @param ?array $tagIds The IDs of the tags to get. Used to filter the list of tags. You may specify a maximum of 100 IDs.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of stream tags.
     * @throws QueryException If the query fails.
     */
    public function getAllStreamTags(
        ?array $tagIds = null,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['first' => $first];
        if ($tagIds !== null) foreach ($tagIds as $tagId) $queryParams['tag_id'][] = $tagId;
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::ALL_STREAM_TAGS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the list of stream tags that the broadcaster or Twitch added to their channel.
     *
     * @param string $broadcasterId The ID of the broadcaster whose stream tags you want to get.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of stream tags.
     * @throws QueryException If the query fails.
     */
    public function getStreamTags(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['broadcaster_id' => $broadcasterId];
        $url = self::STREAM_TAGS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the list of Twitch teams that the broadcaster is a member of.
     *
     * @param string $broadcasterId The ID of the broadcaster whose teams you want to get.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of teams that the broadcaster is a member of.
     * @throws QueryException If the query fails.
     */
    public function getChannelTeams(
        string $broadcasterId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['broadcaster_id' => $broadcasterId];
        $url = self::CHANNEL_TEAMS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets information about the specified Twitch team.
     *
     * @param ?string $name The name of the team to get. This parameter and the id parameter are mutually exclusive; you must specify the team’s name or ID but not both.
     * @param ?string $id The ID of the team to get. This parameter and the name parameter are mutually exclusive; you must specify the team’s name or ID but not both.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the team's information.
     * @throws QueryException If the query fails.
     */
    public function getTeam(
        ?string $name = null,
        ?string $id = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        if ($name === null && $id === null) throw new QueryException('Either name or id must be specified.');
        if ($name !== null && $id !== null) throw new QueryException('Specify either name or id, but not both.');
        $queryParams = [];
        if ($name !== null) $queryParams['name'] = $name;
        if ($id !== null) $queryParams['id'] = $id;
        $url = self::TEAMS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets information about one or more users.
     *
     * @param ?array $ids The IDs of the users to get. You may specify a maximum of 100 IDs.
     * @param ?array $logins The login names of the users to get. You may specify a maximum of 100 login names.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of users.
     * @throws QueryException If the query fails.
     */
    public function getUsers(
        ?array $ids = null,
        ?array $logins = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [];
        if ($ids !== null) foreach ($ids as $id) $queryParams['id'][] = $id;
        if ($logins !== null) foreach ($logins as $login) $queryParams['login'][] = $login;
        $url = self::USERS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Updates the specified user’s information.
     *
     * @param string $description The string to update the channel’s description to. The description is limited to a maximum of 300 characters.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the updated user information.
     * @throws QueryException If the query fails.
     */
    public function updateUser(
        string $description = '',
        ?LoopInterface $loop = null
    ): PromiseInterface {
        if (strlen($description) > 300) return reject(new \Exception('Description is limited to a maximum of 300 characters'));
        $queryParams = ['description' => $description];
        $url = self::USERS . '?' . http_build_query($queryParams);
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets the list of users that the broadcaster has blocked.
     *
     * @param string $broadcasterId The ID of the broadcaster whose list of blocked users you want to get.
     * @param ?int $first The maximum number of items to return per page in the response. The minimum page size is 1 item per page and the maximum is 100. The default is 20.
     * @param ?string $after The cursor used to get the next page of results.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of blocked users.
     * @throws QueryException If the query fails.
     */
    public function getUserBlockList(
        string $broadcasterId,
        ?int $first = 20,
        ?string $after = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [
            'broadcaster_id' => $broadcasterId,
            'first' => $first,
        ];
        if ($after !== null) $queryParams['after'] = $after;
        $url = self::USER_BLOCKS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }
    
    /**
     * Blocks the specified user from interacting with or having contact with the broadcaster.
     *
     * @param string $targetUserId The ID of the user to block. The API ignores the request if the broadcaster has already blocked the user.
     * @param ?string $sourceContext The location where the harassment took place that is causing the broadcaster to block the user. Possible values are: chat, whisper.
     * @param ?string $reason The reason that the broadcaster is blocking the user. Possible values are: harassment, spam, other.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the user is blocked.
     * @throws QueryException If the query fails.
     */
    public function blockUser(
        string $targetUserId,
        ?string $sourceContext = null,
        ?string $reason = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['target_user_id' => $targetUserId,];
        if ($sourceContext !== null) $queryParams['source_context'] = $sourceContext;
        if ($reason !== null) $queryParams['reason'] = $reason;
        $url = self::USER_BLOCKS . '?' . http_build_query($queryParams);
        $method = 'PUT';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }
    
    /**
     * Removes the user from the broadcaster’s list of blocked users.
     *
     * @param string $targetUserId The ID of the user to remove from the broadcaster’s list of blocked users. The API ignores the request if the broadcaster hasn’t blocked the user.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves when the user is unblocked.
     * @throws QueryException If the query fails.
     */
    public function unblockUser(
        string $targetUserId,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = ['target_user_id' => $targetUserId,];
        $url = self::USER_BLOCKS . '?' . http_build_query($queryParams);
        $method = 'DELETE';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }

    /**
     * Gets a list of all extensions (both active and inactive) that the broadcaster has installed.
     *
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the list of extensions that the user has installed.
     * @throws QueryException If the query fails.
     */
    public function getUserExtensionsList(
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::USERS_EXTENSIONS_LIST;
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }
    
    /**
     * Gets the active extensions that the broadcaster has installed for each configuration.
     *
     * @param ?string $userId The ID of the broadcaster whose active extensions you want to get.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the active extensions that the broadcaster has installed.
     * @throws QueryException If the query fails.
     */
    public function getUserActiveExtensions(
        ?string $userId = null,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $queryParams = [];
        if ($userId !== null) $queryParams['user_id'] = $userId;
        $url = self::USERS_EXTENSIONS . '?' . http_build_query($queryParams);
        $method = 'GET';
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method)
            : self::query($url, $method);
        return $promise;
    }
    
    /**
     * Updates an installed extension’s information.
     *
     * @param array $data The extensions to update. The data field is a dictionary of extension types.
     * @param ?LoopInterface $loop Optional event loop interface.
     * @return PromiseInterface A promise that resolves to the updated extensions.
     * @throws QueryException If the query fails.
     */
    public function updateUserExtensions(
        array $data,
        ?LoopInterface $loop = null
    ): PromiseInterface {
        $url = self::USERS_EXTENSIONS;
        $method = 'PUT';
        $data = ['data' => $data];
        $promise = $loop instanceof LoopInterface
            ? self::queryWithRateLimitHandling($loop, $url, $method, $data)
            : self::query($url, $method, $data);
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