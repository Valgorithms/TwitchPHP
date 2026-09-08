# TwitchPHP

An async [ReactPHP](https://reactphp.org/) framework for the **Twitch Helix API**, **EventSub** over
WebSocket, and **chat** (IRC over WebSocket) — designed and built like
[DiscordPHP](https://github.com/discord-php/DiscordPHP).

```php
use Twitch\Twitch;

$twitch = new Twitch([
    'client_id'     => getenv('TWITCH_CLIENT_ID'),
    'client_secret' => getenv('TWITCH_CLIENT_SECRET'), // app token, or:
    'token'         => getenv('TWITCH_ACCESS_TOKEN'),  // a user token
    'refresh_token' => getenv('TWITCH_REFRESH_TOKEN'), // refreshed automatically on 401
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
| A client id + secret only | `client_id`, `client_secret` | Requests an **app access token** (client-credentials). Good for public data and EventSub. |
| A user access token | `token` (+ optional `refresh_token`) | Validated on startup. With a `refresh_token`, an invalid/expired token is refreshed transparently, and any Helix `401` triggers one refresh-and-retry. |

`Twitch\Http\OAuth` also exposes `authorizationUrl()`, `exchangeCode()`, `deviceCode()`, `validate()`
and `revoke()` for building the authorization-code / device-code flows in your own app.

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

```php
// Look users up by login and cache them
$twitch->users->byLogins(['twitchdev', 'twitch'])->then(function ($users) {
    foreach ($users as $user) {
        echo "{$user->display_name} ({$user->id})\n";
    }
});

// Who is live among a set of channels
$twitch->streams->live(['userLogins' => ['twitchdev']])->then(function ($streams) {
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

Repositories share a common surface from `Twitch\Repository\AbstractRepository`:
`freshen()`, `all()` (auto-follows `pagination.cursor`), `fetch($id)`, `save($part)`,
`delete($part)`, plus `get()`, `first()`, `filter()` and iteration.

## EventSub

Enable the WebSocket transport with `'eventsub' => true`, then subscribe once the session is ready:

```php
$twitch = new Twitch([
    'client_id'     => getenv('TWITCH_CLIENT_ID'),
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

`SubscriptionTypes` has a constant for every EventSub type and knows the version
Twitch currently wants for it. Session reconnects (`session_reconnect`),
keepalives and `revocation` are handled for you, and the desired subscription set
(deduplicated) is replayed automatically onto a fresh session after a drop.

## Chat (IRC)

Pass a `nick` and `channels` to connect the chat client. It speaks IRCv3 over
`wss://irc-ws.chat.twitch.tv` and parses tags into `Twitch\Parts\ChatMessage` parts.

```php
$twitch = new Twitch([
    'client_id'      => getenv('TWITCH_CLIENT_ID'),
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
| `closed` | `Twitch` | After `close()`. |
| `eventsub` | `string $type`, `array $event`, `array $meta`, `Twitch` | Every notification. |
| `eventsub.<type>` | `array $event`, `array $meta`, `Twitch` | e.g. `eventsub.channel.chat.message`. |
| `eventsub.ready` / `eventsub.keepalive` / `eventsub.revoked` / `eventsub.disconnected` | | |
| `chat` | `ChatMessage` | Any chat line. |
| `command` | `string $name`, `array $args`, `ChatMessage` | Prefix commands. |

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

## Development

```bash
composer install
composer test          # phpunit
composer cs            # php-cs-fixer
```

## License

MIT — see [LICENSE](LICENSE).
