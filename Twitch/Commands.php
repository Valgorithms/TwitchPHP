<?php

/*
 * This file is apart of the TwitchPHP project.
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
	public function handle(string $command): ?string
	{
		if ($this->verbose) $this->twitch->emit("[HANDLE COMMAND] `$command`");
		if ($command == 'help')
		{
			$commands = '';
			
			$responses = $this->twitch->getResponses();
			$functions = $this->twitch->getFunctions();
			$restricted_functions = $this->twitch->getRestrictedFunctions();
			$private_functions = $this->twitch->getPrivateFunctions();
			
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
		
		return $response;
	}
}
?>