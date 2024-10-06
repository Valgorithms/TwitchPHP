<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

use Twitch\Commands;

use Evenement\EventEmitterTrait;
use Discord\Discord; // DiscordPHP
use Discord\Helpers\CacheConfig;
use Discord\Helpers\Collection;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Monolog\Level;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twitch\Factory\Factory;
use Twitch\Repository\AbstractRepository;

use function React\Async\await;
use function React\Promise\resolve;
use function React\Promise\reject;

enum MessageType: string
{
    case PING = 'PING';
    case PRIVMSG = 'PRIVMSG';
    case UNKNOWN = 'UNKNOWN';

    public static function fromData(string $data): self
    {
        if (trim($data) === 'PING :tmi.twitch.tv') return self::PING;
        if (str_contains($data, 'PRIVMSG')) return self::PRIVMSG;
        return self::UNKNOWN;
    }
}

/**
 * Twitch class represents the Twitch API client.
 *
 * @category Twitch API
 * @package  TwitchPHP
 * @license  MIT License <https://opensource.org/licenses/MIT>
 * @link     https://github.com/VZGCoders/TwitchPHP
 * @link     https://github.com/discord-php/DiscordPHP/ Required if relaying messages to Discord
 */
class Twitch
{
    use EventEmitterTrait;
    
    public const IRC_URL = "irc.chat.twitch.tv:6667";
    public const REDACTED_WRITES = ['PASS'];

    protected Loop|StreamSelectLoop $loop;
    protected Commands $commands;
    
    /**
     * The logger.
     *
     * @var LoggerInterface Logger.
     */
    public $logger;

    /**
     * The Helix client.
     *
     * @var Helix Client.
     */
    protected $http;

    /**
     * The part/repository factory.
     *
     * @var Factory Part factory.
     */
    protected $factory;

    /**
     * The cache configuration.
     *
     * @var CacheConfig[]
     */
    protected $cacheConfig;
    
    private $discord;
    private bool $discord_output = false;
    //private $guild_channel_ids; //guild=>channel assoc array
    private array $socket_options = [];
    
    public bool $verbose = false;
    public bool $debug = false;
    
    private string $secret = '';
    private string $nick = '';
    private array $channels = [];
    private array $commandsymbol = [];
    
    private array $whitelist = [];
    private array $responses = [];
    private array $functions = [];
    private array $restricted_functions = [];
    private array $private_functions = [];
    
    protected Connector $connector;
    public ConnectionInterface $connection;
    protected bool $running = false;

    public $userCache = [];
    public $channelCache = [];
    public $messageCache = [];
    
    private User|string $lastuser = ''; //Who last sent a message in Twitch chat
    private Channel|string $lastchannel = ''; //Where the last command was used
    private Message|string $lastmessage = ''; //What the last message was
    
    private int $retry = 0;

    /**
     * The Client class.
     *
     * @var Client Twitch client.
     */
    protected $client;

