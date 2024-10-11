<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch\Helpers;

use Discord\Helpers\Collection;

/**
 * A null object pattern implementation of the Collection class.
 */
class NullCollection extends Collection
{
    public function __construct(array $items = [], ?string $discrim = 'id', ?string $class = null)
    {
        $this->items = [];
        $this->discrim = $discrim;
        $this->class = $class;
    }

    public static function from(array $items = [], ?string $discrim = 'id', ?string $class = null)
    {
        return new static([], $discrim, $class);
    }
    
    public function set($offset, $value)
    {}

    public function fill(array $items): Collection
    {
        return $this;
    }

    public function push(...$items): Collection
    {
        return $this;
    }

    public function pushItem($item): Collection
    {
        return $this;
    }

    public function merge(Collection $collection): Collection
    {
        return $collection;
    }

    public function offsetSet($offset, $value): void
    {}
}