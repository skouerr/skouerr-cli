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
use Skouerr\Cli\Scaffold\BlockScaffold;
use Skouerr\Cli\Scaffold\PatternFile;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Template;
use Skouerr\Cli\Support\Templates;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr make:style-variation` and `wp skouerr make:block-style`: theme.json partials in `styles/`.
 *
 * - Style variation: `styles/<name>.json`, an alternative global style of the theme.
 * - Block style: `styles/blocks/<name>.json` with "blockTypes" (WordPress 6.6+), no PHP registration needed.
 */
final class MakeStyleCommand extends AbstractCommand {

	/**
	 * Constructor.
	 *
	 * @param bool $block True for `make:block-style`.
	 */
	public function __construct( private readonly bool $block = false ) {}

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return $this->block ? 'make:block-style' : 'make:style-variation';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return $this->block
			? 'Create a block style (styles/blocks/<name>.json, WordPress 6.6+).'
			: 'Create a theme style variation (styles/<name>.json).';
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
		return $this->block
			? array( 'skouerr make:block-style --title="Outline" --block-types=core/button,core/group' )
			: array( 'skouerr make:style-variation --title="Dark"' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		$parameters = array(
			new Parameter(
				name: 'title',
				description: 'Title shown in the editor.',
				required: true,
				prompt: __( 'Title of the style', 'skouerr-cli' )
			),
			new Parameter(
				name: 'name',
				description: 'File name and slug. Defaults to the slugified title.',
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'Name of the style', 'skouerr-cli' )
			),
		);
		if ( $this->block ) {
			$parameters[] = new Parameter(
				name: 'block-types',
				description: 'Comma-separated blocks the style applies to (e.g. core/button,core/group).',
				required: true,
				prompt: __( 'Blocks (comma-separated)', 'skouerr-cli' )
			);
		}
		$parameters[] = Theme::parameter();
		return $parameters;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$directory = Theme::directory( $input->get( 'theme' ) );
		$title     = Validator::label( $input->require( 'title' ) );
		$name      = Validator::assert( $input->require( 'name', Validator::slugify( $title ) ), Validator::BLOCK_SEGMENT, 'name' );

		$contents = Template::render_file(
			Templates::file( $this->block ? 'block-style/default' : 'style-variation/default', $directory ),
			array(
				'SK_STYLE_TITLE' => $title,
				'SK_STYLE_NAME'  => $name,
			),
			'json'
		);

		$data = array();
		if ( $this->block ) {
			$data = json_decode( $contents, true );
			if ( ! is_array( $data ) ) {
				throw CliException::environment( __( 'The block-style template is not valid JSON.', 'skouerr-cli' ) );
			}
			$data['blockTypes'] = PatternFile::list( $input->require( 'block-types' ), Validator::BLOCK_NAME, 'block-types' );
			// json_decode() turns the empty "styles" object into an array: keep it an object.
			if ( empty( $data['styles'] ) ) {
				$data['styles'] = new \stdClass();
			}
			$contents = BlockScaffold::encode( $data );
			$contents = (string) preg_replace_callback( '/^(  )+/m', static fn( array $m ): string => str_repeat( "\t", intdiv( strlen( $m[0] ), 2 ) ), $contents );
		}

		$relative   = ( $this->block ? 'styles/blocks/' : 'styles/' ) . $name . '.json';
		$filesystem = new Filesystem( $directory, $output, $input->flag( 'force' ) );
		$path       = $filesystem->write( $relative, $contents );

		if ( $this->block ) {
			$output->next_step( __( 'Add at least one property to "styles": WordPress does not register a block style whose styles are empty.', 'skouerr-cli' ) );
		}

		$output->set(
			'style',
			array(
				'name'        => $name,
				'title'       => $title,
				'block_types' => $data['blockTypes'] ?? array(),
				'file'        => $path,
			)
		);

		return $this->block
			/* translators: %s: style name. */
			? sprintf( __( 'Block style %s created.', 'skouerr-cli' ), $name )
			/* translators: %s: style name. */
			: sprintf( __( 'Style variation %s created.', 'skouerr-cli' ), $name );
	}
}
