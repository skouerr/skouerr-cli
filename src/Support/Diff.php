<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Support;

/**
 * Unified diffs in the git format, accepted by `git apply` and `patch -p1`.
 *
 * Changes are reported as one hunk: common leading and trailing lines are kept
 * as context, everything in between is replaced. This is always a valid patch,
 * and precise enough for generated files.
 */
final class Diff {

	/** Context lines around the hunk. */
	private const int CONTEXT = 3;

	/** Marker of a last line without newline. */
	private const string NO_EOL = '\ No newline at end of file';

	/**
	 * Diff of one file.
	 *
	 * @param string      $path   Path relative to the patch root.
	 * @param string|null $before Contents before, null for a new file.
	 * @param string|null $after  Contents after, null for a deleted file.
	 */
	public static function unified( string $path, ?string $before, ?string $after ): string {
		if ( $before === $after ) {
			return '';
		}

		$header = 'diff --git a/' . $path . ' b/' . $path . "\n";
		if ( null === $before ) {
			$header .= "new file mode 100644\n";
		} elseif ( null === $after ) {
			$header .= "deleted file mode 100644\n";
		}

		[ $old, $old_eol ] = self::lines( $before ?? '' );
		[ $new, $new_eol ] = self::lines( $after ?? '' );
		if ( array() === $old && array() === $new ) {
			return $header;
		}

		$header .= '--- ' . ( null === $before ? '/dev/null' : 'a/' . $path ) . "\n";
		$header .= '+++ ' . ( null === $after ? '/dev/null' : 'b/' . $path ) . "\n";

		// Comparison keys: a last line without newline differs from the same line with one.
		$old_keys = self::keys( $old, $old_eol );
		$new_keys = self::keys( $new, $new_eol );

		$old_count = count( $old );
		$new_count = count( $new );
		$prefix    = 0;
		$max       = min( $old_count, $new_count );
		while ( $prefix < $max && $old_keys[ $prefix ] === $new_keys[ $prefix ] ) {
			++$prefix;
		}
		$suffix = 0;
		while ( $suffix < $max - $prefix && $old_keys[ $old_count - 1 - $suffix ] === $new_keys[ $new_count - 1 - $suffix ] ) {
			++$suffix;
		}

		$lead     = min( self::CONTEXT, $prefix );
		$trail    = min( self::CONTEXT, $suffix );
		$start    = $prefix - $lead;
		$old_end  = $old_count - $suffix + $trail;
		$new_end  = $new_count - $suffix + $trail;
		$old_size = $old_end - $start;
		$new_size = $new_end - $start;

		$hunk = sprintf(
			"@@ -%s +%s @@\n",
			self::range( $start, $old_size ),
			self::range( $start, $new_size )
		);

		for ( $i = $start; $i < $prefix; $i++ ) {
			$hunk .= ' ' . $old[ $i ] . "\n";
		}
		$old_last = $old_count - $suffix;
		$new_last = $new_count - $suffix;
		for ( $i = $prefix; $i < $old_last; $i++ ) {
			$hunk .= '-' . $old[ $i ] . "\n" . self::eol_marker( $i, $old, $old_eol );
		}
		for ( $i = $prefix; $i < $new_last; $i++ ) {
			$hunk .= '+' . $new[ $i ] . "\n" . self::eol_marker( $i, $new, $new_eol );
		}
		for ( $i = $old_last; $i < $old_end; $i++ ) {
			$hunk .= ' ' . $old[ $i ] . "\n" . self::eol_marker( $i, $old, $old_eol );
		}

		return $header . $hunk;
	}

	/**
	 * Splits contents into lines.
	 *
	 * @param string $contents Contents.
	 * @return array{0: list<string>, 1: bool} Lines, and whether the contents end with a newline.
	 */
	private static function lines( string $contents ): array {
		if ( '' === $contents ) {
			return array( array(), true );
		}
		$eol   = str_ends_with( $contents, "\n" );
		$lines = explode( "\n", $eol ? substr( $contents, 0, -1 ) : $contents );
		return array( $lines, $eol );
	}

	/**
	 * Comparison keys of lines.
	 *
	 * @param list<string> $lines Lines.
	 * @param bool         $eol   Whether the last line ends with a newline.
	 * @return list<string>
	 */
	private static function keys( array $lines, bool $eol ): array {
		if ( ! $eol && array() !== $lines ) {
			$lines[ count( $lines ) - 1 ] .= "\0";
		}
		return $lines;
	}

	/**
	 * "No newline" marker after the last line of a file without final newline.
	 *
	 * @param int          $index Line index.
	 * @param list<string> $lines Lines.
	 * @param bool         $eol   Whether the file ends with a newline.
	 */
	private static function eol_marker( int $index, array $lines, bool $eol ): string {
		return ! $eol && count( $lines ) - 1 === $index ? self::NO_EOL . "\n" : '';
	}

	/**
	 * Hunk range "start,length" (1-based, 0 for empty ranges).
	 *
	 * @param int $start  0-based start.
	 * @param int $length Length.
	 */
	private static function range( int $start, int $length ): string {
		return ( 0 === $length ? $start : $start + 1 ) . ',' . $length;
	}
}
