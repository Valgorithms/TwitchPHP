<?php

/*
* This file is a part of the TwitchPHP project.
*
* Copyright (c) 2021 ValZarGaming <valzargaming@gmail.com>
*/


namespace Twitch;

use Twitch\Commands;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Evenement\EventEmitterTrait;

class Twitch
{
    use EventEmitterTrait;

    protected $loop;
    protected $commands;
    
    
    private $discord;
    private $discord_output;
    private $guild_channel_ids; //guild=>channel assoc array
    
    private $verbose;
    private $socket_options;
    private $debug;
    
    private $secret;
    private $nick;
    private $channels;
    private $commandsymbol;
    private $badwords;
    
    private $whitelist;
    private $responses;
    private $functions;
    private $restricted_functions;
    private $private_functions;
    
    protected $connector;
    public $connection;
    protected $running;
    
    private $lastuser; //Who last sent a message in Twitch chat
    private $lastchannel; //Where the last command was used
    private $lastmessage; //What the last message was

    function __construct(array $options = [])
    {
        if (php_sapi_name() !== 'cli') trigger_error('TwitchPHP will not run on a webserver. Please use PHP CLI to run a TwitchPHP self-bot.', E_USER_ERROR);
        
        $options = $this->resolveOptions($options);
        
        $this->loop = $options['loop'];
        $this->secret = $options['secret'];
        $this->nick = $options['nick'];
        $this->channels = $options['channels'];
        if(is_null($this->channels)) $this->channels[$options['nick']] = [];
        $this->commandsymbol = $options['commandsymbol'] ?? array('!');
        
        foreach ($options['whitelist'] as $whitelist) $this->whitelist[] = $whitelist;
        $this->responses = $options['responses'] ?? array();
        $this->functions = $options['functions'] ?? array();
        $this->restricted_functions = $options['restricted_functions'] ?? array();
        $this->private_functions = $options['private_functions'] ?? array();
        
        if (isset($options['socket_options'])) $this->socket_options = $options['socket_options'];
        if (isset($options['verbose'])) $this->verbose = $options['verbose'];
        if (isset($options['debug'])) $this->debug = $options['debug'];
        if (isset($options['logger']) && $options['logger'] instanceof \Monolog\Logger) $this->logger = $options['logger'];
        else {
            $this->logger = new \Monolog\Logger('New logger');
            $this->logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'));
        }
        
        if (isset($options['discord'])) $this->discord = $options['discord'];
        if (isset($options['discord_output'])) $this->discord_output = $options['discord_output'];
        
        $this->connector = new Connector($this->loop, $options['socket_options']);
        
        include 'Commands.php';
        $this->commands = $options['commands'] ?? new Commands($this, $this->verbose);
    }
    
    public function run(bool $runLoop = true): void
    {
        if ($this->verbose) $this->logger->info('[RUN]');
        if (!$this->running) {
            $this->running = true;
            $this->connect();
        }
        if ($this->verbose) $this->logger->info('[LOOP->RUN]');
        if ($runLoop) $this->loop->run();
    }
    
    public function close(bool $closeLoop = true): void
    {
        if ($this->verbose) $this->logger->info('[CLOSE]');
        if ($this->running) {
            $this->running = false;
            foreach ($this->channels as $twitch_channel => $arr) $this->leaveChannel($twitch_channel);
        }
        if ($closeLoop) {
            if ($this->verbose) $this->logger->info('[LOOP->STOP]');
            $this->loop->stop();
        }
    }
    
    public function write(string $string): void
    {
        if ($this->debug) $this->logger->debug("[WRITE] $string");
        $this->connection->write($string);
    }
    
    public function sendMessage(string $data, ?string $channel = null): bool
    {
        if (isset($this->connection) && ($this->connection !== false)) {
            if ($channel) $this->lastchannel = $channel;
            if ($this->lastchannel) {
                $this->write("PRIVMSG #{$this->lastchannel} :$data\n");
                $this->logger->info("[REPLY] #{$this->lastchannel} - $data");
                return true;
            }
        }
        return false;
    }
    
