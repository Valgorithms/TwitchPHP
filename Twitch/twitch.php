<?php

/*
 * This file is apart of the TwitchPHP project.
 *
 * Copyright (c) 2021 ValZarGaming <valzargaming@gmail.com>
 */


namespace Twitch;

use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

class Twitch
{
	protected $loop;
	private $verbose;
	private $socket_options;
	
    private $secret;
    private $nick;
	private $channel;
	private $commandsymbol;
	
	private $responses;
	private $functions;
	private $restricted_functions;
	private $private_functions;
	
	protected $connector;
	protected $connection;
	protected $running;	
	protected $closing;
	
	protected $lastuser;
	protected $lastmessage;
	

    function __construct(array $options = [])
	{
		if (php_sapi_name() !== 'cli') {
            trigger_error('TwitchPHP will not run on a webserver. Please use PHP CLI to run a TwitchPHP self-bot.', E_USER_ERROR);
        }
		if ($this->verboose) echo '[CONSTRUCT]' . PHP_EOL;
		
		$options = $this->resolveOptions($options);
		
		$this->loop = $options['loop'];
		$this->secret = $options['secret'];
        $this->nick = $options['nick'];
		$this->channel = strtolower($options['channel']) ?? strtolower($options['nick']);
		$this->commandsymbol = $options['commandsymbol'] ?? array('!');
        $this->responses = $options['responses'] ?? array();
        $this->functions = $options['functions'] ?? array();
		$this->private_functions = $options['private_functions'] ?? array();
		$this->verbose = $options['verbose'];
		$this->socket_options = $options['socket_options'];
		
		$this->connector = new Connector($this->loop, $options['socket_options']);
    }
	
	public function run(): void
	{
		if ($this->verboose) echo '[RUN]' . PHP_EOL;
		if (!$this->running) {
			$this->running = true;
			$this->connect();
		}
		if ($this->verboose) echo '[LOOP->RUN]' . PHP_EOL;
		$this->loop->run();
		return;
	}
	
	public function close(bool $closeLoop = true): void
    {
		if ($this->verboose) echo '[CLOSE]' . PHP_EOL;
        if ($closeLoop) {
			$this->closing = true;
			if ($this->verboose) echo '[LOOP->STOP]' . PHP_EOL;
            $this->loop->stop();
        }
		return;
    }
	
	public function sendMessage(string $data, ConnectionInterface $connection)
	{
        $connection->write("PRIVMSG #" . $this->channel . " :" . $data . "\n");
		echo "[REPLY] $data";
    }
	
	public function joinChannel(string $string)
	{
		$connection->write("JOIN #" . strtolower($string) . "\n");
		if ($this->verbose) echo '[VERBOSE] [JOINCHANNEL] `' . strtolower($string) . '`' . PHP_EOL;
	}
	
	protected function resolveOptions(array $options = []): array
	{
		if (!$options['secret']) {
            trigger_error('TwitchPHP requires a client secret to connect. Get your Chat OAuth Password here => https://twitchapps.com/tmi/', E_USER_ERROR);
        }
		if (!$options['nick']) {
            trigger_error('TwitchPHP requires a client username to connect. This should be the same username you use to log in.', E_USER_ERROR);
        }
		
		$options['loop'] = $options['loop'] ?? Factory::create();
		$options['symbol'] = $options['symbol'] ?? '!';
        $options['responses'] = $options['responses'] ?? array();
        $options['functions'] = $options['functions'] ?? array();
		
		return $options;
	}
	
	protected function connect()
	{
		$url = 'irc.chat.twitch.tv';
		$port = '6667';
		if ($this->verbose) echo "[CONNECT] $url:$port" . PHP_EOL;
		
		$twitch = $this;
		$this->connector->connect("$url:$port")->then(
			function (ConnectionInterface $connection) use ($twitch) {
				$twitch->connection = $connection;
				$twitch->initIRC($twitch->connection);
				
				$connection->on('data', function($data) use ($connection, $twitch) {
					$twitch->process($data, $twitch->connection);
				});
			},
			function (Exception $exception) {
				echo $exception->getMessage() . PHP_EOL;
			}
		);
	}
	protected function initIRC(ConnectionInterface $connection)
	{
        $connection->write("PASS " . $this->secret . "\n");
        $connection->write("NICK " . $this->nick . "\n");
        $connection->write("CAP REQ :twitch.tv/membership\n");
        $connection->write("JOIN #" . $this->channel . "\n");
		if ($this->verboose) echo '[INIT IRC]' . PHP_EOL;
    }

    protected function pingPong(string $data, ConnectionInterface $connection)
	{
       // echo "[" . date('h:i:s') . "] PING :tmi.twitch.tv\n";
        $connection->write("PONG :tmi.twitch.tv\n");
       // echo "[" . date('h:i:s') . "] PONG :tmi.twitch.tv\n";
    }
	
	protected function process(string $data, ConnectionInterface $connection)
	{
		//if ($this->verbose) echo "[VERBOSE] [DATA] " . $data . PHP_EOL;
        if (trim($data) == "PING :tmi.twitch.tv") {
            $this->pingPong($data, $connection);
            return;
        }
        if (preg_match('/PRIVMSG/', $data)) {
            $response = $this->parseMessage($data);
            if ($response) {                
                $payload = '@' . $this->lastuser . ', ' . $response . "\n";
                $this->sendMessage($payload, $connection);
            }
        }
    }

    protected function parseMessage(string $data)
	{
        $messageContents = str_replace(PHP_EOL, "", preg_replace('/.* PRIVMSG.*:/', '', $data));
		echo "[PRIVMSG CONTENT] $messageContents" . PHP_EOL;
        $dataArr = explode(' ', $messageContents);
		
		$commandsymbol = '';
		foreach($this->commandsymbol as $temp) {
			if (in_array(substr($messageContents, 0, strlen($temp)), $this->commandsymbol)) {
				$valid = true;
				$commandsymbol = $temp;
				break 1;
			}
		}
		if ($commandsymbol) {
			$command = strtolower(trim(substr($dataArr[0], strlen($commandsymbol))));
			if ($this->verbose) echo "[COMMAND] `$command`" . PHP_EOL; 
			$this->lastuser = $this->parseUser($data);
			
			//Public commands
			if (isset($this->functions[$command])) {
				if ($this->verbose) echo '[FUNCTION]';
				//Do a function then check for a return
			}
			
			//Whitelisted commands
			if (isset($this->restricted_functions[$command])) {
				if ($this->verbose) echo '[RESTRICTED FUNCTION]';
				//Do a function then check for a return
			}
			
			//Bot owner commands (shares the same username)
			if ($this->lastuser == strtolower($this->nick)) {
				if (in_array($command, $this->private_functions)) {
					if ($this->verbose) echo '[PRIVATE FUNCTION]';
					
					if ($command == 'php') {
						if ($this->verbose) echo '[PHP]' . PHP_EOL;
						$response = 'Current PHP version: ' . phpversion();
					}
					
					if ($command == 'stop') {
						if ($this->verbose) echo '[STOP]' . PHP_EOL;
						$this->close();
					}
					
				}
			}
			
			//Reply with a preset message
			if (isset($this->responses[$command])) {
				$response = $this->responses[$command];
			}
			
			return $response;
		}
		return;
    }
	
	protected function parseUser(string $data)
	{
        if (substr($data, 0, 1) == ":") {
            $tmp = explode('!', $data);
			$user = substr($tmp[0], 1);
            return $user;
        }
    }
}