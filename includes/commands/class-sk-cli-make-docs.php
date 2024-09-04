<?php

class Skouerr_CLI_Make_Docs
{
    private string $prompt;

    public function __construct()
    {
        $this->prompt = '';
    }

    public function make_docs()
    {
        $types = array('block', 'theme');
        $type = SK_CLI_Input::select(__('Select the type of documentation to generate'), $types);

        if ($type === 'block') {
            $block_name = SK_CLI_Input::ask(__('Enter the name of the block'));
            WP_CLI::log(__('Start generating block documentation ...'));
            $this->save_block_docs($block_name);
            WP_CLI::success(__('Block documentation generated successfully'));
        }

        if ($type === 'theme') {
            WP_CLI::log(__('Start generating theme documentation ...'));
            $this->save_theme_docs();
            WP_CLI::success(__('Theme documentation generated successfully'));
        }
    }

    public function save_theme_docs()
    {
        $md = $this->generate_theme_docs();
        $file = get_template_directory() . '/theme.md';
        file_put_contents($file, $md);
    }

    public function generate_theme_docs()
    {

        return '';
    }

    public function save_block_docs($block_name)
    {
        $date = date('Y-m-d H:i:s');
        $md = $this->generate_block_docs($block_name);
        $file = get_template_directory() . '/blocks/' . $block_name . '/' . $block_name . '.md';
        file_put_contents($file, $md);
    }

    public function init_prompt($lang = 'en', $format = 'text')
    {
        $this->add_text_to_prompt('Tu est une fonctionnalité d\'un plugin wordpress qui permet de générer une documentation pour un thème wordpress. Tu dois être capable de générer une documentation pour un thème wordpress ainsi que ces blocks gutemberg.');
        $this->add_text_to_prompt('Tu dois générer le résultat dans cette langue : ' . $lang);
        $this->add_text_to_prompt('Tu dois générer le résultat dans ce format : ' . $format);
    }

    public function generate_block_docs($block_name)
    {
        $code = $this->get_code_from_block($block_name);
        $template = $this->get_doc_template('block');


        // Demander la génération de la documentation
        $this->add_text_to_prompt('À partir du code et de la template fournie, génère une documentation en français détaillée pour le block "' . $block_name . '", en veillant à décrire chaque fichier selon le format de la template.');

        // Ajouter la template de documentation au prompt
        $this->add_text_to_prompt('En utilisant la template de documentation suivante, tu peux éditer le contenu situé dans des {{}} {}:');
        $this->add_text_to_prompt($template);

        // Expliquer la tâche à accomplir
        $this->add_text_to_prompt('Tu dois générer une documentation pour le block "' . $block_name . '", en décrivant le fonctionnement et l\'utilité de chaque fichier du block Gutenberg : ' . $block_name);

        // Ajouter le code source des fichiers au prompt
        $this->add_text_to_prompt('Voici le code source des fichiers du block :');
        $this->add_text_to_prompt($code);

        $this->add_text_to_prompt('Voici la fin des instructions, traduit la en français');
        return $this->send_openapi_request();
    }

    private function get_code_from_block($block_name)
    {
        $block_folder = get_template_directory() . '/blocks/' . $block_name;
        $files = array_merge(glob($block_folder . '/*'), glob($block_folder . '/src/*'));
        $files = array_filter($files, function ($file) {
            return is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'md';
        });
        $code = '';
        foreach ($files as $file) {
            $code .= '# File : ' . $file . PHP_EOL;
            $code .= file_get_contents($file) . PHP_EOL;
            $code .= PHP_EOL;
        }
        return $code;
    }

    public function add_text_to_prompt($text)
    {
        $this->prompt .= $text . PHP_EOL;
    }

    private function get_doc_template($type)
    {
        $path =  dirname(__FILE__, 3) . '/templates/docs/' . $type . '.md';
        return file_get_contents($path);
    }

    private function send_openapi_request()
    {
        $client = OpenAI::client(OPENAI_API_KEY);

        $this->add_text_to_prompt('Voici la fin des instructions tu peux commencer a générer le contenu');

        $result = $client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a function to generate a documentation for a wordpress theme.'],
                ['role' => 'user', 'content' => $this->prompt],
            ],
            'max_tokens' => 2000,
        ]);

        return $result->choices[0]->message->content;
    }
}
