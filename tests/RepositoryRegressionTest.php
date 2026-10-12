<?php

namespace Twitch\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

use function React\Async\await;

use React\Http\Browser;
use Twitch\Parts\Channel;
use Twitch\Parts\Conduit;
use Twitch\Parts\CustomReward;
use Twitch\Parts\User;
use Twitch\Twitch;

final class RepositoryRegressionTest extends TestCase
{
    private Twitch $twitch;

    private ScriptedHttp $http;

    protected function setUp(): void
    {
        $this->twitch = new Twitch(['client_id' => 'cid']);
        $this->http = new ScriptedHttp();
        (new \ReflectionProperty(Twitch::class, 'http'))->setValue($this->twitch, $this->http);
    }

    public function testChannelSaveAddsItsBroadcasterContext(): void
    {
        $part = $this->twitch->getFactory()->part(Channel::class, ['broadcaster_id' => '42', 'title' => 'updated'], true);
        await($this->twitch->channels->save($part));
        self::assertSame(['PATCH', 'channels?broadcaster_id=42', ['title' => 'updated']], array_slice($this->http->requests[0], 0, 3));
    }

    public function testAllFollowsStringPaginationCursors(): void
    {
        $this->http->script = [
            ['data' => [['id' => '1']], 'pagination' => 'next-page'],
            ['data' => [['id' => '2']], 'pagination' => ''],
        ];
        $parts = await($this->twitch->games->all());
        self::assertCount(2, $parts);
        self::assertStringContainsString('after=next-page', $this->http->calls[1][1]);
    }

    public function testUserSaveUsesPutAndQueryDescription(): void
    {
        $part = $this->twitch->getFactory()->part(User::class, ['id' => '42', 'description' => 'hello world'], true);
        await($this->twitch->users->save($part));
        self::assertSame(['PUT', 'users?description=hello%20world', null], array_slice($this->http->requests[0], 0, 3));
    }

    public function testRewardSaveAndDeleteCarryContextAndWritableSettings(): void
    {
        $part = $this->twitch->getFactory()->part(CustomReward::class, ['id' => 'r', 'broadcaster_id' => '42', 'title' => 'reward', 'max_per_stream_setting' => ['is_enabled' => true, 'max_per_stream' => 3]], true);
        await($this->twitch->channelPoints->save($part));
        self::assertSame('channel_points/custom_rewards?id=r&broadcaster_id=42', $this->http->calls[0][1]);
        self::assertSame(['title' => 'reward', 'is_max_per_stream_enabled' => true, 'max_per_stream' => 3], $this->http->requests[0][2]);
        await($this->twitch->channelPoints->delete($part));
        self::assertStringContainsString('broadcaster_id=42', $this->http->calls[1][1]);
        self::assertSame(0, $this->twitch->channelPoints->count());
    }

    public function testRewardDraftRetainsFlatWritableFields(): void
    {
        $this->http->script[] = ['data' => [['id' => 'created-reward']]];
        $part = $this->twitch->getFactory()->part(CustomReward::class, ['broadcaster_id' => '42', 'title' => 'reward', 'cost' => 1, 'is_max_per_stream_enabled' => true, 'max_per_stream' => 3]);
        await($this->twitch->channelPoints->save($part));
        self::assertTrue($this->http->requests[0][2]['is_max_per_stream_enabled']);
    }

    public function testConduitSavePutsIdInTheBody(): void
    {
        $part = $this->twitch->getFactory()->part(Conduit::class, ['id' => 'c', 'shard_count' => 3], true);
        await($this->twitch->conduits->save($part));
        self::assertSame(['PATCH', 'eventsub/conduits', ['shard_count' => 3, 'id' => 'c']], array_slice($this->http->requests[0], 0, 3));
    }

    public function testBlockListRetainsIdentifiersAndCursor(): void
    {
        $this->http->script[] = ['data' => [['user_id' => '1', 'user_login' => 'alpha', 'display_name' => 'Alpha'], ['user_id' => '2', 'user_login' => 'beta', 'display_name' => 'Beta']], 'pagination' => ['cursor' => 'next']];
        $parts = await($this->twitch->users->blocks('42', 20, 'start'));
        self::assertSame('alpha', $parts->get('id', '1')->login);
        self::assertSame('beta', $parts->get('id', '2')->login);
        self::assertSame('next', $this->twitch->users->nextCursor());
        self::assertStringContainsString('after=start', $this->http->calls[0][1]);
    }

