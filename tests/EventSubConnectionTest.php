<?php

namespace Twitch\Tests;

use Evenement\EventEmitterTrait;
use PHPUnit\Framework\TestCase;
use React\EventLoop\StreamSelectLoop;
use Twitch\EventSub\EventSub;
use Twitch\Twitch;

final class EventSubConnectionTest extends TestCase
{
    private StreamSelectLoop $loop;

    private EventSub $client;

    private ScriptedHttp $http;

    private Twitch $twitch;

    private array $sockets = [];

    private int $failNext = 0;

    private int $attempts = 0;

    protected function setUp(): void
    {
        $this->loop = new StreamSelectLoop();
        $this->twitch = new Twitch(['client_id' => 'cid', 'loop' => $this->loop]);
        $this->http = new ScriptedHttp();
        (new \ReflectionProperty(Twitch::class, 'http'))->setValue($this->twitch, $this->http);
        $this->client = new EventSub($this->twitch, [
            'retry_delays' => [0.01, 0.01, 0.01], 'keep_trying_every' => 0.02,
            'welcome_timeout' => 0.03, 'connect_timeout' => 0.03,
            'connector' => function () {
                ++$this->attempts;
                if ($this->failNext > 0) {
                    --$this->failNext;

                    return \React\Promise\reject(new \RuntimeException('network down'));
                }

                return \React\Promise\resolve($this->sockets[] = new EventSubSocket());
            },
        ]);
    }

    protected function tearDown(): void
    {
        $this->client->close();
    }

    public function testReadySubscriberIsCreatedOnce(): void
    {
        $this->client->connect()->then(fn () => $this->client->onStreamChange('42'));
        $this->welcome(0, 'initial');
        self::assertCount(2, $this->http->requests);
    }

    public function testHandoffPreservesSessionAndSubscriptionsAfterOldSocketCloses(): void
    {
        $this->client->connect();
        $this->welcome(0, 'old');
        $this->client->subscribe('stream.online', ['broadcaster_user_id' => '42']);
        $this->frame(0, 'session_reconnect', ['session' => ['reconnect_url' => 'wss://synthetic/reconnect']]);
        self::assertFalse($this->sockets[0]->closed);
        $this->welcome(1, 'new');
        self::assertTrue($this->sockets[0]->closed);
        self::assertSame('new', $this->client->getSessionId());
        self::assertCount(1, $this->http->requests);
        $this->runFor(0.02);
        self::assertSame(2, $this->attempts);
    }

    public function testNetworkFailuresContinueRetrying(): void
    {
        $this->client->connect();
        $this->welcome(0, 'initial');
        $this->failNext = 2;
        $this->sockets[0]->close();
        $this->runFor(0.035);
        self::assertGreaterThanOrEqual(4, $this->attempts);
        $this->welcome(1, 'replacement');
        self::assertSame('replacement', $this->client->getSessionId());
    }

    public function testSilenceExpiresAndKeepaliveResetsTheDeadline(): void
    {
        $this->client->connect();
        $this->welcome(0, 'initial', 0.04);
        $this->runFor(0.025);
        $this->frame(0, 'session_keepalive');
        $this->runFor(0.025);
        self::assertSame('initial', $this->client->getSessionId());
        $this->runFor(0.03);
        self::assertTrue($this->sockets[0]->closed);
        self::assertGreaterThan(1, $this->attempts);
    }

    public function testCloseCancelsRetryAndPendingWelcome(): void
    {
        $this->client->connect();
        $this->welcome(0, 'initial');
        $this->sockets[0]->close();
        $this->client->close();
        $this->runFor(0.04);
        self::assertSame(1, $this->attempts);
        self::assertNull($this->client->getSessionId());
    }

    public function testMissingWelcomeRejectsInsteadOfHanging(): void
    {
        $failure = null;
        $this->client->connect()->then(null, function (\Throwable $e) use (&$failure): void {
            $failure = $e;
        });
        $this->runFor(0.04);
        self::assertInstanceOf(\RuntimeException::class, $failure);
        self::assertTrue($this->sockets[0]->closed);
    }

    public function testDuplicateNotificationsAreDeliveredOnce(): void
    {
        $this->client->connect();
        $this->welcome(0, 'initial');
        $delivered = 0;
        $this->twitch->on('eventsub', function () use (&$delivered): void {
            ++$delivered;
        });
        $this->frame(0, 'notification', ['subscription' => ['type' => 'stream.online'], 'event' => ['id' => 'event']], 'same-id');
        $this->frame(0, 'notification', ['subscription' => ['type' => 'stream.online'], 'event' => ['id' => 'event']], 'same-id');
        self::assertSame(1, $delivered);
    }

    public function testUnsubscribeRemovesReconnectIntent(): void
    {
        $this->client->connect();
        $this->welcome(0, 'initial');
        $this->http->script[] = ['data' => [['id' => 'sub-1']]];
        $this->client->subscribe('stream.online', ['broadcaster_user_id' => '42']);
        $this->client->unsubscribe('sub-1');
        self::assertSame([], $this->client->desiredSubscriptions());
    }

    public function testDifferentChannelsRemainInDesiredSpecifications(): void
    {
        $this->client->connect();
        $this->welcome(0, 'initial');
        $this->client->subscribe('stream.online', ['broadcaster_user_id' => '1']);
        $this->client->subscribe('stream.online', ['broadcaster_user_id' => '2']);
        self::assertCount(2, $this->client->desiredSubscriptionSpecs());
    }

    private function welcome(int $socket, string $id, float $timeout = 10): void
    {
        $this->frame($socket, 'session_welcome', ['session' => ['id' => $id, 'keepalive_timeout_seconds' => $timeout]]);
    }

    private function frame(int $socket, string $type, array $payload = [], ?string $id = null): void
    {
        $this->sockets[$socket]->emit('message', [json_encode(['metadata' => ['message_type' => $type, 'message_id' => $id], 'payload' => $payload])]);
    }

    private function runFor(float $time): void
    {
        $this->loop->addTimer($time, fn () => $this->loop->stop());
        $this->loop->run();
    }
}

final class EventSubSocket
{
    use EventEmitterTrait;

    public bool $closed = false;

    public function close(): void
    {
        if (! $this->closed) {
            $this->closed = true;
            $this->emit('close', [1000, 'closed']);
        }
    }
}
