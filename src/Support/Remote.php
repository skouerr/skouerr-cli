<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

use Skouerr\Cli\Console\CliException;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Temporary files outside of WordPress.

/**
 * HTTP access to Skouerr servers.
 *
 * HTTPS is mandatory except for local development hosts. Responses are
 * checked (status code, size, JSON validity, optional SHA-256 checksum).
 */
final class Remote {

	/** Hosts allowed over plain HTTP. */
	private const array LOCAL_SUFFIXES = array( '.localhost', '.local', '.test', '.ddev.site', '.lndo.site' );

	/**
	 * Checks that a URL can be used to download code.
	 *
	 * @param string $url URL.
	 * @throws CliException When the URL is not allowed.
	 */
	public static function assert_safe_url( string $url ): void {
		$parts  = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );

		if ( '' === $host || ! in_array( $scheme, array( 'http', 'https' ), true ) || isset( $parts['user'] ) ) {
			/* translators: %s: URL. */
			throw CliException::invalid( sprintf( __( 'Invalid URL "%s".', 'skouerr-cli' ), $url ), array( 'url' => $url ) );
		}

		if ( 'http' === $scheme && ! self::is_local_host( $host ) ) {
			throw CliException::invalid(
				/* translators: %s: host name. */
				sprintf( __( 'Refusing plain HTTP for %s: use HTTPS (HTTP is only allowed for local hosts).', 'skouerr-cli' ), $host ),
				array( 'url' => $url )
			);
		}
	}

	/**
	 * Tells whether a host is a local development host.
	 *
	 * @param string $host Host name.
	 */
	public static function is_local_host( string $host ): bool {
		$host = trim( $host, '[]' );
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}
		foreach ( self::LOCAL_SUFFIXES as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fetches and decodes a JSON document.
	 *
	 * @param string                $url     URL.
	 * @param array<string, string> $headers Extra headers.
	 * @return array<mixed>
	 * @throws CliException On network error or invalid JSON.
	 */
	public static function get_json( string $url, array $headers = array() ): array {
		self::assert_safe_url( $url );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 30,
				'limit_response_size' => 5 * MB_IN_BYTES,
				'headers'             => array_merge( array( 'Accept' => 'application/json' ), $headers ),
				// Never forward a token to another host through a redirect.
				'redirection'         => isset( $headers['Authorization'] ) ? 0 : 5,
			)
		);
		self::assert_ok( $response, $url );

		try {
			$data = json_decode( wp_remote_retrieve_body( $response ), true, 64, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			/* translators: %s: URL. */
			throw CliException::remote( sprintf( __( 'Invalid JSON returned by %s.', 'skouerr-cli' ), $url ), array( 'url' => $url ) );
		}
		if ( ! is_array( $data ) ) {
			/* translators: %s: URL. */
			throw CliException::remote( sprintf( __( 'Unexpected response from %s.', 'skouerr-cli' ), $url ), array( 'url' => $url ) );
		}
		return $data;
	}

	/**
	 * Downloads a file into a temporary file.
	 *
	 * The SHA-256 checksum is verified when it is passed by the caller or
	 * announced by the server in the `X-Skouerr-Sha256` header.
	 *
	 * @param string                $url       URL.
	 * @param string|null           $sha256    Expected checksum.
	 * @param int                   $max_bytes Maximum size.
	 * @param array<string, string> $headers   Extra headers.
	 * @return array{path: string, sha256: string, verified: bool}
	 * @throws CliException On network error or checksum mismatch.
	 */
	public static function download( string $url, ?string $sha256 = null, int $max_bytes = 104857600, array $headers = array() ): array {
		self::assert_safe_url( $url );

		$path = rtrim( sys_get_temp_dir(), '/' ) . '/skouerr-' . bin2hex( random_bytes( 8 ) ) . '.zip';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 120,
				'stream'              => true,
				'filename'            => $path,
				'limit_response_size' => $max_bytes,
				'headers'             => $headers,
				'redirection'         => isset( $headers['Authorization'] ) ? 0 : 5,
			)
		);

		try {
			self::assert_ok( $response, $url );

			$header   = wp_remote_retrieve_header( $response, 'x-skouerr-sha256' );
			$header   = is_array( $header ) ? (string) reset( $header ) : $header;
			$actual   = (string) hash_file( 'sha256', $path );
			$expected = array_filter( array( $sha256, strtolower( trim( $header ) ) ) );
			foreach ( $expected as $checksum ) {
				if ( ! hash_equals( strtolower( $checksum ), $actual ) ) {
					throw CliException::remote(
						__( 'Checksum mismatch: the downloaded archive is not the expected one.', 'skouerr-cli' ),
						array(
							'url'      => $url,
							'expected' => $checksum,
							'actual'   => $actual,
						)
					);
				}
			}
		} catch ( CliException $e ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
			throw $e;
		}//end try

		return array(
			'path'     => $path,
			'sha256'   => $actual,
			'verified' => ! empty( $expected ),
		);
	}

	/**
	 * Checks an HTTP response.
	 *
	 * @param array<string, mixed>|\WP_Error $response Response.
	 * @param string                         $url      URL.
	 * @throws CliException When the request failed.
	 */
	private static function assert_ok( array|\WP_Error $response, string $url ): void {
		if ( is_wp_error( $response ) ) {
			throw CliException::remote(
				/* translators: %1$s: URL, %2$s: error message. */
				sprintf( __( 'Request to %1$s failed: %2$s', 'skouerr-cli' ), $url, $response->get_error_message() ),
				array( 'url' => $url )
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			throw CliException::remote(
				/* translators: %1$s: URL, %2$d: HTTP status code. */
				sprintf( __( 'Request to %1$s returned HTTP %2$d.', 'skouerr-cli' ), $url, $code ),
				array(
					'url'    => $url,
					'status' => $code,
				)
			);
		}
	}
}