    /**
     * Twitch constructor.
     *
     * @param array $options An array of options to configure the Twitch client.
     */
    function __construct(array $options = [])
    {
        if (php_sapi_name() !== 'cli') trigger_error('TwitchPHP will not run on a webserver. Please use PHP CLI to run a TwitchPHP self-bot.', E_USER_ERROR);
        
        $options = $this->resolveOptions($options);

        if (isset($options['cache'])) {
            $this->cacheConfig = $options['cache'];
            if ($cacheConfig = $this->getCacheConfig()) {
                $this->logger->warning('Attached experimental CacheInterface: '.get_class($cacheConfig->interface));
            }
        }
        
        $this->loop = $options['loop'] ?? Loop::get();
        $this->secret = $options['secret'];
        $this->nick = $options['nick'];
        $this->channels = $options['channels'];
        if (is_null($this->channels)) $this->channels[$options['nick']] = [];
        $this->commandsymbol = $options['symbol'] ?? array('!');
        
        $this->whitelist[] = strtolower($this->nick);
        foreach ($options['whitelist'] as $whitelist) $this->whitelist[] = $whitelist;
        $this->responses = $options['responses'] ?? array();
        $this->functions = $options['functions'] ?? array();
        $this->restricted_functions = $options['restricted_functions'] ?? array();
        $this->private_functions = $options['private_functions'] ?? array();
        
        if (isset($options['socket_options'])) $this->socket_options = $options['socket_options'];
        if (isset($options['verbose'])) $this->verbose = $options['verbose'];
        if (isset($options['debug'])) $this->debug = $options['debug'];
        if (isset($options['logger']) && $options['logger'] instanceof Monolog) $this->logger = $options['logger'];
        else {
            /** @var Monolog */
            $this->logger = new Monolog('New logger');
            $this->logger->pushHandler(new StreamHandler('php://stdout'));
        }
        
        if (isset($options['discord'])) $this->discord = $options['discord'];
        if (isset($options['discord_output'])) $this->discord_output = $options['discord_output'];
        
        $this->connector = new Connector($this->socket_options, $this->loop);
        
        $this->commands = $options['commands'] ?? new Commands($this, $this->verbose);

        /*$this->http = new Http(
            'Bot '.$this->token,
            $this->loop,
            $this->options['logger'],
            new React($this->loop, $options['socket_options'])
        );

        $this->factory = new Factory($this);
        $this->client = $this->factory->part(Client::class, []);*/

        $this->on('PRIVMSG', fn($data) => $this->privmsg($data));
        $this->on('PING', fn($data) => $this->pingPong());
    }
    
    /**
     * Runs the Twitch client.
     *
     * @param bool $runLoop Whether to run the event loop or not.
     * @return void
     */
    public function run(bool $runLoop = true): void
    {
        if ($this->verbose) $this->logger->info('[LOOP->RUN]');
        if (! $this->running) {
            $this->running = true;
            $this->connect()->then(
                fn ($result) => var_dump($this->log('success', json_encode($result))),
                fn ($error) => var_dump($this->log('error', json_encode($error)))
            );
        }
        if ($runLoop) $this->loop->run();
    }
    
    /**
     * Closes the Twitch connection and stops the event loop.
     *
     * @param bool $closeLoop Whether to stop the event loop or not. Default is true.
     * @return void
     */
    public function close(bool $closeLoop = true): void
    {
        if ($this->verbose) $this->logger->info('[CLOSE]');
        if ($this->running) {
            $this->running = false;
            foreach (array_keys($this->channels) as $twitch_channel) $this->leaveChannel($twitch_channel);
        }
        if ($closeLoop) {
            if ($this->verbose) $this->logger->info('[LOOP->STOP]');
            $this->loop->stop();
        }
    }

    public function log(string $method, string $message): string
    {
        $this->logger->$method($message);
        return $message;
    }

    /**
     * Allows access to the part/repository factory.
     *
     * @param string $class   The class to build.
     * @param mixed  $data    Data to create the object.
     * @param bool   $created Whether the object is created (if part).
     *
     * @return Part|AbstractRepository
     *
     * @see Factory::create()
     *
     * @deprecated 10.0.0 Use `new $class($discord, ...)`.
     */
    public function factory(string $class, $data = [], bool $created = false)
    {
        return $this->factory->create($class, $data, $created);
    }

    /**
     * Gets the factory.
     *
     * @return Factory
     */
    public function getFactory(): Factory
    {
        return $this->factory;
    }

    /**
     * Gets the Helix client.
     *
     * @return Helix
     */
    public function getHttpClient(): Helix
    {
        return $this->http;
    }

    /**
     * Gets the loop being used by the client.
     *
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }
    
    /**
     * Writes a string to the connection.
     *
     * @param string $string The string to write.
     * @return PromiseInterface<null, string> 
     */
    public function write(string $string): PromiseInterface
    {
        if ($this->debug) {
            $redactedString = array_reduce(self::REDACTED_WRITES, function ($carry, $redacted) use ($string) {
                return str_starts_with($string, $redacted) ? "[WRITE] $redacted ********" : $carry;
            }, "[WRITE] $string");
            $this->logger->debug($redactedString);
        }

        $deferred = new Deferred;

        if ($this->connection->write($string)) {
            $deferred->resolve();
            return $deferred->promise();
        }
        
        return $this->retryConnection($deferred_callback = fn () => $this->write($string))
            ->then(null, function (\Exception $error) use ($deferred_callback) {
                return is_string ($yield = yield $this->retryConnection($deferred_callback))
                    ? $yield
                    : $this->log('warning', $error->getMessage());
            });
    }

