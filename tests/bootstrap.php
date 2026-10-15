<?php
/**
 * PHPUnit bootstrap: plain PHP, no WordPress.
 *
 * @package Skouerr_CLI
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'SKOUERR_CLI_PATH' ) ) {
	define( 'SKOUERR_CLI_PATH', dirname( __DIR__ ) );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub: returns the text unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore,Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'unload_textdomain' ) ) {
	/**
	 * Text domain stub.
	 *
	 * @param string $domain Text domain.
	 * @return bool
	 */
	function unload_textdomain( $domain ) {
		unset( $domain );
		return true;
	}
}
