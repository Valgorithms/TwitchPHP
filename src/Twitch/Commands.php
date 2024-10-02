<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

/**
 * Provides an easy way to have triggerable commands.
 */
 
/**
 * Commands class represents a collection of Twitch chat commands.
 *
 * @package Twitch
 */
class Commands
{
    protected Twitch $twitch;
    protected bool $verbose;
    protected bool $debug;

    /**
     * Constructor for the Commands class.
     *
     * @param Twitch $twitch The Twitch object to use for API requests.
     * @param bool|null $verbose Whether to output verbose logging information.
     * @param bool|null $debug Whether to output debug logging information.
     */
    public function __construct(Twitch $twitch, ?bool $verbose = false, ?bool $debug = false)
    {
        $this->twitch = $twitch;
        $this->verbose = $verbose;
        $this->debug = $debug;
    }

    /**
     * Handles a Twitch chat command.
     *
     * @param string $command The command to handle.
     * @param array|null $args An optional array of arguments for the command.
     * @return string|null The response to the command, if any.
     */
    public function handle(array $args = []): ?string
    {
        if ($this->verbose) {
            $this->twitch->logger->info("[HANDLE COMMAND] `$args[0]`");
            $this->twitch->logger->info('[ARGS] ' . implode(', ', $args));
        }
        
        if ($this->debug) {
            $i = 0;
            foreach ($args as $arg) {
                $args[$i] = preg_replace('/[^A-Za-z0-9\-]/', '', trim($arg));
                $i++;
            }
            unset($i);
        }
        
        if ($args[0] == 'help')
        {
            $commandsymbol = $this->twitch->getCommandSymbol();
            $responses = $this->twitch->getResponses();
            $functions = $this->twitch->getFunctions();
            $restricted_functions = $this->twitch->getRestrictedFunctions();
            $private_functions = $this->twitch->getPrivateFunctions();
            
            $commands = '';
            if ($commandsymbol) {
                $commands .= '[Command Prefix] ';
                foreach($commandsymbol as $symbol) $commands .= "$symbol, ";
                $commands = substr($commands, 0, strlen($commands)-2) . " ";
            }
            if ($responses || $functions) {
                $commands .= '[Public] ';
                if ($responses) {
                    foreach(array_keys($responses) as $command) $commands .= "$command, ";
                    foreach($functions as $command) $commands .= "$command, ";
                }
                $commands = substr($commands, 0, strlen($commands)-2) . " ";
            }
            if ($restricted_functions) {
                $commands .= '[Whitelisted] ';
                foreach($restricted_functions as $command) $commands .= "$command, ";
                $commands = substr($commands, 0, strlen($commands)-2) . " ";
            }
            if ($private_functions) {            
                $commands .= '[Private] ';
                foreach($private_functions as $command) $commands .= "$command, ";
                $commands = substr($commands, 0, strlen($commands)-2) . " ";
            }
            
            if ($this->verbose) $this->twitch->logger->info("[COMMANDS] `$commands`");
            return $commands;
        }
        
        if ($args[0] == 'php')
        {
            if ($this->verbose) $this->twitch->logger->info('[PHP]');
            $response = 'Current PHP version: ' . phpversion();
        }

        if ($args[0] == 'stop')
        {
            if ($this->verbose) $this->twitch->logger->info('[STOP]');
            $this->twitch->close();
        }
        
        
        
        if ($args[0] == 'leave')
        {
            if ($this->verbose) $this->twitch->logger->info('[PART]');
            $this->twitch->leaveChannel($this->twitch->getLastChannel());
        }
        
        

        if (! $args[1]) return $response ?? null;

        if ($args[0] == 'join')
        {
            if ($this->verbose) $this->twitch->logger->info('[JOIN]' . $args[1]);
            $this->twitch->joinChannel($args[1]);
            return null;
        }

        if ($args[0] == 'so')
        {
            if ($this->verbose) $this->twitch->logger->info('[SO] ' . $args[1]);
            if (! $args[1]) return null;
            $this->twitch->sendMessage("Hey, go check out {$args[1]} at https://www.twitch.tv/{$args[1]} They are good peeples! Pretty good. Pretty good!");
            return null;
        }

        if ($args[0] == 'join')
        {
            if ($this->verbose) $this->twitch->logger->info('[JOIN]' . $args[1]);
            $this->twitch->joinChannel($args[1]);
        }
        return $response ?? null;
    }
}
?>