    /**
     * Attempts to retry the connection to the Twitch service.
     * Logs a warning message before attempting to reconnect.
     * If the connection is successful, resets the retry counter and sets the connection.
     * If a deferred callback is provided, it will be executed upon successful connection.
     * If the connection fails, it will retry up to 5 times before logging an error message.
     * 
     * @param callable|null $deferred_callback A callback to be executed upon successful connection.
     * @return PromiseInterface A promise that resolves to the connection or logs an error message.
     */
    private function retryConnection(?callable $deferred_callback = null): PromiseInterface
    {
        $this->logger->warning('[RETRY CONNECTION]');
        return $this->connect()->then(
            function (ConnectionInterface $connection) use ($deferred_callback) {
                $this->retry = 0;
                $this->connection = $connection;
                if ($deferred_callback) $deferred_callback();
                return $connection;
            },
            function (\Exception $error) use ($deferred_callback) {
                if ($this->retry++ > 5) {
                    $this->logger->error('[RETRY CONNECTION] Failed to reconnect after 5 attempts');
                    return $this->log('error', $error->getMessage());
                }
                return is_string ($yield = yield $this->retryConnection($deferred_callback))
                    ? $yield
                    : $this->log('warning', $error->getMessage());
            }
        );
    }
    
    /**
     * Sends a message to a Twitch channel.
     *
     * @param string $data The message to be sent.
     * @param string|null $channel The channel to send the message to. If null, the last channel used will be used.
     * @return bool Returns true if the message was sent successfully, false otherwise.
     */
    public function sendMessage(string $data, Channel|string|null $channel = null): PromiseInterface|false
    {
        if (! $channel) {
            $this->logger->warning('[SEND MESSAGE] No channel specified');
            return false;
        }
        $this->lastchannel = is_string($channel) ? new Channel($this, $channel) : $channel;
        $this->logger->info("[REPLY] #{$this->lastchannel} - $data");
        return $this->write("PRIVMSG #{$this->lastchannel} :$data\n");
    }
    
    /**
     * Joins a Twitch channel.
     *
     * @param string $string The name of the channel to join.
     * @param string|null $guild_id The ID of the guild (optional).
     * @param string|null $channel_id The ID of the channel (optional).
     * @return bool Returns true if the channel was joined successfully, false otherwise.
     */
    public function joinChannel(string $string = "", ?string $guild_id = '', ?string $channel_id = ''): bool
    {
        if ($this->verbose) $this->logger->info("[VERBOSE] [JOIN CHANNEL] `$string`");
        if (! isset($this->connection) || $this->connection === false) {
            $this->logger->warning('[JOIN CHANNEL] No connection to Twitch');
            return false;
        }
        if (! $string) return false;
        
        $string = strtolower($string);
        /*if (!isset($this->channels[$string]))*/ $this->write("JOIN #$string\n");
        if ($channel_id) $this->channels[$string][$guild_id] = $channel_id;
        else $this->channels[$string][''] = '';
        $this->channels[$string]['channel'] = new Channel($this, $string);
        return true;
    }

    /**
     * Leave a Twitch channel.
     *
     * @param string|null $string The name of the channel to leave. If null, the last channel joined will be left.
     * @param string|null $guild_id The ID of the guild where the channel is located. Optional.
     * @param string|null $channel_id The ID of the channel to leave. Optional.
     * @return bool Returns true if the channel was successfully left, false otherwise.
     */
    public function leaveChannel(?string $string, ?string $guild_id = '', ?string $channel_id = ''): bool
    {
        $string = strtolower($string ?? $this->lastchannel);
        if (! isset($this->channels[$string])) {
            $this->logger->info('[LEAVE CHANNEL] Channel not found');
            return false;
        }
        if (! isset($this->connection) || $this->connection === false) {
            $this->logger->warning('[LEAVE CHANNEL] No connection to Twitch');
            return false;
        }
        if ($this->verbose) $this->logger->info("[VERBOSE] [LEAVE CHANNEL] `$string - $guild_id - $channel_id`");
        
        if (! $guild_id) unset($this->channels[$string]);
        if ($channel_id && isset($this->channels[$string][$guild_id])) unset($this->channels[$string][$guild_id]);
        if (! isset($this->channels[$string]) || empty($this->channels[$string])) $this->write("PART #$string\n");
        return true;
    }
    
