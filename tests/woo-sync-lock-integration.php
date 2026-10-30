<?php
/** Exercise synchronization lease SQL against real disposable WordPress/MariaDB. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
    throw new RuntimeException( 'Disposable localhost WordPress required.' );
}
$checks = 0;
$assert = static function ( bool $value, string $message ) use ( &$checks ): void {
    if ( ! $value ) { throw new RuntimeException( $message ); }
    ++$checks;
};
$uid = random_int( 900000000, 999999999 );
$key = '_tc_woo_sync_lock_' . $uid;
$first = ( time() + 300 ) . ':' . bin2hex( random_bytes( 16 ) );
$second = ( time() + 300 ) . ':' . bin2hex( random_bytes( 16 ) );
$expired = ( time() - 1 ) . ':' . bin2hex( random_bytes( 16 ) );
$read = new ReflectionMethod( TC_Woo_Bridge::class, 'sync_lock_value' );
$owns = new ReflectionMethod( TC_Woo_Bridge::class, 'owns_sync' );
$release = new ReflectionMethod( TC_Woo_Bridge::class, 'release_sync' );
$change = new ReflectionMethod( TC_Woo_Bridge::class, 'role_change' );
$no_network = static function (): never { throw new RuntimeException( 'Unexpected external request during lease test.' ); };
if ( false !== get_option( $key, false ) ) { throw new RuntimeException( 'Fixture lease collision.' ); }
add_filter( 'pre_http_request', $no_network, 1, 3 );
try {
    $assert( add_option( $key, $first, '', false ), 'First worker claims the atomic option.' );
    $assert( ! add_option( $key, $second, '', false ), 'A concurrent worker cannot claim the same option.' );
    $assert( $read->invoke( null, $uid ) === $first && $owns->invoke( null, $uid, $first ), 'Raw SQL sees the current owner.' );
    update_option( $key, $second, false );
    $release->invoke( null, $uid, $first );
    $assert( $read->invoke( null, $uid ) === $second && ! $owns->invoke( null, $uid, $first ), 'Old worker cannot release a replacement owner.' );
    $release->invoke( null, $uid, $second );
    $assert( false === get_option( $key, false ), 'Owner release removes the option and its cached value.' );
    add_option( $key, $expired, '', false );
    $assert( ! $owns->invoke( null, $uid, $expired ), 'Expired lease cannot authorize role writes.' );
    $blocked = $change->invoke( null, $uid, $expired, 'PUT', '/must-not-be-requested' );
    $assert( is_wp_error( $blocked ) && 'sync_lease_lost' === $blocked->get_error_code(), 'Expired worker is rejected before external HTTP.' );
    $release->invoke( null, $uid, $expired );
    $assert( '' === $read->invoke( null, $uid ), 'Expired option can be safely cleaned up.' );
    WP_CLI::success( "Woo sync lease: $checks assertions; real WordPress SQL, no users, payments or roles modified." );
} finally {
    foreach ( array( $first, $second, $expired ) as $lease ) { $release->invoke( null, $uid, $lease ); }
    remove_filter( 'pre_http_request', $no_network, 1 );
}
