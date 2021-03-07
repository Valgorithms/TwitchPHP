<?php

use React\Socket\ConnectionInterface;

$connector->connect('irc.chat.twitch.tv:6667')
->then(
    function (ConnectionInterface $connection) use ($twitch){
        $twitch->initIRC($connection);

        $connection->on('data', function($data) use ($connection, $twitch){
            $twitch->scrape($data, $connection);
        });
    },
    function (Exception $exception){
        echo $exception->getMessage() . PHP_EOL;
    }
);

