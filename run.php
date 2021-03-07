<?php
require 'vendor/autoload.php';
require 'twitch.php';

//$loop = React\EventLoop\Factory::create();

$responses = array( //Reply with value if message starts with $symbol.$responses[$key]
	"ping" => "Pong!",
);

include 'secret.php'; //$secret
$options = array(
	'socket_options' => [
        'dns' => '8.8.8.8', // can change dns
	],
	'secret' => $secret,
	'nick' => 'ValZarGaming', //Twitch username
	'channel' => 'valzargaming', //Channel to join
	'commandsymbol' => [ //Process commands if a message starts with a prefix in this array
		'!',
		';',
	],
	//'loop' => $loop, //Optionally pass your own instance of $loop to share with other ReactPHP applications
	'responses' => $responses, // key=>value array()
	'functions' => null, //NYI
);
$twitch = new Twitch($options);

$twitch->run();
?>