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
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Template;
use Skouerr\Cli\Support\Templates;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr make:binding`: creates `<theme>/bindings/<name>/<name>.php`.
 */
final class MakeBindingCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:binding';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Create a block binding source in the theme.';
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
			'skouerr make:binding --title="Current year" --name=current-year',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'title',
				description: 'Title of the binding.',
				required: true,
				prompt: __( 'Title of the binding', 'skouerr-cli' )
			),
			new Parameter(
				name: 'name',
				description: 'Name of the binding (source "skouerr/<name>"). Defaults to the slugified title.',
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'Name of the binding', 'skouerr-cli' )
			),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$directory = Theme::directory( $input->get( 'theme' ) );
		$title     = Validator::label( $input->require( 'title' ) );
		$name      = Validator::assert( $input->require( 'name', Validator::slugify( $title ) ), Validator::BLOCK_SEGMENT, 'name' );

		$contents = Template::render_file(
			Templates::file( 'binding/default', $directory ),
			array(
				'SK_BINDING_TITLE' => $title,
				'SK_BINDING_NAME'  => $name,
			),
			'php'
		);

		$filesystem = new Filesystem( $directory, $output, $input->flag( 'force' ) );
		$path       = $filesystem->write( 'bindings/' . $name . '/' . $name . '.php', $contents );

		$output->set(
			'binding',
			array(
				'source' => 'skouerr/' . $name,
				'title'  => $title,
				'file'   => $path,
				'usage'  => array( 'metadata' => array( 'bindings' => array( 'content' => array( 'source' => 'skouerr/' . $name ) ) ) ),
			)
		);

		/* translators: %s: binding name. */
		return sprintf( __( 'Block binding skouerr/%s created.', 'skouerr-cli' ), $name );
	}
}