    /*
     * Attempt to catch errors with the user-provided $options early
     * 
     * @param  array     $options    The options to resolve.
     * 
     * @return array                 The resolved options.
     */
    protected function resolveOptions(array $options = []): array
    {
        if (! isset($options['secret'])) throw new \Exception('TwitchPHP requires a client secret to connect. Get your Chat OAuth Password here => https://twitchapps.com/tmi/', E_USER_ERROR);
        if (! isset($options['nick'])) throw new \Exception('TwitchPHP requires a client username to connect. This should be the same username you would use to log in.', E_USER_ERROR);
        
        $resolver = new OptionsResolver();

        $resolver
            ->setRequired(['secret', 'nick'])
            ->setDefined([
                'cache',
                'loop',
                'logger',
                'socket_options',
                'dnsConfig',
                'symbol',
                'debug',
                'verbose',
                'channels',
                'responses',
                'social',
                'tip',
                'functions',
                'private_functions',
                'restricted_functions',
                'whitelist',
            ])
            ->setDefaults([
                'logger' => null,
            ])
            ->setAllowedTypes('secret', 'string')
            ->setAllowedTypes('nick', 'string')
            ->setAllowedTypes('logger', ['null', LoggerInterface::class])
            ->setAllowedTypes('loop', LoopInterface::class)
            ->setAllowedTypes('socket_options', 'array')
            ->setAllowedTypes('cache', ['array', CacheConfig::class, \React\Cache\CacheInterface::class, \Psr\SimpleCache\CacheInterface::class])
            ->setNormalizer('cache', function ($options, $value) {
                if (! is_array($value)) {
                    if (! ($value instanceof CacheConfig)) {
                        $value = new CacheConfig($value);
                    }

                    return [AbstractRepository::class => $value];
                }

                return $value;
            });

        $options = $resolver->resolve($options);

        $options['loop'] ??= Loop::get();

        if (null === $options['logger']) {
            $streamHandler = new StreamHandler('php://stdout', Level::Debug);
            $lineFormatter = new LineFormatter(null, null, true, true);
            $streamHandler->setFormatter($lineFormatter);
            $logger = new Monolog('TwitchPHP', [$streamHandler]);
            $options['logger'] = $logger;   $dnsConfig = \React\Dns\Config\Config::loadSystemConfigBlocking();
            if (! $dnsConfig->nameservers) {
                $dnsConfig->nameservers[] = '8.8.8.8';
            }

            $options['dnsConfig'] = $dnsConfig;
        }

        $options['nick'] = strtolower($options['nick']);
        $options['loop'] = $options['loop'] ?? Loop::get();
        $options['symbol'] = $options['symbol'] ?? '!';
        $options['responses'] = $options['responses'] ?? array();
        $options['functions'] = $options['functions'] ?? array();

        // Twitch doesn't currently support IPv6
        // This prevents xdebug from catching exceptions when trying to fetch IPv6
        $options['socket_options']['happy_eyeballs'] = false;
        
        return $options;
    }

