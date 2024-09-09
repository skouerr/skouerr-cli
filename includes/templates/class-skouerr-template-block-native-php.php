<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_Template_Block_Native_PHP class extends Skouerr_Template_Block
 * to handle the creation of native PHP blocks. This class manages the setup
 * of block files such as controller, PHP template, block.json, style, script,
 * and register files, renaming them and moving them into the correct folders.
 */
class Skouerr_Template_Block_Native_PHP extends Skouerr_Template_Block {


	/**
	 * Constructor that initializes the block and immediately calls
	 * the create_block method to handle the block creation process.
	 *
	 * @param array $block_data The data for the block, passed to the parent constructor.
	 */
	public function __construct( $block_data ) {
		parent::__construct( $block_data );
		$this->create_block();
	}

	/**
	 * Main method to handle the entire block creation process.
	 * It performs the following steps:
	 * - Removes any existing temporary folder
	 * - Creates a new temporary folder
	 * - Creates a block folder in the theme's block directory
	 * - Copies template files into the temporary folder
	 * - Renames important files (controller, PHP template, block, style, script, register)
	 * - Replaces placeholders in the template files with actual block data
	 * - Moves the final files into the block folder
	 * - Removes the temporary folder after the process is complete
	 */
	public function create_block() {
		$this->remove_tmp_folder();
		$this->make_tmp_folder();
		$this->make_block_folder();
		$files = $this->copy_files_in_tmp();

		foreach ( $files as $file ) {
			$name = basename( $file );
			if ( $name == 'controller' ) {
				$this->rename_file( $file, $name, $this->name . '.controller.php' );
			}

			if ( $name == 'template' ) {
				$this->rename_file( $file, $name, $this->name . '.template.php' );
			}

			if ( $name == 'block' ) {
				$this->rename_file( $file, $name, $this->name . '.block.json' );
			}

			if ( $name == 'style' ) {
				$this->rename_file( $file, $name, $this->name . '.style.scss' );
			}

			if ( $name == 'script' ) {
				$this->rename_file( $file, $name, $this->name . '.script.js' );
			}

			if ( $name == 'register' ) {
				$this->rename_file( $file, $name, $this->name . '.register.js' );
			}
		}

		$this->set_values();
		$this->move_files_to_block_folder();
		$this->remove_tmp_folder();
	}
}
