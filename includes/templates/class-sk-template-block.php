<?php

use Symfony\Component\Finder\Finder;

class Skouerr_Template_Block
{

    public string $title;
    public string $name;
    public string $type;
    public string $template;
    public string $slug;
    public string $icon;
    public string $block_folder;


    public function __construct($block_data)
    {
        $this->title = $block_data['title'];
        $this->name = $block_data['name'];
        $this->type = $block_data['type'];
        $this->template = $block_data['template'];
        $this->slug = $block_data['slug'];
        $this->icon = $block_data['icon'];
    }

    public function make_tmp_folder()
    {
        $tmp_folder = get_template_directory() . '/tmp';
        if (!file_exists($tmp_folder)) {
            mkdir($tmp_folder);
        }
    }

    public function remove_tmp_folder()
    {
        $tmp_folder = get_template_directory() . '/tmp';
        if (file_exists($tmp_folder)) {

            $finder = new Finder();
            $finder->files()->in($tmp_folder);
            foreach ($finder as $file) {
                unlink($file->getRealPath());
            }
            rmdir($tmp_folder . '/src');
            rmdir($tmp_folder);
        }
    }

    public function make_block_folder()
    {
        $block_folder = get_template_directory() . '/blocks/' . $this->name;
        if (!file_exists($block_folder)) {
            mkdir($block_folder);
            mkdir($block_folder . '/src');
        }
        $this->block_folder = $block_folder;
        return $block_folder;
    }

    public function get_source()
    {
        $plugin_path = dirname(__FILE__, 3);
        $source = $plugin_path . '/templates/block/' . $this->type . '-' . $this->template;
        return $source;
    }

    public function copy_files_in_tmp()
    {
        $source = $this->get_source();
        $destination = get_template_directory() . '/tmp';
        $files = glob($source . '/*');

        foreach ($files as $file) {
            $file_name = basename($file);
            $directory = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    if (!file_exists($destination . '/' . $iterator->getSubPathName())) {
                        mkdir($destination . '/' . $iterator->getSubPathName());
                    }
                } else {
                    copy($item, $destination . '/' . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                }
            }
        }

        $files =  $this->get_all_files_in_tmp();
        return $files;
    }

    public function move_files_to_block_folder()
    {
        $block_folder = $this->make_block_folder();

        $finder = new Finder();
        $finder->files()->in(get_template_directory() . '/tmp');

        foreach ($finder as $file) {
            $file_path = $file->getRealPath();
            $new_path = $block_folder . '/' . $file->getRelativePathname();
            copy($file_path, $new_path);
        }
    }

    private function get_all_files_in_tmp()
    {
        $finder = new Finder();
        $finder->files()->in(get_template_directory() . '/tmp');
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }
        return $files;
    }

    public function rename_file($path, $old_name, $new_name)
    {
        $new_path = str_replace($old_name, $new_name, $path);
        rename($path, $new_path);
    }

    public function set_values()
    {
        $files = $this->get_all_files_in_tmp();
        foreach ($files as $file) {
            $this->search_and_replace($file, '%SK_BLOCK_NAME%', $this->name);
            $this->search_and_replace($file, '%SK_BLOCK_TITLE%', $this->title);
            $this->search_and_replace($file, '%SK_BLOCK_SLUG%', $this->slug);
            $this->search_and_replace($file, '%SK_BLOCK_ICON%', $this->icon);
        }
    }

    private function search_and_replace($file, $search, $replace)
    {
        $content = file_get_contents($file);
        $content = str_replace($search, $replace, $content);
        file_put_contents($file, $content);
    }
}
