<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * Backward compatibility with Skouerr CLI 1.x for code outside this plugin
 * (for example the `skouerr-theme` commands of older starter themes).
 *
 * @package Skouerr_CLI
 */

if ( class_exists( 'SK_CLI_Input', false ) || ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Prompt helpers of Skouerr CLI 1.x.
 *
 * @deprecated 2.0.0 Prompts are handled by the commands themselves.
 */
class SK_CLI_Input extends WP_CLI {

	/**
	 * Asks a free-text question.
	 *
	 * @param string       $name         Question.
	 * @param string|null  $default      Default answer.
	 * @param array<mixed> $autocomplete Ignored since 2.0.
	 * @return string Answer.
	 */
	public static function ask( string $name, ?string $default = null, array $autocomplete = array() ): string {
		unset( $autocomplete );
		return trim( (string) \cli\prompt( $name, $default ?? false ) );
	}

	/**
	 * Asks to choose one value.
	 *
	 * Like 1.x (Symfony ChoiceQuestion): returns the key when the choices have
	 * string keys, the label otherwise.
	 *
	 * @param string                    $name    Question.
	 * @param array<int|string, string> $choices Choices.
	 * @return string Key or label.
	 */
	public static function select( string $name, array $choices ): string {
		$key = \cli\menu( $choices, null, $name );
		return self::has_string_keys( $choices ) ? (string) $key : (string) $choices[ $key ];
	}

	/**
	 * Asks to choose several values (comma-separated numbers or keys).
	 *
	 * @param string                    $name    Question.
	 * @param array<int|string, string> $choices Choices.
	 * @return list<int|string> Keys or labels, as select().
	 */
	public static function select_multiple( string $name, array $choices ): array {
		$keys   = array_keys( $choices );
		$labels = array();
		foreach ( $keys as $position => $key ) {
			$labels[] = sprintf( '  %d. %s', $position + 1, $choices[ $key ] );
		}
		$answer = (string) \cli\prompt( $name . PHP_EOL . implode( PHP_EOL, $labels ) . PHP_EOL . __( 'Comma-separated numbers', 'skouerr-cli' ) );

		$values = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $answer ) ), static fn( string $item ): bool => '' !== $item ) as $item ) {
			$index = ctype_digit( $item ) ? (int) $item - 1 : array_search( $item, $keys, true );
			if ( is_int( $index ) && isset( $keys[ $index ] ) ) {
				$values[] = self::has_string_keys( $choices ) ? $keys[ $index ] : $choices[ $keys[ $index ] ];
			}
		}
		return $values;
	}

	/**
	 * Tells whether an array has at least one string key.
	 *
	 * @param array<int|string, string> $choices Choices.
	 */
	private static function has_string_keys( array $choices ): bool {
		return array() !== array_filter( array_keys( $choices ), 'is_string' );
	}
}
