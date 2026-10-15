<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

/**
 * Fills `%SK_*%` placeholders in scaffold files, escaping values for the file type.
 *
 * Placeholders inside PHP and JS files are expected to be in single-quoted
 * strings or comments; placeholders inside JSON files in double-quoted strings.
 */
final class Template {

	/**
	 * Renders a template string.
	 *
	 * @param string                $contents Template contents.
	 * @param array<string, string> $values   Values indexed by placeholder name (without `%`).
	 * @param string                $context  Escaping context: php, js, json or text.
	 */
	public static function render( string $contents, array $values, string $context ): string {
		$replacements = array();
		foreach ( $values as $placeholder => $value ) {
			$replacements[ '%' . $placeholder . '%' ] = self::escape( $value, $context );
		}
		return strtr( $contents, $replacements );
	}

	/**
	 * Renders a template file.
	 *
	 * @param string                $path    Template path.
	 * @param array<string, string> $values  Values.
	 * @param string                $context Escaping context.
	 * @throws \RuntimeException When the file cannot be read.
	 */
	public static function render_file( string $path, array $values, string $context ): string {
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			/* translators: %s: template path. */
			throw new \RuntimeException( sprintf( __( 'Cannot read template %s.', 'skouerr-cli' ), $path ) );
		}
		return self::render( $contents, $values, $context );
	}

	/**
	 * Escapes a value for a context.
	 *
	 * @param string $value   Value.
	 * @param string $context Context: php, js, json or text.
	 */
	public static function escape( string $value, string $context ): string {
		return match ( $context ) {
			'php'   => addcslashes( $value, "'\\" ),
			'js'    => str_replace( '</', '<\/', addcslashes( $value, "'\"\\\n\r\t`" ) ),
			'json'  => self::json_string( $value ),
			default => $value,
		};
	}

	/**
	 * Content of a JSON string literal, without the surrounding quotes.
	 *
	 * @param string $value Value.
	 */
	private static function json_string( string $value ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Used without WordPress in tests.
		$json = (string) json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
		return substr( $json, 1, -1 );
	}

	/**
	 * Escaping context for a file name.
	 *
	 * @param string $file File name or path.
	 */
	public static function context_for( string $file ): string {
		return match ( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			'php'        => 'php',
			'js', 'mjs'  => 'js',
			'json'       => 'json',
			default      => 'text',
		};
	}
}
