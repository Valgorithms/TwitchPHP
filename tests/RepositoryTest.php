<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;

use function React\Async\await;

use Twitch\Parts\User;
use Twitch\Twitch;

final class RepositoryTest extends TestCase
{
    private Twitch $twitch;

    private ScriptedHttp $http;

    protected function setUp(): void
    {
        $this->twitch = new Twitch(['client_id' => 'cid']);
        $this->http = new ScriptedHttp();

        (new \ReflectionProperty(Twitch::class, 'http'))->setValue($this->twitch, $this->http);
    }

    public function testUsersByLoginsBuildsTheRightQueryAndHydrates(): void
    {
        $this->http->script[] = ['data' => [
            ['id' => '141981764', 'login' => 'twitchdev', 'display_name' => 'TwitchDev'],
        ]];

        $collection = await($this->twitch->users->byLogins(['@TwitchDev']));

        self::assertSame(['GET', 'users?login=twitchdev'], $this->http->calls[0]);
        self::assertInstanceOf(User::class, $collection->first());
        self::assertSame('TwitchDev', $collection->first()->display_name);
        self::assertSame('twitchdev', $this->twitch->users->get('id', '141981764')->login);
    }

    public function testUsersMeHitsUsersWithNoFilter(): void
    {
        $this->http->script[] = ['data' => [['id' => '1', 'login' => 'me']]];

        $me = await($this->twitch->users->me());

        self::assertSame(['GET', 'users'], $this->http->calls[0]);
        self::assertSame('me', $me->login);
    }

    public function testModerationBanPostsToBansWithQueryContext(): void
    {
        $this->http->script[] = ['data' => [[
            'broadcaster_id' => '1', 'moderator_id' => '2', 'user_id' => '9', 'created_at' => '2024-01-01T00:00:00Z',
        ]]];

        $ban = await($this->twitch->moderation->ban('1', '2', '9', 'spam'));

        self::assertSame('POST', $this->http->calls[0][0]);
        self::assertStringStartsWith('moderation/bans?', $this->http->calls[0][1]);
        self::assertStringContainsString('broadcaster_id=1', $this->http->calls[0][1]);
        self::assertStringContainsString('moderator_id=2', $this->http->calls[0][1]);
        self::assertSame('9', $ban->user_id);
        self::assertTrue($ban->isPermanent());
    }

    public function testChatSendMessagePostsToHelix(): void
    {
        $this->http->script[] = ['data' => [['message_id' => 'abc', 'is_sent' => true]]];

        $result = await($this->twitch->chat->sendMessage('1', '2', 'hello world'));

        self::assertSame(['POST', 'chat/messages'], $this->http->calls[0]);
        self::assertTrue($result['is_sent']);
    }

    public function testGamesTopPassesFirst(): void
    {
        $this->http->script[] = ['data' => [['id' => '509658', 'name' => 'Just Chatting']], 'pagination' => []];

        $games = await($this->twitch->games->top(5));

        self::assertSame('GET', $this->http->calls[0][0]);
        self::assertStringContainsString('games/top?first=5', $this->http->calls[0][1]);
        self::assertSame('Just Chatting', $games->first()->name);
    }

    public function testChannelModifyPatches(): void
    {
        await($this->twitch->channels->modify('42', ['title' => 'new title', 'game_id' => '1']));

        self::assertSame('PATCH', $this->http->calls[0][0]);
        self::assertSame('channels?broadcaster_id=42', $this->http->calls[0][1]);
    }

    public function testClipsForBroadcasterBuildsQueryAndHydrates(): void
    {
        $this->http->script[] = ['data' => [['id' => 'AwkwardHelplessSalamander', 'title' => 'clip', 'view_count' => 10]]];

        $clips = await($this->twitch->clips->forBroadcaster('44445592'));

        self::assertSame('GET', $this->http->calls[0][0]);
        self::assertStringStartsWith('clips?', $this->http->calls[0][1]);
        self::assertStringContainsString('broadcaster_id=44445592', $this->http->calls[0][1]);
        self::assertSame('clip', $clips->first()->title);
    }

    public function testPollOpenPostsChoicesAsObjects(): void
    {
        $this->http->script[] = ['data' => [['id' => 'p1', 'title' => 'Best?', 'status' => 'ACTIVE']]];

        $poll = await($this->twitch->polls->open('1', 'Best?', ['A', 'B'], ['duration' => 120]));

        self::assertSame(['POST', 'polls'], $this->http->calls[0]);
        self::assertSame('ACTIVE', $poll->status);
    }

    public function testPredictionResolvePatchesWithWinningOutcome(): void
    {
        $this->http->script[] = ['data' => [['id' => 'pr1', 'status' => 'RESOLVED']]];

        $prediction = await($this->twitch->predictions->resolve('1', 'pr1', 'o2'));

        self::assertSame(['PATCH', 'predictions'], $this->http->calls[0]);
        self::assertSame('RESOLVED', $prediction->status);
    }

    public function testChannelPointsRedemptionsFilterByStatus(): void
    {
        $this->http->script[] = ['data' => [['id' => 'r1', 'status' => 'UNFULFILLED', 'user_id' => '9']]];

        $redemptions = await($this->twitch->channelPoints->redemptions('1', 'reward-1', 'UNFULFILLED'));

        self::assertSame('GET', $this->http->calls[0][0]);
        self::assertStringContainsString('channel_points/custom_rewards/redemptions?', $this->http->calls[0][1]);
        self::assertStringContainsString('reward_id=reward-1', $this->http->calls[0][1]);
        self::assertStringContainsString('status=UNFULFILLED', $this->http->calls[0][1]);
        self::assertSame('9', $redemptions->first()->user_id);
    }

    public function testSubscriptionCheckReturnsNullOnNotFound(): void
    {
        $this->http->throw = new \Twitch\Http\Exceptions\NotFoundException('no sub');

        $result = await($this->twitch->subscriptions->check('1', '2'));

        self::assertNull($result);
    }

    public function testRaidStartPostsFromAndToViaQuery(): void
    {
        $this->http->script[] = ['data' => [['created_at' => '2024-01-01T00:00:00Z', 'is_mature' => false]]];

        $raid = await($this->twitch->raids->start('1', '2'));

        self::assertSame('POST', $this->http->calls[0][0]);
        self::assertStringContainsString('raids?', $this->http->calls[0][1]);
        self::assertStringContainsString('from_broadcaster_id=1', $this->http->calls[0][1]);
        self::assertStringContainsString('to_broadcaster_id=2', $this->http->calls[0][1]);
        self::assertFalse($raid['is_mature']);
    }

    public function testSearchChannelsHydratesChannelSearchResults(): void
    {
        $this->http->script[] = ['data' => [[
            'id' => '1', 'broadcaster_login' => 'a_seagull', 'display_name' => 'A_Seagull', 'is_live' => true, 'started_at' => '',
        ]]];

        $hits = await($this->twitch->search->channels('seagull', true));

        self::assertSame('GET', $this->http->calls[0][0]);
        self::assertStringContainsString('search/channels?', $this->http->calls[0][1]);
        self::assertStringContainsString('live_only=true', $this->http->calls[0][1]);
        self::assertSame('A_Seagull', $hits->first()->display_name);
        self::assertNull($hits->first()->started_at);
    }
}
