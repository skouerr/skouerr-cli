<?php

class Skouerr_CLI
{
    public function __construct()
    {

        /**
         * Blocks commands
         */

        // Include make block command.
        $skouerr_make_block = new Skouerr_CLI_Make_Block();
        WP_CLI::add_command('skouerr make:block', array($skouerr_make_block, 'make_block'));

        // Include list block command
        $skouerr_list_blocks = new Skouerr_CLI_List_Blocks();
        WP_CLI::add_command('skouerr list:blocks', array($skouerr_list_blocks, 'list_blocks'));

        // Include import block command
        $skouerr_import_block = new Skouerr_CLI_Import_Block();
        WP_CLI::add_command('skouerr import:block', array($skouerr_import_block, 'import_block'));


        /**
         * Post types commands
         */

        // Include make post type command
        $skouerr_make_post_type = new Skouerr_CLI_Make_Post_Type();
        WP_CLI::add_command('skouerr make:post-type', array($skouerr_make_post_type, 'make_post_type'));


        /**
         * Templates commands
         */

        // Include save template command
        $skouerr_save_template = new Skouerr_CLI_Save_Template();
        WP_CLI::add_command('skouerr save:template', array($skouerr_save_template, 'save_template'));

        // Make template command
        $skouerr_make_template = new Skouerr_CLI_Make_Template();
        WP_CLI::add_command('skouerr make:template', array($skouerr_make_template, 'make_template'));
    }
}
