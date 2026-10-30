<?php
/** Real Rank Math importer on disposable localhost; setup/restore supports HTTP browser QA. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
    throw new RuntimeException( 'Disposable localhost WordPress required.' );
}
$mode = getenv( 'TC_RANK_MATH_FIXTURE' ) ?: 'test';
if ( ! in_array( $mode, array( 'test', 'setup', 'restore' ), true ) ) { throw new RuntimeException( 'Unknown fixture mode.' ); }
$backup_key = '_tc_rank_math_seo_fixture_backup';
$restore = static function ( array $backup ): void {
    foreach ( $backup as $key => $value ) {
        null === $value ? delete_option( $key ) : update_option( $key, $value, false );
    }
    delete_option( '_tc_rank_math_seo_fixture_backup' );
};
if ( 'restore' === $mode ) {
    $backup = get_option( $backup_key );
    if ( ! is_array( $backup ) ) { throw new RuntimeException( 'No fixture backup exists.' ); }
    $restore( $backup );
    WP_CLI::success( 'Restored original local Rank Math settings.' );
    return;
}
if ( ! defined( 'RANK_MATH_VERSION' ) ) { throw new RuntimeException( 'Activate the local test Rank Math plugin first.' ); }
if ( false !== get_option( $backup_key, false ) ) { throw new RuntimeException( 'Restore the existing fixture before another test.' ); }
$path = getenv( 'TC_RANK_MATH_IMPORT_JSON' ) ?: '/seo/timonconfigs-rank-math-seo-only-2026-10-30.json';
$data = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
$keys = array_keys( $data ); sort( $keys );
if ( $keys !== array( 'general', 'sitemap', 'titles' ) ) { throw new RuntimeException( 'Only complete SEO sections may enter this fixture.' ); }
$backup = array();
foreach ( array( 'rank-math-options-general', 'rank-math-options-titles', 'rank-math-options-sitemap', 'rank_math_backups', 'rank_math_registration_skip' ) as $key ) {
    $backup[ $key ] = get_option( $key, null );
}
global $wpdb;
$roles_before = get_option( $wpdb->prefix . 'user_roles' );
$modules_before = get_option( 'rank_math_modules' );
$network_calls = 0; $checks = 0; $keep = false;
$no_http = static function () use ( &$network_calls ): WP_Error { ++$network_calls; return new WP_Error( 'tc_seo_fixture_http', 'No external traffic in SEO fixture.' ); };
$assert = static function ( bool $value, string $message ) use ( &$checks ): void {
    if ( ! $value ) { throw new RuntimeException( $message ); } ++$checks;
};
add_filter( 'pre_http_request', $no_http, 1, 3 );
try {
    if ( ! class_exists( '\RankMath\Status\Import_Export_Settings' ) ) {
        require_once WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/status/class-import-export-settings.php';
    }
    if ( ! class_exists( '\RankMath\Status\Backup' ) ) {
        require_once WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/status/class-backup.php';
    }
    $assert( true === \RankMath\Status\Import_Export_Settings::do_import_data( $data, true ), 'Native Rank Math importer accepts the SEO-only file.' );
    foreach ( array( 'general', 'titles', 'sitemap' ) as $section ) {
        $assert( get_option( 'rank-math-options-' . $section ) === $data[ $section ], 'Native importer preserves complete ' . $section . ' settings.' );
    }
    $assert( get_option( 'rank_math_modules' ) === $modules_before, 'SEO-only import leaves active modules unchanged.' );
    $assert( get_option( $wpdb->prefix . 'user_roles' ) === $roles_before, 'SEO-only import leaves WordPress role capabilities unchanged.' );
    $assert( in_array( 'index', $data['titles']['robots_global'], true ), 'Global public indexing is preserved.' );
    $assert( 'on' === $data['sitemap']['pt_page_sitemap'], 'Page XML sitemap remains enabled.' );
    $assert( array() === $data['titles']['opening_hours'], 'Unconfirmed opening hours are not published.' );
    $assert( 0 === $network_calls, 'Importer did not contact an external provider.' );
    if ( 'setup' === $mode ) {
        $assert( add_option( $backup_key, $backup, '', false ), 'Local HTTP fixture has a recoverable private backup.' );
        // The free plugin's standard 'Skip account connection' onboarding choice.
        // No account, API credential, premium feature or provider validation is fabricated.
        update_option( 'rank_math_registration_skip', 1, false );
        $assert( (bool) get_option( 'rank_math_registration_skip' ), 'Local fixture completes the supported optional-account onboarding step.' );
        $keep = true;
    }
    WP_CLI::line( wp_json_encode( array( 'rank_math_version' => RANK_MATH_VERSION, 'assertions' => $checks, 'mode' => $mode, 'home' => home_url( '/' ), 'http_fixture_active' => $keep ) ) );
    WP_CLI::success( 'Native Rank Math SEO-only import passed; no purchases, users or public-site changes.' );
} finally {
    if ( ! $keep ) { $restore( $backup ); }
    remove_filter( 'pre_http_request', $no_http, 1 );
}