    /**
     * Connects to the Twitch IRC server.
     * This command should not be run while the bot is still connected to Twitch
     * Additional handling may be needed in the case of disconnect via $connection->on('close' (See: Issue #1 on GitHub)
     *
     * @return PromiseInterface<ConnectionInterface>
     */
    protected function connect(?callable $deferred_callback = null): PromiseInterface
    {
        if ($this->verbose) $this->logger->info('[CONNECT]');
        $user = await(Helix::getUser($this->nick));
        if ($user === '{"error":"Unauthorized","status":401,"message":"OAuth token is missing"}') {
            $this->logger->error("Oauth token is missing, exiting...");
            return reject("Oauth token is missing");
        }
        $this->logger->info("[USER] " . $user);
        $userData = json_decode($user, true);
        // Check if the data is properly decoded and contains the expected structure
        if (! isset($userData['data'][0]['id'])) $this->logger->error("Failed to extract user ID from the data.");
        else $this->logger->info("[CHANNEL] " . await(Helix::getChannelInformation($userData['data'][0]['id'])));

        if (isset($this->connection) && $this->connection instanceof ConnectionInterface) {
            $this->logger->warning('[CONNECT] A connection already exists');
            return resolve($this->connection);
        }
        $promise = $this->connector->connect(self::IRC_URL)->then(
            fn (ConnectionInterface $connection) => $this->__connect($this->connection = $connection),
            //fn (\Exception $error) => $this->log('error', json_encode($error))
        );
        return $promise;
    }

    private function __connect(ConnectionInterface $connection, ?callable $deferred_callback = null)
    {
        $this->connection = $connection;
        
        $this->initIRC($connection);
        $this->logger->info('[CONNECTED]');
        if ($deferred_callback) $deferred_callback();
        $connection->on('data', fn($data) => $this->process($data));
        $connection->on('close', function () {
            if ($this->verbose) $this->logger->info('[CLOSE]');
            $this->logger->info('[DISCONNECTED, RECONNECTING IN 5 SECONDS]');
            unset($this->connection);
            $this->loop->addTimer(5, fn () => $this->running ? $this->retryConnection() : null);
        });
    }   
    
    /**
     * Initializes the IRC connection by sending the secret, nick, and joining the channels.
     *
     * @return void
     */
    protected function initIRC(): void
    {
        if ($this->verbose) $this->logger->info('[INIT IRC]');
        $this->write("PASS {$this->secret}\n");
        $this->write("NICK {$this->nick}\n");
        $this->write("CAP REQ :twitch.tv/membership\n");
        foreach (array_keys($this->channels) as $twitch_channel) $this->write("JOIN #$twitch_channel\n");
    }

    /**
     * Sends a PONG message to the Twitch server to maintain the connection.
     *
     * @return void
     */
    protected function pingPong(): void
    {
        if ($this->debug) $this->logger->debug('[' . date('h:i:s') . '] PING :tmi.twitch.tv');
        $this->write("PONG :tmi.twitch.tv\n");
        if ($this->debug) $this->logger->debug('[' . date('h:i:s') . '] PONG :tmi.twitch.tv');
    }

    protected function privmsg($data): void
    {
        $this->parseData($data);
        if ($response = $this->parseCommand()) {
            $this->discordRelay("[REPLY] #{$this->lastchannel} - $response");
            $this->sendMessage("@{$this->lastuser}, $response\n");
        }
    }

    /**
     * Processes the received data from Twitch.
     *
     * If the received data is a PING message, it sends a PONG message back to the server.
     * If the received data is a PRIVMSG message, it parses the command and sends a response to the user.
     * It also relays the response to Discord and logs a warning if the message fails to send to Twitch.
     *
     * @param string $data The received data from Twitch.
     * @return void
     */
    protected function process(string $data): void
    {
        if ($this->verbose) $this->logger->debug("[DATA] $data");
        $messageType = MessageType::fromData($data);
        $this->emit($messageType->name, [$data]);
    }