    public function joinChannel(string $string = "", ?string $guild_id = '', ?string $channel_id = '')
    {
        if ($this->verbose) $this->logger->info('[VERBOSE] [JOIN CHANNEL] `' . $string . '`');    
        if (! isset($this->connection) || $this->connection === false) return;
        if (! $string) return;
        
        $string = strtolower($string);
        /*if (!isset($this->channels[$string]))*/ $this->write("JOIN #$string\n");
        if (!isset($this->channels[$string]) || ! isset($this->channels[$string][$guild_id])) $this->channels[$string][$guild_id] = $channel_id;
    }
    
    /*
    * Commands.php should never send a string so as to prevent users from being able to tell the bot to leave someone else's channel
    * This command is exposed so other ReactPHP applications can call it, but those applications should always attempt to pass a valid string
    * getChannels has also been exposed for the purpose of checking if the string exists before attempting to call this function
    */
    public function leaveChannel(?string $string, ?string $guild_id = '', ?string $channel_id = ''): bool
    {
        $string = strtolower($string ?? $this->lastchannel);
        if (! isset($this->channels[$string])) return false;
        if (! isset($this->connection) || $this->connection === false) return false;
        if ($this->verbose) $this->logger->info("[VERBOSE] [LEAVE CHANNEL] `$string - $guild_id - $channel_id`");
        
        if (! $guild_id) unset($this->channels[$string]);
        else if($this->discord->guilds->get('id', $guild_id) && isset($this->channels['guild_id'])) unset($this->channels['guild_id']);
        
        if (! isset($this->channels[$string]) || empty($this->channels[$string])) $this->write("PART #$string\n");
    }
    
    public function ban($username, $reason = ''): bool
    {
        if ($this->verbose) $this->logger->info("[BAN] $username - $reason");
        if (! isset($this->connection) || $this->connection === false) return false;
        if ($username != $this->nick && !in_array($username, array_keys($this->channels))) {
            $this->write("/ban $username $reason");
            return true;
        }
        return false;
    }
    
