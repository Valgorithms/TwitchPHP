<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Tests;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use PHPUnit\Framework\TestCase;
use React\EventLoop\StreamSelectLoop;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

use Twitch\Chat\Irc;
use Twitch\Twitch;

/**
 * The chat client keeping its connection: noticing when it is gone, getting it
 * back, and saying so when it cannot.
 */
final class IrcTest extends TestCase
{
    private StreamSelectLoop $loop;

    private Twitch $twitch;

    /** @var list<FakeSocket> Every socket opened, oldest first. */
    private array $sockets = [];

    /** How many of the next connection attempts fail, as a dead network makes them. */
    private int $failNext = 0;

    /** @var array<string, list<array<int, mixed>>> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->loop = new StreamSelectLoop();
        $this->twitch = new Twitch(['client_id' => 'cid', 'loop' => $this->loop]);

        foreach (['chat.connected', 'chat.disconnected', 'chat.reconnecting', 'chat.reconnect_failed', 'chat.auth_failed'] as $event) {
            $this->twitch->on($event, function (...$args) use ($event): void {
                $this->events[$event][] = $args;
            });
        }
    }

    public function testADroppedConnectionIsReplacedAndItsChannelsRejoined(): void
    {
        $irc = $this->connected();

        $this->sockets[0]->close(1006, 'gone');
        $this->waitFor(fn () => count($this->sockets) === 2);
        $this->welcome($this->sockets[1]);

        $this->assertTrue($irc->isConnected());
        $this->assertCount(2, $this->events['chat.connected']);
        $this->assertSame([1006, 'closed by Twitch (1006 gone)'], array_slice($this->events['chat.disconnected'][0], 0, 2));
        $this->assertContains("JOIN #somechannel\r\n", $this->sockets[1]->sent, 'a reconnect rejoins the channels');
    }

    public function testAttemptsKeepComingWhileTheNetworkIsDown(): void
    {
        $irc = $this->connected(['retry_delays' => [0.01, 0.01, 0.01, 0.01, 0.01]]);

        $this->failNext = 3;
        $this->sockets[0]->close(1006, '');
        $this->waitFor(fn () => count($this->sockets) === 2);
        $this->welcome($this->sockets[1]);

        $this->assertTrue($irc->isConnected());
        $this->assertSame([1, 2, 3, 4], array_map(static fn (array $e) => $e[0], $this->events['chat.reconnecting']));
        $this->assertArrayNotHasKey('chat.reconnect_failed', $this->events);
    }

    public function testRunningOutOfRetriesSaysSoOnceAndKeepsTryingSlowly(): void
    {
        $irc = $this->connected(['keep_trying_every' => 0.2]);

        $this->failNext = PHP_INT_MAX;
        $this->sockets[0]->close(1006, '');
        $this->waitFor(fn () => isset($this->events['chat.reconnect_failed']));

        [$attempts, $reason] = $this->events['chat.reconnect_failed'][0];
        $this->assertSame(3, $attempts, 'as many attempts as retry_delays has');
        $this->assertSame('network down', $reason);
        $this->assertFalse($irc->isConnected());

        // It keeps trying after saying so, without saying so again.
        $this->failNext = 0;
        $this->waitFor(fn () => count($this->sockets) === 2, 2.0);
        $this->welcome($this->sockets[1]);
        $this->assertTrue($irc->isConnected());
        $this->assertCount(1, $this->events['chat.reconnect_failed']);
    }

    public function testReconnectTriesAtOnceAfterGivingUp(): void
    {
        $irc = $this->connected(['keep_trying_every' => 60.0]);

        $this->failNext = PHP_INT_MAX;
        $this->sockets[0]->close(1006, '');
        $this->waitFor(fn () => isset($this->events['chat.reconnect_failed']));

        // Pressed while the network is still down: this attempt fails, and says why.
        $failed = null;
        $irc->reconnect()->then(null, function (\Throwable $e) use (&$failed): void {
            $failed = $e->getMessage();
        });
        $this->waitFor(fn () => $failed !== null);
        $this->assertSame('network down', $failed);

        $this->failNext = 0;
        $connected = false;
        $irc->reconnect()->then(function () use (&$connected): void {
            $connected = true;
        });
        $this->waitFor(fn () => count($this->sockets) === 2);
        $this->welcome($this->sockets[1]);

        $this->assertTrue($connected);
        $this->assertTrue($irc->isConnected());
    }

    public function testReconnectWhileConnectedIsNothingToDo(): void
    {
        $irc = $this->connected();

        $resolved = false;
        $irc->reconnect()->then(function () use (&$resolved): void {
            $resolved = true;
        });

        $this->assertTrue($resolved);
        $this->assertCount(1, $this->sockets);
    }

    public function testASilentConnectionIsPingedAndReplacedWhenNothingAnswers(): void
    {
        $irc = $this->connected(['ping_after' => 0.1, 'pong_timeout' => 0.1]);

        // Nothing arrives, and nothing ever closes: what a dropped network looks like.
        $this->waitFor(fn () => in_array("PING :tmi.twitch.tv\r\n", $this->sockets[0]->sent, true));
        $this->waitFor(fn () => count($this->sockets) === 2);

        $this->assertStringStartsWith('no reply to PING', $this->events['chat.disconnected'][0][1]);
        $this->assertTrue($this->sockets[0]->closed, 'the dead socket is let go of');

        $this->welcome($this->sockets[1]);
        $this->assertTrue($irc->isConnected());
    }

    public function testAnAnsweredPingKeepsTheConnection(): void
    {
        $irc = $this->connected(['ping_after' => 0.1, 'pong_timeout' => 0.2]);
        $socket = $this->sockets[0];
        $socket->onSend = function (string $line) use ($socket): void {
            if ($line === "PING :tmi.twitch.tv\r\n") {
                $socket->receive(":tmi.twitch.tv PONG tmi.twitch.tv :tmi.twitch.tv\r\n");
            }
        };

        $this->runFor(0.8);

        $this->assertTrue($irc->isConnected());
        $this->assertCount(1, $this->sockets);
        $this->assertArrayNotHasKey('chat.disconnected', $this->events);
    }

    public function testTwitchAskingForAReconnectGetsOneAtOnce(): void
    {
        $irc = $this->connected(['retry_delays' => [30]]);

        $this->sockets[0]->receive(":tmi.twitch.tv RECONNECT\r\n");
        $this->waitFor(fn () => count($this->sockets) === 2, 0.5);
        $this->welcome($this->sockets[1]);

        $this->assertTrue($irc->isConnected());
        $this->assertSame('Twitch asked for a reconnect', $this->events['chat.disconnected'][0][1]);
    }

    public function testTheNextLoginUsesTheNewestToken(): void
    {
        $irc = $this->connected(['retry_delays' => [30]]);

        $this->sockets[0]->close(1006, '');
        $this->waitFor(fn () => isset($this->events['chat.reconnecting']));

        // A new token while a retry waits: it logs in with it now, not in 30s.
        $irc->setToken('newtoken');
        $this->waitFor(fn () => count($this->sockets) === 2, 0.5);

        $this->assertContains("PASS oauth:newtoken\r\n", $this->sockets[1]->sent);
    }

    public function testARefusedLoginIsReportedOncePerOutage(): void
    {
        $this->connected(['retry_delays' => [0.01, 0.01, 30]]);

        $this->sockets[0]->close(1006, '');
        foreach ([1, 2] as $i) {
            $this->waitFor(fn () => count($this->sockets) === $i + 1);
            $this->sockets[$i]->receive(":tmi.twitch.tv NOTICE * :Login authentication failed\r\n");
        }
        $this->waitFor(fn () => count($this->events['chat.reconnecting'] ?? []) === 3);

        $this->assertCount(1, $this->events['chat.auth_failed']);
        $this->assertSame('Login authentication failed', $this->events['chat.auth_failed'][0][0]);
    }

    public function testARefusedLoginGetsANewTokenAndLogsInWithIt(): void
    {
        // No refresh token, so recovery re-authorizes; the new token is "fresh".
        $this->twitch = new Twitch([
            'client_id' => 'cid',
            'loop' => $this->loop,
            'reauthorize' => new FreshTokenReauthorizer(),
        ]);
        $irc = $this->irc(['retry_delays' => [0.01, 30]]);
        // What connectIrc() does: the client hands new tokens to its own chat connection.
        (new \ReflectionProperty(Twitch::class, 'irc'))->setValue($this->twitch, $irc);
        $irc->connect();
        $this->waitFor(fn () => count($this->sockets) === 1);
        $this->welcome($this->sockets[0]);

        $this->sockets[0]->close(1006, '');
        $this->waitFor(fn () => count($this->sockets) === 2);
        $this->sockets[1]->receive(":tmi.twitch.tv NOTICE * :Login authentication failed\r\n");

        // The next attempt would be 30s off; the new token brings it forward.
        $this->waitFor(fn () => count($this->sockets) === 3, 1.0);
        $this->assertContains("PASS oauth:fresh\r\n", $this->sockets[2]->sent);
    }

    public function testCloseStopsTheRetrying(): void
    {
        $irc = $this->connected(['retry_delays' => [0.05]]);

        $this->sockets[0]->close(1006, '');
        $irc->close();
        $this->runFor(0.3);

        $this->assertCount(1, $this->sockets);
        $this->assertFalse($irc->isConnected());
    }

    public function testTheFirstConnectionIsTheCallersToRetry(): void
    {
        $this->failNext = 1;
        $irc = $this->irc();

        $error = null;
        $irc->connect()->then(null, function (\Throwable $e) use (&$error): void {
            $error = $e->getMessage();
        });
        $this->runFor(0.2);

        $this->assertSame('network down', $error);
        $this->assertSame([], $this->sockets, 'nothing is retried behind the caller');
        $this->assertArrayNotHasKey('chat.reconnecting', $this->events);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** @param array<string, mixed> $options */
    private function irc(array $options = []): Irc
    {
        return new Irc($this->twitch, 'Bot', 'token', ['#somechannel'], '!', $options + [
            'retry_delays' => [0.01, 0.01, 0.01],
            'jitter' => 0.0,
            'connector' => function (string $url): PromiseInterface {
                if ($this->failNext > 0) {
                    --$this->failNext;

                    return reject(new \RuntimeException('network down'));
                }

                return resolve($this->sockets[] = new FakeSocket());
            },
        ]);
    }

