<?php

class Skouerr_CLI
{
    public function __construct()
    {
        // Include make block command.
        $skouerr_make_block = new Skouerr_CLI_Make_Block();
        WP_CLI::add_command('skouerr make:block', $skouerr_make_block);

        // Include list block command
        $skouerr_list_blocks = new Skouerr_CLI_List_Blocks();
        WP_CLI::add_command('skouerr list:blocks', array($skouerr_list_blocks, 'list_blocks'));
    }
}
