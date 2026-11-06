<?php
/** Reversible LOCAL QA native Rank Math HTTP fixture; no purchases or external credentials. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://127.0.0.1:8080/' !== home_url( '/' ) ) { throw new RuntimeException( 'Disposable localhost WordPress is required.' ); }

$mode = getenv( 'TC_PAGE_SEO_BROWSER_MODE' ) ?: 'setup';
$option = '_tc_page_seo_browser_fixture';
$marker = '_tc_page_seo_browser_fixture_token';
$saved = get_option( $option, false );
$explicit_options = array( 'active_plugins', 'rewrite_rules', 'timonconfigs_page_seo_state', 'timonconfigs_page_seo_queue', '_tc_page_seo_lock', 'tc_rank_math_archive_backup', 'tc_rank_math_archive_managed' );
$provider_option = static fn( string $name ): bool => str_starts_with( $name, 'rank-math' ) || str_starts_with( $name, 'rank_math' ) || str_starts_with( $name, '_transient_rank_math' ) || str_starts_with( $name, '_transient_timeout_rank_math' );
$restore = static function ( array $backup ) use ( $option, $marker, $provider_option ): void {
    global $wpdb;
    wp_set_current_user( 0 );
    foreach ( $backup['ids'] as $id ) {
        $post = get_post( $id );
        if ( ! $post ) { continue; }
        if ( ! hash_equals( $backup['token'], (string) get_post_meta( $id, $marker, true ) ) || ! in_array( $post->post_type, array( 'page', 'tc_config' ), true ) ) { throw new RuntimeException( 'Refusing to delete an unmarked native SEO fixture record.' ); }
        wp_delete_post( $id, true );
    }
    foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" ) as $name ) {
        if ( $provider_option( $name ) && ! isset( $backup['options'][ $name ] ) ) { delete_option( $name ); }
    }
    foreach ( $backup['options'] as $name => $entry ) {
        if ( $entry['exists'] ) { update_option( $name, $entry['value'], $entry['autoload'] ); }
        else { delete_option( $name ); }
    }
    foreach ( $backup['options'] as $name => $entry ) {
        if ( $entry['exists'] && get_option( $name ) !== $entry['value'] ) { throw new RuntimeException( 'Native SEO fixture option was not restored: ' . $name ); }
        if ( ! $entry['exists'] && false !== get_option( $name, false ) ) { throw new RuntimeException( 'New native SEO fixture option remains: ' . $name ); }
    }
    delete_option( $option );
};

if ( 'restore' === $mode ) {
    if ( ! is_array( $saved ) || empty( $saved['token'] ) ) { throw new RuntimeException( 'No native SEO fixture restore data exists.' ); }
    $restore( $saved );
    WP_CLI::line( 'TC_PAGE_SEO_BROWSER:' . wp_json_encode( array( 'restored' => true, 'removed' => count( $saved['ids'] ) ) ) );
    return;
}
if ( 'setup' !== $mode || false !== $saved || ! class_exists( 'TC_Rank_Math_Pages' ) || ! class_exists( 'TC_Collections' ) ) { throw new RuntimeException( 'Restore previous fixtures and load the page SEO module first.' ); }
if ( ! is_file( WP_PLUGIN_DIR . '/seo-by-rank-math/rank-math.php' ) ) { throw new RuntimeException( 'Install the local Rank Math plugin before this test.' ); }

global $wpdb;
$explicit_options[] = $wpdb->prefix . 'user_roles';
$backup = array( 'token' => wp_generate_password( 32, false, false ), 'ids' => array(), 'options' => array() );
foreach ( $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A ) as $row ) {
    $name = $row['option_name'];
    if ( $provider_option( $name ) || in_array( $name, $explicit_options, true ) ) {
        $backup['options'][ $name ] = array( 'exists' => true, 'value' => maybe_unserialize( $row['option_value'] ), 'autoload' => $row['autoload'] );
    }
}
foreach ( $explicit_options as $name ) { $backup['options'][ $name ] = $backup['options'][ $name ] ?? array( 'exists' => false, 'value' => false, 'autoload' => false ); }
if ( ! add_option( $option, $backup, '', false ) ) { throw new RuntimeException( 'Could not save native SEO fixture restore data.' ); }

try {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    if ( ! $admins ) { throw new RuntimeException( 'An existing local administrator is required.' ); }
    wp_set_current_user( (int) $admins[0] );
    $create = static function ( string $type, string $title, array $meta = array() ) use ( &$backup, $option, $marker ): int {
        $id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'tc-native-seo-' . wp_generate_uuid4(), 'post_content' => '<p>LOCAL QA public metadata test only. This synthetic record does not represent an available customer release.</p>', 'post_excerpt' => 'LOCAL QA public metadata test only.', 'meta_input' => array_merge( $meta, array( $marker => $backup['token'] ) ) ) ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $backup['ids'][] = (int) $id; update_option( $option, $backup, false ); return (int) $id;
    };
    $game = get_term_by( 'slug', 'apex-legends', 'tc_game' );
    $device = get_term_by( 'slug', 'xim-matrix', 'tc_device' );
    if ( ! $game instanceof WP_Term || ! $device instanceof WP_Term ) { throw new RuntimeException( 'Existing local Apex Legends and XIM Matrix terms are required.' ); }
    $config_ids = array();
    for ( $number = 1; $number <= 13; ++$number ) {
        $id = $create( 'tc_config', 'LOCAL QA native SEO release ' . $number, array( 'tc_tier' => 'monthly', 'tc_release_status' => 'available', 'tc_input_method' => 'controller', '_tc_values_content' => 'LOCAL_QA_NATIVE_SEO_PRIVATE_VALUES_MUST_NOT_RENDER', '_tc_values_visibility' => 'monthly' ) );
        wp_set_object_terms( $id, array( $game->term_id ), 'tc_game' );
        wp_set_object_terms( $id, array( $device->term_id ), 'tc_device' );
        $config_ids[] = $id;
    }
    $page_id = $create( 'page', 'LOCAL QA native SEO device and game collection', array( '_tc_collection' => 'xim-matrix-apex-legends' ) );
    foreach ( array( $page_id, $config_ids[0] ) as $id ) {
        $result = TC_Rank_Math_Pages::apply_post( $id );
        if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
    }
    delete_option( 'tc_rank_math_archive_backup' ); delete_option( 'tc_rank_math_archive_managed' );
    $result = TC_Rank_Math_Pages::apply_archive();
    if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }

    // Load the already-installed provider on subsequent local requests without running its activation installer.
    update_option( 'rank_math_registration_skip', 1, false );
    $active = get_option( 'active_plugins', array() );
    if ( ! in_array( 'seo-by-rank-math/rank-math.php', $active, true ) ) { $active[] = 'seo-by-rank-math/rank-math.php'; update_option( 'active_plugins', $active ); }
    $native = static fn( int $id ): array => array( 'title' => get_post_meta( $id, 'rank_math_title', true ), 'description' => get_post_meta( $id, 'rank_math_description', true ), 'focus_keyword' => get_post_meta( $id, 'rank_math_focus_keyword', true ) );
    wp_set_current_user( 0 );
    WP_CLI::line( 'TC_PAGE_SEO_BROWSER:' . wp_json_encode( array( 'fixture_active' => true, 'collection' => array( 'id' => $page_id, 'url' => get_permalink( $page_id ), 'native' => $native( $page_id ), 'profile' => TC_Rank_Math_Pages::profile( get_post( $page_id ) ) ), 'release' => array( 'id' => $config_ids[0], 'url' => get_permalink( $config_ids[0] ), 'native' => $native( $config_ids[0] ), 'profile' => TC_Rank_Math_Pages::profile( get_post( $config_ids[0] ) ) ), 'archive' => array( 'url' => get_post_type_archive_link( 'tc_config' ), 'profile' => TC_Rank_Math_Pages::catalogue()['archive']['tc_config'] ) ) ) );
} catch ( Throwable $error ) { $restore( $backup ); throw $error; }
