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
use WP_Post;

/**
 * `wp skouerr save:template`: saves templates edited in the Site Editor to `templates/` or `parts/`.
 */
final class SaveTemplateCommand extends AbstractSaveCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'save:template';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Save a template (or template part) edited in the Site Editor to the theme files.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr save:template --list --format=json',
			'skouerr save:template --template=single --delete',
			'skouerr save:template --part --all --delete --force',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		$parameters   = $this->save_parameters();
		$parameters[] = Parameter::flag( 'part', 'Work on template parts (parts/) instead of templates.' );
		return $parameters;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function item_parameter(): string {
		return 'template';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function post_type( Input $input ): string {
		return $input->flag( 'part' ) ? 'wp_template_part' : 'wp_template';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function directory( Input $input ): string {
		return $input->flag( 'part' ) ? 'parts' : 'templates';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function extension(): string {
		return '.html';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function contents( WP_Post $post, string $slug, Input $input ): string {
		return $post->post_content;
	}

	/**
	 * Templates of the active theme stored in the database.
	 *
	 * @param Input $input Input.
	 * @return list<WP_Post>
	 */
	protected function posts( Input $input ): array {
		return array_values(
			get_posts(
				array(
					'post_type'      => $this->post_type( $input ),
					'post_status'    => array( 'publish', 'draft', 'auto-draft' ),
					'posts_per_page' => -1,
					'orderby'        => 'name',
					'order'          => 'ASC',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'wp_theme',
							'field'    => 'name',
							'terms'    => get_stylesheet(),
						),
					),
				)
			)
		);
	}
}
