<?php

require __DIR__ . '/vendor/autoload.php';

if (class_exists('WP_CLI')) {
    require __DIR__ . '/includes/class-sk-cli-input.php';
}

require __DIR__ . '/includes/class-skouerr-cli-plugin.php';
require __DIR__ . '/includes/class-skouerr-cli.php';
require __DIR__ . '/includes/class-sk-cli-command.php';

require __DIR__ . '/includes/commands/class-sk-cli-import-block.php';
require __DIR__ . '/includes/commands/class-sk-cli-list-blocks.php';
require __DIR__ . '/includes/commands/class-sk-cli-make-block.php';
require __DIR__ . '/includes/commands/class-sk-cli-make-post-type.php';
require __DIR__ . '/includes/commands/class-sk-cli-make-template.php';
require __DIR__ . '/includes/commands/class-sk-cli-save-template.php';


require __DIR__ . '/includes/templates/class-sk-template-block.php';
require __DIR__ . '/includes/templates/class-sk-template-block-native-php.php';
require __DIR__ . '/includes/templates/class-sk-template-block-acf-php.php';
