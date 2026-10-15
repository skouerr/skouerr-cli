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
 * Locates scaffold templates.
 *
 * A theme can override any template by mirroring the `templates/` tree of the
 * plugin in `<theme>/.skouerr/templates/`. The `skouerr_cli_templates_directory`
 * filter adds another directory, searched first.
 */
final class Templates {

	/** Override directory, relative to the theme. */
	public const string THEME_DIRECTORY = '.skouerr/templates';

	/**
	 * Directories searched, by priority.
	 *
	 * @param string|null $theme_directory Theme directory.
	 * @return list<string>
	 */
	public static function roots( ?string $theme_directory = null ): array {
		$roots = array();
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters an extra directory of scaffold templates, searched before the theme and the plugin ones.
			 *
			 * @param string|null $directory       Directory, null for none.
			 * @param string|null $theme_directory Theme the command works on.
			 */
			$custom = apply_filters( 'skouerr_cli_templates_directory', null, $theme_directory );
			if ( is_string( $custom ) && '' !== $custom ) {
				$roots[] = rtrim( $custom, '/' );
			}
		}
		if ( null !== $theme_directory ) {
			$roots[] = $theme_directory . '/' . self::THEME_DIRECTORY;
		}
		$roots[] = SKOUERR_CLI_PATH . '/templates';
		return $roots;
	}

	/**
	 * First existing template file.
	 *
	 * @param string      $relative        Path relative to a templates root (e.g. "post-type/post-type").
	 * @param string|null $theme_directory Theme directory.
	 * @throws CliException When no template exists.
	 */
	public static function file( string $relative, ?string $theme_directory = null ): string {
		foreach ( self::roots( $theme_directory ) as $root ) {
			if ( is_file( $root . '/' . $relative ) ) {
				return $root . '/' . $relative;
			}
		}
		/* translators: %s: template path. */
		throw CliException::environment( sprintf( __( 'Template "%s" not found.', 'skouerr-cli' ), $relative ) );
	}

	/**
	 * First existing template directory.
	 *
	 * @param string      $relative        Path relative to a templates root (e.g. "block/native-php").
	 * @param string|null $theme_directory Theme directory.
	 */
	public static function directory( string $relative, ?string $theme_directory = null ): ?string {
		foreach ( self::roots( $theme_directory ) as $root ) {
			if ( is_dir( $root . '/' . $relative ) ) {
				return $root . '/' . $relative;
			}
		}
		return null;
	}

	/**
	 * Available block kinds ("<type>-<engine>").
	 *
	 * @param string|null $theme_directory Theme directory.
	 * @return list<string>
	 */
	public static function block_kinds( ?string $theme_directory = null ): array {
		$kinds = array();
		foreach ( self::roots( $theme_directory ) as $root ) {
			foreach ( glob( $root . '/block/*', GLOB_ONLYDIR ) ?: array() as $directory ) { // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$kinds[] = basename( $directory );
			}
		}
		$kinds = array_values( array_unique( $kinds ) );
		sort( $kinds );
		return $kinds;
	}
}
