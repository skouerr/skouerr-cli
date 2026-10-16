<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\Parameter;

/**
 * Locates the theme a command works on.
 */
final class Theme {

	/**
	 * Common `--theme` parameter.
	 */
	public static function parameter(): Parameter {
		return new Parameter(
			name: 'theme',
			description: 'Slug of the target theme. Defaults to the active (parent) theme.',
			pattern: Validator::IDENTIFIER
		);
	}

	/**
	 * Directory of a theme.
	 *
	 * @param string|null $slug Theme slug, null for the active parent theme.
	 * @throws CliException When the theme does not exist.
	 */
	public static function directory( ?string $slug = null ): string {
		if ( null === $slug ) {
			return get_template_directory();
		}

		$theme = wp_get_theme( Validator::assert( $slug, Validator::IDENTIFIER, 'theme' ) );
		if ( ! $theme->exists() ) {
			/* translators: %s: theme slug. */
			throw CliException::invalid( sprintf( __( 'Theme "%s" not found.', 'skouerr-cli' ), $slug ), array( 'theme' => $slug ) );
		}
		return $theme->get_stylesheet_directory();
	}

	/**
	 * Slug of a theme.
	 *
	 * @param string|null $slug Theme slug, null for the active parent theme.
	 */
	public static function slug( ?string $slug = null ): string {
		return $slug ?? get_template();
	}

	/**
	 * Clears the caches WordPress keeps on theme files (patterns, theme.json),
	 * so files written by a command are seen by the next request.
	 */
	public static function flush_caches(): void {
		wp_get_theme()->delete_pattern_cache();
		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}
		wp_clean_themes_cache();
	}

	/**
	 * Text domain declared by a theme, falling back to its slug.
	 *
	 * @param string|null $slug Theme slug, null for the active parent theme.
	 */
	public static function text_domain( ?string $slug = null ): string {
		$domain = (string) wp_get_theme( self::slug( $slug ) )->get( 'TextDomain' );
		return 1 === preg_match( Validator::TEXT_DOMAIN, $domain ) ? $domain : self::slug( $slug );
	}

	/** Loader files of the Skouerr starter theme, newest first. */
	public const array LOADERS = array( 'inc/core/loader.php', 'inc/core/class-skouerr-loader.php' );

	/**
	 * Loader file of a Skouerr theme (blocks, patterns, post types, bindings).
	 *
	 * @param string $directory Theme directory.
	 * @return string|null Absolute path, or null when the theme has no Skouerr loader.
	 */
	public static function loader( string $directory ): ?string {
		foreach ( self::LOADERS as $loader ) {
			if ( is_file( $directory . '/' . $loader ) ) {
				return $directory . '/' . $loader;
			}
		}
		return null;
	}

	/**
	 * Tells whether the theme loader requires the files of a folder.
	 *
	 * @param string $directory Theme directory.
	 * @param string $folder    Folder relative to the theme (e.g. "taxonomies").
	 */
	public static function loads( string $directory, string $folder ): bool {
		$loader = self::loader( $directory );
		if ( null === $loader ) {
			return false;
		}
		return str_contains( (string) file_get_contents( $loader ), '/' . $folder . '/' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Folder of the ACF local JSON files, relative to the theme.
	 *
	 * The ACF save point (`acf/settings/save_json`) when it lies inside the theme,
	 * so generated field groups land where the project saves them; else acf-json/.
	 *
	 * @param string $directory Theme directory.
	 */
	public static function acf_json_folder( string $directory ): string {
		$save = function_exists( 'acf_get_setting' ) ? acf_get_setting( 'save_json' ) : null;
		if ( is_string( $save ) && '' !== $save ) {
			$save  = rtrim( str_replace( '\\', '/', $save ), '/' );
			$theme = rtrim( str_replace( '\\', '/', $directory ), '/' );
			if ( str_starts_with( $save . '/', $theme . '/' ) && $save !== $theme ) {
				return substr( $save, strlen( $theme ) + 1 );
			}
		}
		return 'acf-json';
	}
}
