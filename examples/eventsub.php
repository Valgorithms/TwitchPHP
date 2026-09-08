<?php

/*
 * EventSub over WebSocket — follow chat, follows and stream up/down for one
 * channel.
 *
 *   TWITCH_CLIENT_ID=xxx TWITCH_ACCESS_TOKEN=yyy TWITCH_REFRESH_TOKEN=zzz \
 *   TWITCH_BROADCASTER_ID=123 php examples/eventsub.php
 *
 * The user token needs at least: user:read:chat, moderator:read:followers.
 */

require __DIR__ . '/../vendor/autoload.php';

use Twitch\Twitch;

$broadcasterId = getenv('TWITCH_BROADCASTER_ID') ?: exit("set TWITCH_BROADCASTER_ID\n");

$twitch = new Twitch([
    'client_id' => getenv('TWITCH_CLIENT_ID') ?: exit("set TWITCH_CLIENT_ID\n"),
    'token' => getenv('TWITCH_ACCESS_TOKEN') ?: exit("set TWITCH_ACCESS_TOKEN\n"),
    'refresh_token' => getenv('TWITCH_REFRESH_TOKEN') ?: null,
    'eventsub' => true,
]);

$twitch->on('ready', function (Twitch $twitch) use ($broadcasterId): void {
    $es = $twitch->getEventSub();
    $self = $twitch->getUserId();

    // Typed helpers pick the right condition shape and subscription version.
    $es->onChatMessage($broadcasterId, $self);
    $es->onFollow($broadcasterId, $self);   // channel.follow v2
    $es->onStreamChange($broadcasterId);    // stream.online + stream.offline

    // Or the generic form for anything without a helper:
    // $es->subscribe(\Twitch\EventSub\SubscriptionTypes::CHANNEL_CHEER, ['broadcaster_user_id' => $broadcasterId]);

    echo "listening…\n";
});

$twitch->on('eventsub.channel.chat.message', function (array $event): void {
    printf("[chat] %s: %s\n", $event['chatter_user_name'], $event['message']['text']);
});
$twitch->on('eventsub.channel.follow', function (array $event): void {
    printf("[follow] %s\n", $event['user_name']);
});
$twitch->on('eventsub.stream.online', fn () => print("[stream] went live\n"));
$twitch->on('eventsub.stream.offline', fn () => print("[stream] went offline\n"));

$twitch->run();
