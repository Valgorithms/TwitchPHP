<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021 ValZarGaming <valzargaming@gmail.com>
 */


namespace Twitch;

use Twitch\Commands;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

class Twitch
{
	protected $loop;
	protected $commands;
	
	private $discord;
	private $discord_relay;
	private $guild_id;
	private $channel_id;
	
	private $verbose;
	private $socket_options;
	
    private $secret;
    private $nick;
	private $channel;
	private $commandsymbol;
	
	private $whitelist;
	private $responses;
	private $functions;
	private $restricted_functions;
	private $private_functions;
	
	protected $connector;
	protected $connection;
	protected $running;	
	protected $closing;
	
	private $lastuser;
	private $lastmessage;

    function __construct(array $options = [])
	{
		if (php_sapi_name() !== 'cli') {
            trigger_error('TwitchPHP will not run on a webserver. Please use PHP CLI to run a TwitchPHP self-bot.', E_USER_ERROR);
        }
		if ($this->verbose) $this->emit('[CONSTRUCT]');
		
		$options = $this->resolveOptions($options);
		
		$this->loop = $options['loop'];
		$this->secret = $options['secret'];
        $this->nick = $options['nick'];
		$this->channel = strtolower($options['channel']) ?? strtolower($options['nick']);
		$this->commandsymbol = $options['commandsymbol'] ?? array('!');
		
		foreach ($options['whitelist'] as $whitelist){
			$this->whitelist[] = $whitelist;
		}
		$this->responses = $options['responses'] ?? array();
        $this->functions = $options['functions'] ?? array();
		$this->restricted_functions	= $options['restricted_functions'] ?? array();
		$this->private_functions = $options['private_functions'] ?? array();
		
		$this->verbose = $options['verbose'];
		$this->socket_options = $options['socket_options'];
		
		$this->discord = $options['discord'];
		$this->discord_output = $options['discord_output'];
		$this->guild_id = $options['guild_id'];
		$this->channel_id = $options['channel_id'];
		
		$this->connector = new Connector($this->loop, $options['socket_options']);
		
		include 'Commands.php';
		$this->commands = $options['commands'] ?? new Commands($this, $this->verbose);
    }
	
	public function run(): void
	{
		if ($this->verboose) $this->emit('[RUN]');
		if (!$this->running) {
			$this->running = true;
			$this->connect();
		}
		if ($this->verboose) $this->emit('[LOOP->RUN]');
		$this->loop->run();
		return;
	}
	
	public function close(bool $closeLoop = true): void
    {
		if ($this->verboose) $this->emit('[CLOSE]');
        if ($closeLoop) {
			$this->closing = true;
			if ($this->verboose) $this->emit('[LOOP->STOP]');
            $this->loop->stop();
        }
    }
	
	public function sendMessage(string $data, ConnectionInterface $connection): void
	{
        $connection->write("PRIVMSG #" . $this->channel . " :" . $data . "\n");
		$this->emit("[REPLY] $data");
    }
	
	public function joinChannel(string $string): void
	{
		$connection->write("JOIN #" . strtolower($string) . "\n");
		if ($this->verbose) $this->emit('[VERBOSE] [JOINCHANNEL] `' . strtolower($string) . '`');
	}
	
	protected function resolveOptions(array $options = []): array
	{
		if (!$options['secret']) {
            trigger_error('TwitchPHP requires a client secret to connect. Get your Chat OAuth Password here => https://twitchapps.com/tmi/', E_USER_ERROR);
        }
		if (!$options['nick']) {
            trigger_error('TwitchPHP requires a client username to connect. This should be the same username you use to log in.', E_USER_ERROR);
        }
		$options['nick'] = strtolower($options['nick']);
		$options['loop'] = $options['loop'] ?? Factory::create();
		$options['symbol'] = $options['symbol'] ?? '!';
        $options['responses'] = $options['responses'] ?? array();
        $options['functions'] = $options['functions'] ?? array();
		
		return $options;
	}
	
