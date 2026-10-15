<?php
/**
 * Validator tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Support\Validator;

final class ValidatorTest extends TestCase {

	public function test_slugify(): void {
		self::assertSame( 'hero-banner', Validator::slugify( 'Hero Banner' ) );
		self::assertSame( 'cafe-creme', Validator::slugify( '  Café   Crème!! ' ) );
		self::assertSame( 'etc-passwd', Validator::slugify( '../../etc/passwd' ) );
	}

	/**
	 * @return iterable<array{string, string, bool}>
	 */
	public static function identifiers(): iterable {
		yield 'block segment' => array( 'hero-banner', Validator::BLOCK_SEGMENT, true );
		yield 'traversal' => array( '../hero', Validator::IDENTIFIER, false );
		yield 'slash' => array( 'a/b', Validator::IDENTIFIER, false );
		yield 'uppercase' => array( 'Hero', Validator::BLOCK_SEGMENT, false );
		yield 'quote' => array( "x'", Validator::BLOCK_SEGMENT, false );
		yield 'shell' => array( 'x;rm', Validator::IDENTIFIER, false );
		yield 'newline' => array( "x\n", Validator::IDENTIFIER, false );
		yield 'block name' => array( 'core/group', Validator::BLOCK_NAME, true );
		yield 'post type too long' => array( str_repeat( 'a', 21 ), Validator::POST_TYPE, false );
	}

	#[DataProvider( 'identifiers' )]
	public function test_patterns( string $value, string $pattern, bool $valid ): void {
		self::assertSame( $valid, 1 === preg_match( $pattern, $value ) );
	}

	public function test_label_removes_comment_and_php_breakers(): void {
		self::assertSame( 'x system(1);', Validator::label( 'x */ system(1); /*' ) );
		self::assertSame( 'a', Validator::label( '**//a' ) );
		self::assertSame( 'phpphpinfo();', Validator::label( '<?php?>phpinfo();' ) );
		self::assertStringNotContainsString( '<?', Validator::label( '<<??php' ) );
		self::assertSame( 'a b', Validator::label( "a\nb" ) );
	}

	public function test_inert_is_stable(): void {
		foreach ( array( '**//', '*/*/', '<<??', '??>>', '/**/*/' ) as $input ) {
			$output = Validator::inert( $input );
			self::assertStringNotContainsString( '*/', $output );
			self::assertStringNotContainsString( '<?', $output );
			self::assertStringNotContainsString( '?>', $output );
		}
	}

	public function test_label_rejects_empty(): void {
		$this->expectException( CliException::class );
		Validator::label( ' */ ' );
	}

	public function test_assert(): void {
		self::assertSame( 'ok', Validator::assert( 'ok', Validator::IDENTIFIER, 'name' ) );
		$this->expectException( CliException::class );
		Validator::assert( '../x', Validator::IDENTIFIER, 'name' );
	}
}
