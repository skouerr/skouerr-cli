<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr CLI Plugin Class
 *
 * This class is responsible for initializing CLI commands related to the Skouerr plugin.
 * It checks if WP-CLI is defined and, if so, initializes the `Skouerr_CLI` class to
 * add CLI commands.
 *
 * @package Skouerr
 * @subpackage CLI
 * @version 1.0
 * @since 1.0
 */
class Skouerr_CLI_Plugin {

	/**
	 * Constructor for the Skouerr_CLI_Plugin class.
	 *
	 * Initializes the CLI commands for the Skouerr plugin if WP-CLI is defined.
	 * Creates an instance of the `Skouerr_CLI` class to register commands.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		// Add CLI commands.
		if ( defined( 'WP_CLI' ) ) {
			$cli = new Skouerr_CLI();
		}
	}
}
