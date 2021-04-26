<?php
$secret = 'oauth:xxx'; /* Get your Chat OAuth Password here => https://twitchapps.com/tmi/ */

$bot_id = ''; // Obtained from https://dev.twitch.tv/console/apps
$bot_secret = ''; // Obtained from https://dev.twitch.tv/console/apps
$bot_token = ''; // Obtained from your own server using twitch_oauth.php

$refresh_token = ''; // Obtained from your own server using twitch_oauth.php
$expires_in = ''; // Obtained from your own server using twitch_oauth.php

$scope = ["user:edit","user:read:email","viewing_activity_read"];
$token_type = "bearer"; //DO NOT CHANGE!
?>
