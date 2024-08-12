<?php

class Skouerr_CLI_Make_Post_Type
{
    public function __construct() {}

    public function make_post_type()
    {
        //WP_CLI::warning(__('This command is not implemented yet'));

        $slug = SK_CLI_Input::ask('Enter the slug of the post type');
        $label = SK_CLI_Input::ask('Enter the label of the post type');
        $domain = SK_CLI_Input::ask('Enter the domain of the post type') ?? 'skouerr';
        $icon = SK_CLI_Input::ask('Enter the icon of the post type') ?? 'dashicons-admin-post';

        $icon = strtolower($icon);
        $icon = str_replace('dashicons-', '', $icon);

        $slug = strtolower($slug);
        $label = ucfirst($label);
        $name = $slug;

        $plugin_path = dirname(__FILE__, 3);
        $source = $plugin_path . '/templates/post-type/post-type';

        $folder = get_template_directory() . '/post-types';
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
