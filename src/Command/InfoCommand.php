<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Support\Process;
use Skouerr\Cli\Support\Theme;

/**
 * `wp skouerr info`: describes the project the CLI works on.
 *
 * Gives an agent the context it needs before scaffolding: theme paths,
 * existing blocks, post types, bindings, and available integrations.
 */
final class InfoCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'info';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Show the theme context: paths, existing blocks, post types, bindings and integrations.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array( 'skouerr info --format=json' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array( Theme::parameter() );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$slug      = $input->get( 'theme' );
		$directory = Theme::directory( $slug );
		$theme     = wp_get_theme( basename( $directory ) );

		$info = array(
			'cli'          => array(
				'version' => SKOUERR_CLI_VERSION,
				'path'    => SKOUERR_CLI_PATH,
			),
			'environment'  => array(
				'php'       => PHP_VERSION,
				'wordpress' => get_bloginfo( 'version' ),
				'site_url'  => site_url(),
				'composer'  => null !== Process::find( 'composer' ),
				'zip'       => class_exists( \ZipArchive::class ),
			),
			'theme'        => array(
				'slug'           => basename( $directory ),
				'name'           => (string) $theme->get( 'Name' ),
				'version'        => (string) $theme->get( 'Version' ),
				'text_domain'    => Theme::text_domain( $slug ),
				'directory'      => $directory,
				'active'         => get_template() === basename( $directory ) || get_stylesheet() === basename( $directory ),
				'is_block_theme' => $theme->is_block_theme(),
				'is_skouerr'     => is_file( $directory . '/inc/core/loader.php' ),
			),
			'integrations' => array(
				'acf_pro' => function_exists( 'acf_register_block_type' ),
				'timber'  => class_exists( '\Timber\Timber' ),
			),
			'content'      => array(
				'blocks'     => array_map( static fn( string $file ): string => substr( $file, strlen( $directory ) + 1 ), ListBlocksCommand::block_files( $directory ) ),
				'post_types' => self::children( $directory . '/post-types' ),
				'bindings'   => self::children( $directory . '/bindings' ),
				'templates'  => self::files( $directory . '/templates', 'html' ),
				'parts'      => self::files( $directory . '/parts', 'html' ),
				'patterns'   => self::files( $directory . '/patterns', 'php' ),
			),
		);

		$output->document( 'info', $info, (string) wp_json_encode( $info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		/* translators: %s: theme slug. */
		return sprintf( __( 'Theme %s.', 'skouerr-cli' ), $info['theme']['slug'] );
	}

	/**
	 * Sub-directory names.
	 *
	 * @param string $directory Directory.
	 * @return list<string>
	 */
	private static function children( string $directory ): array {
		$children = glob( $directory . '/*', GLOB_ONLYDIR );
		return false === $children ? array() : array_map( 'basename', $children );
	}

	/**
	 * File names without extension.
	 *
	 * @param string $directory Directory.
	 * @param string $extension Extension.
	 * @return list<string>
	 */
	private static function files( string $directory, string $extension ): array {
		$files = glob( $directory . '/*.' . $extension );
		return false === $files ? array() : array_map( static fn( string $file ): string => basename( $file, '.' . $extension ), $files );
	}
}