	protected function connect(): void
	{
		$url = 'irc.chat.twitch.tv';
		$port = '6667';
		if ($this->verbose) $this->emit("[CONNECT] $url:$port");
		
		$twitch = $this;
		$this->connector->connect("$url:$port")->then(
			function (ConnectionInterface $connection) use ($twitch) {
				$twitch->connection = $connection;
				$twitch->initIRC($twitch->connection);
				
				$connection->on('data', function($data) use ($connection, $twitch) {
					$twitch->process($data, $twitch->connection);
				});
				$twitch->emit('[CONNECTED]');
			},
			function (Exception $exception) {
				 $twitch->emit('[ERROR] ' . $exception->getMessage());
			}
		);
	}
	protected function initIRC(ConnectionInterface $connection): void
	{
        $connection->write("PASS " . $this->secret . "\n");
        $connection->write("NICK " . $this->nick . "\n");
        $connection->write("CAP REQ :twitch.tv/membership\n");
        $connection->write("JOIN #" . $this->channel . "\n");
		if ($this->verboose) $this->emit('[INIT IRC]');
    }

    protected function pingPong(string $data, ConnectionInterface $connection): void
	{
       // $this->emit("[" . date('h:i:s') . "] PING :tmi.twitch.tv");
        $connection->write("PONG :tmi.twitch.tv\n");
       // $this->emit("[" . date('h:i:s') . "] PONG :tmi.twitch.tv");
    }
	
	protected function process(string $data, ConnectionInterface $connection): void
	{
		//if ($this->verbose) $this->emit("[VERBOSE] [DATA] " . $data);
        if (trim($data) == "PING :tmi.twitch.tv") {
            $this->pingPong($data, $connection);
            return;
        }
        if (preg_match('/PRIVMSG/', $data)) {
            $response = $this->parseMessage($data);
            if ($response) {                
                $payload = '@' . $this->lastuser . ', ' . $response . "\n";
                $this->sendMessage($payload, $connection);
				if ($this->discord_output){
					if(
						($guild = $discord->guilds->offsetGet($this->guild_id))
						&&
						($channel = $guild->channels->offsetGet($channel_id))
					)
					$channel->sendMessage($payload);
				}
            }
        }
    }

    protected function parseMessage(string $data): ?string
	{
        $messageContents = str_replace(PHP_EOL, "", preg_replace('/.* PRIVMSG.*:/', '', $data));
		$this->emit("[PRIVMSG CONTENT] $messageContents");
        $dataArr = explode(' ', $messageContents);
		
		$commandsymbol = '';
		foreach($this->commandsymbol as $symbol) {
			if (in_array(substr($messageContents, 0, strlen($symbol)), $this->commandsymbol)) {
				$valid = true;
				$commandsymbol = $symbol;
				break 1;
			}
		}
		if ($commandsymbol) {
			$command = strtolower(trim(substr($dataArr[0], strlen($commandsymbol))));
			if ($this->verbose) $this->emit("[COMMAND] `$command`"); 
			$this->lastuser = $this->parseUser($data);
			
			//Public commands
			if (in_array($command, $this->functions)) {
				if ($this->verbose) $this->emit('[FUNCTION]');
				$response = $this->commands->handle($command);
			}
			
			//Whitelisted commands
			if ( in_array($this->lastuser, $this->whitelist) || ($this->lastuser == $this->nick) ) {
				if (in_array($command, $this->restricted_functions)) {
					if ($this->verbose) $this->emit('[RESTRICTED FUNCTION]');
					$response = $this->commands->handle($command);
				}
			}
			
			//Bot owner commands (shares the same username)
			if ($this->lastuser == $this->nick) {
				if (in_array($command, $this->private_functions)) {
					if ($this->verbose) $this->emit('[PRIVATE FUNCTION]');
					$response = $this->commands->handle($command);
				}
			}
			
			//Reply with a preset message
			if (isset($this->responses[$command])) {
				$response = $this->responses[$command];
			}
			
		}
		return $response;
    }
	
	protected function parseUser(string $data): ?string
	{
        if (substr($data, 0, 1) == ":") {
            $tmp = explode('!', $data);
			$user = substr($tmp[0], 1);
            return $user;
        }
    }
	
	public function emit(string $string): void
	{
        echo "[EMIT] $string" . PHP_EOL;
    }
	
	public function getCommandSymbol(): array
	{
		return $this->commandsymbol;
	}
	
	public function getResponses(): array
	{
		return $this->responses;
	}
	
	public function getFunctions(): array
	{
		return $this->functions;
	}
	
	public function getRestrictedFunctions(): array
	{
		return $this->restricted_functions;
	}
	
	public function getPrivateFunctions(): array
	{
		return $this->private_functions;
	}
	
	public function linkDiscord($discord): void
	{
		$this->discord = $discord;
	}
}