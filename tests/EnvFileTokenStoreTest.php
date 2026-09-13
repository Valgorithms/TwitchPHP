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
}
