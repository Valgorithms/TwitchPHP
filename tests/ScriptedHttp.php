<?php

namespace Twitch\Tests;

use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

use Twitch\Http\Endpoint;
use Twitch\Http\HttpInterface;
use Twitch\Http\RateLimit;

/**
 * A stand-in {@see HttpInterface} that records calls and replays a scripted
 * list of decoded bodies. Shared by the repository and EventSub tests.
 */
final class ScriptedHttp implements HttpInterface
{
    /** @var list<array{0: string, 1: string}> */
    public array $calls = [];

    /** @var list<array{0: string, 1: string, 2: array<string, mixed>|null}> */
    public array $requests = [];

    /** @var list<array<string, mixed>|null> */
    public array $script = [];

    /** When set, the next request rejects with this instead of resolving. */
    public ?\Throwable $throw = null;

    public function request(string $method, Endpoint|string $endpoint, ?array $content = null, array $headers = []): PromiseInterface
    {
        $this->calls[] = [$method, (string) $endpoint];
        $this->requests[] = [$method, (string) $endpoint, $content];

        if ($this->throw !== null) {
            $e = $this->throw;
            $this->throw = null;

            return reject($e);
        }

        return resolve($this->script === [] ? ['data' => []] : array_shift($this->script));
    }

    public function get(Endpoint|string $endpoint, ?array $content = null, array $headers = []): PromiseInterface
    {
        return $this->request('GET', $endpoint, $content, $headers);
    }

    public function post(Endpoint|string $endpoint, ?array $content = null, array $headers = []): PromiseInterface
    {
        return $this->request('POST', $endpoint, $content, $headers);
    }

    public function put(Endpoint|string $endpoint, ?array $content = null, array $headers = []): PromiseInterface
    {
        return $this->request('PUT', $endpoint, $content, $headers);
    }

    public function patch(Endpoint|string $endpoint, ?array $content = null, array $headers = []): PromiseInterface
    {
        return $this->request('PATCH', $endpoint, $content, $headers);
    }

    public function delete(Endpoint|string $endpoint, ?array $content = null, array $headers = []): PromiseInterface
    {
        return $this->request('DELETE', $endpoint, $content, $headers);
    }

    public function setToken(string $token): void
    {
    }

    public function getRateLimit(): ?RateLimit
    {
        return null;
    }
}
