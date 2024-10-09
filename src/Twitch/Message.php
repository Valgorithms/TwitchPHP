<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
use Discord\Helpers\Collection;
use Discord\Parts\Part;
use React\Promise\PromiseInterface;

use function React\Promise\reject;

class Message
{
    public string $broadcaster_user_id;
    public string $broadcaster_user_login;
    public string $broadcaster_user_name;
    public ?string $source_broadcaster_user_id;
    public ?string $source_broadcaster_user_login;
    public ?string $source_broadcaster_user_name;
    public string $chatter_user_id;
    public string $chatter_user_login;
    public string $chatter_user_name;
    public string $message_id;
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
    public array $message;
    public string $color;
    /**
     * @var array<array{
     *     set_id: string,
     *     id: string,
     *     info: string
     * }>
     */
    public array $badges;
    /**
     * @var ?array<array{
     *     set_id: string,
     *     id: string,
     *     info: string
     * }>
     */
    public ?array $source_badges;
    public string $message_type;
    public ?array $cheer;
    public ?array $reply;
    public ?string $channel_points_custom_reward_id;
    public ?string $channel_points_animation_id;

    public function __construct(
        public Twitch &$twitch,
        private string|array $json_data
    ) {
        $this->fill($json_data);
        $this->__afterConstruct();
    }
    private function __afterConstruct(){
        $this->twitch->messageCache->pushItem($this);
        $this->twitch->lastmessage = $this;
        if ($channel = $this->getChannelAttribute()) $this->twitch->lastchannel = $channel;
        if ($user = $this->getUserAttribute()) $this->twitch->lastuser = $user;
    }

    public function fill(string|array $json_data): void
    {
        if (is_string($json_data)) $json_data = json_decode($json_data, true);
        if (is_array($json_data)) array_walk($json_data, function($value, $key) {
            if (property_exists($this, $key)) $this->$key = $value;
        });
    }

    public function sendReply(string $content): PromiseInterface
    {
        if ($channel = $this->getChannelAttribute()) return $channel->sendMessage("@{$this->chatter_user_name}, $content");
        return reject(new \Exception("Could not find channel to send reply to."));
    }

    public function __toString(): string
    {
        return $this->message['text'] ?? '';
    }

    /**
     * Returns the channel attribute.
     *
     * @return Channel|null
     */
    protected function getChannelAttribute(): ?Channel
    {
        if (! isset($this->broadcaster_user_id)) return null;

        $channel = null;
        if ($channels = &$this->twitch->channelCache) {
            if (! $channel = $channels->get('broadcaster_user_id', $this->broadcaster_user_id)) {
                if (is_string($this->json_data)) $json_data = json_decode($this->json_data, true);
                if (is_array($json_data)) {
                    $channel = new Channel($this->twitch, $json_data);
                    $channels->push($channel);
                }
            }
        }

        /** @var Channel|null $user */
        return $channel;
    }

    protected function getUserAttribute(): ?User
    {
        if (! isset($this->broadcaster_user_id)) return null;
        
        $user = null;
        if ($users = &$this->twitch->userCache) {
            if (! $user = $users->get('broadcaster_user_id', $this->broadcaster_user_id)) {
                if (is_string($this->json_data)) $json_data = json_decode($this->json_data, true);
                if (is_array($json_data)) {
                    $json_data['id'] = $this->chatter_user_id;
                    $json_data['display_name'] = $this->chatter_user_name;
                    $user = new User($this->twitch, $json_data);
                    $users->push($user);
                }
            }
        }

        /** @var User|null $user */
        return $user;
    }

    /**
     * Converts a string to studlyCase.
     *
     * This is a port of updated Laravel's implementation, a non-regex with
     * static cache. The Discord\studly() is kept due to unintended bug and we
     * do not want to introduce BC by replacing it. This method is private
     * static as we may move it outside this class in future.
     *
     * @param string $string The string to convert.
     *
     * @return string
     */
    private static function studly(string $string): string
    {
        static $studlyCache = [];

        if (isset($studlyCache[$string])) {
            return $studlyCache[$string];
        }

        $words = explode(' ', str_replace(['-', '_'], ' ', $string));

        $studlyWords = array_map('ucfirst', $words);

        return $studlyCache[$string] = implode($studlyWords);
    }
    
    /**
     * Checks if there is a get mutator present.
     *
     * @param string $key The attribute name to check.
     *
     * @return string|false Either a string if it is a method or false.
     */
    private function checkForGetMutator(string $key)
    {
        $str = 'get'.self::studly($key).'Attribute';

        if (method_exists($this, $str)) {
            return $str;
        }

        return false;
    }

    /**
     * Checks if there is a set mutator present.
     *
     * @param string $key The attribute name to check.
     *
     * @return string|false Either a string if it is a method or false.
     */
    private function checkForSetMutator(string $key)
    {
        $str = 'set'.self::studly($key).'Attribute';

        if (method_exists($this, $str)) {
            return $str;
        }

        return false;
    }
    
    /**
     * Gets an attribute on the part.
     *
     * @param string $key The key to the attribute.
     *
     * @return mixed      Either the attribute if it exists or void.
     * @throws \Exception
     */
    private function getAttribute(string $key)
    {
        /*if (isset($this->repositories[$key])) {
            if (! isset($this->repositories_cache[$key])) {
                $this->repositories_cache[$key] = $this->factory->create($this->repositories[$key], $this->getRepositoryAttributes());
            }

            return $this->repositories_cache[$key];
        }*/

        if ($str = $this->checkForGetMutator($key)) {
            error_log("Calling $str");
            return $this->{$str}();
        }

        if (! isset($this->$key)) {
            return null;
        }

        return $this->$key;
    }

    /**
     * Sets an attribute on the part.
     *
     * @param string $key   The key to the attribute.
     * @param mixed  $value The value of the attribute.
     */
    private function setAttribute(string $key, $value): void
    {
        if ($str = $this->checkForSetMutator($key)) {
            $this->{$str}($value);
            return;
        }

        if (property_exists($this, $key)) {
            $this->$key = $value;
        }
    }
    
    /**
     * Handles dynamic get calls onto the part.
     *
     * @param string $key The attributes key.
     *
     * @return mixed The value of the attribute.
     *
     * @throws \Exception
     * @see Part::getAttribute() This function forwards onto getAttribute.
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Handles dynamic set calls onto the part.
     *
     * @param string $key   The attributes key.
     * @param mixed  $value The attributes value.
     *
     * @see self::setAttribute() This function forwards onto setAttribute.
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __debugInfo(): ?array
    {
        $properties = get_object_vars($this);
        unset($properties['twitch']);
        return $properties;
    }
}
