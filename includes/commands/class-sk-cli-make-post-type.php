<?php

/**
 * Skouerr_CLI_Make_Post_Type class handles the creation of a new
 * custom post type using WP-CLI (WordPress Command Line Interface).
 * It interacts with the user to gather necessary details and generates
 * a PHP file for the new post type.
 */
class Skouerr_CLI_Make_Post_Type
{

    /**
     * Constructor method for Skouerr_CLI_Make_Post_Type.
     * Currently, it doesn't perform any operations.
     */
    public function __construct() {}

    /**
     * Prompts the user for input and creates a new custom post type.
     * The method performs the following steps:
     * - Asks the user for the slug, label, domain, and icon for the post type
     * - Formats the icon and slug to lowercase and modifies them as necessary
     * - Creates a new directory for the post type and copies a template PHP file
     * - Replaces placeholders in the copied file with actual user-provided values
     * - Saves the modified file
     * - Outputs a success message indicating the post type creation status
     */
    public function make_post_type()
    {
        //WP_CLI::warning(__('This command is not implemented yet'));

        $slug = SK_CLI_Input::ask('Enter the slug of the post type');
        $label = SK_CLI_Input::ask('Enter the label of the post type');
        $domain = SK_CLI_Input::ask('Enter the domain of the post type', 'skouerr');
        $icon = SK_CLI_Input::ask('Enter the icon of the post type', 'dashicons-admin-post');

        $icon = strtolower($icon);
        $icon = str_replace('dashicons-', '', $icon);

        $slug = strtolower($slug);
        $label = ucfirst($label);
        $name = $slug;

        $plugin_path = dirname(__FILE__, 3);
        $source = $plugin_path . '/templates/post-type/post-type';

        mkdir(get_template_directory() . '/post-types/' . $slug);
        $destination = get_template_directory() . '/post-types/' . $slug . '/' . $slug . '.functions.php';
        copy($source, $destination);

        $content = file_get_contents($destination);
        $content = str_replace('%SK_PT_SLUG%', $slug, $content);
        $content = str_replace('%SK_PT_NAME%', $name, $content);
        $content = str_replace('%SK_PT_LABEL%', $label, $content);
        $content = str_replace('%SK_PT_DOMAIN%', $domain, $content);
        $content = str_replace('%SK_PT_ICON%', $icon, $content);
        file_put_contents($destination, $content);

        WP_CLI::success('Post type ' . $slug . ' created');
    }
}