    /*
    * Attempt to catch errors with the user-provided $options early
    */
    protected function resolveOptions(array $options = []): array
    {
        if (!$options['secret']) trigger_error('TwitchPHP requires a client secret to connect. Get your Chat OAuth Password here => https://twitchapps.com/tmi/', E_USER_ERROR);
        if (!$options['nick']) trigger_error('TwitchPHP requires a client username to connect. This should be the same username you use to log in.', E_USER_ERROR);
        $options['nick'] = strtolower($options['nick']);
        $options['loop'] = $options['loop'] ?? \React\EventLoop\Loop::get();
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
    protected function connect()
    {
        $url = 'irc.chat.twitch.tv';
        $port = '6667';
        if ($this->verbose) $this->logger->info("[CONNECT] $url:$port");
        if (isset($this->connection) && $this->connection !== false) return $this->logger->warning('[CONNECT] A connection already exists');
        
        $this->connector->connect("$url:$port")->then(
            function (ConnectionInterface $connection) {
                $this->initIRC($this->connection = $connection);
                $connection->on('data', function($data) {
                    if ($this->debug) $this->logger->debug("[DATA] $data");
                    if ($this->connection !== false) $this->process($data, $this->connection);
                });
                $connection->on('close', function () {
                    $this->logger->info('[CLOSE]');
                });
                $this->logger->info('[CONNECTED]');
            },
            function (\Exception $exception) {
                $this->logger->warning($exception->getMessage());
            }
        );
    }
    protected function initIRC(ConnectionInterface $connection): void
    {
        $this->write("PASS " . $this->secret . "\n");
        $this->write("NICK " . $this->nick . "\n");
        $this->write("CAP REQ :twitch.tv/membership\n");
        foreach (array_keys($this->channels) as $twitch_channel) $this->write("JOIN #$twitch_channel\n");
        if ($this->verbose) $this->logger->info('[INIT IRC]');
    }

    protected function pingPong(ConnectionInterface $connection): void
    {
        if ($this->debug) $this->logger->debug('[' . date('h:i:s') . '] PING :tmi.twitch.tv');
        $this->write("PONG :tmi.twitch.tv\n");
        if ($this->debug) $this->logger->debug('[' . date('h:i:s') . '] PONG :tmi.twitch.tv');
    }
    
    protected function process(string $data, ConnectionInterface $connection)
    {
        if (trim($data) == 'PING :tmi.twitch.tv') return $this->pingPong($connection);
        if (preg_match('/PRIVMSG/', $data)) {
            if ($response = $this->parseCommand($data)) {
                $this->discordRelay("[REPLY] #{$this->lastchannel} - $response");
                if (!$this->sendMessage("@{$this->lastuser}, $response\n")) $this->logger->warning('[FAILED TO SEND MESSAGE TO TWITCH]');
            }
        }
    }
    protected function badwordsCheck($message): bool
    {
        if ($this->debug) $this->logger->debug('[BADWORD CHECK] ' . $message);
        foreach ($this->badwords as $badword) if (str_contains(strtolower($message), strtolower($badword))) {
            if ($this->verbose) $this->logger->info('[BADWORD] ' . $badword);
            return true;
        }
        return false;
    }
    
    protected function parseCommand(string $data): string
    {
        $this->lastuser = $this->parseUser($data);
        $this->lastchannel = $this->parseChannel($data);
        $this->lastmessage = trim(substr($data, strpos($data, 'PRIVMSG')+11+strlen($this->lastchannel)));
        
        $msg = "#{$this->lastchannel} -  {$this->lastuser}: {$this->lastmessage}";
        if ($this->verbose) $this->logger->info("[PRIVMSG] $msg");
        if (!empty($this->badwords) && $this->badwordsCheck($this->lastmessage) && $this->lastuser != $this->nick) {
            $this->ban($this->lastuser);
            $this->discordRelay("[BANNED - BAD WORD] #{$this->lastchannel} - {$this->lastuser}");
        } else $this->discordRelay("[MSG] $msg");
        
        $called = false;
        foreach($this->commandsymbol as $symbol) if (str_starts_with($this->lastmessage, $symbol)) {
            $this->lastmessage = trim(substr($this->lastmessage, strlen($symbol)));
            $called = true;
            break;
        }
        if (!$called) return '';
        
        $dataArr = explode(' ', $this->lastmessage);
        $command = strtolower(trim($dataArr[0]));
        if ($this->verbose) $this->logger->info("[COMMAND] `$command`");         
        
        $response = '';
        //Public commands
        if (in_array($command, $this->functions)) {
            if ($this->verbose) $this->logger->info('[PUBLIC FUNCTION]');
            $response = $this->commands->handle($command, $dataArr);
        }
        //Whitelisted commands
        if ( in_array($this->lastuser, $this->whitelist) || ($this->lastuser == $this->nick) ) {
            if (in_array($command, $this->restricted_functions)) {
                if ($this->verbose) $this->logger->info('[WHITELISTED FUNCTION]');
                $response = $this->commands->handle($command, $dataArr);
            }
        }
        //Bot owner commands (shares the same username)
        if ($this->lastuser == $this->nick) {
            if (in_array($command, $this->private_functions)) {
                if ($this->verbose) $this->logger->info('[PRIVATE FUNCTION]');
                $response = $this->commands->handle($command, $dataArr);
            }
        }
        //Reply with a preset message
        if (isset($this->responses[$command])) {
            if ($this->verbose) $this->logger->info('[RESPONSE]');
            $response = $this->responses[$command];
        }
        return $response;
    }
    
    protected function parseUser(string $data): ?string
    {
        if (substr($data, 0, 1) == ":") return substr(explode('!', $data)[0], 1);
    }
    
    protected function parseChannel(string $data): ?string
    {
        $arr = explode(' ', substr($data, strpos($data, '#')));
        if (substr($arr[0], 0, 1) == '#') return substr($arr[0], 1);
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
    
    public function getLastChannel(): ?string
    {
        return $this->lastchannel;
    }
    
    public function getLastUser(): ?string
    {
        return $this->lastuser;
    }
    
    public function getLastMessage(): ?string
    {
        return $this->lastmessage;
    }
    
    public function linkDiscord($discord): void
    {
        $this->discord = $discord;
    }
    
    public function discordRelay($payload): bool
    {
        if (! $this->discord_output || ! $discord = $this->discord) return false;
        if ($this->verbose) $this->logger->info('[DISCORD CHAT RELAY]');
        if (empty($this->channels)) return false;
        foreach ($this->channels[$this->lastchannel] as $guild_id => $channel_id) {
            if (! $guild = $this->discord->guilds->get('id', $guild_id)) continue;
            if (! $channel = $guild->channels->get('id', $channel_id)) continue;
            if (! $channel->sendMessage($payload)) $this->logger->warning('[FAILED TO SEND MESSAGE TO TWITCH]');
        }
        return true;
    }
}