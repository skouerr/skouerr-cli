<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * PSR-4 autoloader for the Skouerr\Cli namespace. The plugin has no runtime
 * dependency, so it works without Composer's autoloader.
 *
 * @package Skouerr_CLI
 */

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Skouerr\\Cli\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);
