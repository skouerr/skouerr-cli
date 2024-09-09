<?php

use Symfony\Component\Finder\Finder;

class Skouerr_Template_Block
{

    // Public properties that hold information about the block
    public string $title;       // The title of the block
    public string $name;        // The internal name (identifier) of the block
    public string $type;        // The type/category of the block
    public string $template;    // Template name for the block
    public string $slug;        // The slug used for block identification
    public string $icon;        // The icon representing the block in the editor
    public string $block_folder; // Path to the block folder

    /**
     * Constructor initializes block data from the input array.
     *
     * @param array $block_data Contains values for initializing block properties
     */
    public function __construct($block_data)
    {
        $this->title = $block_data['title'];
        $this->name = $block_data['name'];
        $this->type = $block_data['type'];
        $this->template = $block_data['template'];
        $this->slug = $block_data['slug'];
        $this->icon = $block_data['icon'];
    }

    /**
     * Creates a temporary folder in the theme directory.
     * This folder will be used for holding template files temporarily.
     */
    public function make_tmp_folder()
    {
        $tmp_folder = get_template_directory() . '/tmp';
        if (!file_exists($tmp_folder)) {
            mkdir($tmp_folder);
        }
    }

    /**
     * Removes the temporary folder and its contents from the theme directory.
     * It first deletes the files in the folder, then removes the folder itself.
     */
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

    /**
     * Creates a folder for the block in the theme's 'blocks' directory.
     * If the folder does not exist, it creates both the block folder and
     * a 'src' directory within it.
     *
     * @return string The path to the created block folder
     */
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

    /**
     * Retrieves the source folder path for the block template files.
     *
     * @return string The source path of the template
     */
    public function get_source()
    {
        $plugin_path = dirname(__FILE__, 3);
        $source = $plugin_path . '/templates/block/' . $this->type . '-' . $this->template;
        return $source;
    }

    /**
     * Copies template files from the source to the temporary folder.
     * It uses a recursive iterator to copy directories and files from
     * the source template folder to the temporary folder.
     *
     * @return array List of files copied to the temporary folder
     */
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

    /**
     * Moves files from the temporary folder to the block folder.
     */
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

    /**
     * Retrieves all files in the temporary folder.
     *
     * @return array List of file paths found in the temporary folder
     */
    private function get_all_files_in_tmp()
    {
        $finder = new Finder();
        $finder->files()->in(get_template_directory() . '/tmp');
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }
        return $files;
    }

    /**
     * Renames a file from its old name to a new name.
     *
     * @param string $path The file's current path
     * @param string $old_name The old name of the file
     * @param string $new_name The new name of the file
     */
    public function rename_file($path, $old_name, $new_name)
    {
        $new_path = str_replace($old_name, $new_name, $path);
        rename($path, $new_path);
    }

    /**
     * Replaces placeholders in template files with actual block data.
     * It searches for specific placeholders like '%SK_BLOCK_NAME%' and
     * replaces them with corresponding block properties.
     */
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

    /**
     * Searches for a string in a file and replaces it with another string.
     *
     * @param string $file The file path to search within
     * @param string $search The string to search for
     * @param string $replace The string to replace the search string with
     */
    private function search_and_replace($file, $search, $replace)
    {
        $content = file_get_contents($file);
        $content = str_replace($search, $replace, $content);
        file_put_contents($file, $content);
    }
}
