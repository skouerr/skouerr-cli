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
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Validator;
use WP_Post;

/**
 * Exports content edited in the Site Editor (stored in the database) to theme files.
 *
 * The database copy is kept unless --delete is passed. It is then moved to
 * the trash, so the theme file is used again and the change can be undone.
 */
abstract class AbstractSaveCommand extends AbstractCommand {

	/**
	 * Post type to export.
	 *
	 * @param Input $input Input.
	 */
	abstract protected function post_type( Input $input ): string;

	/**
	 * Destination folder, relative to the theme.
	 *
	 * @param Input $input Input.
	 */
	abstract protected function directory( Input $input ): string;

	/**
	 * File extension, with the dot.
	 */
	abstract protected function extension(): string;

	/**
	 * File contents.
	 *
	 * @param WP_Post $post  Post.
	 * @param string  $slug  File slug.
	 * @param Input   $input Input.
	 */
	abstract protected function contents( WP_Post $post, string $slug, Input $input ): string;

	/**
	 * Name of the item parameter ("template", "pattern").
	 */
	abstract protected function item_parameter(): string;

	/**
	 * Posts that can be exported.
	 *
	 * @param Input $input Input.
	 * @return list<WP_Post>
	 */
	abstract protected function posts( Input $input ): array;

	/**
	 * {@inheritDoc}
	 */
	public function side_effects(): array {
		return $this->supports_delete() ? array( self::EFFECT_FILES, self::EFFECT_DATABASE ) : array( self::EFFECT_FILES );
	}

	/**
	 * File slug of a post.
	 *
	 * @param string $slug Post slug.
	 */
	protected function file_slug( string $slug ): string {
		return $slug;
	}

	/**
	 * Hook after the files are written.
	 *
	 * @param list<WP_Post> $posts  Saved posts.
	 * @param Output        $output Output.
	 */
	protected function after_save( array $posts, Output $output ): void {
		unset( $posts, $output );
	}

	/**
	 * Tells whether the database copy can be moved to the trash after saving.
	 */
	protected function supports_delete(): bool {
		return true;
	}

	/**
	 * Common parameters of the save commands.
	 *
	 * @return list<Parameter>
	 */
	protected function save_parameters(): array {
		$parameters = array(
			new Parameter(
				name: $this->item_parameter(),
				description: sprintf( 'Slug or ID of the %s to save.', $this->item_parameter() ),
				/* translators: %s: item type (pattern, template or navigation). */
				prompt: sprintf( __( 'Select a %s', 'skouerr-cli' ), $this->item_parameter() )
			),
			Parameter::flag( 'all', sprintf( 'Save every %s stored in the database.', $this->item_parameter() ) ),
			Parameter::flag( 'list', sprintf( 'List the %ss stored in the database instead of saving.', $this->item_parameter() ) ),
		);
		if ( $this->supports_delete() ) {
			$parameters[] = Parameter::flag( 'delete', 'Move the database copy to the trash after saving, so the theme file is used.' );
		}
		return $parameters;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$posts = $this->posts( $input );

		if ( $input->flag( 'list' ) ) {
			$rows = array_map(
				static fn( WP_Post $post ): array => array(
					'id'       => $post->ID,
					'slug'     => $post->post_name,
					'title'    => $post->post_title,
					'modified' => $post->post_modified_gmt,
				),
				$posts
			);
			$output->table( 'items', $rows, array( 'id', 'slug', 'title', 'modified' ) );
			/* translators: %1$d: number of items, %2$s: item type (pattern, template or navigation). */
			return sprintf( __( '%1$d %2$s(s) in the database.', 'skouerr-cli' ), count( $rows ), $this->item_parameter() );
		}

		if ( empty( $posts ) ) {
			/* translators: %s: item type (pattern, template or navigation). */
			throw CliException::invalid( sprintf( __( 'No %s found in the database.', 'skouerr-cli' ), $this->item_parameter() ) );
		}

		$selected   = $input->flag( 'all' ) ? $posts : array( $this->select( $posts, $input ) );
		$filesystem = new Filesystem( get_stylesheet_directory(), $output, $input->flag( 'force' ) );
		$files      = array();
		foreach ( $selected as $post ) {
			$slug = Validator::slugify( '' !== $post->post_name ? urldecode( $post->post_name ) : $post->post_title );
			Validator::assert( $slug, Validator::IDENTIFIER, $this->item_parameter() );
			$files[ $this->directory( $input ) . '/' . $this->file_slug( $slug ) . $this->extension() ] = $this->contents( $post, $slug, $input );
		}
		$paths = $filesystem->write_many( $files );
		$this->after_save( $selected, $output );

		$delete = $this->supports_delete() && (
			$input->flag( 'delete' )
			|| ( $input->is_interactive() && $input->confirm( __( 'Move the database copy to the trash so the theme file is used?', 'skouerr-cli' ), true ) )
		);
		if ( $delete ) {
			foreach ( $selected as $post ) {
				if ( ! $output->is_dry_run() && ! wp_trash_post( $post->ID ) ) {
					/* translators: %1$s: item type (pattern, template or navigation), %2$d: post ID. */
					$output->warning( sprintf( __( 'Cannot move %1$s #%2$d to the trash.', 'skouerr-cli' ), $this->item_parameter(), $post->ID ) );
					continue;
				}
				$output->file( 'trash', sprintf( '%s #%d (%s)', $post->post_type, $post->ID, $post->post_name ) );
			}
		} elseif ( $this->supports_delete() ) {
			$output->warning( __( 'The database copy is kept and still overrides the theme file. Pass --delete to move it to the trash.', 'skouerr-cli' ) );
		}

		$output->set( 'files', $paths );
		$output->set( 'trashed', $delete ? array_map( static fn( WP_Post $post ): int => $post->ID, $selected ) : array() );

		/* translators: %1$d: number of items, %2$s: item type (pattern, template or navigation). */
		return sprintf( __( '%1$d %2$s(s) saved.', 'skouerr-cli' ), count( $paths ), $this->item_parameter() );
	}

	/**
	 * Selects one post from the parameter or a prompt.
	 *
	 * @param list<WP_Post> $posts Posts.
	 * @param Input         $input Input.
	 * @throws CliException When the post is not found.
	 */
	private function select( array $posts, Input $input ): WP_Post {
		$options = array();
		foreach ( $posts as $post ) {
			$key             = '' !== $post->post_name ? $post->post_name : (string) $post->ID;
			$options[ $key ] = sprintf( '%s (#%d)', $post->post_title, $post->ID );
		}

		$value = $input->has( $this->item_parameter() )
			? (string) $input->get( $this->item_parameter() )
			: $input->choose( $this->item_parameter(), $options );

		foreach ( $posts as $post ) {
			if ( (string) $post->ID === $value || $post->post_name === $value ) {
				return $post;
			}
		}

		throw CliException::invalid(
			/* translators: %1$s: item type (Pattern, Template or Navigation), %2$s: slug or ID. */
			sprintf( __( '%1$s "%2$s" not found in the database.', 'skouerr-cli' ), ucfirst( $this->item_parameter() ), $value ),
			array( 'choices' => array_map( static fn( WP_Post $post ): string => $post->post_name, $posts ) )
		);
	}
}
