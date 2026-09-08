<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\EventSub;

/**
 * Every EventSub subscription type string, plus the version Twitch currently
 * expects for it. {@see EventSub::subscribe()} looks the version up here when
 * one is not given, so callers never have to remember that `channel.follow` is
 * `2` while everything around it is `1`.
 *
 * @link https://dev.twitch.tv/docs/eventsub/eventsub-subscription-types/
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class SubscriptionTypes
{
    // ── Channel ────────────────────────────────────────────────────────
    public const CHANNEL_UPDATE = 'channel.update';
    public const CHANNEL_FOLLOW = 'channel.follow';
    public const CHANNEL_AD_BREAK_BEGIN = 'channel.ad_break.begin';
    public const CHANNEL_CHAT_CLEAR = 'channel.chat.clear';
    public const CHANNEL_CHAT_CLEAR_USER_MESSAGES = 'channel.chat.clear_user_messages';
    public const CHANNEL_CHAT_MESSAGE = 'channel.chat.message';
    public const CHANNEL_CHAT_MESSAGE_DELETE = 'channel.chat.message_delete';
    public const CHANNEL_CHAT_NOTIFICATION = 'channel.chat.notification';
    public const CHANNEL_CHAT_SETTINGS_UPDATE = 'channel.chat_settings.update';
    public const CHANNEL_CHAT_USER_MESSAGE_HOLD = 'channel.chat.user_message_hold';
    public const CHANNEL_CHAT_USER_MESSAGE_UPDATE = 'channel.chat.user_message_update';
    public const CHANNEL_SHARED_CHAT_BEGIN = 'channel.shared_chat.begin';
    public const CHANNEL_SHARED_CHAT_UPDATE = 'channel.shared_chat.update';
    public const CHANNEL_SHARED_CHAT_END = 'channel.shared_chat.end';
    public const CHANNEL_SUBSCRIBE = 'channel.subscribe';
    public const CHANNEL_SUBSCRIPTION_END = 'channel.subscription.end';
    public const CHANNEL_SUBSCRIPTION_GIFT = 'channel.subscription.gift';
    public const CHANNEL_SUBSCRIPTION_MESSAGE = 'channel.subscription.message';
    public const CHANNEL_CHEER = 'channel.cheer';
    public const CHANNEL_RAID = 'channel.raid';
    public const CHANNEL_BAN = 'channel.ban';
    public const CHANNEL_UNBAN = 'channel.unban';
    public const CHANNEL_UNBAN_REQUEST_CREATE = 'channel.unban_request.create';
    public const CHANNEL_UNBAN_REQUEST_RESOLVE = 'channel.unban_request.resolve';
    public const CHANNEL_MODERATE = 'channel.moderate';
    public const CHANNEL_MODERATOR_ADD = 'channel.moderator.add';
    public const CHANNEL_MODERATOR_REMOVE = 'channel.moderator.remove';
    public const CHANNEL_VIP_ADD = 'channel.vip.add';
    public const CHANNEL_VIP_REMOVE = 'channel.vip.remove';
    public const CHANNEL_BITS_USE = 'channel.bits.use';
    public const CHANNEL_WARNING_ACKNOWLEDGE = 'channel.warning.acknowledge';
    public const CHANNEL_WARNING_SEND = 'channel.warning.send';
    public const CHANNEL_SUSPICIOUS_USER_MESSAGE = 'channel.suspicious_user.message';
    public const CHANNEL_SUSPICIOUS_USER_UPDATE = 'channel.suspicious_user.update';

    // ── Channel Points ─────────────────────────────────────────────────
    public const CHANNEL_POINTS_AUTOMATIC_REWARD_REDEMPTION_ADD = 'channel.channel_points_automatic_reward_redemption.add';
    public const CHANNEL_POINTS_CUSTOM_REWARD_ADD = 'channel.channel_points_custom_reward.add';
    public const CHANNEL_POINTS_CUSTOM_REWARD_UPDATE = 'channel.channel_points_custom_reward.update';
    public const CHANNEL_POINTS_CUSTOM_REWARD_REMOVE = 'channel.channel_points_custom_reward.remove';
    public const CHANNEL_POINTS_CUSTOM_REWARD_REDEMPTION_ADD = 'channel.channel_points_custom_reward_redemption.add';
    public const CHANNEL_POINTS_CUSTOM_REWARD_REDEMPTION_UPDATE = 'channel.channel_points_custom_reward_redemption.update';

    // ── Polls / Predictions ────────────────────────────────────────────
    public const CHANNEL_POLL_BEGIN = 'channel.poll.begin';
    public const CHANNEL_POLL_PROGRESS = 'channel.poll.progress';
    public const CHANNEL_POLL_END = 'channel.poll.end';
    public const CHANNEL_PREDICTION_BEGIN = 'channel.prediction.begin';
    public const CHANNEL_PREDICTION_PROGRESS = 'channel.prediction.progress';
    public const CHANNEL_PREDICTION_LOCK = 'channel.prediction.lock';
    public const CHANNEL_PREDICTION_END = 'channel.prediction.end';

    // ── Charity / Goals / Hype Train ───────────────────────────────────
    public const CHARITY_CAMPAIGN_DONATE = 'channel.charity_campaign.donate';
    public const CHARITY_CAMPAIGN_START = 'channel.charity_campaign.start';
    public const CHARITY_CAMPAIGN_PROGRESS = 'channel.charity_campaign.progress';
    public const CHARITY_CAMPAIGN_STOP = 'channel.charity_campaign.stop';
    public const GOAL_BEGIN = 'channel.goal.begin';
    public const GOAL_PROGRESS = 'channel.goal.progress';
    public const GOAL_END = 'channel.goal.end';
    public const HYPE_TRAIN_BEGIN = 'channel.hype_train.begin';
    public const HYPE_TRAIN_PROGRESS = 'channel.hype_train.progress';
    public const HYPE_TRAIN_END = 'channel.hype_train.end';

    // ── Shoutouts ──────────────────────────────────────────────────────
    public const SHOUTOUT_CREATE = 'channel.shoutout.create';
    public const SHOUTOUT_RECEIVE = 'channel.shoutout.receive';

    // ── Guest Star (beta) ──────────────────────────────────────────────
    public const GUEST_STAR_SESSION_BEGIN = 'channel.guest_star_session.begin';
    public const GUEST_STAR_SESSION_END = 'channel.guest_star_session.end';
    public const GUEST_STAR_GUEST_UPDATE = 'channel.guest_star_guest.update';
    public const GUEST_STAR_SETTINGS_UPDATE = 'channel.guest_star_settings.update';

    // ── AutoMod ────────────────────────────────────────────────────────
    public const AUTOMOD_MESSAGE_HOLD = 'automod.message.hold';
    public const AUTOMOD_MESSAGE_UPDATE = 'automod.message.update';
    public const AUTOMOD_SETTINGS_UPDATE = 'automod.settings.update';
    public const AUTOMOD_TERMS_UPDATE = 'automod.terms.update';

    // ── Stream / User / Drops / Extensions / Conduits ──────────────────
    public const STREAM_ONLINE = 'stream.online';
    public const STREAM_OFFLINE = 'stream.offline';
    public const USER_UPDATE = 'user.update';
    public const USER_WHISPER_MESSAGE = 'user.whisper.message';
    public const USER_AUTHORIZATION_GRANT = 'user.authorization.grant';
    public const USER_AUTHORIZATION_REVOKE = 'user.authorization.revoke';
    public const DROP_ENTITLEMENT_GRANT = 'drop.entitlement.grant';
    public const EXTENSION_BITS_TRANSACTION_CREATE = 'extension.bits_transaction.create';
    public const CONDUIT_SHARD_DISABLED = 'conduit.shard.disabled';

    /**
     * Types whose current version is not `1`. Anything absent is `1`.
     *
     * @var array<string, string>
     */
    private const VERSIONS = [
        self::CHANNEL_UPDATE => '2',
        self::CHANNEL_FOLLOW => '2',
        self::CHANNEL_MODERATE => '2',
        self::CHANNEL_POINTS_AUTOMATIC_REWARD_REDEMPTION_ADD => '2',
        self::CHANNEL_BITS_USE => '1',
        self::CHANNEL_VIP_ADD => '1',
        self::CHANNEL_VIP_REMOVE => '1',
        self::GUEST_STAR_SESSION_BEGIN => 'beta',
        self::GUEST_STAR_SESSION_END => 'beta',
        self::GUEST_STAR_GUEST_UPDATE => 'beta',
        self::GUEST_STAR_SETTINGS_UPDATE => 'beta',
    ];

    /** The version Twitch currently expects for `$type` (`'1'` when unlisted). */
    public static function version(string $type): string
    {
        return self::VERSIONS[$type] ?? '1';
    }

    /** Whether `$type` is a known EventSub subscription type. */
    public static function isKnown(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Every declared subscription-type string.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        static $values = null;
        if ($values === null) {
            $values = [];
            foreach ((new \ReflectionClass(self::class))->getConstants() as $name => $value) {
                if ($name !== 'VERSIONS' && is_string($value)) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }
}
