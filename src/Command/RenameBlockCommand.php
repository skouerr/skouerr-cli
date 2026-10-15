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
use Skouerr\Cli\Support\BlockUsage;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr rename:block`: renames a block folder, its files, its name in
 * the files and in the theme markup, and optionally in the database content.
 */
final class RenameBlockCommand extends AbstractCommand {

	/** File roles used in block file names (`hero.controller.php`…). */
	private const string ROLES = 'controller|template|block|register|style|script|editor|view';

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'rename:block';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Rename a block: folder, files, block name, theme markup, and optionally the database content.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function side_effects(): array {
		return array( self::EFFECT_FILES, self::EFFECT_DATABASE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr rename:block --from=hero --to=hero-banner --format=patch',
			'skouerr rename:block --from=hero --to=hero-banner --prefix=acme --update-content',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'from',
				description: 'Current folder name of the block in blocks/.',
				required: true,
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'Block to rename', 'skouerr-cli' )
			),
			new Parameter(
				name: 'to',
				description: 'New block name.',
				required: true,
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'New name', 'skouerr-cli' )
			),
			new Parameter(
				name: 'prefix',
				description: 'New block namespace. Defaults to the current one.',
				pattern: Validator::BLOCK_SEGMENT
			),
			new Parameter(
				name: 'title',
				description: 'New title of the block.'
			),
			Parameter::flag( 'update-content', 'Also rename the block in the database content (posts, templates, patterns). Back up the database first.' ),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$filesystem = new Filesystem( Theme::directory( $input->get( 'theme' ) ), $output, $input->flag( 'force' ) );
		$from       = $input->has( 'from' ) ? (string) $input->get( 'from' ) : $input->choose( 'from', self::existing_blocks( $filesystem ) );
		$to         = $input->require( 'to' );
		$files      = $filesystem->read_tree( 'blocks/' . $from );

		if ( empty( $files ) ) {
			throw CliException::invalid(
				/* translators: %s: block folder name. */
				sprintf( __( 'Block folder blocks/%s not found.', 'skouerr-cli' ), $from ),
				array( 'choices' => array_keys( self::existing_blocks( $filesystem ) ) )
			);
		}
		if ( $from === $to && ! $input->has( 'prefix' ) && ! $input->has( 'title' ) ) {
			throw CliException::invalid( __( 'Nothing to rename: --to is the current name.', 'skouerr-cli' ) );
		}
		if ( $from !== $to && $filesystem->exists( 'blocks/' . $to ) ) {
			throw CliException::conflict( $filesystem->path( 'blocks/' . $to ) );
		}

		/* translators: %s: block folder name. */
		$old   = self::block_name( $files, $from ) ?? throw CliException::invalid( sprintf( __( 'No block.json with a "name" in blocks/%s.', 'skouerr-cli' ), $from ) );
		$new   = $input->require( 'prefix', strstr( $old, '/', true ) ?: 'skouerr' ) . '/' . $to; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$title = $input->get( 'title' );

		// Block files: new paths and contents.
		$renamed = array();
		foreach ( $files as $path => $contents ) {
			$target             = 'blocks/' . $to . '/' . (string) preg_replace(
				'/^(.*\/)?' . preg_quote( $from, '/' ) . '\.(?=(' . self::ROLES . ')\.)/',
				'${1}' . $to . '.',
				substr( $path, strlen( 'blocks/' . $from . '/' ) )
			);
			$renamed[ $target ] = $this->rename_contents( $contents, $from, $to, $old, $new, $title, $target );
		}

		// ACF field groups and theme markup referencing the block.
		$updates = array();
		foreach ( $filesystem->read_tree( 'acf-json' ) as $path => $contents ) {
			if ( str_contains( $contents, '"value": "' . $old . '"' ) ) {
				$updates[ $path ] = str_replace( '"value": "' . $old . '"', '"value": "' . $new . '"', $contents );
			}
		}
		foreach ( BlockUsage::theme_files( $filesystem, $old ) as $path => $contents ) {
			$updates[ $path ] = strtr( $contents, BlockUsage::replacements( $old, $new ) );
		}

		$posts = BlockUsage::posts( $old );

		if ( $from === $to ) {
			( new Filesystem( $filesystem->root, $output, true ) )->write_many( array_merge( $renamed, $updates ) );
		} else {
			$filesystem->write_many( $renamed );
			$filesystem->delete_many( array_keys( $files ) );
			( new Filesystem( $filesystem->root, $output, true ) )->write_many( $updates );
		}

		if ( ! empty( $posts ) ) {
			if ( $input->flag( 'update-content' ) && ! $output->is_dry_run() ) {
				BlockUsage::rename_in_posts( $old, $new, $posts );
				/* translators: %d: number of posts. */
				$output->info( sprintf( __( 'Block renamed in %d post(s).', 'skouerr-cli' ), count( $posts ) ) );
			} elseif ( $input->flag( 'update-content' ) ) {
				/* translators: %d: number of posts. */
				$output->info( sprintf( __( 'Would rename the block in %d post(s).', 'skouerr-cli' ), count( $posts ) ) );
			} else {
				/* translators: %1$s: old block name, %2$d: number of posts. */
				$output->warning( sprintf( __( 'Block %1$s is used in %2$d post(s) that still reference the old name. Pass --update-content to rename it in the database.', 'skouerr-cli' ), $old, count( $posts ) ) );
			}
		}

		$output->set(
			'block',
			array(
				'from'          => $old,
				'to'            => $new,
				'directory'     => $filesystem->path( 'blocks/' . $to ),
				'posts'         => $posts,
				'posts_updated' => $input->flag( 'update-content' ) && ! $output->is_dry_run(),
			)
		);
		$output->next_step( __( 'Rebuild the block assets with the theme bundler (e.g. npm run build).', 'skouerr-cli' ) );

		/* translators: %1$s: old block name, %2$s: new block name. */
		return sprintf( __( 'Block %1$s renamed to %2$s.', 'skouerr-cli' ), $old, $new );
	}

	/**
	 * Renames a block inside one of its files.
	 *
	 * @param string      $contents Contents.
	 * @param string      $from     Old folder name.
	 * @param string      $to       New folder name.
	 * @param string      $old      Old block name.
	 * @param string      $new      New block name.
	 * @param string|null $title    New title.
	 * @param string      $target   Target path.
	 */
	private function rename_contents( string $contents, string $from, string $to, string $old, string $new, ?string $title, string $target ): string {
		$quoted   = preg_quote( $from, '/' );
		$contents = str_replace( $old, $new, $contents );
		$contents = (string) preg_replace( '/(?<![a-z0-9-])' . $quoted . '\.(?=(' . self::ROLES . ')\.)/', $to . '.', $contents );
		$contents = (string) preg_replace( '/(?<![a-z0-9-])block-' . $quoted . '(?![a-z0-9-])/', 'block-' . $to, $contents );
		$contents = (string) preg_replace( '/(Block Name\s*:\s*)' . $quoted . '(?![a-z0-9-])/', '${1}' . $to, $contents );

		if ( null !== $title && str_ends_with( $target, '.block.json' ) ) {
			$data = json_decode( $contents, true );
			if ( is_array( $data ) ) {
				$data['title'] = Validator::label( $title );
				$contents      = \Skouerr\Cli\Scaffold\BlockScaffold::encode( $data );
			}
		}
		return $contents;
	}

	/**
	 * Block name declared in the block.json of a block folder.
	 *
	 * @param array<string, string> $files  Files of the block, indexed by relative path.
	 * @param string                $folder Folder name.
	 */
	public static function block_name( array $files, string $folder ): ?string {
		foreach ( array( $folder . '.block.json', 'block.json' ) as $file ) {
			$json = json_decode( $files[ 'blocks/' . $folder . '/' . $file ] ?? '', true );
			if ( is_array( $json ) && is_string( $json['name'] ?? null ) ) {
				return $json['name'];
			}
		}
		return null;
	}

	/**
	 * Block folders of the theme.
	 *
	 * @param Filesystem $filesystem Theme filesystem.
	 * @return array<string, string> Folder names indexed by folder name.
	 */
	public static function existing_blocks( Filesystem $filesystem ): array {
		$folders = glob( $filesystem->root . '/blocks/*', GLOB_ONLYDIR );
		$names   = array_map( 'basename', false === $folders ? array() : $folders );
		return array_combine( $names, $names );
	}
}
