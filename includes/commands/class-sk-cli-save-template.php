<?php

class Skouerr_CLI_Save_Template
{
    public function __construct() {}

    public function save_template()
    {
        $template = $this->select_template();
        $this->save_locale_template($template);
        WP_CLI::success('Template ' . $template->post_title . ' saved');
    }

    public function get_template_in_posts()
    {
        $templates = get_posts(array(
            'post_type' => 'wp_template',
            'numberposts' => -1
        ));

        return $templates;
    }

    public function  select_template()
    {
        $templates = $this->get_template_in_posts();

        $templates_for_select = array();
        foreach ($templates as $template) {
            $templates_for_select[$template->ID] = $template->post_title;
        }

        $template_name = SK_CLI_Input::select('Select a template', $templates_for_select);
        $template_id = array_search($template_name, $templates_for_select);

        return get_post($template_id);
    }

    public function save_locale_template($template)
    {
        $name = $template->post_name . '.html';
        $content = $template->post_content;
        file_put_contents(get_template_directory() . '/templates/' . $name, $content);
    }
}
