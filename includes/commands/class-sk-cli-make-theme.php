<?php

class Skouerr_CLI_Make_Theme
{
    public function __construct() {}

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
