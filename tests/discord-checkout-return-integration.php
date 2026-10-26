<?php
/** Disposable local WP: OAuth return destinations, one-use state and CSRF. No provider calls. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
$old = get_option( 'timonconfigs_discord', null ); $old_user = get_current_user_id(); $states = array(); $checks = 0; $uid = 0;
$old_get = $_GET; $old_cookie = $_COOKIE; $old_https = $_SERVER['HTTPS'] ?? null;
$allow_oauth = false; $requests = 0; $redirected = '';
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$call = static function ( string $method, ...$args ): mixed { return ( new ReflectionMethod( TC_Discord::class, $method ) )->invoke( null, ...$args ); };
$https = static fn(): string => 'https://localhost:8080';
$http = static function ( mixed $pre, array $args, string $url ) use ( &$allow_oauth, &$requests ): array {
    if ( ! $allow_oauth ) { throw new RuntimeException( 'Unexpected provider call blocked.' ); }
    if ( 'https://discord.com/api/oauth2/token' === $url && 'authorization_code' === ( $args['body']['grant_type'] ?? '' ) ) {
        $body = array( 'access_token' => 'synthetic-checkout-return-access', 'refresh_token' => 'synthetic-checkout-return-refresh', 'expires_in' => 3600, 'scope' => 'identify guilds.members.read', 'token_type' => 'Bearer' );
    } elseif ( 'https://discord.com/api/v10/users/@me' === $url && 'Bearer synthetic-checkout-return-access' === ( $args['headers']['Authorization'] ?? '' ) ) {
        $body = array( 'id' => '970000000000000001', 'username' => 'Synthetic checkout member' );
    } else { throw new RuntimeException( 'Unexpected provider or payment request blocked.' ); }
    ++$requests;
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $body ) );
};
$redirect = static function ( string $url ) use ( &$redirected ): never { $redirected = $url; throw new RuntimeException( 'tc_oauth_checkout_redirect_captured' ); };
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 10, 3 );
try {
    wp_set_current_user( 0 );
    $settings = array( 'enabled' => 1, 'pilot_enabled' => 1, 'pilot_ids' => '970000000000000001', 'client_id' => '970000000000000002', 'guild_id' => '970000000000000003', 'free_role' => '970000000000000004', 'monthly_role' => '970000000000000005', 'lifetime_role' => '970000000000000006', 'secret_encrypted' => $call( 'encrypt', 'synthetic-checkout-return-secret' ) );
    update_option( 'timonconfigs_discord', $settings, false );
    $assert( TC_Discord::is_ready(), 'Synthetic restricted OAuth fixture is ready.' );
    $token = str_repeat( 'a', 64 );
    foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
        $base = TC_Checkout::get_checkout_url( $plan );
        foreach ( array( $base, add_query_arg( 'tc_billing_intent', $token, $base ) ) as $target ) {
            parse_str( wp_parse_url( TC_Discord::login_url( $target ), PHP_URL_QUERY ), $query );
            $assert( '1' === $query['tc_discord_start'] && $target === $query['tc_discord_return'], 'Sign-in link preserves the selected plan and valid signed intent.' );
            $state = $call( 'create_state', $query['tc_discord_return'] ); $states[] = $state['state'];
            $stored = get_option( '_tc_oauth_' . hash( 'sha256', $state['state'] ) );
            $assert( is_string( $stored ) && ! str_contains( $stored, $target ), 'Return target is in authenticated ciphertext, not plaintext state.' );
            $assert( ! str_contains( $state['url'], 'tc_billing_intent' ) && ! str_contains( $state['url'], 'tc_discord_return' ), 'Provider authorization URL does not disclose checkout intent.' );
            $data = $call( 'validate_state', $state['state'], $state['cookie'] );
            $assert( is_array( $data ) && $target === $data['return_to'] && $target === $call( 'checkout_return', $data['return_to'] ), 'Validated callback state returns to the original canonical checkout.' );
            $assert( is_wp_error( $call( 'validate_state', $state['state'], $state['cookie'] ) ), 'Checkout continuation cannot replay consumed OAuth state.' );
        }
    }
    $base = TC_Checkout::get_checkout_url( 'monthly' );
    foreach ( array( 'https://evil.example/checkout/monthly/', '//evil.example/checkout/monthly/', '/checkout/monthly/', 'https://localhost:8080@evil.example/checkout/monthly/', $base . '../account/', $base . '#fragment', $base . '?redirect_to=https://evil.example', $base . '?tc_billing_intent=' . $token . '&redirect_to=https://evil.example', $base . '?tc_billing_intent[]=' . $token, $base . '?tc_billing_intent=' . str_repeat( 'A', 64 ), $base . '?tc_billing_intent=' . str_repeat( 'a', 63 ), str_repeat( 'a', 2049 ) ) as $bad ) {
        $assert( '' === $call( 'checkout_return', $bad ), 'Unapproved destination or malformed signed intent is rejected.' );
        $url = TC_Discord::login_url( $bad );
        $assert( ! str_contains( $url, 'tc_discord_return' ), 'Unapproved destination cannot enter a sign-in continuation link.' );
    }
    $state = $call( 'create_state', $base ); $states[] = $state['state'];
    $assert( is_wp_error( $call( 'validate_state', $state['state'], str_repeat( '0', 64 ) ) ), 'Checkout continuation retains cookie-bound CSRF protection.' );
    $state = $call( 'create_state' ); $states[] = $state['state'];
    $assert( '' === $call( 'validate_state', $state['state'], $state['cookie'] )['return_to'], 'Ordinary member sign-in keeps its account-page fallback.' );
    // Exercise the actual callback and capture its redirect before exit. Only
    // synthetic identity/token requests are permitted; no payment API exists here.
    $binding = '_tc_discord_binding_970000000000000001';
    if ( false !== get_option( $binding ) ) { throw new RuntimeException( 'Synthetic identity collision; refusing to replace a binding.' ); }
    $uid = wp_insert_user( array( 'user_login' => 'tc_oauth_return_' . bin2hex( random_bytes( 6 ) ), 'user_pass' => wp_generate_password( 48 ), 'role' => 'subscriber' ) );
    if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Synthetic user creation failed.' ); }
    update_user_meta( $uid, '_tc_discord_id', '970000000000000001' ); add_option( $binding, $uid, '', false );
    add_filter( 'wp_redirect', $redirect ); $allow_oauth = true; $_SERVER['HTTPS'] = 'on';
    foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
        wp_set_current_user( 0 );
        $target = add_query_arg( 'tc_billing_intent', $token, TC_Checkout::get_checkout_url( $plan ) );
        $state = $call( 'create_state', $target ); $states[] = $state['state'];
        $_GET = array( 'tc_discord_callback' => '1', 'state' => $state['state'], 'code' => 'synthetic-authorization-code', 'tc_discord_return' => 'https://evil.example/' );
        $_COOKIE['__Host-tc_discord_state'] = $state['cookie']; $redirected = '';
        try { TC_Discord::route(); throw new RuntimeException( 'Expected OAuth redirect was not captured.' ); }
        catch ( RuntimeException $error ) { if ( 'tc_oauth_checkout_redirect_captured' !== $error->getMessage() ) { throw $error; } }
        $assert( $uid === get_current_user_id() && $target === $redirected, 'Real callback authenticates then immediately returns to the saved checkout despite a forged return query.' );
        $assert( false === get_option( '_tc_oauth_' . hash( 'sha256', $state['state'] ) ), 'Real callback consumed the one-time state.' );
    }
    $assert( 4 === $requests, 'Both callbacks requested identity/token only, without creating any checkout or charge.' );
    $allow_oauth = false; remove_filter( 'wp_redirect', $redirect );
    $settings['enabled'] = 0; update_option( 'timonconfigs_discord', $settings, false );
    $assert( home_url( '/account/' ) === TC_Discord::login_url( $base ), 'Unconfigured OAuth cannot offer a checkout continuation.' );
    WP_CLI::success( 'Discord checkout return: ' . $checks . ' assertions; no provider requests or purchases.' );
} finally {
    foreach ( $states as $state ) { $key = '_tc_oauth_' . hash( 'sha256', $state ); delete_option( $key ); wp_clear_scheduled_hook( 'tc_discord_clean_state', array( $key ) ); }
    null === $old ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old, false );
    if ( is_int( $uid ) && $uid > 0 ) { delete_option( '_tc_discord_binding_970000000000000001' ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid ); }
    $_GET = $old_get; $_COOKIE = $old_cookie;
    if ( null === $old_https ) { unset( $_SERVER['HTTPS'] ); } else { $_SERVER['HTTPS'] = $old_https; }
    wp_set_current_user( $old_user ); remove_filter( 'option_home', $https ); remove_filter( 'pre_http_request', $http ); remove_filter( 'wp_redirect', $redirect );
}
