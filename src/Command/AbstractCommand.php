<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Console\Parameter;
use Skouerr\Cli\Console\WpCliPrompter;
use Skouerr\Cli\Console\WpCliWriter;
use WP_CLI;

/**
 * Base class of every `wp skouerr` command.
 *
 * A command declares its parameters once. The base class derives from them
 * the WP-CLI synopsis, the interactive prompts, the validation and the
 * machine-readable schema, and adds the common options:
 *
 * - `--format=json`    one JSON document on STDOUT, no prompt;
 * - `--no-interaction` never prompt (also when STDIN is not a TTY, or SKOUERR_NO_INTERACTION / CI is set);
 * - `--yes`            answer yes to confirmations;
 * - `--dry-run`        report file operations without writing (commands that write);
 * - `--force`          overwrite existing files (commands that write).
 */
abstract class AbstractCommand {

	/** Side effect: writes files in the theme. */
	public const string EFFECT_FILES = 'filesystem';

	/** Side effect: calls a remote server. */
	public const string EFFECT_NETWORK = 'network';

	/** Side effect: changes the database. */
	public const string EFFECT_DATABASE = 'database';

	/** Side effect: runs external programs. */
	public const string EFFECT_PROCESS = 'process';

	/**
	 * Command name, without the "skouerr" prefix (e.g. "make:block").
	 */
	abstract public function name(): string;

	/**
	 * One-line summary.
	 */
	abstract public function summary(): string;

	/**
	 * Specific parameters of the command.
	 *
	 * @return list<Parameter>
	 */
	abstract protected function parameters(): array;

	/**
	 * Runs the command.
	 *
	 * @param Input  $input  Input.
	 * @param Output $output Output.
	 * @return string Success message.
	 * @throws CliException On expected failure.
	 */
	abstract protected function handle( Input $input, Output $output ): string;

	/**
	 * Side effects of the command (EFFECT_* constants).
	 *
	 * @return list<string>
	 */
	public function side_effects(): array {
		return array();
	}

	/**
	 * Usage examples, without the leading "wp ".
	 *
	 * @return list<string>
	 */
	public function examples(): array {
		return array();
	}

	/**
	 * Tells whether the command writes files (adds --dry-run and --force).
	 */
	public function writes_files(): bool {
		return in_array( self::EFFECT_FILES, $this->side_effects(), true );
	}

	/**
	 * Specific and common parameters.
	 *
	 * @return list<Parameter>
	 */
	public function all_parameters(): array {
		$parameters = $this->parameters();
		$formats    = array(
			'text' => 'Text',
			'json' => 'JSON',
		);
		if ( $this->writes_files() ) {
			$formats['patch'] = 'Unified diff';
		}
		$parameters[] = new Parameter(
			name: 'format',
			description: 'Output format. "json" prints one JSON document on STDOUT and disables prompts.'
				. ( $this->writes_files() ? ' "patch" prints a unified diff of the file changes and writes nothing. Paths are relative to the Git repository root (theme directory outside a repository): apply it with `git apply` from that root.' : '' ),
			default: 'text',
			options: $formats
		);
		$parameters[] = Parameter::flag( 'yes', 'Answer yes to every confirmation.' );
		$parameters[] = Parameter::flag( 'interaction', 'Pass --no-interaction to never prompt (fails on missing parameters instead).' );
		if ( $this->writes_files() ) {
			$parameters[] = Parameter::flag( 'dry-run', 'Report the file operations without writing anything.' );
			$parameters[] = Parameter::flag( 'force', 'Overwrite existing files.' );
		}
		return $parameters;
	}

