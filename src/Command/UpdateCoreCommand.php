<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\ExitCode;
use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Support\CoreUpdate;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Theme;

/**
 * `wp skouerr update:core`: updates the core files of the theme (`inc/core/`) to the version shipped with the CLI.
 */
final class UpdateCoreCommand extends AbstractCommand {

	/** Core folder, relative to the theme. */
	public const string FOLDER = 'inc/core';

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'update:core';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Update the core files of the theme (inc/core: loader, block renderer, dev tools, setup) to the version shipped with the CLI.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function side_effects(): array {
		return array( self::EFFECT_FILES );
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr update:core --format=patch',
			'skouerr update:core --yes',
			'skouerr update:core --force --format=patch',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array( Theme::parameter() );
	}

	/**
	 * Folder of the reference core files.
	 */
	public static function reference_directory(): string {
		return SKOUERR_CLI_PATH . '/templates/core';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$directory = Theme::directory( $input->get( 'theme' ) );
		if ( null === Theme::loader( $directory ) ) {
			/* translators: %s: theme directory. */
			throw CliException::environment( sprintf( __( '%s is not a Skouerr theme: no core loader in inc/core/.', 'skouerr-cli' ), $directory ) );
		}

		$source    = self::reference_directory();
		$meta      = CoreUpdate::read_manifest( $source . '/' . CoreUpdate::REFERENCE_MANIFEST );
		$version   = (string) ( $meta['version'] ?? '' );
		$reference = self::read_files( $source, array( CoreUpdate::REFERENCE_MANIFEST ) );
		if ( '' === $version || empty( $reference ) ) {
			throw CliException::environment( __( 'The core files shipped with the CLI are missing.', 'skouerr-cli' ) );
		}

		$core = $directory . '/' . self::FOLDER;

		// Older layout (inc/core/loader.php…): the files and the requires of functions.php differ, no automatic migration.
		$renamed = is_array( $meta['renamed'] ?? null ) ? $meta['renamed'] : array();
		foreach ( $renamed as $old => $new ) {
			if ( is_file( $core . '/' . $old ) ) {
				throw CliException::environment(
					/* translators: %1$s: old core file, %2$s: current core file. */
					sprintf( __( 'This theme uses an older Skouerr core layout (inc/core/%1$s, now %2$s): update:core does not migrate it. Port the theme by hand from the starter theme.', 'skouerr-cli' ), $old, (string) $new ),
					array( 'path' => $core . '/' . $old )
				);
			}
		}

		$installed = CoreUpdate::read_manifest( $core . '/' . CoreUpdate::MANIFEST );
		$removed   = is_array( $meta['removed'] ?? null ) ? $meta['removed'] : array();
		$current   = array();
		foreach ( array_merge( array_keys( $reference ), array_map( 'strval', array_keys( $removed ) ) ) as $file ) {
			if ( is_file( $core . '/' . $file ) ) {
				$current[ $file ] = (string) file_get_contents( $core . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		$force = $input->flag( 'force' );
		$plan  = CoreUpdate::plan( $reference, $meta, $current, $installed, $force );

		$writes  = array();
		$deletes = array();
		foreach ( $plan as $item ) {
			$relative = self::FOLDER . '/' . $item['file'];
			if ( in_array( $item['status'], array( CoreUpdate::CREATE, CoreUpdate::UPDATE, CoreUpdate::OVERWRITE ), true ) ) {
				$writes[ $relative ] = $reference[ $item['file'] ];
			} elseif ( CoreUpdate::REMOVE === $item['status'] ) {
				$deletes[] = $relative;
			} else {
				self::warn_kept( $output, $item['status'], $relative );
			}
		}

		// A deleted core file must not stay required by functions.php: that would be a fatal error.
		$functions = $directory . '/functions.php';
		if ( ! empty( $deletes ) && is_file( $functions ) ) {
			$php     = (string) file_get_contents( $functions ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$updated = $php;
			foreach ( $deletes as $relative ) {
				$updated = CoreUpdate::remove_require( $updated, basename( $relative ) );
				if ( str_contains( $updated, '/' . $relative ) ) {
					throw CliException::invalid(
						/* translators: %s: file path. */
						sprintf( __( 'functions.php still loads %s in a way the CLI cannot remove: remove that line by hand, then run again.', 'skouerr-cli' ), $relative ),
						array( 'path' => $functions )
					);
				}
			}
			if ( $updated !== $php ) {
				$writes['functions.php'] = $updated;
			}
		}
		$this->warn_symbols( $output, $directory, $plan, $removed );

		// A new core file is only loaded once functions.php requires it.
		$loaded = is_file( $functions ) ? (string) file_get_contents( $functions ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		foreach ( $plan as $item ) {
			if ( CoreUpdate::CREATE === $item['status'] && ! str_contains( $loaded, '/' . self::FOLDER . '/' . $item['file'] ) ) {
				/* translators: %s: file path. */
				$output->next_step( sprintf( __( 'Load the new core file from functions.php: require_once get_template_directory() . \'/%s\';', 'skouerr-cli' ), self::FOLDER . '/' . $item['file'] ) );
			}
		}

		$manifest = CoreUpdate::manifest( $plan, $reference, $installed, $version );
		if ( $manifest !== array(
			'version' => (string) ( $installed['version'] ?? '' ),
			'files'   => is_array( $installed['files'] ?? null ) ? $installed['files'] : array(),
		) ) {
			$writes[ self::FOLDER . '/' . CoreUpdate::MANIFEST ] = (string) wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
		}

		$output->set(
			'core',
			array(
				'from'  => isset( $installed['version'] ) ? (string) $installed['version'] : null,
				'to'    => $version,
				'files' => $plan,
			)
		);

		if ( empty( $writes ) && empty( $deletes ) ) {
			/* translators: %s: core version. */
			return sprintf( __( 'Core is up to date (%s).', 'skouerr-cli' ), $version );
		}

		$risky = ! empty( $deletes ) || in_array( CoreUpdate::OVERWRITE, array_column( $plan, 'status' ), true );
		/* translators: %1$d: number of files written, %2$d: number of files deleted. */
		if ( ! $output->is_dry_run() && ! $input->confirm( sprintf( __( 'Write %1$d file(s) and delete %2$d file(s) in the theme?', 'skouerr-cli' ), count( $writes ), count( $deletes ) ), ! $risky ) ) {
			throw new CliException( __( 'Core update aborted. Pass --yes to confirm in non-interactive mode.', 'skouerr-cli' ), ExitCode::Aborted );
		}

		$filesystem = new Filesystem( $directory, $output, true );
		$filesystem->write_many( $writes );
		if ( ! empty( $deletes ) ) {
			$filesystem->delete_many( $deletes );
		}

		$output->next_step( __( 'Load a page of the site and the block editor to check the theme.', 'skouerr-cli' ) );

		$kept = count( array_intersect( array_column( $plan, 'status' ), array( CoreUpdate::MODIFIED, CoreUpdate::KEEP ) ) );
		if ( $kept > 0 ) {
			/* translators: %1$s: core version, %2$d: number of files left as is. */
			return sprintf( __( 'Core partially updated to %1$s: %2$d file(s) changed in the project left as is.', 'skouerr-cli' ), $version, $kept );
		}

		/* translators: %s: core version. */
		return sprintf( __( 'Core updated to %s.', 'skouerr-cli' ), $version );
	}

	/**
	 * Warns about a core file left alone because it was changed in the project.
	 *
	 * @param Output $output   Output.
	 * @param string $status   Plan status.
	 * @param string $relative Path relative to the theme.
	 */
	private static function warn_kept( Output $output, string $status, string $relative ): void {
		if ( CoreUpdate::MODIFIED === $status ) {
			/* translators: %s: file path. */
			$output->warning( sprintf( __( '%s was changed in the project: not updated. Move the project code to inc/ (see the core filters), or review the diff with --force --format=patch and run again with --force.', 'skouerr-cli' ), $relative ) );
		} elseif ( CoreUpdate::KEEP === $status ) {
			/* translators: %s: file path. */
			$output->warning( sprintf( __( '%s is no longer part of the core but was changed in the project: not deleted. Run again with --force to delete it.', 'skouerr-cli' ), $relative ) );
		}
	}

	/**
	 * Files of a folder (not recursive).
	 *
	 * @param string       $directory Folder.
	 * @param list<string> $exclude   File names to skip.
	 * @return array<string, string> Contents by file name.
	 */
	private static function read_files( string $directory, array $exclude ): array {
		$files = array();
		$paths = glob( $directory . '/*' );
		foreach ( false === $paths ? array() : $paths as $path ) {
			if ( is_file( $path ) && ! in_array( basename( $path ), $exclude, true ) ) {
				$files[ basename( $path ) ] = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
		ksort( $files );
		return $files;
	}

	/**
	 * Warns when the theme still uses a symbol (class, function) of a removed core file.
	 *
	 * @param Output                                    $output    Output.
	 * @param string                                    $directory Theme directory.
	 * @param list<array{file: string, status: string}> $plan      Plan.
	 * @param array<mixed>                              $removed   Removed files of the reference manifest.
	 */
	private function warn_symbols( Output $output, string $directory, array $plan, array $removed ): void {
		$symbols = array();
		foreach ( $plan as $item ) {
			if ( CoreUpdate::REMOVE === $item['status'] && is_array( $removed[ $item['file'] ]['symbols'] ?? null ) ) {
				$symbols = array_merge( $symbols, array_map( 'strval', $removed[ $item['file'] ]['symbols'] ) );
			}
		}
		if ( empty( $symbols ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
				static fn( \SplFileInfo $file ): bool => ! $file->isDir() || ! in_array( $file->getFilename(), array( 'vendor', 'node_modules', 'dist', '.git' ), true )
			)
		);
		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( 'php' !== $file->getExtension() || str_starts_with( $file->getPathname(), $directory . '/' . self::FOLDER . '/' ) ) {
				continue;
			}
			$contents = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			foreach ( $symbols as $symbol ) {
				if ( 1 === preg_match( '/\b' . preg_quote( $symbol, '/' ) . '\b/', $contents ) ) {
					$output->warning(
						sprintf(
							/* translators: %1$s: class or function name, %2$s: file path. */
							__( '%1$s is no longer part of the core but is still used in %2$s.', 'skouerr-cli' ),
							$symbol,
							substr( $file->getPathname(), strlen( $directory ) + 1 )
						)
					);
				}
			}
		}
	}
}
