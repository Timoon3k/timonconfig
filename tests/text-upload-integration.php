<?php
/** Disposable local validation: encodings are inspected without rewriting private downloads. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
$checks = 0; $paths = array();
$assert = static function ( bool $ok, string $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$inspect = new ReflectionMethod( TC_Private_Files::class, 'inspect' );
$fixture = static function ( string $bytes ) use ( &$paths ): string { $path = wp_tempnam( 'tc-text-fixture' ); if ( ! is_string( $path ) ) { throw new RuntimeException( 'No fixture temp storage.' ); } file_put_contents( $path, $bytes ); $paths[] = $path; return $path; };
try {
    $plain = "Sensitivity = 10\nProfile = Test\n";
    $utf16le = "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', $plain );
    $utf16be = "\xfe\xff" . iconv( 'UTF-8', 'UTF-16BE', $plain );
    foreach ( array( 'utf8' => $plain, 'utf8-bom' => "\xef\xbb\xbf" . $plain, 'utf16le' => $utf16le, 'utf16be' => $utf16be, 'mz-text' => "MZ sensitivity = 10\n" ) as $kind => $bytes ) {
        $path = $fixture( $bytes ); $result = $inspect->invoke( null, $path, 'config.txt' );
        $assert( is_array( $result ), 'Valid TXT accepted: ' . $kind );
        $assert( $bytes === file_get_contents( $path ) && $result['size'] === strlen( $bytes ) && $result['checksum'] === hash( 'sha256', $bytes ), 'Original bytes/size/checksum preserved: ' . $kind );
    }
    foreach ( array(
        "PK\x03\x04archive", "MZ\0\x01binary", "\x7fELF\0payload", "\xff\xfe\x00\xd8", "\xef\xbb\xbf", "\xff\xfe",
        "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', '<?php echo 1;' ),
        "\xfe\xff" . iconv( 'UTF-8', 'UTF-16BE', '<script>alert(1)</script>' ),
        "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', "#!/bin/sh\nexit 0" ),
        "\xef\xbb\xbf#!/bin/sh\nexit 0", "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', "A\0B" ), "\x00\x00\xfe\xffUTF32",
    ) as $index => $bytes ) {
        $assert( is_wp_error( $inspect->invoke( null, $fixture( $bytes ), 'renamed.txt' ) ), 'Encoded executable/binary/malformed TXT rejected: ' . $index );
    }
    $gpc = "// Copyright \xa9 TimonConfigs \x96 local fixture\r\ndefine TEST_BUTTON = PS4_CROSS;\r\nmain { if(get_val(TEST_BUTTON)) { set_val(TEST_BUTTON, 100); } }\r\n";
    $source = "// Local GPC fixture\r\nmain { set_val(PS4_CROSS, 0); }\r\n";
    foreach ( array( 'ansi-gpc' => $gpc, 'utf8-bom-gpc' => "\xef\xbb\xbf" . $source, 'utf16le-gpc' => "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', $source ), 'utf16be-gpc' => "\xfe\xff" . iconv( 'UTF-8', 'UTF-16BE', $source ) ) as $kind => $bytes ) {
        $path = $fixture( $bytes ); $result = $inspect->invoke( null, $path, 'source.gpc' );
        $assert( is_array( $result ), 'Windows GPC source accepted: ' . $kind );
        $assert( $bytes === file_get_contents( $path ) && $result['checksum'] === hash( 'sha256', $bytes ), 'GPC original bytes preserved: ' . $kind );
    }
    foreach ( array( 'txt', 'gpc', 'cfg', 'ini' ) as $extension ) {
        $result = $inspect->invoke( null, $fixture( "Profile = Timon\x92s test\r\n" ), 'config.' . $extension );
        $assert( is_array( $result ), 'Windows ANSI text accepted: ' . $extension );
        foreach ( array( '<?php echo 1;', '<script>alert(1)</script>', "#!/bin/sh\nexit 0", '<svg onload="alert(1)"></svg>', '<!doctype html><html>active</html>' ) as $payload ) {
            $encoded = "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', $payload );
            $assert( is_wp_error( $inspect->invoke( null, $fixture( $encoded ), 'bad.' . $extension ) ), 'Encoded active payload rejected: ' . $extension );
        }
    }
    // libmagic language guesses must not veto plain GPC source with harmless syntax.
    $pascal = "program LocalFixture;\nvar x: integer;\nbegin x := 1; end.\n";
    $assert( is_array( $inspect->invoke( null, $fixture( $pascal ), 'source.gpc' ) ), 'Benign text language heuristic is accepted as source text.' );
    $unsupported = $inspect->invoke( null, $fixture( "\x00\xff\x01\x02binary" ), 'source.gpc' );
    $assert( is_wp_error( $unsupported ) && str_contains( $unsupported->get_error_message(), 'application/octet-stream' ) && ! str_contains( $unsupported->get_error_message(), 'binary' . chr( 0 ) ), 'Safe MIME diagnostic identifies rejected binary without raw content.' );
    $assert( is_array( $inspect->invoke( null, $fixture( $plain ), 'backup.znbk' ) ), 'ZNBK does not require external approval; automatic checks still apply.' );
    $veto = static fn() => false;
    add_filter( 'timonconfigs_private_file_scan', $veto, PHP_INT_MAX );
    try {
        $assert( is_wp_error( $inspect->invoke( null, $fixture( $plain ), 'backup.znbk' ) ), 'An explicitly rejecting optional scanner still vetoes ZNBK.' );
        $assert( is_wp_error( $inspect->invoke( null, $fixture( $plain ), 'config.txt' ) ), 'An explicitly rejecting optional scanner still vetoes TXT.' );
    } finally { remove_filter( 'timonconfigs_private_file_scan', $veto, PHP_INT_MAX ); }
    WP_CLI::success( "Text uploads: $checks assertions; original bytes preserved, optional scanner veto respected." );
} finally { foreach ( $paths as $path ) { if ( is_file( $path ) ) { unlink( $path ); } } }
