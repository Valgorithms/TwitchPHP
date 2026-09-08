<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Chat;

use Twitch\Parts\ChatMessage;
use Twitch\Twitch;

/**
 * A richer command layer over {@see Irc} — the equivalent of DiscordPHP's
 * `MessageCommandClient`. It listens to the client's `command` event (which
 * {@see Irc} already emits for `<prefix><name> …`) and adds aliases, per-user
 * cooldowns, permission levels and an auto-generated `help` command.
 *
 * ```php
 * $cc = new CommandClient($twitch);
 * $cc->command('so', function (ChatMessage $m, array $args) use ($twitch) {
 *     $twitch->chat->shoutout($m->tags['room-id'], $args[0], $twitch->getUserId());
 * }, permission: Command::MODERATOR, cooldown: 30, description: 'Shout out a channel');
 * ```
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class CommandClient
{
    /** @var array<string, Command> Indexed by command name. */
    private array $commands = [];

    /** @var array<string, string> alias => command name. */
    private array $aliases = [];

    /** @var callable(ChatMessage, Command, string): void */
    private $onDenied;

    /**
     * @param array{help?: bool, on_denied?: callable(ChatMessage, Command, string): void} $options
     *   `help`      — register the built-in `help` command (default true).
     *   `on_denied` — called when a permission/cooldown check fails; the third
     *                 arg is `'permission'` or `'cooldown:<seconds>'`. Defaults
     *                 to a short chat reply.
     */
    public function __construct(private readonly Twitch $twitch, array $options = [])
    {
        $this->onDenied = $options['on_denied'] ?? function (ChatMessage $m, Command $c, string $why): void {
            $m->reply(str_starts_with($why, 'cooldown')
                ? "that command is on cooldown ({$why})"
                : 'you cannot use that command here');
        };

        if ($options['help'] ?? true) {
            $this->command('help', fn (ChatMessage $m, array $args) => $this->help($m, $args), description: 'List commands');
        }

        $this->twitch->on('command', fn (string $name, array $args, ChatMessage $message) => $this->dispatch($name, $args, $message));
    }

    /**
     * Registers a command.
     *
     * @param callable(ChatMessage, list<string>): void $handler
     * @param list<string>                              $aliases
     * @param string|callable(ChatMessage): bool        $permission
     */
    public function command(
        string $name,
        callable $handler,
        array $aliases = [],
        int $cooldown = 0,
        mixed $permission = Command::EVERYONE,
        string $description = '',
    ): Command {
        $command = new Command(strtolower($name), $handler, array_map('strtolower', $aliases), $cooldown, $permission, $description);
        $this->commands[$command->name] = $command;
        foreach ($command->aliases as $alias) {
            $this->aliases[$alias] = $command->name;
        }

        return $command;
    }

    public function unregister(string $name): void
    {
        $name = strtolower($name);
        unset($this->commands[$name]);
        $this->aliases = array_filter($this->aliases, static fn (string $target) => $target !== $name);
    }

    /** @return array<string, Command> */
    public function all(): array
    {
        return $this->commands;
    }

    public function get(string $name): ?Command
    {
        $name = strtolower($name);

        return $this->commands[$name] ?? $this->commands[$this->aliases[$name] ?? ''] ?? null;
    }

    // ── Dispatch ───────────────────────────────────────────────────────

    /**
     * @param list<string> $args
     */
    private function dispatch(string $name, array $args, ChatMessage $message): void
    {
        $command = $this->get($name);
        if ($command === null) {
            return;
        }

        if (! $command->allows($message)) {
            ($this->onDenied)($message, $command, 'permission');

            return;
        }

        $wait = $command->cooldownRemaining($message);
        if ($wait > 0) {
            ($this->onDenied)($message, $command, "cooldown:{$wait}s");

            return;
        }

        $command->run($message, $args);
    }

    /**
     * @param list<string> $args
     */
    private function help(ChatMessage $message, array $args): void
    {
        if (isset($args[0]) && ($one = $this->get($args[0])) !== null) {
            $message->reply(trim("!{$one->name} — " . ($one->description ?: 'no description')));

            return;
        }

        $names = array_map(static fn (Command $c) => '!' . $c->name, array_filter(
            $this->commands,
            fn (Command $c) => $c->allows($message),
        ));
        $message->reply('commands: ' . implode(', ', $names));
    }
}
