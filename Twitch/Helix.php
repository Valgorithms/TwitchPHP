<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 ValZarGaming <valzargaming@gmail.com>
 */

namespace Twitch;

/**
 * Provides an easy way to have triggerable commands.
 */
 
class HelixCommandClient
{
	protected $twitch;
	protected $verbose;
	
	protected $bot_secret;
	protected $bot_id;
	protected $bot_token;
	
	function __construct(Twitch $twitch, string $nick, $bot_id, $bot_secret, $bot_token, bool $verbose = false)
	{
		$this->twitch = $twitch;
		$this->nick = $nick;
		$this->verbose = $verbose;
		
		$this->bot_id = $bot_id; // Obtained from https://dev.twitch.tv/console/apps
		$this->bot_secret = $bot_secret; // Obtained from https://dev.twitch.tv/console/apps
		$this->bot_token = $bot_token; // Obtained from your own server using twitch_oauth.php
		$this->test();
		//die();
	}
	
	function test()
	{
		$authorize_array = array(
			'Authorization: Bearer ' . $this->bot_token,
			'Client-Id: ' . $this->bot_id,
		);
		
		$url = 'https://api.twitch.tv/helix/streams';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, 
			$authorize_array
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		if ($info['http_code'] == 200) {
			$data = json_decode($data);
			var_dump($data);
		} else {
			echo '[HELIX] Failed with data:';
			var_dump($info);
		}
	}
	/*
	function authorizeTest()
	{
		
		$authorize_array = array(
			'response_type' = 'code',
			'client_id' = $this->bot_id,
			'redirect_uri' = 'https://www.valzargaming.com/twitch_oauth.php',
			'scope' = ['authorization_code'],
		);
		if ($bot_token) $authorize_array['state'] = $bot_token;
		
		
		$testArray = array(
			'client_secret' = $this->bot_secret,
			'grant_type' = 'client_credentials',
		);
		
		$url = 'https://id.twitch.tv/oauth2/authorize';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 
			http_build_query(
				$authorize_array
			)
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		if ($info['http_code'] == 200) {
			$data = json_decode($data);
			var_dump($data);
		} else {
			echo '[HELIX] Failed with data:';
			var_dump($info);
		}
	}
	*/
}
?>
