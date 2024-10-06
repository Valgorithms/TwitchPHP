<?php
/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

define('TWITCHBOT_START', microtime(true));
ini_set('display_errors', 1);
error_reporting(E_ALL);

set_time_limit(0);
ignore_user_abort(1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1'); // Unlimited memory usage
define('MAIN_INCLUDED', 1); // Token and SQL credential files may be protected locally and require this to be defined to access

 //if (! $token_included = require getcwd() . '/token.php') // $token
    //throw new \Exception('Token file not found. Create a file named token.php in the root directory with the bot token.');
if (! $autoloader = require file_exists(__DIR__.'/vendor/autoload.php') ? __DIR__.'/vendor/autoload.php' : __DIR__.'/../../autoload.php')
throw new \Exception('Composer autoloader not found. Run `composer install` and try again.');
function loadEnv(string $filePath = __DIR__ . '/.env'): void
{
    if (! file_exists($filePath)) throw new \Exception("The .env file does not exist.");
    putenv('env_path=' . $filePath);

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $trimmedLines = array_map('trim', $lines);
    $filteredLines = array_filter($trimmedLines, fn($line) => $line && ! str_starts_with($line, '#'));

    array_walk($filteredLines, function($line) {
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (! array_key_exists($name, $_ENV)) putenv(sprintf('%s=%s', $name, $value));
    });
}
loadEnv(getcwd() . '/.env');

$loop = \React\EventLoop\Loop::get();
$streamHandler = new StreamHandler('php://stdout', Level::Debug);
$streamHandler->setFormatter(new LineFormatter(null, null, true, true, true));
$logger = new Logger('TwitchPHP', [$streamHandler]);
file_put_contents('output.log', ''); // Clear the contents of 'output.log'
$logger->pushHandler(new StreamHandler('output.log', Level::Debug));
$logger->info('Loading configurations for the bot...');
$options = array(
    //Required
    'secret' => getenv('twitch_secret'), // Client secret
    'nick' => getenv('twitch_nick'), // Twitch username (Case sensitive)
    
    //Optional
    //'discord' => $discord, // Pass your own instance of DiscordPHP (https://github.com/discord-php/DiscordPHP)    
    //'discord_output' => true, // Output Twitch chat to a Discord server
    
    'loop' => $loop, // (Optional) Pass your own instance of $loop to share with other ReactPHP applications
    'socket_options' => [
        //'dns' => '8.8.8.8', // Can change DNS provider
    ],
    'verbose' => true, // Additional output to console (useful for debugging)
    'debug' => true, // Additional output to console (useful for debugging communications with Twitch)
    'logger' => $logger,
    
    //Custom commands
    'symbol' => [ // Process commands if a message starts with a prefix in this array
        "@" . getenv('twitch_nick'), //Users can mention your channel instead of using a command symbol prefix
        '!',
        ';',
    ],
    'whitelist' => [ // Users who are allowed to use restricted functions
        'shriekingechodanica',
    ],
    'social' => [ //NYI
        'discord' => 'https://discord.gg/0duG4FF1ElFGUFVq',
        'twitter' => 'https://twitter.com/valzargaming',
        'x'       => 'https://x.com/valzargaming',
		'youtube' => 'https://www.youtube.com/valzargaming',
    ],
    'tip' => [ //NYI
        'paypal' => 'https://www.paypal.com/paypalme/valithor',
    ],
    'responses' => [ // Whenever a message is sent matching a key and prefixed with a command symbol, reply with the defined value
        'ping' => 'Pong!',
        'github' => 'https://github.com/VZGCoders/TwitchPHP',
        'lurk' => 'You have said the magick word to make yourself invisible to all eyes upon you, allowing you to fade into the shadows.',
        'return' => 'You have rolled a Nat 1, clearing your invisibility buff from earlier. You might want to roll for initiative…',
    ],
    'functions' => [ // Enabled functions usable by anyone
        'help', // Send a list of commands as a chat message
    ],
    'restricted_functions' => [ // Enabled functions usable only by whitelisted users
        'so', //Advertise someone else
    ],
    'private_functions' => [ // Enabled functions usable only by the bot owner sharing the same username as the bot
        'php', //Outputs the current version of PHP as a message
        'join', //Joins another user's channel
        'leave', //Leave the current user's channel
        'stop', //Kills the bot
    ],
);
//Discord servers to relay chat for, formatted ['channels']['twitch_username']['discord_guild_id'] = 'discord_channel_id'
$options['channels'] = [
    strtolower(getenv('twitch_nick')) => [ // Relay chat for the bot's channel
        '923969098185068594' => '924019611534503996',
    ],
    'shriekingechodanica' => [ // Relay chat for another streamer's channel
        '923969098185068594' => '924019611534503996',
        '999053951670423643' => '1014429625826414642',
    ],
];
// Responses that reference other values in options should be declared afterwards
$options['responses'] = array_merge($options['responses'], [
    'social' => 'Come follow the magick through several dimensions:  Twitter - '.$options['social']['twitter'].' |  Discord - '.$options['social']['discord'].' |  YouTube - '.$options['social']['youtube'],
    'tip' => 'Wanna help fund the magick?  PayPal - '.$options['tip']['paypal'],
    'discord' => $options['social']['discord']
]);

//include 'commands.php';
//$options['commands'] => $commands; // Import your own Twitch/Commands object to add additional functions

$twitch = new Twitch($options);
$twitch->run();