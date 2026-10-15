<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Collects the result of a command and renders it for humans or as JSON.
 *
 * JSON mode prints exactly one JSON document on STDOUT:
 *
 *     {"ok": true, "command": "make:block", "dry_run": false, "message": "…",
 *      "data": {…}, "files": [{"action": "create", "path": "…"}],
 *      "warnings": [], "next_steps": []}
 *
 * On failure: {"ok": false, "command": "…", "error": {"code": "…", "message": "…", "details": {…}}}
 */
final class Output {

	/**
	 * Result data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * File operations.
	 *
	 * @var list<array{action: string, path: string}>
	 */
	private array $files = array();

	/**
	 * Warnings.
	 *
	 * @var list<string>
	 */
	private array $warnings = array();

	/**
	 * Suggested follow-up actions.
	 *
	 * @var list<string>
	 */
	private array $next_steps = array();

	/**
	 * Constructor.
	 *
	 * @param Writer $writer  Writer.
	 * @param string $command Command name, without the "skouerr" prefix.
	 * @param bool   $json    JSON output.
	 * @param bool   $dry_run Dry-run mode.
	 * @param bool   $patch   Patch mode: print a unified diff of the file changes instead of writing them.
	 */
	public function __construct(
		private readonly Writer $writer,
		private readonly string $command,
		private readonly bool $json = false,
		private readonly bool $dry_run = false,
		private readonly bool $patch = false,
	) {}

	/**
	 * Unified diff collected in patch mode.
	 *
	 * @var string
	 */
	private string $diff = '';

	/**
	 * Tells whether the output is a patch.
	 */
	public function is_patch(): bool {
		return $this->patch;
	}

	/**
	 * Records a file change in patch mode.
	 *
	 * @param string      $path   Path relative to the directory the patch applies to.
	 * @param string|null $before Contents before, null for a new file.
	 * @param string|null $after  Contents after, null for a deleted file.
	 */
	public function diff( string $path, ?string $before, ?string $after ): void {
		$this->diff .= \Skouerr\Cli\Support\Diff::unified( $path, $before, $after );
	}

	/**
	 * Tells whether the output is JSON.
	 */
	public function is_json(): bool {
		return $this->json;
	}

	/**
	 * Tells whether the command runs in dry-run mode.
	 */
	public function is_dry_run(): bool {
		return $this->dry_run || $this->patch;
	}

	/**
	 * Tells whether human-readable lines are printed on STDOUT.
	 */
	private function prints_lines(): bool {
		return ! $this->json && ! $this->patch;
	}

	/**
	 * Progress message. Hidden in JSON mode.
	 *
	 * @param string $message Message.
	 */
	public function info( string $message ): void {
		if ( $this->prints_lines() ) {
			$this->writer->line( $message );
		}
	}

	/**
	 * Non-blocking problem.
	 *
	 * @param string $message Message.
	 */
	public function warning( string $message ): void {
		$this->warnings[] = $message;
		if ( ! $this->json ) {
			$this->writer->warning( $message );
		}
	}

	/**
	 * Records a file operation.
	 *
	 * @param string $action Action: create, overwrite, delete, trash.
	 * @param string $path   Absolute path.
	 */
	public function file( string $action, string $path ): void {
		$this->files[] = array(
			'action' => $action,
			'path'   => $path,
		);
		if ( $this->prints_lines() ) {
			/* translators: %s: file action (create, overwrite, delete, trash). */
			$label = $this->dry_run ? sprintf( __( 'would %s', 'skouerr-cli' ), $action ) : $action;
			$this->writer->line( sprintf( '  %s %s', str_pad( $label, 16 ), $path ) );
		}
	}

	/**
	 * Adds a suggested follow-up action.
	 *
	 * @param string $step Step, usually a shell command.
	 */
	public function next_step( string $step ): void {
		$this->next_steps[] = $step;
	}

	/**
	 * Sets a result value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function set( string $key, mixed $value ): void {
		$this->data[ $key ] = $value;
	}

	/**
	 * Displays rows. In JSON mode, the rows are stored under the given key.
	 *
	 * @param string                     $key     Key in JSON output.
	 * @param list<array<string, mixed>> $rows    Rows.
	 * @param list<string>               $columns Columns.
	 */
	public function table( string $key, array $rows, array $columns ): void {
		$this->data[ $key ] = $rows;
		if ( $this->prints_lines() ) {
			if ( empty( $rows ) ) {
				$this->writer->line( __( 'No items.', 'skouerr-cli' ) );
				return;
			}
			$this->writer->table( $rows, $columns );
		}
	}

	/**
	 * Prints raw text in text mode, or stores the value in JSON mode.
	 *
	 * @param string $key   Key in JSON output.
	 * @param mixed  $value Value.
	 * @param string $text  Text for humans.
	 */
	public function document( string $key, mixed $value, string $text ): void {
		$this->data[ $key ] = $value;
		if ( $this->prints_lines() ) {
			$this->writer->raw( $text . PHP_EOL );
		}
	}

	/**
	 * Ends a successful command.
	 *
	 * @param string $message Summary.
	 */
	public function success( string $message ): void {
		if ( $this->patch ) {
			$this->writer->raw( $this->diff );
			return;
		}

		if ( $this->json ) {
			$this->print_json(
				array(
					'ok'         => true,
					'command'    => $this->command,
					'dry_run'    => $this->dry_run,
					'message'    => $message,
					'data'       => (object) $this->data,
					'files'      => $this->files,
					'warnings'   => $this->warnings,
					'next_steps' => $this->next_steps,
				)
			);
			return;
		}

		/* translators: %s: success message. */
		$this->writer->success( $this->dry_run ? sprintf( __( '[dry-run] %s', 'skouerr-cli' ), $message ) : $message );
		foreach ( $this->next_steps as $step ) {
			/* translators: %s: suggested follow-up action. */
			$this->writer->line( '  ' . sprintf( __( 'next: %s', 'skouerr-cli' ), $step ) );
		}
	}

	/**
	 * Ends a failed command.
	 *
	 * @param CliException $exception Failure.
	 * @return int Exit code.
	 */
	public function failure( CliException $exception ): int {
		if ( $this->json ) {
			$this->print_json(
				array(
					'ok'       => false,
					'command'  => $this->command,
					'dry_run'  => $this->dry_run,
					'error'    => array(
						'code'    => $exception->exit_code->slug(),
						'message' => $exception->getMessage(),
						'details' => (object) $exception->details,
					),
					'files'    => $this->files,
					'warnings' => $this->warnings,
				)
			);
		} else {
			$this->writer->error( $exception->getMessage() );
		}
		return $exception->exit_code->value;
	}

	/**
	 * Prints a JSON document.
	 *
	 * @param array<string, mixed> $document Document.
	 */
	private function print_json( array $document ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Used without WordPress in tests.
		$this->writer->raw( (string) json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE ) . PHP_EOL );
	}
}
