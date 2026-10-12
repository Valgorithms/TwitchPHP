<?php

namespace Twitch\EventSub;

use Twitch\Twitch;

/** Verifies EventSub webhook requests and emits authenticated notifications. */
final class Webhook
{
    /** @var array<string, int> Recently delivered notification ids. */
    private array $seen = [];

    public function __construct(private readonly Twitch $twitch, #[\SensitiveParameter] private readonly string $secret)
    {
        if (strlen($secret) < 10 || strlen($secret) > 100) {
            throw new \InvalidArgumentException('EventSub webhook secrets must be 10-100 bytes.');
        }
    }

    /**
     * Returns the challenge as plain text, or null for a notification/revocation.
     * Reject with HTTP 403 when verification throws; acknowledge other messages
     * with HTTP 204. Pass the exact raw body, before parsing or re-encoding it.
     *
     * @param array<string, string|list<string>> $headers
     */
    public function handle(array $headers, string $body): ?string
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? ($value[0] ?? '') : $value;
        }
        $id = $normalized['twitch-eventsub-message-id'] ?? '';
        $timestamp = $normalized['twitch-eventsub-message-timestamp'] ?? '';
        $signature = $normalized['twitch-eventsub-message-signature'] ?? '';
        $time = strtotime($timestamp);
        if ($id === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp) || $time === false || abs(time() - $time) > 600 || ! hash_equals('sha256=' . hash_hmac('sha256', $id . $timestamp . $body, $this->secret), $signature)) {
            throw new \UnexpectedValueException('Invalid or expired EventSub webhook signature.');
        }
        $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new \UnexpectedValueException('EventSub webhook payload must be an object.');
        }
        $messageType = $normalized['twitch-eventsub-message-type'] ?? '';
        if ($messageType === 'webhook_callback_verification') {
            if (! isset($payload['challenge']) || ! is_string($payload['challenge'])) {
                throw new \UnexpectedValueException('EventSub challenge is missing.');
            }

            return $payload['challenge'];
        }
        $now = time();
        $this->seen = array_filter($this->seen, static fn (int $at) => $now - $at < 600);
        if (isset($this->seen[$id])) {
            return null;
        }
        $subscription = $payload['subscription'] ?? [];
        if (! is_array($subscription) || (! isset($subscription['type']) && $messageType === 'notification')) {
            throw new \UnexpectedValueException('EventSub subscription is missing.');
        }
        if ($messageType === 'notification') {
            if (! isset($payload['event']) || ! is_array($payload['event'])) {
                throw new \UnexpectedValueException('EventSub notification event is missing.');
            }
            $type = $subscription['type'] ?? '';
            $event = $payload['event'] ?? [];
            $meta = ['message_id' => $id, 'message_timestamp' => $timestamp, 'subscription_type' => $type, 'subscription_version' => $subscription['version'] ?? ''];
            $this->twitch->emit('eventsub', [$type, $event, $meta, $this->twitch]);
            $this->twitch->emit('eventsub.' . $type, [$event, $meta, $this->twitch]);
        } elseif ($messageType === 'revocation') {
            $this->twitch->emit('eventsub.revoked', [$subscription, $this->twitch]);
        } else {
            throw new \UnexpectedValueException('Unsupported EventSub webhook message type.');
        }
        $this->seen[$id] = $now;
        if (count($this->seen) > 10000) {
            array_shift($this->seen);
        }

        return null;
    }

    public function __debugInfo(): array
    {
        return ['cached_notifications' => count($this->seen)];
    }
}
