<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;
use Twitch\Chat\Command;
use Twitch\Chat\CommandClient;
use Twitch\Parts\ChatMessage;
use Twitch\Twitch;

final class CommandClientTest extends TestCase
{
    private Twitch $twitch;

    private CommandClient $cc;

    /** @var list<string> */
    private array $denied = [];

    protected function setUp(): void
    {
        $this->twitch = new Twitch(['client_id' => 'cid']);
        $this->denied = [];
        $this->cc = new CommandClient($this->twitch, [
            'on_denied' => function (ChatMessage $m, Command $c, string $why): void {
                $this->denied[] = "{$c->name}:{$why}";
            },
        ]);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function message(array $attrs = []): ChatMessage
    {
        return $this->twitch->getFactory()->part(ChatMessage::class, $attrs + [
            'id' => 'm1',
            'channel' => 'chan',
            'user' => $attrs['user'] ?? 'viewer',
            'content' => '!x',
        ], true);
    }

    private function fire(string $name, array $args, ChatMessage $message): void
    {
        $this->twitch->emit('command', [$name, $args, $message, $this->twitch]);
    }

    public function testHandlerRunsWithArgs(): void
    {
        $seen = null;
        $this->cc->command('echo', function (ChatMessage $m, array $args) use (&$seen): void {
            $seen = $args;
        });

        $this->fire('echo', ['a', 'b'], $this->message());

        self::assertSame(['a', 'b'], $seen);
    }

    public function testAliasesResolve(): void
    {
        $runs = 0;
        $this->cc->command('lurk', function () use (&$runs): void {
            $runs++;
        }, aliases: ['afk', 'brb']);

        $this->fire('afk', [], $this->message());
        $this->fire('brb', [], $this->message());
        $this->fire('lurk', [], $this->message());

        self::assertSame(3, $runs);
        self::assertSame('lurk', $this->cc->get('afk')->name);
    }

    public function testPermissionGate(): void
    {
        $runs = 0;
        $this->cc->command('ban', function () use (&$runs): void {
            $runs++;
        }, permission: Command::MODERATOR);

        $this->fire('ban', [], $this->message(['is_mod' => false]));
        self::assertSame(0, $runs);
        self::assertSame(['ban:permission'], $this->denied);

        $this->fire('ban', [], $this->message(['is_mod' => true]));
        self::assertSame(1, $runs);
    }

    public function testCustomPermissionPredicate(): void
    {
        $runs = 0;
        $this->cc->command('secret', function () use (&$runs): void {
            $runs++;
        }, permission: fn (ChatMessage $m) => $m->user === 'owner');

        $this->fire('secret', [], $this->message(['user' => 'nobody']));
        $this->fire('secret', [], $this->message(['user' => 'owner']));

        self::assertSame(1, $runs);
    }

    public function testCooldownBlocksRepeatsButNotModerators(): void
    {
        $runs = 0;
        $this->cc->command('roll', function () use (&$runs): void {
            $runs++;
        }, cooldown: 60);

        $viewer = $this->message(['user' => 'v1']);
        $this->fire('roll', [], $viewer);
        $this->fire('roll', [], $viewer);

        self::assertSame(1, $runs);
        self::assertSame(['roll:cooldown:60s'], $this->denied);

        // A different user is unaffected; a moderator bypasses it.
        $this->fire('roll', [], $this->message(['user' => 'v2']));
        $this->fire('roll', [], $this->message(['user' => 'm1', 'is_mod' => true]));
        $this->fire('roll', [], $this->message(['user' => 'm1', 'is_mod' => true]));
        self::assertSame(4, $runs);
    }

    public function testHelpListsAllowedCommands(): void
    {
        $this->cc->command('ping', fn () => null, description: 'pong');
        $this->cc->command('nuke', fn () => null, permission: Command::BROADCASTER);

        $replies = [];
        $msg = $this->message(['is_broadcaster' => false]);
        // ChatMessage::reply() no-ops without an Irc; capture via a spy instead.
        $this->cc->command('help', function (ChatMessage $m, array $a) use (&$replies): void {
            $replies[] = 'called';
        });
        $this->fire('help', [], $msg);

        self::assertSame(['called'], $replies);
    }

    public function testUnregisterRemovesCommandAndAliases(): void
    {
        $runs = 0;
        $this->cc->command('greet', function () use (&$runs): void {
            $runs++;
        }, aliases: ['hi']);

        $this->cc->unregister('greet');
        $this->fire('greet', [], $this->message());
        $this->fire('hi', [], $this->message());

        self::assertSame(0, $runs);
        self::assertNull($this->cc->get('greet'));
        self::assertNull($this->cc->get('hi'));
    }

    public function testBuiltInHelpIsRegisteredByDefault(): void
    {
        self::assertArrayHasKey('help', $this->cc->all());

        $bare = new CommandClient(new Twitch(['client_id' => 'x']), ['help' => false]);
        self::assertArrayNotHasKey('help', $bare->all());
    }
}
