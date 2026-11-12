<?php
/** Reversible LOCAL QA private-download fixtures. Never creates real customers or licensed releases. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://127.0.0.1:8080/' !== home_url( '/' ) ) { throw new RuntimeException( 'Disposable localhost WordPress is required.' ); }
$option = '_tc_download_delivery_browser_fixture';
$marker = '_tc_download_delivery_browser_tag';
$mode = getenv( 'TC_DOWNLOAD_DELIVERY_MODE' ) ?: 'setup';
$saved = get_option( $option, false );
$restore = static function ( array $backup ) use ( $option, $marker ): array {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_set_current_user( 0 );
    $root = TC_Private_Files::root();
    if ( is_wp_error( $root ) ) { throw new RuntimeException( 'Cannot verify the private fixture storage root.' ); }
    foreach ( $backup['posts'] as $id ) {
        $post = get_post( $id );
        if ( ! $post ) { continue; }
        if ( 'tc_config' !== $post->post_type || $backup['tag'] !== get_post_meta( $id, $marker, true ) ) { throw new RuntimeException( 'Refusing to remove an unmarked release.' ); }
        $directory = $root . '/' . $id;
        if ( is_dir( $directory ) ) {
            if ( is_link( $directory ) || realpath( $directory ) !== $directory ) { throw new RuntimeException( 'Unsafe fixture directory.' ); }
            foreach ( $backup['paths'][ $id ] ?? array() as $path ) {
                if ( dirname( $path ) !== $directory || is_link( $path ) ) { throw new RuntimeException( 'Unsafe fixture file.' ); }
                if ( is_file( $path ) ) { unlink( $path ); }
            }
            if ( ! rmdir( $directory ) ) { throw new RuntimeException( 'The fixture directory contains an unexpected file.' ); }
        }
        wp_delete_post( $id, true );
    }
    foreach ( $backup['users'] as $actor ) {
        $id = $actor['id'];
        if ( ! get_user_by( 'id', $id ) ) { continue; }
        if ( $backup['tag'] !== get_user_meta( $id, $marker, true ) ) { throw new RuntimeException( 'Refusing to remove an unmarked actor.' ); }
        delete_option( '_tc_discord_binding_' . $actor['discord'] ); delete_option( '_tc_discord_lock_' . $id );
        wp_delete_user( $id );
    }
    foreach ( $backup['claims'] ?? array() as $claim ) {
        if ( preg_match( '/^_tc_download_claim_[a-f0-9]{64}$/D', $claim ) ) { delete_option( $claim ); if ( false !== get_option( $claim, false ) ) { throw new RuntimeException( 'A fixture claim remains.' ); } }
    }
    wp_clear_scheduled_hook( 'tc_download_cleanup' );
    foreach ( $backup['cron'] as $entry ) {
        $event = $entry['event'];
        if ( $event['schedule'] ) { wp_schedule_event( $entry['timestamp'], $event['schedule'], 'tc_download_cleanup', $event['args'] ); }
        else { wp_schedule_single_event( $entry['timestamp'], 'tc_download_cleanup', $event['args'] ); }
    }
    foreach ( $backup['options'] as $key => $entry ) {
        if ( $entry['exists'] ) { update_option( $key, $entry['value'] ); } else { delete_option( $key ); }
    }
    foreach ( $backup['options'] as $key => $entry ) {
        if ( $entry['exists'] && get_option( $key ) !== $entry['value'] ) { throw new RuntimeException( 'Fixture option restoration failed: ' . $key ); }
        if ( ! $entry['exists'] && false !== get_option( $key, false ) ) { throw new RuntimeException( 'New fixture option remains: ' . $key ); }
    }
    foreach ( $backup['posts'] as $id ) { if ( get_post( $id ) || file_exists( $root . '/' . $id ) || false !== get_option( '_tc_delivery_count_' . $id, false ) ) { throw new RuntimeException( 'A fixture release or counter remains.' ); } }
    foreach ( $backup['users'] as $actor ) { if ( get_user_by( 'id', $actor['id'] ) || false !== get_option( '_tc_discord_binding_' . $actor['discord'], false ) ) { throw new RuntimeException( 'A fixture user or identity binding remains.' ); } }
    delete_option( $option );
    return array( 'restored' => true, 'posts_removed' => count( $backup['posts'] ), 'users_removed' => count( $backup['users'] ), 'claims_removed' => count( $backup['claims'] ?? array() ), 'private_directories_remaining' => 0, 'counters_remaining' => 0, 'bindings_remaining' => 0 );
};
if ( 'restore' === $mode ) {
    if ( ! is_array( $saved ) || empty( $saved['tag'] ) ) { throw new RuntimeException( 'No download fixture backup exists.' ); }
    WP_CLI::line( 'TC_DOWNLOAD_BROWSER:' . wp_json_encode( $restore( $saved ) ) ); return;
}
if ( in_array( $mode, array( 'revoke', 'tamper', 'stats' ), true ) ) {
    if ( ! is_array( $saved ) || empty( $saved['tag'] ) ) { throw new RuntimeException( 'No download fixture exists.' ); }
    if ( 'revoke' === $mode ) {
        $id = $saved['users']['monthly']['id']; update_user_meta( $id, '_tc_download_delivery_roles', array() );
        foreach ( array( '_tc_discord_status', '_tc_discord_attempt', '_tc_discord_retry_until' ) as $key ) { delete_user_meta( $id, $key ); }
        WP_CLI::line( 'TC_DOWNLOAD_BROWSER:' . wp_json_encode( array( 'revoked' => true ) ) ); return;
    }
    if ( 'tamper' === $mode ) {
        $id = $saved['cases']['gpc']['id']; $path = $saved['paths'][ $id ][0];
        if ( ! is_file( $path ) || is_link( $path ) ) { throw new RuntimeException( 'Missing fixture bytes.' ); }
        file_put_contents( $path, str_repeat( 'X', filesize( $path ) ) );
        WP_CLI::line( 'TC_DOWNLOAD_BROWSER:' . wp_json_encode( array( 'tampered' => true ) ) ); return;
    }
    $roles = array(); $recent = array();
    foreach ( $saved['users'] as $kind => $actor ) { $roles[ $kind ] = get_userdata( $actor['id'] )->roles; $recent[ $kind ] = get_user_meta( $actor['id'], '_tc_recent_downloads', true ); }
    WP_CLI::line( 'TC_DOWNLOAD_BROWSER:' . wp_json_encode( array( 'roles' => $roles, 'recent' => $recent, 'claims' => count( $saved['claims'] ?? array() ) ) ) ); return;
}
if ( 'setup' !== $mode || false !== $saved || ! class_exists( 'TC_Downloads' ) || ! class_exists( 'TC_Private_Files' ) ) { throw new RuntimeException( 'Restore the previous fixture and load Core first.' ); }
$tag = getenv( 'TC_DOWNLOAD_DELIVERY_TAG' );
if ( ! is_string( $tag ) || ! preg_match( '/^[a-f0-9]{24}$/D', $tag ) ) { throw new RuntimeException( 'A unique fixture tag is required.' ); }
$backup = array( 'tag' => $tag, 'users' => array(), 'posts' => array(), 'paths' => array(), 'claims' => array(), 'cron' => array(), 'options' => array(), 'cases' => array() );
foreach ( array( 'timonconfigs_page_seo_queue', 'timonconfigs_page_seo_state', '_tc_page_seo_lock', 'timonconfigs_collections_version', '_transient_tc_discord_last_failure', '_transient_timeout_tc_discord_last_failure' ) as $key ) {
    $backup['options'][ $key ] = array( 'exists' => false !== get_option( $key, false ), 'value' => get_option( $key, false ) );
}
foreach ( _get_cron_array() as $timestamp => $hooks ) { foreach ( $hooks['tc_download_cleanup'] ?? array() as $event ) { $backup['cron'][] = array( 'timestamp' => $timestamp, 'event' => $event ); } }
if ( ! add_option( $option, $backup, '', false ) ) { throw new RuntimeException( 'Could not save the fixture backup.' ); }
try {
    wp_set_current_user( 0 );
    $root = TC_Private_Files::root(); if ( is_wp_error( $root ) ) { throw new RuntimeException( 'Private storage is unavailable.' ); }
    $encrypt = new ReflectionMethod( TC_Discord::class, 'encrypt' );
    $base = 810000000000000000 + hexdec( substr( $tag, 0, 10 ) );
    $role_ids = array( 'free' => '899999999999999991', 'monthly' => '899999999999999992', 'lifetime' => '899999999999999993', 'owner' => '899999999999999994' );
    foreach ( array( 'free', 'monthly', 'lifetime', 'owner', 'owner-only', 'unverified' ) as $index => $kind ) {
        $discord = (string) ( $base + $index );
        if ( get_option( '_tc_discord_binding_' . $discord ) ) { throw new RuntimeException( 'Fixture Discord identity already exists.' ); }
        $id = wp_insert_user( array( 'user_login' => 'tc_download_' . $tag . '_' . $kind, 'user_pass' => wp_generate_password( 64, true, true ), 'display_name' => 'LOCAL QA ' . $kind, 'role' => 'subscriber' ) );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        update_user_meta( $id, $marker, $tag ); update_user_meta( $id, '_tc_discord_id', $discord ); update_user_meta( $id, '_tc_discord_name', 'LOCAL QA ' . $kind );
        update_user_meta( $id, '_tc_discord_token', $encrypt->invoke( null, wp_json_encode( array( 'access_token' => 'LOCAL-QA-' . $tag . '-' . $kind, 'refresh_token' => 'LOCAL-QA-refresh', 'expires' => time() + 3600 ) ) ) );
        update_option( '_tc_discord_binding_' . $discord, $id, false );
        $roles = 'unverified' === $kind ? array() : ( 'owner' === $kind ? array_values( $role_ids ) : array( $role_ids[ 'owner-only' === $kind ? 'owner' : $kind ] ) );
        if ( in_array( $kind, array( 'monthly', 'lifetime' ), true ) ) { $roles[] = $role_ids['free']; }
        update_user_meta( $id, '_tc_download_delivery_roles', $roles );
        $backup['users'][ $kind ] = array( 'id' => $id, 'discord' => $discord ); update_option( $option, $backup, false );
    }
    $game = get_term_by( 'slug', 'apex-legends', 'tc_game' ); $cronus = get_term_by( 'slug', 'cronus-zen', 'tc_device' ); $matrix = get_term_by( 'slug', 'xim-matrix', 'tc_device' );
    if ( ! $game || ! $cronus || ! $matrix ) { throw new RuntimeException( 'Existing catalogue terms are required.' ); }
    $bytes = array(
        'gpc' => "// LOCAL QA original Windows code\r\n" . chr( 0xa9 ) . "\r\nmain { wait(10); }\r\n",
        'znbk' => implode( '', array_map( static fn( int $n ): string => chr( ( $n * 31 ) % 256 ), range( 0, 5119 ) ) ),
        'txt' => "\xff\xfe" . iconv( 'UTF-8', 'UTF-16LE', "LOCAL QA original Windows settings\r\nSensitivity=43\r\n" ),
        'matrix' => ">>> LOCAL_QA_XIM_MATRIX\nSensitivity=43\nZażółć gęślą jaźń\n",
        'matrix-txt' => ">>> LOCAL_QA_STORED_MATRIX\nSensitivity=21\n",
        'matrix-binary' => "LOCAL_QA_XIMMR\x00\x01\xff",
    );
    foreach ( array( 'gpc' => array( 'free', 'cronus', 'gpc' ), 'znbk' => array( 'monthly', 'cronus', 'znbk' ), 'txt' => array( 'lifetime', 'cronus', 'txt' ), 'matrix' => array( 'monthly', 'matrix', '' ), 'matrix-txt' => array( 'monthly', 'matrix', 'txt' ), 'matrix-binary' => array( 'free', 'matrix', 'ximmr' ), 'matrix-restricted' => array( 'monthly', 'matrix', '' ) ) as $key => $spec ) {
        $id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'LOCAL QA download ' . $key . ' ' . $tag, 'post_name' => 'tc-download-' . $tag . '-' . $key, 'post_content' => '<p>LOCAL QA public description; this is not a genuine customer release.</p>', 'post_excerpt' => 'LOCAL QA private download delivery fixture only.', 'meta_input' => array( $marker => $tag, 'tc_tier' => $spec[0], 'tc_release_status' => 'available', 'tc_input_method' => 'controller', '_tc_values_visibility' => 'matrix-restricted' === $key ? 'lifetime' : $spec[0] ) ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $backup['posts'][] = $id; update_option( $option, $backup, false );
        wp_set_object_terms( $id, array( $game->term_id ), 'tc_game' ); wp_set_object_terms( $id, array( 'matrix' === $spec[1] ? $matrix->term_id : $cronus->term_id ), 'tc_device' );
        if ( 'matrix' === $spec[1] && 'matrix-binary' !== $key ) { update_post_meta( $id, '_tc_values_content', 'matrix-txt' === $key ? 'LOCAL_QA_UNUSED_VALUES_MUST_NOT_EXPORT' : $bytes['matrix'] ); }
        $file_id = TC_Downloads::MATRIX_TEXT_ID; $name = sanitize_title( get_the_title( $id ) ) . '-xim-matrix.txt';
        if ( $spec[2] ) {
            $directory = $root . '/' . $id; if ( ! mkdir( $directory, 0700 ) ) { throw new RuntimeException( 'Could not create a private fixture directory.' ); }
            $file_id = wp_generate_uuid4(); $path = $directory . '/' . $file_id . '.' . $spec[2]; $name = 'local-qa-' . $key . '.' . $spec[2];
            if ( false === file_put_contents( $path, $bytes[ $key ] ) ) { throw new RuntimeException( 'Could not save fixture bytes.' ); } chmod( $path, 0600 );
            $backup['paths'][ $id ] = array( $path ); update_option( $option, $backup, false );
            update_post_meta( $id, '_tc_private_files', array( array( 'id' => $file_id, 'stored_name' => basename( $path ), 'name' => $name, 'size' => strlen( $bytes[ $key ] ), 'checksum' => hash( 'sha256', $bytes[ $key ] ), 'uploaded_at' => gmdate( 'Y-m-d H:i:s' ) ) ) );
        }
        $backup['cases'][ $key ] = array( 'id' => $id, 'url' => get_permalink( $id ), 'title' => get_the_title( $id ), 'file_id' => $file_id, 'name' => $name, 'bytes' => base64_encode( $bytes[ $key ] ?? $bytes['matrix'] ) ); update_option( $option, $backup, false );
    }
    $settings = array( 'enabled' => 1, 'pilot_enabled' => 1, 'pilot_ids' => implode( "\n", array_column( $backup['users'], 'discord' ) ), 'owner_ids' => $backup['users']['owner']['discord'] . "\n" . $backup['users']['owner-only']['discord'], 'staging_confirmed' => 1, 'client_id' => '899999999999999981', 'guild_id' => '899999999999999982', 'free_role' => $role_ids['free'], 'monthly_role' => $role_ids['monthly'], 'lifetime_role' => $role_ids['lifetime'], 'owner_role' => $role_ids['owner'], 'secret_encrypted' => $encrypt->invoke( null, 'LOCAL-QA-secret' ) );
    $backup['settings'] = $settings; update_option( $option, $backup, false );
    WP_CLI::line( 'TC_DOWNLOAD_BROWSER:' . wp_json_encode( array( 'tag' => $tag, 'users' => $backup['users'], 'cases' => $backup['cases'], 'settings' => $settings ) ) );
} catch ( Throwable $error ) { $restore( get_option( $option, $backup ) ); throw $error; }
