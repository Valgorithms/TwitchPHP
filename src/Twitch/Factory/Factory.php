<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Factory;

use Twitch\Parts\Part;
use Twitch\Repository\AbstractRepository;
use Twitch\Twitch;

/**
 * Hydrates {@see Part}s (and, less often, {@see AbstractRepository}s) from
 * decoded Helix payloads. Every part is handed the {@see Twitch} client so it
 * can reach the HTTP layer and build nested parts.
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class Factory
{
    public function __construct(private readonly Twitch $twitch)
    {
    }

    /**
     * @template T of Part
     *
     * @param class-string<T>             $class
     * @param array<string, mixed>|object $attributes
     *
     * @return T
     */
    public function part(string $class, array|object $attributes = [], bool $created = false): Part
    {
        if (! is_subclass_of($class, Part::class)) {
            throw new \InvalidArgumentException("{$class} is not a " . Part::class);
        }

        return new $class($this->twitch, $attributes, $created);
    }

    /**
     * @template T of Part
     *
     * @param class-string<T>                     $class
     * @param iterable<array<string, mixed>|object> $rows
     *
     * @return list<T>
     */
    public function parts(string $class, iterable $rows, bool $created = true): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->part($class, $row, $created);
        }

        return $out;
    }

    /**
     * @template T of AbstractRepository
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $vars Bound endpoint parameters for the repository.
     *
     * @return T
     */
    public function repository(string $class, array $vars = []): AbstractRepository
    {
        if (! is_subclass_of($class, AbstractRepository::class)) {
            throw new \InvalidArgumentException("{$class} is not an " . AbstractRepository::class);
        }

        return new $class($this->twitch, $vars);
    }
}
