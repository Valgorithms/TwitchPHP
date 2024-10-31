<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch\Parts;

use Twitch\Twitch;
use Discord\Helpers\Collection;
use PHPUnit\Framework\MockObject\MockObject;

interface NeoPartInterface
{
    public function __construct(null|Twitch|MockObject &$twitch, null|string|array $json_data);

    public function fill(string|array $json_data): self;

    public function __get(string $key);

    public function __set(string $key, $value): void;

    public function __unserialize(array $data): void;

    public function __serialize(): array;

    public function __toString(): string;

    public function __debugInfo(): array;
}