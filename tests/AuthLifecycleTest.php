<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;

use function React\Async\await;

use React\EventLoop\StreamSelectLoop;
use Twitch\Auth\ReauthorizerInterface;
use Twitch\Http\Exceptions\InvalidTokenException;
use Twitch\Twitch;

final class AuthLifecycleTest extends TestCase
{
    public function testCloseWhileAuthenticationIsPendingPreventsReadiness(): void
    {
        $loop = new StreamSelectLoop();
        $twitch = new Twitch(['client_id' => 'cid', 'token' => 'synthetic', 'loop' => $loop]);
        $oauth = ScriptedOAuth::attach($twitch);
        $validation = new \React\Promise\Deferred();
        $oauth->validations[] = $validation->promise();
        $error = null;
        $twitch->bootstrap()->then(null, function (\Throwable $e) use (&$error): void {
            $error = $e;
        });
        $twitch->close();
        $validation->resolve(['client_id' => 'cid', 'user_id' => '1', 'login' => 'bot']);
        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertFalse($twitch->isReady());
        self::assertNull((new \ReflectionProperty($twitch, 'validationTimer'))->getValue($twitch));
    }

    public function testAppTokenIsReissuedOnUnauthorized(): void
    {
        $twitch = new Twitch(['client_id' => 'cid', 'client_secret' => 'synthetic', 'loop' => new StreamSelectLoop()]);
        $oauth = ScriptedOAuth::attach($twitch);
        await($twitch->bootstrap());
        $http = new ScriptedHttp();
        $http->throw = new InvalidTokenException('expired', 401);
        (new \ReflectionProperty($twitch, 'http'))->setValue($twitch, $http);
        $oauth->grants[] = ['access_token' => 'renewed-app', 'expires_in' => 3600];
        await($twitch->streams->live());
        self::assertSame('renewed-app', $twitch->getToken());
        self::assertNull($twitch->getUserId());
        self::assertCount(2, $http->calls);
        $twitch->close();
        self::assertFalse($twitch->isReady());
    }

    public function testReauthorizationUsesValidatedIdentityAndScopes(): void
    {
        $twitch = $this->userClient();
        $oauth = ScriptedOAuth::attach($twitch);
        $oauth->validations[] = ['client_id' => 'cid', 'user_id' => '2', 'login' => 'second', 'scopes' => ['actual']];
        await($twitch->reauthorize());
        self::assertSame('2', $twitch->getUserId());
        self::assertSame('second', $twitch->getLogin());
        self::assertSame(['actual'], $twitch->getScopes());
    }

    public function testMismatchedClientIsRejectedBeforeAdoptingTheGrant(): void
    {
        $twitch = $this->userClient();
        $oauth = ScriptedOAuth::attach($twitch);
        $oauth->validations[] = ['client_id' => 'other', 'user_id' => '2'];
        $this->expectException(\RuntimeException::class);
        await($twitch->reauthorize());
    }

    public function testBootstrapSchedulesHourlyValidationAndCloseCancelsIt(): void
    {
        $loop = new StreamSelectLoop();
        $twitch = new Twitch(['client_id' => 'cid', 'token' => 'synthetic', 'loop' => $loop]);
        $oauth = ScriptedOAuth::attach($twitch);
        await($twitch->bootstrap());
        $property = new \ReflectionProperty($twitch, 'validationTimer');
        $timer = $property->getValue($twitch);
        self::assertSame(3600.0, $timer->getInterval());
        $loop->cancelTimer($timer);
        ($timer->getCallback())($timer);
        self::assertCount(2, $oauth->calls);
        self::assertNotNull($property->getValue($twitch));
        $twitch->close();
        self::assertNull($property->getValue($twitch));
    }

    public function testRevokedGrantStopsTheSessionWhenRecoveryFails(): void
    {
        $loop = new StreamSelectLoop();
        $twitch = new Twitch(['client_id' => 'cid', 'token' => 'synthetic', 'loop' => $loop]);
        $oauth = ScriptedOAuth::attach($twitch);
        await($twitch->bootstrap());
        $oauth->validations[] = new InvalidTokenException('revoked', 401);
        $error = null;
        $twitch->on('error', function ($e) use (&$error): void {
            $error = $e;
        });
        $timer = (new \ReflectionProperty($twitch, 'validationTimer'))->getValue($twitch);
        $loop->cancelTimer($timer);
        ($timer->getCallback())($timer);
        self::assertFalse($twitch->isReady());
        self::assertInstanceOf(InvalidTokenException::class, $error);
        $twitch->close();
    }

    public function testAppTokensCannotBootstrapUserTransports(): void
    {
        $twitch = new Twitch(['client_id' => 'cid', 'client_secret' => 'synthetic', 'eventsub' => true]);
        ScriptedOAuth::attach($twitch);
        $this->expectException(\RuntimeException::class);
        await($twitch->bootstrap());
    }

    public function testNetworkValidationFailureDoesNotRequestANewGrant(): void
    {
        $twitch = new Twitch(['client_id' => 'cid', 'token' => 'synthetic']);
        $oauth = ScriptedOAuth::attach($twitch);
        $oauth->validations[] = new \RuntimeException('network down');
        try {
            await($twitch->validateToken());
            self::fail('expected failure');
        } catch (\RuntimeException $e) {
            self::assertSame('network down', $e->getMessage());
        }
        self::assertCount(1, $oauth->calls);
    }

    private function userClient(): Twitch
    {
        return new Twitch(['client_id' => 'cid', 'reauthorize' => new class () implements ReauthorizerInterface {
            public function reauthorize(): \React\Promise\PromiseInterface
            {
                return \React\Promise\resolve(['access_token' => 'new-user', 'refresh_token' => 'refresh', 'scope' => ['unverified']]);
            }
        }]);
    }
}
