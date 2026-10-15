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
use Skouerr\Cli\Support\Dashicons;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Template;
use Skouerr\Cli\Support\Templates;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr make:variation`: creates `<theme>/js/admin/variations/<name>.js`.
 */
final class MakeVariationCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:variation';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Create a block variation in the theme.';
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
			'skouerr make:variation --block=core/group --name=card --title="Card"',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'block',
				description: 'Name of the block to vary (namespace/name).',
				required: true,
				pattern: Validator::BLOCK_NAME,
				prompt: __( 'Block to create a variation for', 'skouerr-cli' )
			),
			new Parameter(
				name: 'title',
				description: 'Title of the variation.',
				required: true,
				prompt: __( 'Title of the variation', 'skouerr-cli' )
			),
			new Parameter(
				name: 'name',
				description: 'Name of the variation. Defaults to the slugified title.',
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'Name of the variation', 'skouerr-cli' )
			),
			new Parameter(
				name: 'icon',
				description: 'Dashicon slug.',
				default: 'block-default',
				pattern: Validator::ICON,
				prompt: __( 'Dashicon', 'skouerr-cli' )
			),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$directory = Theme::directory( $input->get( 'theme' ) );
		$block     = $input->require( 'block' );
		$title     = Validator::label( $input->require( 'title' ) );
		$name      = Validator::assert( $input->require( 'name', Validator::slugify( $title ) ), Validator::BLOCK_SEGMENT, 'name' );
		$icon      = Dashicons::normalize( $input->require( 'icon' ) );

		$contents = Template::render_file(
			Templates::file( 'variations/default', $directory ),
			array(
				'SK_VARIATION_BLOCK' => $block,
				'SK_VARIATION_TITLE' => $title,
				'SK_VARIATION_NAME'  => $name,
				'SK_VARIATION_ICON'  => $icon,
			),
			'js'
		);

		$filesystem = new Filesystem( $directory, $output, $input->flag( 'force' ) );
		$path       = $filesystem->write( 'js/admin/variations/' . $name . '.js', $contents );

		$output->set(
			'variation',
			array(
				'block' => $block,
				'name'  => $name,
				'title' => $title,
				'file'  => $path,
			)
		);

		/* translators: %1$s: variation name, %2$s: block name. */
		return sprintf( __( 'Variation %1$s of %2$s created.', 'skouerr-cli' ), $name, $block );
	}
}
