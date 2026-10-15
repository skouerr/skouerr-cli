<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Low-level terminal writer.
 */
interface Writer {

	/**
	 * Writes an informational line.
	 *
	 * @param string $message Message.
	 */
	public function line( string $message ): void;

	/**
	 * Writes a success line.
	 *
	 * @param string $message Message.
	 */
	public function success( string $message ): void;

	/**
	 * Writes a warning line on STDERR.
	 *
	 * @param string $message Message.
	 */
	public function warning( string $message ): void;

	/**
	 * Writes an error line on STDERR without exiting.
	 *
	 * @param string $message Message.
	 */
	public function error( string $message ): void;

	/**
	 * Writes a table.
	 *
	 * @param list<array<string, mixed>> $rows    Rows.
	 * @param list<string>               $columns Columns.
	 */
	public function table( array $rows, array $columns ): void;

	/**
	 * Writes raw text on STDOUT.
	 *
	 * @param string $text Text.
	 */
	public function raw( string $text ): void;
}
