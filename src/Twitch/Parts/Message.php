<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch\Parts;

use Carbon\Carbon; // NYI
use React\Promise\PromiseInterface;

use function React\Promise\reject;

/**
 * @since 3.0.0
 *
 * @property ?string $discrim The discriminator for the message.
 * @property ?string $cache The cache key for the message.
 * @property string $broadcaster_user_id The ID of the broadcaster.
 * @property string $broadcaster_user_login The login name of the broadcaster.
 * @property string $broadcaster_user_name The display name of the broadcaster.
 * @property ?string $source_broadcaster_user_id The ID of the source broadcaster.
 * @property ?string $source_broadcaster_user_login The login name of the source broadcaster.
 * @property ?string $source_broadcaster_user_name The display name of the source broadcaster.
 * @property string $chatter_user_id The ID of the user who sent the message.
 * @property string $chatter_user_login The login name of the user who sent the message.
 * @property string $chatter_user_name The display name of the user who sent the message.
 * @property string $message_id The ID of the message.
 * @property ?string $source_message_id The ID of the source message.
 * @property array $message The content of the message.
 * @property string $color The color of the message.
 * @property array $badges The badges associated with the message.
 * @property ?array $source_badges The badges associated with the source message.
 * @property string $message_type The type of the message.
 * @property ?array $cheer The cheer information associated with the message.
 * @property ?array $reply The reply information associated with the message.
 * @property ?string $channel_points_custom_reward_id The ID of the custom reward associated with the message.
 * @property ?string $channel_points_animation_id The ID of the animation associated with the channel points.
 *
 * @method PromiseInterface sendReply(string $content) Sends a reply message to the user who sent the original message.
 * @method string __toString() Returns the string representation of the message.
 */
class Message extends NeoPart
{
    public ?string $discrim = 'message_id';
    public ?string $cache = 'message';

    public string  $broadcaster_user_id;
    public string  $broadcaster_user_login;
    public string  $broadcaster_user_name;
    public ?string $source_broadcaster_user_id;
    public ?string $source_broadcaster_user_login;
    public ?string $source_broadcaster_user_name;
    public string  $chatter_user_id;
    public string  $chatter_user_login;
    public string  $chatter_user_name;
    public string  $message_id;
    public ?string $source_message_id;
    /**
     * @var array{
     *     text: string,
     *     fragments: array<array{
     *         type: string,
     *         text: string,
     *         cheermote: ?string,
     *         emote: ?string,
     *         mention: ?string
     *     }>
     * }
     */
    public array   $message;
    public string  $color;
    /**
     * @var array<array{
     *     set_id: string,
     *     id: string,
     *     info: string
     * }>
     */
    public array   $badges;
    /**
     * @var ?array<array{
     *     set_id: string,
     *     id: string,
     *     info: string
     * }>
     */
    public ?array  $source_badges;
    public string  $message_type;
    public ?array  $cheer;
    public ?array  $reply;
    public ?string $channel_points_custom_reward_id;
    public ?string $channel_points_animation_id;

    /**
     * Sends a reply message to the user who sent the original message.
     *
     * @param string $content The content of the reply message.
     * @return PromiseInterface A promise that resolves when the message is sent.
     * @throws \Exception If the channel to send the reply to could not be found.
     */
    public function sendReply(string $content): PromiseInterface
    {
        if ($channel = $this->getChannelAttribute()) return $channel->sendMessage("@{$this->chatter_user_name}, $content");
        return reject(new \Exception("Could not find channel to send reply to."));
    }

    public function __toString(): string
    {
        return $this->message['text'] ? "{$this->chatter_user_name}: $this->message['text']}" : '';
    }
}
