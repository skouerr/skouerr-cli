<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

/**
 * Plans the update of the core files of a Skouerr theme (`inc/core/`).
 *
 * The reference files ship with the CLI (`templates/core/`), with a manifest:
 *
 *     {"version": "2.0.0",
 *      "history": {"class-skouerr-loader.php": ["<sha256 of a previous starter version>", …]},
 *      "removed": {"class-skouerr-mail.php": {"symbols": ["Skouerr_Mail"]}}}
 *
 * The theme records what was installed in `inc/core/skouerr-core.json`:
 *
 *     {"version": "2.0.0", "files": {"class-skouerr-loader.php": "<sha256>"}}
 *
 * A theme file is "pristine" when its hash is the installed one, or the hash of a
 * known starter version. Pristine files are updated; files changed in the project
 * are left alone unless forced.
 */
final class CoreUpdate {

	/** Manifest of the theme, in its core folder. */
	public const string MANIFEST = 'skouerr-core.json';

	/** Manifest of the reference files, in the CLI core template folder. */
	public const string REFERENCE_MANIFEST = 'manifest.json';

	/** File missing in the theme: created. */
	public const string CREATE = 'create';

	/** Pristine file with older contents: replaced. */
	public const string UPDATE = 'update';

	/** Same contents as the reference. */
	public const string UP_TO_DATE = 'up-to-date';

	/** File changed in the project: left alone. */
	public const string MODIFIED = 'modified';

	/** File changed in the project, replaced because of --force. */
	public const string OVERWRITE = 'overwrite';

	/** File removed from the core: deleted. */
	public const string REMOVE = 'remove';

	/** File removed from the core but changed in the project: left alone. */
	public const string KEEP = 'keep';

	/**
	 * Hash of a file, insensitive to line endings.
	 *
	 * @param string $contents Contents.
	 */
	public static function hash( string $contents ): string {
		return hash( 'sha256', str_replace( "\r\n", "\n", $contents ) );
	}

	/**
	 * Reads a JSON manifest.
	 *
	 * @param string $path Manifest path.
	 * @return array<string, mixed> Empty when the file is missing or invalid.
	 */
	public static function read_manifest( string $path ): array {
		if ( ! is_file( $path ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Plans the update.
	 *
	 * @param array<string, string> $reference Reference contents by file name.
	 * @param array<string, mixed>  $meta      Reference manifest (history, removed).
	 * @param array<string, string> $current   Theme contents by file name (reference and removed files only).
	 * @param array<string, mixed>  $installed Theme manifest.
	 * @param bool                  $force     Replace or delete files changed in the project.
	 * @return list<array{file: string, status: string}>
	 */
	public static function plan( array $reference, array $meta, array $current, array $installed, bool $force ): array {
		$plan = array();
		foreach ( $reference as $file => $contents ) {
			if ( ! array_key_exists( $file, $current ) ) {
				$status = self::CREATE;
			} elseif ( self::hash( $current[ $file ] ) === self::hash( $contents ) ) {
				$status = self::UP_TO_DATE;
			} elseif ( self::pristine( $file, $current[ $file ], $meta, $installed ) ) {
				$status = self::UPDATE;
			} else {
				$status = $force ? self::OVERWRITE : self::MODIFIED;
			}
			$plan[] = array(
				'file'   => $file,
				'status' => $status,
			);
		}

		$removed = is_array( $meta['removed'] ?? null ) ? $meta['removed'] : array();
		foreach ( array_keys( $removed ) as $file ) {
			$file = (string) $file;
			if ( ! array_key_exists( $file, $current ) || array_key_exists( $file, $reference ) ) {
				continue;
			}
			$plan[] = array(
				'file'   => $file,
				'status' => $force || self::pristine( $file, $current[ $file ], $meta, $installed ) ? self::REMOVE : self::KEEP,
			);
		}

		return $plan;
	}

	/**
	 * Theme manifest after the update.
	 *
	 * Only files equal to a reference version are recorded: a file changed in the
	 * project keeps its previous entry, so it is never mistaken for a pristine one.
	 *
	 * @param list<array{file: string, status: string}> $plan      Plan.
	 * @param array<string, string>                     $reference Reference contents by file name.
	 * @param array<string, mixed>                      $installed Previous theme manifest.
	 * @param string                                    $version   Reference version.
	 * @return array{version: string, files: array<string, string>}
	 */
	public static function manifest( array $plan, array $reference, array $installed, string $version ): array {
		$previous = is_array( $installed['files'] ?? null ) ? $installed['files'] : array();
		$files    = array();
		foreach ( $plan as $item ) {
			$file = $item['file'];
			if ( in_array( $item['status'], array( self::CREATE, self::UPDATE, self::UP_TO_DATE, self::OVERWRITE ), true ) ) {
				$files[ $file ] = self::hash( $reference[ $file ] );
			} elseif ( self::MODIFIED === $item['status'] && isset( $previous[ $file ] ) ) {
				$files[ $file ] = (string) $previous[ $file ];
			}
		}
		ksort( $files );

		return array(
			'version' => $version,
			'files'   => $files,
		);
	}

	/**
	 * Removes the lines of a PHP file that require a core file.
	 *
	 * @param string $php  PHP source (usually functions.php).
	 * @param string $file Core file name.
	 * @return string Source without those lines.
	 */
	public static function remove_require( string $php, string $file ): string {
		$pattern = '#^[ \t]*(?://[ \t]*)?(?:require|include)(?:_once)?\b[^\n]*/inc/core/' . preg_quote( $file, '#' ) . '[\'"][^\n]*\n#m';
		return (string) preg_replace( $pattern, '', $php );
	}

	/**
	 * Tells whether a theme file is a known, unchanged version of the core file.
	 *
	 * @param string               $file      File name.
	 * @param string               $contents  Theme contents.
	 * @param array<string, mixed> $meta      Reference manifest.
	 * @param array<string, mixed> $installed Theme manifest.
	 */
	private static function pristine( string $file, string $contents, array $meta, array $installed ): bool {
		$hash  = self::hash( $contents );
		$files = is_array( $installed['files'] ?? null ) ? $installed['files'] : array();
		if ( isset( $files[ $file ] ) && $files[ $file ] === $hash ) {
			return true;
		}
		$history = is_array( $meta['history'][ $file ] ?? null ) ? $meta['history'][ $file ] : array();
		return in_array( $hash, $history, true );
	}
}
