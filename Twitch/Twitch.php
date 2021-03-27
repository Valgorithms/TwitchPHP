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
	private $discord_output;
	private $guild_id;
	private $channel_id;
	
	private $verbose;
	private $socket_options;
	private $debug;
	
    private $secret;
    private $nick;
	private $channels;
	private $commandsymbol;
	
	private $whitelist;
	private $responses;
	private $functions;
	private $restricted_functions;
	private $private_functions;
	
	protected $connector;
	protected $connection;
	protected $running;
	
	private $reallastuser;
	private $reallastchannel;
	private $lastmessage;
	private $lastuser; //Used a command
	private $lastchannel; //Where command was used

    function __construct(array $options = [])
	{
		if (php_sapi_name() !== 'cli') trigger_error('TwitchPHP will not run on a webserver. Please use PHP CLI to run a TwitchPHP self-bot.', E_USER_ERROR);
		if ($this->verbose) $this->emit('[CONSTRUCT]');
		
		$options = $this->resolveOptions($options);
		
		$this->loop = $options['loop'];
		$this->secret = $options['secret'];
        $this->nick = $options['nick'];
		foreach($options['channels'] as $channel) $this->channels[] = strtolower($channel);
		if(is_null($this->channels)) $this->channels = array($options['nick']);
		$this->commandsymbol = $options['commandsymbol'] ?? array('!');
		
		foreach ($options['whitelist'] as $whitelist) $this->whitelist[] = $whitelist;
		$this->responses = $options['responses'] ?? array();
        $this->functions = $options['functions'] ?? array();
		$this->restricted_functions	= $options['restricted_functions'] ?? array();
		$this->private_functions = $options['private_functions'] ?? array();
		
		$this->verbose = $options['verbose'];
		$this->socket_options = $options['socket_options'];
		$this->debug = $options['debug'];
		
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
		if ($this->verbose) $this->emit('[RUN]');
		if (!$this->running) {
			$this->running = true;
			$this->connect();
		}
		if ($this->verbose) $this->emit('[LOOP->RUN]');
		$this->loop->run();
		return;
	}
	
	public function close(bool $closeLoop = true): void
    {
		if ($this->verbose) $this->emit('[CLOSE]');
		if ($this->running) {
			$this->running = false;
			foreach ($this->channels as $channel) $this->leaveChannel($channel);
		}
        if ($closeLoop) {
			if ($this->verbose) $this->emit('[LOOP->STOP]');
            $this->loop->stop();
        }
    }
	
	public function sendMessage(string $data, ?string $channel = null): void
	{
        $this->connection->write("PRIVMSG #" . ($channel ?? $this->reallastchannel ?? current($this->channels)) . " :" . $data . "\n");
		$this->emit('[REPLY] #' . ($channel ?? $this->reallastchannel ?? current($this->channels)) . ' - ' . $data);
		if ($channel) $this->reallastchannel = $channel ?? $this->reallastchannel ?? current($this->channels);
    }
	
	public function joinChannel(string $string = ""): void
	{
		if ($this->verbose) $this->emit('[VERBOSE] [JOIN CHANNEL] `' . $string . '`');		
		$string = strtolower($string);
		$this->connection->write("JOIN #" . $string . "\n");
		if (!in_array($string, $this->channels)) $this->channels[] = $string;
	}
	
	/*
	* Commands.php should never send a string so as to prevent users from being able to tell the bot to leave someone else's channel
	* This command is exposed so other ReactPHP applications can call it, but those applications should always attempt to pass a valid string
	* getChannels has also been exposed for the purpose of checking if the string exists before attempting to call this function
	*/
	public function leaveChannel(?string $string = ""): void
	{
		if ($this->verbose) $this->emit('[VERBOSE] [LEAVE CHANNEL] `' . $string . '`');
		$string = strtolower($string ?? $this->reallastchannel);
		$this->connection->write("PART #" . ($string ?? $this->reallastchannel) . "\n");
		foreach ($this->channels as &$channel){
			if ($channel == $string) $channel = null;
			unset ($channel);
		}
	}
	
	/*
	* Attempt to catch errors with the user-provided $options early
	*/
	protected function resolveOptions(array $options = []): array
	{
		if (!$options['secret']) trigger_error('TwitchPHP requires a client secret to connect. Get your Chat OAuth Password here => https://twitchapps.com/tmi/', E_USER_ERROR);
		if (!$options['nick']) trigger_error('TwitchPHP requires a client username to connect. This should be the same username you use to log in.', E_USER_ERROR);
		$options['nick'] = strtolower($options['nick']);
		$options['loop'] = $options['loop'] ?? Factory::create();
		$options['symbol'] = $options['symbol'] ?? '!';
        $options['responses'] = $options['responses'] ?? array();
        $options['functions'] = $options['functions'] ?? array();
		
		return $options;
	}
	
	/*
	* Connect the bot to Twitch
	* This command should not be run while the bot is still connected to Twitch
	* Additional handling may be needed in the case of disconnect via $connection->on('close' (See: Issue #1 on GitHub)
	*/ 
	protected function connect(): void
	{
		$url = 'irc.chat.twitch.tv';
		$port = '6667';
		if ($this->verbose) $this->emit("[CONNECT] $url:$port");
		
		if(!$this->connection){
			$twitch = $this;
			$this->connector->connect("$url:$port")->then(
				function (ConnectionInterface $connection) use ($twitch) {
					$twitch->connection = $connection;
					$twitch->initIRC($twitch->connection);
					
					$connection->on('data', function($data) use ($connection, $twitch) {
						$twitch->process($data, $twitch->connection);
					});
					$connection->on('close', function () use ($twitch) {
						$twitch->emit('[CLOSE]');
					});
					$twitch->emit('[CONNECTED]');
				},
				function (Exception $exception) {
					$twitch->emit('[ERROR] ' . $exception->getMessage());
				}
			);
		} else $twitch->emit('[SYMANTICS ERROR] A connection already exists!');
	}
	protected function initIRC(ConnectionInterface $connection): void
	{
        $connection->write("PASS " . $this->secret . "\n");
        $connection->write("NICK " . $this->nick . "\n");
        $connection->write("CAP REQ :twitch.tv/membership\n");
		foreach ($this->channels as $channel) $this->joinChannel($channel);
		if ($this->verbose) $this->emit('[INIT IRC]');
    }

    protected function pingPong(string $data, ConnectionInterface $connection): void
	{
		if ($this->debug) $this->emit("[DEBUG] [" . date('h:i:s') . "] PING :tmi.twitch.tv");
		$connection->write("PONG :tmi.twitch.tv\n");
		if ($this->debug) $this->emit("[DEBUG] [" . date('h:i:s') . "] PONG :tmi.twitch.tv");
    }
	
	protected function process(string $data, ConnectionInterface $connection): void
	{
		if ($this->debug) $this->emit("[DEBUG] [DATA] " . $data);
        if (trim($data) == "PING :tmi.twitch.tv") {
            $this->pingPong($data, $connection);
            return;
        }
        if (preg_match('/PRIVMSG/', $data)) {
            $response = $this->parseMessage($data);
            if ($response) {                
                $payload = '@' . $this->lastuser . ', ' . $response . "\n";
                $this->sendMessage($payload);
				$this->discordRelay('[REPLY] #' . $this->reallastchannel . ' - ' . $payload);
            }
        }
    }

    protected function parseMessage(string $data): ?string
	{
        $messageContents = str_replace(PHP_EOL, "", preg_replace('/.* PRIVMSG.*:/', '', $data));
		if ($this->verbose) $this->emit("[PRIVMSG CONTENT] $messageContents");
        $dataArr = explode(' ', $messageContents);
		
		/* Output to Discord */
		$this->lastmessage = $messageContents;
		$this->reallastuser = $this->parseUser($data);
		$this->reallastchannel = $this->parseChannel($data);
		$this->discordRelay('[MSG] #' . $this->reallastchannel . ' - ' . $this->reallastuser . ': ' . $messageContents);
		
		$response = '';
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
			$this->lastuser = $this->reallastuser;
			$this->lastchannel = $this->reallastchannel;
			$this->lastchannel = null;
			
			//Public commands
			if (in_array($command, $this->functions)) {
				if ($this->verbose) $this->emit('[FUNCTION]');
				$response = $this->commands->handle($command, $dataArr);
			}
			
			//Whitelisted commands
			if ( in_array($this->lastuser, $this->whitelist) || ($this->lastuser == $this->nick) ) {
				if (in_array($command, $this->restricted_functions)) {
					if ($this->verbose) $this->emit('[RESTRICTED FUNCTION]');
					$response = $this->commands->handle($command, $dataArr);
				}
			}
			
			//Bot owner commands (shares the same username)
			if ($this->lastuser == $this->nick) {
				if (in_array($command, $this->private_functions)) {
					if ($this->verbose) $this->emit('[PRIVATE FUNCTION]');
					$response = $this->commands->handle($command, $dataArr);
				}
			}
			
			//Reply with a preset message
			if (isset($this->responses[$command])) {
				if ($this->verbose) $this->emit('[RESPONSE]');
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
        }
		return $user;
    }
	
	protected function parseChannel(string $data): ?string
	{
		$arr = explode(' ', substr($data, strpos($data, '#')));
        if (substr($arr[0], 0, 1) == "#") return substr($arr[0], 1);
    }
	
	/*
	* This function can double as an event listener
	*/
	public function emit(string $string): void
	{
        echo "[EMIT] $string" . PHP_EOL;
    }
	
	public function getChannels(): array
	{
		return $this->channels;
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
	
	public function getDiscordOutput(): ?bool
	{
		return $this->discord_output;
	}
	
	public function getGuildId(): ?string
	{
		return $this->guild_id;
	}
	
	public function getChannelId(): ?string
	{
		return $this->channel_id;
	}
	
	public function linkDiscord($discord): void
	{
		$this->discord = $discord;
	}
	
	public function discordRelay($payload): void
	{
		if ($this->discord_output){
			if ($this->verbose) $this->emit('[DISCORD CHAT RELAY]');
			if(
				($discord = $this->discord)
				&&
				($guild = $discord->guilds->offsetGet($this->guild_id))
				&&
				($channel = $guild->channels->offsetGet($this->channel_id))
			)
			$channel->sendMessage($payload);
		}
	}
}
