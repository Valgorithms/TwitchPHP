<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Auth;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\reject;

use Twitch\Http\OAuth;

/**
 * Re-authorizes through the OAuth device-code flow.
 *
 * Needs no redirect URI and no local HTTP listener: it asks Twitch for a
 * short user code, hands that to a prompt callback for display, then polls
 * until the user approves or the code expires.
 *
 * The prompt is injected rather than printed so the flow works wherever the
 * client runs — `echo` from a CLI, a DM from a bot, a desktop notification:
 *
 * ```php
 * new DeviceCodeReauthorizer($oauth, $scopes, function (array $device): void {
 *     printf("go to %s and enter %s\n", $device['verification_uri'], $device['user_code']);
 * });
 * ```
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class DeviceCodeReauthorizer implements ReauthorizerInterface
{
    private LoopInterface $loop;

    /** The in-flight attempt, so concurrent callers share one prompt. */
    private ?PromiseInterface $pending = null;

    /**
     * @param list<string>                     $scopes The scopes to request.
     * @param (callable(array<string, mixed>): void)|null $prompt Shown the `{verification_uri, user_code, expires_in, interval}` payload.
     */
    public function __construct(
        private readonly OAuth $oauth,
        private readonly array $scopes,
        private $prompt = null,
        ?LoopInterface $loop = null,
    ) {
        $this->loop = $loop ?? Loop::get();
    }

    public function reauthorize(): PromiseInterface
    {
        return $this->pending ??= $this->start()->finally(function (): void {
            $this->pending = null;
        });
    }

    /** @return PromiseInterface<array<string, mixed>> */
    private function start(): PromiseInterface
    {
        return $this->oauth->deviceCode($this->scopes)->then(function (array $device): PromiseInterface {
            if ($this->prompt !== null) {
                ($this->prompt)($device);
            }

            $deferred = new Deferred();
            $this->poll(
                $deferred,
                (string) $device['device_code'],
                max(1, (int) ($device['interval'] ?? 5)),
                time() + (int) ($device['expires_in'] ?? 1800),
            );

            return $deferred->promise();
        });
    }

    /**
     * @param Deferred<array<string, mixed>> $deferred
     */
    private function poll(Deferred $deferred, string $deviceCode, int $interval, int $deadline): void
    {
        if (time() >= $deadline) {
            $deferred->reject(new \RuntimeException('Device code expired before it was approved'));

            return;
        }

        $this->loop->addTimer($interval, function () use ($deferred, $deviceCode, $interval, $deadline): void {
            $this->oauth->pollDeviceToken($deviceCode)->then(
                static fn (array $token) => $deferred->resolve($token),
                function (\Throwable $e) use ($deferred, $deviceCode, $interval, $deadline): void {
                    $message = $e->getMessage();

                    // The user has not finished approving yet — keep waiting.
                    if (str_contains($message, 'authorization_pending')) {
                        $this->poll($deferred, $deviceCode, $interval, $deadline);

                        return;
                    }

                    // Twitch is asking us to back off.
                    if (str_contains($message, 'slow_down')) {
                        $this->poll($deferred, $deviceCode, $interval + 5, $deadline);

                        return;
                    }

                    $deferred->reject($e);
                },
            );
        });
    }

    /**
     * A reauthorizer that never succeeds — the default, so a headless client
     * surfaces the original 401 instead of hanging on a prompt nobody sees.
     */
    public static function disabled(): ReauthorizerInterface
    {
        return new class () implements ReauthorizerInterface {
            public function reauthorize(): PromiseInterface
            {
                return reject(new \RuntimeException('No reauthorizer is configured'));
            }
        };
    }
}
