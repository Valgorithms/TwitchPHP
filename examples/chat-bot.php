<?php

/*
 * IRC chat bot — `!ping`, a `!dice` roll on a cooldown, a mod-only `!so`, and a
 * "hello" greeter. Uses Twitch\Chat\CommandClient for aliases / cooldowns /
 * permissions on top of the plain command routing.
 *
 *   TWITCH_CLIENT_ID=xxx TWITCH_ACCESS_TOKEN=yyy TWITCH_REFRESH_TOKEN=zzz \
 *   TWITCH_NICK=mybot TWITCH_CHANNELS=twitchdev php examples/chat-bot.php
 *
 * The user token needs chat:read and chat:edit.
 */

require __DIR__ . '/../vendor/autoload.php';

use Twitch\Chat\Command;
use Twitch\Chat\CommandClient;
use Twitch\Twitch;

$twitch = new Twitch([
    'client_id' => getenv('TWITCH_CLIENT_ID') ?: exit("set TWITCH_CLIENT_ID\n"),
    'token' => getenv('TWITCH_ACCESS_TOKEN') ?: exit("set TWITCH_ACCESS_TOKEN\n"),
    'refresh_token' => getenv('TWITCH_REFRESH_TOKEN') ?: null,
    'nick' => getenv('TWITCH_NICK') ?: exit("set TWITCH_NICK\n"),
    'channels' => array_filter(explode(',', getenv('TWITCH_CHANNELS') ?: 'twitchdev')),
    'command_prefix' => '!',
]);

$commands = new CommandClient($twitch);

$commands->command('ping', fn ($m) => $m->reply('pong 🏓'), description: 'Health check');

$commands->command('dice', fn ($m) => $m->reply('🎲 ' . random_int(1, 6)), cooldown: 10, description: 'Roll a die');

$commands->command('so', function ($m, array $args) use ($twitch): void {
    if ($args === []) {
        $m->reply('usage: !so <channel>');

        return;
    }
    $twitch->chat->shoutout($m->tags['room-id'], ltrim($args[0], '@'), $twitch->getUserId());
}, aliases: ['shoutout'], permission: Command::MODERATOR, cooldown: 30, description: 'Shout a channel out');

$twitch->on('ready', function (Twitch $twitch): void {
    $twitch->getIrc()->on('chat', function ($message): void {
        if (stripos($message->content, 'hello') !== false) {
            $message->say("hi, {$message->display_name}!");
        }
    });

    echo "bot online\n";
});

$twitch->run();
