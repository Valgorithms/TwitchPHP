<?php
/*
 * This file is apart of the TwitchPHP project.
 *
 * Copyright (c) 2021 ValZarGaming <valzargaming@gmail.com>
 */
 
require 'vendor/autoload.php';
require 'Twitch/Twitch.php';

//print_r(get_declared_classes());

//$loop = React\EventLoop\Factory::create();
include 'secret.php'; //$secret
$options = array(
	//Required
	'secret' => $secret, // Client secret
	'nick' => 'ValZarGaming', // Twitch username
	'channel' => 'valzargaming', // Channel to join
	
	//Optional
	//'loop' => $loop, // (Optional) pass your own instance of $loop to share with other ReactPHP applications
	'socket_options' => [
        'dns' => '8.8.8.8', // Can change DNS provider
	],
	'verbose' => true, // Additional output to console (useful for debugging)_
	
	//Custom commands
	'commandsymbol' => [ // Process commands if a message starts with a prefix in this array
		'!',
		';',
	],
	'whitelist' => [ // Users who are allowed to use whitelisted commands
		'daathren',
	],
	'responses' => [ // Whenever a message is sent matching a key and prefixed with a command symbol, reply with the defined value
		"ping" => "Pong!",
	],
	'functions' => [ // Enabled functions usable by anyone
		//
	],
	'restricted_functions' => [ // Enabled functions usable only by whitelisted users
		'php', //Outputs the current version of PHP as a message
	],
	'private_functions' => [ // Enabled functions usable only by the bot owner sharing the same username as the bot
		'stop', //Kills the bot
	],
);
$twitch = new Twitch\Twitch($options);

$twitch->run();
?>