<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;

use function React\Async\await;

use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

use Twitch\Auth\ReauthorizerInterface;
use Twitch\Auth\TokenStoreInterface;
use Twitch\Http\Exceptions\InvalidTokenException;
use Twitch\Http\Exceptions\MissingScopeException;
use Twitch\Twitch;

/**
 * The 401 recovery chain in {@see Twitch::request()}.
 *
 * These exercise the re-authorization leg specifically (no `refresh_token`
 * is configured, so recovery goes straight there) and so need no network.
 */
final class TokenRecoveryTest extends TestCase
{
    private ScriptedHttp $http;

    private RecordingReauthorizer $reauthorizer;

    private RecordingTokenStore $store;

    protected function setUp(): void
    {
        $this->http = new ScriptedHttp();
        $this->reauthorizer = new RecordingReauthorizer();
        $this->store = new RecordingTokenStore();
    }

    private function client(): Twitch
    {
        $twitch = new Twitch([
            'client_id'   => 'cid',
            'reauthorize' => $this->reauthorizer,
            'token_store' => $this->store,
        ]);

        (new \ReflectionProperty(Twitch::class, 'http'))->setValue($twitch, $this->http);

        return $twitch;
    }

    public function testUnauthorizedTriggersReauthorizationThenRetries(): void
    {
        $twitch = $this->client();
        $this->http->throw = new InvalidTokenException('HTTP 401: Invalid OAuth token', 401);
        $this->http->script[] = ['data' => [['id' => '1', 'login' => 'me']]];

        $me = await($twitch->users->me());

        self::assertSame('me', $me->login);
        self::assertSame(1, $this->reauthorizer->calls, 're-authorized exactly once');
        self::assertCount(2, $this->http->calls, 'original call plus one retry');
    }

    public function testMissingScopeIsNeverRecoveredFrom(): void
    {
        $twitch = $this->client();
        $this->http->throw = new MissingScopeException('HTTP 401: Missing scope: bits:read', 401);

        $this->expectException(MissingScopeException::class);

        try {
            await($twitch->users->me());
        } finally {
            self::assertSame(0, $this->reauthorizer->calls, 'a narrow grant is not fixed by a new token');
            self::assertCount(1, $this->http->calls, 'not retried');
        }
    }

    public function testFailedReauthorizationSurfacesTheOriginalUnauthorized(): void
    {
        $twitch = $this->client();
        $this->reauthorizer->refuse = true;
        $this->http->throw = new InvalidTokenException('HTTP 401: Invalid OAuth token', 401);

        try {
            await($twitch->users->me());
            self::fail('expected the 401 to surface');
        } catch (\Throwable $e) {
            // The caller asked about the endpoint, not about our plumbing.
            self::assertInstanceOf(InvalidTokenException::class, $e);
            self::assertStringContainsString('Invalid OAuth token', $e->getMessage());
        }

        self::assertSame(1, $this->reauthorizer->calls);
    }

    public function testRecoveryIsAttemptedAtMostOncePerRequest(): void
    {
        $twitch = $this->client();

        // Re-arm the failure while recovering, so the retry 401s as well.
        $this->reauthorizer->onCall = function (): void {
            $this->http->throw = new InvalidTokenException('HTTP 401: Invalid OAuth token', 401);
        };
        $this->http->throw = new InvalidTokenException('HTTP 401: Invalid OAuth token', 401);

        $this->expectException(InvalidTokenException::class);

        try {
            await($twitch->users->me());
        } finally {
            self::assertSame(1, $this->reauthorizer->calls, 'no recovery loop');
            self::assertCount(2, $this->http->calls);
        }
    }

    public function testReauthorizedTokenIsPersisted(): void
    {
        $twitch = $this->client();
        $this->http->throw = new InvalidTokenException('HTTP 401: Invalid OAuth token', 401);
        $this->http->script[] = ['data' => [['id' => '1', 'login' => 'me']]];

        await($twitch->users->me());

        self::assertCount(1, $this->store->saved, 'the new pair was written through');
        self::assertSame('fresh_access', $this->store->saved[0]['access_token']);
        self::assertSame('fresh_refresh', $this->store->saved[0]['refresh_token']);
        self::assertSame('fresh_access', $twitch->getToken());
    }

    public function testReauthorizationUpdatesTheCachedScopes(): void
    {
        $twitch = $this->client();
        $this->http->throw = new InvalidTokenException('HTTP 401: Invalid OAuth token', 401);
        $this->http->script[] = ['data' => [['id' => '1', 'login' => 'me']]];

        await($twitch->users->me());

        self::assertSame(['chat:read', 'chat:edit'], $twitch->getScopes());
    }

    public function testStartupPrefersStoredTokensWhenNoneArePassed(): void
    {
        $this->store->stored = ['access_token' => 'from_store', 'refresh_token' => 'refresh_from_store'];

        $twitch = new Twitch(['client_id' => 'cid', 'token_store' => $this->store]);

        self::assertSame('from_store', $twitch->getToken());
    }

    public function testAnUnwritableStoreDoesNotBreakTheRequest(): void
    {
        $twitch = $this->client();
        $this->store->explode = true;
        $this->http->throw = new InvalidTokenException('HTTP 401: Invalid OAuth token', 401);
        $this->http->script[] = ['data' => [['id' => '1', 'login' => 'me']]];

        // Losing persistence is worth logging, not worth failing a request
        // that otherwise succeeded.
        self::assertSame('me', await($twitch->users->me())->login);
    }
}

final class RecordingReauthorizer implements ReauthorizerInterface
{
    public int $calls = 0;

    public bool $refuse = false;

    /** @var (callable(): void)|null */
    public $onCall = null;

    public function reauthorize(): PromiseInterface
    {
        $this->calls++;

        if ($this->onCall !== null) {
            ($this->onCall)();
        }

        return $this->refuse
            ? reject(new \RuntimeException('operator declined'))
            : resolve([
                'access_token'  => 'fresh_access',
                'refresh_token' => 'fresh_refresh',
                'scope'         => ['chat:read', 'chat:edit'],
                'token_type'    => 'bearer',
            ]);
    }
}

final class RecordingTokenStore implements TokenStoreInterface
{
    /** @var array<string, string> */
    public array $stored = [];

    /** @var list<array<string, mixed>> */
    public array $saved = [];

    public bool $explode = false;

    public function load(): array
    {
        return $this->stored;
    }

    public function save(array $token): void
    {
        if ($this->explode) {
            throw new \RuntimeException('disk is on fire');
        }

        $this->saved[] = $token;
    }
}
