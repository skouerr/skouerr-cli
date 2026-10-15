<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Prompter based on the php-cli-tools library bundled with WP-CLI.
 */
final class WpCliPrompter implements Prompter {

	/**
	 * {@inheritDoc}
	 */
	public function ask( string $question, ?string $default = null ): string {
		return trim( (string) \cli\prompt( $question, $default ?? false ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function choose( string $question, array $options, ?string $default = null ): string {
		return (string) \cli\menu( $options, $default, $question );
	}

	/**
	 * {@inheritDoc}
	 */
	public function confirm( string $question, bool $default = true ): bool {
		return 'y' === \cli\choose( rtrim( $question, '?' ), 'yn', $default ? 'y' : 'n' );
	}
}
