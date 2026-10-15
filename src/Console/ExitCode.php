<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Process exit codes. They are part of the public contract for scripts and agents.
 */
enum ExitCode: int {

	case Ok           = 0;
	case Error        = 1;
	case InvalidInput = 2;
	case Conflict     = 3;
	case Remote       = 4;
	case Environment  = 5;
	case Aborted      = 6;

	/**
	 * Machine-readable error code used in JSON output.
	 */
	public function slug(): string {
		return match ( $this ) {
			self::Ok           => 'ok',
			self::Error        => 'error',
			self::InvalidInput => 'invalid_input',
			self::Conflict     => 'conflict',
			self::Remote       => 'remote_error',
			self::Environment  => 'environment',
			self::Aborted      => 'aborted',
		};
	}
}
