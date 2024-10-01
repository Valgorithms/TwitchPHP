<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

class User
{
    public function __construct(
        public Twitch &$twitch,
        public string $name
    ) {}

    public function __toString(): string
    {
        return $this->name;
    }
}