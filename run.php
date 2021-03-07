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
	'nick' => 'ValZarGaming',
	'commandsymbol' => array('!', ';'),
	//'loop' => $loop,
	'responses' => $responses, // key=>value array()
	'functions' => null, //NYI
);
$twitch = new Twitch($options);

$twitch->run();
?>