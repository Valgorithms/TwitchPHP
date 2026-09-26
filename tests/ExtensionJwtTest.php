<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;

use function React\Async\await;

use Twitch\Auth\ExtensionJwt;
use Twitch\Twitch;

final class ExtensionJwtTest extends TestCase
{
    private const SECRET = 'c2VjcmV0LWtleS1ieXRlcy1mb3ItdGVzdHM='; // base64 of "secret-key-bytes-for-tests"

    private Twitch $twitch;

    private ScriptedHttp $http;

    private ExtensionJwt $jwt;

    protected function setUp(): void
    {
        $this->twitch = new Twitch(['client_id' => 'cid']);
        $this->http = new ScriptedHttp();
        (new \ReflectionProperty(Twitch::class, 'http'))->setValue($this->twitch, $this->http);

        $this->jwt = new ExtensionJwt('ext-id', self::SECRET, '141981764');
    }

    /**
     * Checks the signature with the decoded secret and returns the payload.
     *
     * @return array<string, mixed>
     */
    private static function verify(string $token): array
    {
        [$header, $payload, $signature] = explode('.', $token);
        $decode = static fn (string $part): string => base64_decode(strtr($part, '-_', '+/'), true);

        self::assertSame(['alg' => 'HS256', 'typ' => 'JWT'], json_decode($decode($header), true));
        self::assertTrue(hash_equals(
            hash_hmac('sha256', "{$header}.{$payload}", base64_decode(self::SECRET), true),
            $decode($signature),
        ), 'the signature must verify against the decoded secret');

        return json_decode($decode($payload), true);
    }

    /** @return array<string, mixed> The claims of the token a request was sent with. */
    private function claimsOf(int $request): array
    {
        $headers = $this->http->requests[$request][3];
        self::assertSame('ext-id', $headers['Client-Id']);
        self::assertStringStartsWith('Bearer ', $headers['Authorization']);

        return self::verify(substr($headers['Authorization'], 7));
    }

    public function testSignsAnHs256TokenForTheExternalRole(): void
    {
        $before = time();
        $claims = self::verify($this->jwt->sign());

        self::assertSame('141981764', $claims['user_id']);
        self::assertSame('external', $claims['role']);
        self::assertGreaterThanOrEqual($before + 60, $claims['exp']);
        self::assertLessThanOrEqual(time() + 60, $claims['exp']);
        self::assertStringNotContainsString('=', $this->jwt->sign(), 'segments are unpadded base64url');
    }

    public function testClaimsAddToAndOverrideTheDefaults(): void
    {
        $claims = self::verify($this->jwt->sign(['channel_id' => '9', 'role' => 'broadcaster']));

        self::assertSame('9', $claims['channel_id']);
        self::assertSame('broadcaster', $claims['role']);
        self::assertSame('141981764', $claims['user_id']);
    }

    public function testRejectsASecretThatIsNotBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtensionJwt('ext-id', 'not base64!', '1');
    }

    public function testRejectsANonPositiveLifetime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtensionJwt('ext-id', self::SECRET, '1', 0);
    }

    public function testDebugOutputLeavesTheSecretOut(): void
    {
        $dump = print_r($this->jwt, true);

        self::assertStringContainsString('ext-id', $dump);
        self::assertStringNotContainsString(self::SECRET, $dump);
        self::assertStringNotContainsString('secret-key-bytes-for-tests', $dump);
    }

    public function testConfigurationSegmentsAuthenticateAsTheExtension(): void
    {
        $this->http->script[] = ['data' => [['segment' => 'broadcaster', 'broadcaster_id' => '9', 'content' => '{}', 'version' => '1']]];

        $segments = await($this->twitch->extensions->configurationSegments($this->jwt, ['broadcaster', 'developer'], '9'));
        await($this->twitch->extensions->setConfigurationSegment($this->jwt, 'global', content: '{"a":1}'));
        await($this->twitch->extensions->setRequiredConfiguration($this->jwt, '9', '1.0.0', 'ready'));

        self::assertSame(['GET', 'extensions/configurations?extension_id=ext-id&broadcaster_id=9&segment=broadcaster&segment=developer'], $this->http->calls[0]);
        self::assertSame(['extension_id' => 'ext-id', 'segment' => 'global', 'content' => '{"a":1}'], $this->http->requests[1][2]);
        self::assertSame(['PUT', 'extensions/required_configuration?broadcaster_id=9', [
            'extension_id' => 'ext-id',
            'extension_version' => '1.0.0',
            'required_configuration' => 'ready',
        ]], array_slice($this->http->requests[2], 0, 3));
        self::assertSame('{}', $segments[0]['content']);
        foreach ([0, 1, 2] as $request) {
            self::assertSame('external', $this->claimsOf($request)['role']);
        }
    }

    public function testPubSubTokensAreScopedToTheirTargets(): void
    {
        await($this->twitch->extensions->sendPubSubMessage($this->jwt, '9', 'hello', ['broadcast', 'whisper-7']));
        await($this->twitch->extensions->broadcastPubSubMessage($this->jwt, 'hello all'));

        self::assertSame(['POST', 'extensions/pubsub', [
            'target' => ['broadcast', 'whisper-7'],
            'broadcaster_id' => '9',
            'is_global_broadcast' => false,
            'message' => 'hello',
        ]], array_slice($this->http->requests[0], 0, 3));
        $channel = $this->claimsOf(0);
        self::assertSame('9', $channel['channel_id']);
        self::assertSame(['send' => ['broadcast', 'whisper-7']], $channel['pubsub_perms']);

        self::assertSame(['target' => ['global'], 'is_global_broadcast' => true, 'message' => 'hello all'], $this->http->requests[1][2]);
        $global = $this->claimsOf(1);
        self::assertSame('all', $global['channel_id']);
        self::assertSame(['send' => ['global']], $global['pubsub_perms']);
    }

    public function testChatSecretsAndMetadataAuthenticateAsTheExtension(): void
    {
        $secrets = ['format_version' => 1, 'secrets' => [['content' => 'abc', 'active_at' => '2026-01-01T00:00:00Z', 'expires_at' => '2099-01-01T00:00:00Z']]];
        $this->http->script[] = null;
        $this->http->script[] = ['data' => [$secrets]];
        $this->http->script[] = ['data' => [$secrets]];
        $this->http->script[] = ['data' => [['id' => 'ext-id', 'version' => '1.0.0']]];
        $this->http->script[] = ['data' => []];

        await($this->twitch->extensions->sendChatMessage($this->jwt, '9', '1.0.0', 'hi chat'));
        $current = await($this->twitch->extensions->secrets($this->jwt));
        $created = await($this->twitch->extensions->createSecret($this->jwt, 600));
        await($this->twitch->extensions->metadata('ext-id', null, $this->jwt));
        await($this->twitch->extensions->metadata('ext-id'));

        self::assertSame(['POST', 'extensions/chat?broadcaster_id=9', [
            'text' => 'hi chat',
            'extension_id' => 'ext-id',
            'extension_version' => '1.0.0',
        ]], array_slice($this->http->requests[0], 0, 3));
        self::assertSame(['GET', 'extensions/jwt/secrets'], $this->http->calls[1]);
        self::assertSame(['POST', 'extensions/jwt/secrets?extension_id=ext-id&delay=600'], $this->http->calls[2]);
        self::assertSame(['GET', 'extensions?extension_id=ext-id'], $this->http->calls[3]);
        self::assertSame('abc', $current['secrets'][0]['content']);
        self::assertSame($current, $created);
        foreach ([0, 1, 2, 3] as $request) {
            self::assertSame('141981764', $this->claimsOf($request)['user_id']);
        }
        self::assertSame([], $this->http->requests[4][3], 'without a JWT, metadata() keeps using the client token');
    }
}
