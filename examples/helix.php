<?php

/*
 * Helix REST — look up users, channels and streams.
 *
 *   TWITCH_CLIENT_ID=xxx TWITCH_CLIENT_SECRET=yyy php examples/helix.php
 *
 * An app access token (client_id + client_secret) is enough for public data.
 */

require __DIR__ . '/../vendor/autoload.php';

use Twitch\Twitch;

$twitch = new Twitch([
    'client_id' => getenv('TWITCH_CLIENT_ID') ?: exit("set TWITCH_CLIENT_ID\n"),
    'client_secret' => getenv('TWITCH_CLIENT_SECRET') ?: exit("set TWITCH_CLIENT_SECRET\n"),
]);

$twitch->on('ready', function (Twitch $twitch): void {
    $twitch->users->fetchByLogin('twitchdev')
        ->then(function ($user) use ($twitch) {
            printf("twitchdev is #%s, joined %s\n", $user->id, $user->created_at->toFormattedDateString());

            return $twitch->streams->live(['user_id' => $user->id]);
        })
        ->then(function ($streams) use ($twitch) {
            echo $streams->count() > 0 ? "…and is live right now.\n" : "…and is offline.\n";

            return $twitch->games->top(5);
        })
        ->then(function ($games): void {
            echo "\nTop categories:\n";
            foreach ($games as $i => $game) {
                printf("  %d. %s\n", $i + 1, $game->name);
            }
        })
        ->finally(fn () => $twitch->close());
});

$twitch->run();
