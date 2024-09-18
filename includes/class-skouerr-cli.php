<?php

/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * The Skouerr_CLI class is used to create a command-line interface (CLI) for the Skouerr theme.
 */
class Skouerr_CLI {




	private string $cli_name = 'skouerr';
	private array $commands = array();

	/**
	 * Skouerr_CLI constructor.
	 */
	public function __construct() {
		/*
		$this->register_command('list', array($this, 'list'), 'Liste des commandes', 'Affiche les commandes utilisées par le plugin starter-toolbox');
		$this->register_command('create-component', array($this, 'create_component'), 'Créer un composant', 'Créer un composant dans le thème Wordpress');
		$this->register_command('rename-component', array($this, 'rename_component'), 'Renome un composant', 'Renome un composant dans le thème Wordpress');
		$this->register_command('list-components', array($this, 'list_components'), 'Affiche les composants', 'Affiche les composants utilisés par le thème Wordpress');
		$this->register_command('delete-component', array($this, 'delete_component'), 'Supprime un composant', 'Supprime les fichiers liés a un composant');
		$this->register_command('create-cpt', array($this, 'create_cpt'), 'Créer un Custom Post Type', 'Créer un Custom Post Type dans le thème Wordpress');
		$this->register_command('migrate-block', array($this, 'migrate_block'), 'Migrate block', 'Migrate block register in function to json file');
		$this->register_command('create-theme', array($this, 'create_theme'), 'Créer un thème', 'Créer un thème wordpress depuis le starter theme');
		*/

		$this->register_command( 'list', array( $this, 'list_command' ), __( 'List of commands' ), __( 'Display the commands used by Skouerr cli' ) );

		$skouerr_cli_make_theme = new Skouerr_CLI_Make_Theme();
		$this->register_command( 'make:theme', array( $skouerr_cli_make_theme, 'make_theme' ), __( 'Make a theme' ), __( 'Create a new theme in themes directory' ) );

		$skouerr_cli_make_block = new Skouerr_CLI_Make_Block();
		$this->register_command( 'make:block', array( $skouerr_cli_make_block, 'make_block' ), __( 'Make a block' ), __( 'Create a new block in the theme' ) );

		$skouerr_cli_make_post_type = new Skouerr_CLI_Make_Post_Type();
		$this->register_command( 'make:post-type', array( $skouerr_cli_make_post_type, 'make_post_type' ), __( 'Make a post type' ), __( 'Create a new post type in the theme' ) );

		$skouerr_cli_make_template = new Skouerr_CLI_Make_Template();
		$this->register_command( 'make:template', array( $skouerr_cli_make_template, 'make_template' ), __( 'Make a template' ), __( 'Create a new template in the theme' ) );

		$skouerr_cli_make_variation = new Skouerr_CLI_Make_Variation();
		$this->register_command( 'make:variation', array( $skouerr_cli_make_variation, 'make_variation' ), __( 'Make a variation' ), __( 'Create a new variation in the theme' ) );

		$skouerr_cli_list_blocks = new Skouerr_CLI_List_Blocks();
		$this->register_command( 'list:blocks', array( $skouerr_cli_list_blocks, 'list_blocks' ), __( 'List blocks' ), __( 'List all blocks in the theme' ) );

		$skouerr_cli_import_block = new Skouerr_CLI_Import_Block();
		$this->register_command( 'import:block', array( $skouerr_cli_import_block, 'import_block' ), __( 'Import block' ), __( 'Import a block in the theme' ) );

		$skouerr_cli_save_template = new Skouerr_CLI_Save_Template();
		$this->register_command( 'save:template', array( $skouerr_cli_save_template, 'save_template' ), __( 'Save a template' ), __( 'Save a template in the theme' ) );

		$skouerr_cli_save_pattern = new Skouerr_CLI_Save_Pattern();
		$this->register_command( 'save:pattern', array( $skouerr_cli_save_pattern, 'save_pattern' ), __( 'Save a pattern' ), __( 'Save a pattern in the theme' ) );
	}

	/**
	 * Registers a command with a given name, callback function, title, and description.
	 *
	 * @param string   $name A string that represents the name
	 *   of the command. It is used to identify the command when it is called.
	 * @param callable $callback A callable function or method that will be
	 * executed when the command is called. It can be a closure, a function name,
	 * or an array containing an object and a method name.
	 * @param string   $title An optional string that represents the title or name
	 *   of the command. If no title is provided, the command name will be used as
	 *   the title.
	 * @param string   $description A string that provides a brief description or
	 *   explanation of what the command does. It is an optional parameter, so if
	 *   no description is provided, it will be an empty string.
	 */
	public function register_command( string $name, callable $callback, string $title = '', string $description = '' ) {
		$this->commands[ $name ] = array(
			'callback' => $callback,
			'name' => $name,
			'title' => empty( $title ) ? $name : $title,
			'description' => $description,
		);
		WP_CLI::add_command( $this->cli_name . ' ' . $name, $callback );
	}

	/**
	 * Lists the commands used by the plugin "starter-toolbox" in a formatted table.
	 *
	 * @throws Exception If the $cli_name or $commands is null.
	 */
	public function list_command() {
		$commands = $this->commands;
		$cli_name = $this->cli_name;
		try {
			// Check if $cli_name or $commands is null.
			if ( null === $cli_name || null === $commands ) {
				throw new Exception( 'Invalid parameters. $cli_name and $commands cannot be null.' );
			}

			$data = self::format_commands_data( $cli_name, $commands );
			$headers = array( 'Command name', 'Title', 'Description' );

			WP_CLI::line( 'List of commands used by the skouerr cli plugin' );
			self::output_table( $data, $headers );
		} catch ( Exception $e ) {
			WP_CLI::error( 'An error occurred while retrieving commands:' . $e->getMessage() );
		}
	}

	/**
	 * Takes a CLI name and an array of commands, and returns a formatted array
	 * of data containing the command name, title, and description.
	 *
	 * @param string $cli_name A string representing the name of the command-line
	 * interface (CLI) tool. It is used to generate the full name of each command
	 * by concatenating it with the command's name.
	 * @param array  $commands An array of commands, where each command is represented by
	 *  an associative array with keys 'name', 'title', and 'description'.
	 *
	 * @return array An array of formatted data for each command.
	 */
	private static function format_commands_data( $cli_name, $commands ) {
		$data = array();
		foreach ( $commands as $command ) {
			$data[] = array(
				'Command name' => $cli_name . ' ' . $command['name'],
				'Title' => $command['title'],
				'Description' => $command['description'],
			);
		}
		return $data;
	}

	/**
	 * Outputs a table format of data with specified headers.
	 *
	 * @param array $data An array of data that you want to display in a table format.
	 * Each element in the array represents a row in the table, and the values
	 * within each element represent the columns of that row.
	 * @param array $headers An array of strings representing the column headers of the
	 * table.
	 */
	private static function output_table( $data, $headers ) {
		WP_CLI\Utils\format_items( 'table', $data, $headers );
	}
}
