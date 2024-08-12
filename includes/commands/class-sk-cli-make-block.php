<?php

class Skouerr_CLI_Make_Block extends Skouerr_Command
{

    public array $types;
    public array $templates;
    public array $prefix;

    public function __construct()
    {

        $acf_text = __('ACF (needs ACF Pro)');

        $this->types = array(
            'native' => 'Native',
            'acf' => $acf_text,
        );

        $this->templates = array(
            'native' => array(
                'react' => 'React',
                'php' => 'PHP',
            ),
            'acf' => array(
                'php' => 'PHP',
                'twig' => 'Twig / Timber',
                'react' => 'React',
            ),
        );

        $this->prefix = array(
            'skouerr',
            get_template(),
            'acf',
            'core',
            'custom',
        );

        $block_data = $this->form_block();
        $this->create_block($block_data);
        die();
    }

    public function form_block()
    {
        $type = SK_CLI_Input::select(__('Select the type of block'), $this->types);

        $templates = $this->templates[$type];
        $template = SK_CLI_Input::select(__('Select the template'), $templates);

        $title = SK_CLI_Input::ask(__('Enter the name of the block'));
        $title = ucwords($title);
        $prefix = SK_CLI_Input::select(__('Select the prefix'), $this->prefix);
        $name = strtolower(str_replace(' ', '-', $title));
        $slug = $prefix . '/' . $name;

        $icon = SK_CLI_Input::ask(__('Enter the dashicon of the block (https://developer.wordpress.org/resource/dashicons)'));
        $icon = str_replace('dashicons-', '', $icon);
        $icon = $icon ?? 'block-default';

        WP_CLI::line(__('Confirm the following information:'));
        WP_CLI::line(__(' Title: ') . $title);
        WP_CLI::line(__(' Name: ') . $name);
        WP_CLI::line(__(' Type: ') . $type);
        WP_CLI::line(__(' Template: ') . $template);
        WP_CLI::line(__(' Slug: ') . $slug);
        WP_CLI::line(__(' Icon: ') . $icon);

        $confirm = SK_CLI_Input::select(__('Do you confirm creation of block ?'), array('yes', 'no'));
        if ($confirm == 'no') {
            WP_CLI::line(__('Block creation aborted'));
            die();
        }
        $block_data = array(
            'title' => $title,
            'name' => $name,
            'type' => $type,
            'template' => $template,
            'slug' => $slug,
            'icon' => $icon,
        );
        return $block_data;
    }

    public function create_block($block_data)
    {
        new Skouerr_Template_Block_Native_Php($block_data);
    }
}
