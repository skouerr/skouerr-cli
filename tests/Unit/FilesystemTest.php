<?php
/**
 * Filesystem tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Skouerr\Cli\Console\BufferWriter;
use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\ExitCode;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Support\Filesystem;

final class FilesystemTest extends TestCase {

	private function filesystem( string $root, bool $force = false, bool $dry_run = false ): Filesystem {
		return new Filesystem( $root, new Output( new BufferWriter(), 'test', false, $dry_run ), $force );
	}

	/**
	 * @return iterable<array{string}>
	 */
	public static function unsafe_paths(): iterable {
		yield array( '../wp-config.php' );
		yield array( 'templates/../../x' );
		yield array( '/etc/passwd' );
		yield array( 'C:/x' );
		yield array( "a\0b" );
		yield array( '..\\x' );
		yield array( '' );
		yield array( './' );
	}

	#[DataProvider( 'unsafe_paths' )]
	public function test_rejects_unsafe_paths( string $path ): void {
		$this->expectException( CliException::class );
		$this->filesystem( $this->temp_directory() )->path( $path );
	}

	public function test_writes_and_creates_directories(): void {
		$root = $this->temp_directory();
		$path = $this->filesystem( $root )->write( 'blocks/hero/hero.block.json', '{}' );

		self::assertSame( $root . '/blocks/hero/hero.block.json', $path );
		self::assertSame( '{}', file_get_contents( $path ) );
	}

	public function test_refuses_to_overwrite_without_force(): void {
		$root = $this->temp_directory();
		file_put_contents( $root . '/a.txt', 'original' );

		try {
			$this->filesystem( $root )->write_many(
				array(
					'b.txt' => 'new',
					'a.txt' => 'changed',
				)
			);
			self::fail( 'Conflict expected' );
		} catch ( CliException $e ) {
			self::assertSame( ExitCode::Conflict, $e->exit_code );
		}

		self::assertSame( 'original', file_get_contents( $root . '/a.txt' ) );
		self::assertFileDoesNotExist( $root . '/b.txt', 'Nothing is written when one target conflicts.' );

		$this->filesystem( $root, true )->write( 'a.txt', 'changed' );
		self::assertSame( 'changed', file_get_contents( $root . '/a.txt' ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$root = $this->temp_directory();
		$this->filesystem( $root, false, true )->write( 'x/y.txt', 'data' );
		self::assertDirectoryDoesNotExist( $root . '/x' );
	}

	public function test_refuses_symlinked_directory_outside_root(): void {
		$root    = $this->temp_directory();
		$outside = $this->temp_directory();
		symlink( $outside, $root . '/blocks' );

		$this->expectException( CliException::class );
		$this->filesystem( $root )->write( 'blocks/evil.php', '<?php' );
	}

	public function test_copy_tree(): void {
		$source = $this->temp_directory();
		mkdir( $source . '/src' );
		file_put_contents( $source . '/a.txt', 'a' );
		file_put_contents( $source . '/src/b.txt', 'b' );
		$root = $this->temp_directory();

		$paths = $this->filesystem( $root )->copy_tree( $source, 'blocks/x' );

		sort( $paths );
		self::assertSame( array( $root . '/blocks/x/a.txt', $root . '/blocks/x/src/b.txt' ), $paths );
	}
}
