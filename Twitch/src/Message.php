<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

class Message
{
    public function __construct(
        public Twitch &$twitch,
        public string $data
    ) {}

    public function __toString(): string
    {
        return $this->data;
    }
}