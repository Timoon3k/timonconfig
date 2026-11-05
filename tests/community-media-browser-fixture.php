<?php
/** Reversible LOCAL QA data; never represents real community feedback or a customer release. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://127.0.0.1:8080/' !== home_url( '/' ) ) { throw new RuntimeException( 'Disposable localhost WordPress is required.' ); }
$mode = getenv( 'TC_COMMUNITY_BROWSER_MODE' ) ?: 'setup';
$option = '_tc_community_browser_fixture'; $marker = '_tc_community_browser_fixture_token';
$saved = get_option( $option, false );
$restore = static function ( array $backup ) use ( $option, $marker ): void {
    foreach ( $backup['ids'] as $id ) {
        $post = get_post( $id ); if ( ! $post ) { continue; }
        if ( ! hash_equals( $backup['token'], (string) get_post_meta( $id, $marker, true ) ) || ! in_array( $post->post_type, array( 'tc_review', 'tc_config' ), true ) ) { throw new RuntimeException( 'Refusing to delete an unmarked fixture record.' ); }
        wp_delete_post( $id, true );
    }
    foreach ( $backup['statuses'] as $id => $entry ) {
        $post = get_post( $id ); if ( ! $post ) { continue; }
        if ( $post->post_type !== $entry['type'] || ! in_array( $post->post_status, array( 'draft', $entry['status'] ), true ) ) { throw new RuntimeException( 'A pre-existing local fixture record changed concurrently.' ); }
        wp_update_post( array( 'ID' => $id, 'post_status' => $entry['status'] ) );
    }
    if ( $backup['mods_exists'] ) { update_option( $backup['mods_option'], $backup['mods'] ); } else { delete_option( $backup['mods_option'] ); }
    delete_option( $option );
};
if ( 'restore' === $mode ) {
    if ( ! is_array( $saved ) || empty( $saved['token'] ) ) { throw new RuntimeException( 'No community browser fixture backup exists.' ); }
    $restore( $saved ); WP_CLI::line( 'TC_COMMUNITY_BROWSER:' . wp_json_encode( array( 'restored' => true, 'removed' => count( $saved['ids'] ) ) ) ); return;
}
if ( 'empty' === $mode ) {
    if ( ! is_array( $saved ) || empty( $saved['valid_review'] ) || ! hash_equals( $saved['token'], (string) get_post_meta( $saved['valid_review'], $marker, true ) ) ) { throw new RuntimeException( 'No valid marked review exists for the empty-state test.' ); }
    wp_update_post( array( 'ID' => $saved['valid_review'], 'post_status' => 'draft' ) );
    WP_CLI::line( 'TC_COMMUNITY_BROWSER:' . wp_json_encode( array( 'empty' => true ) ) ); return;
}
if ( 'setup' !== $mode || false !== $saved || ! class_exists( 'TC_Discord_Vouches' ) || ! class_exists( 'TC_Videos' ) ) { throw new RuntimeException( 'Restore any previous fixture and load the updated Core first.' ); }
$mods_option = 'theme_mods_' . get_option( 'stylesheet' );
$backup = array( 'token' => wp_generate_password( 32, false, false ), 'ids' => array(), 'statuses' => array(), 'mods_option' => $mods_option, 'mods_exists' => false !== get_option( $mods_option, false ), 'mods' => get_option( $mods_option, array() ) );
foreach ( get_posts( array( 'post_type' => array( 'tc_review', 'tc_video' ), 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $post ) { $backup['statuses'][ $post->ID ] = array( 'type' => $post->post_type, 'status' => $post->post_status ); }
if ( ! add_option( $option, $backup, '', false ) ) { throw new RuntimeException( 'Could not save fixture restore data.' ); }
try {
    foreach ( $backup['statuses'] as $id => $entry ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) ); }
    for ( $index = 1; $index <= 3; ++$index ) { foreach ( array( 'url', 'title', 'device', 'description', 'poster', 'poster_alt' ) as $field ) { remove_theme_mod( 'timonconfigs_video_' . $index . '_' . $field ); } }
    $create = static function ( string $type, string $title, string $content, array $meta = array() ) use ( &$backup, $option, $marker ): int {
        $id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content, 'post_excerpt' => 'LOCAL QA browser fixture only; not a customer release or genuine vouch.', 'menu_order' => -999, 'meta_input' => array_merge( $meta, array( $marker => $backup['token'] ) ) ) ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $backup['ids'][] = $id; update_option( $option, $backup ); return $id;
    };
    $config = TC_Discord_Vouches::config();
    $text = 'LOCAL QA — synthetic browser fixture, not genuine community feedback. Literal <img src="invalid" onerror="window.tcCommunityExecuted=1"> [gallery] [woocommerce_checkout] <script>window.tcCommunityExecuted=1</script> remains quoted text.';
    $content = str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), esc_html( $text ) );
    $reviews = array();
    foreach ( array( 'valid', 'hidden', 'edited', 'invalid' ) as $index => $kind ) {
        $message_id = (string) ( 899999999999999910 + $index );
        $meta = array( 'tc_review_demo' => false, 'tc_review_rating' => 0, '_tc_vouch_message_id' => $message_id, '_tc_vouch_channel_id' => $config['channel_id'], '_tc_vouch_guild_id' => $config['guild_id'], '_tc_vouch_source_url' => TC_Discord_Vouches::source_url( $message_id, $config ), '_tc_vouch_timestamp' => '2026-10-31T12:34:56+00:00' );
        $title = 'LOCAL QA ' . $kind . ' message'; $id = $create( 'tc_review', $title, $content, $meta );
        $post = get_post( $id ); update_post_meta( $id, '_tc_vouch_managed_hash', hash( 'sha256', $post->post_title . "\n" . $post->post_content ) );
        if ( 'hidden' === $kind ) { update_post_meta( $id, '_tc_vouch_hidden', true ); }
        if ( 'edited' === $kind ) { wp_update_post( array( 'ID' => $id, 'post_content' => $content . ' LOCAL QA later edit must be excluded.' ) ); }
        if ( 'invalid' === $kind ) { update_post_meta( $id, '_tc_vouch_guild_id', 'not-a-discord-id' ); }
        $reviews[ $kind ] = array( 'id' => $id, 'title' => $title, 'source' => $meta['_tc_vouch_source_url'] );
    }
    $create( 'tc_review', 'LOCAL QA demo must never render', 'LOCAL QA DEMO MUST NOT BE DISPLAYED', array( 'tc_review_demo' => true, 'tc_review_rating' => 5 ) );
    $id = $create( 'tc_config', 'LOCAL QA public release details', '<p>LOCAL QA public release description. This synthetic entry exists only to check that public release information remains available while the download flow still requires verified access.</p>', array( 'tc_tier' => 'monthly', 'tc_release_status' => 'available', 'tc_input_method' => 'controller', '_tc_values_content' => 'LOCAL_QA_PRIVATE_VALUES_MUST_NOT_RENDER', '_tc_values_visibility' => 'monthly', 'tc_config_version' => 'LOCAL-QA-ONLY' ) );
    foreach ( array( 'tc_game' => 'apex-legends', 'tc_device' => 'xim-matrix' ) as $taxonomy => $slug ) {
        $term = get_term_by( 'slug', $slug, $taxonomy ); if ( ! $term ) { throw new RuntimeException( 'Existing game/device term required.' ); }
        wp_set_object_terms( $id, array( $term->term_id ), $taxonomy );
    }
    $backup['valid_review'] = $reviews['valid']['id']; update_option( $option, $backup );
    WP_CLI::line( 'TC_COMMUNITY_BROWSER:' . wp_json_encode( array( 'fixture_active' => true, 'reviews' => $reviews, 'text' => $text, 'channel' => 'https://discord.com/channels/' . $config['guild_id'] . '/' . $config['channel_id'], 'videos' => TC_Videos::get_public_videos(), 'config' => array( 'id' => $id, 'title' => get_the_title( $id ), 'url' => get_permalink( $id ), 'cta' => TC_Catalog::download_cta( $id ) ) ) ) );
} catch ( Throwable $error ) { $restore( $backup ); throw $error; }
