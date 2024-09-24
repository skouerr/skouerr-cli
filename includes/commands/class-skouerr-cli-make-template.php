<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

/**
 * Skouerr_CLI_Make_Template class handles the creation of a new
 * template file using WP-CLI (WordPress Command Line Interface).
 * It interacts with the user to gather the template name and generates
 * an empty HTML file for the new template.
 */
class Skouerr_CLI_Make_Template {


	/**
	 * Constructor method for Skouerr_CLI_Make_Template.
	 * Currently, it doesn't perform any operations.
	 */
	public function __construct() {}

	/**
	 * Prompts the user for the name of the template and creates
	 * an empty HTML file with that name in the templates directory.
	 * Outputs a success message once the template file is created.
	 */
	public function make_template() {
		$name = SK_CLI_Input::ask( _x( 'Enter the name of the template', 'Input of the command `wp skouerr make:template`', 'skouerr-cli' ) );
		file_put_contents( get_template_directory() . '/templates/' . $name . '.html', '' );
		WP_CLI::success( _x('Template created', 'Log of the command `wp skouerr make:template`', 'skouerr-cli') );
	}
}
