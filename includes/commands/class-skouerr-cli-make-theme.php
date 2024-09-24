<?php

/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_Make_Theme class handles the creation of a new theme
 * using WP-CLI (WordPress Command Line Interface). It interacts with
 * the user to gather theme details, downloads the theme from a remote
 * source, unzips it, and switches to the new theme.
 */
class Skouerr_CLI_Make_Theme {





	/**
	 * Constructor method for Skouerr_CLI_Make_Theme.
	 * Currently, it doesn't perform any operations.
	 */
	public function __construct() {}

	/**
	 * Prompts the user for theme details, downloads the theme from a remote source,
	 * unzips the downloaded file, and activates the new theme. Outputs messages
	 * indicating the progress and success or failure of each operation.
	 */
	public function make_theme() {
		$title = SK_CLI_Input::ask(
            _x( 'Enter the title of the theme', 'Input of the command `wp skouerr make:theme`', 'skouerr-cli' ) )
            ?? _x('Theme', 'Default value for the title of the theme.', 'skouerr-cli');
		$name = SK_CLI_Input::ask(
            _x( 'Enter the name of the theme', 'Input of the command `wp skouerr make:theme`', 'skouerr-cli' ) )
            ?? _x('theme', 'Default value for the name of the theme.', 'skouerr-cli');
		$text_domain = SK_CLI_Input::ask(
            _x( 'Enter the text domain of the theme', 'Input of the command `wp skouerr make:theme`', 'skouerr-cli' ) )
            ?? _x('theme', 'Default value for the text domain of the theme.', 'skouerr-cli');

		try {
			WP_CLI::log( _x( 'Start Downloading theme ...', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );
			$zip_path = $this->download_remote_theme( $title, $name, $text_domain );
			WP_CLI::success( _x( 'Theme downloaded', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );
		} catch ( Exception $e ) {
			WP_CLI::error( _x( 'Error downloading theme', 'Log message of the command `wp skouerr make:theme`', 'skouerr-cli' ) );
		}

		WP_CLI::log( _x( 'Start unzipping theme ...', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );
		$this->unzip_theme( $zip_path, $name );
		WP_CLI::success( _x( 'Theme unzipped', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );

		WP_CLI::log( _x( 'Switching to theme ...', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );
		switch_theme( $name );
		WP_CLI::success(  _x( 'Theme switched', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );

		WP_CLI::log( _x( 'Install composer dependencies ...', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );
		$this->install_composer_dependencies( $name );
		WP_CLI::success( _x( 'Composer dependencies installed', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );

		WP_CLI::success( _x( 'Theme created successfully, enjoy!', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli' ) );
	}

	/**
	 * Downloads a theme from a remote source based on the provided details.
	 *
	 * @param string $title The title of the theme.
	 * @param string $name The name of the theme.
	 * @param string $text_domain The text domain of the theme.
	 * @return string The path to the downloaded ZIP file.
	 */
	public function download_remote_theme( $title, $name, $text_domain ) {
		try {
			$response = wp_remote_get(
				SKOUERR_REMOTE_THEME_URL,
				array(
					'body' => array(
						'title' => $title,
						'name' => $name,
						'text_domain' => $text_domain,
					),
					'headers' => array(
						'Content-Type' => 'application/json; charset=utf-8',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				WP_CLI::error( 'Error downloading theme' );
			}

			$content = wp_remote_retrieve_body( $response );
			file_put_contents( WP_CONTENT_DIR . '/themes/' . $name . '.zip', $content );
			return WP_CONTENT_DIR . '/themes/' . $name . '.zip';
		} catch ( Exception $e ) {
			WP_CLI::error(  _x( 'Error downloading theme', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli'  ) );
		}
	}

	/**
	 * Unzips the downloaded theme file to the specified directory.
	 *
	 * @param string $path The path to the ZIP file.
	 * @param string $name The name of the theme.
	 */
	public function unzip_theme( $path, $name ) {
		$zip = new ZipArchive();
		$res = $zip->open( $path );
		if ( $res === true ) {
			$zip->extractTo( WP_CONTENT_DIR . '/themes/' . $name );
			$zip->close();
		} else {
			WP_CLI::error( _x( 'Error unzipping theme', 'Log message of the command `wp skouerr make:theme`.', 'skouerr-cli'  ) );
		}
		unlink( $path );
	}

	/**
	 * Install composer dependencies for the theme.
	 *
	 * @param string $name The name of the theme.
	 */
	public function install_composer_dependencies( $name ) {
		exec( 'cd ' . WP_CONTENT_DIR . '/themes/' . $name . ' && composer install' );
	}
}
