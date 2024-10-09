<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Carbon\Carbon;
use Discord\Helpers\Collection;
use React\Promise\PromiseInterface;

class Channel
{
    public string $broadcaster_user_id;
    public string $broadcaster_user_login;
    public string $broadcaster_user_name;

    public function __construct(
        public Twitch &$twitch,
        private string|array $json_data
    ) {
        $this->fill($json_data);
        $this->__afterConstruct();
    }
    private function __afterConstruct(){
        $this->twitch->channelCache->pushItem($this);
        $this->twitch->lastchannel = $this;
        $this->getUserAttribute();
    }

    public function fill(string|array $json_data): void
    {
        if (is_string($json_data)) $json_data = json_decode($json_data, true);
        if (is_array($json_data)) array_walk($json_data, function($value, $key) {
            if (property_exists($this, $key)) $this->$key = $value;
        });
    }

    protected function getUserAttribute(): ?User
    {
        if (! isset($this->broadcaster_user_id)) return null;
        
        $user = null;
        if ($users = &$this->twitch->userCache) {
            if (! $user = $users->get('id', $this->broadcaster_user_id)) {
                $json_data = null;
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
     * Sends a message to the Twitch channel.
     *
     * @param string $data The message to be sent.
     *
     * @return void
     */
    public function sendMessage(string $data): PromiseInterface
    {
        $this->twitch->logger->info("[REPLY] #$this - $data");
        return $this->twitch->write("PRIVMSG #$this :$data\n");
    }
    
    public function __toString(): string
    {
        return $this->broadcaster_user_name ?? '';
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
            return $this->{$str}();
        }

        if (! isset($this->attributes[$key])) {
            return null;
        }

        return $this->attributes[$key];
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