<?php

/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_Import_Block class handles the import functionality
 * for blocks using WP-CLI (WordPress Command Line Interface).
 * Currently, the import functionality is not yet implemented, and
 * it outputs a warning message when invoked.
 */
class Skouerr_CLI_Import_Block {




	public array $libraries;
	public string $library;
	/**
	 * Constructor method for Skouerr_CLI_Import_Block.
	 * Currently, it doesn't perform any operations.
	 */
	public function __construct() {
		$this->libraries = apply_filters(
			'skouerr_import_libraries',
			array(
				'http://localhost',
			)
		);
	}

	/**
	 * Method to import a block.
	 * This function currently displays a warning message indicating
	 * that the command is not yet implemented.
	 */
	public function import_block( $args, $assoc_args ) {

		if ( isset( $assoc_args['library'] ) ) {
			$this->library = $assoc_args['library'];
		}
		if ( count( $this->libraries ) > 1 ) {
			if ( $assoc_args['library'] ) {
				$this->library = $assoc_args['library'];
			} else {
				$this->library = SK_CLI_Input::select( 'Select a skouerr library', $this->libraries );
			}
		} else {
			$this->library = $this->libraries[0];
		}

		if ( ! isset( $assoc_args['name'] ) && ! isset( $assoc_args['type'] ) ) {
			$type = SK_CLI_Input::select( __( 'What type of item do you want to import?' ), array( 'block', 'pattern', 'template' ) );

			if ( $type === 'block' ) {
				$this->import_block_type();
			}

			if ( $type === 'pattern' ) {
				$this->import_pattern();
			}

			if ( $type === 'template' ) {
				$this->import_template();
			}
		} else {
			$this->download_item( $assoc_args['type'], $assoc_args['name'] );
		}
	}


	/**
	 * Method to import a block type.
	 */
	public function import_block_type() {
		$items = $this->fetch_items( 'blocks' );
		$items_name = array_map(
			function ( $item ) {
				return $item['name'];
			},
			$items
		);

		$block_name = SK_CLI_Input::select( 'Select a block to import', $items_name );
		SK_CLI_Input::confirm( 'Do you want to import ' . $block_name . ' block?' );
		$this->download_item( 'blocks', $block_name );
	}

	/**
	 * Method to import a pattern.
	 */
	public function import_pattern() {
		$items = $this->fetch_items( 'patterns' );
		$items_name = array_map(
			function ( $item ) {
				return $item['name'];
			},
			$items
		);

		$pattern_name = SK_CLI_Input::select( 'Select a pattern to import', $items_name );
		SK_CLI_Input::confirm( 'Do you want to import ' . $pattern_name . ' pattern?' );
		$this->download_item( 'patterns', $pattern_name, false );
	}

	/**
	 * Method to import a template.
	 */
	public function import_template() {
		$items = $this->fetch_items( 'templates' );
		$items_name = array_map(
			function ( $item ) {
				return $item['name'];
			},
			$items
		);

		$template_name = SK_CLI_Input::select( 'Select a template to import', $items_name );
		SK_CLI_Input::confirm( 'Do you want to import ' . $template_name . ' template?' );
		$this->download_item( 'templates', $template_name, false );
	}

	/**
	 * Method to fetch items from the library.
	 */
	public function fetch_items( $type ) {
		$response = wp_remote_get( $this->library . '/wp-json/skouerr/v1/library/' . $type );
		$items = json_decode( wp_remote_retrieve_body( $response ), true );
		return $items;
	}

	/**
	 * Method to download an item from the library.
	 */
	public function download_item( $type, $name, $folder = true ) {
		WP_CLI::line( 'Downloading ' . $name . '...' );
		// Get zip.
		$response = wp_remote_get( $this->library . '/wp-json/skouerr/v1/library/' . $type . '/' . $name );
		$zip_file = wp_remote_retrieve_body( $response );

		// Save zip.
		$zip_path = WP_CONTENT_DIR . '/uploads/' . $name . '.zip';
		file_put_contents( $zip_path, $zip_file );

		$zip = new ZipArchive();
		$res = $zip->open( $zip_path );
		if ( $res === true ) {
			if ( $folder ) {
				$zip->extractTo( get_template_directory() . '/' . $type . '/' . $name );
			} else {
				$zip->extractTo( get_template_directory() . '/' . $type );
			}
			$zip->close();
		} else {
			WP_CLI::error( 'Error unzipping item' );
		}

		unlink( $zip_path );
		WP_CLI::success( $name . ' ' . $type . ' imported' );
	}
}
