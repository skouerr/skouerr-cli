<?php
/**
 * Fake Skouerr server for tests/integration/library.sh (php -S 127.0.0.1:8099 router.php).
 *
 * @package Skouerr_CLI
 */

$path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

// Private library: same content under /private, behind a bearer token.
if ( str_starts_with( $path, '/private/' ) ) {
	if ( 'Bearer secret' !== ( $_SERVER['HTTP_AUTHORIZATION'] ?? '' ) ) {
		http_response_code( 401 );
		exit;
	}
	$path = substr( $path, strlen( '/private' ) );
}

/**
 * Sends a ZIP built from files.
 *
 * @param array<string, string> $files    Contents by entry name.
 * @param string|null           $checksum Value of the X-Skouerr-Sha256 header, null for the real one.
 */
function send_zip( array $files, ?string $checksum = null ): void {
	$path = tempnam( sys_get_temp_dir(), 'zip' );
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::OVERWRITE );
	foreach ( $files as $name => $contents ) {
		$zip->addFromString( $name, $contents );
	}
	$zip->close();
	header( 'Content-Type: application/zip' );
	header( 'X-Skouerr-Sha256: ' . ( $checksum ?? hash_file( 'sha256', $path ) ) );
	readfile( $path );
	unlink( $path );
}

switch ( $path ) {
	case '/theme':
		$name = preg_replace( '/[^a-z0-9-]/', '', (string) ( $_GET['name'] ?? 'theme' ) );
		send_zip(
			array(
				'style.css'            => "/*\nTheme Name: " . ( $_GET['title'] ?? 'Theme' ) . "\nText Domain: $name\n*/\n",
				'theme.json'           => '{"version": 3}',
				'templates/index.html' => '<!-- wp:post-content /-->',
			)
		);
		break;

	case '/wp-json/skouerr/v1/library/blocks':
		header( 'Content-Type: application/json' );
		echo json_encode( array( array( 'name' => 'promo' ), array( 'name' => 'evil' ), array( 'name' => '../bad' ) ) );
		break;

	case '/wp-json/skouerr/v1/library/blocks/promo':
		send_zip(
			array(
				'promo.block.json'     => '{"name": "skouerr/promo", "title": "Promo"}',
				'promo.template.php'   => '<div class="block block-promo"></div>',
				'src/promo.style.scss' => '',
			)
		);
		break;

	case '/wp-json/skouerr/v1/library/blocks/evil':
		send_zip( array( '../../../evil.php' => '<?php echo "pwned";' ) );
		break;

	case '/wp-json/skouerr/v1/library/blocks/tampered':
		send_zip( array( 'tampered.block.json' => '{}' ), str_repeat( '0', 64 ) );
		break;

	default:
		http_response_code( 404 );
		echo 'Not found';
}