    /**
     * Parses the data received from the Twitch IRC server and sets the last user, last channel, and last message properties.
     *
     * @param string $data The data received from the Twitch IRC server.
     * @return void
     */
    protected function parseData(string $data): void
    {
        $lastuser = $lastchannel = $lastmessage = null;
        if ($us = $this->parseUser($data)) {
            $lastuser = new User($this, $us);
            $lastuser->seen();
        }
        if ($ch = $this->parseChannel($data)) {
            $lastchannel = new Channel($this, $ch);
        }
        if ($msg = trim(substr($data, strpos($data, 'PRIVMSG')+11+strlen($ch)))) {
            $lastmessage = new Message($this, $msg, $lastchannel, $lastuser);
        }
        
        if ($us) {
            $lastuser = new User(
                $this,
                $us,
                $lastchannel,
                new Collection(),
                $lastmessage,
            );
        }
        if ($ch) {
            $lastchannel = new Channel(
                $this,
                $ch,
                new Collection(),
                $lastmessage,
                $lastuser
            );
        }
        if ($lastmessage) {
            //
        }

        if ($lastuser instanceof User) {
            if ($lastchannel instanceof Channel) $lastuser->lastchannel = $lastchannel;
            if ($lastmessage instanceof Message) $lastuser->lastmessage = $lastmessage;
        }
        if ($lastchannel instanceof Channel) {
            if ($lastuser instanceof User) $lastchannel->lastuser = $lastuser;
            if ($lastmessage instanceof Message) $lastchannel->lastmessage = $lastmessage;
        }
        if ($lastmessage instanceof Message) {
            if ($lastuser instanceof User) $lastmessage->user = $lastuser;
            if ($lastchannel instanceof Channel) $lastmessage->channel = $lastchannel;
        }

        $this->lastuser = $lastuser;
        $this->lastchannel = $lastchannel;
        $this->lastmessage = $lastmessage;
    }

    /**
     * Parses a command from the given data and returns a response.
     *
     * @param string $data The data to parse the command from.
     * @return string The response to the parsed command.
     */
    protected function parseCommand(): string
    {
        $msg = "#{$this->lastchannel} - {$this->lastuser}: {$this->lastmessage}";
        if ($this->verbose) $this->logger->info("[PRIVMSG] $msg");
        $this->discordRelay("[TTV] $msg");
        
        $called = false;
        foreach($this->commandsymbol as $symbol) if (str_starts_with($this->lastmessage, $symbol)) {
            $this->lastmessage = trim(substr($this->lastmessage, strlen($symbol)));
            $called = true;
            break;
        }
        if (! $called) return '';
        
        $dataArr = explode(' ', $this->lastmessage);
        $dataArr[0] = strtolower(trim($dataArr[0]));
        if ($this->verbose) $this->logger->info("[COMMAND] `{$dataArr[0]}`");         
        
        $response = '';
        //Public commands
        if (in_array($dataArr[0], $this->functions)) {
            if ($this->verbose) $this->logger->info('[PUBLIC FUNCTION]');
            $response = $this->commands->handle($dataArr);
        }
        //Whitelisted commands
        if ( in_array($this->lastuser, $this->whitelist) || ($this->lastuser === $this->nick) ) {
            if (in_array($dataArr[0], $this->restricted_functions)) {
                if ($this->verbose) $this->logger->info('[WHITELISTED FUNCTION]');
                $response = $this->commands->handle($dataArr);
            }
        }
        //Bot owner commands (shares the same username)
        if ($this->lastuser === $this->nick) {
            if (in_array($dataArr[0], $this->private_functions)) {
                if ($this->verbose) $this->logger->info('[PRIVATE FUNCTION]');
                $response = $this->commands->handle($dataArr);
            }
        }
        //Reply with a preset message
        if (isset($this->responses[$dataArr[0]])) {
            if ($this->verbose) $this->logger->info('[RESPONSE]');
            $response = $this->responses[$dataArr[0]];
        }
        return $response;
    }
    
    /**
     * Parses the user from the given data string.
     *
     * @param string $data The data string to parse.
     *
     * @return string|null The parsed user or null if the data string does not start with a colon.
     */
    protected function parseUser(string $data): ?string
    {
        if (substr($data, 0, 1) === ":") return substr(explode('!', $data)[0], 1);
    }
    
    /**
     * Parses the channel name from the given data string.
     *
     * @param string $data The data string to parse the channel name from.
     *
     * @return string|null The parsed channel name, or null if it could not be parsed.
     */
    protected static function parseChannel(string $data): ?string
    {
        $arr = explode(' ', substr($data, strpos($data, '#')));
        return ltrim($arr[0], '#');
    }
    
