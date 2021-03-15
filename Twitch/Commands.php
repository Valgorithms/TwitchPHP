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
 
class Commands
{
	protected $twitch;
	protected $verbose;
	
	public function __construct(Twitch $twitch, bool $verbose)
	{
		$this->twitch = $twitch;
		$this->verbose = $verbose;
	}
	public function handle(string $command, ?array $args = []): ?string
	{
		if ($this->verbose) $this->twitch->emit("[HANDLE COMMAND] `$command`");
		echo '[ARGS] '; var_dump($args); echo PHP_EOL;
		if ($command == 'help')
		{
			$commandsymbol = $this->twitch->getCommandSymbol();
			$responses = $this->twitch->getResponses();
			$functions = $this->twitch->getFunctions();
			$restricted_functions = $this->twitch->getRestrictedFunctions();
			$private_functions = $this->twitch->getPrivateFunctions();
			
			$commands = '';
			if ($commandsymbol){
				$commands.= '[Command Prefix] ';
				foreach($commandsymbol as $symbol){
					$commands .= "$symbol, ";
				}
				$commands = substr($commands, 0, strlen($commands)-2) . " ";
			}
			if($responses || $functions){
				$commands .= '[Public] ';
				if($responses){
					foreach($responses as $command => $value){
						$commands .= "$command, ";
					}
					
				}
				if($responses){
					foreach($functions as $command){
						$commands .= "$command, ";
					}
				}
				$commands = substr($commands, 0, strlen($commands)-2) . " ";
			}
			if($restricted_functions){
				$commands .= '[Whitelisted] ';
				foreach($restricted_functions as $command){
					$commands .= "$command, ";
				}
				$commands = substr($commands, 0, strlen($commands)-2) . " ";
			}
			if($private_functions){			
				$commands .= '[Private] ';
				foreach($private_functions as $command){
					$commands .= "$command, ";
				}
				$commands = substr($commands, 0, strlen($commands)-2) . " ";
			}
			
			if ($this->verbose) $this->twitch->emit("[COMMANDS] `$commands`");
			return $commands;
		}
		
		if ($command == 'php')
		{
			if ($this->verbose) $this->twitch->emit('[PHP]');
			$response = 'Current PHP version: ' . phpversion();
		}

		if ($command == 'stop')
		{
			if ($this->verbose) $this->twitch->emit('[STOP]');
			$this->twitch->close();
		}
		
		if ($command == 'join')
		{
			if ($this->verbose) $this->twitch->emit('[JOIN]');
			$this->twitch->joinChannel($args[1]);
		}
		
		if ($command == 'leave')
		{
			if ($this->verbose) $this->twitch->emit('[PART]');
			$this->twitch->leaveChannel();
		}
		
		if ($command == 'so')
		{
			if ($this->verbose) $this->twitch->emit('[SO]');
			$this->twitch->sendMessage('Hey, go check out ' . $args[1] . '. They are good peeples! Pretty good. Pretty good!');
		}
		
		return $response;
	}
}
?>