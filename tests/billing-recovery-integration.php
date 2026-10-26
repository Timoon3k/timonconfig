<?php
/** Disposable localhost WordPress; all provider and Discord traffic is intercepted. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
foreach ( array( 'TIMONCONFIGS_BILLING_TEST_STRIPE_SECRET_KEY', 'TIMONCONFIGS_BILLING_TEST_STRIPE_WEBHOOK_SECRET', 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID', 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_SECRET' ) as $name ) { if ( defined( $name ) ) { throw new RuntimeException( 'Fresh CLI process without actual credentials required.' ); } }
define( 'TIMONCONFIGS_BILLING_TEST_STRIPE_SECRET_KEY', 'sk_test_localRecoveryFixture' );
define( 'TIMONCONFIGS_BILLING_TEST_STRIPE_WEBHOOK_SECRET', 'whsec_localRecoveryFixture' );
define( 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID', 'synthetic-recovery-client' );
define( 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_SECRET', 'synthetic-recovery-secret' );
global $wpdb;
$checks = 0; $users = array(); $requests = array(); $sessions = array(); $contracts = array(); $fault = ''; $switch_order = null; $paypal_creations = 0;
$tag = 'recovery_' . bin2hex( random_bytes( 6 ) ); $host = 'https://localhost:8080';
$ids = array( 'close' => '980000000000000101', 'other' => '980000000000000102', 'badprice' => '980000000000000103', 'unknown' => '980000000000000104', 'race' => '980000000000000105', 'owner' => '980000000000000106', 'active' => '980000000000000107', 'application' => '980000000000000108', 'guild' => '980000000000000109', 'free' => '980000000000000110', 'monthly' => '980000000000000111', 'lifetime' => '980000000000000112', 'switch' => '980000000000000113' );
foreach ( array( 'close', 'other', 'badprice', 'unknown', 'race', 'owner', 'active', 'switch' ) as $actor ) { if ( false !== get_option( '_tc_discord_binding_' . $ids[ $actor ] ) ) { throw new RuntimeException( 'Existing binding collision; refusing to replace an account.' ); } }
$old_options = array(); foreach ( array( 'timonconfigs_billing', 'timonconfigs_discord', 'cron', 'timonconfigs_billing_last_tick', 'timonconfigs_billing_worker_error' ) as $name ) { $old_options[ $name ] = get_option( $name, null ); }
$old_user = get_current_user_id(); $old_get = $_GET; $old_post = $_POST; $old_https = $_SERVER['HTTPS'] ?? null; $old_method = $_SERVER['REQUEST_METHOD'] ?? null; $old_failure = get_transient( 'tc_discord_last_failure' );
$paypal_cache_key = 'tc_pp_token_test_' . substr( hash( 'sha256', TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID . ':' . TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_SECRET ), 0, 24 ); $old_paypal_token = get_transient( $paypal_cache_key );
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Billing recovery: ' . $message ); } ++$checks; };
$must = static function ( mixed $value ): mixed { if ( is_wp_error( $value ) ) { throw new RuntimeException( 'Unexpected recovery error: ' . $value->get_error_code() ); } return $value; };
$error_is = static fn( mixed $value, string $code ): bool => is_wp_error( $value ) && $code === $value->get_error_code();
$encrypt = static fn( string $value ): string => ( new ReflectionMethod( TC_Discord::class, 'encrypt' ) )->invoke( null, $value );
$https = static function () use ( &$host ): string { return $host; };
$response = static fn( array $body, int $status = 200 ): array => array( 'response' => array( 'code' => $status ), 'headers' => array(), 'body' => wp_json_encode( $body ) );
$oauth = array( 'enabled' => 1, 'staging_confirmed' => 1, 'client_id' => $ids['application'], 'guild_id' => $ids['guild'], 'free_role' => $ids['free'], 'monthly_role' => $ids['monthly'], 'lifetime_role' => $ids['lifetime'], 'owner_ids' => $ids['owner'], 'secret_encrypted' => $encrypt( 'synthetic-recovery-oauth-secret' ) );
$billing = array( 'mode' => 'test', 'routing' => 'direct', 'role_sync_enabled' => false, 'paypal_enabled' => false );
$prices = array();
foreach ( array( 'monthly' => 2999, 'lifetime' => 8000 ) as $plan => $amount ) {
    $price = 'price_' . $tag . '_' . $plan; $product = 'prod_' . $tag . '_' . $plan;
    $billing[ 'test_stripe_' . $plan . '_price_id' ] = $price; $billing[ 'test_stripe_' . $plan . '_product_id' ] = $product;
    $prices[ $price ] = array( 'id' => $price, 'livemode' => false, 'active' => true, 'product' => $product, 'currency' => 'eur', 'unit_amount' => $amount, 'billing_scheme' => 'per_unit', 'type' => 'monthly' === $plan ? 'recurring' : 'one_time', 'recurring' => 'monthly' === $plan ? array( 'interval' => 'month', 'interval_count' => 1, 'usage_type' => 'licensed' ) : null );
}
$metadata = static fn( array $order ): array => array( 'tc_order_id' => $order['id'], 'tc_user_id' => (string) $order['user_id'], 'tc_discord_id' => $order['discord_id'], 'tc_plan' => $order['plan'], 'tc_mode' => $order['mode'] );
$session = static function ( array $order, string $suffix ) use ( $prices, $billing, $metadata, $tag ): array {
    $price = $prices[ $billing[ 'test_stripe_' . $order['plan'] . '_price_id' ] ]; $id = 'cs_test_' . $tag . '_' . $suffix;
    return array( 'id' => $id, 'livemode' => false, 'metadata' => $metadata( $order ), 'client_reference_id' => $order['id'], 'mode' => 'monthly' === $order['plan'] ? 'subscription' : 'payment', 'currency' => 'eur', 'amount_total' => (int) $order['amount'], 'customer' => 'cus_' . $tag . '_' . $suffix, 'subscription' => null, 'payment_intent' => null, 'status' => 'open', 'payment_status' => 'unpaid', 'url' => 'https://checkout.stripe.com/c/pay/' . $id, 'line_items' => array( 'has_more' => false, 'data' => array( array( 'quantity' => 1, 'amount_total' => (int) $order['amount'], 'price' => $price ) ) ) );
};
$http = static function ( mixed $pre, array $args, string $url ) use ( &$requests, &$sessions, &$contracts, &$fault, &$switch_order, &$paypal_creations, $response, $session, $prices, $ids, $tag ): array|WP_Error {
    $requests[] = array( $url, $args ); $method = $args['method'] ?? 'GET';
    if ( 0 !== ( $args['redirection'] ?? null ) || true !== ( $args['sslverify'] ?? null ) ) { throw new RuntimeException( 'Unsafe transport blocked.' ); }
    if ( str_starts_with( $url, 'https://discord.com/api/v10/' ) ) {
        $auth = $args['headers']['Authorization'] ?? ''; $prefix = 'Bearer synthetic-recovery-';
        if ( 'GET' !== $method || ! str_starts_with( $auth, $prefix ) || ! str_ends_with( $url, '/users/@me/guilds/' . $ids['guild'] . '/member' ) ) { throw new RuntimeException( 'Unexpected Discord request or mutation blocked.' ); }
        $actor = substr( $auth, strlen( $prefix ) ); if ( ! isset( $ids[ $actor ] ) ) { throw new RuntimeException( 'Unexpected synthetic actor.' ); }
        return $response( array( 'user' => array( 'id' => $ids[ $actor ] ), 'roles' => array( $ids['free'] ) ) );
    }
    if ( str_starts_with( $url, 'https://api-m.sandbox.paypal.com/' ) ) {
        $path = wp_parse_url( $url, PHP_URL_PATH ); $authorization = $args['headers']['Authorization'] ?? '';
        if ( 'POST' === $method && '/v1/oauth2/token' === $path && 'Basic ' . base64_encode( TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID . ':' . TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_SECRET ) === $authorization ) { return $response( array( 'access_token' => 'synthetic-recovery-paypal-token', 'token_type' => 'Bearer', 'expires_in' => 3600 ) ); }
        if ( 'POST' === $method && '/v2/checkout/orders' === $path && 'Bearer synthetic-recovery-paypal-token' === $authorization ) {
            if ( ! is_array( $switch_order ) || 'canceled' !== TC_Billing_Store::order( $switch_order['id'] )['status'] || 'expired' !== ( $sessions[ $switch_order['checkout_id'] ]['status'] ?? '' ) ) { throw new RuntimeException( 'New provider creation occurred before exact remote and local closure of the original Stripe checkout.' ); }
            $body = json_decode( $args['body'] ?? '', true ); $order = TC_Billing_Store::order( $body['purchase_units'][0]['reference_id'] ?? '' );
            if ( ! $order || $order['user_id'] !== $switch_order['user_id'] || $order['discord_id'] !== $switch_order['discord_id'] || 'paypal' !== $order['provider'] || 'lifetime' !== $order['plan'] ) { throw new RuntimeException( 'PayPal switch did not preserve the actual customer and offer.' ); }
            ++$paypal_creations; $id = 'ORDER-RECOVERY-' . strtoupper( substr( $tag, 9 ) );
            return $response( array( 'id' => $id, 'intent' => 'CAPTURE', 'status' => 'CREATED', 'purchase_units' => $body['purchase_units'], 'links' => array( array( 'rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=' . $id ) ) ), 201 );
        }
        throw new RuntimeException( 'Unexpected PayPal endpoint, capture or mutation blocked.' );
    }
    if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/' ) || 'Bearer sk_test_localRecoveryFixture' !== ( $args['headers']['Authorization'] ?? '' ) || '2024-06-20' !== ( $args['headers']['Stripe-Version'] ?? '' ) ) { throw new RuntimeException( 'Unexpected remote provider or credentials blocked.' ); }
    $path = substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( '/v1/' ) );
    if ( 'GET' === $method && str_starts_with( $path, 'prices/' ) && isset( $prices[ substr( $path, 7 ) ] ) ) { $price = $prices[ substr( $path, 7 ) ]; if ( 'price-read' === $fault ) { $price['unit_amount'] = 1; } return $response( $price ); }
    if ( 'GET' === $method && str_starts_with( $path, 'products/prod_' . $tag . '_' ) ) { return $response( array( 'id' => substr( $path, 9 ), 'livemode' => false, 'active' => true ) ); }
    if ( 'POST' === $method && 'checkout/sessions' === $path ) {
        if ( 'network-create' === $fault ) { return new WP_Error( 'synthetic_transport', 'PRIVATE_PROVIDER_RESPONSE' ); }
        parse_str( $args['body'], $body ); $order = TC_Billing_Store::order( $body['client_reference_id'] ?? '' );
        if ( ! $order || ( $body['metadata'] ?? array() ) !== ( $session( $order, 'validation' )['metadata'] ) ) { throw new RuntimeException( 'Unexpected checkout binding.' ); }
        $new = $session( $order, 'created_' . count( $sessions ) ); $sessions[ $new['id'] ] = $new; return $response( $new );
    }
    if ( 'GET' === $method && str_starts_with( $path, 'checkout/sessions/' ) && isset( $sessions[ substr( $path, 18 ) ] ) ) { return $response( $sessions[ substr( $path, 18 ) ] ); }
    if ( 'POST' === $method && preg_match( '~^checkout/sessions/([A-Za-z0-9_]+)/expire$~D', $path, $match ) && isset( $sessions[ $match[1] ] ) ) {
        if ( 'paid-race' === $fault ) { $sessions[ $match[1] ]['status'] = 'complete'; $sessions[ $match[1] ]['payment_status'] = 'paid'; return $response( array( 'error' => array( 'type' => 'invalid_request_error', 'message' => 'PRIVATE_PROVIDER_RESPONSE' ) ), 400 ); }
        $sessions[ $match[1] ]['status'] = 'expired'; return $response( $sessions[ $match[1] ] );
    }
    if ( str_starts_with( $path, 'subscriptions/' ) && isset( $contracts[ substr( $path, 14 ) ] ) ) {
        $id = substr( $path, 14 );
        if ( 'GET' === $method ) { return $response( $contracts[ $id ] ); }
        if ( 'POST' === $method ) { parse_str( $args['body'], $body ); if ( array( 'cancel_at_period_end' => 'true' ) !== $body ) { throw new RuntimeException( 'Unexpected contract mutation.' ); } $contracts[ $id ]['cancel_at_period_end'] = true; return $response( $contracts[ $id ] ); }
    }
    throw new RuntimeException( 'Unexpected Stripe endpoint blocked.' );
};
$writes = static function () use ( &$requests ): int { return count( array_filter( $requests, static fn( array $call ): bool => 'GET' !== ( $call[1]['method'] ?? 'GET' ) ) ); };
$purchase = static fn( string $plan = 'monthly' ): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'checkout', 'plan' => $plan, 'provider' => 'stripe', 'consent' => '1' );
$close = static fn( array $order ): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'close_checkout', 'order_id' => $order['id'], 'plan' => $order['plan'], 'confirm_close' => '1' );
$admin = static fn( string $op, array $order ): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_admin' ), 'operation' => $op, 'mode' => $order['mode'], 'order_id' => $order['id'], 'confirm_order' => '1' );
$make_order = static function ( string $actor, string $suffix, string $plan = 'monthly' ) use ( &$sessions, &$users, $ids, $session, $must ): array {
    $order = $must( TC_Billing_Store::create_order( $users[ $actor ], $ids[ $actor ], 'stripe', 'test', $plan ) ); $canonical = $session( $order, $suffix ); $sessions[ $canonical['id'] ] = $canonical;
    $must( TC_Billing_Store::update_order( $order['id'], array( 'checkout_id' => $canonical['id'], 'customer_id' => $canonical['customer'], 'status' => 'checkout' ) ) ); return TC_Billing_Store::order( $order['id'] );
};
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 1, 3 ); $_SERVER['HTTPS'] = 'on';
try {
    delete_transient( $paypal_cache_key );
    foreach ( array( 'close', 'other', 'badprice', 'unknown', 'race', 'owner', 'active', 'switch', 'admin' ) as $actor ) {
        $users[ $actor ] = (int) $must( wp_insert_user( array( 'user_login' => $tag . '_' . $actor, 'user_pass' => wp_generate_password( 48 ), 'role' => 'admin' === $actor ? 'administrator' : 'subscriber' ) ) );
        if ( 'admin' !== $actor ) { add_option( '_tc_discord_binding_' . $ids[ $actor ], $users[ $actor ], '', false ); update_user_meta( $users[ $actor ], '_tc_discord_id', $ids[ $actor ] ); update_user_meta( $users[ $actor ], '_tc_discord_token', $encrypt( wp_json_encode( array( 'access_token' => 'synthetic-recovery-' . $actor, 'refresh_token' => 'synthetic-refresh-' . $actor, 'expires' => time() + HOUR_IN_SECONDS ) ) ) ); }
    }
    update_option( 'timonconfigs_discord', $oauth, false ); update_option( 'timonconfigs_billing', $billing, false );
    $order = $make_order( 'close', 'self' ); wp_set_current_user( $users['other'] ); $before = count( $requests );
    $assert( is_wp_error( TC_Billing::perform( $close( $order ) ) ) && $before === count( $requests ) && 'checkout' === TC_Billing_Store::order( $order['id'] )['status'], 'A customer cannot close another customer’s unpaid checkout.' );
    wp_set_current_user( $users['close'] ); $invalid = $close( $order ); $invalid['_wpnonce'] = 'invalid';
    $assert( $error_is( TC_Billing::perform( $invalid ), 'tc_billing_nonce' ) && $before === count( $requests ), 'Invalid customer nonce is rejected before provider reads or writes.' );
    $unconfirmed = $close( $order ); $unconfirmed['confirm_close'] = '0';
    $assert( is_wp_error( TC_Billing::perform( $unconfirmed ) ) && $before === count( $requests ), 'Closing an unpaid checkout requires the deliberate confirmation action.' );
    $result = $must( TC_Billing::perform( $close( $order ) ) );
    $assert( is_array( $result ) && 'expired' === $sessions[ $order['checkout_id'] ]['status'] && 'canceled' === TC_Billing_Store::order( $order['id'] )['status'], 'Customer close expires the provider session before recording its local canceled state.' );
    $assert( array() === $must( TC_Billing_Store::checkout_attempts( $users['close'], 'test', 'monthly' ) ) && array() === TC_Billing_Store::active( $users['close'], 'test' ), 'A proven unpaid close releases the blocker without granting or deleting paid access.' );
    $restarted = $must( TC_Billing::perform( $purchase() ) );
    $assert( isset( $restarted['order_id'] ) && $restarted['order_id'] !== $order['id'] && str_starts_with( $restarted['redirect'], 'https://checkout.stripe.com/' ), 'The buyer can start a fresh checkout after proven remote closure.' );

    $paypal_enabled = array_merge( $billing, array( 'paypal_enabled' => true, 'stripe_test_confirmed' => true, 'test_paypal_webhook_id' => 'WH-SYNTHETIC-RECOVERY' ) ); update_option( 'timonconfigs_billing', $paypal_enabled, false );
    $switch_order = $make_order( 'switch', 'switch', 'lifetime' ); wp_set_current_user( $users['switch'] ); $switch = array_merge( $purchase( 'lifetime' ), array( 'provider' => 'paypal', 'user_id' => $users['admin'], 'discord_id' => $ids['owner'] ) );
    $switched = $must( TC_Billing::perform( $switch ) ); $paypal_order = TC_Billing_Store::order( $switched['order_id'] );
    $assert( 1 === $paypal_creations && 'canceled' === TC_Billing_Store::order( $switch_order['id'] )['status'] && 'expired' === $sessions[ $switch_order['checkout_id'] ]['status'], 'Choosing PayPal automatically closes a provably unpaid Stripe session before creating the new provider order.' );
    $assert( $paypal_order['id'] !== $switch_order['id'] && 'paypal' === $paypal_order['provider'] && (int) $paypal_order['user_id'] === $users['switch'] && $paypal_order['discord_id'] === $ids['switch'] && 8000 === (int) $paypal_order['amount'] && str_starts_with( $switched['redirect'], 'https://www.sandbox.paypal.com/checkoutnow?' ), 'Provider switching uses a fresh PayPal order bound to the actual buyer and immutable EUR80 offer.' );
    $assert( array() === TC_Billing_Store::active( $users['switch'], 'test' ) && 1 === count( $must( TC_Billing_Store::checkout_attempts( $users['switch'], 'test', 'lifetime' ) ) ), 'An automatic provider switch retains only one unresolved checkout and grants no paid membership.' ); update_option( 'timonconfigs_billing', $billing, false );

    $managed = $make_order( 'other', 'managed' ); wp_set_current_user( $users['owner'] ); $before = count( $requests );
    $assert( TC_Discord::can_manage_configs_local() && ! current_user_can( 'manage_options' ), 'The synthetic Owner can publish configs but has no WordPress financial administration capability.' );
    $assert( $error_is( TC_Billing::admin_perform( $admin( 'order_close', $managed ) ), 'tc_billing_forbidden' ) && $before === count( $requests ), 'Owner publishing permission cannot close another customer’s financial order.' );
    wp_set_current_user( $users['admin'] ); $bad_admin = $admin( 'order_close', $managed ); $bad_admin['_wpnonce'] = 'invalid';
    $assert( $error_is( TC_Billing::admin_perform( $bad_admin ), 'tc_billing_forbidden' ) && $before === count( $requests ), 'Administrative order management rejects an invalid nonce before remote activity.' );
    $no_confirm = $admin( 'order_close', $managed ); $no_confirm['confirm_order'] = '0';
    $assert( is_wp_error( TC_Billing::admin_perform( $no_confirm ) ) && $before === count( $requests ), 'Administrator close requires explicit confirmation.' );
    $wrong_mode = $admin( 'order_close', $managed ); $wrong_mode['mode'] = 'live';
    $assert( is_wp_error( TC_Billing::admin_perform( $wrong_mode ) ) && $before === count( $requests ), 'Administrative operations cannot reinterpret a stored Test order as Live.' );
    $host = 'http://billing.example.test'; $_SERVER['HTTPS'] = 'off';
    $assert( is_wp_error( TC_Billing::admin_perform( $admin( 'order_close', $managed ) ) ) && $before === count( $requests ), 'Remote HTTP cannot authorize a financial management action.' ); $host = 'https://localhost:8080'; $_SERVER['HTTPS'] = 'on';
    $assert( ! is_wp_error( TC_Billing::admin_perform( $admin( 'order_refresh', $managed ) ) ) && 'checkout' === TC_Billing_Store::order( $managed['id'] )['status'], 'An unbound WordPress administrator can queue customer reconciliation without fabricating a new payment status.' );
    $assert( ! is_wp_error( TC_Billing::admin_perform( $admin( 'order_close', $managed ) ) ) && 'canceled' === TC_Billing_Store::order( $managed['id'] )['status'], 'An authorized administrator can close another member’s provably unpaid session.' );
    $audits = get_user_meta( $users['admin'], '_tc_billing_order_audit' ); $close_audits = array_values( array_filter( $audits, static fn( mixed $item ): bool => is_array( $item ) && ( $item['order_id'] ?? '' ) === $managed['id'] && ( $item['action'] ?? '' ) === 'order_close' && ( $item['status'] ?? '' ) === 'accepted' ) );
    $assert( 1 === count( $close_audits ) && (int) $close_audits[0]['target_user'] === $users['other'] && 'test' === $close_audits[0]['mode'] && ! empty( $close_audits[0]['at'] ), 'Accepted administrator closure records the exact order, target customer, mode and timestamp under the acting administrator.' );

    wp_set_current_user( $users['badprice'] ); $fault = 'price-read'; $before = $writes(); $bad = TC_Billing::perform( $purchase() ); $bad_orders = TC_Billing_Store::orders_for_user( $users['badprice'], 'test' );
    $assert( is_wp_error( $bad ) && $before === $writes() && 1 === count( $bad_orders ) && 'failed' === $bad_orders[0]['status'], 'A new local attempt that fails before any creation POST is marked failed instead of blocking other payment methods.' );
    $assert( array() === $must( TC_Billing_Store::checkout_attempts( $users['badprice'], 'test', 'monthly' ) ), 'Proven pre-provider failure does not remain an unfinished checkout.' ); $fault = '';
    $retry = $must( TC_Billing::perform( $purchase() ) );
    $assert( $retry['order_id'] !== $bad_orders[0]['id'], 'Correcting a price setup problem permits a new independent checkout.' );
    wp_set_current_user( $users['unknown'] ); $fault = 'network-create'; $network = TC_Billing::perform( $purchase() ); $uncertain = TC_Billing_Store::orders_for_user( $users['unknown'], 'test' );
    $assert( is_wp_error( $network ) && 1 === count( $uncertain ) && 'pending' === $uncertain[0]['status'] && empty( $uncertain[0]['checkout_id'] ), 'An unknown provider POST response remains pending; missing IDs do not prove no session exists.' );
    $fault = 'price-read'; $before = $writes(); $again = TC_Billing::perform( $purchase() );
    $assert( is_wp_error( $again ) && $before === $writes() && 'pending' === TC_Billing_Store::order( $uncertain[0]['id'] )['status'], 'A later read-only price error cannot release an older uncertain provider attempt.' );
    $fault = ''; $before = $writes();
    $assert( is_wp_error( TC_Billing::perform( $close( $uncertain[0] ) ) ) && $before === $writes() && 'pending' === TC_Billing_Store::order( $uncertain[0]['id'] )['status'], 'Unknown missing-session attempts cannot be closed by blind local status changes.' );

    $raced = $make_order( 'race', 'race' ); wp_set_current_user( $users['race'] ); $fault = 'paid-race'; $racing = TC_Billing::perform( $close( $raced ) ); $fault = '';
    $assert( is_wp_error( $racing ) && 'complete' === $sessions[ $raced['checkout_id'] ]['status'] && 'checkout' === TC_Billing_Store::order( $raced['id'] )['status'], 'A payment completing during expiration remains unresolved instead of being falsely canceled locally.' );
    $paid = $must( TC_Billing_Store::create_order( $users['active'], $ids['active'], 'stripe', 'test', 'monthly' ) );
    $must( TC_Billing_Store::update_order( $paid['id'], array( 'customer_id' => 'cus_' . $tag . '_active', 'subscription_id' => 'sub_' . $tag . '_active', 'status' => 'active' ) ) ); $paid = TC_Billing_Store::order( $paid['id'] );
    $contracts[ $paid['subscription_id'] ] = array( 'id' => $paid['subscription_id'], 'livemode' => false, 'metadata' => $metadata( $paid ), 'customer' => $paid['customer_id'], 'status' => 'active', 'cancel_at_period_end' => false );
    $until = gmdate( 'Y-m-d H:i:s', time() + 10 * DAY_IN_SECONDS );
    $must( TC_Billing_Store::upsert_entitlement( array( 'source' => $tag . ':paid-month', 'user_id' => $users['active'], 'discord_id' => $ids['active'], 'provider' => 'stripe', 'mode' => 'test', 'plan' => 'monthly', 'status' => 'active', 'valid_until' => $until, 'order_id' => $paid['id'], 'external_id' => $paid['subscription_id'] ) ) );
    wp_set_current_user( $users['admin'] ); $before = $writes();
    $assert( is_wp_error( TC_Billing::admin_perform( $admin( 'order_close', $paid ) ) ) && $before === $writes() && 'active' === TC_Billing_Store::order( $paid['id'] )['status'], 'Closing an unpaid attempt cannot masquerade as canceling an active paid contract.' );
    $assert( ! is_wp_error( TC_Billing::admin_perform( $admin( 'order_cancel', $paid ) ) ) && true === $contracts[ $paid['subscription_id'] ]['cancel_at_period_end'], 'Administrator cancel-renewal uses the exact canonical provider contract.' );
    $access = $must( TC_Billing_Store::active( $users['active'], 'test' ) );
    $assert( 1 === count( $access ) && $until === $access[0]['valid_until'] && 'active' === TC_Billing_Store::order( $paid['id'] )['status'], 'Stopping future renewal preserves prepaid entitlement and leaves final financial status to reconciliation.' );
    $assert( ! str_contains( $bad->get_error_message(), 'PRIVATE_PROVIDER_RESPONSE' ) && ! str_contains( $network->get_error_message(), 'PRIVATE_PROVIDER_RESPONSE' ), 'Customer errors never expose raw provider response text.' );
    WP_CLI::success( "Billing recovery integration: $checks assertions; real local WordPress, all HTTP mocked, no actual payment or cancellation." );
} finally {
    remove_filter( 'pre_http_request', $http, 1 ); remove_filter( 'option_home', $https );
    foreach ( $users as $uid ) {
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'tc_billing_events WHERE order_id IN (SELECT id FROM ' . $wpdb->prefix . 'tc_billing_orders WHERE user_id=%d)', $uid ) );
        foreach ( array( 'entitlements', 'orders', 'sync_jobs', 'owned_roles' ) as $table ) { $wpdb->delete( $wpdb->prefix . 'tc_billing_' . $table, array( 'user_id' => $uid ) ); }
        delete_option( '_tc_discord_lock_' . $uid ); delete_transient( 'tc_billing_feedback_' . $uid ); delete_transient( 'tc_billing_admin_' . $uid );
        require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid );
    }
    foreach ( array( 'close', 'other', 'badprice', 'unknown', 'race', 'owner', 'active', 'switch' ) as $actor ) { delete_option( '_tc_discord_binding_' . $ids[ $actor ] ); }
    foreach ( $old_options as $name => $value ) { null === $value ? delete_option( $name ) : update_option( $name, $value, false ); }
    false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
    false === $old_paypal_token ? delete_transient( $paypal_cache_key ) : set_transient( $paypal_cache_key, $old_paypal_token, HOUR_IN_SECONDS );
    $_GET = $old_get; $_POST = $old_post; wp_set_current_user( $old_user );
    if ( null === $old_https ) { unset( $_SERVER['HTTPS'] ); } else { $_SERVER['HTTPS'] = $old_https; }
    if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
}
