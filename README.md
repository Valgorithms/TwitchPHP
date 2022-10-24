TwitchPHP
====

A chat self-bot built with ReactPHP for the official [Twitch TV](https://www.twitch.tv) Internet Relay Chat (IRC) interface.

## Before you start

Before you start using this Library, you **need** to know how PHP works, you need to know the language and you need to know how Event Loops and Promises work. This is a fundamental requirement before you start. Without this knowledge, you will only suffer.

## FAQ

1. Can I run TwitchPHP on a webserver (e.g. Apache, nginx)?
    - No, TwitchPHP will only run in CLI. If you want to have an interface for your bot you can integrate [react/http](https://github.com/ReactPHP/http) with your bot and run it through CLI.
2. PHP is running out of memory?
    - Try increase your memory limit using `ini_set('memory_limit', '-1');`.

## Getting Started

### Requirements

- PHP 8
    - This library is being built with PHP8 support in mind. PHP7 is no longer supported, but can easily be forked and modified to do so.
- Composer
- [DiscordPHP](https://github.com/discord-php/DiscordPHP/) (Optional)

### Windows and SSL

Unfortunately PHP on Windows does not have access to the Windows Certificate Store. This is an issue because TLS gets used and as such certificate verification gets applied (turning this off is **not** an option).

You will notice this issue by your script exiting immediately after one loop turn without any errors. Unfortunately there is for some reason no error or exception.

As such users of this library need to download a [Certificate Authority extract](https://curl.haxx.se/docs/caextract.html) from the cURL website.<br>
The path to the caextract must be set in the [`php.ini`](https://secure.php.net/manual/en/openssl.configuration.php) for `openssl.cafile`.

#### Recommended Extensions

- The latest PHP version.
- One of `ext-uv` (preferred), `ext-libev` or `evt-event` for a faster, and more performant event loop.
- `ext-mbstring` if handling non-english characters.

### Installing TwitchPHP

TwitchPHP is installed using [Composer](https://getcomposer.org).

1. Run `composer require VZGCoders/TwitchPHP`. This will install the lastest release.
2. Include the Composer autoload file at the top of your main file:
    - `include __DIR__.'/vendor/autoload.php';`
3. Make a bot!

## Configuration

1. Add the required "secret" and "nick" values.
2. Customize your commands and responses.

### Basic Example

```php
<?php
ignore_user_abort(1);
set_time_limit(0); // Don't time out the script
ini_set('max_execution_time', 0); // Don't time out the script
ini_set('memory_limit', '-1'); //Unlimited memory usage

require 'vendor/autoload.php';

$nick = 'ValZarGaming';  // Twitch username (Case sensitive)

//$loop = \React\EventLoop\Loop::get();
$logger = new Monolog\Logger('New logger');
$logger->pushHandler(new Monolog\Handler\StreamHandler('php://stdout'));

require 'secret.php'; //$secret
$options = array(
    //Required
    'secret' => $secret, // Client secret
    'nick' => $nick, // Twitch username
    
    //Optional
    //'discord' => $discord, // Pass your own instance of DiscordPHP (https://github.com/discord-php/DiscordPHP)    
    //'discord_output' => true, // Output Twitch chat to a Discord server's channel
    
    //'loop' => $loop, // Pass your own instance of $loop to share with other ReactPHP applications
    'socket_options' => [
        'dns' => '8.8.8.8', // Can change DNS provider
    ],
    'verbose' => true, // Additional output to console (useful for debugging TwitchPHP)
    'debug' => false, // Additional output to console (useful for debugging communications with Twitch)
    'logger' => $logger,
    
    //Custom commands
    'commandsymbol' => [ // Process commands if a message starts with a prefix in this array
        "@$nick", //Users can mention your channel instead of using a command symbol prefix
		'!s',
    ],
    'whitelist' => [ // Users who are allowed to use restricted functions
        strtolower($nick),
        'shriekingechodanica',
    ],
    'badwords' => [ // List of blacklisted words or phrases in their entirety; User will be immediately banned with reason 'badword' if spoken in chat
        'Buy followers, primes and viewers',
		'bigfollows . com',
		'stearncomminuty',
        'Get viewers, followers and primes on',
    ],
    'responses' => [ // Whenever a message is sent matching a key and prefixed with a command symbol, reply with the defined value
        'ping' => 'Pong!',
        'github' => 'https://github.com/VZGCoders/TwitchPHP',
        'discord' => 'https://discord.gg/NU4BS5P36g',
    ],
    'functions' => [ // Enabled functions usable by anyone
        'help', // Send a list of commands as a chat message
    ],
    'restricted_functions' => [ // Enabled functions usable only by whitelisted users
        'join', //Joins another user's channel
        'leave', //Leave the current user's channel
        'ban', // Ban someone from the channel, takes a username and an optional reason
    ],
    'private_functions' => [ // Enabled functions usable only by the bot owner sharing the same username as the bot
        'stop', //Kills the bot
        'php', //Outputs the current version of PHP as a message
    ],
);

//include 'commands.php';
//$options['commands'] => $commands; // Import your own Twitch/Commands object to add additional functions

//Twitch channels to join that do not need to be relayed to Discord, formatted ['channels']['twitch_username'][''] = ''
$twitch_options['channels']['shriekingechodanica'][''] = '';

//Twitch channels to join and relay chat for, formatted ['channels']['twitch_username']['discord_guild_id'] = 'discord_channel_id'
$twitch_options['channels']['shriekingechodanica']['923969098185068594'] = '924019611534503996';

$twitch = new Twitch\Twitch($options);
$twitch->run();
?>
```

### DiscordPHP Example
Inside of `$discord->on('message'` function with `use ($twitch, $twitch_relay)`
```php
$message_content = $message->content;
$message_content_lower = strtolower($message->content);

if ($message->user_id != $discord->id) $twitch_relay($message, $message_content, $message_content_lower);

if (str_starts_with($message_content_lower, 'join #')) return $twitch->joinChannel(trim(str_replace('join #', '#', $message_content_lower)), $message->guild_id, $message->channel_id);
if (str_starts_with($message_content_lower, 'leave #')) return $twitch->leaveChannel(trim(str_replace('leave #', '#', $message_content_lower)), $message->guild_id, $message->channel_id);
```
```php
$twitch_relay = function ($message, string $message_content, string $message_content_lower) use ($discord, $twitch): void
{
    if ($channels = $twitch->getChannels()) foreach ($channels as $twitch_channel => $arr) foreach ($arr as $guild_id => $channel_id) {
        if (!($message->guild_id == $guild_id && $message->channel_id == $channel_id)) continue;
        $channel = '';
        if (str_starts_with($message_content_lower, "#$twitch_channel")) {
            $message_content = trim(substr($message_content, strlen("#$twitch_channel")));
            $channel = $twitch_channel;
        }
        //else $channel = $twitch->getLastChannel(); //Only works reliably if only relaying chat for a single Twitch chat
        if (! $channel) continue;
        if (! $twitch->sendMessage("{$message->author->displayname} => $message_content", $channel)) $twitch->logger->warning('[FAILED TO SEND MESSAGE TO TWITCH]');
    }
};
```

## Documentation

Raw documentation can be found in-line in the code.
