<?php

class Skouerr_Template_Block_Acf_Twig extends Skouerr_Template_Block
{

    public function __construct($block_data)
    {
        parent::__construct($block_data);
        $this->create_block();
    }

    public function create_block()
    {
        $this->remove_tmp_folder();
        $this->make_tmp_folder();
        $this->make_block_folder();
        $files = $this->copy_files_in_tmp();

        foreach ($files as $file) {
            $name = basename($file);
            if ($name == 'controller') {
                $this->rename_file($file, $name, $this->name . '.controller.php');
            }

            if ($name == 'template') {
                $this->rename_file($file, $name, $this->name . '.template.twig');
            }

            if ($name == 'block') {
                $this->rename_file($file, $name, $this->name . '.block.json');
            }

            if ($name == 'style') {
                $this->rename_file($file, $name, $this->name . '.style.scss');
            }

            if ($name == 'script') {
                $this->rename_file($file, $name, $this->name . '.script.js');
            }
        }

        $this->set_values();
        $this->move_files_to_block_folder();
        $this->remove_tmp_folder();
    }
}
