<?php
/** Disposable localhost only: failed fresh verification must not look like Owner revocation. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Owner_Editor' ) ) { throw new RuntimeException( 'Core Owner editor is required.' ); }
$checks = 0;
$expect = static function ( bool $condition, string $message ) use ( &$checks ): void { if ( ! $condition ) { throw new RuntimeException( 'Owner verification: ' . $message ); } ++$checks; };
$old_settings = get_option( 'timonconfigs_discord', null );
$old_failure = get_transient( 'tc_discord_last_failure' );
$old_user = get_current_user_id(); $old_get = $_GET;
$uid = 0; $outsider_uid = 0; $created = array(); $discord_id = '899999999999999981';
$binding = '_tc_discord_binding_' . $discord_id;
if ( false !== get_option( $binding, false ) ) { throw new RuntimeException( 'Test identity collision; no existing binding will be modified.' ); }
$encrypt = static fn( string $plain ): string => ( new ReflectionMethod( TC_Discord::class, 'encrypt' ) )->invoke( null, $plain );
$settings = array( 'enabled' => 1, 'staging_confirmed' => 1, 'pilot_enabled' => 1, 'pilot_ids' => $discord_id, 'owner_ids' => $discord_id, 'client_id' => '899999999999999982', 'guild_id' => '899999999999999983', 'free_role' => '899999999999999984', 'monthly_role' => '899999999999999985', 'lifetime_role' => '899999999999999986', 'owner_role' => '899999999999999988', 'secret_encrypted' => $encrypt( 'verification-fixture-secret' ) );
$roles = array( $settings['owner_role'] ); $mode = 'ok'; $calls = 0;
$https = static fn(): string => 'https://localhost:8080';
$http = static function ( $pre, $args, $url ) use ( &$mode, &$roles, &$calls, $discord_id, $settings ) {
    ++$calls;
    if ( 'https://discord.com/api/v10/users/@me/guilds/' . $settings['guild_id'] . '/member' !== $url ) { throw new RuntimeException( 'Unexpected outbound HTTP blocked.' ); }
    if ( 'transport' === $mode ) { return new WP_Error( 'fixture_network', 'PRIVATE_PROVIDER_DATA must never appear in diagnostics.' ); }
    if ( '429' === $mode ) { return array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '30' ), 'body' => '{"message":"PRIVATE_PROVIDER_DATA"}' ); }
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'user' => array( 'id' => $discord_id ), 'roles' => 'malformed' === $mode ? 'PRIVATE_PROVIDER_DATA' : $roles ) ) );
};
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 10, 3 );
try {
    $uid = wp_insert_user( array( 'user_login' => 'tc_verification_' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 64, true, true ), 'display_name' => 'Disposable Owner', 'role' => 'subscriber' ) );
    if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Could not create fixture subscriber.' ); }
    add_option( $binding, $uid, '', false ); update_user_meta( $uid, '_tc_discord_id', $discord_id );
    $token = $encrypt( wp_json_encode( array( 'access_token' => 'verification-fixture-access', 'refresh_token' => 'verification-fixture-refresh', 'expires' => time() + 3600 ) ) );
    update_user_meta( $uid, '_tc_discord_token', $token ); update_option( 'timonconfigs_discord', $settings, false ); wp_set_current_user( $uid );
    $_GET = array( 'tc_manage_configs' => '1', 'config_id' => '0' );
    $data = array( '_wpnonce' => wp_create_nonce( 'tc_owner_config' ), 'operation' => 'save', 'config_id' => '0', 'title' => 'Unsaved verification fixture', 'status' => 'draft', 'tc_tier' => 'monthly', 'tc_release_status' => 'available', 'tc_game' => array(), 'tc_device' => array(), 'tc_platform' => array() );
    $count_posts = static fn(): int => (int) ( new WP_Query( array( 'post_type' => 'tc_config', 'post_status' => 'any', 'author' => $uid, 'fields' => 'ids', 'posts_per_page' => 1 ) ) )->found_posts;
    $allow_retry = static function () use ( $uid ): void { update_user_meta( $uid, '_tc_discord_attempt', time() - 11 ); };

    // Owner configuration management is based on a specifically approved,
    // OAuth-bound WordPress session; NO online Discord role lookup is needed.
    $expect( TC_Discord::can_manage_configs_local(), 'Only the allowlisted bound Owner has frontend management access.' );
    $before_calls = $calls;
    $mode = 'transport';
    $html = TC_Owner_Editor::render();
    $expect( str_contains( $html, 'name="title"' ) && str_contains( $html, 'Manage Configs' ), 'Owner editor stays usable during Discord API outage.' );
    $expect( $calls === $before_calls, 'Opening Owner panel performs zero Discord HTTP calls.' );

    $draft = TC_Owner_Editor::perform( $data );
    if ( is_int( $draft ) && $draft > 0 ) { $created[] = $draft; }
    $expect( is_int( $draft ) && 'draft' === get_post_status( $draft ), 'Owner can save a draft when Discord is unreachable.' );
    $expect( $calls === $before_calls && ! TC_Owner_Editor::in_authorized_context(), 'No Discord HTTP check or persistent global privilege after Owner save.' );
    $edited = $data; $edited['config_id'] = (string) $draft; $edited['title'] = 'Saved again immediately';
    $expect( $draft === TC_Owner_Editor::perform( $edited ) && 'Saved again immediately' === get_the_title( $draft ), 'Back-to-back Owner saves are no longer rate-limited.' );
    $expect( $calls === $before_calls, 'Repeated save performs zero Discord HTTP calls.' );

    $roles = array( $settings['free_role'] );
    $expect( TC_Discord::can_manage_configs_local(), 'Removing Owner guild role does not block explicitly approved management.' );
    $expect( $calls === $before_calls, 'Role changes require no live lookup for Owner writes.' );
    $settings['owner_ids'] = ''; update_option( 'timonconfigs_discord', $settings, false );
    $expect( false === TC_Discord::can_manage_configs_local(), 'Revoking the ID allowlist immediately blocks Owner.' );
    $expect( is_wp_error( TC_Owner_Editor::perform( $edited ) ), 'Revoked Owner ID cannot change an existing config.' );
    $settings['owner_ids'] = $discord_id; update_option( 'timonconfigs_discord', $settings, false );

    $settings['pilot_ids'] = '899999999999999989'; update_option( 'timonconfigs_discord', $settings, false );
    $expect( false === TC_Discord::can_manage_configs_local(), 'Restricted pilot still blocks IDs removed from its list.' );
    $settings['pilot_ids'] = $discord_id; update_option( 'timonconfigs_discord', $settings, false );

    update_option( $binding, 0, false );
    $expect( false === TC_Discord::can_manage_configs_local(), 'Missing unique Discord-to-WP binding blocks editing.' );
    update_option( $binding, $uid, false );
    delete_user_meta( $uid, '_tc_discord_token' );
    $expect( false === TC_Discord::can_manage_configs_local(), 'Revoked OAuth token blocks Owner even while WP session exists.' );
    update_user_meta( $uid, '_tc_discord_token', $token );
    $expect( true === TC_Discord::can_manage_configs_local(), 'Restored bound OAuth session restores local Owner access.' );

    $other = wp_insert_user( array( 'user_login' => 'tc_outsider_' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 30 ), 'role' => 'subscriber' ) );
    if ( is_wp_error( $other ) ) { throw new RuntimeException( 'Could not create unapproved subscriber fixture.' ); }
    $outsider_uid = $other;
    wp_set_current_user( $other );
    $expect( false === TC_Discord::can_manage_configs_local(), 'Another logged-in WordPress user cannot inherit the Owner approval.' );
    $intruder = $edited; $intruder['_wpnonce'] = wp_create_nonce( 'tc_owner_config' );
    $expect( is_wp_error( TC_Owner_Editor::perform( $intruder ) ), 'Another subscriber with valid nonce cannot edit Owner config.' );
    wp_set_current_user( 0 );
    $expect( false === TC_Discord::can_manage_configs_local(), 'Anonymous visitors cannot access Owner.' );
    wp_set_current_user( $uid );

    // Paid configuration entitlements still use live Discord verification.
    $mode = 'transport'; delete_user_meta( $uid, '_tc_discord_status' ); delete_user_meta( $uid, '_tc_discord_attempt' );
    $status = TC_Discord::status();
    $expect( 'unavailable' === $status['state'], 'VIP role retrieval still fails closed on provider outage.' );
    $expect( TC_Discord::can_manage_configs_local(), 'Unrelated VIP outage does not remove local Owner authorization.' );
    WP_CLI::success( "Owner verification integration: $checks assertions; mocked Discord, disposable localhost fixtures only." );
} finally {
    remove_filter( 'pre_http_request', $http, 10 ); remove_filter( 'option_home', $https );
    foreach ( $created as $id ) { wp_delete_post( $id, true ); }
    if ( $outsider_uid ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $outsider_uid ); }
    if ( $uid ) { delete_transient( 'tc_owner_feedback_' . $uid ); delete_option( '_tc_discord_lock_' . $uid ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid ); }
    delete_option( $binding );
    null === $old_settings ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old_settings, false );
    false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
    $_GET = $old_get; wp_set_current_user( $old_user );
}
