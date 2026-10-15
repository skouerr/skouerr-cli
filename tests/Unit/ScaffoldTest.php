<?php
/**
 * Block schema, pattern file, theme.json and diff tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Scaffold\BlockSchema;
use Skouerr\Cli\Scaffold\PatternFile;
use Skouerr\Cli\Support\Diff;
use Skouerr\Cli\Support\Libraries;
use Skouerr\Cli\Support\ThemeJson;

final class ScaffoldTest extends TestCase {

	public function test_attributes(): void {
		$attributes = BlockSchema::attributes( '{"title": {"type": "string", "default": ""}, "ids": {"type": ["array", "null"]}}' );
		self::assertSame( array( 'title', 'ids' ), array_keys( $attributes ) );
	}

	public function test_attributes_reject_bad_types(): void {
		$this->expectException( CliException::class );
		BlockSchema::attributes( '{"title": {"type": "text"}}' );
	}

	public function test_attributes_reject_lists(): void {
		$this->expectException( CliException::class );
		BlockSchema::attributes( '[{"type": "string"}]' );
	}

	public function test_field_group_has_stable_keys(): void {
		$fields = BlockSchema::fields( '[{"name": "heading", "type": "text"}, {"name": "items", "type": "repeater", "sub_fields": [{"name": "photo", "type": "image", "return_format": "id"}]}]' );
		$group  = BlockSchema::field_group( 'skouerr/team', 'Team', $fields );
		$again  = BlockSchema::field_group( 'skouerr/team', 'Team', $fields );

		self::assertSame( $group['key'], $again['key'] );
		self::assertSame( BlockSchema::group_key( 'skouerr/team' ), $group['key'] );
		self::assertSame( 'skouerr/team', $group['location'][0][0]['value'] );
		self::assertSame( 'Heading', $group['fields'][0]['label'] );
		self::assertMatchesRegularExpression( '/^field_[a-f0-9]{13}$/', $group['fields'][1]['sub_fields'][0]['key'] );
		self::assertSame( 'id', $group['fields'][1]['sub_fields'][0]['return_format'] );
		self::assertNotSame( $group['fields'][0]['key'], $group['fields'][1]['key'] );
	}

	public function test_fields_validation(): void {
		foreach ( array( '{"a": 1}', '[{"name": "Title", "type": "text"}]', '[{"name": "a", "type": "nope"}]', '[{"name": "a", "type": "text"}, {"name": "a", "type": "text"}]', 'not json' ) as $json ) {
			try {
				BlockSchema::fields( $json );
				self::fail( 'Rejected expected for ' . $json );
			} catch ( CliException $e ) {
				self::assertSame( 'fields', $e->details['parameter'] );
			}
		}
	}

	public function test_pattern_file_is_inert(): void {
		$code = PatternFile::render(
			array(
				'Title'      => 'Promo */ echo "pwned"; /*',
				'Slug'       => 'skouerr/promo',
				'Categories' => '',
			),
			'<p><?php echo "pwned"; ?></p>'
		);
		$file = $this->temp_directory() . '/p.php';
		file_put_contents( $file, $code );

		ob_start();
		include $file;
		$rendered = (string) ob_get_clean();

		self::assertSame( "<p>&lt;?php echo \"pwned\"; ?></p>\n", $rendered );
		self::assertStringNotContainsString( 'Categories', $code, 'Empty headers are skipped.' );
	}

	public function test_pattern_list(): void {
		self::assertSame( array( 'a', 'b' ), PatternFile::list( ' a, b ,', '/^[a-z]+$/D', 'x' ) );
		self::assertSame( array(), PatternFile::list( null, '/x/', 'x' ) );
		$this->expectException( CliException::class );
		PatternFile::list( 'a,B', '/^[a-z]+$/D', 'x' );
	}

	public function test_theme_json_merge(): void {
		$theme = array(
			'version'       => 3,
			'settings'      => array(
				'color'  => array(
					'palette' => array( array( 'slug' => 'a' ), array( 'slug' => 'b' ) ),
					'custom'  => false,
				),
				'layout' => array( 'contentSize' => '640px' ),
			),
			'templateParts' => array( array( 'name' => 'header' ) ),
		);
		$user  = array(
			'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'c' ) ) ) ),
			'styles'   => array( 'color' => array( 'background' => '#000' ) ),
		);

		$merged = ThemeJson::merge( $theme, $user );

		self::assertSame( array( array( 'slug' => 'c' ) ), $merged['settings']['color']['palette'], 'Lists are replaced.' );
		self::assertFalse( $merged['settings']['color']['custom'], 'Untouched keys are kept.' );
		self::assertSame( '640px', $merged['settings']['layout']['contentSize'] );
		self::assertSame( '#000', $merged['styles']['color']['background'] );
		self::assertSame( array( 'settings.color', 'styles.color' ), ThemeJson::changed_paths( $user ) );
		self::assertStringStartsWith( "{\n\t\"version\"", ThemeJson::encode( $merged ) );
	}

	public function test_diff_applies_with_git(): void {
		$cases     = array(
			'new.txt'     => array( null, "a\nb\n" ),
			'deleted.txt' => array( "a\n", null ),
			'changed.txt' => array( "1\n2\n3\n4\n5\n6\n7\n8\n9\n", "1\n2\n3\n4\nfive\n6\n7\n8\n9\n" ),
			'no-eol.txt'  => array( "a\nb", "a\nc" ),
			'add-eol.txt' => array( "a\nb", "a\nb\n" ),
			'empty.txt'   => array( null, '' ),
		);
		$directory = $this->temp_directory();
		$patch     = '';
		foreach ( $cases as $path => [ $before, $after ] ) {
			if ( null !== $before ) {
				file_put_contents( $directory . '/' . $path, $before );
			}
			$patch .= Diff::unified( $path, $before, $after );
		}
		file_put_contents( $directory . '/changes.patch', $patch );

		exec( 'cd ' . escapeshellarg( $directory ) . ' && git apply --unsafe-paths changes.patch 2>&1', $output, $code );
		self::assertSame( 0, $code, implode( "\n", $output ) . "\n" . $patch );

		foreach ( $cases as $path => [ , $after ] ) {
			if ( null === $after ) {
				self::assertFileDoesNotExist( $directory . '/' . $path );
			} else {
				self::assertSame( $after, file_get_contents( $directory . '/' . $path ), $path );
			}
		}
		self::assertSame( '', Diff::unified( 'same.txt', 'x', 'x' ) );
	}

	public function test_same_library(): void {
		self::assertTrue( Libraries::same_library( 'https://lib.example.com/wp-json/x', 'https://lib.example.com' ) );
		self::assertTrue( Libraries::same_library( 'https://example.com/lib/a', 'https://example.com/lib/' ) );
		self::assertFalse( Libraries::same_library( 'https://example.com/library', 'https://example.com/lib' ) );
		self::assertFalse( Libraries::same_library( 'https://lib.example.com.evil.io', 'https://lib.example.com' ) );
		self::assertFalse( Libraries::same_library( 'http://lib.example.com', 'https://lib.example.com' ) );
		self::assertFalse( Libraries::same_library( 'https://lib.example.com:8443', 'https://lib.example.com' ) );
		self::assertNull( Libraries::allowed() );
		self::assertSame( array(), Libraries::headers( 'https://lib.example.com' ) );
	}
}
