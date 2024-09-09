<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_List_Blocks class handles the listing of blocks using
 * WP-CLI (WordPress Command Line Interface). It retrieves block data
 * and displays it in a tabular format.
 */
class Skouerr_CLI_List_Blocks {


	/**
	 * Lists all blocks by retrieving their data and displaying it
	 * in a table format using WP-CLI.
	 */
	public function list_blocks() {
		$blocks = $this->get_blocks();
		$headers = array( 'title', 'name', 'category', 'icon' );
		$data = array_map(
			function ( $block ) {
				return array(
					'name' => $block->name,
					'title' => $block->title,
					'category' => $block->category,
					'icon' => $block->icon,
				);
			},
			$blocks
		);

		WP_CLI\Utils\format_items( 'table', $data, $headers );
		die();
	}

	/**
	 * Retrieves all blocks by loading block data from JSON files.
	 * It requires the Skouerr_Loader class to load and decode block data.
	 *
	 * @return array An array of block objects.
	 */
	public function get_blocks() {
		$blocks = array();
		if ( ! class_exists( 'Skouerr_Loader' ) ) {
			require get_template_directory() . '/inc/core/loader.php';
		}
		$loader = new Skouerr_Loader();
		foreach ( $loader->get_blocks() as $block ) {
			$block_json = file_get_contents( $block );
			$blocks[] = json_decode( $block_json );
		}

		return $blocks;
	}
}
