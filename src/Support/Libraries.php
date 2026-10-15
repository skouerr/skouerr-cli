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
 * Access rules for Skouerr libraries.
 *
 * - `SKOUERR_ALLOWED_LIBRARIES` (array or comma-separated string): when defined,
 *   only these libraries can be used, `--library` included.
 * - `SKOUERR_LIBRARY_TOKENS` (array: library URL => token): bearer token sent to a
 *   private library, and to that library only. The `skouerr_library_token`
 *   filter can provide it too.
 */
final class Libraries {

	/**
	 * Checks that a library may be used.
	 *
	 * @param string $url Library URL.
	 * @throws CliException When an allowlist is defined and the library is not in it.
	 */
	public static function assert_allowed( string $url ): void {
		$allowed = self::allowed();
		if ( null === $allowed ) {
			return;
		}
		foreach ( $allowed as $library ) {
			if ( self::same_library( $url, $library ) ) {
				return;
			}
		}
		throw CliException::invalid(
			/* translators: %s: library URL. */
			sprintf( __( 'Library %s is not allowed (SKOUERR_ALLOWED_LIBRARIES).', 'skouerr-cli' ), $url ),
			array(
				'library' => $url,
				'choices' => $allowed,
			)
		);
	}

	/**
	 * Allowed libraries, null when there is no allowlist.
	 *
	 * @return list<string>|null
	 */
	public static function allowed(): ?array {
		if ( ! defined( 'SKOUERR_ALLOWED_LIBRARIES' ) ) {
			return null;
		}
		$value = constant( 'SKOUERR_ALLOWED_LIBRARIES' );
		$list  = is_array( $value ) ? $value : explode( ',', (string) $value );
		return array_values( array_filter( array_map( static fn( mixed $item ): string => rtrim( trim( (string) $item ), '/' ), $list ), static fn( string $item ): bool => '' !== $item ) );
	}

	/**
	 * HTTP headers for a library (authorization of private libraries).
	 *
	 * @param string $url Library URL.
	 * @return array<string, string>
	 */
	public static function headers( string $url ): array {
		$token = null;
		if ( defined( 'SKOUERR_LIBRARY_TOKENS' ) && is_array( constant( 'SKOUERR_LIBRARY_TOKENS' ) ) ) {
			foreach ( constant( 'SKOUERR_LIBRARY_TOKENS' ) as $library => $value ) {
				if ( self::same_library( $url, (string) $library ) ) {
					$token = (string) $value;
				}
			}
		}
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the bearer token sent to a Skouerr library.
			 *
			 * @param string|null $token Token.
			 * @param string      $url   Library URL.
			 */
			$token = apply_filters( 'skouerr_library_token', $token, $url );
		}
		return is_string( $token ) && '' !== $token ? array( 'Authorization' => 'Bearer ' . $token ) : array();
	}

	/**
	 * Tells whether a URL belongs to a library: same scheme, host, port, and path prefix.
	 *
	 * @param string $url     URL.
	 * @param string $library Library URL.
	 */
	public static function same_library( string $url, string $library ): bool {
		$a = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$b = parse_url( rtrim( $library, '/' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $a ) || ! is_array( $b ) ) {
			return false;
		}
		foreach ( array( 'scheme', 'host', 'port' ) as $part ) {
			if ( strtolower( (string) ( $a[ $part ] ?? '' ) ) !== strtolower( (string) ( $b[ $part ] ?? '' ) ) ) {
				return false;
			}
		}
		$path   = rtrim( (string) ( $a['path'] ?? '' ), '/' ) . '/';
		$prefix = rtrim( (string) ( $b['path'] ?? '' ), '/' ) . '/';
		return str_starts_with( $path, $prefix );
	}
}
