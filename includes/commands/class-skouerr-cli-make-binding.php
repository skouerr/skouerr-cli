<?php

/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_Make_Binding class handles the creation of a new block binding
 * using WP-CLI (WordPress Command Line Interface).
 */

class Skouerr_CLI_Make_Binding {



	public string $block;
	public string $title;
	public string $name;
	public string $icon;

	/**
	 * Make Variation
	 */
	public function make_binding( $args, $assoc_args ) {
		
		if ( isset( $assoc_args['title'] ) ) {
			$this->title = $assoc_args['title'];
		} else {
			$this->title = SK_CLI_Input::ask( __( 'Enter the title of the binding:' ) );
		}

		if ( isset( $assoc_args['name'] ) ) {
			$this->name = $assoc_args['name'];
		} else {
			$this->name = SK_CLI_Input::ask( __( 'Enter the name of the binding:' ) );
		}

		$plugin_path = dirname( __DIR__, 2 );
		$source = $plugin_path . '/templates/binding/default';
		$destination = get_template_directory() . '/bindings/'.$this->name.'/' . $this->name . '.php';
		mkdir( get_template_directory() . '/bindings/'.$this->name );

		copy( $source, $destination );

		$content = file_get_contents( $destination );
		$content = str_replace( '%SK_BINDING_TITLE%', $this->title, $content );
		$content = str_replace( '%SK_BINDING_NAME%', $this->name, $content );
		file_put_contents( $destination, $content );

		WP_CLI::success( 'Block Binding ' . $this->name . ' created successfully.' );
	}
}
