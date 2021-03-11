<?php
/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 ValZarGaming <valzargaming@gmail.com>
 */
 
require 'vendor/autoload.php';
require 'Twitch/Twitch.php';

//$loop = React\EventLoop\Factory::create();
require 'secret.php'; //$secret
$options = array(
	//Required
	'secret' => $secret, // Client secret
	'nick' => 'ValZarGaming', // Twitch username
	'channel' => [
		'daathren', // Channel to join
		'valzargaming', // (Optional) Additional channels
	],
	
	//Optional
	//'discord' => $discord, // Pass your own instance of DiscordPHP (https://github.com/discord-php/DiscordPHP)	
	//'discord_output' => true, // Output Twitch chat to a Discord server's channel
	//'guild_id' => '116927365652807686', //ID of the server
	//'channel_id' => '431415282129698866', //ID of the channel
	
	//'loop' => $loop, // Pass your own instance of $loop to share with other ReactPHP applications
	'socket_options' => [
        'dns' => '8.8.8.8', // Can change DNS provider
	],
	'verbose' => true, // Additional output to console (useful for debugging TwitchPHP)
	'debug' => false, // Additional output to console (useful for debugging communications with Twitch)
	
	//Custom commands
	'commandsymbol' => [ // Process commands if a message starts with a prefix in this array
		'!',
		';',
	],
	'whitelist' => [ // Users who are allowed to use restricted functions
		'valzargaming',
		'daathren',
	],
	'responses' => [ // Whenever a message is sent matching a key and prefixed with a command symbol, reply with the defined value
		'ping' => 'Pong!',
		'github' => 'https://github.com/VZGCoders/TwitchPHP',
		'discord' => 'https://discord.gg/yXJVTQNh9e',
		'lurk' => 'You have said the magick word to make yourself invisible to all eyes upon you, allowing you to fade into the shadows.',
		'social' => 'Come follow the magick through several dimensions:  Twitter - https://twitter.com/daathren |  Instagram - https://www.instagram.com/daathren/ |  Discord - https://discord.gg/FhzXf8VK |  Tumblr - https://daathren.tumblr.com/',
		'tip' => 'Wanna help fund the magick?  PayPal - https://www.paypal.com/paypalme/daathren |  CashApp - $DAAthren',
		'return' => 'You have rolled a Nat 1, clearing your invisibility buff from earlier. You might want to roll for initiative…',
	],
	'functions' => [ // Enabled functions usable by anyone
		'help', // Send a list of commands as a chat message
	],
	'restricted_functions' => [ // Enabled functions usable only by whitelisted users
		'join', //Joins another user's channel
		'leave', //Leave the current user's channel
		'so', //Advertise someone else
	],
	'private_functions' => [ // Enabled functions usable only by the bot owner sharing the same username as the bot
		'stop', //Kills the bot
		'php', //Outputs the current version of PHP as a message
	],
);
//include 'commands.php';
//$options['commands'] => $commands; // Import your own Twitch/Commands object to add additional functions

$twitch = new Twitch\Twitch($options);
$twitch->run();
?>