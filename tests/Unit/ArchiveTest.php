<?php
/**
 * Archive tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Support\Archive;

final class ArchiveTest extends TestCase {

	/**
	 * Builds a ZIP archive.
	 *
	 * @param array<string, string> $files    Contents by entry name.
	 * @param list<string>          $symlinks Entry names stored as symbolic links.
	 */
	private function zip( array $files, array $symlinks = array() ): string {
		$path = $this->temp_directory() . '/a.zip';
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE );
		foreach ( $files as $name => $contents ) {
			$zip->addFromString( $name, $contents );
			if ( in_array( $name, $symlinks, true ) ) {
				$zip->setExternalAttributesName( $name, \ZipArchive::OPSYS_UNIX, ( 0120777 << 16 ) );
			}
		}
		$zip->close();
		return $path;
	}

	public function test_extracts_valid_archive(): void {
		$directory = Archive::extract_to_temp(
			$this->zip(
				array(
					'style.css'       => 'a',
					'blocks/x/x.json' => '{}',
				)
			)
		);

		self::assertSame( 'a', file_get_contents( $directory . '/style.css' ) );
		self::assertSame( '{}', file_get_contents( $directory . '/blocks/x/x.json' ) );
		Archive::remove( $directory );
		self::assertDirectoryDoesNotExist( $directory );
	}

	public function test_rejects_traversal(): void {
		$this->expectException( CliException::class );
		Archive::extract_to_temp( $this->zip( array( '../../evil.php' => '<?php' ) ) );
	}

	public function test_rejects_absolute_path(): void {
		$this->expectException( CliException::class );
		Archive::normalize( '/etc/cron.d/x' );
	}

	public function test_rejects_symlinks(): void {
		$this->expectException( CliException::class );
		Archive::extract_to_temp( $this->zip( array( 'link' => '/etc/passwd' ), array( 'link' ) ) );
	}

	public function test_rejects_server_configuration(): void {
		$this->expectException( CliException::class );
		Archive::extract_to_temp( $this->zip( array( 'assets/.htaccess' => 'php_flag engine on' ) ) );
	}

	public function test_rejects_oversized_archive(): void {
		$this->expectException( CliException::class );
		Archive::extract_to_temp( $this->zip( array( 'big.txt' => str_repeat( 'a', 2048 ) ) ), 10, 1024 );
	}

	public function test_rejects_non_zip(): void {
		$path = $this->temp_directory() . '/x.zip';
		file_put_contents( $path, '<html>404</html>' );
		$this->expectException( CliException::class );
		Archive::extract_to_temp( $path );
	}
}