    public function testDeleteManyOnlyEvictsAndReturnsConfirmedIds(): void
    {
        $this->http->script[] = ['data' => [['id' => 'v1'], ['id' => 'v2']]];
        await($this->twitch->videos->byIds(['v1', 'v2']));
        $this->http->script[] = ['data' => ['v1']];
        self::assertSame(['v1'], await($this->twitch->videos->deleteMany(['v1', 'v2'])));
        self::assertNull($this->twitch->videos->get('id', 'v1'));
        self::assertNotNull($this->twitch->videos->get('id', 'v2'));
    }

    public function testPollPaginationUsesValidPageSize(): void
    {
        $this->http->script = [
            ['data' => [['id' => 'p1']], 'pagination' => ['cursor' => 'next']],
            ['data' => [['id' => 'p2']], 'pagination' => []],
        ];
        self::assertCount(2, await($this->twitch->polls->all(['broadcaster_id' => '42'])));
        self::assertSame('polls?broadcaster_id=42&first=20', $this->http->calls[0][1]);
        self::assertStringContainsString('after=next', $this->http->calls[1][1]);
    }

    public function testSchedulesMergeSegmentsAcrossPages(): void
    {
        $this->http->script = [
            ['data' => ['broadcaster_id' => '42', 'segments' => [['id' => 's1']]], 'pagination' => ['cursor' => 'next']],
            ['data' => ['broadcaster_id' => '42', 'segments' => [['id' => 's2']]], 'pagination' => []],
        ];
        $schedule = await($this->twitch->schedule->all(['broadcaster_id' => '42']))->first();
        self::assertSame(['s1', 's2'], array_column($schedule->segments, 'id'));
        self::assertStringContainsString('after=next', $this->http->calls[1][1]);
    }

    public function testHypeTrainRepositoryHydratesCurrentStatus(): void
    {
        $this->http->script[] = ['data' => [['current' => ['level' => 2], 'all_time_high' => null, 'shared_all_time_high' => null]]];
        $part = await($this->twitch->hypeTrain->all(['broadcaster_id' => '42']))->first();
        self::assertSame('42', $part->broadcaster_id);
        self::assertSame(2, $part->current['level']);
    }

    public function testEmotesRetainTheTemplateAndBuildUrls(): void
    {
        $this->http->script[] = ['data' => [['id' => 'e1', 'name' => 'Kappa']], 'template' => 'https://synthetic/{{id}}/{{format}}/{{theme_mode}}/{{scale}}'];
        $emote = await($this->twitch->chat->globalEmotes())->first();
        self::assertSame('https://synthetic/e1/static/dark/1.0', $emote->url());
    }

    public function testICalendarKeepsTheTextResponse(): void
    {
        $browser = $this->createMock(Browser::class);
        $browser->method('withRejectErrorResponse')->willReturnSelf();
        $browser->method('withTimeout')->willReturnSelf();
        $calendar = "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n";
        $browser->expects(self::once())->method('get')->with('https://api.twitch.tv/helix/schedule/icalendar?broadcaster_id=42', ['Accept' => 'text/calendar'])->willReturn(\React\Promise\resolve(new Response(200, ['Content-Type' => 'text/calendar'], $calendar)));
        $method = new \ReflectionMethod($this->twitch->schedule, 'calendarWith');
        self::assertSame($calendar, await($method->invoke($this->twitch->schedule, $browser, '42')));
    }

    public function testFetchStreamUsesBroadcasterIdForCacheLookup(): void
    {
        $this->http->script[] = ['data' => [['id' => 'stream-id', 'user_id' => '42']]];
        await($this->twitch->streams->live());
        self::assertSame('stream-id', await($this->twitch->streams->fetch('42'))->id);
        self::assertCount(1, $this->http->calls);
    }

    public function testMarkerPaginationIsAccessible(): void
    {
        await($this->twitch->streams->markers('42', null, 20, 'cursor'));
        self::assertSame('streams/markers?user_id=42&first=20&after=cursor', $this->http->calls[0][1]);
    }

    public function testConduitSubscriptionsResolveVersionsAndTransport(): void
    {
        $this->http->script[] = ['data' => [['id' => 'sub-1']]];
        $part = await($this->twitch->eventSubscriptions->createConduit('channel.follow', ['broadcaster_user_id' => '42', 'moderator_user_id' => '1'], 'conduit'));
        self::assertSame('sub-1', $part->id);
        self::assertSame('2', $this->http->requests[0][2]['version']);
        self::assertSame(['method' => 'conduit', 'conduit_id' => 'conduit'], $this->http->requests[0][2]['transport']);
    }
}
