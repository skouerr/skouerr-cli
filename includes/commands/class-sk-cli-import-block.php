<?php

/**
 * Skouerr_CLI_Import_Block class handles the import functionality
 * for blocks using WP-CLI (WordPress Command Line Interface).
 * Currently, the import functionality is not yet implemented, and
 * it outputs a warning message when invoked.
 */
class Skouerr_CLI_Import_Block
{

    /**
     * Constructor method for Skouerr_CLI_Import_Block.
     * Currently, it doesn't perform any operations.
     */
    public function __construct() {}

    /**
     * Method to import a block.
     * This function currently displays a warning message indicating
     * that the command is not yet implemented.
     */
    public function import_block()
    {
        WP_CLI::warning(__('This command is not implemented yet'));
    }
}
