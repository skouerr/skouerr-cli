<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Writer that keeps everything in memory. Used by tests.
 */
final class BufferWriter implements Writer {

	/**
	 * Written lines, prefixed with their channel.
	 *
	 * @var list<string>
	 */
	public array $lines = array();

	/**
	 * Raw STDOUT content.
	 *
	 * @var string
	 */
	public string $stdout = '';

	/**
	 * {@inheritDoc}
	 */
	public function line( string $message ): void {
		$this->lines[] = 'line: ' . $message;
	}

	/**
	 * {@inheritDoc}
	 */
	public function success( string $message ): void {
		$this->lines[] = 'success: ' . $message;
	}

	/**
	 * {@inheritDoc}
	 */
	public function warning( string $message ): void {
		$this->lines[] = 'warning: ' . $message;
	}

	/**
	 * {@inheritDoc}
	 */
	public function error( string $message ): void {
		$this->lines[] = 'error: ' . $message;
	}

	/**
	 * {@inheritDoc}
	 */
	public function table( array $rows, array $columns ): void {
		$this->lines[] = 'table: ' . (string) json_encode( $rows ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test helper.
	}

	/**
	 * {@inheritDoc}
	 */
	public function raw( string $text ): void {
		$this->stdout .= $text;
	}
}
