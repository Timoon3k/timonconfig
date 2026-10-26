<?php
/** Real local WordPress, synthetic Live objects and intercepted HTTP; never real payments. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
foreach ( array( 'TIMONCONFIGS_BILLING_LIVE_APPROVED', 'TIMONCONFIGS_BILLING_LIVE_STRIPE_SECRET_KEY', 'TIMONCONFIGS_BILLING_LIVE_STRIPE_WEBHOOK_SECRET', 'TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN', 'TIMONCONFIGS_BILLING_LIVE_PAYPAL_CLIENT_ID', 'TIMONCONFIGS_BILLING_LIVE_PAYPAL_CLIENT_SECRET', 'TIMONCONFIGS_BILLING_DISCORD_PUBLIC_KEY' ) as $constant ) { if ( defined( $constant ) ) { throw new RuntimeException( 'A fresh CLI process without real billing credentials is required.' ); } }
if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) { throw new RuntimeException( 'Sodium is required for signed pilot intent checks.' ); }
define( 'TIMONCONFIGS_BILLING_LIVE_STRIPE_SECRET_KEY', 'sk_live_localPilotFixture' );
define( 'TIMONCONFIGS_BILLING_LIVE_STRIPE_WEBHOOK_SECRET', 'whsec_localPilotFixture' );
define( 'TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN', 'synthetic-local-pilot-bot' );
define( 'TIMONCONFIGS_BILLING_LIVE_PAYPAL_CLIENT_ID', 'synthetic-local-paypal-client' );
define( 'TIMONCONFIGS_BILLING_LIVE_PAYPAL_CLIENT_SECRET', 'synthetic-local-paypal-secret' );
$key_pair = sodium_crypto_sign_keypair();
define( 'TIMONCONFIGS_BILLING_DISCORD_PUBLIC_KEY', bin2hex( sodium_crypto_sign_publickey( $key_pair ) ) );
global $wpdb;
$checks = 0; $users = array(); $requests = array(); $sessions = array(); $intents = array();
$tag = 'pilot_' . bin2hex( random_bytes( 6 ) ); $host = 'https://localhost:8080'; $fault = '';
$ids = array( 'invited' => '960000000000000201', 'other' => '960000000000000202', 'guild' => '960000000000000203', 'application' => '960000000000000204', 'free' => '960000000000000205', 'monthly' => '960000000000000206', 'lifetime' => '960000000000000207', 'bot' => '960000000000000208', 'bot_role' => '960000000000000209' );
foreach ( array( 'invited', 'other' ) as $actor ) { if ( false !== get_option( '_tc_discord_binding_' . $ids[ $actor ] ) ) { throw new RuntimeException( 'Existing binding collision; refusing to replace it.' ); } }
$old_options = array();
foreach ( array( 'timonconfigs_billing', 'timonconfigs_discord', 'timonconfigs_billing_secrets', 'cron', 'timonconfigs_billing_last_tick', 'timonconfigs_billing_worker_error' ) as $option ) { $old_options[ $option ] = get_option( $option, null ); }
$old_failure = get_transient( 'tc_discord_last_failure' ); $old_user = get_current_user_id(); $old_get = $_GET; $old_https = $_SERVER['HTTPS'] ?? null;
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Live pilot: ' . $message ); } ++$checks; };
$must = static function ( mixed $result ): mixed { if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Unexpected pilot fixture error: ' . $result->get_error_code() ); } return $result; };
$error_is = static fn( mixed $value, string $code ): bool => is_wp_error( $value ) && $code === $value->get_error_code();
$encrypt = static fn( string $value ): string => ( new ReflectionMethod( TC_Discord::class, 'encrypt' ) )->invoke( null, $value );
$https = static function () use ( &$host ): string { return $host; };
$response = static fn( array $body, int $status = 200 ): array => array( 'response' => array( 'code' => $status ), 'headers' => array(), 'body' => wp_json_encode( $body ) );
$oauth = array( 'enabled' => 1, 'staging_confirmed' => 1, 'client_id' => $ids['application'], 'guild_id' => $ids['guild'], 'free_role' => $ids['free'], 'monthly_role' => $ids['monthly'], 'lifetime_role' => $ids['lifetime'], 'secret_encrypted' => $encrypt( 'synthetic-pilot-oauth-secret' ) );
$pilot = array( 'mode' => 'live', 'routing' => 'pilot', 'live_pilot_enabled' => true, 'pilot_real_charges_confirmed' => true, 'pilot_discord_id' => $ids['invited'], 'role_sync_enabled' => true, 'stripe_test_confirmed' => false, 'migration_confirmed' => false, 'legal_confirmed' => false );
$prices = array();
foreach ( array( 'monthly' => 2999, 'lifetime' => 8000 ) as $plan => $amount ) {
    $price = 'price_' . $tag . '_' . $plan; $product = 'prod_' . $tag . '_' . $plan;
    $prices[ $price ] = array( 'id' => $price, 'livemode' => true, 'active' => true, 'product' => $product, 'currency' => 'eur', 'unit_amount' => $amount, 'billing_scheme' => 'per_unit', 'type' => 'monthly' === $plan ? 'recurring' : 'one_time', 'recurring' => 'monthly' === $plan ? array( 'interval' => 'month', 'interval_count' => 1, 'usage_type' => 'licensed' ) : null );
}
$monthly_price = 'price_' . $tag . '_monthly'; $lifetime_price = 'price_' . $tag . '_lifetime'; $event_id = 'evt_' . $tag . '_paid';
$monthly_contract = array();
$metadata = static fn( array $order ): array => array( 'tc_order_id' => $order['id'], 'tc_user_id' => (string) $order['user_id'], 'tc_discord_id' => $order['discord_id'], 'tc_plan' => $order['plan'], 'tc_mode' => $order['mode'] );
$http = static function ( mixed $pre, array $args, string $url ) use ( &$requests, &$sessions, &$fault, &$monthly_contract, $prices, $response, $ids, $tag ): array {
    $requests[] = array( $url, $args ); $method = $args['method'] ?? 'GET';
    if ( 0 !== ( $args['redirection'] ?? null ) || true !== ( $args['sslverify'] ?? null ) ) { throw new RuntimeException( 'Unsafe HTTP transport blocked.' ); }
    if ( str_starts_with( $url, 'https://discord.com/api/v10/' ) ) {
        if ( 'GET' !== $method ) { throw new RuntimeException( 'Discord role writes are forbidden in this checkout fixture.' ); }
        $authorization = $args['headers']['Authorization'] ?? '';
        if ( 'Bot synthetic-local-pilot-bot' === $authorization ) {
            $path = substr( $url, strlen( 'https://discord.com/api/v10' ) );
            if ( '/users/@me' === $path ) { return 'bot-unavailable' === $fault ? $response( array( 'message' => 'Synthetic unavailable' ), 503 ) : $response( array( 'id' => $ids['bot'], 'bot' => true ) ); }
            // The invited buyer owns the guild. Exact VIP-role endpoints may still
            // assign roles below the bot; no moderation or full replacement is needed.
            if ( '/guilds/' . $ids['guild'] === $path ) { return $response( array( 'id' => $ids['guild'], 'owner_id' => $ids['invited'] ) ); }
            if ( '/guilds/' . $ids['guild'] . '/roles' === $path ) {
                $roles = array();
                foreach ( array( 'guild' => 0, 'free' => 1, 'monthly' => 2, 'lifetime' => 3, 'bot_role' => 10 ) as $role => $position ) {
                    $roles[] = array( 'id' => $ids[ $role ], 'position' => 'hierarchy' === $fault && 'lifetime' === $role ? 10 : $position, 'permissions' => 'bot_role' === $role ? '268435456' : '0', 'managed' => false );
                }
                return $response( $roles );
            }
            if ( '/guilds/' . $ids['guild'] . '/members/' . $ids['bot'] === $path ) { return $response( array( 'user' => array( 'id' => $ids['bot'], 'bot' => true ), 'roles' => array( $ids['bot_role'] ) ) ); }
            if ( '/guilds/' . $ids['guild'] . '/members/' . $ids['invited'] === $path ) {
                if ( 'pilot-paused' === $fault ) { update_option( 'timonconfigs_billing', array_merge( TC_Billing::config(), array( 'routing' => 'disabled' ) ), false ); }
                return $response( array( 'user' => array( 'id' => $ids['invited'] ), 'roles' => array( $ids['free'] ) ) );
            }
            throw new RuntimeException( 'Unexpected bot endpoint blocked.' );
        }
        $actor = 'Bearer synthetic-pilot-invited' === $authorization ? 'invited' : ( 'Bearer synthetic-pilot-other' === $authorization ? 'other' : '' );
        if ( '' === $actor || ! str_ends_with( $url, '/users/@me/guilds/' . $ids['guild'] . '/member' ) ) { throw new RuntimeException( 'Unexpected Discord request blocked.' ); }
        return $response( array( 'user' => array( 'id' => $ids[ $actor ] ), 'roles' => array( $ids['free'] ) ) );
    }
    if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/' ) || 'Bearer sk_live_localPilotFixture' !== ( $args['headers']['Authorization'] ?? '' ) || '2024-06-20' !== ( $args['headers']['Stripe-Version'] ?? '' ) ) { throw new RuntimeException( 'Unexpected provider or credentials blocked.' ); }
    $path = substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( '/v1/' ) );
    if ( 'GET' === $method && str_starts_with( $path, 'prices/' ) && isset( $prices[ substr( $path, 7 ) ] ) ) {
        $price = $prices[ substr( $path, 7 ) ]; if ( 'bad-lifetime' === $fault && 'one_time' === $price['type'] ) { $price['unit_amount'] = 1; } return $response( $price );
    }
    if ( 'GET' === $method && str_starts_with( $path, 'products/prod_' . $tag . '_' ) ) { return $response( array( 'id' => substr( $path, 9 ), 'livemode' => true, 'active' => true ) ); }
    if ( 'POST' === $method && 'checkout/sessions' === $path ) {
        parse_str( $args['body'], $body );
        if ( 'payment' !== ( $body['mode'] ?? '' ) || ! isset( $prices[ $body['line_items'][0]['price'] ?? '' ] ) ) { throw new RuntimeException( 'Unexpected fake checkout plan.' ); }
        $price = $prices[ $body['line_items'][0]['price'] ]; $id = 'cs_live_' . $tag;
        $sessions[ $id ] = array( 'id' => $id, 'livemode' => true, 'metadata' => $body['metadata'], 'client_reference_id' => $body['client_reference_id'], 'mode' => 'payment', 'currency' => 'eur', 'amount_total' => 8000, 'customer' => 'cus_' . $tag, 'subscription' => null, 'payment_intent' => 'pi_' . $tag, 'status' => 'open', 'payment_status' => 'unpaid', 'url' => 'https://checkout.stripe.com/c/pay/' . $id, 'line_items' => array( 'has_more' => false, 'data' => array( array( 'quantity' => 1, 'amount_total' => 8000, 'price' => $price ) ) ) );
        return $response( $sessions[ $id ] );
    }
    if ( 'GET' === $method && str_starts_with( $path, 'checkout/sessions/' ) && isset( $sessions[ substr( $path, 18 ) ] ) ) { return $response( $sessions[ substr( $path, 18 ) ] ); }
    if ( 'GET' === $method && 'payment_intents/pi_' . $tag === $path ) {
        $session = reset( $sessions );
        return $response( array( 'id' => 'pi_' . $tag, 'livemode' => true, 'metadata' => $session['metadata'], 'customer' => 'cus_' . $tag, 'invoice' => null, 'status' => 'succeeded', 'currency' => 'eur', 'amount' => 8000, 'amount_received' => 8000, 'latest_charge' => 'ch_' . $tag ) );
    }
    if ( 'GET' === $method && 'charges/ch_' . $tag === $path ) { return $response( array( 'id' => 'ch_' . $tag, 'livemode' => true, 'customer' => 'cus_' . $tag, 'invoice' => null, 'payment_intent' => 'pi_' . $tag, 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'currency' => 'eur', 'amount' => 8000, 'amount_refunded' => 0, 'refunded' => false, 'disputed' => false ) ); }
    if ( 'GET' === $method && 'subscriptions/sub_' . $tag === $path ) { return $response( $monthly_contract ); }
    if ( 'POST' === $method && 'subscriptions/sub_' . $tag === $path ) {
        parse_str( $args['body'], $body ); if ( array( 'cancel_at_period_end' => 'true' ) !== $body ) { throw new RuntimeException( 'Unexpected cancellation mutation blocked.' ); }
        $monthly_contract['cancel_at_period_end'] = true; return $response( $monthly_contract );
    }
    throw new RuntimeException( 'Unexpected provider endpoint blocked.' );
};
$purchase = static fn(): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'checkout', 'plan' => 'lifetime', 'provider' => 'stripe', 'consent' => '1' );
$admin_setup = static fn(): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_admin' ), 'operation' => 'stripe_setup', 'mode' => 'live', 'monthly_price_id' => $monthly_price, 'lifetime_price_id' => $lifetime_price );
$write_count = static function () use ( &$requests ): int { return count( array_filter( $requests, static fn( array $call ): bool => 'GET' !== ( $call[1]['method'] ?? 'GET' ) ) ); };
$command = static function ( string $actor ) use ( $ids, $key_pair ): array|WP_Error {
    $raw = wp_json_encode( array( 'type' => 2, 'application_id' => $ids['application'], 'guild_id' => $ids['guild'], 'member' => array( 'user' => array( 'id' => $ids[ $actor ] ) ), 'data' => array( 'type' => 1, 'name' => 'buy' ) ) ); $stamp = (string) time();
    return TC_Billing_Discord::interaction( $raw, array( 'X-Signature-Timestamp' => $stamp, 'X-Signature-Ed25519' => bin2hex( sodium_crypto_sign_detached( $stamp . $raw, sodium_crypto_sign_secretkey( $key_pair ) ) ) ) );
};
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 1, 3 ); $_SERVER['HTTPS'] = 'on';
try {
    foreach ( array( 'invited' => 'subscriber', 'other' => 'subscriber', 'admin' => 'administrator' ) as $actor => $role ) {
        $uid = $must( wp_insert_user( array( 'user_login' => $tag . '_' . $actor, 'user_pass' => wp_generate_password( 48 ), 'role' => $role ) ) ); $users[ $actor ] = (int) $uid;
        if ( 'admin' !== $actor ) {
            add_option( '_tc_discord_binding_' . $ids[ $actor ], $uid, '', false ); update_user_meta( $uid, '_tc_discord_id', $ids[ $actor ] );
            update_user_meta( $uid, '_tc_discord_token', $encrypt( wp_json_encode( array( 'access_token' => 'synthetic-pilot-' . $actor, 'refresh_token' => 'synthetic-refresh-' . $actor, 'expires' => time() + HOUR_IN_SECONDS ) ) ) );
        }
    }
    update_option( 'timonconfigs_discord', $oauth, false ); update_option( 'timonconfigs_billing', $pilot, false ); wp_set_current_user( $users['admin'] );
    $assert( TC_Discord::is_ready() && TC_Billing::mode_allowed( 'live' ) && TC_Billing::direct_enabled(), 'Confirmed one-account pilot enables Live without a public approval constant or fake Test acceptance.' );
    $assert( $ids['guild'] === TC_Billing::setting( 'discord_guild_id', 'live' ) && $ids['monthly'] === TC_Billing::setting( 'discord_monthly_role_id', 'live' ), 'Live pilot reuses the actual existing OAuth guild and role settings.' );
    $before = count( $requests ); $bad_nonce = $admin_setup(); $bad_nonce['_wpnonce'] = 'invalid';
    $assert( $error_is( TC_Billing::admin_perform( $bad_nonce ), 'tc_billing_forbidden' ) && $before === count( $requests ), 'Price setup rejects bad nonce before provider HTTP.' );
    wp_set_current_user( $users['other'] ); $assert( $error_is( TC_Billing::admin_perform( $admin_setup() ), 'tc_billing_forbidden' ) && $before === count( $requests ), 'A member cannot inspect or save merchant setup.' );
    wp_set_current_user( $users['admin'] ); $host = 'http://setup.example.test';
    $assert( $error_is( TC_Billing::admin_perform( $admin_setup() ), 'tc_billing_https' ) && $before === count( $requests ), 'Remote HTTP setup is refused before provider HTTP.' ); $host = 'https://localhost:8080';
    $before_config = get_option( 'timonconfigs_billing' ); $fault = 'bad-lifetime';
    $assert( is_wp_error( TC_Billing::admin_perform( $admin_setup() ) ) && $before_config === get_option( 'timonconfigs_billing' ), 'A bad second price preserves every previously stored setting; no partial Monthly save.' );
    $fault = ''; $assert( true === $must( TC_Billing::admin_perform( $admin_setup() ) ), 'Administrator setup derives both canonical product IDs from the two entered prices.' );
    foreach ( array( 'monthly' => $monthly_price, 'lifetime' => $lifetime_price ) as $plan => $price ) { $assert( $price === TC_Billing::setting( 'stripe_' . $plan . '_price_id', 'live' ) && $prices[ $price ]['product'] === TC_Billing::setting( 'stripe_' . $plan . '_product_id', 'live' ), 'Canonical price/product pair saved: ' . $plan ); }
    $assert( 0 === $write_count(), 'Price setup never creates merchant products, subscriptions or charges.' );
    $configured = TC_Billing::config();
    foreach ( array( 'live_pilot_enabled', 'pilot_real_charges_confirmed', 'pilot_discord_id' ) as $required ) {
        $bad = $configured; $bad[ $required ] = 'pilot_discord_id' === $required ? '' : false; update_option( 'timonconfigs_billing', $bad, false );
        $assert( ! TC_Billing::mode_allowed( 'live' ) && ! TC_Billing::direct_enabled(), 'Missing pilot confirmation or identity cannot unlock Live: ' . $required );
    }
    update_option( 'timonconfigs_billing', array_merge( $configured, array( 'routing' => 'direct' ) ), false );
    $assert( ! TC_Billing::direct_enabled(), 'Pilot approval cannot be reused as public Live checkout approval.' ); update_option( 'timonconfigs_billing', $configured, false );
    wp_set_current_user( 0 ); $before = count( $requests );
    $assert( $error_is( TC_Billing::perform( $purchase() ), 'tc_billing_nonce' ) && ! str_contains( TC_Billing_UI::checkout( 'lifetime' ), '<form' ) && $before === count( $requests ), 'Guest cannot start a payment or receive a purchase form.' );
    wp_set_current_user( $users['admin'] );
    $assert( $error_is( TC_Billing::perform( $purchase() ), 'tc_billing_identity' ) && ! TC_Billing::current_user_can_purchase() && $before === count( $requests ), 'An unbound WordPress administrator cannot buy as the invited Discord identity.' );
    wp_set_current_user( $users['other'] );
    $forged = array_merge( $purchase(), array( 'user_id' => $users['invited'], 'discord_id' => $ids['invited'], 'pilot_discord_id' => $ids['invited'] ) );
    $assert( $error_is( TC_Billing::perform( $forged ), 'tc_billing_pilot' ) && ! TC_Billing::actor_can_purchase( $ids['other'] ) && $before === count( $requests ), 'A valid noninvited OAuth member cannot forge the invitation with POST fields.' );
    $assert( array() === TC_Billing_Store::orders_for_user( $users['other'], 'live' ) && ! str_contains( TC_Billing_UI::checkout( 'lifetime' ), '<form' ), 'Rejected member receives no order or payment form.' );
    $denied_command = $command( 'other' );
    $assert( is_array( $denied_command ) && 64 === $denied_command['data']['flags'] && ! str_contains( $denied_command['data']['content'], 'tc_billing_intent' ), 'A signed noninvited Discord command is private and cannot issue an intent.' );
    $allowed_command = $command( 'invited' );
    $assert( is_array( $allowed_command ) && preg_match( '/tc_billing_intent=([a-f0-9]{64})/', $allowed_command['data']['content'], $match ) === 1, 'The signed invited Discord actor can request an actor-bound website intent.' ); $intents[] = $match[1];
    wp_set_current_user( $users['invited'] );
    $checkout_html = TC_Billing_UI::checkout( 'lifetime' );
    $assert( TC_Billing::current_user_can_purchase() && str_contains( $checkout_html, '<form' ) && str_contains( $checkout_html, 'Payments are real' ), 'Only the invited OAuth actor immediately receives the form and explicit real-payment notice.' );
    $assert( str_contains( $checkout_html, 'type="hidden" name="consent" value="1"' ) && ! str_contains( $checkout_html, 'type="checkbox"' ) && str_contains( $checkout_html, 'Stripe' ), 'A deliberate provider button POST records consent without an extra checkout checkbox.' );
    $assert( str_contains( $checkout_html, '/terms-of-service/' ) && str_contains( $checkout_html, '/refund-policy/' ) && str_contains( $checkout_html, '/privacy-policy/' ), 'Price and terms remain visible beside provider purchase actions.' );
    $before = count( $requests );
    update_option( 'timonconfigs_billing', array_merge( $configured, array( 'paypal_enabled' => true, 'stripe_test_confirmed' => true, 'live_paypal_webhook_id' => 'WH-SYNTHETIC-PILOT' ) ), false );
    $both_html = TC_Billing_UI::checkout( 'lifetime' );
    $assert( str_contains( $both_html, 'Pay with Stripe' ) && str_contains( $both_html, 'Pay with PayPal' ) && ! str_contains( $both_html, 'type="checkbox"' ) && $before === count( $requests ), 'Both configured methods appear immediately without provider requests, checkbox selection or an order.' );
    $without_delivery = array_merge( $configured, array( 'role_sync_enabled' => false ) ); update_option( 'timonconfigs_billing', $without_delivery, false );
    $unavailable_html = TC_Billing_UI::checkout( 'lifetime' );
    $assert( ! str_contains( $unavailable_html, '<form' ) && str_contains( $unavailable_html, 'tc-billing-notice' ), 'Missing Live delivery prerequisites produce a visible notice instead of a silently empty checkout.' );
    $assert( ! str_contains( $unavailable_html, 'Administrator: payment setup' ) && ! str_contains( $unavailable_html, 'Open billing settings' ), 'Ordinary members cannot see detailed administrator setup diagnostics.' );
    $invited_member = wp_get_current_user(); $invited_member->add_cap( 'manage_options' );
    $diagnostic_html = TC_Billing_UI::checkout( 'lifetime' ); $invited_member->remove_cap( 'manage_options' );
    $assert( str_contains( $diagnostic_html, 'Administrator: payment setup' ) && str_contains( $diagnostic_html, 'Discord VIP role synchronization is disabled.' ) && str_contains( $diagnostic_html, 'Open billing settings' ), 'An invited administrator sees the actual disabled-delivery cause and a direct settings link.' );
    wp_set_current_user( $users['admin'] ); ob_start(); TC_Billing_UI::admin(); $admin_html = (string) ob_get_clean();
    $assert( str_contains( $admin_html, 'role_sync_enabled' ) && str_contains( $admin_html, 'Live' ) && ! str_contains( $admin_html, 'sk_live_localPilotFixture' ) && ! str_contains( $admin_html, 'synthetic-local-pilot-bot' ), 'Administrator receives setup controls without rendering saved provider or bot credentials.' );
    wp_set_current_user( $users['invited'] ); update_option( 'timonconfigs_billing', $configured, false );
    update_option( '_tc_discord_binding_' . $ids['invited'], $users['other'], false );
    $assert( $error_is( TC_Billing::perform( $purchase() ), 'tc_billing_identity' ), 'A conflicting unique Discord binding prevents the invited ID from purchasing.' ); update_option( '_tc_discord_binding_' . $ids['invited'], $users['invited'], false );
    foreach ( array( 'hierarchy' => 'tc_billing_discord_hierarchy', 'bot-unavailable' => 'tc_billing_discord_http', 'pilot-paused' => 'tc_billing_pilot' ) as $failure => $code ) {
        $fault = $failure; $writes_before = $write_count();
        $assert( $error_is( TC_Billing::perform( $purchase() ), $code ) && $writes_before === $write_count() && array() === TC_Billing_Store::orders_for_user( $users['invited'], 'live' ), 'Live VIP delivery preflight fails before order creation or provider writes: ' . $failure );
        update_option( 'timonconfigs_billing', $configured, false );
    }
    $fault = '';
    $checkout = $must( TC_Billing::perform( array_merge( $purchase(), array( 'user_id' => $users['other'], 'discord_id' => $ids['other'], 'amount' => '1' ) ) ) );
    $order = TC_Billing_Store::order( $checkout['order_id'] );
    $assert( $users['invited'] === (int) $order['user_id'] && $ids['invited'] === $order['discord_id'] && 8000 === (int) $order['amount'] && 'eur' === $order['currency'], 'Fake hosted Checkout stores the actual bound actor and immutable Lifetime total.' );
    $assert( 1 === $write_count() && array() === TC_Billing_Store::active( $users['invited'], 'live' ), 'Hosted Checkout creation alone makes no payment grant; its only write is intercepted.' );
    $paused = array_merge( $configured, array( 'routing' => 'disabled', 'role_sync_enabled' => false, 'pilot_discord_id' => $ids['other'] ) ); update_option( 'timonconfigs_billing', $paused, false );
    $assert( ! TC_Billing::direct_enabled() && TC_Billing::provider_ready( 'stripe', 'live' ) && TC_Billing::mode_allowed( 'live' ), 'Pausing purchases and Discord writes preserves financial processing readiness.' );
    $assert( $error_is( TC_Billing::perform( $purchase() ), 'tc_billing_disabled' ), 'Old pilot cannot start another checkout after pause.' );
    $own_action = array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'refresh', 'order_id' => $order['id'] );
    $assert( is_array( $must( TC_Billing::perform( $own_action ) ) ), 'The original buyer can reconcile its own order after invitation changes.' );
    wp_set_current_user( $users['other'] ); $own_action['_wpnonce'] = wp_create_nonce( 'tc_billing_action' );
    $assert( $error_is( TC_Billing::perform( $own_action ), 'tc_billing_order' ), 'The newly invited member still cannot manage the original buyer’s order.' );
    wp_set_current_user( 0 ); $session_id = $order['checkout_id']; $sessions[ $session_id ]['status'] = 'complete'; $sessions[ $session_id ]['payment_status'] = 'paid';
    $raw = wp_json_encode( array( 'id' => $event_id, 'type' => 'checkout.session.completed', 'created' => time(), 'livemode' => true, 'data' => array( 'object' => array( 'id' => $session_id, 'livemode' => true ) ) ) ); $stamp = time();
    $request = new WP_REST_Request( 'POST', '/timonconfigs/v1/billing/stripe/live' ); $request->set_url_params( array( 'provider' => 'stripe', 'mode' => 'live' ) ); $request->set_body( $raw );
    $request->set_header( 'Stripe-Signature', 't=' . $stamp . ',v1=' . hash_hmac( 'sha256', $stamp . '.' . $raw, TIMONCONFIGS_BILLING_LIVE_STRIPE_WEBHOOK_SECRET ) );
    $assert( 200 === TC_Billing::webhook( $request )->get_status(), 'A signed Live webhook needs no login/invitation and is accepted while new sales are paused.' );
    $event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'tc_billing_events WHERE event_id=%s AND provider=%s AND mode=%s', $event_id, 'stripe', 'live' ), ARRAY_A );
    $assert( true === $must( TC_Billing::process_event( $event ) ) && 1 === count( TC_Billing_Store::active( $users['invited'], 'live' ) ), 'Canonical financial reconciliation continues for the original buyer independently of the current invite.' );
    $monthly = $must( TC_Billing_Store::create_order( $users['invited'], $ids['invited'], 'stripe', 'live', 'monthly' ) );
    $must( TC_Billing_Store::update_order( $monthly['id'], array( 'status' => 'active', 'customer_id' => 'cus_' . $tag . '_monthly', 'subscription_id' => 'sub_' . $tag ) ) ); $monthly = TC_Billing_Store::order( $monthly['id'] );
    $monthly_contract = array( 'id' => 'sub_' . $tag, 'livemode' => true, 'customer' => $monthly['customer_id'], 'metadata' => $metadata( $monthly ), 'status' => 'active', 'cancel_at_period_end' => false );
    wp_set_current_user( $users['invited'] );
    $canceled = $must( TC_Billing::perform( array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'cancel', 'order_id' => $monthly['id'], 'confirm_cancel' => '1' ) ) );
    $assert( is_array( $canceled ) && true === $monthly_contract['cancel_at_period_end'] && 2 === $write_count(), 'Original customer can cancel a real-shaped owned contract after invitation removal; cancellation HTTP is also mocked.' );
    wp_set_current_user( $users['admin'] ); $sanitized = TC_Billing::sanitize( array_merge( $paused, array( 'mode' => 'test', 'routing' => 'direct', 'live_pilot_enabled' => false, 'pilot_real_charges_confirmed' => false, 'pilot_discord_id' => '' ) ) );
    $assert( 'live' === $sanitized['mode'] && 'disabled' === $sanitized['routing'] && ! empty( $sanitized['live_pilot_enabled'] ) && $ids['other'] === $sanitized['pilot_discord_id'], 'Live records prevent switching away from their worker environment; new purchases are paused instead.' );
    $host = 'http://setup.example.test'; $_SERVER['HTTPS'] = 'off';
    $insecure = TC_Billing::sanitize( array_merge( $paused, array( 'mode' => 'test', 'routing' => 'direct', 'live_pilot_enabled' => false, 'pilot_real_charges_confirmed' => false, 'pilot_discord_id' => '' ) ) );
    $assert( $insecure === $paused, 'An insecure request cannot switch an existing Live configuration away from its financial maintenance environment.' );
    $host = 'https://localhost:8080'; $_SERVER['HTTPS'] = 'on';
    $assert( 2 === $write_count(), 'No provider writes occurred beyond the exact fake checkout and fake cancellation.' );
    WP_CLI::success( "Live pilot integration: $checks assertions; actual local WordPress, signed events, all external HTTP mocked, no real charge or role mutation." );
} finally {
    remove_filter( 'pre_http_request', $http, 1 ); remove_filter( 'option_home', $https );
    foreach ( $intents as $token ) { delete_transient( 'tc_billing_intent_' . hash( 'sha256', $token ) ); }
    foreach ( $users as $uid ) {
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'tc_billing_events WHERE order_id IN (SELECT id FROM ' . $wpdb->prefix . 'tc_billing_orders WHERE user_id=%d)', $uid ) );
        foreach ( array( 'entitlements', 'orders', 'sync_jobs', 'owned_roles' ) as $table ) { $wpdb->delete( $wpdb->prefix . 'tc_billing_' . $table, array( 'user_id' => $uid ) ); }
        delete_option( '_tc_discord_lock_' . $uid ); delete_transient( 'tc_billing_feedback_' . $uid ); delete_transient( 'tc_billing_admin_' . $uid );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid );
    }
    $wpdb->delete( $wpdb->prefix . 'tc_billing_events', array( 'event_id' => $event_id, 'provider' => 'stripe', 'mode' => 'live' ) );
    foreach ( array( 'invited', 'other' ) as $actor ) { delete_option( '_tc_discord_binding_' . $ids[ $actor ] ); }
    foreach ( $old_options as $option => $value ) { null === $value ? delete_option( $option ) : update_option( $option, $value, false ); }
    false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
    $_GET = $old_get; if ( null === $old_https ) { unset( $_SERVER['HTTPS'] ); } else { $_SERVER['HTTPS'] = $old_https; }
    wp_set_current_user( $old_user );
}
