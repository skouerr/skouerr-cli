<?php

class Skouerr_CLI_Plugin
{
	public function __construct()
	{
		// Add CLI commands.
		if (defined('WP_CLI')) {
			$cli = new Skouerr_CLI();
		}
	}
}
