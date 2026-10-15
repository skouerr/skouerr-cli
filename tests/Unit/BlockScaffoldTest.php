<?php
/**
 * Block scaffold tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Skouerr\Cli\Scaffold\BlockScaffold;

final class BlockScaffoldTest extends TestCase {

	private const array BLOCK = array(
		'name'       => 'hero',
		'title'      => 'Hero "Big" \\ Banner',
		'slug'       => 'skouerr/hero',
		'icon'       => 'cover-image',
		'textdomain' => 'acme',
		'category'   => 'acme-blocks',
	);

	/**
	 * @return iterable<array{string, string, list<string>}>
	 */
	public static function kinds(): iterable {
		$common = array( 'hero.block.json', 'hero.controller.php', 'src/hero.script.js', 'src/hero.style.scss' );
		yield 'native php' => array( 'native', 'php', array_merge( $common, array( 'hero.register.js', 'hero.template.php' ) ) );
		yield 'native twig' => array( 'native', 'twig', array_merge( $common, array( 'hero.register.js', 'hero.template.twig' ) ) );
		yield 'native react' => array( 'native', 'react', array_merge( $common, array( 'hero.template.php', 'src/hero.editor.js' ) ) );
		yield 'acf php' => array( 'acf', 'php', array_merge( $common, array( 'hero.template.php' ) ) );
		yield 'acf twig' => array( 'acf', 'twig', array_merge( $common, array( 'hero.template.twig' ) ) );
		yield 'interactive php' => array( 'interactive', 'php', array_merge( $common, array( 'hero.register.js', 'hero.template.php', 'hero.view.asset.php', 'hero.view.js' ) ) );
		yield 'interactive twig' => array( 'interactive', 'twig', array_merge( $common, array( 'hero.register.js', 'hero.template.twig', 'hero.view.asset.php', 'hero.view.js' ) ) );
	}

	/**
	 * @param list<string> $expected Expected files.
	 */
	#[DataProvider( 'kinds' )]
	public function test_files( string $type, string $engine, array $expected ): void {
		$files = ( new BlockScaffold() )->files( SKOUERR_CLI_PATH . '/templates/block/' . $type . '-' . $engine, $engine, self::BLOCK );

		sort( $expected );
		self::assertSame( $expected, array_keys( $files ) );

		$json = json_decode( $files['hero.block.json'], true, 512, JSON_THROW_ON_ERROR );
		self::assertSame( 'skouerr/hero', $json['name'] );
		self::assertSame( self::BLOCK['title'], $json['title'] );
		self::assertSame( 'cover-image', $json['icon'] );
		self::assertSame( 'acme', $json['textdomain'] );
		self::assertSame( 'acme-blocks', $json['category'] );

		foreach ( $files as $path => $contents ) {
			self::assertStringNotContainsString( '%SK_', $contents );
			if ( str_ends_with( $path, '.php' ) ) {
				$file = $this->temp_directory() . '/x.php';
				file_put_contents( $file, $contents );
				exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $lint, $code );
				self::assertSame( 0, $code, $path . ' is not valid PHP' );
			}
		}
	}

	public function test_attributes_are_merged(): void {
		$files = ( new BlockScaffold() )->files(
			SKOUERR_CLI_PATH . '/templates/block/interactive-php',
			'php',
			self::BLOCK + array(
				'attributes' => array(
					'open' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		$json  = json_decode( $files['hero.block.json'], true );

		self::assertSame(
			array(
				'type'    => 'boolean',
				'default' => false,
			),
			$json['attributes']['open']
		);
		self::assertTrue( $json['supports']['interactivity'] );
		self::assertStringStartsWith( "{\n  \"", $files['hero.block.json'], 'Two-space indentation is kept.' );
	}
}
