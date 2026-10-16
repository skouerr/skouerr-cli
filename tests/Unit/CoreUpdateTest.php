<?php
/**
 * Core update tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use Skouerr\Cli\Support\CoreUpdate;

final class CoreUpdateTest extends TestCase {

	private const array REFERENCE = array(
		'class-skouerr-block.php'  => "<?php // block v2\n",
		'class-skouerr-loader.php' => "<?php // loader v2\n",
		'setup.php'                => "<?php // setup v2\n",
	);

	/**
	 * @return array<string, mixed>
	 */
	private static function meta(): array {
		return array(
			'version' => '2.0.0',
			'history' => array(
				'class-skouerr-loader.php' => array( CoreUpdate::hash( "<?php // loader v1\n" ) ),
				'class-skouerr-mail.php'   => array( CoreUpdate::hash( "<?php // mail v1\n" ) ),
			),
			'removed' => array(
				'class-skouerr-mail.php' => array( 'symbols' => array( 'Skouerr_Mail' ) ),
			),
		);
	}

	/**
	 * @param list<array{file: string, status: string}> $plan Plan.
	 * @return array<string, string>
	 */
	private static function statuses( array $plan ): array {
		return array_column( $plan, 'status', 'file' );
	}

	public function test_plan(): void {
		$current = array(
			'class-skouerr-block.php'  => "<?php // block v2\r\n",
			'class-skouerr-loader.php' => "<?php // loader v1\n",
			'class-skouerr-mail.php'   => "<?php // mail v1\n",
		);

		self::assertSame(
			array(
				'class-skouerr-block.php'  => CoreUpdate::UP_TO_DATE,
				'class-skouerr-loader.php' => CoreUpdate::UPDATE,
				'setup.php'                => CoreUpdate::CREATE,
				'class-skouerr-mail.php'   => CoreUpdate::REMOVE,
			),
			self::statuses( CoreUpdate::plan( self::REFERENCE, self::meta(), $current, array(), false ) )
		);
	}

	public function test_files_changed_in_the_project_are_kept_unless_forced(): void {
		$current = array(
			'class-skouerr-loader.php' => "<?php // loader v1 + project code\n",
			'class-skouerr-mail.php'   => "<?php // new Skouerr_Mail(); commented out\n",
		);

		$kept = self::statuses( CoreUpdate::plan( self::REFERENCE, self::meta(), $current, array(), false ) );
		self::assertSame( CoreUpdate::MODIFIED, $kept['class-skouerr-loader.php'] );
		self::assertSame( CoreUpdate::KEEP, $kept['class-skouerr-mail.php'] );

		$forced = self::statuses( CoreUpdate::plan( self::REFERENCE, self::meta(), $current, array(), true ) );
		self::assertSame( CoreUpdate::OVERWRITE, $forced['class-skouerr-loader.php'] );
		self::assertSame( CoreUpdate::REMOVE, $forced['class-skouerr-mail.php'] );
	}

	public function test_installed_manifest_marks_pristine_files(): void {
		$current   = array( 'setup.php' => "<?php // setup v1.5\n" );
		$installed = array(
			'version' => '1.9.0',
			'files'   => array( 'setup.php' => CoreUpdate::hash( "<?php // setup v1.5\n" ) ),
		);

		self::assertSame( CoreUpdate::UPDATE, self::statuses( CoreUpdate::plan( self::REFERENCE, self::meta(), $current, $installed, false ) )['setup.php'] );
	}

	public function test_manifest_never_records_a_changed_file(): void {
		$current = array( 'class-skouerr-loader.php' => "<?php // project code\n" );
		$plan    = CoreUpdate::plan( self::REFERENCE, self::meta(), $current, array(), false );

		$manifest = CoreUpdate::manifest( $plan, self::REFERENCE, array(), '2.0.0' );

		self::assertSame( '2.0.0', $manifest['version'] );
		self::assertArrayNotHasKey( 'class-skouerr-loader.php', $manifest['files'] );
		self::assertSame( CoreUpdate::hash( self::REFERENCE['setup.php'] ), $manifest['files']['setup.php'] );
	}

	public function test_remove_require(): void {
		$php = "<?php\nrequire_once get_template_directory() . '/inc/core/class-skouerr-block.php';\n"
			. "require_once get_template_directory() . '/inc/core/class-skouerr-mail.php';\n"
			. "\t// require get_template_directory() . \"/inc/core/class-skouerr-mail.php\";\n"
			. "require_once get_template_directory() . '/inc/core/dev.php';\n";

		self::assertSame(
			"<?php\nrequire_once get_template_directory() . '/inc/core/class-skouerr-block.php';\nrequire_once get_template_directory() . '/inc/core/dev.php';\n",
			CoreUpdate::remove_require( $php, 'class-skouerr-mail.php' )
		);
	}

	public function test_shipped_reference_is_consistent(): void {
		$directory = SKOUERR_CLI_PATH . '/templates/core';
		$meta      = CoreUpdate::read_manifest( $directory . '/' . CoreUpdate::REFERENCE_MANIFEST );

		self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', (string) $meta['version'] );
		self::assertFileExists( $directory . '/class-skouerr-loader.php' );
		self::assertArrayHasKey( 'class-skouerr-mail.php', $meta['removed'] );
		foreach ( array_keys( $meta['removed'] ) as $file ) {
			self::assertFileDoesNotExist( $directory . '/' . $file, 'A removed file cannot be shipped.' );
		}
	}
}
