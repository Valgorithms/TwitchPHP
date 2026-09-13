<?php

/*
 * One-shot re-authorization via the OAuth device-code flow.
 *
 *   php examples/reauthorize.php
 *
 * Reads client_id / client_secret and the desired scope list from .env,
 * walks you through granting them, then writes the resulting tokens back
 * to .env (after taking a backup).
 *
 * Tokens are never printed — only scope counts and confirmation.
 */

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use Twitch\Http\OAuth;

const ENV_PATH = __DIR__ . '/../.env';

// ── read .env ──────────────────────────────────────────────────────────
function envRead(): array
{
    $out = [];
    foreach (file(ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === '#' || ! str_contains($trimmed, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $trimmed, 2);
        $out[trim($k)] = trim($v, " \t\"'");
    }

    return $out;
}

/** Rewrites the given keys in place, preserving comments, order and unrelated lines. */
function envWrite(array $updates): void
{
    $lines = file(ENV_PATH, FILE_IGNORE_NEW_LINES);
    $seen = [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === '#' || ! str_contains($trimmed, '=')) {
            continue;
        }
        $key = trim(explode('=', $trimmed, 2)[0]);
        if (array_key_exists($key, $updates)) {
            $lines[$i] = $key . '=' . $updates[$key];
            $seen[$key] = true;
        }
    }

    foreach ($updates as $key => $value) {
        if (! isset($seen[$key])) {
            $lines[] = $key . '=' . $value;
        }
    }

    file_put_contents(ENV_PATH, implode("\n", $lines) . "\n");
}

$env = envRead();

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

printf("requesting %d scopes for client %s…\n\n", count($scopes), substr($env['twitch_client_id'], 0, 6) . '…');

$oauth = new OAuth($env['twitch_client_id'], $env['twitch_client_secret'], Loop::get());

$oauth->deviceCode($scopes)->then(
    function (array $device) use ($oauth, $scopes): void {
        echo "┌───────────────────────────────────────────────\n";
        echo "│ 1. open:  {$device['verification_uri']}\n";
        echo "│ 2. enter: {$device['user_code']}\n";
        echo "│ 3. approve ALL the listed permissions\n";
        echo "└───────────────────────────────────────────────\n\n";
        printf("waiting (expires in %ds)", $device['expires_in']);

        $interval = max(1, (int) ($device['interval'] ?? 5));
        $deadline = time() + (int) $device['expires_in'];

        $poll = function () use (&$poll, $oauth, $device, $interval, $deadline, $scopes): void {
            if (time() >= $deadline) {
                echo "\n\ndevice code expired — re-run the script\n";
                Loop::stop();

                return;
            }

            $oauth->pollDeviceToken($device['device_code'])->then(
                function (array $token) use ($oauth, $scopes): void {
                    echo "\n\ngranted. verifying…\n";

                    $oauth->validate($token['access_token'])->then(
                        function (array $info) use ($token, $scopes): void {
                            $granted = $info['scopes'] ?? [];

                            copy(ENV_PATH, ENV_PATH . '.bak');

                            envWrite([
                                'twitch_access_token'  => $token['access_token'],
                                'twitch_refresh_token' => $token['refresh_token'] ?? '',
                                'twitch_expires_in'    => (string) ($token['expires_in'] ?? ''),
                                'twitch_token_type'    => $token['token_type'] ?? 'bearer',
                                'twitch_scope'         => json_encode(array_values($granted)),
                            ]);

                            printf("\nlogin:   %s (#%s)\n", $info['login'] ?? '?', $info['user_id'] ?? '?');
                            printf("granted: %d of %d requested scopes\n", count($granted), count($scopes));

                            $missing = array_diff($scopes, $granted);
                            if ($missing) {
                                printf("\nNOT granted (%d):\n", count($missing));
                                foreach ($missing as $m) {
                                    echo "  {$m}\n";
                                }
                            }

                            echo "\n.env updated (backup at .env.bak)\n";
                            Loop::stop();
                        },
                        function (\Throwable $e): void {
                            echo 'validation failed: ' . $e->getMessage() . "\n";
                            Loop::stop();
                        },
                    );
                },
                function (\Throwable $e) use (&$poll, $interval): void {
                    $msg = $e->getMessage();

                    if (str_contains($msg, 'authorization_pending')) {
                        echo '.';
                        Loop::addTimer($interval, $poll);

                        return;
                    }
                    if (str_contains($msg, 'slow_down')) {
                        echo '~';
                        Loop::addTimer($interval + 5, $poll);

                        return;
                    }

                    echo "\n\nstopped: {$msg}\n";
                    Loop::stop();
                },
            );
        };

        Loop::addTimer($interval, $poll);
    },
    function (\Throwable $e): void {
        echo 'could not start device flow: ' . $e->getMessage() . "\n";
        Loop::stop();
    },
);

Loop::run();
