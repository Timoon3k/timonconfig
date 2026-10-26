<?php
/** Public OAuth must admit customers without granting file, Owner or WordPress privileges. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( defined( 'TIMONCONFIGS_BILLING_LIVE_APPROVED' ) ) { throw new RuntimeException( 'A fresh CLI process without a server Live override is required.' ); }
$checks = 0; $users = array(); $posts = array(); $calls = 0; $roles = array(); $fault = ''; $host = 'https://localhost:8080';
$ids = array( 'customer' => '980000000000000001', 'second' => '980000000000000002', 'owner' => '980000000000000003', 'client' => '980000000000000004', 'guild' => '980000000000000005', 'free' => '980000000000000006', 'monthly' => '980000000000000007', 'lifetime' => '980000000000000008', 'owner_role' => '980000000000000009' );
foreach ( array( 'customer', 'second' ) as $actor ) { if ( false !== get_option( '_tc_discord_binding_' . $ids[ $actor ] ) ) { throw new RuntimeException( 'Synthetic binding collision; refusing to replace existing data.' ); } }
$old = array(); foreach ( array( 'timonconfigs_billing', 'timonconfigs_discord', 'cron', 'default_role' ) as $name ) { $old[ $name ] = get_option( $name, null ); }
$old_user = get_current_user_id(); $old_failure = get_transient( 'tc_discord_last_failure' );
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Public OAuth: ' . $message ); } ++$checks; };
$call = static fn( string $method, ...$args ): mixed => ( new ReflectionMethod( TC_Discord::class, $method ) )->invoke( null, ...$args );
$home = static function () use ( &$host ): string { return $host; };
$http = static function ( mixed $pre, array $args, string $url ) use ( &$calls, &$fault, &$roles, $ids ): array {
    if ( 'GET' !== ( $args['method'] ?? 'GET' ) || 'https://discord.com/api/v10/users/@me/guilds/' . $ids['guild'] . '/member' !== $url || 'Bearer synthetic-public-customer' !== ( $args['headers']['Authorization'] ?? '' ) ) { throw new RuntimeException( 'Unexpected external request, payment or role write blocked.' ); }
    ++$calls;
    if ( 'transport' === $fault ) { return array( 'response' => array( 'code' => 503 ), 'headers' => array(), 'body' => '{}' ); }
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'user' => array( 'id' => $ids['customer'] ), 'roles' => $roles ) ) );
};
$refresh = static function ( int $uid ): void { delete_user_meta( $uid, '_tc_discord_status' ); delete_user_meta( $uid, '_tc_discord_attempt' ); delete_user_meta( $uid, '_tc_discord_retry_until' ); };
add_filter( 'option_home', $home ); add_filter( 'pre_http_request', $http, 1, 3 );
try {
    wp_set_current_user( 0 );
    $settings = array( 'enabled' => 1, 'pilot_enabled' => 1, 'pilot_ids' => $ids['owner'], 'owner_ids' => $ids['owner'], 'staging_confirmed' => 0, 'client_id' => $ids['client'], 'guild_id' => $ids['guild'], 'free_role' => $ids['free'], 'monthly_role' => $ids['monthly'], 'lifetime_role' => $ids['lifetime'], 'owner_role' => $ids['owner_role'], 'secret_encrypted' => $call( 'encrypt', 'synthetic-public-oauth-secret' ) );
    update_option( 'timonconfigs_discord', $settings, false );
    $billing = array( 'mode' => 'live', 'routing' => 'direct', 'public_live_enabled' => false ); update_option( 'timonconfigs_billing', $billing, false );
    $denied = $call( 'bind', array( 'id' => $ids['customer'], 'username' => 'Synthetic customer' ) );
    $assert( is_wp_error( $denied ) && 'pilot_denied' === ( $denied->get_error_data()['reason'] ?? '' ) && false === get_option( '_tc_discord_binding_' . $ids['customer'] ), 'Without public approval, an uninvited customer is still denied before account creation.' );
    $assert( TC_Discord::is_pilot_enabled(), 'The old pilot hint remains effective before explicit public approval.' );
    $billing['public_live_enabled'] = true; update_option( 'timonconfigs_billing', $billing, false );
    $assert( TC_Billing::public_accounts_enabled() && TC_Discord::is_ready() && ! TC_Discord::is_pilot_enabled(), 'Explicit public Live approval enables configured OAuth without falsely confirming staging tests.' );
    $bad_list = $settings; $bad_list['pilot_ids'] = ''; update_option( 'timonconfigs_discord', $bad_list, false );
    $assert( TC_Discord::is_ready(), 'A leftover empty pilot list cannot block explicitly approved public login.' ); update_option( 'timonconfigs_discord', $settings, false );
    foreach ( array( 'enabled', 'secret_encrypted', 'client_id', 'guild_id' ) as $required ) {
        $bad = $settings; $bad[ $required ] = ''; update_option( 'timonconfigs_discord', $bad, false );
        $assert( ! TC_Discord::is_ready(), 'Public approval does not bypass required OAuth setup: ' . $required );
    }
    $bad = $settings; $bad['monthly_role'] = $bad['free_role']; update_option( 'timonconfigs_discord', $bad, false );
    $assert( ! TC_Discord::is_ready(), 'Public approval preserves distinct protected-role validation.' ); update_option( 'timonconfigs_discord', $settings, false );
    foreach ( array( '', '123', 'not-a-discord-id' ) as $invalid ) { $assert( is_wp_error( $call( 'bind', array( 'id' => $invalid ) ) ), 'Malformed identities are denied despite public approval.' ); }
    update_option( 'default_role', 'administrator', false );
    foreach ( array( 'customer', 'second' ) as $actor ) {
        $uid = $call( 'bind', array( 'id' => $ids[ $actor ], 'username' => 'Synthetic public member', 'email' => 'ignored@example.invalid', 'role' => 'administrator' ) );
        if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Synthetic public binding failed: ' . $uid->get_error_code() ); }
        $users[ $actor ] = $uid;
        $assert( (int) get_option( '_tc_discord_binding_' . $ids[ $actor ] ) === $uid && $ids[ $actor ] === get_user_meta( $uid, '_tc_discord_id', true ), 'Public customer receives a unique canonical identity binding: ' . $actor );
        $user = get_userdata( $uid );
        $assert( array( 'subscriber' ) === $user->roles && ! user_can( $uid, 'manage_options' ) && '' === $user->user_email, 'Public registration ignores submitted/default administrator role and email: ' . $actor );
    }
    $uid = $users['customer']; wp_set_current_user( $uid );
    update_user_meta( $uid, '_tc_discord_token', $call( 'encrypt', wp_json_encode( array( 'access_token' => 'synthetic-public-customer', 'refresh_token' => 'synthetic-public-refresh', 'expires' => time() + HOUR_IN_SECONDS ) ) ) );
    $assert( ! TC_Discord::can_manage_configs_local( $uid ), 'Public checkout does not approve Owner publishing tools.' );
    foreach ( array( 'free', 'monthly' ) as $tier ) {
        $post = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'Disposable public OAuth ' . $tier, 'post_author' => $uid ), true );
        if ( is_wp_error( $post ) ) { throw new RuntimeException( 'Synthetic config failed.' ); }
        $posts[ $tier ] = $post; update_post_meta( $post, 'tc_release_status', 'available' ); update_post_meta( $post, 'tc_tier', $tier );
    }
    $status = TC_Discord::status( $uid );
    $assert( 'unverified' === $status['state'] && ! $status['owner_verified'] && empty( $status['badges'] ), 'Public login without guild roles grants no Free, VIP, Staff or Owner status.' );
    $assert( false === TC_Discord::can_access( $posts['free'], $uid ) && false === TC_Discord::can_access( $posts['monthly'], $uid ), 'Public registration alone cannot download even a Free-member protected file.' );
    $roles = array( $ids['free'], $ids['owner_role'] ); $refresh( $uid ); $status = TC_Discord::status( $uid );
    $assert( 'free' === $status['state'] && isset( $status['badges']['owner'] ) && ! $status['owner_verified'] && ! TC_Discord::can_manage_configs_local( $uid ), 'An actual Owner-role badge still cannot bypass the approved Owner user-ID list.' );
    $assert( true === TC_Discord::can_access( $posts['free'], $uid ) && false === TC_Discord::can_access( $posts['monthly'], $uid ), 'Canonical Free role allows only Free files, not paid configuration downloads.' );
    $fault = 'transport'; $refresh( $uid ); $status = TC_Discord::status( $uid );
    $assert( 'unavailable' === $status['state'] && is_wp_error( TC_Discord::can_access( $posts['monthly'], $uid ) ), 'Discord verification failures still deny protected file delivery.' ); $fault = '';
    $paused = $billing; $paused['routing'] = 'disabled'; update_option( 'timonconfigs_billing', $paused, false );
    $assert( TC_Billing::public_accounts_enabled() && TC_Discord::is_ready() && ! TC_Billing::direct_enabled(), 'Pausing new purchases retains public member login for existing customer management.' );
    wp_set_current_user( 0 );
    $assert( $uid === $call( 'bind', array( 'id' => $ids['customer'] ) ), 'Existing customer can reconnect to the same binding while checkout is paused.' );
    $paused['public_live_enabled'] = false; update_option( 'timonconfigs_billing', $paused, false );
    $denied = $call( 'bind', array( 'id' => $ids['customer'] ) );
    $assert( is_wp_error( $denied ) && TC_Discord::is_pilot_enabled(), 'Removing public approval restores the configured restricted OAuth allowlist.' );
    $host = 'http://localhost:8080'; update_option( 'timonconfigs_billing', $billing, false );
    $assert( ! TC_Billing::public_accounts_enabled() && ! TC_Discord::is_ready(), 'Plain HTTP cannot broaden OAuth through the public billing flag.' );
    $assert( $calls >= 3, 'Role authorization used canonical mocked guild membership reads, without payment or role-write traffic.' );
    WP_CLI::success( "Public Discord checkout integration: $checks assertions; local WordPress, synthetic identities, GET-only mocked Discord, no purchases or role writes." );
} finally {
    remove_filter( 'pre_http_request', $http, 1 ); remove_filter( 'option_home', $home );
    foreach ( $posts as $post ) { wp_delete_post( $post, true ); }
    foreach ( $users as $actor => $uid ) { delete_option( '_tc_discord_binding_' . $ids[ $actor ] ); delete_option( '_tc_discord_lock_' . $uid ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid ); }
    foreach ( $old as $name => $value ) { null === $value ? delete_option( $name ) : update_option( $name, $value, false ); }
    false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
    wp_set_current_user( $old_user );
}
