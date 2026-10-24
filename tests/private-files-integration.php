<?php
/** Disposable WordPress only: validates private storage without changing configured roots. */
defined( 'ABSPATH' ) || exit;
$tc_private_checks = 0;
$tc_private_ids = array();
$tc_private_original_user = get_current_user_id();
$tc_private_original_post = $_POST;
$tc_private_original_files = $_FILES;
$tc_private_original_document_root = $_SERVER['DOCUMENT_ROOT'] ?? null;
$tc_private_directory = sys_get_temp_dir() . '/tc-private-test-' . strtolower( wp_generate_password( 12, false ) );
mkdir( $tc_private_directory, 0700 );
$tc_private_root = $tc_private_directory . '/storage';
mkdir( $tc_private_root, 0700 );
$tc_private_selected_root = $tc_private_root;
$tc_private_root_filter = static function () use ( &$tc_private_selected_root ): mixed { return $tc_private_selected_root; };
$tc_private_scanner = null;
$tc_private_expect = static function ( bool $ok, string $message ) use ( &$tc_private_checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Private files: ' . $message ); } ++$tc_private_checks; };
$tc_private_call = static function ( string $method, mixed ...$args ): mixed { return ( new ReflectionMethod( TC_Private_Files::class, $method ) )->invoke( null, ...$args ); };
$tc_private_fixture = static function ( string $name, string $bytes ) use ( $tc_private_directory ): string {
	$path = $tc_private_directory . '/' . wp_generate_uuid4();
	file_put_contents( $path, $bytes );
	chmod( $path, 0600 );
	return $path;
};
$tc_private_remove = static function ( string $path ) use ( &$tc_private_remove ): void {
	if ( is_link( $path ) || is_file( $path ) ) { unlink( $path ); return; }
	if ( is_dir( $path ) ) { foreach ( scandir( $path ) as $entry ) { if ( '.' !== $entry && '..' !== $entry ) { $tc_private_remove( $path . '/' . $entry ); } } rmdir( $path ); }
};
add_filter( 'timonconfigs_private_storage_directory', $tc_private_root_filter );
try {
	$tc_private_expect( class_exists( 'TC_Private_Files' ), 'Core module is available.' );
	$tc_private_expect( TC_Private_Files::root() === $tc_private_root, 'An existing private root outside the website is accepted.' );
	foreach ( array( '', array( 'bad' ), '/does-not-exist-tc', ABSPATH, WP_CONTENT_DIR ) as $bad_root ) {
		$tc_private_selected_root = $bad_root;
		$tc_private_expect( is_wp_error( TC_Private_Files::root() ), 'Missing, malformed, or public roots fail closed.' );
	}
	$tc_private_selected_root = $tc_private_root;
	$_SERVER['DOCUMENT_ROOT'] = $tc_private_directory;
	$tc_private_expect( is_wp_error( TC_Private_Files::root() ), 'Additional actual document roots are forbidden.' );
	$_SERVER['DOCUMENT_ROOT'] = $tc_private_original_document_root ?? '';
	$tc_private_link = $tc_private_directory . '/root-link';
	symlink( $tc_private_root, $tc_private_link );
	$tc_private_selected_root = $tc_private_link;
	$tc_private_expect( is_wp_error( TC_Private_Files::root() ), 'A configured symlink root is rejected.' );
	$tc_private_selected_root = $tc_private_root;
	chmod( $tc_private_root, 0777 ); clearstatcache();
	$tc_private_expect( is_wp_error( TC_Private_Files::root() ), 'Other-user-writable roots are rejected.' );
	chmod( $tc_private_root, 0700 ); clearstatcache();

	$good = $tc_private_fixture( 'valid.gpc', "// Actual test-only script\nmain { set_val(PS4_RX, 0); }\n" );
	$inspection = $tc_private_call( 'inspect', $good, 'apex-test.gpc' );
	$tc_private_expect( is_array( $inspection ) && $inspection['size'] === filesize( $good ) && $inspection['checksum'] === hash_file( 'sha256', $good ), 'Inspection uses actual bytes and computes SHA-256.' );
	foreach ( array( '../private.gpc', 'folder\\private.gpc', 'payload.php.gpc', 'payload.zip', "bad\0.gpc", array( 'bad.gpc' ) ) as $bad_name ) {
		$tc_private_expect( is_wp_error( $tc_private_call( 'inspect', $good, $bad_name ) ), 'Traversal, executable/archives, NUL and malformed filenames are rejected.' );
	}
	// Original file bytes are treated as opaque private data, not decoded as source.
	// Only permitted extensions, filenames, size, Owner role and vault privacy limit uploads.
	foreach ( array(
		array( 'php.gpc', "<?php system('x');" ),
		array( 'html.txt', '<html><script>alert(1)</script></html>' ),
		array( 'binary.gpc', "\x7fELF\x00binary" ),
		array( 'archive.znbk', "PK\x03\x04archive" ),
		array( 'embedded.ximmr', "safe prefix PK\x03\x04archive" ),
		array( 'shell.txt', "#!/bin/sh\nexit 0" ),
		array( 'bad.json', '{invalid}' ),
		array( 'nul.txt', "\0\x01\x02binary" ),
		array( 'invalid-utf.gpc', "\xff\x00\x8d\r\n" ),
	) as $case ) {
		$path = $tc_private_fixture( $case[0], $case[1] );
		$result = $tc_private_call( 'inspect', $path, $case[0] );
		$tc_private_expect( is_array( $result ) && 'owner-private-raw' === $result['inspection'], 'Private byte vault accepts opaque content for allowed format: ' . $case[0] );
		$tc_private_expect( $result['checksum'] === hash_file( 'sha256', $path ) && $result['size'] === strlen( $case[1] ), 'Opaque source bytes and checksum are preserved: ' . $case[0] );
	}
	$json = $tc_private_fixture( 'valid.json', '{"controller":"test"}' );
	$tc_private_expect( is_array( $tc_private_call( 'inspect', $json, 'valid.json' ) ), 'Valid JSON is stored without parsing.' );
	$empty = $tc_private_fixture( 'empty.gpc', '' );
	$tc_private_expect( is_wp_error( $tc_private_call( 'inspect', $empty, 'empty.gpc' ) ), 'Zero-byte files remain rejected.' );
	$oversize = $tc_private_fixture( 'large.gpc', 'x' );
	$stream = fopen( $oversize, 'r+' ); ftruncate( $stream, 20971521 ); fclose( $stream );
	$tc_private_expect( is_wp_error( $tc_private_call( 'inspect', $oversize, 'large.gpc' ) ), 'Host/storage maximum still protects the server.' );
	$opaque = $tc_private_fixture( 'binary.znbk', "TCBIN\0\x01\x02\xff" );
	$tc_private_expect( is_array( $tc_private_call( 'inspect', $opaque, 'binary.znbk' ) ), 'ZNBK stored as raw private bytes with no decoding or ZIP extraction.' );
	foreach ( array( 'znbk', 'ximmr' ) as $extension ) {
		$tc_private_expect( is_array( $tc_private_call( 'inspect', $good, 'text-backup.' . $extension ) ), 'Proprietary config backups use raw byte storage: ' . $extension );
	}
	if ( class_exists( 'ZipArchive' ) ) {
		$zen_zip_path = $tc_private_directory . '/' . wp_generate_uuid4() . '.znbk';
		$zip = new ZipArchive();
		$tc_private_expect( true === $zip->open( $zen_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ), 'Raw ZIP-backed ZNBK test fixture exists.' );
		$zip->addFromString( 'slots/config.json', '{"test":true}' );
		$zip->close();
		$raw_zip = $tc_private_call( 'inspect', $zen_zip_path, 'fixture-backup.znbk' );
		$tc_private_expect( is_array( $raw_zip ) && $raw_zip['checksum'] === hash_file( 'sha256', $zen_zip_path ), 'ZIP-backed ZNBK stored with original SHA-256, not extracted.' );
	}
	$tc_private_expect( is_wp_error( $tc_private_call( 'inspect', $good, 'malware.exe' ) ), 'Executable file extensions remain rejected even in the private vault.' );
	$tc_private_expect( is_wp_error( $tc_private_call( 'inspect', $good, 'malware.php.gpc' ) ), 'Double executable extensions remain rejected.' );
	$tc_private_scanner = static fn() => true;
	add_filter( 'timonconfigs_private_file_scan', $tc_private_scanner );
	$tc_private_expect( is_array( $tc_private_call( 'inspect', $opaque, 'binary.znbk' ) ), 'An approved inspector can accept a non-executable proprietary binary.' );

	foreach ( array( 'znbk', 'ximmr', 'gpc', 'txt' ) as $extension ) {
		$approved = $tc_private_call( 'inspect', $good, 'private-config.' . $extension );
		$tc_private_expect( is_array( $approved ) && 'scanner-approved' === $approved['inspection'], 'Optional scanner approval remains recorded for raw private data: ' . $extension );
	}
	remove_filter( 'timonconfigs_private_file_scan', $tc_private_scanner );
	$tc_private_scanner = static fn() => new WP_Error( 'fixture_scan', 'Do not leak scanner internals.' );
	add_filter( 'timonconfigs_private_file_scan', $tc_private_scanner );
	$rejected = $tc_private_call( 'inspect', $good, 'valid.gpc' );
	$tc_private_expect( is_wp_error( $rejected ) && ! str_contains( $rejected->get_error_message(), 'scanner internals' ), 'Scanner errors veto upload without exposing scanner details.' );
	remove_filter( 'timonconfigs_private_file_scan', $tc_private_scanner );
	$tc_private_scanner = null;

	$tc_private_expect( array() === $tc_private_call( 'uploads', null ), 'A zero-file input is normalized safely.' );
	$ten_empty = array( 'name' => array_fill( 0, 10, '' ), 'error' => array_fill( 0, 10, UPLOAD_ERR_NO_FILE ), 'size' => array_fill( 0, 10, 0 ), 'tmp_name' => array_fill( 0, 10, '' ) );
	$tc_private_expect( array() === $tc_private_call( 'uploads', $ten_empty ), 'Ten explicit no-file controls do not produce corrupt records.' );
	$eleven = array_map( static fn( array $values ): array => array_pad( $values, 11, $values[0] ), $ten_empty );
	$tc_private_expect( is_wp_error( $tc_private_call( 'uploads', $eleven ) ), 'More than ten files is rejected before processing.' );
	foreach ( array( 'name', 'error', 'size', 'tmp_name' ) as $key ) {
		$bad = $ten_empty; $bad[ $key ] = 'bad';
		$tc_private_expect( is_wp_error( $tc_private_call( 'uploads', $bad ) ), 'Malformed upload array ' . $key . ' fails safely.' );
	}
	$fake = array( 'name' => array( 'valid.gpc' ), 'error' => array( UPLOAD_ERR_OK ), 'size' => array( filesize( $good ) ), 'tmp_name' => array( $good ) );
	$tc_private_expect( is_wp_error( $tc_private_call( 'uploads', $fake ) ), 'An arbitrary local path cannot substitute for an HTTP upload.' );
	$bad = $ten_empty; unset( $bad['size'][3] );
	$tc_private_expect( is_wp_error( $tc_private_call( 'uploads', $bad ) ), 'Mismatched upload indexes are rejected.' );

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$tc_private_expect( ! empty( $admins ), 'A local administrator is available.' );
	foreach ( array( 'first', 'other' ) as $label ) {
		$id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'draft', 'post_title' => 'LOCAL PRIVATE FILE FIXTURE ' . $label ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$tc_private_ids[] = (int) $id;
	}
	$id = $tc_private_ids[0];
	$directory = $tc_private_root . '/' . $id;
	mkdir( $directory, 0700 );
	$file_id = wp_generate_uuid4();
	$stored = $file_id . '.gpc';
	copy( $good, $directory . '/' . $stored ); chmod( $directory . '/' . $stored, 0600 );
	$record = array( 'id' => $file_id, 'stored_name' => $stored, 'name' => 'private-test.gpc', 'size' => filesize( $good ), 'uploaded_at' => '2026-10-22 00:00:00', 'checksum' => hash_file( 'sha256', $good ) );
	update_post_meta( $id, '_tc_private_files', array( $record ) );
	$public = TC_Private_Files::files( $id );
	$tc_private_expect( 1 === count( $public ) && ! isset( $public[0]['stored_name'] ) && ! str_contains( wp_json_encode( $public ), $tc_private_root ), 'Display metadata never discloses stored paths or storage names.' );
	$tc_private_expect( TC_Private_Files::resolve( $id, $file_id ) === $directory . '/' . $stored, 'An owned file resolves with verified size/checksum.' );
	$tc_private_expect( is_wp_error( TC_Private_Files::resolve( $tc_private_ids[1], $file_id ) ), 'Cross-config file IDOR fails closed.' );
	$tc_private_expect( is_wp_error( TC_Private_Files::resolve( $id, '../' . $file_id ) ), 'Traversal-shaped IDs are rejected.' );
	$bad_record = $record; $bad_record['stored_name'] = '../' . $stored;
	update_post_meta( $id, '_tc_private_files', array( $bad_record ) );
	$tc_private_expect( is_wp_error( TC_Private_Files::resolve( $id, $file_id ) ), 'Forged stored-name traversal cannot escape its config directory.' );
	update_post_meta( $id, '_tc_private_files', array( $record ) );
	unlink( $directory . '/' . $stored ); symlink( $good, $directory . '/' . $stored );
	$tc_private_expect( is_wp_error( TC_Private_Files::resolve( $id, $file_id ) ), 'Symlink files are not delivered.' );
	$tc_private_expect( is_wp_error( $tc_private_call( 'usage', $tc_private_root ) ), 'Quota inspection rejects symlink entries.' );
	unlink( $directory . '/' . $stored ); copy( $good, $directory . '/' . $stored ); chmod( $directory . '/' . $stored, 0600 );
	file_put_contents( $directory . '/' . $stored, "tampered script\n" );
	$tc_private_expect( is_wp_error( TC_Private_Files::resolve( $id, $file_id ) ), 'Changed bytes revoke delivery through integrity checks.' );
	copy( $good, $directory . '/' . $stored );
	wp_set_current_user( 0 );
	$tc_private_expect( is_wp_error( $tc_private_call( 'mutate', $id, 'delete', array( 'file_id' => $file_id, 'confirm' => '1' ), null ) ), 'Anonymous mutation cannot bypass capability checks.' );
	$_POST = array( 'tc_private_files_nonce' => array( 'bad' ) ); $_FILES = array( 'tc_private_files' => $fake );
	TC_Private_Files::save( $id, get_post( $id ) );
	$tc_private_expect( get_post_meta( $id, '_tc_private_files', true ) === array( $record ), 'Malformed nonce and unauthorized save leave metadata unchanged.' );
	wp_set_current_user( (int) $admins[0] );
	$tc_private_expect( is_wp_error( $tc_private_call( 'mutate', $id, 'delete', array( 'file_id' => $file_id ), null ) ), 'Destructive actions require explicit confirmation.' );
	$result = $tc_private_call( 'mutate', $id, 'update', array( 'file_id' => $file_id, 'version' => '<b>2.0</b>', 'notes' => "Reviewed\nOwner note", 'order' => '3' ), null );
	$public = TC_Private_Files::files( $id );
	$tc_private_expect( true === $result && '2.0' === $public[0]['version'] && 3 === $public[0]['order'] && str_contains( $public[0]['notes'], "\n" ), 'Editable metadata is sanitized and retains multiline notes/order.' );
	$tc_private_expect( 1 === count( get_post_meta( $id, '_tc_private_file_audit', false ) ), 'Metadata changes append an audit record.' );
	$_POST = array( 'tc_private_files_nonce' => array( 'bad' ) );
	$before = get_post_meta( $id, '_tc_private_files', true );
	TC_Private_Files::save( $id, get_post( $id ) );
	$tc_private_expect( $before === get_post_meta( $id, '_tc_private_files', true ), 'Administrator saves also reject malformed nonces.' );
	$request = new WP_REST_Request( 'GET', '/wp/v2/tc_config/' . $id ); $request->set_param( 'context', 'edit' );
	$response = rest_do_request( $request );
	$tc_private_expect( 200 === $response->get_status() && ! str_contains( wp_json_encode( $response->get_data() ), 'stored_name' ) && ! isset( $response->get_data()['meta']['_tc_private_files'] ), 'Private records are absent from registered config REST metadata.' );
	$held = fopen( $tc_private_root . '/.tc-private.lock', 'c' ); flock( $held, LOCK_EX );
	$tc_private_expect( is_wp_error( $tc_private_call( 'mutate', $id, 'update', array( 'file_id' => $file_id, 'version' => 'race' ), null ) ), 'Concurrent writes fail with a retryable lock instead of losing records.' );
	flock( $held, LOCK_UN ); fclose( $held );
	$result = $tc_private_call( 'mutate', $id, 'delete', array( 'file_id' => $file_id, 'confirm' => '1' ), null );
	$tc_private_expect( true === $result && ! file_exists( $directory . '/' . $stored ) && array() === TC_Private_Files::files( $id ), 'Confirmed deletion removes both record and private bytes.' );
	$tc_private_expect( 2 === count( get_post_meta( $id, '_tc_private_file_audit', false ) ), 'Deletion appends rather than replacing the existing audit history.' );
	WP_CLI::success( sprintf( 'Private-file integration: %d assertions passed.', $tc_private_checks ) );
} finally {
	remove_filter( 'timonconfigs_private_storage_directory', $tc_private_root_filter );
	if ( $tc_private_scanner ) { remove_filter( 'timonconfigs_private_file_scan', $tc_private_scanner ); }
	foreach ( $tc_private_ids as $id ) { wp_delete_post( $id, true ); }
	wp_set_current_user( $tc_private_original_user );
	$_POST = $tc_private_original_post; $_FILES = $tc_private_original_files;
	if ( null === $tc_private_original_document_root ) { unset( $_SERVER['DOCUMENT_ROOT'] ); } else { $_SERVER['DOCUMENT_ROOT'] = $tc_private_original_document_root; }
	$tc_private_remove( $tc_private_directory );
}
