<?php
/** Public Live checkout on disposable WordPress; every provider/Discord request is synthetic. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
foreach ( array( 'TIMONCONFIGS_BILLING_LIVE_APPROVED', 'TIMONCONFIGS_BILLING_LIVE_STRIPE_SECRET_KEY', 'TIMONCONFIGS_BILLING_LIVE_STRIPE_WEBHOOK_SECRET', 'TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN' ) as $constant ) { if ( defined( $constant ) ) { throw new RuntimeException( 'Fresh CLI without real merchant credentials required.' ); } }
define( 'TIMONCONFIGS_BILLING_LIVE_STRIPE_SECRET_KEY', 'sk_live_publicLocalFixture' );
define( 'TIMONCONFIGS_BILLING_LIVE_STRIPE_WEBHOOK_SECRET', 'whsec_publicLocalFixture' );
define( 'TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN', 'synthetic-public-bot-token' );
global $wpdb;
$checks = 0; $users = array(); $requests = array(); $sessions = array(); $fault = '';
$tag = 'public_' . bin2hex( random_bytes( 6 ) ); $host = 'https://localhost:8080';
$ids = array( 'a' => '980000000000000101', 'b' => '980000000000000102', 'c' => '980000000000000103', 'owner' => '980000000000000104', 'guild' => '980000000000000105', 'app' => '980000000000000106', 'free' => '980000000000000107', 'monthly' => '980000000000000108', 'lifetime' => '980000000000000109', 'bot' => '980000000000000110', 'bot_role' => '980000000000000111' );
foreach ( array( 'a', 'b', 'c' ) as $actor ) { if ( false !== get_option( '_tc_discord_binding_' . $ids[ $actor ] ) ) { throw new RuntimeException( 'Synthetic binding collision; refusing to replace it.' ); } }
$old_options = array(); foreach ( array( 'timonconfigs_billing', 'timonconfigs_discord', 'cron', 'timonconfigs_billing_last_tick', 'timonconfigs_billing_worker_error' ) as $option ) { $old_options[ $option ] = get_option( $option, null ); }
$old_user = get_current_user_id(); $old_https = $_SERVER['HTTPS'] ?? null; $old_get = $_GET; $old_failure = get_transient( 'tc_discord_last_failure' );
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Public Live: ' . $message ); } ++$checks; };
$must = static function ( mixed $value ): mixed { if ( is_wp_error( $value ) ) { throw new RuntimeException( 'Unexpected public Live error: ' . $value->get_error_code() ); } return $value; };
$error_is = static fn( mixed $value, string $code ): bool => is_wp_error( $value ) && $code === $value->get_error_code();
$encrypt = static fn( string $value ): string => ( new ReflectionMethod( TC_Discord::class, 'encrypt' ) )->invoke( null, $value );
$https = static function () use ( &$host ): string { return $host; };
$response = static fn( array $body, int $status = 200 ): array => array( 'response' => array( 'code' => $status ), 'headers' => array(), 'body' => wp_json_encode( $body ) );
$oauth = array( 'enabled' => 1, 'staging_confirmed' => 0, 'pilot_enabled' => 1, 'pilot_ids' => $ids['owner'], 'owner_ids' => $ids['owner'], 'client_id' => $ids['app'], 'guild_id' => $ids['guild'], 'free_role' => $ids['free'], 'monthly_role' => $ids['monthly'], 'lifetime_role' => $ids['lifetime'], 'secret_encrypted' => $encrypt( 'synthetic-public-oauth-secret' ) );
$pilot = array( 'mode' => 'live', 'routing' => 'pilot', 'live_pilot_enabled' => true, 'pilot_real_charges_confirmed' => true, 'pilot_discord_id' => $ids['owner'], 'public_live_enabled' => false, 'role_sync_enabled' => true, 'stripe_test_confirmed' => false, 'migration_confirmed' => false, 'legal_confirmed' => false );
$prices = array();
foreach ( array( 'monthly' => 2999, 'lifetime' => 8000 ) as $plan => $amount ) {
    $price = 'price_' . $tag . '_' . $plan; $product = 'prod_' . $tag . '_' . $plan;
    $pilot[ 'live_stripe_' . $plan . '_price_id' ] = $price; $pilot[ 'live_stripe_' . $plan . '_product_id' ] = $product;
    $prices[ $price ] = array( 'id' => $price, 'livemode' => true, 'active' => true, 'product' => $product, 'currency' => 'eur', 'unit_amount' => $amount, 'billing_scheme' => 'per_unit', 'type' => 'monthly' === $plan ? 'recurring' : 'one_time', 'recurring' => 'monthly' === $plan ? array( 'interval' => 'month', 'interval_count' => 1, 'usage_type' => 'licensed' ) : null );
}
$http = static function ( mixed $pre, array $args, string $url ) use ( &$requests, &$sessions, &$fault, $response, $ids, $prices, $tag ): array {
    $requests[] = array( $url, $args ); $method = $args['method'] ?? 'GET';
    if ( 0 !== ( $args['redirection'] ?? null ) || true !== ( $args['sslverify'] ?? null ) ) { throw new RuntimeException( 'Unsafe transport blocked.' ); }
    if ( str_starts_with( $url, 'https://discord.com/api/v10/' ) ) {
        if ( 'GET' !== $method ) { throw new RuntimeException( 'Actual Discord role mutation blocked.' ); }
        $auth = $args['headers']['Authorization'] ?? ''; $path = substr( $url, strlen( 'https://discord.com/api/v10' ) );
        if ( str_starts_with( $auth, 'Bearer synthetic-public-' ) ) {
            $actor = substr( $auth, strlen( 'Bearer synthetic-public-' ) );
            if ( ! in_array( $actor, array( 'a', 'b', 'c' ), true ) || '/users/@me/guilds/' . $ids['guild'] . '/member' !== $path ) { throw new RuntimeException( 'Unexpected OAuth request blocked.' ); }
            return $response( array( 'user' => array( 'id' => $ids[ $actor ] ), 'roles' => array( $ids['free'] ) ) );
        }
        if ( 'Bot synthetic-public-bot-token' !== $auth ) { throw new RuntimeException( 'Unexpected Discord credential blocked.' ); }
        if ( '/users/@me' === $path ) { return $response( array( 'id' => $ids['bot'], 'bot' => true ) ); }
        if ( '/guilds/' . $ids['guild'] === $path ) { return $response( array( 'id' => $ids['guild'], 'owner_id' => $ids['owner'] ) ); }
        if ( '/guilds/' . $ids['guild'] . '/roles' === $path ) {
            $roles = array(); foreach ( array( 'guild' => 0, 'free' => 1, 'monthly' => 2, 'lifetime' => 3, 'bot_role' => 10 ) as $role => $position ) { $roles[] = array( 'id' => $ids[ $role ], 'position' => 'hierarchy' === $fault && 'lifetime' === $role ? 10 : $position, 'permissions' => 'bot_role' === $role ? '268435456' : '0', 'managed' => false ); } return $response( $roles );
        }
        if ( '/guilds/' . $ids['guild'] . '/members/' . $ids['bot'] === $path ) { return $response( array( 'user' => array( 'id' => $ids['bot'], 'bot' => true ), 'roles' => array( $ids['bot_role'] ) ) ); }
        foreach ( array( 'a', 'b', 'c' ) as $actor ) {
            if ( '/guilds/' . $ids['guild'] . '/members/' . $ids[ $actor ] === $path ) {
                if ( 'pause' === $fault ) { update_option( 'timonconfigs_billing', array_merge( TC_Billing::config(), array( 'routing' => 'disabled' ) ), false ); }
                return $response( array( 'user' => array( 'id' => $ids[ $actor ] ), 'roles' => array( $ids['free'] ) ) );
            }
        }
        throw new RuntimeException( 'Unexpected bot endpoint blocked.' );
    }
    if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/' ) || 'Bearer sk_live_publicLocalFixture' !== ( $args['headers']['Authorization'] ?? '' ) || '2024-06-20' !== ( $args['headers']['Stripe-Version'] ?? '' ) ) { throw new RuntimeException( 'Unexpected merchant request blocked.' ); }
    $path = substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( '/v1/' ) );
    if ( 'GET' === $method && str_starts_with( $path, 'prices/' ) && isset( $prices[ substr( $path, 7 ) ] ) ) { return $response( $prices[ substr( $path, 7 ) ] ); }
    if ( 'GET' === $method && str_starts_with( $path, 'products/prod_' . $tag . '_' ) ) { return $response( array( 'id' => substr( $path, 9 ), 'livemode' => true, 'active' => true ) ); }
    if ( 'POST' === $method && 'checkout/sessions' === $path ) {
        parse_str( $args['body'], $body ); $price = $prices[ $body['line_items'][0]['price'] ?? '' ] ?? null;
        if ( ! $price || ( 'recurring' === $price['type'] ? 'subscription' : 'payment' ) !== ( $body['mode'] ?? '' ) ) { throw new RuntimeException( 'Unexpected immutable purchase offer.' ); }
        $id = 'cs_live_' . $tag . '_' . ( count( $sessions ) + 1 );
        $sessions[ $id ] = array( 'id' => $id, 'livemode' => true, 'metadata' => $body['metadata'], 'client_reference_id' => $body['client_reference_id'], 'mode' => $body['mode'], 'currency' => 'eur', 'amount_total' => $price['unit_amount'], 'customer' => 'cus_' . $tag . '_' . count( $sessions ), 'subscription' => null, 'payment_intent' => null, 'status' => 'open', 'payment_status' => 'unpaid', 'url' => 'https://checkout.stripe.com/c/pay/' . $id );
        return $response( $sessions[ $id ] );
    }
    throw new RuntimeException( 'Unexpected provider endpoint blocked.' );
};
$purchase = static fn( string $plan = 'lifetime' ): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'checkout', 'provider' => 'stripe', 'plan' => $plan, 'consent' => '1' );
$writes = static function () use ( &$requests ): int { return count( array_filter( $requests, static fn( array $call ): bool => 'GET' !== ( $call[1]['method'] ?? 'GET' ) ) ); };
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 1, 3 ); $_SERVER['HTTPS'] = 'on';
try {
    foreach ( array( 'a', 'b', 'c', 'admin' ) as $actor ) {
        $uid = $must( wp_insert_user( array( 'user_login' => $tag . '_' . $actor, 'user_pass' => wp_generate_password( 48 ), 'role' => 'admin' === $actor ? 'administrator' : 'subscriber' ) ) ); $users[ $actor ] = (int) $uid;
        if ( 'admin' !== $actor ) { add_option( '_tc_discord_binding_' . $ids[ $actor ], $uid, '', false ); update_user_meta( $uid, '_tc_discord_id', $ids[ $actor ] ); update_user_meta( $uid, '_tc_discord_token', $encrypt( wp_json_encode( array( 'access_token' => 'synthetic-public-' . $actor, 'refresh_token' => 'synthetic-public-refresh-' . $actor, 'expires' => time() + HOUR_IN_SECONDS ) ) ) ); }
    }
    update_option( 'timonconfigs_discord', $oauth, false ); update_option( 'timonconfigs_billing', $pilot, false );
    $before_config = TC_Billing::config();
    $assert( ! TC_Billing::public_accounts_enabled() && ! TC_Billing::actor_can_purchase( $ids['a'] ), 'Existing restricted pilot does not become public merely by upgrading the plugin.' );
    wp_set_current_user( $users['a'] );
    $requested = array_merge( $pilot, array( 'public_live_enabled' => true, 'routing' => 'direct' ) );
    $assert( $before_config === TC_Billing::sanitize( $requested ), 'Ordinary members cannot approve public Live checkout through settings sanitization.' );
    wp_set_current_user( $users['admin'] ); $host = 'http://public.example.test'; $_SERVER['HTTPS'] = 'off';
    $assert( $before_config === TC_Billing::sanitize( $requested ), 'An insecure administrator request cannot approve public Live checkout.' ); $host = 'https://localhost:8080'; $_SERVER['HTTPS'] = 'on';
    $public = TC_Billing::sanitize( $requested );
    $assert( 'live' === $public['mode'] && 'direct' === $public['routing'] && ! empty( $public['public_live_enabled'] ), 'A secure administrator explicitly enables public Live without a server-file edit.' );
    $assert( empty( $public['stripe_test_confirmed'] ) && empty( $public['migration_confirmed'] ) && empty( $public['legal_confirmed'] ), 'Opening public checkout does not fabricate completed provider tests, migration or legal review.' );
    update_option( 'timonconfigs_billing', $public, false );
    $assert( TC_Billing::mode_allowed( 'live' ) && TC_Billing::direct_enabled() && TC_Billing::public_accounts_enabled(), 'Explicit public approval allows the Live environment and customer login.' );
    $assert( $ids['guild'] === TC_Billing::setting( 'discord_guild_id', 'live' ) && $ids['monthly'] === TC_Billing::setting( 'discord_monthly_role_id', 'live' ) && $ids['lifetime'] === TC_Billing::setting( 'discord_lifetime_role_id', 'live' ), 'Public checkout reuses the existing membership role IDs when billing fields are blank.' );
    $assert( TC_Billing::actor_can_purchase( $ids['a'] ) && TC_Billing::actor_can_purchase( $ids['b'] ), 'Different valid Discord customers may purchase despite saved single-account pilot flags.' );
    $assert( ! TC_Billing::actor_can_purchase( '' ) && ! TC_Billing::actor_can_purchase( 'invalid' ), 'Opening public checkout never makes malformed Discord identities valid.' );
    $before = count( $requests ); wp_set_current_user( 0 );
    $guest_html = TC_Billing_UI::checkout( 'monthly' );
    $assert( ! str_contains( $guest_html, '<form' ) && str_contains( $guest_html, 'Continue with Discord' ) && str_contains( $guest_html, 'tc_discord_return' ) && $before === count( $requests ), 'A guest receives login with direct checkout return, not an unauthenticated payment operation.' );
    $assert( $error_is( TC_Billing::perform( $purchase() ), 'tc_billing_nonce' ) && 0 === $writes(), 'Public availability does not remove authentication or nonce protection.' );
    wp_set_current_user( $users['admin'] );
    $assert( $error_is( TC_Billing::perform( $purchase() ), 'tc_billing_identity' ), 'An unbound administrator cannot purchase under another Discord identity.' );
    $rendered = array();
    foreach ( array( 'a' => 'monthly', 'b' => 'lifetime' ) as $actor => $plan ) {
        wp_set_current_user( $users[ $actor ] ); $before = count( $requests ); $html = TC_Billing_UI::checkout( $plan );
        $rendered[ 'checkout-public-' . $plan ] = $html;
        $assert( ! current_user_can( 'manage_options' ) && TC_Billing::current_user_can_purchase() && str_contains( $html, 'Pay with Stripe' ) && ! str_contains( $html, 'type="checkbox"' ) && ! str_contains( $html, 'Restricted Live pilot' ) && $before === count( $requests ), 'Ordinary non-Owner sees usable public payment buttons immediately: ' . $plan );
        $forged = array_merge( $purchase( $plan ), array( 'user_id' => $users['admin'], 'discord_id' => $ids['owner'], 'amount' => '1', 'currency' => 'usd' ) );
        $checkout = $must( TC_Billing::perform( $forged ) ); $order = TC_Billing_Store::order( $checkout['order_id'] );
        $assert( (int) $order['user_id'] === $users[ $actor ] && $ids[ $actor ] === $order['discord_id'] && $plan === $order['plan'] && ( 'monthly' === $plan ? 2999 : 8000 ) === (int) $order['amount'] && 'eur' === $order['currency'], 'The checkout binds to its own actual buyer and immutable EUR price: ' . $plan );
        $assert( array() === TC_Billing_Store::active( $users[ $actor ], 'live' ) && 1 === count( TC_Billing_Store::orders_for_user( $users[ $actor ], 'live' ) ), 'An open hosted checkout alone grants no paid access: ' . $plan );
    }
    $assert( 2 === $writes() && 2 === count( $sessions ), 'Two ordinary buyers create only their two intercepted independent checkout sessions.' );
    wp_set_current_user( $users['c'] );
    foreach ( array( 'hierarchy', 'pause' ) as $failure ) {
        $fault = $failure; $before = $writes(); $result = TC_Billing::perform( $purchase() );
        $assert( is_wp_error( $result ) && $before === $writes() && array() === TC_Billing_Store::orders_for_user( $users['c'], 'live' ), 'Live role delivery failure or in-flight sales pause stops an ordinary buyer before order creation: ' . $failure );
        update_option( 'timonconfigs_billing', $public, false );
    }
    $fault = ''; $paused = array_merge( $public, array( 'routing' => 'disabled' ) ); update_option( 'timonconfigs_billing', $paused, false );
    $assert( ! TC_Billing::direct_enabled() && TC_Billing::public_accounts_enabled() && TC_Discord::is_ready() && TC_Billing::provider_ready( 'stripe', 'live' ), 'Pausing new purchases preserves public customer login and financial processing.' );
    wp_set_current_user( $users['a'] ); $own = TC_Billing_Store::orders_for_user( $users['a'], 'live' )[0];
    $manage = array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'refresh', 'order_id' => $own['id'] );
    $assert( is_array( $must( TC_Billing::perform( $manage ) ) ), 'An ordinary customer can request reconciliation of their own order while purchases are paused.' );
    wp_set_current_user( $users['b'] ); $manage['_wpnonce'] = wp_create_nonce( 'tc_billing_action' );
    $assert( $error_is( TC_Billing::perform( $manage ), 'tc_billing_order' ), 'A different public customer cannot manage the first customer’s order.' );
    wp_set_current_user( $users['admin'] );
    $revert = TC_Billing::sanitize( array_merge( $paused, array( 'mode' => 'test', 'public_live_enabled' => false, 'live_pilot_enabled' => false, 'pilot_real_charges_confirmed' => false, 'pilot_discord_id' => '' ) ) );
    $assert( 'live' === $revert['mode'] && 'disabled' === $revert['routing'] && ! empty( $revert['public_live_enabled'] ), 'Existing Live orders retain the authorized public account environment when switching off new purchases.' );
    $public_to_pilot = TC_Billing::sanitize( array_merge( $paused, array( 'mode' => 'live', 'routing' => 'disabled', 'public_live_enabled' => false, 'live_pilot_enabled' => true, 'pilot_real_charges_confirmed' => true, 'pilot_discord_id' => $ids['owner'] ) ) );
    $assert( 'live' === $public_to_pilot['mode'] && 'disabled' === $public_to_pilot['routing'] && ! empty( $public_to_pilot['public_live_enabled'] ) && TC_Billing::pilot_active( $public_to_pilot ), 'Outstanding public Live orders preserve public authorization even when a valid leftover pilot would otherwise keep Live enabled.' );
    update_option( 'timonconfigs_billing', $public_to_pilot, false );
    $assert( TC_Billing::public_accounts_enabled() && ! TC_Billing::direct_enabled(), 'Saving public-to-pilot maintenance settings keeps existing public customers able to log in while new purchases stay paused.' );
    update_option( 'timonconfigs_billing', $paused, false );
    $host = 'http://public.example.test'; $_SERVER['HTTPS'] = 'off';
    $assert( ! TC_Billing::public_accounts_enabled() && ! TC_Billing::mode_allowed( 'live' ), 'Public Live cannot operate over HTTP.' ); $host = 'https://localhost:8080'; $_SERVER['HTTPS'] = 'on';
    $assert( 2 === $writes(), 'No external writes occurred beyond the two intercepted synthetic checkout creations.' );
    WP_CLI::success( "Public Live billing: $checks assertions; ordinary customers, actual local WordPress, all HTTP mocked, no real payment or role mutation." );
    if ( getenv( 'TC_BILLING_RENDER' ) ) { WP_CLI::line( 'TC_BILLING_PUBLIC_FIXTURES:' . wp_json_encode( $rendered ) ); }
} finally {
    remove_filter( 'pre_http_request', $http, 1 ); remove_filter( 'option_home', $https );
    foreach ( $users as $uid ) {
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'tc_billing_events WHERE order_id IN (SELECT id FROM ' . $wpdb->prefix . 'tc_billing_orders WHERE user_id=%d)', $uid ) );
        foreach ( array( 'entitlements', 'orders', 'sync_jobs', 'owned_roles' ) as $table ) { $wpdb->delete( $wpdb->prefix . 'tc_billing_' . $table, array( 'user_id' => $uid ) ); }
        delete_option( '_tc_discord_lock_' . $uid ); delete_transient( 'tc_billing_feedback_' . $uid ); delete_transient( 'tc_billing_admin_' . $uid );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid );
    }
    foreach ( array( 'a', 'b', 'c' ) as $actor ) { delete_option( '_tc_discord_binding_' . $ids[ $actor ] ); }
    foreach ( $old_options as $option => $value ) { null === $value ? delete_option( $option ) : update_option( $option, $value, false ); }
    false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
    $_GET = $old_get; if ( null === $old_https ) { unset( $_SERVER['HTTPS'] ); } else { $_SERVER['HTTPS'] = $old_https; }
    wp_set_current_user( $old_user );
}
