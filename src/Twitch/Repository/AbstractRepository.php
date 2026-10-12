<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Repository;

use Discord\Helpers\Collection;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

use Twitch\Factory\Factory;
use Twitch\Http\Endpoint;
use Twitch\Parts\Part;
use Twitch\Twitch;

/**
 * A live, lazily-populated view of one Helix resource collection — the same
 * shape as a DiscordPHP repository: an in-memory {@see Collection} of hydrated
 * {@see Part}s plus async `freshen` / `fetch` / `save` / `delete` against the
 * bound endpoints.
 *
 * Concrete repositories set {@see $part}, {@see $endpoints}, and (where the API
 * uses a non-`id` filter) {@see $idParam}, then add typed convenience methods.
 *
 * @template TPart of Part
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
abstract class AbstractRepository implements \Countable, \IteratorAggregate
{
    /**
     * The Part class this repository hydrates.
     *
     * @var class-string<TPart>
     */
    protected string $part;

    /** The Part attribute that identifies a row. */
    protected string $discrim = 'id';

    /** The Helix query parameter used to filter the list endpoint by id. */
    protected string $idParam = 'id';

    /**
     * Endpoint constants keyed by action: `all` (list), `get`, `create`,
     * `update`, `delete`. Only `all` is required.
     *
     * @var array<string, string>
     */
    protected array $endpoints = [];

    /** @var Collection<TPart> */
    protected Collection $items;

    protected Factory $factory;

    private ?string $lastCursor = null;

    /**
     * @param array<string, string|int> $vars Bound endpoint parameters (rare on Helix).
     */
    public function __construct(
        protected readonly Twitch $twitch,
        protected array $vars = [],
    ) {
        $this->factory = $twitch->getFactory();
        $this->items = new Collection([], $this->discrim, $this->part);
    }

    // ── Reads ────────────────────────────────────────────────────────────

    /**
     * Replaces the local collection with one page of the list endpoint.
     *
     * @param array<string, string|int|bool|array<int, string|int>|null> $query
     *
     * @return PromiseInterface<static>
     */
    public function freshen(array $query = []): PromiseInterface
    {
        return $this->twitch->request('GET', $this->endpoint('all')->withQuery($query))
            ->then(function (?array $body): static {
                $this->items->clear();
                foreach ($this->rows($body) as $row) {
                    $this->items->pushItem($this->factory->part($this->part, $row, true));
                }

                return $this;
            });
    }

    /**
     * Follows `pagination.cursor` and returns every row as Parts. Stops at
     * `$max` rows (0 = no limit) or when Twitch runs out of pages.
     *
     * @param array<string, string|int|bool|array<int, string|int>|null> $query
     *
     * @return PromiseInterface<Collection<TPart>>
     */
    public function all(array $query = [], int $max = 0): PromiseInterface
    {
        $collection = new Collection([], $this->discrim, $this->part);

        $fetchPage = function (?string $cursor) use (&$fetchPage, $collection, $query, $max): PromiseInterface {
            $pageQuery = $query + ['first' => 20];
            if ($cursor !== null) {
                $pageQuery['after'] = $cursor;
            }

            return $this->twitch->request('GET', $this->endpoint('all')->withQuery($pageQuery))
                ->then(function (?array $body) use (&$fetchPage, $collection, $max): PromiseInterface {
                    foreach ($this->rows($body) as $row) {
                        $collection->pushItem($this->factory->part($this->part, $row, true));
                        if ($max > 0 && $collection->count() >= $max) {
                            return resolve($collection);
                        }
                    }

                    $next = $this->lastCursor;

                    return ($next === null || $next === '') ? resolve($collection) : $fetchPage($next);
                });
        };

        return $fetchPage(null);
    }

    /**
     * A single row by id — from the local collection unless `$fresh`, otherwise
     * the list endpoint filtered by {@see $idParam}.
     *
     * @return PromiseInterface<TPart|null>
     */
    public function fetch(string $id, bool $fresh = false): PromiseInterface
    {
        if (! $fresh && ($cached = $this->items->get($this->discrim, $id)) !== null) {
            return resolve($cached);
        }

        if ($this->idParam === '') {
            return $this->all()->then(fn (Collection $parts) => $parts->get($this->discrim, $id));
        }

        $endpointKey = isset($this->endpoints['get']) ? 'get' : 'all';

        return $this->twitch->request('GET', $this->endpoint($endpointKey)->addQuery($this->idParam, $id))
            ->then(function (?array $body) {
                $row = $this->rows($body)[0] ?? null;
                if ($row === null) {
                    return null;
                }
                $part = $this->factory->part($this->part, $row, true);
                $this->items->pushItem($part);

                return $part;
            });
    }

    // ── Writes ───────────────────────────────────────────────────────────

    /**
     * POSTs a new row (`$part->created === false`) or PATCHes an existing one.
     *
     * @param TPart $part
     *
     * @return PromiseInterface<TPart>
     */
    public function save(Part $part): PromiseInterface
    {
        [$method, $key] = $part->created ? ['PATCH', 'update'] : ['POST', 'create'];
        if (! isset($this->endpoints[$key])) {
            return reject(new \LogicException(static::class . " cannot {$key} a " . $this->part));
        }

        if (! $part instanceof $this->part) {
            return reject(new \InvalidArgumentException('Expected a ' . $this->part));
        }
        try {
            [$method, $endpoint, $body] = $this->writeRequest($key, $part);
        } catch (\Throwable $e) {
            return reject($e);
        }

        return $this->twitch->request($method, $endpoint, $body)
            ->then(function (?array $body) use ($part): Part {
                $row = $this->rows($body)[0] ?? null;
                if ($row !== null) {
                    $part->fill($row);
                }
                $part->created = true;
                $this->items->pushItem($part);

                return $part;
            });
    }

    /** Resource-specific HTTP method, query context and writable body. */
    protected function writeRequest(string $key, Part $part): array
    {
        $endpoint = $this->endpoint($key);
        if ($key === 'update') {
            $id = $part->getAttribute($this->discrim);
            if ($id === null || $id === '') {
                throw new \InvalidArgumentException('The resource identifier is required to update it.');
            }
            $endpoint->addQuery($this->idParam, (string) $id);
        }

        return [$key === 'create' ? 'POST' : 'PATCH', $endpoint, $part->getUpdatableAttributes()];
    }

    /** Cursor from the last completed page request on this repository. */
    public function nextCursor(): ?string
    {
        return $this->lastCursor;
    }

    /**
     * @param TPart|string $part
     *
     * @return PromiseInterface<null>
     */
    public function delete(Part|string $part): PromiseInterface
    {
        if (! isset($this->endpoints['delete'])) {
            return reject(new \LogicException(static::class . ' cannot delete a ' . $this->part));
        }

        $id = $part instanceof Part ? (string) $part->getAttribute($this->discrim) : $part;

        return $this->twitch->request('DELETE', $this->endpoint('delete')->addQuery($this->idParam === '' ? 'id' : $this->idParam, $id))
            ->then(function () use ($id) {
                $this->items->offsetUnset($id);

                return null;
            });
    }

    // ── Collection passthrough ──────────────────────────────────────────

    /** @return TPart|null */
    public function get(string $discrim, mixed $value): ?Part
    {
        return $this->items->get($discrim, $value);
    }

    /** @return TPart|null */
    public function first(): ?Part
    {
        return $this->items->first();
    }

    /** @param callable(TPart): bool $callback @return Collection<TPart> */
    public function filter(callable $callback): Collection
    {
        return $this->items->filter($callback);
    }

    /** @return Collection<TPart> */
    public function collect(): Collection
    {
        return $this->items;
    }

    public function count(): int
    {
        return $this->items->count();
    }

    public function getIterator(): \Traversable
    {
        return $this->items->getIterator();
    }

    // ── Internals ──────────────────────────────────────────────────────

    protected function endpoint(string $key): Endpoint
    {
        if (! isset($this->endpoints[$key])) {
            throw new \LogicException(static::class . " has no '{$key}' endpoint");
        }

        $endpoint = new Endpoint($this->endpoints[$key]);
        if ($this->vars !== []) {
            $endpoint->bindAssoc($this->vars);
            $endpoint->withQuery($this->vars);
        }

        return $endpoint;
    }

    /**
     * Hydrate the rows of `$body` into Parts and return them as a fresh
     * {@see Collection}. Rows of the repository's own {@see $part} are also
     * merged into the live local collection; a different `$partClass` (a nested
     * or sibling model) is only returned, not cached.
     *
     * @param array<string, mixed>|null $body
     * @param class-string<Part>|null   $partClass
     *
     * @return Collection<Part>
     */
    protected function collectRows(?array $body, ?string $partClass = null, ?string $discrim = null): Collection
    {
        $partClass ??= $this->part;
        $own = $partClass === $this->part;
        $collection = new Collection([], $discrim ?? ($own ? $this->discrim : 'id'), $partClass);

        foreach ($this->rows($body) as $row) {
            $part = $this->factory->part($partClass, $row, true);
            if ($own) {
                $this->items->pushItem($part);
            }
            $collection->pushItem($part);
        }

        return $collection;
    }

    /**
     * Twitch wraps list results in `{ "data": [...] }`; a few endpoints return
     * a bare object. Normalise to a list of rows.
     *
     * @param array<string, mixed>|null $body
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(?array $body): array
    {
        $pagination = $body['pagination'] ?? null;
        $this->lastCursor = is_array($pagination) ? ($pagination['cursor'] ?? null) : (is_string($pagination) ? $pagination : null);
        if ($body === null) {
            return [];
        }
        if (! array_key_exists('data', $body)) {
            return [$body];
        }

        return array_is_list($body['data']) ? $body['data'] : [$body['data']];
    }
}
