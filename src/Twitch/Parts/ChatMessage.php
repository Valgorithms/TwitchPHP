<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Parts;

/**
 * A parsed chat `PRIVMSG` from the IRC gateway.
 *
 * Built by {@see \Twitch\Chat\Irc} from the raw IRC line and its IRCv3 tags.
 * `->reply(...)` and `->say(...)` route back through the same connection.
 *
 * @property string               $id            IRCv3 message id (`id` tag).
 * @property string               $channel       Channel login, no leading `#`.
 * @property string               $user          Sender login.
 * @property string               $user_id
 * @property string               $display_name
 * @property string               $content       The message text.
 * @property string               $color         Sender name colour (`#RRGGBB` or '').
 * @property bool                  $is_mod
 * @property bool                  $is_subscriber
 * @property bool                  $is_vip
 * @property bool                  $is_broadcaster
 * @property bool                  $is_first_message
 * @property int                   $bits
 * @property array<string, string> $badges
 * @property array<string, mixed>  $tags          Every raw IRCv3 tag.
 * @property \Carbon\CarbonImmutable $timestamp
 */
final class ChatMessage extends Part
{
    protected array $fillable = [
        'id', 'channel', 'user', 'user_id', 'display_name', 'content', 'color',
        'is_mod', 'is_subscriber', 'is_vip', 'is_broadcaster', 'is_first_message',
        'bits', 'badges', 'emotes', 'tags', 'timestamp', 'reply_parent_msg_id',
    ];

    protected array $dates = ['timestamp'];

    /** Sends a message to the same channel. */
    public function say(string $message): void
    {
        $this->twitch->getIrc()?->say($this->getAttribute('channel'), $message);
    }

    /** Replies to this message in the same channel (threaded where supported). */
    public function reply(string $message): void
    {
        $this->twitch->getIrc()?->say($this->getAttribute('channel'), $message, $this->getAttribute('id'));
    }
}