    /** @param array<string, mixed> $options */
    private function connected(array $options = []): Irc
    {
        $irc = $this->irc($options);
        $irc->connect();
        $this->waitFor(fn () => count($this->sockets) === 1);
        $this->welcome($this->sockets[0]);
        $this->assertTrue($irc->isConnected());

        return $irc;
    }

    private function welcome(FakeSocket $socket): void
    {
        $socket->receive(":tmi.twitch.tv 001 bot :Welcome, GLHF!\r\n");
    }

    private function waitFor(callable $done, float $timeout = 2.0): void
    {
        $deadline = microtime(true) + $timeout;
        $check = $this->loop->addPeriodicTimer(0.005, function () use ($done, $deadline): void {
            if ($done() || microtime(true) >= $deadline) {
                $this->loop->stop();
            }
        });
        if (! $done()) {
            $this->loop->run();
        }
        $this->loop->cancelTimer($check);

        $this->assertTrue((bool) $done(), 'timed out waiting');
    }

    private function runFor(float $seconds): void
    {
        $this->loop->addTimer($seconds, fn () => $this->loop->stop());
        $this->loop->run();
    }
}

/** A re-authorization that always succeeds, with the token "fresh". */
final class FreshTokenReauthorizer implements \Twitch\Auth\ReauthorizerInterface
{
    public function reauthorize(): PromiseInterface
    {
        return resolve(['access_token' => 'fresh', 'refresh_token' => 'r']);
    }
}

/** Stands in for Pawl's WebSocket: records what is sent, and says what Twitch would. */
final class FakeSocket implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var list<string> */
    public array $sent = [];

    public bool $closed = false;

    /** @var (callable(string): void)|null */
    public $onSend = null;

    public function send(string $data): void
    {
        $this->sent[] = $data;
        if ($this->onSend !== null) {
            ($this->onSend)($data);
        }
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->emit('close', [$code, $reason]);
    }

    public function receive(string $lines): void
    {
        $this->emit('message', [$lines]);
    }
}