    /**
     * Returns an array of channels.
     *
     * @return array An array of channels.
     */
    public function getChannels(): array
    {
        return $this->channels;
    }
    
    /**
     * Returns the command symbol(s) used by the Twitch bot.
     *
     * @return array The command symbol(s) used by the Twitch bot.
     */
    public function getCommandSymbol(): array
    {
        return $this->commandsymbol;
    }
    
    /**
     * Returns an array of responses.
     *
     * @return array An array of responses.
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /**
     * Returns an array of functions.
     *
     * @return array An array of functions.
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Returns an array of restricted functions.
     *
     * @return array An array of restricted functions.
     */
    public function getRestrictedFunctions(): array
    {
        return $this->restricted_functions;
    }

    /**
     * Returns an array of private functions.
     *
     * @return array An array of private functions.
     */
    public function getPrivateFunctions(): array
    {
        return $this->private_functions;
    }
    
    /**
     * Returns whether the Discord output is enabled or not.
     *
     * @return bool|null True if Discord output is enabled, false if it's disabled, and null if it hasn't been set.
     */
    public function getDiscordOutput(): ?bool
    {
        return $this->discord_output;
    }

    /**
     * Returns the name of the last channel that received a message.
     *
     * @return string|null The name of the last channel that received a message, or null if it hasn't been set.
     */
    public function getLastChannel(): ?string
    {
        return $this->lastchannel;
    }

    /**
     * Returns the name of the last user that sent a message.
     *
     * @return string|null The name of the last user that sent a message, or null if it hasn't been set.
     */
    public function getLastUser(): ?string
    {
        return $this->lastuser;
    }

    /**
     * Returns the last message that was received.
     *
     * @return string|null The last message that was received, or null if it hasn't been set.
     */
    public function getLastMessage(): ?string
    {
        return $this->lastmessage;
    }

    /**
     * Links the Twitch bot to a Discord bot.
     *
     * @param mixed $discord The Discord bot to link to.
     *
     * @return void
     */
    public function linkDiscord($discord): void
    {
        if (isset($discord)) return;

        if (! $discord instanceof Discord) throw new \Exception('The Discord bot must be an instance of DiscordPHP', E_USER_ERROR);

        $this->discord = $discord;
    }
    
    /**
     * Sends a message to Discord channels that are configured for the current Twitch channel.
     *
     * @param string $payload The message to send to Discord.
     *
     * @return bool Returns true if the message was sent successfully, false otherwise.
     */
    public function discordRelay(string $payload): bool
    {
        if (! $this->discord_output || ! isset($this->discord)) return false;
        if (empty($this->channels)) return false;
        if (! isset($this->channels[$this->lastchannel])) return false;
        if ($this->verbose) $this->logger->info('[DISCORD CHAT RELAY]');
        foreach ($this->channels[$this->lastchannel] as $guild_id => $channel_id) {
            if (! $guild = $this->discord->guilds->get('id', $guild_id)) continue;
            if (! $channel = $guild->channels->get('id', $channel_id)) continue;
            $channel->sendMessage($payload);
        }
        return true;
    }

    /**
     * Gets the cache configuration.
     *
     * @param string $repository_class Repository class name.
     *
     * @return ?CacheConfig
     */
    public function getCacheConfig($repository_class = AbstractRepository::class)
    {
        if (! array_key_exists($repository_class, $this->cacheConfig)) {
            $repository_class = AbstractRepository::class;
        }

        return $this->cacheConfig[$repository_class];
    }

    /**
     * Handles dynamic get calls to the client.
     *
     * @param string $name Variable name.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        $allowed = ['loop', 'options', 'logger', 'http', 'application_commands'];

        if (in_array($name, $allowed)) {
            return $this->{$name};
        }

        if (null === $this->client) {
            return;
        }

        return $this->client->{$name};
    }

    /**
     * Handles dynamic set calls to the client.
     *
     * @param string $name  Variable name.
     * @param mixed  $value Value to set.
     */
    public function __set(string $name, $value): void
    {
        if (null === $this->client) {
            return;
        }

        $this->client->{$name} = $value;
    }
}