	/**
	 * WP-CLI entry point.
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$json   = 'json' === ( $assoc_args['format'] ?? null );
		$patch  = 'patch' === ( $assoc_args['format'] ?? null ) && $this->writes_files();
		$output = new Output( new WpCliWriter(), $this->name(), $json, ! empty( $assoc_args['dry-run'] ), $patch );
		$input  = new Input(
			$this->all_parameters(),
			$args,
			$assoc_args,
			self::is_interactive( $assoc_args, $json || $patch ) ? new WpCliPrompter() : null
		);

		if ( $json || $patch ) {
			// Machine-readable output is a stable contract: messages stay in English.
			unload_textdomain( 'skouerr-cli' );
			// STDOUT must only contain the document: PHP notices go to STDERR.
			ini_set( 'display_errors', 'stderr' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
		}

		// WP-CLI 2.12 triggers PHP 8.4+ deprecations in its own code (php-cli-tools, react/promise)
		// each time it prints a line. They are not ours to fix and would pollute the output.
		set_error_handler( array( self::class, 'silence_wp_cli_deprecation' ), E_DEPRECATED | E_USER_DEPRECATED ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		try {
			$code = $this->execute( $input, $output );
		} finally {
			restore_error_handler();
		}

		if ( 0 !== $code ) {
			WP_CLI::halt( $code );
		}
	}

	/**
	 * Error handler: hides deprecations raised by WP-CLI's own code, lets others through.
	 *
	 * @param int    $errno  Error level.
	 * @param string $errstr Message.
	 * @param string $file   File that raised the error.
	 * @return bool True when the error is handled (hidden).
	 */
	public static function silence_wp_cli_deprecation( int $errno, string $errstr, string $file = '' ): bool {
		unset( $errno, $errstr );
		if ( str_starts_with( $file, 'phar://' ) ) {
			return true;
		}
		if ( defined( 'WP_CLI_ROOT' ) ) {
			// WP_CLI_ROOT is vendor/wp-cli/wp-cli: its vendor directory holds php-cli-tools and the others.
			$vendor = dirname( (string) WP_CLI_ROOT, 2 );
			return str_starts_with( $file, $vendor . '/' ) && ! str_starts_with( $file, SKOUERR_CLI_PATH . '/' );
		}
		return false;
	}

	/**
	 * Runs the command and renders the result.
	 *
	 * @param Input  $input  Input.
	 * @param Output $output Output.
	 * @return int Exit code.
	 */
	public function execute( Input $input, Output $output ): int {
		try {
			$message = $this->handle( $input, $output );
			if ( $this->writes_files() && ! $output->is_dry_run() && function_exists( 'wp_get_theme' ) ) {
				\Skouerr\Cli\Support\Theme::flush_caches();
			}
			$output->success( $message );
			return 0;
		} catch ( CliException $e ) {
			return $output->failure( $e );
		}
	}

	/**
	 * Arguments for WP_CLI::add_command().
	 *
	 * @return array<string, mixed>
	 */
	public function registration(): array {
		$longdesc = '';
		if ( ! empty( $this->examples() ) ) {
			$longdesc = "## EXAMPLES\n\n" . implode(
				"\n\n",
				array_map( static fn( string $example ): string => '    wp ' . $example, $this->examples() )
			);
		}

		return array(
			'shortdesc' => $this->summary(),
			'longdesc'  => $longdesc,
			'synopsis'  => array_map( static fn( Parameter $parameter ): array => $parameter->to_synopsis(), $this->all_parameters() ),
		);
	}

	/**
	 * Machine-readable description of the command.
	 *
	 * @return array<string, mixed>
	 */
	public function schema(): array {
		return array(
			'name'         => $this->name(),
			'usage'        => 'wp skouerr ' . $this->name(),
			'summary'      => $this->summary(),
			'side_effects' => $this->side_effects(),
			'parameters'   => array_map( static fn( Parameter $parameter ): array => $parameter->to_schema(), $this->all_parameters() ),
			'examples'     => array_map( static fn( string $example ): string => 'wp ' . $example, $this->examples() ),
		);
	}

	/**
	 * Tells whether prompts can be shown.
	 *
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @param bool                 $json       JSON output.
	 */
	public static function is_interactive( array $assoc_args, bool $json ): bool {
		if ( $json || false === ( $assoc_args['interaction'] ?? true ) ) {
			return false;
		}
		if ( getenv( 'SKOUERR_NO_INTERACTION' ) || getenv( 'CI' ) ) {
			return false;
		}
		return defined( 'STDIN' ) && stream_isatty( STDIN );
	}
}
