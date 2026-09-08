# Changelog

All notable changes to this project are documented here.

## [Unreleased]

### Added

- 17 more Helix resource repositories on the client: `clips`, `videos`, `polls`,
  `predictions`, `channelPoints` (custom rewards + redemptions), `subscriptions`,
  `eventSubscriptions` (the EventSub REST list), `teams`, `schedule`, `charity`,
  `goals`, `raids`, `bits` (leaderboard + Cheermotes), `hypeTrain`, `search`
  (categories + channels), `whispers`, `ads` (start commercial / schedule /
  snooze).
- Parts for the above: `Clip`, `Video`, `Poll`, `Prediction`, `CustomReward`,
  `RewardRedemption`, `Subscription`, `EventSubSubscription`, `Team`, `Schedule`,
  `ScheduleSegment`, `CharityCampaign`, `CharityDonation`, `Goal`, `Cheermote`,
  `BitsLeaderboardEntry`, `HypeTrainEvent`, `ChannelSearchResult`.
- `AbstractRepository::collectRows()` — shared hydrate-and-collect helper for
  repositories returning a sibling/nested Part.
- `Part::setRawAttribute()` / `getRawAttribute()` so a `set{Key}Attribute` mutator
  can store its shaped value without recursing.

## [2.0.0] - 2026-09-08

Ground-up rewrite. TwitchPHP is now a DiscordPHP-style async framework rather than a
single-purpose chat self-bot.

### Added

- **`Twitch\Twitch` client** — owns the OAuth token lifecycle (app token, user token,
  transparent refresh on `401`), the HTTP transport, the part factory, and the resource
  repositories. Emits `init` / `ready`.
- **Helix repositories** — `users`, `channels`, `streams`, `games`, `chat`, `moderation`,
  each a `Twitch\Repository\AbstractRepository` with `freshen()` / `all()` (auto-paginates
  `pagination.cursor`) / `fetch()` / `save()` / `delete()` and collection access.
- **Parts** — hydrated `Twitch\Parts\*` models (`User`, `Channel`, `Stream`, `Game`,
  `Chatter`, `ChatSettings`, `Emote`, `ChatBadge`, `BannedUser`, `Moderator`, `BlockedTerm`,
  `ChatMessage`) with `$fillable` allow-lists, `set*/get*Attribute` hooks and `Carbon` date
  casting.
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
