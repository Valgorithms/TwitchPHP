<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 ValZarGaming <valzargaming@gmail.com>
 * 
 * Features several code snippets from https://github.com/cp6/Twitch-API-class
 */

namespace Twitch;

/**
 * Provides an easy way to have triggerable commands.
 */
 
class HelixCommandClient
{
	const URI = 'https://api.twitch.tv';
	
	protected $twitch;
	protected $verbose;
	
	protected $bot_secret;
	protected $bot_id;
	protected $bot_token;
	
	protected $refresh_token;
	protected $expires_in;
	protected $expires_time;
	protected $data;
	
	function __construct(Twitch $twitch, $browser, string $nick, string $bot_id, string $bot_secret, string $bot_token, string $refresh_token, string $expires_in, bool $verbose = false)
	{
		$this->twitch = $twitch;
		$this->nick = $nick;
		$this->verbose = $verbose;
		
		$this->bot_id = $bot_id; // Obtained from https://dev.twitch.tv/console/apps
		$this->bot_secret = $bot_secret; // Obtained from https://dev.twitch.tv/console/apps
		$this->bot_token = $bot_token; // Obtained from your own server using twitch_oauth.php
		$this->refresh_token = $refresh_token;
		$this->expires_in = $expires_in;
		
		$this->test();
		//die();
	}
	
