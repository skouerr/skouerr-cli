<?php
/**
 * Copies the core files of the starter theme into templates/core/, for `wp skouerr update:core`.
 *
 * Usage: php bin/sync-core.php /path/to/skouerr-theme 2.0.0
 *
 * - templates/core/*.php: the inc/core/ files of the starter (working tree);
 * - templates/core/manifest.json: version, hashes of every version of each file in
 *   the starter history (all branches), the files removed from the core with the
 *   classes and functions they declared, and the older file names whose code moved
 *   to a current file (older layout, not migrated by update:core);
 * - <starter>/inc/core/skouerr-core.json: the manifest of the starter itself, so a theme
 *   created from it can be updated without a prior `update:core`.
 *
 * @package Skouerr_CLI
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.Security.EscapeOutput

require __DIR__ . '/../src/Support/CoreUpdate.php';

use Skouerr\Cli\Support\CoreUpdate;

if ( 3 !== $argc || 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $argv[2] ) ) {
	fwrite( STDERR, "Usage: php bin/sync-core.php <starter theme directory> <version x.y.z>\n" );
	exit( 2 );
}

$starter = rtrim( $argv[1], '/' );
$version = $argv[2];
$target  = dirname( __DIR__ ) . '/templates/core';

/**
 * Runs git in the starter repository.
 *
 * @param string       $starter Repository.
 * @param list<string> $args    Arguments.
 * @return string Output.
 */
function sync_core_git( string $starter, array $args ): string {
	$command = array_merge( array( 'git', '-c', 'safe.directory=*', '-C', $starter ), $args );
	$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) {
		fwrite( STDERR, "Cannot run git.\n" );
		exit( 1 );
	}
	$output = (string) stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	proc_close( $process );
	return $output;
}

$current = array();
foreach ( glob( $starter . '/inc/core/*.php' ) ?: array() as $path ) {
	$current[ basename( $path ) ] = (string) file_get_contents( $path );
}
if ( empty( $current ) ) {
	fwrite( STDERR, "No inc/core/*.php in {$starter}.\n" );
	exit( 1 );
}

// Every core file name ever committed, on every branch.
$names = array();
foreach ( explode( "\n", sync_core_git( $starter, array( 'log', '--all', '--format=', '--name-only', '--', 'inc/core/' ) ) ) as $line ) {
	if ( 1 === preg_match( '#^inc/core/([^/]+\.php)$#', trim( $line ), $match ) ) {
		$names[ $match[1] ] = true;
	}
}

// Classes and functions declared by the current core files.
$declared = array();
foreach ( $current as $name => $contents ) {
	preg_match_all( '/^\s*(?:final\s+|abstract\s+)?(?:class|function)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $symbols );
	foreach ( $symbols[1] as $symbol ) {
		$declared[ $symbol ] = $name;
	}
}

$history = array();
$removed = array();
$renamed = array();
foreach ( array_keys( $names + $current ) as $name ) {
	$hashes = array();
	$last   = null;
	foreach ( array_filter( explode( "\n", sync_core_git( $starter, array( 'log', '--all', '--format=%H', '--', 'inc/core/' . $name ) ) ) ) as $commit ) {
		$contents = sync_core_git( $starter, array( 'show', $commit . ':inc/core/' . $name ) );
		if ( '' === $contents ) {
			continue;
		}
		$last   ??= $contents;
		$hashes[] = CoreUpdate::hash( $contents );
	}
	$hashes = array_values( array_unique( $hashes ) );
	sort( $hashes );
	if ( ! empty( $hashes ) ) {
		$history[ $name ] = $hashes;
	}

	if ( ! isset( $current[ $name ] ) && null !== $last ) {
		preg_match_all( '/^\s*(?:final\s+|abstract\s+)?(?:class|function)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $last, $symbols );
		$symbols = array_values( array_unique( $symbols[1] ) );
		$moved   = array_values( array_unique( array_intersect_key( $declared, array_flip( $symbols ) ) ) );
		if ( ! empty( $moved ) ) {
			// Older layout (loader.php, block.php…): its code lives on in a current file.
			$renamed[ $name ] = $moved[0];
		} else {
			$removed[ $name ] = array( 'symbols' => $symbols );
		}
	}
}
ksort( $history );
ksort( $removed );
ksort( $renamed );

// templates/core/: exactly the current core files.
if ( ! is_dir( $target ) ) {
	mkdir( $target, 0755, true );
}
foreach ( glob( $target . '/*' ) ?: array() as $path ) {
	unlink( $path );
}
$files = array();
foreach ( $current as $name => $contents ) {
	file_put_contents( $target . '/' . $name, $contents );
	$files[ $name ] = CoreUpdate::hash( $contents );
}
ksort( $files );

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
file_put_contents(
	$target . '/' . CoreUpdate::REFERENCE_MANIFEST,
	json_encode(
		array(
			'version' => $version,
			'history' => (object) $history,
			'removed' => (object) $removed,
			'renamed' => (object) $renamed,
		),
		$flags
	) . "\n"
);
file_put_contents(
	$starter . '/inc/core/' . CoreUpdate::MANIFEST,
	json_encode(
		array(
			'version' => $version,
			'files'   => $files,
		),
		$flags
	) . "\n"
);

printf( "Core %s: %d files, %d removed, %d renamed, history of %d files.\n", $version, count( $files ), count( $removed ), count( $renamed ), count( $history ) );
