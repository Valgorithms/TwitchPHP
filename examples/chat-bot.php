<?php

/*
 * IRC chat bot — replies to `!ping` and greets on "hello".
 *
 *   TWITCH_CLIENT_ID=xxx TWITCH_ACCESS_TOKEN=yyy TWITCH_REFRESH_TOKEN=zzz \
 *   TWITCH_NICK=mybot TWITCH_CHANNELS=twitchdev php examples/chat-bot.php
 *
 * The user token needs chat:read and chat:edit.
 */

require __DIR__ . '/../vendor/autoload.php';

use Twitch\Twitch;

$twitch = new Twitch([
    'client_id' => getenv('TWITCH_CLIENT_ID') ?: exit("set TWITCH_CLIENT_ID\n"),
    'token' => getenv('TWITCH_ACCESS_TOKEN') ?: exit("set TWITCH_ACCESS_TOKEN\n"),
    'refresh_token' => getenv('TWITCH_REFRESH_TOKEN') ?: null,
    'nick' => getenv('TWITCH_NICK') ?: exit("set TWITCH_NICK\n"),
    'channels' => array_filter(explode(',', getenv('TWITCH_CHANNELS') ?: 'twitchdev')),
    'command_prefix' => '!',
]);

$twitch->on('ready', function (Twitch $twitch): void {
    $irc = $twitch->getIrc();

    $irc->registerCommand('ping', function ($message): void {
        $message->reply('pong 🏓');
    });

    $irc->on('chat', function ($message): void {
        if (stripos($message->content, 'hello') !== false) {
            $message->say("hi, {$message->display_name}!");
        }
    });

    echo "bot online\n";
});

$twitch->run();