	protected function doCurl(string $url, string $type = 'GET', array $headers = [], array $post_fields = [], string $user_agent = '', string $referrer = '', bool $follow = true, bool $use_ssl = false, int $con_timeout = 10, int $timeout = 40)
	{
		$crl = curl_init($url);
		curl_setopt($crl, CURLOPT_CUSTOMREQUEST, $type);
		curl_setopt($crl, CURLOPT_USERAGENT, $user_agent);
		curl_setopt($crl, CURLOPT_REFERER, $referrer);
		if ($type == 'POST') {
			curl_setopt($crl, CURLOPT_POST, true);
			if (!empty($post_fields)) {
				curl_setopt($crl, CURLOPT_POSTFIELDS, $post_fields);
			}
		}
		if (!empty($headers)) {
			curl_setopt($crl, CURLOPT_HTTPHEADER, $headers);
		}
		curl_setopt($crl, CURLOPT_FOLLOWLOCATION, $follow);
		curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $con_timeout);
		curl_setopt($crl, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($crl, CURLOPT_SSL_VERIFYHOST, $use_ssl);
		curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, $use_ssl);
		curl_setopt($crl, CURLOPT_ENCODING, 'gzip,deflate'); 
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
		$call_response = curl_exec($crl);
		curl_close($crl);
		return $call_response;//Return data
	}
	
	protected function handleAPIError($response, $responseInfo, bool $fatal = false, string $class = 'this', string $funcName = '', $param = null)
	{
		//Check HTTP status code, refresh token on 401
			//call_user_func(array($this, 'functionName'));
		switch ($responseInfo['http_code']) {
            case 0:
                throw new Exception('Timeout reached when calling ' . $url);
                break;
            case 200:
                $data = $response;
                break;
            case 401:
				if($fatal){
					$this->twitch->emit('[FATAL] [HELIX] Unauthorized request to ' . $url . ': ' . json_decode($response)->message);
					$this->twitch->unsetHelix();
				}
				if($this->refreshToken() && $funcName) $$class->$funcName($param);
                break;
            case 404;
                $data = null;
                break;
            default:
                throw new Exception('Connect to API failed with response: ' . $responseInfo['http_code']);
                break;
        }
        $this->data = $data;
        return $data;
	}
	
	protected function getAuthorizationArray(): array
	{
		return array(
			'Authorization: Bearer ' . $this->bot_token,
			'Client-Id: ' . $this->bot_id,
		);
	}

	public function setApiKey($api_key)
    {
        if (!isset($api_key) || is_null(trim($api_key))) throw new Exception("You must provide an API key");
        $this->apikey = $api_key;
    }
	
	function refreshToken(): bool
	{
		if ($refresh_token = $this->refresh_token ?? VarLoad('temp', 'refresh_token.php')){
			
			$expires_time = $this->expires_time ?? VarLoad('temp', 'expires_time.php') ?? time()+60;
			if (time() > $expires_time){ //expires_time has already passed and the token has expired, so prompt user to request and set a new token
				$this->twitch->unsetHelix();
				$twitch = $this->twitch;
				$this->twitch->getLoop()->addTimer(5, //This can be called during start-up so we want to make sure it gets seen
					function() use ($twitch) {
						$twitch->emit('[FATAL] [HELIX] [EXPIRES_TIME] New token required!');
					}
				);
				return false;
			}
			
			$url = "https://id.twitch.tv/oauth2/token?grant_type=refresh_token&refresh_token={$this->refresh_token}&client_id={$this->bot_id}&client_secret={$this->bot_secret}";
			echo 'doCurl: ' . $url . PHP_EOL;
			$response = $this->doCurl($url, 'POST');
			$data = json_decode($response);
			echo 'data dump: '; var_dump($data); echo PHP_EOL;
			if(isset($data->access_token)){
				echo 'access_token: ' . $data->access_token . PHP_EOL;
				$this->bot_token = $data->access_token;
				VarSave('temp', 'access_token.php', $data->access_token);
			}
			if(isset($data->refresh_token)){
				$this->refresh_token = $data->refresh_token;
				VarSave('temp', 'refresh_token.php', $data->refresh_token);
			}
			if(isset($data->expires_in)){
				$this->expires_in = $data->expires_in;
				VarSave('temp', 'expires_in.php', $data->expires_in);
				$this->expires_time = time()+$data->expires_in;
				VarSave('temp', 'expires_time.php', $this->expires_time);
				$helix = $this;
				$this->twitch->getLoop()->addTimer($data->expires_in-60,
					function() use ($helix) { //We want to refresh the token before it expires again
						$helix->refreshToken();
					}
				);
			}
			return true;
		} else {
			$this->twitch->unsetHelix();
			$twitch = $this->twitch;
			$this->twitch->getLoop()->addTimer(5, //This can be called during start-up so we want to make sure it gets seen
				function() use ($twitch) {
					$twitch->emit('[FATAL] [HELIX] [REFRESH TOKEN] New token required!');
				}
			);
			return false;
		}
	}
	
	function test()
	{
		$authorize_array = $this->getAuthorizationArray();
		
		/*
		//get
		get(string $url, array $headers = array()): PromiseInterface<ResponseInterface>
		
		$twitch = $this->twitch;
		$browser->get($url, $authorize_array)->then(
			function (Psr\Http\Message\ResponseInterface $response) {
				var_dump((string)$response->getBody());
			},
			function (Exception $exception) use ($twitch) {
				$twitch->emit('[ERROR] ' . $exception->getMessage());
			}
		);
		
		//post
		post(string $url, array $headers = array(), string|ReadableStreamInterface $contents = ''): PromiseInterface<ResponseInterface>
		$browser->post(
			$url,
			[
				'Content-Type' => 'application/x-www-form-urlencoded'
			],
			http_build_query($data)
		);
		*/
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
		} elseif($info['http_code'] == 401) {
			$this->refreshToken();
		}
	}
	
	protected function CheckFile($foldername, $filename): bool
	{
		if (!is_null($foldername)) $folder_symbol = "/";
		else $folder_symbol = "";
		$path = getcwd().$foldername.$folder_symbol.$filename;
		return file_exists($path);
	}

	protected function VarSave($foldername, $filename, $variable): void
	{
		if (!is_null($foldername)) $folder_symbol = "/";
		else $folder_symbol = "";
		$path = getcwd().$foldername.$folder_symbol;
		//Create folder if it doesn't already exist
		if (!file_exists($path)) {
			mkdir($path, 0777, true);
			echo "NEW DIR CREATED: $path" . PHP_EOL;
		}
		$serialized_variable = serialize($variable);
		file_put_contents($path.$filename, $serialized_variable);
	}

	protected function VarLoad($foldername, $filename)
	{
		if (!is_null($foldername)) $folder_symbol = "/";
		else $folder_symbol = "";;
		$path = getcwd().$foldername.$folder_symbol; //echo "PATH: $path" . PHP_EOL;
		if (!file_exists($path.$filename)) return null;
		$loadedvar = file_get_contents($path.$filename);
		$unserialized = unserialize($loadedvar);
		return $unserialized;
	}

	protected function VarDelete($foldername, $filename): bool
	{
		if (!is_null($foldername)) $folder_symbol = "/";
		else $folder_symbol = "";
		$path = getcwd().$foldername.$folder_symbol.$filename; //echo "PATH: $path" . PHP_EOL;
		if (CheckFile($foldername, $filename)) {
			unlink($path);
			clearstatcache();
			return true;
		} else return false;
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
