<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;
use React\EventLoop\StreamSelectLoop;
use Twitch\Auth\DeviceCodeReauthorizer;
use Twitch\Twitch;

final class DeviceCodeReauthorizerTest extends TestCase
{
    public function testFailedSetupDoesNotStayCached(): void
    {
        $client = new Twitch(['client_id' => 'cid', 'client_secret' => 'synthetic']);
        $browser = ScriptedOAuth::attach($client);
        $browser->grants = [new \RuntimeException('network down'), new \RuntimeException('network down')];
        $reauthorizer = new DeviceCodeReauthorizer($client->getOAuth(), ['chat:read']);
        $first = $reauthorizer->reauthorize();
        $first->then(null, static fn () => null);
        $second = $reauthorizer->reauthorize();
        $second->then(null, static fn () => null);
        self::assertNotSame($first, $second);
        self::assertCount(2, $browser->calls);
    }

    public function testConcurrentCallersShareThePromptAndGrant(): void
    {
        $loop = new StreamSelectLoop();
        $client = new Twitch(['client_id' => 'cid', 'client_secret' => 'synthetic', 'loop' => $loop]);
        $browser = ScriptedOAuth::attach($client);
        $browser->grants = [
            ['device_code' => 'device', 'user_code' => 'code', 'verification_uri' => 'https://synthetic/verify', 'expires_in' => 10, 'interval' => 1],
            ['access_token' => 'granted'],
        ];
        $prompts = 0;
        $reauthorizer = new DeviceCodeReauthorizer($client->getOAuth(), ['chat:read'], function () use (&$prompts): void {
            ++$prompts;
        }, $loop);
        $first = $reauthorizer->reauthorize();
        self::assertSame($first, $reauthorizer->reauthorize());
        $token = null;
        $first->then(function ($value) use (&$token, $loop): void {
            $token = $value;
            $loop->stop();
        });
        $loop->run();
        self::assertSame('granted', $token['access_token']);
        self::assertSame(1, $prompts);
    }
}
