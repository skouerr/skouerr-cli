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
use Skouerr\Cli\Support\Template;
use Skouerr\Cli\Support\Templates;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr make:taxonomy`: creates `<theme>/taxonomies/<slug>/<slug>.functions.php`.
 */
final class MakeTaxonomyCommand extends AbstractCommand {

	/** Taxonomy key (WordPress limits it to 32 characters). */
	private const string TAXONOMY = '/^[a-z0-9_-]{1,32}$/D';

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:taxonomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Create a custom taxonomy in the theme.';
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
		return array( 'skouerr make:taxonomy --slug=event-type --label="Event type" --plural="Event types" --post-types=event --hierarchical' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'slug',
				description: 'Taxonomy key (max. 32 characters: a-z, 0-9, _ and -).',
				required: true,
				pattern: self::TAXONOMY,
				prompt: __( 'Slug of the taxonomy', 'skouerr-cli' )
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
				name: 'post-types',
				description: 'Comma-separated post types using the taxonomy.',
				default: 'post',
				prompt: __( 'Post types (comma-separated)', 'skouerr-cli' )
			),
			new Parameter(
				name: 'domain',
				description: 'Text domain of the labels. Defaults to the theme text domain.',
				pattern: Validator::TEXT_DOMAIN
			),
			Parameter::flag( 'hierarchical', 'Hierarchical taxonomy, like categories.' ),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$theme      = $input->get( 'theme' );
		$directory  = Theme::directory( $theme );
		$slug       = strtolower( $input->require( 'slug' ) );
		$label      = Validator::label( ucfirst( $input->require( 'label' ) ), 'label' );
		$plural     = Validator::label( $input->require( 'plural', $label . 's' ), 'plural' );
		$post_types = PatternFile::list( $input->require( 'post-types' ), Validator::POST_TYPE, 'post-types' );
		$domain     = $input->require( 'domain', Theme::text_domain( $theme ) );

		if ( taxonomy_exists( $slug ) ) {
			/* translators: %s: taxonomy slug. */
			$output->warning( sprintf( __( 'A taxonomy "%s" is already registered.', 'skouerr-cli' ), $slug ) );
		}

		// Code placeholders first (validated values), then string placeholders (escaped).
		$code     = Template::render_file(
			Templates::file( 'taxonomy/taxonomy', $directory ),
			array(
				'SK_TAX_POST_TYPES'   => "['" . implode( "', '", $post_types ) . "']",
				'SK_TAX_HIERARCHICAL' => $input->flag( 'hierarchical' ) ? 'true' : 'false',
			),
			'text'
		);
		$contents = Template::render(
			$code,
			array(
				'SK_TAX_SLUG'   => $slug,
				'SK_TAX_LABEL'  => $label,
				'SK_TAX_PLURAL' => $plural,
				'SK_TAX_DOMAIN' => $domain,
			),
			'php'
		);

		$filesystem = new Filesystem( $directory, $output, $input->flag( 'force' ) );
		$path       = $filesystem->write( 'taxonomies/' . $slug . '/' . $slug . '.functions.php', $contents );

		$loader = $directory . '/inc/core/loader.php';
		if ( ! is_file( $loader ) || ! str_contains( (string) file_get_contents( $loader ), 'taxonomies' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$output->warning( __( 'The theme loader does not seem to load taxonomies/: require the file from functions.php.', 'skouerr-cli' ) );
			/* translators: %1$s: taxonomy slug. */
			$output->next_step( sprintf( __( 'Add require_once __DIR__ . \'/taxonomies/%1$s/%1$s.functions.php\'; to functions.php.', 'skouerr-cli' ), $slug ) );
		}
		$output->next_step( 'wp rewrite flush' );

		$output->set(
			'taxonomy',
			array(
				'slug'       => $slug,
				'label'      => $label,
				'plural'     => $plural,
				'post_types' => $post_types,
				'file'       => $path,
			)
		);

		/* translators: %s: taxonomy slug. */
		return sprintf( __( 'Taxonomy %s created.', 'skouerr-cli' ), $slug );
	}
}
