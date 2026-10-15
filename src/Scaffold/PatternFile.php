<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Scaffold;

use Skouerr\Cli\Support\Validator;

/**
 * Builds a theme pattern file: a PHP header comment followed by block markup.
 *
 * Header values are cleaned and `<?` sequences in the markup are encoded, so
 * neither the header nor the content can ever become PHP code.
 */
final class PatternFile {

	/**
	 * Renders a pattern file.
	 *
	 * @param array<string, string> $header  Header fields (Title, Slug, Categories…). Empty values are skipped.
	 * @param string                $content Block markup.
	 */
	public static function render( array $header, string $content ): string {
		$lines = array( '<?php', '/**' );
		foreach ( $header as $key => $value ) {
			$value = trim( Validator::inert( $value ) );
			if ( '' !== $value ) {
				$lines[] = ' * ' . $key . ': ' . $value;
			}
		}
		$lines[] = ' */';
		$lines[] = '?>';

		return implode( "\n", $lines ) . "\n" . rtrim( str_replace( '<?', '&lt;?', $content ) ) . "\n";
	}

	/**
	 * Parses a comma-separated list and validates each item.
	 *
	 * @param string|null $list    List.
	 * @param string      $pattern Pattern of each item.
	 * @param string      $name    Parameter name.
	 * @return list<string>
	 */
	public static function list( ?string $list, string $pattern, string $name ): array {
		if ( null === $list ) {
			return array();
		}
		$items = array_values( array_filter( array_map( 'trim', explode( ',', $list ) ), static fn( string $item ): bool => '' !== $item ) );
		foreach ( $items as $item ) {
			Validator::assert( $item, $pattern, $name );
		}
		return $items;
	}
}
