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
 * `wp skouerr make:post-type`: creates `<theme>/post-types/<slug>/<slug>.functions.php`.
 */
final class MakePostTypeCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:post-type';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Create a custom post type in the theme.';
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
			'skouerr make:post-type --slug=event --label=Event --plural=Events --icon=calendar-alt',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'slug',
				description: 'Post type key (max. 20 characters: a-z, 0-9, _ and -).',
				required: true,
				pattern: Validator::POST_TYPE,
				prompt: __( 'Slug of the post type', 'skouerr-cli' )
			),
			new Parameter(
				name: 'label',
				description: 'Singular label.',
				required: true,
				prompt: __( 'Singular label', 'skouerr-cli' )
			),
			new Parameter(
				name: 'plural',
				description: 'Plural label. Defaults to the singular label followed by "s".',
				prompt: __( 'Plural label', 'skouerr-cli' )
			),
			new Parameter(
				name: 'text-domain',
				description: 'Text domain of the labels. Defaults to the theme text domain.',
				pattern: Validator::TEXT_DOMAIN,
				prompt: __( 'Text domain', 'skouerr-cli' )
			),
			new Parameter(
				name: 'domain',
				description: 'Deprecated alias of --text-domain.',
				pattern: Validator::TEXT_DOMAIN
			),
			new Parameter(
				name: 'icon',
				description: 'Dashicon slug of the admin menu.',
				default: 'admin-post',
				pattern: '/^(dashicons-)?[a-z0-9-]{1,64}$/D',
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
		$theme     = $input->get( 'theme' );
		$slug      = strtolower( $input->require( 'slug' ) );
		$label     = Validator::label( ucfirst( $input->require( 'label' ) ), 'label' );
		$plural    = Validator::label( $input->require( 'plural', $label . 's' ), 'plural' );
		$domain    = $input->get( 'domain' ) ?? $input->require( 'text-domain', Theme::text_domain( $theme ) );
		$icon      = Dashicons::normalize( $input->require( 'icon' ) );

		if ( post_type_exists( $slug ) ) {
			/* translators: %s: post type slug. */
			$output->warning( sprintf( __( 'A post type "%s" is already registered.', 'skouerr-cli' ), $slug ) );
		}
		if ( ! Dashicons::exists( $icon ) ) {
			/* translators: %s: dashicon name. */
			$output->warning( sprintf( __( 'Unknown dashicon "%s".', 'skouerr-cli' ), $icon ) );
		}

		$contents = Template::render_file(
			Templates::file( 'post-type/post-type', $directory ),
			array(
				'SK_PT_SLUG'   => $slug,
				'SK_PT_NAME'   => $slug,
				'SK_PT_LABEL'  => $label,
				'SK_PT_PLURAL' => $plural,
				'SK_PT_DOMAIN' => $domain,
				'SK_PT_ICON'   => $icon,
			),
			'php'
		);

		$filesystem = new Filesystem( $directory, $output, $input->flag( 'force' ) );
		$path       = $filesystem->write( 'post-types/' . $slug . '/' . $slug . '.functions.php', $contents );

		$output->set(
			'post_type',
			array(
				'slug'   => $slug,
				'label'  => $label,
				'plural' => $plural,
				'file'   => $path,
			)
		);
		$output->next_step( 'wp rewrite flush' );

		/* translators: %s: post type slug. */
		return sprintf( __( 'Post type %s created.', 'skouerr-cli' ), $slug );
	}
}
