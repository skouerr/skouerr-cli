<?php

/**
 * Skouerr_CLI_Save_Pattern class manages the saving of a pattern
 * from the WordPress database to a file and then deletes the pattern
 * from the database. It uses WP-CLI (WordPress Command Line Interface)
 * for user interaction and file operations.
 */
class Skouerr_CLI_Save_Pattern
{

    /**
     * Constructor method for Skouerr_CLI_Save_Pattern.
     * Currently, it doesn't perform any operations.
     */
    public function __construct() {}

    /**
     * Prompts the user to select a pattern, saves the selected pattern
     * as a PHP file in the theme's patterns directory, and deletes the
     * pattern from the WordPress database. Outputs a success message
     * once the pattern is saved.
     */
    public function save_pattern()
    {
        $pattern = $this->select_pattern();
        $this->save_locale_pattern($pattern);
        $this->delete_in_database($pattern);
        WP_CLI::success('Pattern ' . $pattern->post_title . ' saved');
    }

    /**
     * Prompts the user to select a pattern from the available patterns
     * in the WordPress posts. Returns the selected pattern as a post object.
     *
     * @return WP_Post The selected pattern post object.
     */
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

    /**
     * Retrieves all patterns stored in WordPress posts of type 'wp_block'.
     *
     * @return array An array of WP_Post objects representing patterns.
     */
    public function get_patterns_in_posts()
    {
        $patterns = get_posts(array(
            'post_type' => 'wp_block',
            'numberposts' => -1
        ));
        return $patterns;
    }

    /**
     * Saves the pattern content to a PHP file in the theme's patterns directory.
     * The file name is based on the pattern's slug and includes a PHP comment
     * header with the pattern's title and slug.
     *
     * @param WP_Post $pattern The pattern post object to be saved.
     */
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

    /**
     * Deletes the pattern from the WordPress database.
     *
     * @param WP_Post $pattern The pattern post object to be deleted.
     */
    public function delete_in_database($pattern)
    {
        wp_delete_post($pattern->ID, true);
    }
}
