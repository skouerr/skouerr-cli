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
use Skouerr\Cli\Support\Validator;
use WP_Post;

/**
 * `wp skouerr save:navigation`: saves navigation menus (wp_navigation) as template parts.
 *
 * The part contains a navigation block with the links as inner blocks, so the
 * theme no longer depends on a menu stored in the database.
 */
final class SaveNavigationCommand extends AbstractSaveCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'save:navigation';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Save a navigation menu (wp_navigation) as a template part parts/navigation-<slug>.html.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr save:navigation --list --format=json',
			'skouerr save:navigation --navigation=header-navigation',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return $this->save_parameters();
	}

	/**
	 * Navigation menus are referenced by ID from templates: never trash them.
	 */
	protected function supports_delete(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function item_parameter(): string {
		return 'navigation';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function post_type( Input $input ): string {
		return 'wp_navigation';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function directory( Input $input ): string {
		return 'parts';
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
	protected function file_slug( string $slug ): string {
		return 'navigation-' . $slug;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function contents( WP_Post $post, string $slug, Input $input ): string {
		return "<!-- wp:navigation -->\n" . trim( $post->post_content ) . "\n<!-- /wp:navigation -->\n";
	}

	/**
	 * {@inheritDoc}
	 */
	protected function after_save( array $posts, Output $output ): void {
		foreach ( $posts as $post ) {
			$output->next_step(
				sprintf(
					/* translators: %1$d: navigation post ID, %2$s: navigation slug. */
					__( 'Replace <!-- wp:navigation {"ref":%1$d} /--> with <!-- wp:template-part {"slug":"navigation-%2$s"} /--> in the theme parts.', 'skouerr-cli' ),
					$post->ID,
					Validator::slugify( '' !== $post->post_name ? urldecode( $post->post_name ) : $post->post_title )
				)
			);
		}
	}

	/**
	 * Navigation menus stored in the database.
	 *
	 * @param Input $input Input.
	 * @return list<WP_Post>
	 */
	protected function posts( Input $input ): array {
		return array_values(
			get_posts(
				array(
					'post_type'      => 'wp_navigation',
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => -1,
					'orderby'        => 'name',
					'order'          => 'ASC',
				)
			)
		);
	}
}
