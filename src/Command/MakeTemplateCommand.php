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
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr make:template`: creates an empty block template or template part.
 */
final class MakeTemplateCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:template';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Create an empty block template (templates/) or template part (parts/).';
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
			'skouerr make:template --name=single-event',
			'skouerr make:template --name=footer-minimal --part',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'name',
				description: 'Template slug, following the template hierarchy (e.g. single-event, archive, 404).',
				required: true,
				pattern: Validator::IDENTIFIER,
				prompt: __( 'Name of the template', 'skouerr-cli' )
			),
			Parameter::flag( 'part', 'Create a template part in parts/ instead of a template.' ),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$name      = $input->require( 'name' );
		$directory = $input->flag( 'part' ) ? 'parts' : 'templates';

		$filesystem = new Filesystem( Theme::directory( $input->get( 'theme' ) ), $output, $input->flag( 'force' ) );
		$path       = $filesystem->write( $directory . '/' . $name . '.html', '' );

		$output->set( 'file', $path );

		/* translators: %s: template name. */
		return sprintf( __( 'Template %s created.', 'skouerr-cli' ), $name );
	}
}
