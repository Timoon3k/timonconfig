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
$uid = 0; $created = array(); $discord_id = '899999999999999981';
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

    $expect( true === TC_Discord::can_manage_configs( $uid, true ), 'Fixture is a current approved Owner before the failed request.' );
    $expect( is_array( get_user_meta( $uid, '_tc_discord_status', true ) ), 'A real fresh Owner result is cached.' );
    $before_count = $count_posts(); $mode = 'transport'; $allow_retry();
    $failure = TC_Owner_Editor::perform( $data );
    $expect( is_wp_error( $failure ) && 'tc_discord_unavailable' === $failure->get_error_code(), 'Fresh provider failure blocks a write despite the cached Owner result.' );
    $expect( $before_count === $count_posts() && ! TC_Owner_Editor::in_authorized_context(), 'Verification failure writes no config and leaves no scoped capabilities.' );
    $expect( is_array( get_user_meta( $uid, '_tc_discord_status', true ) ), 'A valid 60-second read-only Owner cache remains available after a failed forced check.' );
    // Simulate handle() feedback and its same-user 303 destination without exiting WP-CLI.
    set_transient( 'tc_owner_feedback_' . $uid, array( 'message' => '<script>unsafe feedback</script> Previous POST could not be verified.', 'error' => true ), 120 );
    $before_calls = $calls; $html = TC_Owner_Editor::render();
    $expect( $calls === $before_calls, 'Immediate redirect respects the existing ten-second cooldown.' );
    $expect( str_contains( $html, 'name="title"' ) && ! str_contains( $html, 'administrator-approved Owner access is required' ), 'Temporary failure retains an existing verified Owner editor without treating it as revoked.' );
    $expect( str_contains( $html, 'Previous POST could not be verified.' ) && str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ), 'Pending POST feedback is shown safely before the verification gate.' );
    $expect( str_contains( $html, 'name="title"' ) && str_contains( $html, '<form' ), 'An unexpired Owner read view persists but fresh write validation remains mandatory.' );
    $expect( false === get_transient( 'tc_owner_feedback_' . $uid ), 'Displayed feedback is consumed once.' );
    $diag = get_transient( 'tc_discord_last_failure' );
    $expect( 'membership' === ( $diag['stage'] ?? '' ) && 'transport' === ( $diag['reason'] ?? '' ) && 0 === ( $diag['http'] ?? -1 ), 'Administrator diagnostics identify membership transport failure.' );
    $expect( ! str_contains( wp_json_encode( $diag ), 'PRIVATE_PROVIDER_DATA' ) && ! str_contains( $html, 'PRIVATE_PROVIDER_DATA' ), 'Neither diagnostics nor UI contain provider payloads.' );
    $expect( $token === get_user_meta( $uid, '_tc_discord_token', true ) && (int) get_option( $binding ) === $uid && $settings['owner_ids'] === get_option( 'timonconfigs_discord' )['owner_ids'] && array( 'subscriber' ) === get_userdata( $uid )->roles, 'Temporary failures do not remove credentials, identity binding, Owner approval or WordPress roles.' );

    $mode = 'ok'; $allow_retry();
    $html = TC_Owner_Editor::render();
    $expect( str_contains( $html, 'name="title"' ) && ! str_contains( $html, 'temporarily unavailable' ), 'A successful later check restores the editor without relogin or manual privilege changes.' );
    $allow_retry(); $saved = TC_Owner_Editor::perform( $data );
    if ( is_int( $saved ) && $saved > 0 ) { $created[] = $saved; }
    $expect( is_int( $saved ) && $saved > 0 && 'draft' === get_post_status( $saved ), 'A subsequent verified write succeeds normally.' );

    // Cached Owner must never defeat a genuine role removal on a new write.
    $roles = array( $settings['free_role'] ); $allow_retry(); $denied = TC_Owner_Editor::perform( $data );
    $expect( is_wp_error( $denied ) && 'tc_owner_forbidden' === $denied->get_error_code(), 'A fresh successful Discord response without Owner rejects the write.' );
    set_transient( 'tc_owner_feedback_' . $uid, array( 'message' => 'Verified Owner access is required.', 'error' => true ), 120 );
    $html = TC_Owner_Editor::render();
    $expect( str_contains( $html, 'administrator-approved Owner access is required' ) && ! str_contains( $html, 'temporarily unavailable' ) && ! str_contains( $html, '<form' ), 'Actual role removal is shown as a permission denial, distinct from an outage.' );
    $expect( $before_count + 1 === $count_posts() && ! current_user_can( 'edit_tc_configs' ) && ! current_user_can( 'manage_options' ), 'Role removal creates no extra config and grants no persistent capabilities.' );

    foreach ( array( '429' => array( 'rate_limited', 429 ), 'malformed' => array( 'malformed_response', 200 ) ) as $failure_mode => $expected ) {
        delete_user_meta( $uid, '_tc_discord_retry_until' );
        $mode = (string) $failure_mode; $allow_retry();
        $result = TC_Discord::can_manage_configs( $uid, true );
        $diag = get_transient( 'tc_discord_last_failure' );
        $expect( is_wp_error( $result ) && $expected[0] === ( $diag['reason'] ?? '' ) && $expected[1] === ( $diag['http'] ?? 0 ), 'HTTP and malformed membership failures keep only bounded safe diagnostics.' );
        $expect( ! str_contains( wp_json_encode( $diag ), 'PRIVATE_PROVIDER_DATA' ), 'Provider response is never retained in the administrator diagnostic.' );
    }
    $expect( TC_Discord::retry_after( $uid ) >= 0, 'The UI can read a bounded Discord retry countdown.' );
    WP_CLI::success( "Owner verification integration: $checks assertions; mocked Discord, disposable localhost fixtures only." );
} finally {
    remove_filter( 'pre_http_request', $http, 10 ); remove_filter( 'option_home', $https );
    foreach ( $created as $id ) { wp_delete_post( $id, true ); }
    if ( $uid ) { delete_transient( 'tc_owner_feedback_' . $uid ); delete_option( '_tc_discord_lock_' . $uid ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid ); }
    delete_option( $binding );
    null === $old_settings ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old_settings, false );
    false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
    $_GET = $old_get; wp_set_current_user( $old_user );
}
