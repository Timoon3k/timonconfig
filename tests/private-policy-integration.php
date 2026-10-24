<?php
/** Disposable localhost only: configurable format policy and guarded server adapter. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
$checks = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Private policy: ' . $message ); } ++$checks; };
$inspect = static fn( string $path, mixed $name ): mixed => ( new ReflectionMethod( TC_Private_Files::class, 'inspect' ) )->invoke( null, $path, $name );
$old = get_option( 'timonconfigs_private_formats', null );
$original_user = get_current_user_id();
$directory = sys_get_temp_dir() . '/tc-format-policy-' . wp_generate_uuid4();
mkdir( $directory, 0700 );
$storage = $directory . '/storage'; mkdir( $storage, 0700 );
$root_filter = static fn(): string => $storage;
add_filter( 'timonconfigs_private_storage_directory', $root_filter );
$text = $directory . '/text'; file_put_contents( $text, "[config]\nsensitivity=5\n" ); chmod( $text, 0600 );
$binary = $directory . '/binary'; file_put_contents( $binary, "TCBIN\0\x01\xff" ); chmod( $binary, 0600 );
$id = 0;
try {
    delete_option( 'timonconfigs_private_formats' ); wp_set_current_user( 0 );
    $assert( array( 'znbk', 'gpc', 'ximmr', 'json', 'txt', 'cfg', 'ini' ) === TC_Private_Files::extensions(), 'Reviewed defaults include CFG/INI and preserve legacy formats.' );
    foreach ( array( 'cfg', 'ini' ) as $extension ) {
        $assert( is_array( $inspect( $text, 'support.' . $extension ) ), 'Text support data accepted: ' . $extension );
        $assert( is_wp_error( $inspect( $binary, 'support.' . $extension ) ), 'Binary data cannot disguise itself as text support data: ' . $extension );
    }
    foreach ( array( 'php', 'js', 'html', 'svg', 'zip', 'exe', 'sh' ) as $extension ) {
        $assert( is_wp_error( $inspect( $text, 'payload.' . $extension ) ), 'Active/executable/archive format unavailable: ' . $extension );
    }
    foreach ( array( 'znbk', 'ximmr' ) as $extension ) { $assert( 'znbk' === $extension ? is_array( $inspect( $text, 'backup.' . $extension ) ) : is_wp_error( $inspect( $text, 'backup.' . $extension ) ), 'Automatic ZNBK / mandatory XIMMR policy: ' . $extension ); }
    $assert( TC_Private_Files::extensions() === TC_Private_Files::sanitize_formats( array( 'txt' ) ), 'Anonymous caller cannot alter enabled formats.' );
    ob_start(); TC_Private_Files::formats_page(); $html = ob_get_clean();
    $assert( '' === $html, 'Anonymous caller sees no settings form.' );
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    wp_set_current_user( (int) $admins[0] );
    $approved = TC_Private_Files::sanitize_formats( array( 'txt', 'cfg', 'cfg', 'js', array( 'php' ), 7 ) );
    $assert( array( 'txt', 'cfg' ) === $approved, 'Administrator can narrow the reviewed set; duplicates and unknown formats rejected.' );
    update_option( 'timonconfigs_private_formats', $approved, false );
    $assert( '.txt,.cfg' === TC_Private_Files::accept(), 'File chooser matches server-side policy.' );
    $assert( is_wp_error( $inspect( $text, 'support.ini' ) ) && is_array( $inspect( $text, 'support.cfg' ) ), 'Disabled formats rejected while enabled safe text remains accepted.' );
    // Even a forged option cannot extend the hard-coded safe universe.
    update_option( 'timonconfigs_private_formats', array( 'php', 'html', 'zip', 'cfg' ), false );
    $assert( array( 'cfg' ) === TC_Private_Files::extensions(), 'Raw option tampering cannot permit executable extensions.' );
    $id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'draft', 'post_title' => 'LOCAL PRIVATE POLICY FIXTURE' ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    mkdir( $storage . '/' . $id, 0700 ); $file_id = wp_generate_uuid4();
    $path = $storage . '/' . $id . '/' . $file_id . '.cfg'; copy( $text, $path ); chmod( $path, 0600 );
    update_post_meta( $id, '_tc_private_files', array( array( 'id' => $file_id, 'stored_name' => $file_id . '.cfg', 'name' => 'support.cfg', 'size' => filesize( $text ), 'checksum' => hash_file( 'sha256', $text ) ) ) );
    $assert( $path === TC_Private_Files::resolve( $id, $file_id ), 'New format resolves only through existing private storage and integrity checks.' );
    update_option( 'timonconfigs_private_formats', array( 'txt' ), false );
    $assert( is_wp_error( TC_Private_Files::resolve( $id, $file_id ) ) && is_file( $path ), 'Policy revocation denies existing delivery without deleting bytes.' );
    $assert( array() === TC_Private_Files::files( $id ), 'Disabled file format is absent from member listing.' );
    wp_set_current_user( 0 );
    $assert( is_wp_error( TC_Private_Files::manage( $id, 'update', array( 'file_id' => $file_id ) ) ), 'Public server adapter cannot bypass anonymous capability checks.' );
    $assert( ! ( new ReflectionMethod( TC_Private_Files::class, 'authorise' ) )->invoke( null, $id ), 'No frontend authorization exists outside verified context.' );
    wp_set_current_user( (int) $admins[0] );
    $assert( true === TC_Private_Files::manage( $id, 'update', array( 'file_id' => $file_id, 'version' => 'reviewed' ) ), 'Administrator fallback retains existing safe mutation path.' );
    $assert( 1 === count( get_post_meta( $id, '_tc_private_file_audit', false ) ), 'Server adapter preserves audit trail.' );
    update_option( 'timonconfigs_private_formats', array(), false );
    $assert( array() === TC_Private_Files::extensions() && '' === TC_Private_Files::accept() && is_wp_error( $inspect( $text, 'support.txt' ) ), 'Administrator can disable all new uploads and deliveries.' );
    WP_CLI::success( "Private format policy: $checks assertions passed." );
} finally {
    if ( $id && ! is_wp_error( $id ) ) { wp_delete_post( $id, true ); }
    foreach ( glob( $storage . '/*/*' ) ?: array() as $file ) { unlink( $file ); }
    foreach ( glob( $storage . '/*', GLOB_ONLYDIR ) ?: array() as $subdir ) { rmdir( $subdir ); }
    foreach ( glob( $storage . '/.*' ) ?: array() as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
    unlink( $text ); unlink( $binary ); rmdir( $storage ); rmdir( $directory );
    remove_filter( 'timonconfigs_private_storage_directory', $root_filter );
    null === $old ? delete_option( 'timonconfigs_private_formats' ) : update_option( 'timonconfigs_private_formats', $old, false );
    wp_set_current_user( $original_user );
}
