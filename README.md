# TwitchPHP

An async [ReactPHP](https://reactphp.org/) framework for the **Twitch Helix API**, **EventSub** over
WebSocket, and **chat** (IRC over WebSocket) — designed and built like
[DiscordPHP](https://github.com/discord-php/DiscordPHP).

```php
use Twitch\Twitch;

$twitch = new Twitch([
    'client_id'     => getenv('TWITCH_CLIENT_ID'),
    'client_secret' => getenv('TWITCH_CLIENT_SECRET') ?: '', // app token, or:
    'token'         => getenv('TWITCH_ACCESS_TOKEN') ?: null,  // a user token
    'refresh_token' => getenv('TWITCH_REFRESH_TOKEN') ?: null, // refreshed automatically on 401
]);

$twitch->on('ready', function (Twitch $twitch) {
    $twitch->users->fetchByLogin('twitchdev')->then(function ($user) {
        echo "twitchdev is user {$user->id}, created {$user->created_at->diffForHumans()}\n";
    });
});

$twitch->run();
```

## Requirements

- PHP 8.4+
- [Composer](https://getcomposer.org/)
- A registered application at <https://dev.twitch.tv/console/apps> for the `client_id` / `client_secret`

## Installation

```bash
composer require vzgcoders/twitchphp
```

The HTTP transport lives in its own package, [`twitchphp/http`](https://github.com/Valgorithms/TwitchPHP-Http),
and is pulled in automatically.

### Windows and SSL

PHP on Windows has no access to the system certificate store. Download a
[CA bundle](https://curl.se/docs/caextract.html) and point `openssl.cafile` in your `php.ini` at it,
otherwise every TLS connection fails silently.

## Authentication

Twitch has several OAuth flows; TwitchPHP supports the ones a bot or service needs.

| You have… | Pass | Behaviour |
| --- | --- | --- |
| A client id + secret only | `client_id`, `client_secret` | Requests an **app access token** for public data, webhook subscriptions and conduits. Reissues it after a token-related 401. |
| A user access token | `token`, `client_secret` (+ optional `refresh_token`) | Validated on startup and hourly thereafter. With a `refresh_token`, expired tokens are refreshed and the failed request is retried once. Required for IRC and EventSub WebSockets. |

`Twitch\Http\OAuth` also exposes `authorizeUrl()`, `exchangeCode()`, `deviceCode()`, `pollDeviceToken()`, `validate()`
and `revoke()` for building the authorization-code / device-code flows in your own app.
The current OAuth transport requires `client_secret` for refresh and device-code setup.
Tokens must belong to the configured `client_id`. Granted scopes and channel roles
are checked by Twitch: a scope alone does not make the bot a moderator or editor.

`bootstrap()` resolves after authentication and configured connections are ready,
and sets `isReady()`. `run()` also emits `init` / `ready` and runs the loop.
`close()` clears readiness, cancels validation and connection timers, and stops the loop.
You can call `validateToken()` explicitly. If hourly validation finds an invalid
grant and recovery fails, the socket sessions are closed and `error` is emitted.
Network validation failures are reported without replacing the grant.

### Keeping a token alive

Refresh tokens may change when Twitch issues a new token pair. Save the latest
pair so restarting does not load stale credentials. Pass a `token_store` and the
rotation is persisted for you; pass a `reauthorize` strategy and a grant that
can no longer be refreshed is rebuilt from scratch:

```php
use Twitch\Auth\EnvFileTokenStore;
use Twitch\Auth\DeviceCodeReauthorizer;

$twitch = new Twitch([
    'client_id'     => getenv('TWITCH_CLIENT_ID'),
    'client_secret' => getenv('TWITCH_CLIENT_SECRET'),
    'token_store'   => new EnvFileTokenStore(__DIR__ . '/.env'),
    'reauthorize'   => new DeviceCodeReauthorizer($oauth, $scopes, $prompt),
]);
```

A `401` is then refreshed, or re-authorized, and the request retried once. The
exception is a missing **scope**: `MissingScopeException` surfaces immediately,
naming the scopes that would have satisfied the call. Change the requested scope
list and explicitly reauthorize to obtain additional permissions. See
`examples/self-healing-auth.php`.
When no token is available, a configured reauthorization strategy obtains the
first user grant. Every reauthorized grant is validated before its identity and
scopes are adopted. Copy `example.env` to `.env` and edit `TWITCH_SCOPE`, a JSON
array of requested scopes; the auth examples accept uppercase or lowercase keys.

## The Helix client

Resources hang off the client as repositories, mirroring DiscordPHP. 29 of them:

```php
$twitch->users;         $twitch->channels;      $twitch->streams;
$twitch->games;         $twitch->chat;          $twitch->moderation;
$twitch->clips;         $twitch->videos;        $twitch->polls;
$twitch->predictions;   $twitch->channelPoints; $twitch->subscriptions;
$twitch->eventSubscriptions;  $twitch->teams;   $twitch->schedule;
$twitch->charity;       $twitch->goals;         $twitch->raids;
$twitch->bits;          $twitch->hypeTrain;     $twitch->search;
$twitch->whispers;      $twitch->ads;           $twitch->contentLabels;
$twitch->conduits;      $twitch->entitlements;  $twitch->analytics;
$twitch->guestStar;     $twitch->extensions;
```

Every call returns a `React\Promise\PromiseInterface`. Collections are
[`Discord\Helpers\Collection`](https://github.com/discord-php/DiscordPHP-Helpers) instances of
hydrated `Twitch\Parts\*` models.

React Promise 2.11 and 3 are supported. Use `then($success, $failure)` for
portable error handling; `catch()` / `finally()` are available only in version 3.
Prefer current dependency releases on PHP 8.4/8.5, since old upstream releases
can emit deprecation notices even when their behavior remains compatible.

```php
// Look users up by login and cache them
$twitch->users->byLogins(['twitchdev', 'twitch'])->then(function ($users) {
    foreach ($users as $user) {
        echo "{$user->display_name} ({$user->id})\n";
    }
});

// Who is live among a set of channels
$twitch->streams->live(['user_login' => ['twitchdev']])->then(function ($streams) {
    echo $streams->count() . " live\n";
});

// Update your channel
$twitch->channels->modify($broadcasterId, [
    'title'         => 'Now with 20% more PHP',
    'game_id'       => '509670',
    'tags'          => ['php', 'reactphp'],
]);

// Moderation
$twitch->moderation->ban($broadcasterId, $modId, $userId, 'spam');
$twitch->moderation->timeout($broadcasterId, $modId, $userId, 600, 'chill');
```

Repositories expose `freshen()`, `all()`, `fetch()`, `save()`, `delete()`, plus
`get()`, `first()`, `filter()` and iteration. HTTP operations require the resource's
query context and supported endpoints; unsupported writes reject with `LogicException`.
Action-only repositories (ads, analytics, extensions, Guest Star, raids and whispers)
use their named methods instead of generic reads.

| Resource | Generic writes | Required context |
| --- | --- | --- |
| users | `save()` an existing user | Authenticated user; updates `description`. |
| channels | `save()` an existing channel | Part's `broadcaster_id`. |
| channelPoints | create/update/delete a `CustomReward` | Part's `broadcaster_id`, or a bound repository context. |
| conduits | create/update/delete a `Conduit` | App token; existing part's `id`. |
| eventSubscriptions | create/delete a subscription | Valid `type`, `version`, `condition`, `transport`; correct token for the transport. |
| videos | delete | Video id; use `deleteMany()` for a confirmed list. |
| Other repositories | None | Use their named operation methods. |

Bind required query context when using generic operations on channel resources:

```php
$polls = $twitch->getFactory()->repository(
    \Twitch\Repository\PollRepository::class,
    ['broadcaster_id' => $broadcasterId],
);
$polls->all()->then(function ($parts) { /* all pages of polls */ });
```

`all($query, $max)` follows cursors, using a default page size of 20. `$max = 0`
means unlimited. Convenience list methods return one page; read `nextCursor()`
after completion and pass it as `after` on the next call. The cursor describes
the last completed page request on that repository, so retain it locally if you
make concurrent calls. Responses returned as raw envelopes already contain pagination.
`streams->fetch($userId)` looks up a broadcaster's stream by user id.
Schedule `all()` merges segments from every page into one `Schedule`; its `$max`
limits segments. Hype Train generic reads return `HypeTrainStatus` parts;
`status()` continues to return the raw status row. `schedule->iCalendar()` returns
the public text feed, without OAuth headers or Helix JSON decoding.

`freshen()` replaces the local cache with a page; most named reads merge their
own resource's parts into it. `all()` returns a separate collection; it does not
generally replace the cache. Use `fetch($id, true)` for a fresh lookup. On endpoints
without an id filter, a fresh fetch searches the channel/context's pages.
`deleteMany()` removes and returns only the ids Twitch confirms were deleted.

Every rejected promise needs an error handler. For example:

```php
$twitch->users->fetchByLogin('twitchdev')
    ->then(
        function ($user) { /* $user is null when not found */ },
        function (\Throwable $e) { echo $e->getMessage() . PHP_EOL; },
    );
```

### Extension backends

Some extension endpoints want a JWT signed with the extension's shared secret
rather than an OAuth token: configuration segments, PubSub, extension chat and
the secrets themselves. Those methods take a `Twitch\Auth\ExtensionJwt`, which
signs a short-lived token for each request:

```php
use Twitch\Auth\ExtensionJwt;

// The extension's client id, its base64 secret from the developer console, and the owner's user id
$jwt = new ExtensionJwt($extensionClientId, $base64Secret, $ownerUserId);

$twitch->extensions->sendPubSubMessage($jwt, $broadcasterId, json_encode(['score' => 3]));
$twitch->extensions->configurationSegments($jwt, 'broadcaster', $broadcasterId);
$twitch->extensions->sendChatMessage($jwt, $broadcasterId, '1.0.0', 'GG!');
```

## EventSub

Enable the WebSocket transport with `'eventsub' => true`, then subscribe once the session is ready:

```php
$twitch = new Twitch([
    'client_id'     => getenv('TWITCH_CLIENT_ID'),
    'client_secret' => getenv('TWITCH_CLIENT_SECRET'),
    'token'         => getenv('TWITCH_ACCESS_TOKEN'),
    'refresh_token' => getenv('TWITCH_REFRESH_TOKEN'),
    'eventsub'      => true,
]);

use Twitch\EventSub\SubscriptionTypes;

$twitch->on('ready', function (Twitch $twitch) use ($broadcasterId) {
    $es = $twitch->getEventSub();

    // Typed helpers for the common ones — right condition, right version.
    $es->onChatMessage($broadcasterId, $twitch->getUserId());
    $es->onFollow($broadcasterId, $twitch->getUserId());  // channel.follow v2
    $es->onStreamChange($broadcasterId);                   // online + offline

    // Generic form — version is looked up from SubscriptionTypes unless given.
    $es->subscribe(SubscriptionTypes::CHANNEL_CHEER, ['broadcaster_user_id' => $broadcasterId]);
    $es->subscribeMany([
        [SubscriptionTypes::CHANNEL_RAID, ['to_broadcaster_user_id' => $broadcasterId]],
        [SubscriptionTypes::CHANNEL_AD_BREAK_BEGIN, ['broadcaster_user_id' => $broadcasterId]],
    ]);
});

// Every notification fires twice: a generic event and a per-type event.
$twitch->on('eventsub', function (string $type, array $event, array $meta, Twitch $twitch) {
    echo "eventsub {$type}\n";
});
$twitch->on('eventsub.stream.online', function (array $event) {
    echo "{$event['broadcaster_user_name']} went live\n";
});
```

`SubscriptionTypes` has a constant for every documented EventSub type and knows
its default version. WebSocket subscriptions require a user token; webhook and
conduit subscriptions use app tokens. The `channel.follow` helper also requires
the named user to be the broadcaster or a moderator of that channel.

Fresh sessions replay the desired subscriptions once. A server-requested handoff
keeps the existing subscriptions and closes the old socket after the new welcome.
Keepalive and welcome timeouts detect connections that stop responding; failed
reconnects continue with backoff. Configure these through `eventsub_options`.
The first `connect()` attempt rejects on failure; later outages retry automatically.

`desiredSubscriptions()` retains its legacy `type => condition` map (the last
condition for each type wins). `desiredSubscriptionSpecs()` returns a **list of
`{type, condition, version}` records**, including every channel and version.
Repeated `subscribe()`
calls for the same specification share the existing request. `forget()` removes
reconnect intent; `unsubscribe($id)` deletes the active subscription and its intent.
Calls made before the welcome queue the desired specification and reject immediately.
`subscribeMany()` settles every specification and returns `null` for failed entries.
Listen for `eventsub.subscription_failed` to observe failed automatic replays.

Duplicate notification ids are suppressed within this process for ten minutes,
with a bounded cache of 10,000 ids. For durable deduplication across restarts,
persist `metadata.message_id` in your application. Events missed during a lost
connection cannot be replayed by this library.

### Webhooks and conduits

Create webhook subscriptions with `eventSubscriptions->createWebhook()` and
verify incoming requests with `Twitch\EventSub\Webhook`. Host the HTTPS callback
in your application and pass the **exact raw request body** and headers:

```php
$webhook = new \Twitch\EventSub\Webhook($twitch, $secret);
// In your HTTP handler, retain this instance across requests:
try {
    $challenge = $webhook->handle($request->getHeaders(), (string) $request->getBody());
    // Respond 200 text/plain with $challenge when non-null; otherwise 204.
} catch (\UnexpectedValueException | \JsonException $e) {
    // Reject the request with HTTP 403.
}
```

The helper checks HMAC-SHA256 signatures and rejects timestamps more than ten
minutes away from the current time. It emits the same notification and revocation
events as WebSockets, and suppresses recent duplicate deliveries. Your server
owns HTTP responses, HTTPS availability, durable delivery tracking and handler errors.
Use a securely generated secret of 10–100 bytes, shared with `createWebhook()`.
Conduit management uses `conduits`; bind events to an existing conduit with
`eventSubscriptions->createConduit($type, $condition, $conduitId)`. Your application
assigns shard transports and operates their webhook/WebSocket receivers.

## Chat (IRC)

Pass a `nick` and `channels` to connect the chat client. It speaks IRCv3 over
`wss://irc-ws.chat.twitch.tv` and parses tags into `Twitch\Parts\ChatMessage` parts.

```php
$twitch = new Twitch([
    'client_id'      => getenv('TWITCH_CLIENT_ID'),
    'client_secret'  => getenv('TWITCH_CLIENT_SECRET'),
    'token'          => getenv('TWITCH_ACCESS_TOKEN'),   // needs chat:read + chat:edit
    'refresh_token'  => getenv('TWITCH_REFRESH_TOKEN'),
    'nick'           => 'valgorithms',
    'channels'       => ['twitchdev'],
    'command_prefix' => '!',
]);

$twitch->on('ready', function (Twitch $twitch) {
    $irc = $twitch->getIrc();

    $irc->on('chat', function ($message) {
        if (str_contains(strtolower($message->content), 'hello')) {
            $message->reply('hey!');
        }
    });

    $irc->registerCommand('ping', fn ($message) => $message->say('pong'));
});
```

### Staying connected

Once it has logged in, the chat client keeps itself connected:

- A connection that closes is replaced, and its channels are joined again.
- A connection that goes quiet gets a `PING`. No reply within `pong_timeout`
  means it is dead, which is how a dropped network shows up: no close ever
  arrives.
- Twitch's `RECONNECT` gets a new connection at once.
- Attempts back off along `retry_delays` (1s, 2s, 5s … up to a minute). When
  those run out, `chat.reconnect_failed` fires once, and it keeps trying every
  `keep_trying_every` seconds (5 minutes).
- `$twitch->getIrc()->reconnect()` tries at once, whatever the schedule is
  doing. It resolves when logged in and rejects with why that attempt failed.
  `isConnected()` says where things stand.
- A token refreshed while chat is down is the one the next login uses. A login
  Twitch refuses sends the token through the same recovery as an API call's 401.

The first connection is the caller's: `bootstrap()` rejects if it fails.

### Sending messages

`Irc::sendMessage($channel, $text, $replyId)` queues a message and returns a
promise resolving when it is written to the socket. IRC does not acknowledge
delivery: use Helix `chat->sendMessage()` when you need Twitch's `is_sent` /
`drop_reason` response. Messages sent while disconnected, rejected input and
queue overflow reject; queued messages reject when the connection is lost.
Legacy `say()` / `ChatMessage::reply()` report failures through `chat.message_failed`.

The default queue permits 20 messages per 30 seconds and one per second per
channel, with at most 100 queued messages. These conservative defaults are safe
for regular accounts. Configure `message_limit`, `message_window`,
`channel_message_interval` and `queue_limit` inside `irc` for your account's
permissions; limits apply across all connections using the same Twitch account.
Input must contain 1–500 characters and no CR, LF or NUL. `raw()` intentionally
bypasses the message queue and is for trusted protocol commands.

### Richer commands

`Twitch\Chat\CommandClient` layers aliases, per-user cooldowns, permission
levels and an auto `!help` on top of the plain `command` event:

```php
use Twitch\Chat\Command;
use Twitch\Chat\CommandClient;

$cc = new CommandClient($twitch);

$cc->command('dice', fn ($m) => $m->reply('🎲 ' . random_int(1, 6)), cooldown: 10, description: 'Roll a die');

$cc->command('so', function ($m, $args) use ($twitch) {
    $twitch->chat->shoutout($m->tags['room-id'], $args[0], $twitch->getUserId());
}, aliases: ['shoutout'], permission: Command::MODERATOR, cooldown: 30, description: 'Shout a channel out');
```

Moderators and the broadcaster bypass cooldowns; `permission` also accepts a
`fn (ChatMessage $m): bool` predicate.

## Events

| Event | Payload | Notes |
| --- | --- | --- |
| `init` / `ready` | `Twitch` | Authenticated and connected. |
| `error` | `\Throwable`, `Twitch` | |
| `token_refreshed` | `string $token`, `Twitch` | |
| `reauthorized` | `string $token`, `Twitch` | New grant validated and adopted. |
| `closed` | `Twitch` | After `close()`. |
| `eventsub` | `string $type`, `array $event`, `array $meta`, `Twitch` | Every notification. |
| `eventsub.<type>` | `array $event`, `array $meta`, `Twitch` | e.g. `eventsub.channel.chat.message`. |
| `eventsub.ready` | `string $sessionId`, `Twitch` | Welcome on a new session or handoff. |
| `eventsub.keepalive` | `Twitch` | |
| `eventsub.revoked` | `array $subscription`, `Twitch` | |
| `eventsub.disconnected` | `int $code`, `string $reason`, `Twitch` | |
| `eventsub.subscription_failed` | `array $specification`, `\Throwable`, `Twitch` | Automatic replay failed. |
| `chat` | `ChatMessage` | Any chat line. |
| `command` | `string $name`, `array $args`, `ChatMessage` | Prefix commands. |
| `chat.connected` | `Twitch` | Logged in to chat, including after a reconnect. |
| `chat.disconnected` | `int $code`, `string $reason`, `Twitch` | A chat connection that was up is gone. `$code` is 0 when the client gave up on it. |
| `chat.reconnecting` | `int $attempt`, `float $delay`, `string $reason`, `Twitch` | Attempt `$attempt` of this outage starts in `$delay` seconds. |
| `chat.reconnect_failed` | `int $attempts`, `string $reason`, `Twitch` | `retry_delays` ran out. Once per outage; retrying carries on. |
| `chat.auth_failed` | `string $notice`, `Twitch` | Twitch refused the chat login. Once per outage. |
| `chat.message_failed` | `string $channel`, `string $message`, `\Throwable`, `Twitch` | Legacy `say()` failed to write. |
| `chat.join` / `chat.part` | `string $nick`, `string $channel`, `Twitch` | Membership notice. |
| `chat.notice` / `chat.clearchat` / `chat.clearmsg` / `chat.usernotice` / `chat.roomstate` | `array $tags`, `string $params`, `Twitch` | Raw parsed IRC event. |

## Configuration

| Option | Default | |
| --- | --- | --- |
| `client_id` | — | **Required.** |
| `client_secret` | `''` | Enables the app-token flow. |
| `token` / `refresh_token` | `null` | A user token and its refresh token. |
| `scopes` | `[]` | Informational. |
| `loop` | `Loop::get()` | A `React\EventLoop\LoopInterface`. |
| `logger` | `NullLogger` | Any PSR-3 logger. |
| `socket_options` | `[]` | Passed to `React\Socket\Connector`. |
| `nick` | `null` | Set to connect the IRC client. |
| `channels` | `[]` | Normalised to `#lowercase`. |
| `command_prefix` | `'!'` | |
| `eventsub` | `false` | Set to connect the EventSub client. |
| `eventsub_options` | `[]` | `retry_delays` (1, 2, 5, 10, 20, 30, 60), `keep_trying_every` (300), `welcome_timeout` (15), `connect_timeout` (15), in seconds. |
| `token_store` | `null` | A `Twitch\Auth\TokenStoreInterface`. Read at startup, written through on every token change. |
| `reauthorize` | `null` | A `Twitch\Auth\ReauthorizerInterface`, used when refreshing can no longer recover a grant. |
| `irc` | `[]` | Reconnection: `retry_delays` (list), `keep_trying_every` (300), `jitter` (0.2), `ping_after` (60), `pong_timeout` (15), `welcome_timeout` (15), `connect_timeout` (15). Sending: `message_limit` (20), `message_window` (30), `channel_message_interval` (1), `queue_limit` (100). Times are seconds. |

## Development

Use `composer update --no-interaction --prefer-dist` for a fresh checkout without a lock.
Use `composer install` to reproduce an existing local lock. `composer verify` checks
the manifest/lock, platform requirements, installed dependency provenance and tests
without installing anything or contacting Twitch.

`composer test:api` runs the offline repository, hydration, token recovery and token
persistence checks with synthetic transports and credentials. `composer deps:check`
rejects local path packages by default. For intentional sibling development, use
`composer deps:check -- --allow-local-paths` and run the other checks separately;
this does not replace testing against published dependencies before release.

```bash
composer install
composer test          # phpunit
composer cs            # php-cs-fixer
composer validate
composer audit
```

The ignored `composer.lock` is local development state. Dependencies resolve from
published releases; no sibling checkout is required. CI checks supported PHP versions,
the lowest allowed dependency versions, formatting and dependency advisories.

## License

MIT — see [LICENSE](LICENSE).
