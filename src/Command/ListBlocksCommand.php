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
use Skouerr\Cli\Support\Theme;

/**
 * `wp skouerr list:blocks`: lists the blocks of the theme.
 */
final class ListBlocksCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'list:blocks';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'List the blocks of the theme.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array( 'skouerr list:blocks --format=json' );
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
		$directory = Theme::directory( $input->get( 'theme' ) );
		$blocks    = array();

		foreach ( self::block_files( $directory ) as $file ) {
			$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_array( $json ) ) {
				/* translators: %s: file path. */
				$output->warning( sprintf( __( 'Invalid JSON in %s.', 'skouerr-cli' ), $file ) );
				continue;
			}
			$blocks[] = array(
				'name'     => (string) ( $json['name'] ?? '' ),
				'title'    => (string) ( $json['title'] ?? '' ),
				'category' => (string) ( $json['category'] ?? '' ),
				'icon'     => is_string( $json['icon'] ?? null ) ? $json['icon'] : '',
				'acf'      => isset( $json['acf'] ),
				'path'     => $file,
			);
		}

		usort( $blocks, static fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );
		$output->table( 'blocks', $blocks, array( 'name', 'title', 'category', 'icon' ) );

		/* translators: %d: number of blocks. */
		return sprintf( __( '%d blocks found.', 'skouerr-cli' ), count( $blocks ) );
	}

	/**
	 * Block metadata files of a theme.
	 *
	 * Uses the theme loader when the active theme provides one, otherwise
	 * scans `blocks/` for `*.block.json` and `block.json` files.
	 *
	 * @param string $directory Theme directory.
	 * @return list<string>
	 */
	public static function block_files( string $directory ): array {
		if ( class_exists( 'Skouerr_Loader' ) && get_template_directory() === $directory ) {
			$loader = new \Skouerr_Loader();
			if ( method_exists( $loader, 'get_blocks' ) ) {
				return array_values( array_map( 'strval', (array) $loader->get_blocks() ) );
			}
		}

		$files = array_merge(
			glob( $directory . '/blocks/*/*.block.json' ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			glob( $directory . '/blocks/*/block.json' ) ?: array() // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		);
		sort( $files );
		return $files;
	}
}
