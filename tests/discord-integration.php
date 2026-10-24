<?php
/** Run only on disposable local WordPress: fake Discord HTTP; no external requests. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Discord' ) ) { require WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-discord.php'; }
$old_failure = get_transient( 'tc_discord_last_failure' );
$checks = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$call = static function ( string $method, ...$args ) { $m = new ReflectionMethod( TC_Discord::class, $method ); $m->setAccessible( true ); return $m->invoke( null, ...$args ); };
$old = get_option( 'timonconfigs_discord', null ); $original_user = get_current_user_id(); $uids = array(); $posts = array(); $states = array();
$https = static fn() => 'https://localhost:8080';
$roles = array(); $mode = 'ok'; $requests = array(); $id = '900000000000000001';
$http = static function ( $pre, $args, $url ) use ( &$roles, &$mode, &$requests, $id ) {
    $requests[] = array( $url, $args );
    if ( ! str_starts_with( $url, 'https://discord.com/api/v10/users/' ) && ! in_array( $url, array( 'https://discord.com/api/oauth2/token', 'https://discord.com/api/oauth2/token/revoke' ), true ) ) { throw new RuntimeException( 'Unexpected outbound request blocked.' ); }
    if ( 'invalid-client' === $mode ) { return array( 'response' => array( 'code' => 401 ), 'body' => wp_json_encode( array( 'error' => 'invalid_client', 'error_description' => 'SECRET-MUST-NOT-LEAK' ) ), 'headers' => array() ); }
    if ( 'unknown-error' === $mode ) { return array( 'response' => array( 'code' => 400 ), 'body' => wp_json_encode( array( 'error' => 'SECRET-MUST-NOT-LEAK' ) ), 'headers' => array() ); }
    if ( 'error' === $mode ) { return new WP_Error( 'fake', 'Never expose upstream details' ); }
    if ( str_ends_with( $url, '/oauth2/token' ) ) { $body = array( 'access_token' => 'fake-access-new', 'refresh_token' => 'fake-refresh-new', 'token_type' => 'Bearer', 'scope' => 'identify guilds.members.read', 'expires_in' => 3600 ); if ( 'scope-array' === $mode ) { $body['scope'] = array( 'identify' ); } if ( 'expiry-negative' === $mode ) { $body['expires_in'] = -1; } }
    else { $body = array( 'user' => array( 'id' => 'wrong-user' === $mode ? '900000000000000099' : $id ), 'roles' => $roles ); if ( 'wrong-guild' === $mode ) { $body['guild_id'] = '900000000000000099'; } }
    return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $body ), 'headers' => array() );
};
add_filter( 'pre_http_request', $http, 10, 3 ); add_filter( 'option_home', $https );
try {
    wp_set_current_user( 0 ); delete_option( 'timonconfigs_discord' );
    $assert( ! TC_Discord::is_ready(), 'Default off.' );
    $settings = array( 'enabled' => 1, 'staging_confirmed' => 1, 'pkce' => 1, 'client_id' => '900000000000000002', 'guild_id' => '900000000000000003', 'free_role' => '900000000000000004', 'monthly_role' => '900000000000000005', 'lifetime_role' => '900000000000000006', 'secret_encrypted' => $call( 'encrypt', 'fake-client-secret' ) );
    update_option( 'timonconfigs_discord', $settings, false );
    $assert( TC_Discord::is_ready(), 'Explicit fake configured staging is ready.' );
    foreach ( array( 'scope-array', 'expiry-negative' ) as $bad_response ) { $mode = $bad_response; $assert( is_wp_error( $call( 'token_request', array( 'grant_type' => 'authorization_code', 'code' => 'synthetic' ) ) ), 'Malformed provider response fails closed without PHP diagnostics: ' . $mode ); }
    foreach ( array( 'invalid-client' => 'invalid_client', 'unknown-error' => 'http', 'error' => 'transport', 'scope-array' => 'malformed_response' ) as $diagnostic_mode => $reason ) {
        $mode = $diagnostic_mode;
        $failure = $call( 'token_request', array( 'grant_type' => 'authorization_code', 'code' => 'synthetic' ) );
        $assert( is_wp_error( $failure ) && $failure->get_error_data()['stage'] === 'token' && $failure->get_error_data()['reason'] === $reason, 'Token diagnostics preserve safe stage/reason: ' . $mode );
        $call( 'record_failure', $failure );
        $stored = get_transient( 'tc_discord_last_failure' );
        $assert( array_keys( $stored ) === array( 'time', 'stage', 'reason', 'http' ) && ! str_contains( wp_json_encode( $stored ), 'SECRET' ), 'Stored diagnostics contain only safe fields.' );
    }
    $call( 'record_failure', new WP_Error( 'external', 'SECRET', array( 'stage' => 'SECRET', 'reason' => 'SECRET', 'http' => 999, 'token' => 'SECRET' ) ) );
    $assert( 'unavailable' === get_transient( 'tc_discord_last_failure' )['reason'] && 0 === get_transient( 'tc_discord_last_failure' )['http'], 'Unknown diagnostic input is not persisted.' );
    ob_start(); TC_Discord::settings_page(); $anon_html = ob_get_clean();
    $assert( '' === $anon_html, 'Diagnostics are not rendered for anonymous users.' );
    $mode = 'ok';
    $cipher = $call( 'encrypt', 'secret-test' );
    $assert( ! str_contains( $cipher, 'secret-test' ) && 'secret-test' === $call( 'decrypt', $cipher ), 'Authenticated encryption round-trip.' );
    $assert( '' === $call( 'decrypt', substr( $cipher, 0, -4 ) . 'AAAA' ), 'Tampering rejected.' );
    $assert( 'unverified' === TC_Discord::status()['state'], 'Anonymous denied.' );
    $state = $call( 'create_state' ); $states[] = $state['state']; parse_str( wp_parse_url( $state['url'], PHP_URL_QUERY ), $query );
    $assert( 'identify guilds.members.read' === $query['scope'] && 'S256' === $query['code_challenge_method'], 'Minimal scopes and configured PKCE S256.' );
    $assert( 'https://localhost:8080/?tc_discord_callback=1' === $query['redirect_uri'], 'Exact callback.' );
    $verified = $call( 'validate_state', $state['state'], $state['cookie'] );
    $assert( is_array( $verified ) && $query['code_challenge'] === rtrim( strtr( base64_encode( hash( 'sha256', $verified['verifier'], true ) ), '+/', '-_' ), '=' ), 'PKCE challenge matches secret verifier.' );
    $assert( is_wp_error( $call( 'validate_state', $state['state'], $state['cookie'] ) ), 'State replay denied atomically.' );
    $state = $call( 'create_state' ); $states[] = $state['state'];
    $assert( is_wp_error( $call( 'validate_state', $state['state'], str_repeat( '0', 64 ) ) ), 'Cookie CSRF mismatch denied.' );
    $state = $call( 'create_state' ); $states[] = $state['state']; $key = '_tc_oauth_' . hash( 'sha256', $state['state'] );
    $data = json_decode( $call( 'decrypt', get_option( $key ) ), true ); $data['expires'] = time() - 1; update_option( $key, $call( 'encrypt', wp_json_encode( $data ) ), false );
    $assert( is_wp_error( $call( 'validate_state', $state['state'], $state['cookie'] ) ), 'Expired state denied.' );
    $uid = $call( 'bind', array( 'id' => $id, 'username' => 'Fake tester' ) );
    $assert( is_int( $uid ), 'Snowflake-only user created.' ); $uids[] = $uid;
    $assert( ! str_contains( get_userdata( $uid )->user_login, $id ) && ! str_contains( get_userdata( $uid )->user_nicename, $id ), 'Discord ID does not leak through WordPress login or public author slug.' );
    $assert( $uid === $call( 'bind', array( 'id' => $id, 'username' => 'Renamed' ) ), 'Stable unique identity binding.' );
    $assert( is_wp_error( $call( 'bind', array( 'id' => 'bad', 'email' => get_userdata( $uid )->user_email ) ) ), 'Malformed identity cannot email-link.' );
    wp_set_current_user( $uid );
    $assert( is_wp_error( $call( 'bind', array( 'id' => '900000000000000007' ) ) ), 'Logged-in account cannot attach a different Discord ID.' );
    $seed = static function ( int $expiry = 3600 ) use ( $call, $uid ) { update_user_meta( $uid, '_tc_discord_token', $call( 'encrypt', wp_json_encode( array( 'access_token' => 'fake-access', 'refresh_token' => 'fake-refresh', 'expires' => time() + $expiry ) ) ) ); delete_user_meta( $uid, '_tc_discord_status' ); delete_user_meta( $uid, '_tc_discord_attempt' ); };
    $post = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'Disposable Discord test', 'meta_input' => array( 'tc_tier' => 'free', 'tc_release_status' => 'available' ) ) ); $posts[] = $post;
    foreach ( array( 'unverified', 'free', 'monthly', 'lifetime' ) as $member_tier ) {
        $roles = 'unverified' === $member_tier ? array() : array( $settings[ $member_tier . '_role' ] ); $seed();
        $assert( $member_tier === TC_Discord::status()['state'], 'Roles derive ' . $member_tier );
        foreach ( array( 'free', 'monthly', 'lifetime' ) as $tier ) {
            update_post_meta( $post, 'tc_tier', $tier );
            $expected = 'free' === $tier ? 'unverified' !== $member_tier : ( 'monthly' === $tier ? in_array( $member_tier, array( 'monthly', 'lifetime' ), true ) : 'lifetime' === $member_tier );
            $assert( $expected === TC_Discord::can_access( $post ), 'Tier matrix ' . $member_tier . '/' . $tier );
        }
    }
    $before = count( $requests ); TC_Discord::status(); $assert( count( $requests ) === $before, 'Fresh cache avoids provider call.' );
    $assert( 'unavailable' === TC_Discord::status( 0, true )['state'], 'Refresh rate limited.' );
    delete_user_meta( $uid, '_tc_discord_attempt' ); $roles = array();
    $assert( 'unverified' === TC_Discord::status( 0, true )['state'], 'Role removal replaces cached VIP.' );
    $roles = array( $settings['lifetime_role'] ); $seed( 1 );
    $assert( 'lifetime' === TC_Discord::status()['state'], 'Expiring token proactively refreshed.' );
    $assert( 'fake-access-new' === json_decode( $call( 'decrypt', get_user_meta( $uid, '_tc_discord_token', true ) ), true )['access_token'], 'Rotated token persisted encrypted.' );
    $assert( in_array( 'https://discord.com/api/oauth2/token', array_column( $requests, 0 ), true ), 'Token exchange uses the documented OAuth endpoint.' );
    foreach ( array( 'error', 'wrong-guild', 'wrong-user' ) as $failure ) { $mode = $failure; $seed(); $assert( 'unavailable' === TC_Discord::status()['state'], 'Fail closed: ' . $failure ); $assert( is_wp_error( TC_Discord::can_access( $post ) ), 'Unavailable is explicit WP_Error.' ); }
    $mode = 'ok'; $seed(); add_option( '_tc_discord_lock_' . $uid, time() + 30, '', false );
    $assert( 'unavailable' === TC_Discord::status()['state'], 'Concurrent refresh never serves stale roles.' ); delete_option( '_tc_discord_lock_' . $uid );
    $seed(); TC_Discord::status(); $cache = get_user_meta( $uid, '_tc_discord_status', true ); $cache['expires_at'] = time() - 1; update_user_meta( $uid, '_tc_discord_status', $cache ); delete_user_meta( $uid, '_tc_discord_attempt' ); $mode = 'error';
    $assert( 'unavailable' === TC_Discord::status()['state'], 'Expired cache with API failure denies VIP.' );
    $assert( ! get_user_meta( $uid, '_tc_discord_status', true ), 'Failure clears stale status.' );
    $mode = 'ok'; $seed(); TC_Discord::revoke( $uid );
    $assert( ! get_user_meta( $uid, '_tc_discord_token', true ) && 'unverified' === TC_Discord::status()['state'], 'Revoke removes local credentials.' );
    parse_str( wp_parse_url( TC_Discord::logout_url(), PHP_URL_QUERY ), $logout ); $assert( (bool) wp_verify_nonce( $logout['_wpnonce'], 'tc_discord_logout' ), 'Logout nonce is session-bound.' );
    $seed(); update_post_meta( $post, 'tc_release_status', '' ); $assert( false === TC_Discord::can_access( $post ), 'Missing release status denied.' );
    update_post_meta( $post, 'tc_release_status', 'available' ); wp_update_post( array( 'ID' => $post, 'post_status' => 'draft' ) ); $assert( false === TC_Discord::can_access( $post ), 'Draft denied.' );
    $settings['guild_id'] = '900000000000000009'; update_option( 'timonconfigs_discord', $settings, false ); $seed(); $roles = array( $settings['free_role'] ); TC_Discord::status();
    $assert( str_contains( end( $requests )[0], '/guilds/900000000000000009/member' ), 'Configured guild owns role request.' );
    $seed();
    $replacement = ( time() + 60 ) . ':replacement-owner';
    $lease_race = static function ( $pre, $args, $url ) use ( $uid, $replacement ) { if ( str_ends_with( $url, '/member' ) ) { update_option( '_tc_discord_lock_' . $uid, $replacement, false ); } return $pre; };
    add_filter( 'pre_http_request', $lease_race, 9, 3 ); TC_Discord::status(); remove_filter( 'pre_http_request', $lease_race, 9 );
    $assert( $replacement === get_option( '_tc_discord_lock_' . $uid ), 'Old refresh owner cannot release a replacement lease.' ); delete_option( '_tc_discord_lock_' . $uid );
    // Simulate logout in another request while refresh-token HTTP is in flight.
    $seed( 1 );
    $race = static function ( $pre, $args, $url ) use ( $uid ) { if ( str_ends_with( $url, '/oauth2/token' ) ) { delete_user_meta( $uid, '_tc_discord_token' ); } return $pre; };
    add_filter( 'pre_http_request', $race, 9, 3 );
    $assert( 'unavailable' === TC_Discord::status()['state'], 'Concurrent logout defeats refresh compare-and-swap.' );
    $assert( ! get_user_meta( $uid, '_tc_discord_token', true ), 'Refresh cannot resurrect revoked credentials.' );
    remove_filter( 'pre_http_request', $race, 9 );
    $state = $call( 'create_state' ); $states[] = $state['state']; wp_set_current_user( 0 );
    $assert( is_wp_error( $call( 'validate_state', $state['state'], $state['cookie'] ) ), 'State bound to initiating WordPress session.' );
    $settings['staging_confirmed'] = 0; update_option( 'timonconfigs_discord', $settings, false );
    $assert( TC_Discord::is_ready() === in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true ), 'Unconfirmed OAuth is restricted to explicitly configured test environments.' );
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    wp_set_current_user( (int) $admins[0] );
    ob_start(); TC_Discord::settings_page(); $admin_html = ob_get_clean();
    $assert( str_contains( $admin_html, 'Last Discord verification failure' ) && ! str_contains( $admin_html, 'SECRET' ), 'Administrator sees redacted diagnostic panel.' );
    $saved = TC_Discord::sanitize_settings( array_merge( $settings, array( 'pilot_enabled' => 1, 'pilot_ids' => $id . ', ' . $id ) ) );
    $assert( 1 === $saved['pilot_enabled'] && $id === $saved['pilot_ids'], 'Admin can save a normalized pilot list.' );
    $assert( $settings['secret_encrypted'] === $saved['secret_encrypted'], 'Pilot configuration preserves the existing encrypted secret.' );
    $saved = TC_Discord::sanitize_settings( array_merge( $settings, array( 'pilot_enabled' => 1, 'pilot_ids' => array( $id ) ) ) );
    $assert( '' === $saved['pilot_ids'], 'Malformed submitted allowlist is cleared, never accepted.' );
    wp_set_current_user( 0 );
    $saved = TC_Discord::sanitize_settings( array( 'pilot_enabled' => 1, 'pilot_ids' => $id ) );
    $assert( $settings === $saved, 'Anonymous caller cannot change pilot settings.' );
    // Restricted production pilot never treats a local test as staging approval.
    $settings['pilot_enabled'] = 1; $settings['pilot_ids'] = $id;
    update_option( 'timonconfigs_discord', $settings, false );
    $assert( TC_Discord::is_ready(), 'Configured pilot works without false staging confirmation.' );
    $assert( empty( get_option( 'timonconfigs_discord' )['staging_confirmed'] ), 'Pilot leaves staging confirmation unset.' );
    $assert( $uid === $call( 'bind', array( 'id' => $id ) ), 'Allowlisted identity can reconnect.' );
    $outsider = '900000000000000088';
    $assert( is_wp_error( $call( 'bind', array( 'id' => $outsider ) ) ), 'Unlisted identity cannot bind.' );
    $assert( false === get_option( '_tc_discord_binding_' . $outsider ), 'Denied pilot identity creates no binding.' );
    $assert( array( $id ) === $call( 'pilot_ids', $id . ', ' . $id ), 'Duplicate IDs normalized.' );
    foreach ( array( '', array( $id ), $id . ',invalid', str_repeat( '9', 513 ), implode( ',', array_fill( 0, 11, $id ) ) ) as $invalid ) {
        $settings['pilot_ids'] = $invalid; update_option( 'timonconfigs_discord', $settings, false );
        $assert( ! TC_Discord::is_ready(), 'Empty/malformed/oversized pilot list never enables OAuth.' );
    }
    $settings['pilot_ids'] = $id; update_option( 'timonconfigs_discord', $settings, false );
    wp_set_current_user( $uid ); $seed(); $roles = array( $settings['lifetime_role'] );
    wp_update_post( array( 'ID' => $post, 'post_status' => 'publish' ) );
    $assert( 'lifetime' === TC_Discord::status()['state'], 'Allowed pilot still derives actual provider roles.' );
    $settings['pilot_ids'] = $outsider; update_option( 'timonconfigs_discord', $settings, false );
    $before = count( $requests );
    $assert( 'unverified' === TC_Discord::status()['state'], 'Allowlist removal defeats fresh cached VIP.' );
    $assert( false === TC_Discord::can_access( $post ), 'Removed tester cannot download with an existing session.' );
    $assert( count( $requests ) === $before, 'Removed tester is denied before provider traffic.' );
    $assert( is_wp_error( $call( 'bind', array( 'id' => $id ) ) ), 'Previously bound identity is also rejected after removal.' );
    $settings['staging_confirmed'] = 1; update_option( 'timonconfigs_discord', $settings, false );
    $assert( is_wp_error( $call( 'bind', array( 'id' => $id ) ) ), 'Pilot restriction wins even when staging was confirmed.' );
    $settings['staging_confirmed'] = 0; $settings['pilot_enabled'] = 0; update_option( 'timonconfigs_discord', $settings, false );
    $assert( TC_Discord::is_ready() === in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true ), 'Turning off pilot restores production staging gate.' );
    // Privileged badges remain separate from membership entitlements and require real role IDs.
    $settings['staging_confirmed'] = 1; $settings['pilot_enabled'] = 0;
    $settings['staff_role'] = '900000000000000021'; $settings['owner_role'] = '900000000000000022';
    $settings['owner_ids'] = $id;
    update_option( 'timonconfigs_discord', $settings, false ); wp_set_current_user( $uid );
    $assert( TC_Discord::is_ready(), 'Optional distinct Staff/Owner settings preserve readiness.' );
    $assert( 'subscriber' === get_userdata( $uid )->roles[0], 'Discord account keeps its existing subscriber role.' );
    foreach ( array( 'free', 'monthly', 'lifetime', 'staff', 'owner' ) as $badge ) {
        $roles = array( $settings[ $badge . '_role' ] ); $seed();
        $status = TC_Discord::status();
        $assert( array( $badge ) === array_keys( $status['badges'] ), 'Only verified configured badges shown: ' . $badge );
        $assert( ( 'owner' === $badge ) === TC_Discord::can_manage_configs( $uid, false ), 'Only approved Owner has frontend-management policy: ' . $badge );
        if ( in_array( $badge, array( 'staff', 'owner' ), true ) ) {
            $assert( 'unverified' === $status['state'] && false === TC_Discord::can_access( $post ), 'Staff/Owner badge does not grant paid or Free entitlement: ' . $badge );
        }
    }
    $roles = array( $settings['free_role'], $settings['monthly_role'], $settings['staff_role'], $settings['owner_role'], '900000000000000099' ); $seed();
    $status = TC_Discord::status();
    $assert( array( 'free', 'monthly', 'staff', 'owner' ) === array_keys( $status['badges'] ) && 'monthly' === $status['state'], 'All matching known badges displayed; unknown/Unverified role omitted.' );
    $assert( $status['owner_verified'] && $status['staff_verified'], 'Independent role-verification fields are true for verified IDs.' );
    $assert( is_wp_error( TC_Discord::can_manage_configs() ), 'Write authorization requires fresh verification and rate-limits even with cached Owner.' );
    delete_user_meta( $uid, '_tc_discord_attempt' ); $roles = array( $settings['free_role'] );
    $assert( false === TC_Discord::can_manage_configs(), 'Fresh role removal defeats previously cached Owner.' );
    $roles = array( $settings['owner_role'] ); $seed();
    $assert( true === TC_Discord::can_manage_configs(), 'Fresh role plus explicit approved ID grants management policy.' );
    $settings['owner_ids'] = ''; update_option( 'timonconfigs_discord', $settings, false ); $seed();
    $assert( false === TC_Discord::can_manage_configs(), 'Owner guild role alone never grants management.' );
    $settings['owner_ids'] = '900000000000000088'; update_option( 'timonconfigs_discord', $settings, false ); $seed();
    $assert( false === TC_Discord::can_manage_configs(), 'A different approved Discord ID cannot authorize this binding.' );
    $settings['owner_ids'] = $id; update_option( 'timonconfigs_discord', $settings, false ); $seed(); $mode = 'wrong-guild';
    $assert( is_wp_error( TC_Discord::can_manage_configs() ), 'Mismatched guild cannot authorize Owner writes.' );
    $mode = 'error'; $seed();
    $assert( is_wp_error( TC_Discord::can_manage_configs() ), 'Provider failure cannot authorize Owner writes.' );
    $mode = 'ok'; $seed();
    update_option( '_tc_discord_binding_' . $id, 0, false );
    $assert( false === TC_Discord::can_manage_configs(), 'Missing unique identity binding denies even an allowlisted Owner.' );
    update_option( '_tc_discord_binding_' . $id, $uid, false );
    $settings['staff_role'] = $settings['owner_role']; update_option( 'timonconfigs_discord', $settings, false );
    $assert( ! TC_Discord::is_ready(), 'Aliased Staff/Owner IDs fail closed.' );
    $settings['staff_role'] = '900000000000000021'; update_option( 'timonconfigs_discord', $settings, false );
    wp_set_current_user( (int) $admins[0] );
    $saved = TC_Discord::sanitize_settings( array_merge( $settings, array( 'owner_ids' => $id . ', ' . $id ) ) );
    $assert( $id === $saved['owner_ids'] && $settings['owner_role'] === $saved['owner_role'] && $settings['staff_role'] === $saved['staff_role'], 'Administrator saves normalized owner allowlist and distinct optional roles.' );
    $saved = TC_Discord::sanitize_settings( array_merge( $settings, array( 'owner_ids' => array( $id ), 'owner_role' => array( 'bad' ) ) ) );
    $assert( '' === $saved['owner_ids'] && '' === $saved['owner_role'], 'Malformed Owner settings are not accepted.' );
    $assert( false === TC_Discord::can_manage_configs(), 'Unbound WordPress administrator has no frontend Owner bypass.' );
    wp_set_current_user( 0 );
    $assert( false === TC_Discord::can_manage_configs(), 'Anonymous user cannot manage configs.' );
    $settings['enabled'] = 0; update_option( 'timonconfigs_discord', $settings, false );
    $assert( ! TC_Discord::is_ready() && TC_Discord::login_url() === home_url( '/account/' ), 'Disabled mode has no OAuth handoff.' );
    WP_CLI::success( "Discord integration: $checks assertions; fake HTTP only, no real Discord verification." );
} finally {
    false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
    remove_filter( 'pre_http_request', $http, 10 ); remove_filter( 'option_home', $https );
    foreach ( $posts as $post ) { wp_delete_post( $post, true ); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $uids as $uid ) { delete_option( '_tc_discord_binding_' . get_user_meta( $uid, '_tc_discord_id', true ) ); delete_option( '_tc_discord_lock_' . $uid ); wp_delete_user( $uid ); }
    foreach ( $states as $state ) { $key = '_tc_oauth_' . hash( 'sha256', $state ); delete_option( $key ); wp_clear_scheduled_hook( 'tc_discord_clean_state', array( $key ) ); }
    null === $old ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old, false ); wp_set_current_user( $original_user );
}
