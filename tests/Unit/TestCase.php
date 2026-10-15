<?php
/**
 * Base test case.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use Skouerr\Cli\Support\Archive;

/**
 * Creates and removes temporary directories.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Temporary directories to remove.
	 *
	 * @var list<string>
	 */
	private array $directories = array();

	protected function temp_directory(): string {
		$directory = sys_get_temp_dir() . '/skouerr-test-' . bin2hex( random_bytes( 6 ) );
		mkdir( $directory );
		$this->directories[] = $directory;
		return $directory;
	}

	protected function tearDown(): void {
		foreach ( $this->directories as $directory ) {
			Archive::remove( $directory );
		}
		parent::tearDown();
	}
}
