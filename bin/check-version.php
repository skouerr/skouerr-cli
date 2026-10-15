<?php
/**
 * Checks that the version is the same everywhere (and matches a tag, if given).
 *
 * Usage: php bin/check-version.php [tag]
 *
 * @package Skouerr_CLI
 */

$root     = dirname( __DIR__ );
$composer = json_decode( (string) file_get_contents( $root . '/composer.json' ), true );
$plugin   = (string) file_get_contents( $root . '/skouerr-cli.php' );

preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $plugin, $header );
preg_match( "/define\(\s*'SKOUERR_CLI_VERSION',\s*'([^']+)'/", $plugin, $constant );

$versions = array(
	'composer.json'          => $composer['version'] ?? null,
	'plugin header'          => $header[1] ?? null,
	'SKOUERR_CLI_VERSION'    => $constant[1] ?? null,
	'tests/phpstan-bootstrap' => preg_match( "/'SKOUERR_CLI_VERSION', '([^']+)'/", (string) file_get_contents( $root . '/tests/phpstan-bootstrap.php' ), $stub ) ? $stub[1] : null,
);
if ( isset( $argv[1] ) && '' !== $argv[1] ) {
	$versions['tag'] = ltrim( $argv[1], 'v' );
}

if ( count( array_unique( $versions ) ) !== 1 || in_array( null, $versions, true ) ) {
	foreach ( $versions as $source => $version ) {
		fwrite( STDERR, sprintf( "%-24s %s\n", $source, $version ?? '(missing)' ) );
	}
	fwrite( STDERR, "Versions differ.\n" );
	exit( 1 );
}

$version = reset( $versions );
if ( ! preg_match( '/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', (string) $version ) ) {
	fwrite( STDERR, "Not a semantic version: $version\n" );
	exit( 1 );
}

echo $version, PHP_EOL;
