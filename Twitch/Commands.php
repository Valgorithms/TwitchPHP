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
 
class Commands extends Twitch
{
	protected $twitch;
	protected $verbose;
	
	public function __construct(Twitch $twitch, bool $verbose)
    {
		$this->twitch = $twitch;
		$this->verbose = $verbose;
	}
	public function handle(string $command)
	{
		if ($this->verbose) $this->twitch->emit('[HANDLE]');
		
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