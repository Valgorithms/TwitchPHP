<?php

/*
 * One-shot re-authorization via the OAuth device-code flow.
 *
 *   php examples/reauthorize.php
 *
 * Reads client_id / client_secret and the desired scope list from .env,
 * walks you through granting them, then writes the resulting tokens back
 * to .env.
 *
 * Tokens are never printed — only scope counts and confirmation.
 */

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use Twitch\Auth\DeviceCodeReauthorizer;
use Twitch\Auth\EnvFileTokenStore;
use Twitch\Http\OAuth;

const ENV_PATH = __DIR__ . '/../.env';

$env = [];
foreach (file(ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || $trimmed[0] === '#' || ! str_contains($trimmed, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $trimmed, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

foreach (['twitch_client_id', 'twitch_client_secret'] as $required) {
    if (empty($env[$required])) {
        exit("missing {$required} in .env\n");
    }
}

// The scope wishlist: whatever is already recorded in .env.
$scopes = json_decode($env['twitch_scope'] ?? '[]', true);
if (! is_array($scopes) || $scopes === []) {
    exit("no scope list found in .env (expected twitch_scope as a JSON array)\n");
}
$scopes = array_values(array_unique($scopes));

printf("requesting %d scopes for client %s…\n", count($scopes), substr($env['twitch_client_id'], 0, 6) . '…');

$oauth = new OAuth($env['twitch_client_id'], $env['twitch_client_secret'], Loop::get());
$store = new EnvFileTokenStore(ENV_PATH);

$reauthorizer = new DeviceCodeReauthorizer($oauth, $scopes, function (array $device): void {
    echo "\n┌───────────────────────────────────────────────\n";
    echo "│ 1. open:  {$device['verification_uri']}\n";
    echo "│ 2. enter: {$device['user_code']}\n";
    echo "│ 3. approve ALL the listed permissions\n";
    echo "└───────────────────────────────────────────────\n\n";
    printf("waiting (expires in %ds)…\n", $device['expires_in']);
});

$reauthorizer->reauthorize()->then(
    function (array $token) use ($oauth, $store, $scopes): void {
        // Trust what Twitch says was granted, not what we asked for.
        $oauth->validate($token['access_token'])->then(
            function (array $info) use ($token, $store, $scopes): void {
                $granted = $info['scopes'] ?? [];
                $store->save($token + ['scope' => $granted]);

                printf("\nlogin:   %s (#%s)\n", $info['login'] ?? '?', $info['user_id'] ?? '?');
                printf("granted: %d of %d requested scopes\n", count($granted), count($scopes));

                if ($missing = array_diff($scopes, $granted)) {
                    printf("\nNOT granted (%d):\n", count($missing));
                    foreach ($missing as $scope) {
                        echo "  {$scope}\n";
                    }
                }

                echo "\n.env updated\n";
            },
            fn (\Throwable $e) => print('validation failed: ' . $e->getMessage() . "\n"),
        );
    },
    fn (\Throwable $e) => print("\nstopped: " . $e->getMessage() . "\n"),
);

Loop::run();
