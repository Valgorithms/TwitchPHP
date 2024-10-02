<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
use Discord\Helpers\Collection;
use Discord\Parts\Part;

class Message extends Part
{
    public function __construct(
        public Twitch &$twitch,
        public string $content = '',
        public Channel|string $channel = '',
        public User|string $user = ''
    ) {}

    public function __toString(): string
    {
        return $this->content;
    }

    public function __serialize(): array
    {
        return [
            'content' => $this->content,
            'channel' => $this->channel,
            'user' => $this->user,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->twitch = &$data['twitch'];
        $this->content = $data['content'];
        $this->channel = $data['channel'];
        $this->user = $data['user'];
    }

    public function __get($name)
    {
        if (method_exists($this, $name)) return $this->$name();

        trigger_error("Undefined property: " . __CLASS__ . "::$name", E_USER_NOTICE);
        return null;
    }

    public function __set(string $name, $value): void
    {
        trigger_error("Undefined property: " . __CLASS__ . "::$name", E_USER_NOTICE);
    }
}