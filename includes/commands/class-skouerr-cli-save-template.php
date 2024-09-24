<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_Save_Template class manages the saving of a template
 * from the WordPress database to a file and then deletes the template
 * from the database. It uses WP-CLI (WordPress Command Line Interface)
 * for user interaction and file operations.
 */
class Skouerr_CLI_Save_Template {


	/**
	 * Constructor method for Skouerr_CLI_Save_Template.
	 * Currently, it doesn't perform any operations.
	 */
	public function __construct() {}

	/**
	 * Prompts the user to select a template, saves the selected template
	 * as an HTML file in the theme's templates directory, and deletes the
	 * template from the WordPress database. Outputs a success message
	 * once the template is saved.
	 */
	public function save_template() {
		$template = $this->select_template();
		$this->save_locale_template( $template );
		$this->delete_in_database( $template );
		WP_CLI::success( sprintf( _x('Template %s saved', 'Log of the command `wp skouerr save:template`. %s correspond to the template.', 'skouerr-cli'), $template->post_title) );
}

	/**
	 * Retrieves all templates stored in WordPress posts of type 'wp_template'.
	 *
	 * @return array An array of WP_Post objects representing templates.
	 */
	public function get_template_in_posts() {
		$templates = get_posts(
			array(
				'post_type' => 'wp_template',
				'numberposts' => -1,
			)
		);

		return $templates;
	}

	/**
	 * Prompts the user to select a template from the available templates
	 * in the WordPress posts. Returns the selected template as a post object.
	 *
	 * @return WP_Post The selected template post object.
	 */
	public function select_template() {
		$templates = $this->get_template_in_posts();
		$nb_templates = count($templates);

		if ($nb_templates === 0) {
			WP_CLI::error( _x('No template found', 'Log message for the command `wp skouerr save:template`', 'skouerr-cli') );
		}

		$templates_for_select = array();
		foreach ( $templates as $template ) {
			$templates_for_select[ $template->ID ] = $template->post_title;
		}

		$template_name = SK_CLI_Input::select( _x('Select a template', 'Input of the command `wp skouerr save:template`', 'skouerr-cli'), $templates_for_select );
		$template_id = array_search( $template_name, $templates_for_select );

		return get_post( $template_id );
	}

	/**
	 * Saves the template content to an HTML file in the theme's templates directory.
	 * The file name is based on the template's slug.
	 *
	 * @param WP_Post $template The template post object to be saved.
	 */
	public function save_locale_template( $template ) {
		$name = $template->post_name . '.html';
		$content = $template->post_content;
		file_put_contents( get_template_directory() . '/templates/' . $name, $content );
	}

	/**
	 * Deletes the template from the WordPress database.
	 *
	 * @param WP_Post $template The template post object to be deleted.
	 */
	public function delete_in_database( $template ) {
		wp_delete_post( $template->ID, true );
	}
}
