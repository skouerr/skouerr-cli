<?php

/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_Make_Variation class handles the creation of a new variation
 * using WP-CLI (WordPress Command Line Interface). It interacts with
 * the user to gather variation details, copies the default variation template
 * to the theme's variations directory, and replaces the placeholders with the
 * user-provided values.
 */
class Skouerr_CLI_Make_Variation {



	public string $block;
	public string $title;
	public string $name;
	public string $icon;

	/**
	 * Make Variation
	 */
	public function make_variation( $args, $assoc_args ) {
		if ( isset( $assoc_args['block'] ) ) {
			$this->block = $assoc_args['block'];
		} else {
			$this->block = SK_CLI_Input::ask( __( 'Enter the block name to create a variation for:' ) );
		}

		if ( isset( $assoc_args['title'] ) ) {
			$this->title = $assoc_args['title'];
		} else {
			$this->title = SK_CLI_Input::ask( __( 'Enter the title of the variation:' ) );
		}

		if ( isset( $assoc_args['name'] ) ) {
			$this->name = $assoc_args['name'];
		} else {
			$this->name = SK_CLI_Input::ask( __( 'Enter the name of the variation:' ) );
		}

		if ( isset( $assoc_args['icon'] ) ) {
			$this->icon = $assoc_args['icon'];
		} else {
			$this->icon = SK_CLI_Input::ask( __( 'Enter the icon of the variation:' ), 'block-default' );
		}

		$plugin_path = dirname( __DIR__, 2 );
		$source = $plugin_path . '/templates/variations/default';
		$destination = get_template_directory() . '/js/admin/variations/' . $this->name . '.js';

		copy( $source, $destination );

		$content = file_get_contents( $destination );
		$content = str_replace( '%SK_VARIATION_BLOCK%', $this->block, $content );
		$content = str_replace( '%SK_VARIATION_TITLE%', $this->title, $content );
		$content = str_replace( '%SK_VARIATION_NAME%', $this->name, $content );
		$content = str_replace( '%SK_VARIATION_ICON%', $this->icon, $content );
		file_put_contents( $destination, $content );

		WP_CLI::success( 'Variation ' . $this->name . ' created successfully.' );
	}
}
