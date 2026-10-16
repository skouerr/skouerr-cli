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
use Skouerr\Cli\Console\Output;

// phpcs:disable WordPress.WP.AlternativeFunctions -- WP_Filesystem is not available in a CLI context without credentials.

/**
 * File writes confined to a root directory.
 *
 * - Every path is resolved inside the root: `..`, absolute paths and null bytes are rejected.
 * - Existing files are never overwritten without `--force`.
 * - In dry-run mode nothing is written, operations are only reported.
 * - Multi-file writes check every target before writing the first one.
 */
final class Filesystem {

	/**
	 * Root directory, without trailing slash.
	 *
	 * @var string
	 */
	public readonly string $root;

	/**
	 * Constructor.
	 *
	 * @param string $root   Root directory.
	 * @param Output $output Output receiving the file operations.
	 * @param bool   $force  Allow overwriting existing files.
	 */
	public function __construct( string $root, private readonly Output $output, private readonly bool $force = false ) {
		$this->root = rtrim( $root, '/\\' );
	}

	/**
	 * Absolute path of a path relative to the root.
	 *
	 * @param string $relative Relative path.
	 * @throws CliException When the path escapes the root.
	 */
	public function path( string $relative ): string {
		$relative = str_replace( '\\', '/', $relative );

		if ( '' === $relative || str_contains( $relative, "\0" ) || str_starts_with( $relative, '/' ) || 1 === preg_match( '/^[a-zA-Z]:/', $relative ) ) {
			/* translators: %s: path. */
			throw CliException::invalid( sprintf( __( 'Unsafe path "%s".', 'skouerr-cli' ), $relative ), array( 'path' => $relative ) );
		}

		$segments = array();
		foreach ( explode( '/', $relative ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				/* translators: %s: path. */
				throw CliException::invalid( sprintf( __( 'Unsafe path "%s".', 'skouerr-cli' ), $relative ), array( 'path' => $relative ) );
			}
			$segments[] = $segment;
		}

		if ( empty( $segments ) ) {
			/* translators: %s: path. */
			throw CliException::invalid( sprintf( __( 'Unsafe path "%s".', 'skouerr-cli' ), $relative ), array( 'path' => $relative ) );
		}

		return $this->root . '/' . implode( '/', $segments );
	}

	/**
	 * Tells whether a relative path exists.
	 *
	 * @param string $relative Relative path.
	 */
	public function exists( string $relative ): bool {
		return file_exists( $this->path( $relative ) );
	}

	/**
	 * Writes one file.
	 *
	 * @param string $relative Relative path.
	 * @param string $contents Contents.
	 * @return string Absolute path.
	 */
	public function write( string $relative, string $contents ): string {
		$paths = $this->write_many( array( $relative => $contents ) );
		return $paths[0];
	}

	/**
	 * Writes several files after checking that none of them conflicts.
	 *
	 * @param array<string, string> $files Contents indexed by relative path.
	 * @return list<string> Absolute paths.
	 * @throws CliException On conflict or write error.
	 */
	public function write_many( array $files ): array {
		$plan = array();
		foreach ( $files as $relative => $contents ) {
			$absolute = $this->path( (string) $relative );
			if ( is_link( $absolute ) || is_dir( $absolute ) ) {
				/* translators: %s: path. */
				throw CliException::invalid( sprintf( __( 'Refusing to write over a link or directory: %s.', 'skouerr-cli' ), $absolute ), array( 'path' => $absolute ) );
			}
			$this->assert_inside_root( $absolute );
			$exists = file_exists( $absolute );
			if ( $exists && ! $this->force ) {
				throw CliException::conflict( $absolute );
			}
			$plan[] = array( $absolute, $contents, $exists ? 'overwrite' : 'create' );
		}

		$paths = array();
		foreach ( $plan as [ $absolute, $contents, $action ] ) {
			if ( $this->output->is_patch() ) {
				$before = 'overwrite' === $action ? (string) file_get_contents( $absolute ) : null;
				$this->output->diff( $this->patch_path( $absolute ), $before, $contents );
			} elseif ( ! $this->output->is_dry_run() ) {
				$this->ensure_directory( dirname( $absolute ) );
				if ( false === file_put_contents( $absolute, $contents ) ) {
					/* translators: %s: file path. */
					throw new CliException( sprintf( __( 'Cannot write %s.', 'skouerr-cli' ), $absolute ), ExitCode::Error, array( 'path' => $absolute ) );
				}
			}
			$this->output->file( $action, $absolute );
			$paths[] = $absolute;
		}
		return $paths;
	}

	/**
	 * Deletes files, then the directories left empty.
	 *
	 * @param list<string> $relatives Relative paths of files.
	 * @return list<string> Absolute paths deleted.
	 * @throws CliException When a path is not a file or cannot be deleted.
	 */
	public function delete_many( array $relatives ): array {
		$plan = array();
		foreach ( $relatives as $relative ) {
			$absolute = $this->path( $relative );
			$this->assert_inside_root( $absolute );
			if ( ! is_file( $absolute ) && ! is_link( $absolute ) ) {
				/* translators: %s: path. */
				throw CliException::invalid( sprintf( __( 'Not a file: %s.', 'skouerr-cli' ), $absolute ), array( 'path' => $absolute ) );
			}
			$plan[] = $absolute;
		}

		foreach ( $plan as $absolute ) {
			if ( $this->output->is_patch() ) {
				$this->output->diff( $this->patch_path( $absolute ), (string) file_get_contents( $absolute ), null );
			} elseif ( ! $this->output->is_dry_run() ) {
				if ( ! unlink( $absolute ) ) {
					/* translators: %s: file path. */
					throw new CliException( sprintf( __( 'Cannot delete %s.', 'skouerr-cli' ), $absolute ), ExitCode::Error, array( 'path' => $absolute ) );
				}
				$this->remove_empty_parents( dirname( $absolute ) );
			}
			$this->output->file( 'delete', $absolute );
		}
		return $plan;
	}

