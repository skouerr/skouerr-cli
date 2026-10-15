<?php
/**
 * Remote, Process and Dashicons tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Support\Dashicons;
use Skouerr\Cli\Support\Process;
use Skouerr\Cli\Support\Remote;

final class SupportTest extends TestCase {

	/**
	 * @return iterable<array{string, bool}>
	 */
	public static function urls(): iterable {
		yield array( 'https://download.skouerr.dev/x', true );
		yield array( 'http://localhost', true );
		yield array( 'http://skouerr.test/', true );
		yield array( 'http://127.0.0.1:8080', true );
		yield array( 'http://example.com', false );
		yield array( 'http://localhost.evil.com', false );
		yield array( 'ftp://example.com', false );
		yield array( 'file:///etc/passwd', false );
		yield array( 'https://user:pass@example.com', false );
		yield array( 'not a url', false );
	}

	#[DataProvider( 'urls' )]
	public function test_safe_urls( string $url, bool $allowed ): void {
		if ( ! $allowed ) {
			$this->expectException( CliException::class );
		}
		Remote::assert_safe_url( $url );
		self::assertTrue( $allowed );
	}

	public function test_process_does_not_use_a_shell(): void {
		$directory = $this->temp_directory();
		$result    = Process::run( array( 'php', '-r', 'echo $argv[1];', 'x; touch pwned' ), $directory );

		self::assertSame( 0, $result['code'] );
		self::assertSame( 'x; touch pwned', $result['stdout'] );
		self::assertFileDoesNotExist( $directory . '/pwned' );
	}

	public function test_process_reports_failures(): void {
		$result = Process::run( array( 'php', '-r', 'fwrite(STDERR, "boom"); exit(3);' ), $this->temp_directory() );
		self::assertSame( 3, $result['code'] );
		self::assertSame( 'boom', $result['stderr'] );
	}

	public function test_process_find(): void {
		self::assertNotNull( Process::find( 'php' ) );
		self::assertNull( Process::find( 'skouerr-does-not-exist' ) );
	}

	public function test_dashicons(): void {
		self::assertSame( 'admin-post', Dashicons::normalize( ' Dashicons-Admin-Post ' ) );
		self::assertTrue( Dashicons::exists( 'dashicons-admin-post' ) );
		self::assertTrue( Dashicons::exists( 'block-default' ) );
		self::assertFalse( Dashicons::exists( 'not-an-icon' ) );
	}
}
