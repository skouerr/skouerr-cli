<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Parameter;
use Skouerr\Cli\Scaffold\PatternFile;
use Skouerr\Cli\Support\Validator;
use WP_Post;

/**
 * `wp skouerr save:pattern`: saves a pattern created in the editor to `patterns/<slug>.php`.
 */
final class SavePatternCommand extends AbstractSaveCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'save:pattern';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Save a pattern created in the editor to the theme patterns/ folder.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr save:pattern --list --format=json',
			'skouerr save:pattern --pattern=hero-home --delete',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		$parameters   = $this->save_parameters();
		$parameters[] = new Parameter(
			name: 'prefix',
			description: 'Namespace of the pattern slug (<prefix>/<slug>).',
			default: 'skouerr',
			pattern: Validator::BLOCK_SEGMENT
		);
		return $parameters;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function item_parameter(): string {
		return 'pattern';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function post_type( Input $input ): string {
		return 'wp_block';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function directory( Input $input ): string {
		return 'patterns';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function extension(): string {
		return '.php';
	}

	/**
	 * Pattern file: a PHP header comment followed by the block markup.
	 *
	 * The header only contains cleaned values, and `<?` sequences in the markup
	 * are encoded, so the content of the post can never become PHP code.
	 *
	 * @param WP_Post $post  Post.
	 * @param string  $slug  File slug.
	 * @param Input   $input Input.
	 */
	protected function contents( WP_Post $post, string $slug, Input $input ): string {
		$categories = wp_get_post_terms( $post->ID, 'wp_pattern_category', array( 'fields' => 'slugs' ) );

		return PatternFile::render(
			array(
				'Title'      => '' !== trim( Validator::inert( $post->post_title ) ) ? $post->post_title : $slug,
				'Slug'       => $input->require( 'prefix' ) . '/' . $slug,
				'Categories' => is_array( $categories ) ? implode( ', ', array_map( 'strval', $categories ) ) : '',
			),
			$post->post_content
		);
	}

	/**
	 * Patterns stored in the database.
	 *
	 * @param Input $input Input.
	 * @return list<WP_Post>
	 */
	protected function posts( Input $input ): array {
		return array_values(
			get_posts(
				array(
					'post_type'      => 'wp_block',
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => -1,
					'orderby'        => 'name',
					'order'          => 'ASC',
				)
			)
		);
	}
}
