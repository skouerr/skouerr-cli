<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

use Skouerr\Cli\Console\CliException;

/**
 * Validation rules shared by the commands.
 *
 * Identifiers end up in file paths, PHP, JS and JSON files: they are
 * restricted to a safe character set. Free text (titles, labels) is
 * cleaned, then escaped by the template renderer for each file type.
 */
final class Validator {

	/** File-system safe identifier: theme, template, binding, variation names. */
	public const string IDENTIFIER = '/^[a-z0-9][a-z0-9_-]{0,63}$/D';

	/** Block name or block namespace (lowercase, dashes, starts with a letter). */
	public const string BLOCK_SEGMENT = '/^[a-z][a-z0-9-]{0,63}$/D';

	/** Full block name: namespace/name. */
	public const string BLOCK_NAME = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/D';

	/** Post type key (WordPress limits it to 20 characters). */
	public const string POST_TYPE = '/^[a-z0-9_-]{1,20}$/D';

	/** Text domain. */
	public const string TEXT_DOMAIN = '/^[a-z0-9_-]{1,64}$/D';

	/** Dashicon slug, with or without "dashicons-" prefix. */
	public const string ICON = '/^[a-z0-9-]{1,64}$/D';

	/** SHA-256 checksum. */
	public const string SHA256 = '/^[a-f0-9]{64}$/D';

	/**
	 * Builds a slug from free text: "Hero Banner é" → "hero-banner-e".
	 *
	 * @param string $text Text.
	 */
	public static function slugify( string $text ): string {
		if ( function_exists( 'remove_accents' ) ) {
			$text = remove_accents( $text );
		} elseif ( function_exists( 'iconv' ) ) {
			$text = (string) iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
		}
		$text = strtolower( $text );
		$text = (string) preg_replace( '/[^a-z0-9]+/', '-', $text );
		return trim( $text, '-' );
	}

	/**
	 * Cleans a human label (title, name) so it is safe inside comments and strings.
	 *
	 * Removes control characters and sequences that close a comment or a PHP block.
	 *
	 * @param string $text Text.
	 * @param string $name Parameter name, for error messages.
	 * @throws CliException When the label is empty or too long.
	 */
	public static function label( string $text, string $name = 'title' ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', self::inert( $text ) ) );

		if ( '' === $text ) {
			/* translators: %s: parameter name. */
			throw CliException::invalid( sprintf( __( 'Parameter --%s cannot be empty.', 'skouerr-cli' ), $name ), array( 'parameter' => $name ) );
		}
		if ( mb_strlen( $text ) > 100 ) {
			/* translators: %s: parameter name. */
			throw CliException::invalid( sprintf( __( 'Parameter --%s is limited to 100 characters.', 'skouerr-cli' ), $name ), array( 'parameter' => $name ) );
		}
		return $text;
	}

	/**
	 * Removes control characters and the sequences that open or close a
	 * comment or a PHP block. Repeats until stable: removing "*\/" from
	 * "**\/\/" would otherwise create a new one.
	 *
	 * @param string $text Text.
	 */
	public static function inert( string $text ): string {
		$text = (string) preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $text );
		do {
			$previous = $text;
			$text     = str_replace( array( '*/', '/*', '<?', '?>' ), '', $text );
		} while ( $text !== $previous );
		return $text;
	}

	/**
	 * Checks a value against a pattern.
	 *
	 * @param string $value   Value.
	 * @param string $pattern Pattern.
	 * @param string $name    Parameter name, for error messages.
	 * @throws CliException When the value does not match.
	 */
	public static function assert( string $value, string $pattern, string $name ): string {
		if ( 1 !== preg_match( $pattern, $value ) ) {
			throw CliException::invalid(
				/* translators: %1$s: value, %2$s: parameter name, %3$s: expected regular expression. */
				sprintf( __( 'Invalid value "%1$s" for --%2$s. Expected format: %3$s.', 'skouerr-cli' ), $value, $name, $pattern ),
				array(
					'parameter' => $name,
					'pattern'   => $pattern,
				)
			);
		}
		return $value;
	}
}
