<?php

class Skouerr_CLI_Make_Template
{
    public function __construct() {}

    public function make_template()
    {
        $name = SK_CLI_Input::ask('Enter the name of the template');
        file_put_contents(get_template_directory() . '/templates/' . $name . '.html', '');
        WP_CLI::success('Template created');
    }
}
