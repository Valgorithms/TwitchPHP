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

    public function testConduitCreatePostsShardCountAndHydrates(): void
    {
        $this->http->script[] = ['data' => [['id' => 'cond-1', 'shard_count' => 5]]];

        $conduit = await($this->twitch->conduits->create(5));

        self::assertSame(['POST', 'eventsub/conduits'], $this->http->calls[0]);
        self::assertSame(5, $conduit->shard_count);
        self::assertSame('cond-1', $conduit->id);
    }

    public function testEntitlementUpdateStatusPatchesIdsAndStatus(): void
    {
        $this->http->script[] = ['data' => [['status' => 'SUCCESS', 'ids' => ['e1', 'e2']]]];

        $report = await($this->twitch->entitlements->updateStatus(['e1', 'e2'], 'FULFILLED'));

        self::assertSame(['PATCH', 'entitlements/drops'], $this->http->calls[0]);
        self::assertSame('SUCCESS', $report['data'][0]['status']);
    }

    public function testAnalyticsGamesForwardsFilters(): void
    {
        $this->http->script[] = ['data' => [['game_id' => '9', 'URL' => 'https://x/report.csv']]];

        await($this->twitch->analytics->games(['game_id' => '9', 'type' => 'overview_v2']));

        self::assertSame('GET', $this->http->calls[0][0]);
        self::assertStringContainsString('analytics/games?', $this->http->calls[0][1]);
        self::assertStringContainsString('game_id=9', $this->http->calls[0][1]);
    }

    public function testBitsCustomPowerUpsRepeatsTheIdFilter(): void
    {
        $this->http->script[] = ['data' => [['id' => 'a', 'title' => 'Confetti', 'bits' => 100]]];

        $powerUps = await($this->twitch->bits->customPowerUps('1', ['a', 'b']));
        await($this->twitch->bits->customPowerUps('1'));

        self::assertSame(['GET', 'bits/custom_power_ups?broadcaster_id=1&id=a&id=b'], $this->http->calls[0]);
        self::assertSame(['GET', 'bits/custom_power_ups?broadcaster_id=1'], $this->http->calls[1]);
        self::assertSame('Confetti', $powerUps[0]['title']);
    }

    public function testChatPinsUseEachVerbOnTheSameEndpoint(): void
    {
        $this->http->script[] = ['data' => [['message_id' => 'm1', 'pinned_by_user_id' => '2']]];
        $this->http->script[] = null;
        $this->http->script[] = null;
        $this->http->script[] = null;
        $this->http->script[] = ['data' => []];

        $pinned = await($this->twitch->chat->pinnedMessage('1', '2'));
        await($this->twitch->chat->pinMessage('1', '2', 'm1', 300));
        await($this->twitch->chat->updatePinnedMessage('1', '2', 'm1'));
        await($this->twitch->chat->unpinMessage('1', '2', 'm1'));
        $nothing = await($this->twitch->chat->pinnedMessage('1', '2'));

        self::assertSame('m1', $pinned['message_id']);
        self::assertNull($nothing);
        self::assertSame([
            ['GET', 'chat/pins?broadcaster_id=1&moderator_id=2'],
            ['PUT', 'chat/pins?broadcaster_id=1&moderator_id=2&message_id=m1&duration_seconds=300'],
            ['PATCH', 'chat/pins?broadcaster_id=1&moderator_id=2&message_id=m1'],
            ['DELETE', 'chat/pins?broadcaster_id=1&moderator_id=2&message_id=m1'],
        ], array_slice($this->http->calls, 0, 4));
    }

    public function testSharedChatSessionIsNullOutsideASession(): void
    {
        $this->http->script[] = ['data' => [['session_id' => 's1', 'host_broadcaster_id' => '1', 'participants' => []]]];
        $this->http->script[] = ['data' => []];

        $session = await($this->twitch->chat->sharedChatSession('1'));

        self::assertSame(['GET', 'shared_chat/session?broadcaster_id=1'], $this->http->calls[0]);
        self::assertSame('s1', $session['session_id']);
        self::assertNull(await($this->twitch->chat->sharedChatSession('2')));
    }

    public function testClipsCreateFromVodAndDownloads(): void
    {
        $this->http->script[] = ['data' => [['id' => 'clip-1', 'edit_url' => 'https://clips.twitch.tv/clip-1/edit']]];
        $this->http->script[] = ['data' => [['clip_id' => 'clip-1', 'landscape_download_url' => 'https://x/l.mp4', 'portrait_download_url' => null]]];

        $clip = await($this->twitch->clips->createFromVod('9', '1', 'v5', 120, 'Big play', 12.54));
        $links = await($this->twitch->clips->downloads('9', '1', ['clip-1', 'clip-2']));

        self::assertSame(['POST', 'videos/clips?editor_id=9&broadcaster_id=1&vod_id=v5&vod_offset=120&duration=12.5&title=Big%20play'], $this->http->calls[0]);
        self::assertSame(['GET', 'clips/downloads?editor_id=9&broadcaster_id=1&clip_id=clip-1&clip_id=clip-2'], $this->http->calls[1]);
        self::assertSame('clip-1', $clip['id']);
        self::assertSame('https://x/l.mp4', $links[0]['landscape_download_url']);
    }

    public function testModerationCheckAutoModStatusMapsYourIdsToVerdicts(): void
    {
        $this->http->script[] = ['data' => [
            ['msg_id' => 'a', 'is_permitted' => true],
            ['msg_id' => 'b', 'is_permitted' => false],
        ]];

        $verdicts = await($this->twitch->moderation->checkAutoModStatus('1', ['a' => 'hello', 'b' => 'something rude']));

        self::assertSame(['POST', 'moderation/enforcements/status?broadcaster_id=1'], $this->http->calls[0]);
        self::assertSame(['data' => [
            ['msg_id' => 'a', 'msg_text' => 'hello'],
            ['msg_id' => 'b', 'msg_text' => 'something rude'],
        ]], $this->http->requests[0][2]);
        self::assertSame(['a' => true, 'b' => false], $verdicts);
    }

    public function testModerationHeldMessagesAndAutoModSettings(): void
    {
        $settings = ['broadcaster_id' => '1', 'moderator_id' => '2', 'overall_level' => 3];
        $this->http->script[] = null;
        $this->http->script[] = ['data' => [$settings]];
        $this->http->script[] = ['data' => [$settings]];

        await($this->twitch->moderation->resolveHeldMessage('2', 'msg-1', false));
        $current = await($this->twitch->moderation->autoModSettings('1', '2'));
        $saved = await($this->twitch->moderation->updateAutoModSettings('1', '2', ['overall_level' => 3]));

        self::assertSame(['POST', 'moderation/automod/message', ['user_id' => '2', 'msg_id' => 'msg-1', 'action' => 'DENY']], array_slice($this->http->requests[0], 0, 3));
        self::assertSame(['GET', 'moderation/automod/settings?broadcaster_id=1&moderator_id=2'], $this->http->calls[1]);
        self::assertSame(['PUT', 'moderation/automod/settings?broadcaster_id=1&moderator_id=2', ['overall_level' => 3]], array_slice($this->http->requests[2], 0, 3));
        self::assertSame(3, $current['overall_level']);
        self::assertSame($current, $saved);
    }

    public function testModerationSuspiciousUsers(): void
    {
        $this->http->script[] = ['data' => [['user_id' => '9', 'status' => 'RESTRICTED', 'types' => ['MANUALLY_ADDED']]]];
        $this->http->script[] = ['data' => [['user_id' => '9', 'status' => 'NO_TREATMENT', 'types' => []]]];

        $added = await($this->twitch->moderation->addSuspiciousUser('1', '2', '9', 'RESTRICTED'));
        $removed = await($this->twitch->moderation->removeSuspiciousUser('1', '2', '9'));

        self::assertSame(['POST', 'moderation/suspicious_users?broadcaster_id=1&moderator_id=2', ['user_id' => '9', 'status' => 'RESTRICTED']], array_slice($this->http->requests[0], 0, 3));
        self::assertSame(['DELETE', 'moderation/suspicious_users?broadcaster_id=1&moderator_id=2&user_id=9'], $this->http->calls[1]);
        self::assertSame('RESTRICTED', $added['status']);
        self::assertSame('NO_TREATMENT', $removed['status']);
    }

    public function testGuestStarInvitesSlotMovesAndSlotSettings(): void
    {
        $this->http->script[] = ['data' => [['user_id' => '7', 'status' => 'INVITED']]];

        $invites = await($this->twitch->guestStar->invites('1', '2', 's1'));
        await($this->twitch->guestStar->moveSlot('1', '2', 's1', '1', '2'));
        await($this->twitch->guestStar->moveSlot('1', '2', 's1', '1'));
        await($this->twitch->guestStar->updateSlotSettings('1', '2', 's1', '1', ['is_audio_enabled' => false, 'volume' => 80, 'slot_id' => 'ignored']));

        self::assertSame('7', $invites[0]['user_id']);
        self::assertSame([
            ['GET', 'guest_star/invites?broadcaster_id=1&moderator_id=2&session_id=s1'],
            ['PATCH', 'guest_star/slot?broadcaster_id=1&moderator_id=2&session_id=s1&source_slot_id=1&destination_slot_id=2'],
            ['PATCH', 'guest_star/slot?broadcaster_id=1&moderator_id=2&session_id=s1&source_slot_id=1'],
            ['PATCH', 'guest_star/slot_settings?broadcaster_id=1&moderator_id=2&session_id=s1&slot_id=1&is_audio_enabled=false&volume=80'],
        ], $this->http->calls);
    }

    public function testUsersAuthorizationsRepeatsTheUserId(): void
    {
        $this->http->script[] = ['data' => [['user_id' => '1', 'scopes' => ['chat:read'], 'has_authorized' => true]]];

        $grants = await($this->twitch->users->authorizations(['1', '2']));

        self::assertSame(['GET', 'authorization/users?user_id=1&user_id=2'], $this->http->calls[0]);
        self::assertSame(['chat:read'], $grants[0]['scopes']);
    }

    public function testExtensionsReleasedAndBitsProductsUseTheClientToken(): void
    {
        $product = ['sku' => 'boost', 'cost' => ['amount' => 100, 'type' => 'bits'], 'display_name' => 'Boost'];
        $this->http->script[] = ['data' => [['id' => 'ext', 'name' => 'Overlay', 'state' => 'Released']]];
        $this->http->script[] = ['data' => [$product]];
        $this->http->script[] = ['data' => [$product]];

        $released = await($this->twitch->extensions->released('ext'));
        $products = await($this->twitch->extensions->bitsProducts());
        $saved = await($this->twitch->extensions->saveBitsProduct($product));

        self::assertSame(['GET', 'extensions/released?extension_id=ext'], $this->http->calls[0]);
        self::assertSame(['GET', 'bits/extensions?should_include_all=false'], $this->http->calls[1]);
        self::assertSame(['PUT', 'bits/extensions', $product, []], $this->http->requests[2]);
        self::assertSame('Overlay', $released['name']);
        self::assertSame('boost', $products[0]['sku']);
        self::assertSame($product, $saved);
    }
}
