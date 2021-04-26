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
	
	private $twitch;
	private $browser;
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
		$this->browser = $browser;
		
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
	
	protected function doCurl(string $url, string $method = 'GET', array $headers = [], array $post_fields = [], string $user_agent = '', string $referrer = '', bool $follow = true, bool $use_ssl = false, int $con_timeout = 10, int $timeout = 40)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
		curl_setopt($curl, CURLOPT_REFERER, $referrer);
		if ($method == 'POST') {
			curl_setopt($curl, CURLOPT_POST, true);
			if (!empty($post_fields)) curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
		}
		if (!empty($headers)) curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $follow);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $con_timeout);
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $use_ssl);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $use_ssl);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate'); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		$responseInfo = curl_getinfo($curl);
		curl_close($curl);
		//return $response;//Return data
		return $this->handleAPIError($response, $responseInfo);
	}
	
	protected function handleAPIError(string|bool $response, $responseInfo, bool $fatal = false, string $funcName = '', string $class = 'this', $param = null)
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
			case 400:
				if ($response->message == "Invalid refresh token"){
					$this->twitch->emit('[FATAL] [HELIX] Bad request to ' . $url . ': ' . json_decode($response)->message);
					$this->twitch->unsetHelix();
					break;
				}
				if ($this->verbose) $this->twitch->emit('[HELIX] Bad request to ' . $url . ': ' . json_decode($response)->message);
				break;
			case 401:
				if($fatal){
					$this->twitch->emit('[FATAL] [HELIX] Unauthorized request to ' . $url . ': ' . json_decode($response)->message);
					$this->twitch->unsetHelix();
				}
				if($this->refreshToken(true) && $funcName) $$class->$funcName($param);
				break;
			case 404;
				$data = null;
				break;
			default:
				$this->twitch->emit('[FATAL] [HELIX] Connect to API failed with response: ' . $responseInfo['http_code']);
				$this->twitch->unsetHelix();
				break;
		}
		$this->data = $data;
		return $data;
	}
	
	public function getAuthorizationArray(): array
	{
		return array(
			'Authorization: Bearer ' . $this->bot_token,
			'Client-Id: ' . $this->bot_id,
		);
	}
	
	public function getAssocAuthorizationArray(): array
	{
		return array(
			'Authorization' => 'Bearer ' . $this->bot_token,
			'Client-Id' => $this->bot_id,
		);
	}
	
	public function refreshToken(bool $restore = false): bool
	{
		if ($this->verbose) $this->twitch->emit('[REFRESH TOKEN]');
		if (!$restore) $refresh_token = VarLoad('temp', 'refresh_token.php');
		else $refresh_token = $this->refresh_token ?? VarLoad('temp', 'refresh_token.php');
		
		if ($refresh_token){
			$expires_time = $this->expires_time ?? VarLoad('temp', 'expires_time.php') ?? time()+60;
			if (time() > $expires_time){ //expires_time has already passed and the token has expired, so prompt user to request and set a new token
				$this->twitch->unsetHelix();
				$twitch = $this->twitch;
				$this->twitch->getLoop()->addTimer(0, //This can be called during start-up so we want to make sure it gets seen
					function() use ($twitch) {
						$twitch->emit('[FATAL] [HELIX] [EXPIRES_TIME] New token required!');
					}
				);
				return false;
			}
			
			/*
			* This library's cURL calls should be fully asnyc using React Browser,
			* however token refreshing is time-critical and should be done immediately
			*/
			$url = "https://id.twitch.tv/oauth2/token?grant_type=refresh_token&refresh_token={$this->refresh_token}&client_id={$this->bot_id}&client_secret={$this->bot_secret}";
			$response = $this->doCurl($url, 'POST');
			if (is_null($response)){//Something has gone wrong with either Twitch's Helix API or the bot's connection to it
				$this->twitch->unsetHelix();
				$twitch = $this->twitch;
				$this->twitch->getLoop()->addTimer(5, //This can be called during start-up so we want to make sure it gets seen
					function() use ($twitch) {
						$twitch->emit('[FATAL] [HELIX] [REFRESH TOKEN] New token required!');
					}
				);
				return false;
			}
			$data = json_decode($response);
			if(isset($data->refresh_token)){
				$this->refresh_token = $data->refresh_token;
				VarSave('temp', 'refresh_token.php', $data->refresh_token);
				
				if(isset($data->access_token)){
					$this->bot_token = $data->access_token;
					VarSave('temp', 'access_token.php', $data->access_token);
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
				$this->twitch->emit('[HELIX] [REFRESH TOKEN] [SUCCESS]');
				return true;
			}
			$this->twitch->emit('[HELIX] [REFRESH TOKEN] [FAILED]');
			return false;
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
		$this->twitch->emit('[HELIX] [BROWSER] [START]');
		$url = 'https://api.twitch.tv/helix/streams';
		$helix = $this;
		$browser = $this->browser;
		$this->twitch->getLoop()->addTimer(7, //This can be called during start-up so we want to make sure it gets seen
			function() use ($helix, $browser, $url) {
				$helix->twitch->emit('[HELIX] [BROWSER] [START]');
				$assoc_authorize_array = $helix->getAssocAuthorizationArray();
				$helix->twitch->emit('[URL] ' . $url);
				foreach ($assoc_authorize_array as $key => $value) $helix->twitch->emit('[AUTHORIZE ARRAY] ' . $key . ' => ' . $value); //Debug output
				$browser->get($url, $assoc_authorize_array)->then(
					function (\Psr\Http\Message\ResponseInterface $response) use ($helix) {
						
						/*
						$helix->twitch->emit('[HELIX] [BROWSER] [RESPONSE]');
						$export = var_export($response, true);
						ob_flush();
						ob_start();
						var_dump($error);
						file_put_contents("helix_browser_response.txt", ob_get_flush());
						*/
						if ($response->getStatusCode() == 200){
							//$data = json_decode($response->getBody());
							//$export = var_export($data, true);
							//$helix->twitch->emit('[BROWSER]' . $export);
							$helix->twitch->emit('[HELIX] [BROWSER] [BODY]' . $response->getBody());
						} else $helix->twitch->emit('[HELIX] [BROWSER] [RESPONSE?]');
					},
					function (\Exception $error) use ($helix) {
						$export = var_export($error, true);
						file_put_contents("helix_browser_error.txt", $export);
						$helix->twitch->emit('[HELIX] [BROWSER] [ERROR] [LOG] browser_error.txt');
						$helix->twitch->emit('[GET CLASS] ' . get_class($error));
						if ($error instanceof \React\Http\Message\ResponseException){
							$response = $error->getResponse();
							var_dump($response->getStatusCode(), $response->getReasonPhrase());
							if( ($response->getStatusCode() == 401) && ($response->getReasonPhrase() == 'Unauthorized') ) {
								$helix->twitch->emit('[HELIX] [BROWSER] [REFRESH TOKEN]');
								$helix->refreshToken(true);
							}
						}
						//$helix->refreshToken(true);
					}
				)->done();
			}
		);
		
		/*
		echo '[CURL START]' . PHP_EOL;
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
			$this->refreshToken(true);
		}
		*/
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
		if (!file_exists($path)) {
			mkdir($path, 0777, true);
			$this->twitch->emit('[HELIX] [NEW DIR] $path');
		}
		$serialized_variable = serialize($variable);
		file_put_contents($path.$filename, $serialized_variable);
	}

	protected function VarLoad($foldername, $filename)
	{
		if (!is_null($foldername)) $folder_symbol = "/";
		else $folder_symbol = "";;
		$path = getcwd().$foldername.$folder_symbol; 
		if (!file_exists($path.$filename)) return null;
		$loadedvar = file_get_contents($path.$filename);
		$unserialized = unserialize($loadedvar);
		return $unserialized;
	}

	protected function VarDelete($foldername, $filename): bool
	{
		if (!is_null($foldername)) $folder_symbol = "/";
		else $folder_symbol = "";
		$path = getcwd().$foldername.$folder_symbol.$filename;
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