	/**
	 * Files of a directory, recursively.
	 *
	 * @param string $relative Relative directory.
	 * @return array<string, string> Contents indexed by path relative to the root.
	 */
	public function read_tree( string $relative ): array {
		$directory = $this->path( $relative );
		if ( ! is_dir( $directory ) ) {
			return array();
		}
		$files    = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( $file->isFile() && ! $file->isLink() ) {
				$files[ $this->relative( $file->getPathname() ) ] = (string) file_get_contents( $file->getPathname() );
			}
		}
		ksort( $files );
		return $files;
	}

	/**
	 * Path relative to the root.
	 *
	 * @param string $absolute Absolute path inside the root.
	 */
	public function relative( string $absolute ): string {
		return ltrim( substr( $absolute, strlen( $this->root ) ), '/' );
	}

	/**
	 * Path written in a patch: relative to the Git repository holding the root,
	 * or to the root itself outside a repository.
	 *
	 * `git apply` resolves paths from the repository root and silently skips the
	 * ones outside the current directory: theme-relative paths would be ignored
	 * when the theme lives in a sub-folder of the repository (Bedrock).
	 *
	 * @param string $absolute Absolute path inside the root.
	 */
	public function patch_path( string $absolute ): string {
		$repository = self::repository_root( $this->root );
		if ( null === $repository ) {
			return $this->relative( $absolute );
		}
		return ltrim( substr( $absolute, strlen( $repository ) ), '/' );
	}

	/**
	 * Closest directory holding a `.git` entry (folder, or file for worktrees and submodules).
	 *
	 * @param string $directory Directory to start from.
	 * @return string|null Repository root, without trailing slash, or null outside a repository.
	 */
	public static function repository_root( string $directory ): ?string {
		$directory = rtrim( str_replace( '\\', '/', $directory ), '/' );
		while ( '' !== $directory ) {
			if ( file_exists( $directory . '/.git' ) ) {
				return $directory;
			}
			$parent = dirname( $directory );
			if ( $parent === $directory ) {
				break;
			}
			$directory = $parent;
		}
		return null;
	}

	/**
	 * Removes empty directories from a directory up to the root (excluded).
	 *
	 * @param string $directory Absolute directory.
	 */
	private function remove_empty_parents( string $directory ): void {
		while ( str_starts_with( $directory, $this->root . '/' ) && is_dir( $directory ) && array() === array_diff( (array) scandir( $directory ), array( '.', '..' ) ) ) {
			rmdir( $directory );
			$directory = dirname( $directory );
		}
	}

	/**
	 * Copies a directory tree into the root.
	 *
	 * @param string $source      Source directory (absolute).
	 * @param string $destination Destination directory, relative to the root. Empty for the root itself.
	 * @return list<string> Absolute paths written.
	 */
	public function copy_tree( string $source, string $destination = '' ): array {
		$files    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( ! $file->isFile() || $file->isLink() ) {
				continue;
			}
			$relative = ltrim( substr( $file->getPathname(), strlen( $source ) ), '/\\' );
			$target   = '' === $destination ? $relative : $destination . '/' . $relative;
			$contents = file_get_contents( $file->getPathname() );
			if ( false === $contents ) {
				/* translators: %s: file path. */
				throw new CliException( sprintf( __( 'Cannot read %s.', 'skouerr-cli' ), $file->getPathname() ) );
			}
			$files[ $target ] = $contents;
		}
		return $this->write_many( $files );
	}

	/**
	 * Checks that the closest existing parent of a path resolves inside the root.
	 * Protects against symbolic links pointing outside of the root.
	 *
	 * @param string $absolute Absolute path.
	 * @throws CliException When the path resolves outside of the root.
	 */
	private function assert_inside_root( string $absolute ): void {
		$root = realpath( $this->root );
		if ( false === $root ) {
			return;
			// The root does not exist yet: it will be created, nothing can point outside.
		}

		$parent = dirname( $absolute );
		while ( ! file_exists( $parent ) && dirname( $parent ) !== $parent ) {
			$parent = dirname( $parent );
		}
		$resolved = realpath( $parent );

		if ( false === $resolved || ( $resolved !== $root && ! str_starts_with( $resolved . '/', $root . '/' ) ) ) {
			/* translators: %1$s: path, %2$s: root directory. */
			throw CliException::invalid( sprintf( __( 'Path %1$s resolves outside of %2$s.', 'skouerr-cli' ), $absolute, $this->root ), array( 'path' => $absolute ) );
		}
	}

	/**
	 * Creates a directory and its parents.
	 *
	 * @param string $directory Absolute directory path.
	 * @throws CliException When the directory cannot be created.
	 */
	private function ensure_directory( string $directory ): void {
		if ( is_dir( $directory ) ) {
			return;
		}
		if ( ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
			/* translators: %s: directory path. */
			throw new CliException( sprintf( __( 'Cannot create directory %s.', 'skouerr-cli' ), $directory ), ExitCode::Error, array( 'path' => $directory ) );
		}
	}
}
