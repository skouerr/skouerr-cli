<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

/**
 * Finds and replaces block names in content (database and theme files).
 */
final class BlockUsage {

	/** Theme folders holding block markup. */
	private const array THEME_FOLDERS = array( 'templates', 'parts', 'patterns' );

	/**
	 * Replacements that rename a block in block markup.
	 *
	 * @param string $old Old block name (namespace/name).
	 * @param string $new New block name.
	 * @return array<string, string>
	 */
	public static function replacements( string $old, string $new ): array {
		return array(
			'<!-- wp:' . $old . ' '     => '<!-- wp:' . $new . ' ',
			'<!-- /wp:' . $old . ' -->' => '<!-- /wp:' . $new . ' -->',
			// ACF blocks also store their name in the block attributes.
			'"name":"' . $old . '"'     => '"name":"' . $new . '"',
		);
	}

	/**
	 * Posts whose content uses a block.
	 *
	 * @param string $name Block name.
	 * @return list<int> Post IDs.
	 */
	public static function posts( string $name ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off CLI query.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_content LIKE %s",
				'%' . $wpdb->esc_like( '<!-- wp:' . $name . ' ' ) . '%'
			)
		);
		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * Renames a block in the content of posts.
	 *
	 * @param string    $old Old block name.
	 * @param string    $new New block name.
	 * @param list<int> $ids Post IDs.
	 */
	public static function rename_in_posts( string $old, string $new, array $ids ): void {
		global $wpdb;
		foreach ( $ids as $id ) {
			$content = get_post_field( 'post_content', $id, 'raw' );
			$content = is_string( $content ) ? $content : '';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update: no revision, no filters on the raw content.
			$wpdb->update( $wpdb->posts, array( 'post_content' => strtr( $content, self::replacements( $old, $new ) ) ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}
	}

	/**
	 * Theme files using a block.
	 *
	 * @param Filesystem $filesystem Theme filesystem.
	 * @param string     $name       Block name.
	 * @return array<string, string> Contents indexed by relative path.
	 */
	public static function theme_files( Filesystem $filesystem, string $name ): array {
		$files = array();
		foreach ( self::THEME_FOLDERS as $folder ) {
			foreach ( $filesystem->read_tree( $folder ) as $path => $contents ) {
				if ( str_contains( $contents, '<!-- wp:' . $name . ' ' ) ) {
					$files[ $path ] = $contents;
				}
			}
		}
		return $files;
	}
}
