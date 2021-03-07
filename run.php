<?php
require 'vendor/autoload.php';
require 'twitchfunctions.php';

$loop = React\EventLoop\Factory::create();


$responses = array( //Reply with value if message starts with $symbol.$responses[$key]
	"ping" => "Pong!",
);

include 'secret.php'; //$secret
$nick = 'ValZarGaming'; /* Your Twitch username */
$symbol = ';'; //Command prefix

$options = array(
	'socket_options' => [
        'dns' => '8.8.8.8', // can change dns
	],
	'secret' => $secret,
	'nick' => 'ValZarGaming',
	'commandsymbol' => ';',
	'loop' => $loop,
	'responses' => $responses, // key=>value array()
	'functions' => null, //NYI
);
$twitch = new Twitch($options);


//include 'twitchclient.php'; //Load dependencies and connect the $connector to twitch; uses $twitch

//$loop->run();
$twitch->run();
$loop->run();
?>