<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch\Parts;

use React\Promise\PromiseInterface;

/**
 * @since 3.0.0
 *
 * @property string|null $discrim                   The discriminator used for the collection.
 * @property string|null $cache                     The partial name of the cache for studlyCase lookup.
 * @property string      $broadcaster_user_id       The ID of the broadcaster user.
 * @property string      $broadcaster_user_login    The login name of the broadcaster user.
 * @property string      $broadcaster_user_name     The display name of the broadcaster user.
 *
 * @method void sendMessage(string $data) Sends a message to the Twitch channel.
 * @method string __toString() Returns the broadcaster user name as a string.
 */
class Channel extends NeoPart
{
    public ?string $discrim = 'broadcaster_user_id';
    public ?string $cache = 'channel';

    public string $broadcaster_user_id;
    public string $broadcaster_user_login;
    public string $broadcaster_user_name;

    /**
     * Sends a message to the Twitch channel.
     *
     * @param string $data The message to be sent.
     *
     * @return void
     */
    public function sendMessage(string $data): PromiseInterface
    {
        $this->twitch->logger->info("[REPLY] #$this - $data");
        return $this->twitch->write("PRIVMSG #$this :$data\n");
    }
    
    public function __toString(): string
    {
        return $this->broadcaster_user_name ?? '';
    }
}