<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;
use Twitch\Auth\EnvFileTokenStore;

final class EnvFileTokenStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'twitchphp_env_');

        file_put_contents($this->path, <<<'ENV'
            # a comment
            twitch_nick=Valgorithms
            twitch_access_token=old_access
            twitch_refresh_token=old_refresh
            unrelated=keep_me
            ENV);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    private function contents(): string
    {
        return file_get_contents($this->path);
    }

    public function testLoadReturnsTheStoredPair(): void
    {
        self::assertSame(
            ['access_token' => 'old_access', 'refresh_token' => 'old_refresh'],
            (new EnvFileTokenStore($this->path))->load(),
        );
    }

    public function testLoadOnAMissingFileIsEmpty(): void
    {
        self::assertSame([], (new EnvFileTokenStore($this->path . '.nope'))->load());
    }

    public function testSaveRewritesOnlyTheKeysItOwns(): void
    {
        (new EnvFileTokenStore($this->path))->save([
            'access_token'  => 'new_access',
            'refresh_token' => 'new_refresh',
        ]);

        $contents = $this->contents();

        self::assertStringContainsString('twitch_access_token=new_access', $contents);
        self::assertStringContainsString('twitch_refresh_token=new_refresh', $contents);
        self::assertStringContainsString('# a comment', $contents);
        self::assertStringContainsString('unrelated=keep_me', $contents);
        self::assertStringContainsString('twitch_nick=Valgorithms', $contents);
    }

    public function testSaveAppendsKeysThatAreNotPresentYet(): void
    {
        (new EnvFileTokenStore($this->path))->save([
            'access_token' => 'new_access',
            'expires_in'   => 14400,
            'token_type'   => 'bearer',
        ]);

        self::assertStringContainsString('twitch_expires_in=14400', $this->contents());
        self::assertStringContainsString('twitch_token_type=bearer', $this->contents());
    }

    public function testSaveDoesNotDuplicateKeys(): void
    {
        $store = new EnvFileTokenStore($this->path);
        $store->save(['access_token' => 'one']);
        $store->save(['access_token' => 'two']);

        self::assertSame(1, substr_count($this->contents(), 'twitch_access_token='));
        self::assertSame('two', $store->load()['access_token']);
    }

    public function testScopeIsStoredAsJson(): void
    {
        (new EnvFileTokenStore($this->path))->save([
            'access_token' => 'a',
            'scope'        => ['chat:read', 'chat:edit'],
        ]);

        self::assertStringContainsString('twitch_scope=["chat:read","chat:edit"]', $this->contents());
    }

    public function testNullValuesDoNotClobberWhatIsStored(): void
    {
        // An app access token carries no refresh token; that must not wipe
        // the user refresh token sitting in the file.
        $store = new EnvFileTokenStore($this->path);
        $store->save(['access_token' => 'app_token', 'refresh_token' => null]);

        self::assertSame('old_refresh', $store->load()['refresh_token']);
    }

    public function testRoundTrip(): void
    {
        $store = new EnvFileTokenStore($this->path);
        $store->save(['access_token' => 'a1', 'refresh_token' => 'r1']);

        self::assertSame(['access_token' => 'a1', 'refresh_token' => 'r1'], $store->load());
    }

    public function testAnUppercaseFileIsUpdatedInPlaceNotDuplicated(): void
    {
        // How this library's own examples, and apps built on it, spell them.
        // Matching exactly used to leave these untouched and append lowercase
        // copies, so a caller reading TWITCH_ACCESS_TOKEN kept the stale one.
        file_put_contents($this->path, "TWITCH_CLIENT_ID=abc\nTWITCH_ACCESS_TOKEN=old_access\nTWITCH_REFRESH_TOKEN=\n");

        $store = new EnvFileTokenStore($this->path);
        $store->save(['access_token' => 'new_access', 'refresh_token' => 'new_refresh', 'expires_in' => 14400]);

        $contents = $this->contents();
        self::assertStringContainsString("TWITCH_ACCESS_TOKEN=new_access\n", $contents);
        self::assertStringContainsString("TWITCH_REFRESH_TOKEN=new_refresh\n", $contents);
        self::assertStringNotContainsString('twitch_access_token', $contents);
        // A key that was not there yet follows the file's convention.
        self::assertStringContainsString("TWITCH_EXPIRES_IN=14400\n", $contents);
        self::assertSame(['access_token' => 'new_access', 'refresh_token' => 'new_refresh'], $store->load());
    }

    public function testAFileAlreadyHoldingBothSpellingsEndsUpConsistent(): void
    {
        // What the old behaviour left behind: the uppercase originals, and
        // lowercase copies appended after them. Every copy gets the new value.
        file_put_contents($this->path, "TWITCH_ACCESS_TOKEN=stale\ntwitch_access_token=newer\n");

        $store = new EnvFileTokenStore($this->path);
        $store->save(['access_token' => 'newest']);

        self::assertSame(2, substr_count($this->contents(), '=newest'));
        self::assertStringNotContainsString('stale', $this->contents());
    }
}
