<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch\Parts;

use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use Twitch\Builders\MessageBuilder;
use Twitch\Parts\NeoPart;
use Twitch\Parts\Channel\Message;
use Twitch\Twitch;
use React\Promise\ExtendedPromiseInterface;

/**
 * @since 3.0.0
 *
 * @property string       $id                     An ID that identifies the user.
 * @property string       $login                  The user’s login name.
 * @property string       $display_name           The user’s display name.
 * @property string       $type                   The type of user. Possible values are: admin, global_mod, staff, "" (Normal user).
 * @property string       $broadcaster_type       The type of broadcaster. Possible values are: affiliate, partner, "" (Normal broadcaster).
 * @property string       $description            The user’s description of their channel.
 * @property string       $profile_image_url      A URL to the user’s profile image.
 * @property string       $offline_image_url      A URL to the user’s offline image.
 * @property int|null     $view_count             The number of times the user’s channel has been viewed. (Deprecated)
 * @property string|null  $email                  The user’s verified email address.
 * @property string       $created_at             The UTC date and time that the user’s account was created. The timestamp is in RFC3339 format.
 *
 * @method ExtendedPromiseInterface<Message> sendMessage(MessageBuilder $builder)
 * @method string __toString() Returns the user's display name as a string.
 */
class User extends NeoPart
{
    public ?string $discrim = 'id';
    public ?string $cache = 'user';

    public ?string $id;
    public ?string $display_name;
    public ?string $type;
    public ?string $broadcaster_type;
    public ?string $description;
    public ?string $profile_image_url;
    public ?string $offline_image_url;
    public ?int    $view_count;
    public ?string $email;
    public ?Carbon $created_at;

    public function __construct(
        public null|Twitch|MockObject &$twitch,
        private null|string|array $json_data
    ) {
        $json_data = &$this->json_data;
        if (is_string($json_data)) $json_data = json_decode($json_data, true);
        if (! is_array($json_data)) $json_data = ['id' => 1];
        if (! isset($json_data['id'])) $json_data['id'] = 1; // Default to 1
        parent::__construct($twitch, $json_data);
    }
    
    public function __toString(): string
    {
        return $this->display_name ?? $this->id ?? '';
    }
}