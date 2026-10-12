<?php

namespace Twitch\Tests;

use PHPUnit\Framework\TestCase;
use Twitch\EventSub\Webhook;
use Twitch\Twitch;

final class WebhookTest extends TestCase
{
    private const SECRET = 'synthetic-webhook-secret';

    public function testVerifiedChallengeIsReturnedAsText(): void
    {
        $handler = new Webhook(new Twitch(['client_id' => 'cid']), self::SECRET);
        $body = json_encode(['challenge' => 'challenge-1']);
        self::assertSame('challenge-1', $handler->handle($this->headers($body, 'webhook_callback_verification'), $body));
    }

    public function testTamperedBodyIsRejected(): void
    {
        $handler = new Webhook(new Twitch(['client_id' => 'cid']), self::SECRET);
        $this->expectException(\UnexpectedValueException::class);
        $handler->handle($this->headers('{}'), '{"event":{}}');
    }

    public function testOldSignedRequestIsRejected(): void
    {
        $handler = new Webhook(new Twitch(['client_id' => 'cid']), self::SECRET);
        $this->expectException(\UnexpectedValueException::class);
        $handler->handle($this->headers('{}', 'notification', time() - 601), '{}');
    }

    public function testDuplicateNotificationIsEmittedOnceWithMetadata(): void
    {
        $twitch = new Twitch(['client_id' => 'cid']);
        $handler = new Webhook($twitch, self::SECRET);
        $received = [];
        $twitch->on('eventsub', function (...$args) use (&$received): void {
            $received[] = $args;
        });
        $body = json_encode(['subscription' => ['type' => 'stream.online', 'version' => '1'], 'event' => ['id' => 'stream']]);
        $headers = $this->headers($body);
        $handler->handle($headers, $body);
        $handler->handle($headers, $body);
        self::assertCount(1, $received);
        self::assertSame('message-1', $received[0][2]['message_id']);
    }

    public function testListHeadersAndRevocationsAreSupported(): void
    {
        $twitch = new Twitch(['client_id' => 'cid']);
        $handler = new Webhook($twitch, self::SECRET);
        $revoked = null;
        $twitch->on('eventsub.revoked', function ($sub) use (&$revoked): void {
            $revoked = $sub;
        });
        $body = json_encode(['subscription' => ['id' => 'sub-1', 'status' => 'authorization_revoked']]);
        $handler->handle(array_map(static fn ($h) => [$h], $this->headers($body, 'revocation')), $body);
        self::assertSame('sub-1', $revoked['id']);
    }

    private function headers(string $body, string $type = 'notification', ?int $time = null): array
    {
        $stamp = gmdate('Y-m-d\TH:i:s\Z', $time ?? time());

        return [
            'Twitch-Eventsub-Message-Id' => 'message-1',
            'Twitch-Eventsub-Message-Timestamp' => $stamp,
            'Twitch-Eventsub-Message-Type' => $type,
            'Twitch-Eventsub-Message-Signature' => 'sha256=' . hash_hmac('sha256', 'message-1' . $stamp . $body, self::SECRET),
        ];
    }
}
