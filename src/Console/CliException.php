<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Expected failure of a command. Carries an exit code and structured details.
 */
final class CliException extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string               $message   Human readable message.
	 * @param ExitCode             $exit_code Exit code of the process.
	 * @param array<string, mixed> $details   Structured details for JSON output.
	 */
	public function __construct(
		string $message,
		public readonly ExitCode $exit_code = ExitCode::Error,
		public readonly array $details = array(),
	) {
		parent::__construct( $message, $exit_code->value );
	}

	/**
	 * Invalid or missing input.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $details Details.
	 */
	public static function invalid( string $message, array $details = array() ): self {
		return new self( $message, ExitCode::InvalidInput, $details );
	}

	/**
	 * Target already exists.
	 *
	 * @param string $path Conflicting path.
	 */
	public static function conflict( string $path ): self {
		return new self(
			/* translators: %s: file path. */
			sprintf( __( 'File already exists: %s. Use --force to overwrite.', 'skouerr-cli' ), $path ),
			ExitCode::Conflict,
			array( 'path' => $path )
		);
	}

	/**
	 * Network or remote server failure.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $details Details.
	 */
	public static function remote( string $message, array $details = array() ): self {
		return new self( $message, ExitCode::Remote, $details );
	}

	/**
	 * Missing requirement in the environment.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $details Details.
	 */
	public static function environment( string $message, array $details = array() ): self {
		return new self( $message, ExitCode::Environment, $details );
	}
}
