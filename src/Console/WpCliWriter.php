<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

use WP_CLI;

/**
 * Writer based on WP-CLI.
 */
final class WpCliWriter implements Writer {

	/**
	 * {@inheritDoc}
	 */
	public function line( string $message ): void {
		WP_CLI::log( $message );
	}

	/**
	 * {@inheritDoc}
	 */
	public function success( string $message ): void {
		WP_CLI::success( $message );
	}

	/**
	 * {@inheritDoc}
	 */
	public function warning( string $message ): void {
		WP_CLI::warning( $message );
	}

	/**
	 * {@inheritDoc}
	 */
	public function error( string $message ): void {
		WP_CLI::error( $message, false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function table( array $rows, array $columns ): void {
		WP_CLI\Utils\format_items( 'table', $rows, $columns );
	}

	/**
	 * {@inheritDoc}
	 */
	public function raw( string $text ): void {
		fwrite( STDOUT, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	}
}
