<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch\Parts;

//use Discord\Helpers\BigInt;
use Discord\Helpers\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Twitch\Twitch;

trait NeoPartTrait
{
    public ?string $discrim = 'broadcaster_user_id'; // broadcaster_user_id, message_id
    public ?string $cache = null; // channel, user, message

    public function __construct(
        public null|Twitch|MockObject &$twitch,
        private null|string|array $json_data
    ) {
        if ($json_data) $this->fill($json_data);
        $this->__afterConstruct();
    }
    private function __afterConstruct(): void
    {
        if (isset($this->twitch)) $this->pushToCache();
    }
    public function linkTwitch(null|Twitch|MockObject &$twitch): void
    {
        if ($this->twitch = $twitch) $this->pushToCache();
    }
    private function pushToCache(): void
    {
        if (isset($this->twitch->{$this->discrim.'Cache'})) {
            /** @var Collection collection */
            $collection = $this->twitch->{$this->discrim.'Cache'};
            $collection->set($this->{$this->disrim}, $this);
        }
        if ($this instanceof Message) $this->twitch->lastmessage = $this; // Backwards compatibility, will be removed in a later version
        if (! $this instanceof Channel) if ($channel = $this->getChannelAttribute()) $this->twitch->lastchannel = $channel;
        if (! $this instanceof User) if ($user = $this->getUserAttribute()) $this->twitch->lastuser = $user;
    }

    /**
     * Returns the channel attribute.
     *
     * @return Channel|null
     */
    protected function getChannelAttribute(): ?Channel
    {
        if (! isset($this->broadcaster_user_id)) return null;

        $json_data = $this->json_data ?? null;
        $channel = null;
        if ($channels = &$this->twitch->channelCache) {
            if (! $channel = $channels->get('broadcaster_user_id', $this->broadcaster_user_id)) {
                if (is_string($json_data)) $json_data = json_decode($json_data, true);
                if (is_array($json_data)) $channels->set($this->broadcaster_user_id, $channel = new Channel($this->twitch, $json_data));
            }
        }

        /** @var Channel|null $user */
        return $channel;
    }

    protected function getUserAttribute(): ?User
    {
        if (! isset($this->broadcaster_user_id)) return null;
        
        $json_data = $this->json_data ?? null;
        $user = null;
        if ($users = &$this->twitch->userCache) {
            if (is_string($json_data)) $json_data = json_decode($json_data, true);
            if (is_array($json_data)) {
                $json_data['id'] = $this->chatter_user_id;
                $json_data['display_name'] = $this->chatter_user_name;
            }
            /** @var User|null $user */
            if ($user = $users->get('broadcaster_user_id', $this->broadcaster_user_id)) {
                /** @var User $user */
                $user->fill($json_data);
                $users->offsetSet($user->id, $user);
            } else {
                $users->set($this->broadcaster_user_id, $user = new User($this->twitch, $json_data));
            }
        }

        /** @var User|null $user */
        return $user;
    }

    public function fill(string|array $json_data): self
    {
        if (is_string($json_data)) $json_data = json_decode($json_data, true);
        if (is_array($json_data)) foreach ($json_data as $key => $value) {
            if (property_exists($this, $key) && $value !== null && $value !== '')
                $this->$key = $value;
        }
        return $this;
    }

    /**
     * Resolves and returns a part based on the given attribute value and type.
     * 
     * This will not work if the original object is a normal Collection object.
     * It must be extended from Collection and have the $type property set statically, not from the constructor.
     *
     * @param mixed $value The attribute value to resolve.
     * @param string|null $type The type of cache to use. If null, defaults to the instance's cache type.
     * @return mixed|null The resolved part or null if not found.
     */
    public function getResolvableAttribute($value, ?string $type = null,)
    {
        if ( $type === null) $type = $this->cache;
        if (! isset($this->caches[$type])) return null;

        $json_data = $this->json_data ?? null;
        $part = null;
        if ($cache = &$this->twitch->{$type.'Cache'}) {
            if (! $cache instanceof Collection) return $part;
            if (! $part = $cache->get($this->caches[$type], $value)) {
                if (is_string($json_data)) $json_data = json_decode($json_data, true);
                if (is_array($json_data)) {
                    $reflection = new \ReflectionClass($cache);
                    $property = $reflection->getProperty('class');
                    $property->setAccessible(true);
                    $class = $property->getValue($cache);
                    if (! $class instanceof NeoPartInterface) return $part;
                    $part = new $class($this->twitch, $json_data);
                    $cache->push($part);
                }
            }
        }
        return $part;
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
        if (method_exists($this, $str)) return $str;
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
        if (method_exists($this, $str)) return $str;
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
        if ($str = $this->checkForGetMutator($key)) return $this->{$str}();
        if (isset($this->$key)) return $this->$key;
        return null;
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

        if (property_exists($this, $key)) $this->$key = $value;
    }

    /**
     * Magic method to convert the object to a string.
     *
     * @return string An empty string (default).
     */
    public function __toString(): string
    {
        return '';
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

    /**
     * Restores the object state from an array of data.
     *
     * This method is called during the unserialization process to restore the object's state.
     *
     * @param array $data The array of data to restore the object's state from.
     * 
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->fill($data);
        $this->__afterConstruct();
    }
    
    /**
     * Serializes the object to an array representation.
     *
     * This method is called during the serialization process to convert the object
     * into an array. It retrieves all object properties using `get_object_vars()`,
     * then removes the 'twitch' property from the array before returning it.
     *
     * @return array The array representation of the object, excluding the 'twitch' property.
     */
    public function __serialize(): array
    {
        $properties = get_object_vars($this);
        unset($properties['twitch']);
        return $properties;
    }

    /**
     * Provides debugging information for the object.
     *
     * This method is called by var_dump() when dumping an object to get the properties that should be shown.
     *
     * @return array An array of the object's properties.
     */
    public function __debugInfo(): array
    {
        return $this->__serialize();
    }
}
