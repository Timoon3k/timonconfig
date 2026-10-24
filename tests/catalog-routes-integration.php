<?php
/** Disposable local cache migration; restore generated routes before leaving. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
$checks = 0; $version = get_option( 'timonconfigs_catalog_routes_version', null );
$before = array( get_option( 'home' ), get_option( 'page_on_front' ), get_option( 'timonconfigs_checkout_urls' ), get_option( 'timonconfigs_discord' ) );
$assert = static function ( bool $ok, string $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
try {
    delete_option( 'timonconfigs_catalog_routes_version' );
    update_option( 'rewrite_rules', array( 'stale-local/?$' => 'index.php?stale_local=1' ) );
    TC_Catalog::refresh_routes();
    $rules = get_option( 'rewrite_rules' );
    $assert( isset( $rules['configs/?$'] ) && str_contains( $rules['configs/?$'], 'post_type=tc_config' ), 'Upgrade rebuilds the missing public Configs archive route.' );
    $assert( ! isset( $rules['stale-local/?$'] ), 'Stale route cache is regenerated.' );
    $assert( '2026-10-24-archive-v1' === get_option( 'timonconfigs_catalog_routes_version' ), 'Route upgrade is recorded.' );
    $updates = 0; $spy = static function ( $value ) use ( &$updates ) { ++$updates; return $value; };
    add_filter( 'pre_update_option_rewrite_rules', $spy );
    TC_Catalog::refresh_routes(); remove_filter( 'pre_update_option_rewrite_rules', $spy );
    $assert( 0 === $updates, 'Normal requests do not repeatedly flush routes.' );
    $assert( $before === array( get_option( 'home' ), get_option( 'page_on_front' ), get_option( 'timonconfigs_checkout_urls' ), get_option( 'timonconfigs_discord' ) ), 'Route migration leaves URLs, authored Home and integration settings intact.' );
    WP_CLI::success( "Catalog route migration: $checks assertions passed." );
} finally {
    null === $version ? delete_option( 'timonconfigs_catalog_routes_version' ) : update_option( 'timonconfigs_catalog_routes_version', $version, false );
    flush_rewrite_rules( false );
}
