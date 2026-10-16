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
	 * {@inheritDoc}
	 */
	protected function kept_warning(): string {
		return __( 'The synced pattern stays in the database: blocks that reference it (wp:block "ref") keep using it. The theme file is a separate, unsynced pattern.', 'skouerr-cli' );
	}

	/**
	 * A synced pattern still referenced by a wp:block is never trashed: the references would break.
	 *
	 * @param WP_Post $post   Pattern.
	 * @param Output  $output Output.
	 */
	protected function can_trash( WP_Post $post, Output $output ): bool {
		$references = self::references( $post->ID );
		if ( empty( $references ) ) {
			return true;
		}
		$output->warning(
			sprintf(
				/* translators: %1$d: pattern ID, %2$s: list of posts and files. */
				__( 'Pattern #%1$d is not moved to the trash: it is still referenced by %2$s.', 'skouerr-cli' ),
				$post->ID,
				implode( ', ', $references )
			)
		);
		return false;
	}

	/**
	 * Posts and theme files referencing a synced pattern (`<!-- wp:block {"ref":ID} /-->`).
	 *
	 * @param int $id Pattern ID.
	 * @return list<string> "#ID" for posts, relative paths for theme files.
	 */
	public static function references( int $id ): array {
		global $wpdb;
		$pattern = '/"ref":' . $id . '(?![0-9])/';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off CLI query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status <> 'trash' AND ID <> %d AND post_content LIKE %s",
				$id,
				'%' . $wpdb->esc_like( '"ref":' . $id ) . '%'
			)
		);

		$references = array();
		foreach ( (array) $rows as $row ) {
			if ( 1 === preg_match( $pattern, (string) $row->post_content ) ) {
				$references[] = '#' . (int) $row->ID;
			}
		}

		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $theme ) {
			foreach ( array( 'templates', 'parts', 'patterns' ) as $folder ) {
				// No GLOB_BRACE: musl (Alpine) does not support it.
				$files = array_merge( (array) glob( $theme . '/' . $folder . '/*.html' ), (array) glob( $theme . '/' . $folder . '/*.php' ) );
				foreach ( $files as $file ) {
					if ( is_string( $file ) && 1 === preg_match( $pattern, (string) file_get_contents( $file ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						$references[] = substr( $file, strlen( $theme ) + 1 );
					}
				}
			}
		}
		return $references;
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
