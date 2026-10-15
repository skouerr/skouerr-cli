<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Console\Parameter;
use Skouerr\Cli\Support\Archive;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Libraries;
use Skouerr\Cli\Support\Remote;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr import:block`: imports a block, pattern or template from a Skouerr library.
 *
 * Libraries are declared with the `skouerr_import_libraries` filter.
 */
final class ImportCommand extends AbstractCommand {

	/** Item types and their folder in the theme. */
	private const array TYPES = array(
		'blocks'    => 'Block',
		'patterns'  => 'Pattern',
		'templates' => 'Template',
	);

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'import:block';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Import a block, pattern or template from a Skouerr library.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function side_effects(): array {
		return array( self::EFFECT_NETWORK, self::EFFECT_FILES );
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr import:block --type=blocks --list --format=json',
			'skouerr import:block --type=blocks --name=hero',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'library',
				description: 'Library URL. Defaults to the first library of the "skouerr_import_libraries" filter.',
				prompt: __( 'Skouerr library', 'skouerr-cli' )
			),
			new Parameter(
				name: 'type',
				description: 'Type of item.',
				required: true,
				options: self::TYPES,
				prompt: __( 'Type of item to import', 'skouerr-cli' )
			),
			new Parameter(
				name: 'name',
				description: 'Name of the item in the library. Run with --list to see the available names.',
				pattern: Validator::IDENTIFIER,
				prompt: __( 'Item to import', 'skouerr-cli' )
			),
			new Parameter(
				name: 'sha256',
				description: 'Expected SHA-256 checksum of the downloaded archive.',
				pattern: Validator::SHA256
			),
			Parameter::flag( 'list', 'List the available items instead of importing one.' ),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$library = rtrim( $this->library( $input ), '/' );
		Remote::assert_safe_url( $library );
		Libraries::assert_allowed( $library );
		$headers = Libraries::headers( $library );

		$type = $input->require( 'type' );

		if ( $input->flag( 'list' ) ) {
			$names = array_keys( $this->items( $library, $type, $headers ) );
			$output->table( 'items', array_map( static fn( string $name ): array => array( 'name' => $name ), $names ), array( 'name' ) );
			/* translators: %1$d: number of items, %2$s: item type (blocks, patterns or templates), %3$s: library URL. */
			return sprintf( __( '%1$d %2$s available in %3$s.', 'skouerr-cli' ), count( $names ), $type, $library );
		}

		$name = $input->has( 'name' )
			? (string) $input->get( 'name' )
			: Validator::assert( $input->choose( 'name', $this->items( $library, $type, $headers ) ), Validator::IDENTIFIER, 'name' );

		/* translators: %s: item name. */
		$output->info( sprintf( __( 'Downloading %s…', 'skouerr-cli' ), $name ) );
		$download = Remote::download( $library . '/wp-json/skouerr/v1/library/' . $type . '/' . rawurlencode( $name ), $input->get( 'sha256' ), 104857600, $headers );
		if ( ! $download['verified'] ) {
			$output->warning( __( 'The archive checksum was not verified (no --sha256 and no X-Skouerr-Sha256 header).', 'skouerr-cli' ) );
		}

		try {
			$extracted = Archive::extract_to_temp( $download['path'] );
			try {
				$filesystem = new Filesystem( Theme::directory( $input->get( 'theme' ) ), $output, $input->flag( 'force' ) );
				$filesystem->copy_tree( $extracted, 'blocks' === $type ? $type . '/' . $name : $type );
			} finally {
				Archive::remove( $extracted );
			}
		} finally {
			wp_delete_file( $download['path'] );
		}

		$output->set(
			'item',
			array(
				'library' => $library,
				'type'    => $type,
				'name'    => $name,
				'sha256'  => $download['sha256'],
			)
		);

		$messages = array(
			/* translators: %s: block name. */
			'blocks'    => __( 'Block %s imported.', 'skouerr-cli' ),
			/* translators: %s: pattern name. */
			'patterns'  => __( 'Pattern %s imported.', 'skouerr-cli' ),
			/* translators: %s: template name. */
			'templates' => __( 'Template %s imported.', 'skouerr-cli' ),
		);

		return sprintf( $messages[ $type ] ?? '%s', $name );
	}

	/**
	 * Library to use.
	 *
	 * @param Input $input Input.
	 * @throws CliException When no library is configured.
	 */
	private function library( Input $input ): string {
		if ( $input->has( 'library' ) ) {
			return (string) $input->get( 'library' );
		}

		/**
		 * Filters the Skouerr libraries available for import.
		 *
		 * @param list<string> $libraries Library URLs.
		 */
		$libraries = array_values( array_filter( (array) apply_filters( 'skouerr_import_libraries', Libraries::allowed() ?? array( 'http://localhost' ) ), 'is_string' ) );

		if ( empty( $libraries ) ) {
			throw CliException::environment( __( 'No Skouerr library configured (filter "skouerr_import_libraries").', 'skouerr-cli' ) );
		}
		if ( 1 === count( $libraries ) ) {
			return $libraries[0];
		}
		return $input->choose( 'library', array_combine( $libraries, $libraries ) );
	}

	/**
	 * Items available in a library.
	 *
	 * @param string                $library Library URL.
	 * @param string                $type    Item type.
	 * @param array<string, string> $headers Request headers.
	 * @return array<string, string> Names indexed by name.
	 */
	private function items( string $library, string $type, array $headers ): array {
		$items = array();
		foreach ( Remote::get_json( $library . '/wp-json/skouerr/v1/library/' . $type, $headers ) as $item ) {
			$name = is_array( $item ) ? ( $item['name'] ?? null ) : null;
			if ( is_string( $name ) && 1 === preg_match( Validator::IDENTIFIER, $name ) ) {
				$items[ $name ] = $name;
			}
		}
		return $items;
	}
}
