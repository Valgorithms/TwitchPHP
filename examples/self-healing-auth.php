<?php

/*
 * A client that keeps itself authorized.
 *
 *   php examples/self-healing-auth.php
 *
 * Wires up the two halves of the token lifecycle:
 *
 *  - `token_store`  persists every rotation, so the credentials survive a
 *    restart. Twitch invalidates the old refresh token each time it issues
 *    a new one, so without this a long-lived client eventually locks itself
 *    out of its own account.
 *
 *  - `reauthorize`  is the last resort, used only when refreshing fails
 *    (typically because the refresh token itself went stale). It prompts
 *    here on the console; a bot would DM the operator instead.
 *
 * With both in place a 401 on any Helix call is recovered transparently and
 * the request is retried — apart from a missing *scope*, which no new token
 * can fix and which therefore surfaces immediately.
 */

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use Twitch\Auth\DeviceCodeReauthorizer;
use Twitch\Auth\EnvFileTokenStore;
use Twitch\Http\Exceptions\MissingScopeException;
use Twitch\Http\OAuth;
use Twitch\Twitch;

$envPath = __DIR__ . '/../.env';

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || $trimmed[0] === '#' || ! str_contains($trimmed, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $trimmed, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

$scopes = json_decode($env['twitch_scope'] ?? '[]', true) ?: [];

$oauth = new OAuth($env['twitch_client_id'], $env['twitch_client_secret'], Loop::get());

$twitch = new Twitch([
    'client_id'     => $env['twitch_client_id'],
    'client_secret' => $env['twitch_client_secret'],

    // Tokens are read from here at startup and written back on every change,
    // so `token` / `refresh_token` need not be passed explicitly at all.
    'token_store'   => new EnvFileTokenStore($envPath),

    'reauthorize'   => new DeviceCodeReauthorizer($oauth, $scopes, function (array $device): void {
        echo "\n!! re-authorization needed\n";
        echo "   open {$device['verification_uri']} and enter {$device['user_code']}\n\n";
    }),
]);

$twitch->on('token_refreshed', fn () => print("token refreshed and persisted\n"));
$twitch->on('reauthorized', fn () => print("re-authorized and persisted\n"));

$twitch->on('ready', function (Twitch $t) {
    printf("ready as %s (#%s) with %d scopes\n\n", $t->getLogin(), $t->getUserId(), count($t->getScopes()));

    $t->users->me()
        ->then(fn ($user) => printf("me: %s\n", $user->display_name))
        ->catch(function (MissingScopeException $e): void {
            // Not recoverable by re-authorizing mid-flight: the grant itself
            // is too narrow. Surfaced immediately, with the scopes named.
            printf("needs one of: %s\n", implode(', ', $e->scopes));
        })
        ->catch(fn (\Throwable $e) => printf("failed: %s\n", $e->getMessage()))
        ->finally(fn () => $t->close());
});

$twitch->on('error', function (\Throwable $e) use ($twitch): void {
    echo 'startup failed: ' . $e->getMessage() . "\n";
    $twitch->close();
});

$twitch->run();
