<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\ExitCode;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Plain PHP file operations in a temporary directory.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive::$numFiles.

/**
 * Safe ZIP extraction.
 *
 * Every entry is checked before anything is written: absolute paths, `..`
 * segments, symbolic links and server configuration files are rejected, and
 * the number of entries and the uncompressed size are capped.
 */
final class Archive {

	/** Files that change the web server or PHP configuration. */
	private const array FORBIDDEN_FILES = array( '.htaccess', '.htpasswd', '.user.ini', 'php.ini', 'web.config' );

	/**
	 * Extracts an archive into a new temporary directory.
	 *
	 * @param string $zip       Archive path.
	 * @param int    $max_files Maximum number of entries.
	 * @param int    $max_bytes Maximum uncompressed size.
	 * @return string Temporary directory. The caller deletes it with Archive::remove().
	 * @throws CliException When the archive is invalid or unsafe.
	 */
	public static function extract_to_temp( string $zip, int $max_files = 5000, int $max_bytes = 209715200 ): string {
		if ( ! class_exists( \ZipArchive::class ) ) {
			throw CliException::environment( __( 'The PHP zip extension is required.', 'skouerr-cli' ) );
		}

		$archive = new \ZipArchive();
		if ( true !== $archive->open( $zip, \ZipArchive::RDONLY ) ) {
			throw CliException::remote( __( 'The downloaded file is not a valid ZIP archive.', 'skouerr-cli' ), array( 'file' => $zip ) );
		}

		try {
			$entries = self::check_entries( $archive, $max_files, $max_bytes );
			$target  = self::temp_directory();

			foreach ( $entries as $index => $name ) {
				$path = $target . '/' . $name;
				if ( str_ends_with( $name, '/' ) ) {
					self::mkdir( $path );
					continue;
				}
				self::mkdir( dirname( $path ) );
				$entry  = $archive->getNameIndex( $index );
				$stream = false === $entry ? false : $archive->getStream( $entry );
				$handle = fopen( $path, 'xb' );
				if ( false === $stream || false === $handle ) {
					/* translators: %s: archive entry. */
					throw new CliException( sprintf( __( 'Cannot extract %s.', 'skouerr-cli' ), $name ) );
				}
				$stat = $archive->statIndex( $index );
				stream_copy_to_stream( $stream, $handle, false === $stat ? 0 : (int) $stat['size'] );
				fclose( $stream );
				fclose( $handle );
			}
		} finally {
			$archive->close();
		}//end try

		return $target;
	}

	/**
	 * Validates every entry and returns normalized names indexed by entry index.
	 *
	 * @param \ZipArchive $archive   Archive.
	 * @param int         $max_files Maximum number of entries.
	 * @param int         $max_bytes Maximum uncompressed size.
	 * @return array<int, string>
	 * @throws CliException When an entry is unsafe.
	 */
	public static function check_entries( \ZipArchive $archive, int $max_files, int $max_bytes ): array {
		if ( $archive->numFiles > $max_files ) {
			/* translators: %1$d: number of files, %2$d: maximum number of files. */
			throw CliException::remote( sprintf( __( 'Archive contains too many files (%1$d > %2$d).', 'skouerr-cli' ), $archive->numFiles, $max_files ) );
		}

		$entries = array();
		$total   = 0;
		for ( $index = 0; $index < $archive->numFiles; $index++ ) {
			$stat = $archive->statIndex( $index );
			if ( false === $stat ) {
				throw CliException::remote( __( 'Corrupted archive.', 'skouerr-cli' ) );
			}
			$name = self::normalize( $stat['name'] );

			$opsys = 0;
			$attr  = 0;
			if ( $archive->getExternalAttributesIndex( $index, $opsys, $attr ) && \ZipArchive::OPSYS_UNIX === $opsys ) {
				$mode = ( $attr >> 16 ) & 0170000;
				if ( 0120000 === $mode ) {
					/* translators: %s: archive entry. */
					throw CliException::remote( sprintf( __( 'Archive contains a symbolic link: %s.', 'skouerr-cli' ), $name ), array( 'entry' => $name ) );
				}
			}

			if ( in_array( strtolower( basename( $name ) ), self::FORBIDDEN_FILES, true ) ) {
				/* translators: %s: archive entry. */
				throw CliException::remote( sprintf( __( 'Archive contains a forbidden file: %s.', 'skouerr-cli' ), $name ), array( 'entry' => $name ) );
			}

			$total += (int) $stat['size'];
			if ( $total > $max_bytes ) {
				throw CliException::remote( __( 'Archive is too large once uncompressed.', 'skouerr-cli' ) );
			}

			$entries[ $index ] = $name;
		}//end for
		return $entries;
	}

	/**
	 * Normalizes an entry name and rejects unsafe ones.
	 *
	 * @param string $name Entry name.
	 * @throws CliException When the name is unsafe.
	 */
	public static function normalize( string $name ): string {
		$clean  = str_replace( '\\', '/', $name );
		$unsafe = '' === $clean
			|| str_contains( $clean, "\0" )
			|| str_starts_with( $clean, '/' )
			|| 1 === preg_match( '/^[a-zA-Z]:/', $clean )
			|| in_array( '..', explode( '/', $clean ), true );

		if ( $unsafe ) {
			/* translators: %s: archive entry. */
			throw CliException::remote( sprintf( __( 'Archive contains an unsafe path: %s.', 'skouerr-cli' ), $name ), array( 'entry' => $name ) );
		}
		return $clean;
	}

	/**
	 * Recursively deletes a directory created by extract_to_temp().
	 *
	 * @param string $directory Directory.
	 */
	public static function remove( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( $file->isDir() && ! $file->isLink() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}
		rmdir( $directory );
	}

	/**
	 * Creates a private temporary directory.
	 *
	 * @throws CliException When the directory cannot be created.
	 */
	private static function temp_directory(): string {
		$directory = rtrim( sys_get_temp_dir(), '/' ) . '/skouerr-' . bin2hex( random_bytes( 8 ) );
		if ( ! mkdir( $directory, 0700 ) ) {
			throw new CliException( __( 'Cannot create a temporary directory.', 'skouerr-cli' ), ExitCode::Environment );
		}
		return $directory;
	}

	/**
	 * Creates a directory and its parents.
	 *
	 * @param string $directory Directory.
	 * @throws CliException When the directory cannot be created.
	 */
	private static function mkdir( string $directory ): void {
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0700, true ) && ! is_dir( $directory ) ) {
			/* translators: %s: directory path. */
			throw new CliException( sprintf( __( 'Cannot create directory %s.', 'skouerr-cli' ), $directory ) );
		}
	}
}
