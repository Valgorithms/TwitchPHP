<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
use Discord\Helpers\Collection;

class Channel
{
    public function __construct(
        public Twitch &$twitch,
        public string $name,
        public ?Collection $messages = null,
        public Message|string &$lastmessage = '',
        public User|string &$lastuser = ''
    ) {
        $this->messages = $messages ?? new Collection();
    }

    /**
     * Sends a message to the Twitch channel.
     *
     * @param string $data The message to be sent.
     *
     * @return void
     */
    public function sendMessage(string $data): void
    {
        $this->twitch->write("PRIVMSG #$this :$data\n");
        $this->twitch->logger->info("[REPLY] #$this - $data");
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'messages' => $this->messages->toArray(),
            'lastmessage' => $this->lastmessage,
            'lastuser' => $this->lastuser,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->twitch = &$data['twitch'];
        $this->name = $data['name'];
        $this->messages = new Collection($data['messages']);
        $this->lastmessage = $data['lastmessage'];
        $this->lastuser = $data['lastuser'];
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