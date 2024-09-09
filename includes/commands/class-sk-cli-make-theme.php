<?php

/**
 * Skouerr_CLI_Make_Theme class handles the creation of a new theme
 * using WP-CLI (WordPress Command Line Interface). It interacts with
 * the user to gather theme details, downloads the theme from a remote
 * source, unzips it, and switches to the new theme.
 */
class Skouerr_CLI_Make_Theme
{

    /**
     * Constructor method for Skouerr_CLI_Make_Theme.
     * Currently, it doesn't perform any operations.
     */
    public function __construct() {}

    /**
     * Prompts the user for theme details, downloads the theme from a remote source,
     * unzips the downloaded file, and activates the new theme. Outputs messages
     * indicating the progress and success or failure of each operation.
     */
    public function make_theme()
    {
        $title = SK_CLI_Input::ask(__('Enter the title of the theme')) ?? 'Theme';
        $name = SK_CLI_Input::ask(__('Enter the name of the theme')) ?? 'theme';
        $text_domain = SK_CLI_Input::ask(__('Enter the text domain of the theme')) ?? 'theme';

        try {
            WP_CLI::log(__('Start Downloading theme ...'));
            $zipPath = $this->download_remote_theme($title, $name, $text_domain);
            WP_CLI::success(__('Theme downloaded'));
        } catch (Exception $e) {
            WP_CLI::error(__('Error downloading theme'));
        }

        WP_CLI::log(__('Start unzipping theme ...'));
        $this->unzip_theme($zipPath, $name);
        WP_CLI::success(__('Theme unzipped'));

        WP_CLI::log(__('Switching to theme ...'));
        switch_theme($name);
        WP_CLI::success(__('Theme switched'));

        WP_CLI::success(__('Theme created successfully, enjoy!'));
    }

    /**
     * Downloads a theme from a remote source based on the provided details.
     *
     * @param string $title The title of the theme.
     * @param string $name The name of the theme.
     * @param string $text_domain The text domain of the theme.
     * @return string The path to the downloaded ZIP file.
     */
    public function download_remote_theme($title, $name, $text_domain)
    {
        try {
            $response = wp_remote_get(SKOUERR_REMOTE_THEME_URL, array(
                'body' => array(
                    'title' => $title,
                    'name' => $name,
                    'text_domain' => $text_domain
                ),
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8'
                )
            ));

            if (is_wp_error($response)) {
                WP_CLI::error('Error downloading theme');
            }

            $content = wp_remote_retrieve_body($response);
            file_put_contents(WP_CONTENT_DIR . '/themes/' . $name . '.zip', $content);
            return WP_CONTENT_DIR . '/themes/' . $name . '.zip';
        } catch (Exception $e) {
            WP_CLI::error('Error downloading theme');
        }
    }

    /**
     * Unzips the downloaded theme file to the specified directory.
     *
     * @param string $path The path to the ZIP file.
     * @param string $name The name of the theme.
     */
    public function unzip_theme($path, $name)
    {
        $zip = new ZipArchive;
        $res = $zip->open($path);
        if ($res === TRUE) {
            $zip->extractTo(WP_CONTENT_DIR . '/themes/' . $name);
            $zip->close();
        } else {
            WP_CLI::error('Error unzipping theme');
        }
        unlink($path);
    }
}
