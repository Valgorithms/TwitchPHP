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
	'channels' => [
		'valzargaming', // Channel to join
		'daathren', // (Optional) Additional channels
	],
	
	//Optional
	//'discord' => $discord, // Pass your own instance of DiscordPHP (https://github.com/discord-php/DiscordPHP)	
	//'discord_output' => true, // Output Twitch chat to a Discord server
	//'guild_id' => '116927365652807686', //ID of the Discord server
	//'channel_id' => '431415282129698866', //ID of the Discord channel to output messages to
	
	//'loop' => $loop, // Pass your own instance of $loop to share with other ReactPHP applications
	'socket_options' => [
        'dns' => '8.8.8.8', // Can change DNS provider
	],
	'verbose' => true, // Additional output to console (useful for debugging)
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
	'badwords' => [ // List of blacklisted words or phrases in their entirety; User will be immediately banned with reason 'badword' if spoken in chat
		'Buy followers, primes and viewers',
		'bigfollows . com',
		'stearncomminuty',
	],
	'social' => [ //NYI
		'twitter' => 'https://twitter.com/daathren',
		'instagram' => 'https://www.instagram.com/daathren',
		'discord' => 'https://discord.gg/CpVbC78XWT',
		'tumblr' => 'https://daathren.tumblr.com',
		'youtube' => 'https://www.youtube.com/daathren',
	],
	'tip' => [ //NYI
		'paypal' => 'https://www.paypal.com/paypalme/daathren',
		'cashapp' => '$DAAthren',
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
		'so', //Advertise someone 
		'ban', //Ban someone with or without a reason included after the username
	],
	'private_functions' => [ // Enabled functions usable only by the bot owner sharing the same username as the bot
		'php', //Outputs the current version of PHP as a message
		'join', //Joins another user's channel
		'leave', //Leave the current user's channel
		'stop', //Kills the bot
	],
);
// Responses that reference other values in options should be declared afterwards
$options['responses']['social'] = 'Come follow the magick through several dimensions:  Twitter - '.$options['social']['twitter'].' |  Instagram - '.$options['social']['instagram'].' |  Discord - '.$options['social']['discord'].' |  Tumblr - '.$options['social']['tumblr'].' |  YouTube - '.$options['social']['youtube'];
$options['responses']['tip'] = 'Wanna help fund the magick?  PayPal - '.$options['tip']['paypal'].' |  CashApp - '.$options['tip']['cashapp'];
$options['responses']['discord'] = $options['social']['discord'];

//include 'commands.php';
//$options['commands'] => $commands; // Import your own Twitch/Commands object to add additional functions

$twitch = new Twitch\Twitch($options);
$twitch->run();
?>