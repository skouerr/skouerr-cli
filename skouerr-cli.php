<?php
/**
 * Plugin Name:       Skouerr CLI
 * Plugin URI:        https://skouerr.dev
 * Description:       WP-CLI commands and abilities to scaffold and maintain Skouerr FSE themes. Agent-friendly: JSON output, non-interactive mode, dry-run, Abilities API.
 * Version:           2.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            R2
 * Author URI:        https://r2.fr
 * License:           GPL-2.0-or-later
 *
 * @package Skouerr_CLI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKOUERR_CLI_VERSION', '2.1.0' );
define( 'SKOUERR_CLI_PATH', __DIR__ );

// Both URLs can be overridden in wp-config.php.
if ( ! defined( 'SKOUERR_REMOTE_DOMAIN' ) ) {
	define( 'SKOUERR_REMOTE_DOMAIN', 'https://download.skouerr.dev' );
}
if ( ! defined( 'SKOUERR_REMOTE_THEME_URL' ) ) {
	define( 'SKOUERR_REMOTE_THEME_URL', SKOUERR_REMOTE_DOMAIN . '/wp-json/skouerr/v1/download/theme' );
}

// @phpstan-ignore smaller.alwaysFalse (checked at runtime on older PHP versions)
if ( PHP_VERSION_ID < 80300 ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::warning( 'Skouerr CLI requires PHP 8.3 or later. Current version: ' . PHP_VERSION . '.' );
	}
	return;
}

require_once __DIR__ . '/src/autoload.php';

// Human messages are translated (languages/). JSON output stays in English.
load_textdomain( 'skouerr-cli', __DIR__ . '/languages/skouerr-cli-' . determine_locale() . '.mo' );

// Abilities API (WordPress 6.9+): the commands are also available to REST and MCP clients.
Skouerr\Cli\Abilities::register();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/src/Compat/legacy.php';
	Skouerr\Cli\Plugin::register();
}
