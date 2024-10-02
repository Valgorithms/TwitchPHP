<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
use Discord\Helpers\Collection;

class User
{
    public ?string $id;
    public ?string $display_name;
    public ?string $type;
    public ?string $broadcaster_type;
    public ?string $description;
    public ?string $profile_image_url;
    public ?string $offline_image_url;
    public ?int $view_count;
    public ?string $email;
    public ?Carbon $created_at;

    public function __construct(
        public Twitch &$twitch,
        public string $login,
        public Channel|string $lastchannel = '',
        public ?Collection $channels = null,
        public Message|string $lastmessage = '',
        public ?Carbon $lastseen = null
    ) {
        $this->channels = $channels ?? new Collection();
        $this->lastseen = $lastseen ?? Carbon::now();
    }

    public function seen(?Carbon $timestamp = null): Carbon
    {
        $this->twitch->logger->debug("[SEEN] $this");
        return $this->lastseen = $timestamp ?? Carbon::now();
    }

    public function lastseen(): ?Carbon
    {
        return $this->lastseen;
    }

        public function __toString(): string
    {
        return $this->login;
    }

    public function __serialize():array
    {
        return [
            'name' => $this->login,
            'channels' => $this->channels->toArray(),
            'lastchannel' => $this->lastchannel,
            'lastmessage' => $this->lastmessage,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->twitch = &$data['twitch'];
        $this->login = $data['login'];
        $this->channels = new Collection($data['channels']);
        $this->lastchannel = $data['lastchannel'];
        $this->lastmessage = $data['lastmessage'];
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