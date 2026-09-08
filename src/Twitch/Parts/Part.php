<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Parts;

use Carbon\CarbonImmutable;
use Twitch\Factory\Factory;
use Twitch\Twitch;

/**
 * Base model for a single Twitch Helix entity.
 *
 * Mirrors DiscordPHP's `Part`: a `$fillable` allow-list, `get{Attr}Attribute` /
 * `set{Attr}Attribute` mutator hooks, array + property access, and JSON
 * serialisation. Timestamp fields named in `$dates` are exposed as
 * {@see CarbonImmutable}. Sub-classes only declare `$fillable` (and mutators
 * where a raw value needs shaping).
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
abstract class Part implements \ArrayAccess, \JsonSerializable
{
    /**
     * Attribute names this part accepts from the API. Empty = accept anything.
     *
     * @var list<string>
     */
    protected array $fillable = [];

    /**
     * Attributes sent back on {@see \Twitch\Repository\AbstractRepository::save()}
     * for a PATCH-able resource. Empty = the resource cannot be updated.
     *
     * @var list<string>
     */
    protected array $fillableAfterSave = [];

    /**
     * Attribute names to hydrate as {@see CarbonImmutable} instances.
     *
     * @var list<string>
     */
    protected array $dates = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    /** Whether this part reflects a row that exists on Twitch (vs. a local draft). */
    public bool $created = false;

    /**
     * @param array<string, mixed>|object $attributes
     */
    public function __construct(
        protected readonly Twitch $twitch,
        array|object $attributes = [],
        bool $created = false,
    ) {
        $this->created = $created;
        $this->fill((array) $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if ($this->fillable === [] || in_array($key, $this->fillable, true)) {
                $this->setAttribute((string) $key, $value);
            }
        }
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $setter = 'set' . self::studly($key) . 'Attribute';
        if (method_exists($this, $setter)) {
            $this->{$setter}($value);

            return;
        }

        if ($value !== null && in_array($key, $this->dates, true)) {
            $value = CarbonImmutable::parse($value);
        }

        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed
    {
        $getter = 'get' . self::studly($key) . 'Attribute';
        if (method_exists($this, $getter)) {
            return $this->{$getter}();
        }

        return $this->attributes[$key] ?? null;
    }

    public function attributeExists(string $key): bool
    {
        return array_key_exists($key, $this->attributes) || method_exists($this, 'get' . self::studly($key) . 'Attribute');
    }

    /** @return array<string, mixed> */
    public function getRawAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * The body for a create/update request — only the writable fields that are
     * actually set.
     *
     * @return array<string, mixed>
     */
    public function getUpdatableAttributes(): array
    {
        $out = [];
        foreach ($this->fillableAfterSave as $key) {
            if (array_key_exists($key, $this->attributes)) {
                $value = $this->attributes[$key];
                $out[$key] = $value instanceof CarbonImmutable ? $value->toIso8601String() : $value;
            }
        }

        return $out;
    }

    /** Builds another part from the same client — for nested objects. */
    protected function factory(): Factory
    {
        return $this->twitch->getFactory();
    }

    /**
     * @param class-string<T>             $class
     * @param array<string, mixed>|object $data
     *
     * @template T of Part
     *
     * @return T
     */
    protected function createOf(string $class, array|object $data): Part
    {
        return $this->factory()->part($class, (array) $data, $this->created);
    }

    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->getAttribute($name) !== null;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->attributeExists((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $out = [];
        foreach ($this->attributes as $key => $value) {
            $out[$key] = $value instanceof CarbonImmutable ? $value->toIso8601String()
                : ($value instanceof \JsonSerializable ? $value->jsonSerialize() : $value);
        }

        return $out;
    }

    public function __toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
