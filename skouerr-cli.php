<?php

/**
 * Plugin Name:  Skouerr CLI
 * Plugin URI:   https://skouerr.dev
 * Description:  CLI for Skouerr
 * Version:      1.0.3
 * Author:       R2
 * Author URI:   https://r2.fr
 *
 * @package Skouerr_CLI
 */

if (! defined('ABSPATH')) {
	die('Kangaroos cannot jump here');
}

// Plugin path.
define('SKOUERR_CLI_PATH', __DIR__);

// Include constants.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

// Include functions.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

// Include loader.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'loader.php';

// ======================================================================
// = All plugin initialization is done in Skouerr_CLI __constructor
// ======================================================================
$sk_cli = new Skouerr_CLI_Plugin();
