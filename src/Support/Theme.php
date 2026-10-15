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
}
