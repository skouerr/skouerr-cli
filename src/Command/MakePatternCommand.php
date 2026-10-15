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
use Skouerr\Cli\Console\Parameter;
use Skouerr\Cli\Scaffold\PatternFile;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Templates;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr make:pattern`: creates `<theme>/patterns/<name>.php`.
 */
final class MakePatternCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:pattern';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Create a block pattern in the theme patterns/ folder.';
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
			'skouerr make:pattern --title="Hero home" --categories=featured,banner',
			'skouerr make:pattern --title="Footer links" --block-types=core/template-part/footer --hidden',
			'skouerr make:pattern --title="CTA" --content=\'<!-- wp:buttons --><div class="wp-block-buttons"></div><!-- /wp:buttons -->\'',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'title',
				description: 'Title of the pattern.',
				required: true,
				prompt: __( 'Title of the pattern', 'skouerr-cli' )
			),
			new Parameter(
				name: 'name',
				description: 'File name and slug of the pattern. Defaults to the slugified title.',
				pattern: Validator::IDENTIFIER,
				prompt: __( 'Name of the pattern', 'skouerr-cli' )
			),
			new Parameter(
				name: 'prefix',
				description: 'Namespace of the pattern slug (<prefix>/<name>).',
				default: 'skouerr',
				pattern: Validator::BLOCK_SEGMENT
			),
			new Parameter(
				name: 'description',
				description: 'Description shown in the inserter.'
			),
			new Parameter(
				name: 'categories',
				description: 'Comma-separated pattern categories (e.g. featured,banner).',
				prompt: __( 'Categories (comma-separated)', 'skouerr-cli' )
			),
			new Parameter(
				name: 'keywords',
				description: 'Comma-separated search keywords.'
			),
			new Parameter(
				name: 'block-types',
				description: 'Comma-separated block types the pattern is suggested for (e.g. core/template-part/header).'
			),
			new Parameter(
				name: 'content',
				description: 'Block markup of the pattern. Defaults to an empty group.'
			),
			Parameter::flag( 'hidden', 'Hide the pattern from the inserter (Inserter: no).' ),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$directory = Theme::directory( $input->get( 'theme' ) );
		$title     = Validator::label( $input->require( 'title' ) );
		$name      = Validator::assert( $input->require( 'name', Validator::slugify( $title ) ), Validator::IDENTIFIER, 'name' );
		$slug      = $input->require( 'prefix' ) . '/' . $name;

		$categories  = PatternFile::list( $input->get( 'categories' ), Validator::IDENTIFIER, 'categories' );
		$block_types = PatternFile::list( $input->get( 'block-types' ), '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*(\/[a-z0-9-]+)?$/D', 'block-types' );
		$keywords    = array_map( static fn( string $keyword ): string => Validator::label( $keyword, 'keywords' ), PatternFile::list( $input->get( 'keywords' ), '/^[^,]+$/D', 'keywords' ) );

		$content = $input->get( 'content' );
		if ( null === $content ) {
			$content = (string) file_get_contents( Templates::file( 'pattern/default', $directory ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		$contents = PatternFile::render(
			array(
				'Title'       => $title,
				'Slug'        => $slug,
				'Description' => (string) $input->get( 'description' ),
				'Categories'  => implode( ', ', $categories ),
				'Keywords'    => implode( ', ', $keywords ),
				'Block Types' => implode( ', ', $block_types ),
				'Inserter'    => $input->flag( 'hidden' ) ? 'no' : '',
			),
			$content
		);

		$filesystem = new Filesystem( $directory, $output, $input->flag( 'force' ) );
		$path       = $filesystem->write( 'patterns/' . $name . '.php', $contents );

		$output->set(
			'pattern',
			array(
				'slug'       => $slug,
				'title'      => $title,
				'categories' => $categories,
				'file'       => $path,
			)
		);

		/* translators: %s: pattern slug. */
		return sprintf( __( 'Pattern %s created.', 'skouerr-cli' ), $slug );
	}
}
