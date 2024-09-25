<?php
/**
 * Plugin Name:  Skouerr CLI
 * Plugin URI:   https://skouerr.dev
 * Description:  CLI for Skouerr
 * Version:      1.0.0
 * Author:       R2
 * Author URI:   https://r2.fr
 *
 * @package Starter_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

// Plugin path.
define( 'SKOUERR_CLI_PATH', __DIR__ );

// Include constants.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

// Include functions.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

// Include loader.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'loader.php';

// ======================================================================
// = All plugin initialization is done in Skouerr_CLI __constructor
// ======================================================================

// Load the plugin text domain for translation.
function skouerr_cli_load_textdomain() {
	load_plugin_textdomain( 'skouerr-cli', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}
add_action( 'plugins_loaded', 'skouerr_cli_load_textdomain' );

// Load plugin after the theme to make sure all translations are loaded.
function skouerr_cli_init_after_theme() {
	$sk_cli = new Skouerr_CLI_Plugin();
}
add_action( 'after_setup_theme', 'skouerr_cli_init_after_theme', 20 );
