<?php
/** Reversible LOCAL QA fixture for the PDF's four Apex SEO targets; no production data. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://127.0.0.1:8080/' !== home_url( '/' ) ) { throw new RuntimeException( 'Disposable localhost WordPress is required.' ); }

$mode = getenv( 'TC_APEX_SEO_BROWSER_MODE' ) ?: 'setup';
$option = '_tc_apex_seo_browser_fixture'; $marker = '_tc_apex_seo_browser_fixture_token';
$saved = get_option( $option, false );
$explicit = array( 'active_plugins', 'rewrite_rules', 'show_on_front', 'page_on_front', 'page_for_posts', 'timonconfigs_page_seo_state', 'timonconfigs_page_seo_queue', '_tc_page_seo_lock', 'tc_rank_math_archive_backup', 'tc_rank_math_archive_managed', 'timonconfigs_collections_version' );
$provider_option = static fn( string $name ): bool => str_starts_with( $name, 'rank-math' ) || str_starts_with( $name, 'rank_math' ) || str_starts_with( $name, '_transient_rank_math' ) || str_starts_with( $name, '_transient_timeout_rank_math' );
$term_keys = array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', '_tc_rank_math_page_backup', '_tc_rank_math_page_managed' );
$no_http = static fn(): WP_Error => new WP_Error( 'tc_apex_seo_qa_http', 'External traffic is disabled in the local Apex SEO fixture.' );
add_filter( 'pre_http_request', $no_http, 1 );

$restore = static function ( array $backup ) use ( $option, $marker, $provider_option ): void {
    global $wpdb;
    wp_set_current_user( 0 );
    foreach ( array_reverse( $backup['ids'] ) as $id ) {
        $post = get_post( $id ); if ( ! $post ) { continue; }
        if ( ! hash_equals( $backup['token'], (string) get_post_meta( $id, $marker, true ) ) || ! in_array( $post->post_type, array( 'page', 'tc_config' ), true ) ) { throw new RuntimeException( 'Refusing to delete an unmarked Apex SEO fixture record.' ); }
        wp_delete_post( $id, true );
    }
    foreach ( $backup['terms'] as $id => $fields ) {
        foreach ( $fields as $key => $values ) {
            delete_term_meta( (int) $id, $key );
            foreach ( $values as $value ) { add_term_meta( (int) $id, $key, wp_slash( $value ) ); }
            if ( get_term_meta( (int) $id, $key, false ) !== $values ) { throw new RuntimeException( 'An Apex term metadata field was not restored: ' . $key ); }
        }
    }
    foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" ) as $name ) { if ( $provider_option( $name ) && ! isset( $backup['options'][ $name ] ) ) { delete_option( $name ); } }
    foreach ( $backup['options'] as $name => $entry ) { $entry['exists'] ? update_option( $name, $entry['value'], $entry['autoload'] ) : delete_option( $name ); }
    // Restoring Reading can regenerate rules with the provider loaded in this request.
    // Restore their exact snapshot last, after the Home assignment and all other options.
    $rules = $backup['options']['rewrite_rules'];
    $rules['exists'] ? update_option( 'rewrite_rules', $rules['value'], $rules['autoload'] ) : delete_option( 'rewrite_rules' );
    foreach ( $backup['options'] as $name => $entry ) {
        // Reading sanitizers can return an integer from the request cache while
        // the original database snapshot contains its string representation.
        $stored = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name ), ARRAY_A );
        if ( $entry['exists'] ? ! $stored || maybe_unserialize( $stored['option_value'] ) !== $entry['value'] : null !== $stored ) { throw new RuntimeException( 'An Apex SEO fixture option was not restored: ' . $name ); }
    }
    foreach ( $backup['ids'] as $id ) { if ( get_post( $id ) ) { throw new RuntimeException( 'An Apex SEO fixture record remains.' ); } }
    delete_option( $option );
};

if ( 'restore' === $mode ) {
    if ( ! is_array( $saved ) || empty( $saved['token'] ) ) { throw new RuntimeException( 'No Apex SEO fixture restore data exists.' ); }
    try { $restore( $saved ); WP_CLI::line( 'TC_APEX_SEO_BROWSER:' . wp_json_encode( array( 'restored' => true, 'removed' => count( $saved['ids'] ), 'term_fields_restored' => count( $saved['terms'] ) ) ) ); }
    finally { remove_filter( 'pre_http_request', $no_http, 1 ); }
    return;
}
if ( 'setup' !== $mode || false !== $saved || ! class_exists( 'TC_Rank_Math_Pages' ) || ! is_callable( array( 'TC_Collections', 'upgrade_intro' ) ) ) { throw new RuntimeException( 'Restore previous fixtures and load the complete Apex SEO release first.' ); }
if ( ! is_file( WP_PLUGIN_DIR . '/seo-by-rank-math/rank-math.php' ) ) { throw new RuntimeException( 'The local Rank Math plugin must already be installed.' ); }

global $wpdb;
$explicit[] = $wpdb->prefix . 'user_roles';
$backup = array( 'token' => wp_generate_password( 32, false, false ), 'ids' => array(), 'options' => array(), 'terms' => array() );
foreach ( $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A ) as $row ) {
    $name = $row['option_name'];
    if ( $provider_option( $name ) || in_array( $name, $explicit, true ) ) { $backup['options'][ $name ] = array( 'exists' => true, 'value' => maybe_unserialize( $row['option_value'] ), 'autoload' => $row['autoload'] ); }
}
foreach ( $explicit as $name ) { $backup['options'][ $name ] = $backup['options'][ $name ] ?? array( 'exists' => false, 'value' => false, 'autoload' => false ); }
$game = get_term_by( 'slug', 'apex-legends', 'tc_game' );
$devices = array( get_term_by( 'slug', 'xim-matrix', 'tc_device' ), get_term_by( 'slug', 'cronus-zen', 'tc_device' ) );
if ( ! $game instanceof WP_Term || ! $devices[0] instanceof WP_Term || ! $devices[1] instanceof WP_Term ) { throw new RuntimeException( 'Existing local Apex Legends, XIM Matrix and Cronus Zen terms are required.' ); }
foreach ( $term_keys as $key ) { $backup['terms'][ $game->term_id ][ $key ] = get_term_meta( $game->term_id, $key, false ); }
if ( ! add_option( $option, $backup, '', false ) ) { throw new RuntimeException( 'Could not save the Apex SEO fixture restore data.' ); }

try {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    if ( ! $admins ) { throw new RuntimeException( 'An existing local administrator is required.' ); }
    wp_set_current_user( (int) $admins[0] );
    $create = static function ( string $type, string $title, string $content, array $meta = array() ) use ( &$backup, $option, $marker ): int {
        $id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'tc-apex-seo-' . wp_generate_uuid4(), 'post_content' => $content, 'post_excerpt' => 'LOCAL QA synthetic SEO test only; not a real community release.', 'meta_input' => array_merge( $meta, array( $marker => $backup['token'] ) ) ) ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $backup['ids'][] = (int) $id; update_option( $option, $backup, false ); return (int) $id;
    };
    $expect_success = static function ( array|WP_Error $result ): void { if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); } };
    $native = static fn( int $id ): array => array( 'title' => get_post_meta( $id, 'rank_math_title', true ), 'description' => get_post_meta( $id, 'rank_math_description', true ), 'focus_keyword' => get_post_meta( $id, 'rank_math_focus_keyword', true ) );
    $home = $create( 'page', 'LOCAL QA Apex SEO Home', '<!-- wp:timonconfigs/section {"section":"hero"} /-->' );
    update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $home ); update_option( 'page_for_posts', 0 );
    $expect_success( TC_Rank_Math_Pages::apply_post( $home ) );

    $config_ids = array();
    for ( $number = 1; $number <= 13; ++$number ) {
        $id = $create( 'tc_config', 'LOCAL QA Apex SEO public release ' . $number, '<p>LOCAL QA public release description only; no real device compatibility is claimed by this synthetic fixture.</p>', array( 'tc_tier' => 'monthly', 'tc_release_status' => 'available', 'tc_input_method' => 'controller', '_tc_values_content' => 'LOCAL_QA_APEX_SEO_PRIVATE_VALUES_MUST_NOT_RENDER', '_tc_values_visibility' => 'monthly' ) );
        wp_set_object_terms( $id, array( $game->term_id ), 'tc_game' );
        wp_set_object_terms( $id, array( $devices[0]->term_id, $devices[1]->term_id ), 'tc_device' );
        $config_ids[] = $id;
    }
    $collections = array();
    foreach ( array( 'xim-matrix-apex-legends', 'cronus-zen-apex-legends' ) as $key ) {
        $definition = TC_Collections::definitions()[ $key ]; $old_content = TC_Collections::default_content( $definition, true );
        $id = $create( 'page', $definition['title'], $old_content, array( '_tc_collection' => $key ) );
        $upgrade = TC_Collections::upgrade_intro( $id ); $expect_success( $upgrade );
        if ( empty( $upgrade['changed'] ) || TC_Collections::default_content( $definition ) !== get_post_field( 'post_content', $id ) || $old_content === get_post_field( 'post_content', $id ) || $old_content !== ( get_post_meta( $id, '_tc_apex_intro_backup', true )['content'] ?? null ) ) { throw new RuntimeException( 'The native fixture did not upgrade and back up its legacy collection intro.' ); }
        $expect_success( TC_Rank_Math_Pages::apply_post( $id ) );
        $collections[] = array( 'name' => $key, 'id' => $id, 'url' => get_permalink( $id ), 'h1' => $definition['title'], 'native' => $native( $id ), 'profile' => TC_Rank_Math_Pages::profile( get_post( $id ) ), 'intro' => get_post_field( 'post_content', $id ), 'device' => $definition['device'] );
    }
    // Update only the captured native fields for the real Apex term; restore their exact presence/value lists.
    delete_term_meta( $game->term_id, '_tc_rank_math_page_backup' ); delete_term_meta( $game->term_id, '_tc_rank_math_page_managed' );
    $expect_success( TC_Rank_Math_Pages::apply_term( $game->term_id ) );
    $game_native = array(); foreach ( array( 'title', 'description', 'focus_keyword' ) as $field ) { $game_native[ $field ] = get_term_meta( $game->term_id, 'rank_math_' . $field, true ); }
    $game_url = get_term_link( $game ); if ( is_wp_error( $game_url ) ) { throw new RuntimeException( $game_url->get_error_message() ); }

    $empty = null;
    foreach ( TC_Collections::definitions() as $key => $definition ) {
        if ( ! $definition['game'] || 'apex-legends' === $definition['game'] || TC_Collections::has_configs( $definition ) ) { continue; }
        $id = $create( 'page', $definition['title'], TC_Collections::default_content( $definition ), array( '_tc_collection' => $key ) );
        $expect_success( TC_Rank_Math_Pages::apply_post( $id ) );
        $empty = array( 'id' => $id, 'name' => 'empty-collection', 'url' => get_permalink( $id ), 'h1' => $definition['title'], 'native' => $native( $id ), 'profile' => TC_Rank_Math_Pages::profile( get_post( $id ) ) ); break;
    }
    update_option( 'rank_math_registration_skip', 1, false );
    $active = get_option( 'active_plugins', array() );
    if ( ! in_array( 'seo-by-rank-math/rank-math.php', $active, true ) ) { $active[] = 'seo-by-rank-math/rank-math.php'; update_option( 'active_plugins', $active ); }
    wp_set_current_user( 0 );
    WP_CLI::line( 'TC_APEX_SEO_BROWSER:' . wp_json_encode( array( 'fixture_active' => true, 'home' => array( 'name' => 'home', 'id' => $home, 'url' => home_url( '/' ), 'native' => $native( $home ), 'profile' => TC_Rank_Math_Pages::profile( get_post( $home ) ) ), 'game' => array( 'name' => 'apex-game', 'url' => $game_url, 'h1' => 'Apex Legends Configs', 'native' => $game_native, 'profile' => TC_Rank_Math_Pages::profile( $game ) ), 'collections' => $collections, 'empty' => $empty, 'release_ids' => $config_ids ) ) );
} catch ( Throwable $error ) { $restore( $backup ); throw $error; }
finally { remove_filter( 'pre_http_request', $no_http, 1 ); }
