# Changelog

All notable changes to this project are documented here.

## [Unreleased]

## [3.2.0] - 2026-09-23

### Added

- `HypeTrainRepository::status()` and `isActive()`, on `hypetrain/status` — the
  current train, the all-time high and the shared all-time high, or `null` when
  the channel has no data.

- `Twitch\Auth\TokenStoreInterface` + `EnvFileTokenStore` — somewhere durable to
  keep the token pair, passed as the new `token_store` option. Twitch rotates
  the refresh token on every refresh and invalidates the old one, so a client
  that refreshed without persisting the result locked itself out of its own
  account the moment the process restarted. The store is read at startup (so
  `token` / `refresh_token` need not be passed at all) and written through on
  every token change.
- `Twitch\Auth\ReauthorizerInterface` + `DeviceCodeReauthorizer` — a strategy
  for obtaining a brand-new grant when refreshing can no longer recover one,
  passed as the new `reauthorize` option. The device-code flow needs no
  redirect URI and no local HTTP listener; its prompt is injected, so the same
  flow works from a CLI, a bot DM, or anywhere else. Defaults to disabled, so a
  headless client surfaces the 401 rather than hanging on a prompt nobody sees.
- `Twitch::reauthorize()` and the `reauthorized` event.
- `examples/reauthorize.php` (one-shot re-authorization) and
  `examples/self-healing-auth.php` (the full lifecycle wired up).

### Changed

- A 401 is now recovered from by refreshing *and*, failing that, by
  re-authorizing; the request is then retried once and continues. If neither
  route works the original 401 surfaces rather than the recovery error.
- Concurrent callers now join an in-flight token refresh instead of being
  rejected with "A token refresh is already in progress" — and, more
  importantly, instead of each burning a rotation of their own.
- EventSub Hype Train subscriptions default to version 2; Twitch withdrew
  version 1 along with Get Hype Train Events.
- Requires `twitchphp/http` `^1.1` instead of `dev-main`, so a release is pinned
  to a transport it was tested with rather than whatever `main` holds when it
  is installed.

### Deprecated

- `HypeTrainRepository::forBroadcaster()` and `latest()`. Twitch withdrew Get
  Hype Train Events, which now answers 410; both reject with a message pointing
  at `status()` instead of a bare HTTP error.

### Fixed

- A missing *scope* no longer triggers a token refresh. Every 401 was being
  mapped to `InvalidTokenException`, so each scope failure spent a full
  refresh round trip (~550ms against ~90ms) and a refresh-token rotation on a
  retry that could never succeed. Scope failures now raise
  `MissingScopeException`, which names the scopes that would satisfy the call.
- A 401 from an endpoint that requires a *different kind* of token (conduits,
  which accept app access tokens only) no longer triggers recovery either — it
  previously refreshed on every call and would have re-authorized in a loop.
- `EnvFileTokenStore` matches `.env` keys in any case. It matched
  `twitch_access_token` exactly, so against a file spelled
  `TWITCH_ACCESS_TOKEN` — as the README and examples spell it — every save
  appended lowercase copies, and the next start read the token from before the
  last rotation, took a 401 and refreshed again. Existing lines keep their
  spelling, every matching line is updated, and new keys follow the file's case.

## [3.1.0] - 2026-09-08

### Added

- Five more Helix repositories — `conduits` (EventSub conduits + shards),
  `entitlements` (Drops), `analytics` (extension / game report URLs),
  `guestStar` (beta), `extensions` (installed / active / live-channels /
  transactions). 29 repositories in total.
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
