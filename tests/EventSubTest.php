<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;

use function React\Async\await;

use Twitch\EventSub\EventSub;
use Twitch\EventSub\SubscriptionTypes;
use Twitch\Twitch;

final class EventSubTest extends TestCase
{
    private Twitch $twitch;

    private ScriptedHttp $http;

    private EventSub $eventSub;

    protected function setUp(): void
    {
        $this->twitch = new Twitch(['client_id' => 'cid']);
        $this->http = new ScriptedHttp();
        (new \ReflectionProperty(Twitch::class, 'http'))->setValue($this->twitch, $this->http);

        $this->eventSub = new EventSub($this->twitch);
        // Pretend the WebSocket handshake finished.
        (new \ReflectionProperty(EventSub::class, 'sessionId'))->setValue($this->eventSub, 'sess-1');
    }

    /** @return array<string, mixed> The body of the last POST to eventsub/subscriptions. */
    private function lastSubscribeBody(): array
    {
        foreach (array_reverse($this->http->requests) as [$method, $endpoint, $content]) {
            if ($method === 'POST' && $endpoint === 'eventsub/subscriptions') {
                return $content ?? [];
            }
        }

        self::fail('no subscribe request was made');
    }

    public function testVersionResolvesFromTheTypeWhenNotGiven(): void
    {
        self::assertSame('1', SubscriptionTypes::version(SubscriptionTypes::CHANNEL_CHAT_MESSAGE));
        self::assertSame('2', SubscriptionTypes::version(SubscriptionTypes::CHANNEL_FOLLOW));
        self::assertSame('2', SubscriptionTypes::version(SubscriptionTypes::CHANNEL_UPDATE));
        self::assertSame('beta', SubscriptionTypes::version(SubscriptionTypes::GUEST_STAR_SESSION_BEGIN));
        self::assertSame('1', SubscriptionTypes::version('something.unknown'));
    }

    public function testSubscribeSendsResolvedVersionAndWebsocketTransport(): void
    {
        $this->http->script[] = ['data' => [['id' => 'sub-1', 'status' => 'enabled']]];

        $row = await($this->eventSub->subscribe(SubscriptionTypes::CHANNEL_FOLLOW, [
            'broadcaster_user_id' => '1',
            'moderator_user_id' => '2',
        ]));

        $body = $this->lastSubscribeBody();
        self::assertSame('channel.follow', $body['type']);
        self::assertSame('2', $body['version']);
        self::assertSame(['method' => 'websocket', 'session_id' => 'sess-1'], $body['transport']);
        self::assertSame('sub-1', $row['id']);
    }

    public function testOnChatMessageBuildsTheRightCondition(): void
    {
        $this->http->script[] = ['data' => [['id' => 'sub-x']]];

        await($this->eventSub->onChatMessage('11111', '22222'));

        $body = $this->lastSubscribeBody();
        self::assertSame('channel.chat.message', $body['type']);
        self::assertSame(['broadcaster_user_id' => '11111', 'user_id' => '22222'], $body['condition']);
    }

    public function testOnPointsRedemptionScopesToARewardWhenGiven(): void
    {
        $this->http->script[] = ['data' => [[]]];
        $this->http->script[] = ['data' => [[]]];

        await($this->eventSub->onPointsRedemption('1'));
        await($this->eventSub->onPointsRedemption('1', 'reward-7'));

        $bodies = array_values(array_filter(
            array_map(static fn ($r) => $r[2], $this->http->requests),
            static fn ($c) => ($c['type'] ?? null) === SubscriptionTypes::CHANNEL_POINTS_CUSTOM_REWARD_REDEMPTION_ADD,
        ));
        self::assertArrayNotHasKey('reward_id', $bodies[0]['condition']);
        self::assertSame('reward-7', $bodies[1]['condition']['reward_id']);
    }

    public function testSubscribeManySettlesAllAndSurvivesAFailure(): void
    {
        // `throw` is single-shot and fires on the very next request, so the
        // first spec fails and the second succeeds.
        $this->http->throw = new \RuntimeException('boom');
        $this->http->script[] = ['data' => [['id' => 'a']]];

        $results = await($this->eventSub->subscribeMany([
            [SubscriptionTypes::STREAM_ONLINE, ['broadcaster_user_id' => '1']],
            [SubscriptionTypes::STREAM_OFFLINE, ['broadcaster_user_id' => '1']],
        ]));

        self::assertCount(2, $results);
        self::assertNull($results[0]);
        self::assertSame('a', $results[1]['id']);
    }

    public function testDesiredSubscriptionsDeduplicatesAndForgetRemoves(): void
    {
        $this->http->script = [['data' => [[]]], ['data' => [[]]], ['data' => [[]]]];

        await($this->eventSub->subscribe(SubscriptionTypes::STREAM_ONLINE, ['broadcaster_user_id' => '1']));
        await($this->eventSub->subscribe(SubscriptionTypes::STREAM_ONLINE, ['broadcaster_user_id' => '1'])); // dupe
        await($this->eventSub->subscribe(SubscriptionTypes::STREAM_OFFLINE, ['broadcaster_user_id' => '1']));

        self::assertSame(
            ['stream.online' => ['broadcaster_user_id' => '1'], 'stream.offline' => ['broadcaster_user_id' => '1']],
            $this->eventSub->desiredSubscriptions(),
        );

        $this->eventSub->forget(SubscriptionTypes::STREAM_ONLINE, ['broadcaster_user_id' => '1']);
        self::assertSame(['stream.offline'], array_keys($this->eventSub->desiredSubscriptions()));
    }

    public function testSubscribeBeforeReadyQueuesAndRejects(): void
    {
        (new \ReflectionProperty(EventSub::class, 'sessionId'))->setValue($this->eventSub, null);

        $rejected = false;
        $this->eventSub->subscribe(SubscriptionTypes::STREAM_ONLINE, ['broadcaster_user_id' => '9'])
            ->then(null, static function () use (&$rejected): void {
                $rejected = true;
            });

        self::assertTrue($rejected);
        self::assertArrayHasKey('stream.online', $this->eventSub->desiredSubscriptions());
        self::assertSame([], $this->http->requests, 'nothing goes over HTTP until the session is ready');
    }

    public function testAllTypesAreDistinctStringsWithADot(): void
    {
        $all = SubscriptionTypes::all();
        self::assertGreaterThan(60, count($all));
        self::assertSame($all, array_unique($all));
        foreach ($all as $t) {
            self::assertStringContainsString('.', $t);
        }
    }
}
