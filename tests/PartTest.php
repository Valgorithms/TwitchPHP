<?php

namespace Twitch\Tests;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Twitch\Parts\BannedUser;
use Twitch\Parts\Channel;
use Twitch\Parts\User;
use Twitch\Twitch;

final class PartTest extends TestCase
{
    private Twitch $twitch;

    protected function setUp(): void
    {
        $this->twitch = new Twitch(['client_id' => 'cid']);
    }

    public function testFillHonoursTheFillableAllowList(): void
    {
        $user = new User($this->twitch, ['id' => '1', 'login' => 'foo', 'not_a_field' => 'x'], true);

        self::assertSame('1', $user->id);
        self::assertSame('foo', $user->login);
        self::assertArrayNotHasKey('not_a_field', $user->getRawAttributes());
        self::assertTrue($user->created);
    }

    public function testDateAttributesBecomeCarbon(): void
    {
        $user = new User($this->twitch, ['created_at' => '2020-01-02T03:04:05Z']);

        self::assertInstanceOf(CarbonImmutable::class, $user->created_at);
        self::assertSame('2020-01-02', $user->created_at->toDateString());
    }

    public function testArrayAndPropertyAccessAgree(): void
    {
        $game = new Channel($this->twitch, ['broadcaster_id' => '42', 'title' => 'hi']);

        self::assertSame('hi', $game->title);
        self::assertSame('hi', $game['title']);
        self::assertTrue(isset($game['title']));
        self::assertNull($game['missing']);
    }

    public function testGetUpdatableAttributesReturnsOnlyWritableSetFields(): void
    {
        $channel = new Channel($this->twitch, ['broadcaster_id' => '42', 'title' => 'old', 'game_name' => 'ignored'], true);
        $channel->title = 'new';
        $channel->game_id = '509658';

        $updatable = $channel->getUpdatableAttributes();

        self::assertEqualsCanonicalizing(['title' => 'new', 'game_id' => '509658'], $updatable);
        self::assertArrayNotHasKey('game_name', $updatable, 'game_name is not writable');
        self::assertArrayNotHasKey('broadcaster_id', $updatable, 'the id is not writable');
    }

    public function testJsonSerializeRendersCarbonAsIso8601(): void
    {
        $ban = new BannedUser($this->twitch, ['user_id' => '7', 'expires_at' => '2030-06-01T12:00:00Z']);

        $json = $ban->jsonSerialize();

        self::assertSame('7', $json['user_id']);
        self::assertStringStartsWith('2030-06-01T12:00:00', $json['expires_at']);
        self::assertFalse($ban->isPermanent());
    }

    public function testStreamAndGameUrlTemplating(): void
    {
        $game = $this->twitch->getFactory()->part(\Twitch\Parts\Game::class, [
            'id' => '1', 'box_art_url' => 'https://x/{width}x{height}.jpg',
        ]);

        self::assertSame('https://x/285x380.jpg', $game->boxArt());
        self::assertSame('https://x/100x100.jpg', $game->boxArt(100, 100));
    }
}
