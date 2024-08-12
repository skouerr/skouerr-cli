<?php

class Skouerr_CLI_List_Blocks
{
    public function list_blocks()
    {
        $blocks = $this->get_blocks();
        $headers = array('title', 'name', 'category', 'icon');
        $data = array_map(function ($block) {
            return array(
                'name' => $block->name,
                'title' => $block->title,
                'category' => $block->category,
                'icon' => $block->icon,
            );
        }, $blocks);

        WP_CLI\Utils\format_items('table', $data, $headers);
        die();
    }

    public function get_blocks()
    {
        $blocks = array();
        if (!class_exists('Skouerr_Loader')) {
            require get_template_directory() . '/inc/core/loader.php';
        }
        $loader = new Skouerr_Loader();
        foreach ($loader->get_blocks() as $block) {
            $block_json = file_get_contents($block);
            $blocks[] = json_decode($block_json);
        }

        return $blocks;
    }
}
