# Changelog

All notable changes to this project are documented here.

## [Unreleased]

### Added

- `Twitch\EventSub\SubscriptionTypes` — a constant for every EventSub
  subscription type plus the version Twitch currently expects for it.
  `EventSub::subscribe()` now resolves the version from it when one is not
  passed (so `channel.follow` → `2`, Guest Star → `beta`, the rest → `1`).
- Typed `EventSub` helpers: `onChatMessage()`, `onFollow()`, `onSubscriptions()`,
  `onCheer()`, `onRaid()`, `onStreamChange()`, `onChannelUpdate()`,
  `onAdBreakBegin()`, `onPointsRedemption()`, `onBans()`, plus `subscribeMany()`
  for batches.
- `EventSub::desiredSubscriptions()` / `forget()`; the desired set is now keyed
  by type + condition so repeat calls and reconnects no longer accumulate
  duplicate subscriptions.
- `Twitch\Chat\CommandClient` + `Command` — a `MessageCommandClient`-style layer
  over the IRC `command` event with aliases, per-user cooldowns (moderators and
  the broadcaster bypass), permission levels (`everyone` / `subscriber` / `vip` /
  `moderator` / `broadcaster`, or a predicate), and an auto-generated `!help`.

## [3.0.0] - 2026-09-08

Ground-up rewrite. TwitchPHP is now a DiscordPHP-style async framework rather than a
single-purpose chat self-bot. Nothing from the `2.0.x` line carries over — the
client, models and entry points are all new.

### Added

- **`Twitch\Twitch` client** — owns the OAuth token lifecycle (app token, user token,
  transparent refresh on `401`), the HTTP transport, the part factory, and the resource
  repositories. Emits `init` / `ready`.
- **24 Helix repositories** on the client — `users`, `channels`, `streams`, `games`,
  `chat`, `moderation`, `clips`, `videos`, `polls`, `predictions`, `channelPoints`
  (rewards + redemption queue), `subscriptions`, `eventSubscriptions`, `teams`,
  `schedule`, `charity`, `goals`, `raids`, `bits` (leaderboard + Cheermotes),
  `hypeTrain`, `search`, `whispers`, `ads`, `contentLabels` — each a
  `Twitch\Repository\AbstractRepository` with `freshen()` / `all()` (auto-paginates
  `pagination.cursor`) / `fetch()` / `save()` / `delete()`, `collectRows()` for
  sibling Parts, and typed convenience methods.
- **30 Parts** — hydrated `Twitch\Parts\*` models with `$fillable` allow-lists,
  `set*/get*Attribute` hooks (`setRawAttribute()` / `getRawAttribute()` for
  non-recursing mutators) and `Carbon` date casting.
- **EventSub over WebSocket** (`Twitch\EventSub\EventSub`) — `session_welcome` /
  `session_keepalive` / `session_reconnect` / `revocation` / `notification` handling,
  automatic reconnect and subscription replay. Fires `eventsub` and `eventsub.<type>`.
- **Chat client** (`Twitch\Chat\Irc`) — IRCv3 over `wss://irc-ws.chat.twitch.tv`, tag
  parsing into `ChatMessage` parts, `say()` / `reply()` / `join()` / `part()`,
  `registerCommand()`, auto-reconnect.
- **HTTP transport** extracted to the standalone [`twitchphp/http`](https://github.com/Valgorithms/TwitchPHP-Http)
  package: async queue with per-Client-ID points rate-limiting (`Ratelimit-*` headers),
  429 hold, 5xx/408 exponential backoff, typed `HttpException` hierarchy, and an
  `Endpoint` builder covering ~90 Helix endpoints.
- PSR-3 logging, `symfony/options-resolver` client options, PHPUnit suite, CI, and
  `php-cs-fixer` config.

### Changed

- Requires **PHP 8.4+**.
- Collections are now `Discord\Helpers\Collection` (via `discord-php-helpers/collection`).
- The `vzgcoders/helix` dependency is retired; its endpoint list moved into
  `twitchphp/http`.

### Removed

- The legacy `Twitch\Twitch` chat self-bot, the `Commands` / `Message` / `ModerationMethod`
  classes, and the competing `NeoPart*` model experiments.
