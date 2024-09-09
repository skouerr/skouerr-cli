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

const SKOUERR_REMOTE_DOMAIN = 'https://download.skouerr.dev';
const SKOUERR_REMOTE_THEME_URL = SKOUERR_REMOTE_DOMAIN . '/wp-json/skouerr/v1/download/theme';
