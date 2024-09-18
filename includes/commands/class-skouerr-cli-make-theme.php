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
	public function make_theme( $args, $assoc_args ) {

		if ( ! isset( $assoc_args['title'] ) ) {
			$title = SK_CLI_Input::ask( __( 'Enter the title of the theme' ) ) ?? 'Theme';
		} else {
			$title = $assoc_args['title'];
		}

		if ( ! isset( $assoc_args['name'] ) ) {
			$name = SK_CLI_Input::ask( __( 'Enter the name of the theme' ) ) ?? 'theme';
		} else {
			$name = $assoc_args['name'];
		}

		if ( ! isset( $assoc_args['text_domain'] ) ) {
			$text_domain = SK_CLI_Input::ask( __( 'Enter the text domain of the theme' ) ) ?? 'theme';
		} else {
			$text_domain = $assoc_args['text_domain'];
		}

		try {
			WP_CLI::log( __( 'Start Downloading theme ...' ) );
			$zip_path = $this->download_remote_theme( $title, $name, $text_domain );
			WP_CLI::success( __( 'Theme downloaded' ) );
		} catch ( Exception $e ) {
			WP_CLI::error( __( 'Error downloading theme' ) );
		}

		WP_CLI::log( __( 'Start unzipping theme ...' ) );
		$this->unzip_theme( $zip_path, $name );
		WP_CLI::success( __( 'Theme unzipped' ) );

		WP_CLI::log( __( 'Switching to theme ...' ) );
		switch_theme( $name );
		WP_CLI::success( __( 'Theme switched' ) );

		WP_CLI::log( __( 'Install composer dependencies ...' ) );
		$this->install_composer_dependencies( $name );
		WP_CLI::success( __( 'Composer dependencies installed' ) );

		// Setup with theme command.
		WP_CLI::runcommand( 'skouerr-theme setup' );

		WP_CLI::success( __( 'Theme created successfully ! Good luck !' ) );
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
			WP_CLI::error( 'Error downloading theme' );
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
			WP_CLI::error( 'Error unzipping theme' );
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
