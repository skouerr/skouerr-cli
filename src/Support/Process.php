<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

/**
 * Runs external programs without a shell.
 */
final class Process {

	/**
	 * Runs a command. Arguments are passed as an array: no shell, no injection.
	 *
	 * @param list<string> $command Program and arguments.
	 * @param string       $cwd     Working directory.
	 * @return array{code: int, stdout: string, stderr: string}
	 */
	public static function run( array $command, string $cwd ): array {
		// Output goes to temporary files: reading two pipes one after the other can deadlock.
		$stdout_file = (string) tempnam( sys_get_temp_dir(), 'skouerr-out-' );
		$stderr_file = (string) tempnam( sys_get_temp_dir(), 'skouerr-err-' );
		$pipes       = array();
		$process     = proc_open( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs Composer without a shell.

			$command,
			array(
				0 => array( 'file', '/dev/null', 'r' ),
				1 => array( 'file', $stdout_file, 'w' ),
				2 => array( 'file', $stderr_file, 'w' ),
			),
			$pipes,
			$cwd
		);

		$started = is_resource( $process );
		$code    = $started ? proc_close( $process ) : 127;

		// phpcs:disable WordPress.WP.AlternativeFunctions
		$result = array(
			'code'   => $code,
			'stdout' => (string) file_get_contents( $stdout_file ),
			'stderr' => $started ? (string) file_get_contents( $stderr_file ) : 'Cannot start ' . $command[0],
		);
		unlink( $stdout_file );
		unlink( $stderr_file );
		// phpcs:enable

		return $result;
	}

	/**
	 * Finds an executable in the PATH.
	 *
	 * @param string $program Program name.
	 */
	public static function find( string $program ): ?string {
		foreach ( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ) as $directory ) {
			$path = rtrim( $directory, '/' ) . '/' . $program;
			if ( '' !== $directory && is_file( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}
		return null;
	}
}
