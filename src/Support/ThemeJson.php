<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

/**
 * Helpers for theme.json files.
 */
final class ThemeJson {

	/**
	 * Merges user data over theme data.
	 *
	 * Objects are merged recursively; lists (palettes, font sizes…) are replaced
	 * as a whole, like WordPress does when it resolves the user origin.
	 *
	 * @param array<mixed> $base     Theme data.
	 * @param array<mixed> $override User data.
	 * @return array<mixed>
	 */
	public static function merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && ! array_is_list( $value ) && is_array( $base[ $key ] ?? null ) && ! array_is_list( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Dotted paths of the leaves changed by an override, two levels deep (e.g. "styles.color").
	 *
	 * @param array<mixed> $override User data.
	 * @return list<string>
	 */
	public static function changed_paths( array $override ): array {
		$paths = array();
		foreach ( array( 'settings', 'styles' ) as $root ) {
			if ( ! is_array( $override[ $root ] ?? null ) ) {
				continue;
			}
			foreach ( array_keys( $override[ $root ] ) as $key ) {
				$paths[] = $root . '.' . $key;
			}
		}
		return $paths;
	}

	/**
	 * Encodes theme.json with tab indentation, like the WordPress themes.
	 *
	 * @param array<mixed> $data Data.
	 */
	public static function encode( array $data ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Used without WordPress in tests.
		$json = (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$json = (string) preg_replace_callback(
			'/^( {4})+/m',
			static fn( array $match ): string => str_repeat( "\t", intdiv( strlen( $match[0] ), 4 ) ),
			$json
		);
		return $json . "\n";
	}
}
