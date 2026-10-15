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
use Skouerr\Cli\Support\ThemeJson;
use WP_Theme_JSON_Resolver;

/**
 * `wp skouerr save:styles`: merges the global styles edited in the Site Editor into theme.json.
 */
final class SaveStylesCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'save:styles';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Merge the global styles edited in the Site Editor (settings and styles) into the theme.json of the active theme.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function side_effects(): array {
		return array( self::EFFECT_FILES, self::EFFECT_DATABASE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr save:styles --dry-run --format=json',
			'skouerr save:styles --delete',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			Parameter::flag( 'delete', 'Move the user global styles to the trash after saving, so theme.json is used.' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$theme = wp_get_theme();
		$post  = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( $theme );
		$user  = json_decode( (string) ( $post['post_content'] ?? '' ), true );

		if ( ! is_array( $user ) || ( empty( $user['settings'] ) && empty( $user['styles'] ) ) ) {
			$output->set( 'changed', array() );
			return __( 'No global styles to save: theme.json is up to date.', 'skouerr-cli' );
		}

		$directory = get_stylesheet_directory();
		$file      = $directory . '/theme.json';
		$current   = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array( 'version' => 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $current ) ) {
			/* translators: %s: file path. */
			throw CliException::invalid( sprintf( __( '%s is not valid JSON.', 'skouerr-cli' ), $file ), array( 'path' => $file ) );
		}

		$override = array_intersect_key( $user, array_flip( array( 'settings', 'styles' ) ) );
		$merged   = ThemeJson::merge( $current, $override );

		// theme.json is updated on purpose: the merge keeps every key the user did not change.
		( new Filesystem( $directory, $output, true ) )->write( 'theme.json', ThemeJson::encode( $merged ) );

		$changed = ThemeJson::changed_paths( $override );
		$output->set( 'changed', $changed );
		$output->set( 'global_styles_id', (int) ( $post['ID'] ?? 0 ) );

		$delete = $input->flag( 'delete' ) || ( $input->is_interactive() && $input->confirm( __( 'Move the user global styles to the trash so theme.json is used?', 'skouerr-cli' ), true ) );
		if ( $delete && isset( $post['ID'] ) ) {
			if ( ! $output->is_dry_run() && ! wp_trash_post( (int) $post['ID'] ) ) {
				/* translators: %d: post ID. */
				$output->warning( sprintf( __( 'Cannot move global styles #%d to the trash.', 'skouerr-cli' ), $post['ID'] ) );
			} else {
				$output->file( 'trash', sprintf( 'wp_global_styles #%d', $post['ID'] ) );
			}
		} else {
			$output->warning( __( 'The user global styles are kept and still override theme.json. Pass --delete to move them to the trash.', 'skouerr-cli' ) );
		}

		/* translators: %s: list of changed keys. */
		return sprintf( __( 'Global styles saved to theme.json (%s).', 'skouerr-cli' ), implode( ', ', $changed ) );
	}
}
