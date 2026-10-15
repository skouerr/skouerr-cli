<?php
/**
 * Template tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use Skouerr\Cli\Support\Template;

final class TemplateTest extends TestCase {

	public function test_php_single_quoted_string_cannot_be_escaped(): void {
		$payload = "'; system('id'); //";
		$code    = Template::render( "<?php return '%SK_X%';", array( 'SK_X' => $payload ), 'php' );
		$file    = $this->temp_directory() . '/t.php';
		file_put_contents( $file, $code );

		self::assertSame( $payload, require $file );
	}

	public function test_php_backslash(): void {
		$file = $this->temp_directory() . '/t.php';
		file_put_contents( $file, Template::render( "<?php return '%SK_X%';", array( 'SK_X' => 'a\\\'b\\' ), 'php' ) );
		self::assertSame( 'a\\\'b\\', require $file );
	}

	public function test_json(): void {
		$value   = "Hero \"quoted\" \\ é\n";
		$json    = Template::render( '{"title": "%SK_X%"}', array( 'SK_X' => $value ), 'json' );
		$decoded = json_decode( $json, true );
		self::assertSame( $value, $decoded['title'] );
	}

	public function test_js(): void {
		$js = Template::render( "title: '%SK_X%'", array( 'SK_X' => "a'b\"c\\d\n</script>`" ), 'js' );
		self::assertSame( "title: 'a\\'b\\\"c\\\\d\\n<\\/script>\\`'", $js );
	}

	public function test_placeholders_are_replaced_once(): void {
		self::assertSame(
			'%SK_B% b',
			Template::render(
				'%SK_A% %SK_B%',
				array(
					'SK_A' => '%SK_B%',
					'SK_B' => 'b',
				),
				'text'
			)
		);
	}

	public function test_context_for(): void {
		self::assertSame( 'php', Template::context_for( 'x.controller.php' ) );
		self::assertSame( 'json', Template::context_for( 'x.block.json' ) );
		self::assertSame( 'js', Template::context_for( 'src/x.script.js' ) );
		self::assertSame( 'text', Template::context_for( 'x.template.twig' ) );
	}
}
