<?php

/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

if ( class_exists( 'WP_CLI' ) ) {
	require __DIR__ . '/includes/class-sk-cli-input.php';
}

// Include all the files that you want to load in here.

require __DIR__ . '/vendor/autoload.php';

require __DIR__ . '/includes/class-skouerr-cli-plugin.php';
require __DIR__ . '/includes/class-skouerr-cli.php';

require __DIR__ . '/includes/commands/class-skouerr-cli-import-block.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-list-blocks.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-make-theme.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-make-block.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-make-post-type.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-make-variation.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-make-template.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-save-template.php';
require __DIR__ . '/includes/commands/class-skouerr-cli-save-pattern.php';


require __DIR__ . '/includes/templates/class-skouerr-template-block.php';
require __DIR__ . '/includes/templates/class-skouerr-template-block-native-php.php';
require __DIR__ . '/includes/templates/class-skouerr-template-block-acf-php.php';
require __DIR__ . '/includes/templates/class-skouerr-template-block-acf-twig.php';
