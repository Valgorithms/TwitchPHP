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

/**
 * One registered chat command for the {@see CommandClient}: a name, its handler,
 * and the gates around it (aliases, a per-user cooldown, a permission level).
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class Command
{
    public const EVERYONE = 'everyone';
    public const SUBSCRIBER = 'subscriber';
    public const VIP = 'vip';
    public const MODERATOR = 'moderator';
    public const BROADCASTER = 'broadcaster';

    /** `user login => unix timestamp` of the last successful run, for the cooldown. */
    private array $lastRun = [];

    /**
     * @param callable(ChatMessage, list<string>): void $handler
     * @param list<string>                              $aliases
     * @param string|callable(ChatMessage): bool        $permission A level constant or a predicate.
     */
    public function __construct(
        public readonly string $name,
        private readonly mixed $handler,
        public readonly array $aliases = [],
        public readonly int $cooldown = 0,
        public readonly mixed $permission = self::EVERYONE,
        public readonly string $description = '',
    ) {
    }

    /** Whether `$message`'s author clears this command's permission level. */
    public function allows(ChatMessage $message): bool
    {
        if (is_callable($this->permission)) {
            return (bool) ($this->permission)($message);
        }

        return match ($this->permission) {
            self::BROADCASTER => (bool) $message->is_broadcaster,
            self::MODERATOR => $message->is_mod || $message->is_broadcaster,
            self::VIP => $message->is_vip || $message->is_mod || $message->is_broadcaster,
            self::SUBSCRIBER => $message->is_subscriber || $message->is_vip || $message->is_mod || $message->is_broadcaster,
            default => true,
        };
    }

    /**
     * Seconds left on `$user`'s cooldown for this command, or `0` when ready.
     * Moderators and the broadcaster are never rate-limited.
     */
    public function cooldownRemaining(ChatMessage $message): int
    {
        if ($this->cooldown <= 0 || $message->is_mod || $message->is_broadcaster) {
            return 0;
        }
        $last = $this->lastRun[strtolower((string) $message->user)] ?? 0;
        $remaining = $this->cooldown - (time() - $last);

        return max(0, $remaining);
    }

    /**
     * @param list<string> $args
     */
    public function run(ChatMessage $message, array $args): void
    {
        $this->lastRun[strtolower((string) $message->user)] = time();
        ($this->handler)($message, $args);
    }
}
