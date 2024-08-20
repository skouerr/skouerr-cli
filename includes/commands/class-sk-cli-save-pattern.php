<?php

class Skouerr_CLI_Save_Pattern
{

    public function __construct() {}

    public function save_pattern()
    {
        $pattern = $this->select_pattern();
        $this->save_locale_pattern($pattern);
        $this->delete_in_database($pattern);
        WP_CLI::success('Pattern ' . $pattern->post_title . ' saved');
    }

    public function select_pattern()
    {
        $patterns = $this->get_patterns_in_posts();

        $patterns_for_select = array();
        foreach ($patterns as $pattern) {
            $patterns_for_select[$pattern->ID] = $pattern->post_title;
        }

        if (empty($patterns_for_select)) {
            WP_CLI::error('No patterns in database found');
        }
        $pattern_name = SK_CLI_Input::select('Select a pattern', $patterns_for_select);
        $pattern_id = array_search($pattern_name, $patterns_for_select);

        return get_post($pattern_id);
    }

    public function get_patterns_in_posts()
    {
        $patterns = get_posts(array(
            'post_type' => 'wp_block',
            'numberposts' => -1
        ));
        return $patterns;
    }

    public function save_locale_pattern($pattern)
    {
        $name = $pattern->post_name . '.php';
        $content = $pattern->post_content;

        ob_start();
        echo '<?php ' . PHP_EOL;
        echo '/**' . PHP_EOL;
        echo '* Title: ' . $pattern->post_title . PHP_EOL;
        echo '* Slug: skouerr/' . $pattern->post_name . PHP_EOL;
        echo '*/' . PHP_EOL;
        echo '?>' . PHP_EOL;
        echo $pattern->post_content;
        $content = ob_get_clean();

        file_put_contents(get_template_directory() . '/patterns/' . $name, $content);
    }

    public function delete_in_database($pattern)
    {
        wp_delete_post($pattern->ID, true);
    }
}
