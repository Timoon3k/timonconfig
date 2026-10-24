<?php
/** Disposable localhost WordPress regression: reviewed config files preserve opaque bytes. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost WordPress required.' );
}
$checks = 0;
$paths = array();
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks;
};
$inspect = new ReflectionMethod( TC_Private_Files::class, 'inspect' );
$fixture = static function ( string $bytes ) use ( &$paths ): string {
	$path = wp_tempnam( 'tc-raw-private-fixture' );
	if ( ! is_string( $path ) ) { throw new RuntimeException( 'No temporary test storage.' ); }
	file_put_contents( $path, $bytes );
	$paths[] = $path;
	return $path;
};
try {
	$plain = "Sensitivity = 10\nProfile = Test\n";
	$utf16le = "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', $plain );
	$utf16be = "\xfe\xff" . iconv( 'UTF-8', 'UTF-16BE', $plain );
	$cases = array(
		'utf8' => $plain,
		'utf8-bom' => "\xef\xbb\xbf" . $plain,
		'utf16le' => $utf16le,
		'utf16be' => $utf16be,
		'windows-ansi' => "Copyright \x96 \xa9\n",
		'control' => "// Real source\nmain { set_val(PS4_RX, 0); }\x00\x0b\x1b\n",
		'raw-binary' => "\x00\x01\x02\xff\xfe\x8d\x81",
		'zip-envelope' => "PK\x03\x04raw archive bytes",
		'php-contents' => "<?php echo 'stored but never executed'; ?>",
		'invalid-json' => '{not a real json document}',
		'shell-contents' => "#!/bin/sh\nexit 0",
	);
	foreach ( array( 'gpc', 'znbk', 'ximmr', 'txt', 'cfg', 'ini', 'json' ) as $extension ) {
		$assert( in_array( $extension, TC_Private_Files::extensions(), true ), 'Test format enabled: ' . $extension );
		foreach ( $cases as $name => $bytes ) {
			$path = $fixture( $bytes );
			$result = $inspect->invoke( null, $path, 'PREDATOR SCRIPT S29_PIN_350305.' . $extension );
			$assert( is_array( $result ) && 'owner-private-raw' === $result['inspection'], 'Raw config accepts ' . $name . ' in ' . $extension );
			$assert( $bytes === file_get_contents( $path ) && $result['checksum'] === hash( 'sha256', $bytes ) && $result['size'] === strlen( $bytes ), 'File bytes and checksum preserved ' . $name . ' / ' . $extension );
		}
	}
	foreach ( array( 'script.php', 'script.exe', 'shell.sh', 'archive.zip', 'hidden.php.gpc', '../config.gpc', 'unsafe\\config.gpc' ) as $name ) {
		$assert( is_wp_error( $inspect->invoke( null, $fixture( $plain ), $name ) ), 'Unapproved extension/path cannot be uploaded: ' . $name );
	}
	$assert( is_wp_error( $inspect->invoke( null, $fixture( '' ), 'empty.gpc' ) ), 'Zero-byte uploads are denied.' );
	$veto = static fn() => false;
	add_filter( 'timonconfigs_private_file_scan', $veto, PHP_INT_MAX );
	try {
		$assert( is_wp_error( $inspect->invoke( null, $fixture( $plain ), 'blocked.gpc' ) ), 'External trusted scanner veto remains supported.' );
	} finally {
		remove_filter( 'timonconfigs_private_file_scan', $veto, PHP_INT_MAX );
	}
	WP_CLI::success( "Private raw uploads: $checks assertions; all bytes unchanged, scanner veto and file boundary controls preserved." );
} finally {
	foreach ( $paths as $path ) { if ( is_file( $path ) ) { unlink( $path ); } }
}
