<?php
/** Actual WordPress billing services; disposable localhost fixtures and fake HTTP only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
foreach ( array( 'TIMONCONFIGS_BILLING_TEST_STRIPE_SECRET_KEY', 'TIMONCONFIGS_BILLING_TEST_STRIPE_WEBHOOK_SECRET', 'TIMONCONFIGS_BILLING_TEST_DISCORD_BOT_TOKEN', 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID', 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_SECRET', 'TIMONCONFIGS_BILLING_LIVE_APPROVED' ) as $constant ) {
	if ( defined( $constant ) ) { throw new RuntimeException( 'Run in a fresh localhost CLI process without billing credentials or live approval.' ); }
}
define( 'TIMONCONFIGS_BILLING_TEST_STRIPE_SECRET_KEY', 'sk_test_localFacadeFixture' );
define( 'TIMONCONFIGS_BILLING_TEST_STRIPE_WEBHOOK_SECRET', 'whsec_localFacadeFixture' );
define( 'TIMONCONFIGS_BILLING_TEST_DISCORD_BOT_TOKEN', 'synthetic-local-facade-bot' );
define( 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID', 'synthetic-local-facade-client' );
define( 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_SECRET', 'synthetic-local-facade-secret' );
foreach ( array( 'TC_Billing', 'TC_Billing_Store', 'TC_Billing_Stripe', 'TC_Billing_Discord', 'TC_Billing_UI', 'TC_Discord' ) as $class ) { if ( ! class_exists( $class ) ) { throw new RuntimeException( 'Active core billing classes required: ' . $class ); } }
if ( ! function_exists( 'add_settings_error' ) ) { require_once ABSPATH . 'wp-admin/includes/template.php'; }

global $wpdb;
$checks = 0; $users = array(); $posts = array(); $requests = array(); $checkout_posts = array();
$sessions = array(); $payments = array(); $charges = array(); $fault = ''; $event_ids = array();
$tag = 'facade_' . bin2hex( random_bytes( 6 ) );
$ids = array( 'user' => '950000000000000101', 'other' => '950000000000000102', 'bot' => '950000000000000103', 'application' => '950000000000000104', 'guild' => '950000000000000105', 'free' => '950000000000000106', 'monthly' => '950000000000000107', 'lifetime' => '950000000000000108', 'staff' => '950000000000000109', 'owner' => '950000000000000110', 'bot_role' => '950000000000000111', 'guild_owner' => '950000000000000112', 'expiry' => '950000000000000113' );
foreach ( array( 'user', 'other', 'expiry' ) as $actor ) { if ( false !== get_option( '_tc_discord_binding_' . $ids[ $actor ] ) ) { throw new RuntimeException( 'Fixture binding collision; no existing account will be changed.' ); } }
$member_roles = array( $ids['free'] );
$old_billing = get_option( 'timonconfigs_billing', null ); $old_oauth = get_option( 'timonconfigs_discord', null );
$old_cron = get_option( 'cron', null ); $old_failure = get_transient( 'tc_discord_last_failure' );
$old_tick = get_option( 'timonconfigs_billing_last_tick', null ); $old_worker_error = get_option( 'timonconfigs_billing_worker_error', null );
$original_user = get_current_user_id(); $old_get = $_GET; $old_post = $_POST; $old_method = $_SERVER['REQUEST_METHOD'] ?? null;
$tables = array(); foreach ( array( 'orders', 'entitlements', 'events', 'sync_jobs', 'owned_roles', 'leases' ) as $suffix ) { $tables[ $suffix ] = $wpdb->prefix . 'tc_billing_' . $suffix; }
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void { if ( ! $condition ) { throw new RuntimeException( 'Billing integration: ' . $message ); } ++$checks; };
$must = static function ( $result ) { if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Unexpected service error: ' . $result->get_error_code() ); } return $result; };
$error_is = static fn( $result, string $code ): bool => is_wp_error( $result ) && $code === $result->get_error_code();
$encrypt = static fn( string $value ): string => ( new ReflectionMethod( TC_Discord::class, 'encrypt' ) )->invoke( null, $value );
$https = static fn(): string => 'https://localhost:8080';
$response = static fn( array $body, int $status = 200 ): array => array( 'response' => array( 'code' => $status ), 'headers' => array(), 'body' => wp_json_encode( $body ) );
$oauth = array( 'enabled' => 1, 'staging_confirmed' => 1, 'client_id' => $ids['application'], 'guild_id' => $ids['guild'], 'free_role' => $ids['free'], 'monthly_role' => $ids['monthly'], 'lifetime_role' => $ids['lifetime'], 'staff_role' => $ids['staff'], 'owner_role' => $ids['owner'], 'secret_encrypted' => $encrypt( 'synthetic-local-oauth-secret' ) );
$billing = array( 'mode' => 'test', 'routing' => 'direct', 'stripe_test_confirmed' => false, 'migration_confirmed' => false, 'role_sync_enabled' => true, 'paypal_enabled' => false, 'test_paypal_webhook_id' => 'WH-synthetic-local-facade', 'test_discord_guild_id' => $ids['guild'], 'test_discord_monthly_role_id' => $ids['monthly'], 'test_discord_lifetime_role_id' => $ids['lifetime'] );
$prices = array();
foreach ( array( 'monthly' => 2999, 'lifetime' => 8000 ) as $plan => $amount ) {
	$billing[ 'test_stripe_' . $plan . '_price_id' ] = 'price_' . $tag . '_' . $plan;
	$billing[ 'test_stripe_' . $plan . '_product_id' ] = 'prod_' . $tag . '_' . $plan;
	$prices[ $billing[ 'test_stripe_' . $plan . '_price_id' ] ] = array( 'id' => $billing[ 'test_stripe_' . $plan . '_price_id' ], 'livemode' => false, 'active' => true, 'product' => $billing[ 'test_stripe_' . $plan . '_product_id' ], 'currency' => 'eur', 'unit_amount' => $amount, 'billing_scheme' => 'per_unit', 'type' => 'monthly' === $plan ? 'recurring' : 'one_time', 'recurring' => 'monthly' === $plan ? array( 'interval' => 'month', 'interval_count' => 1, 'usage_type' => 'licensed' ) : null );
}
$http = static function ( $pre, $args, $url ) use ( &$requests, &$checkout_posts, &$sessions, &$payments, &$charges, &$fault, &$member_roles, $ids, $prices, $tag, $response ) {
	$requests[] = array( $url, $args ); $method = $args['method'] ?? 'GET';
	if ( 0 !== ( $args['redirection'] ?? null ) || true !== ( $args['sslverify'] ?? null ) ) { throw new RuntimeException( 'Unpinned HTTP request blocked.' ); }
	if ( str_starts_with( $url, 'https://api.stripe.com/v1/' ) ) {
		if ( 'Bearer sk_test_localFacadeFixture' !== ( $args['headers']['Authorization'] ?? '' ) || '2024-06-20' !== ( $args['headers']['Stripe-Version'] ?? '' ) ) { throw new RuntimeException( 'Unexpected Stripe credentials/version.' ); }
		$path = substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( '/v1/' ) );
		if ( 'GET' === $method && str_starts_with( $path, 'prices/' ) && isset( $prices[ substr( $path, 7 ) ] ) ) { return $response( $prices[ substr( $path, 7 ) ] ); }
		if ( 'GET' === $method && str_starts_with( $path, 'products/prod_' . $tag . '_' ) ) { return $response( array( 'id' => substr( $path, 9 ), 'livemode' => false, 'active' => true ) ); }
		if ( 'POST' === $method && 'checkout/sessions' === $path ) {
			parse_str( $args['body'], $body ); $checkout_posts[] = array( 'body' => $body, 'key' => $args['headers']['Idempotency-Key'] ?? '' );
			if ( 'stripe_create_network' === $fault ) { return new WP_Error( 'fixture_network', 'PRIVATE_UPSTREAM_RESPONSE' ); }
			$price = $prices[ $body['line_items'][0]['price'] ] ?? null;
			if ( ! $price ) { throw new RuntimeException( 'Unconfigured Stripe price requested.' ); }
			$n = count( $checkout_posts ); $payment = 'payment' === $body['mode'];
			$id = 'cs_test_' . $tag . '_' . $n; $customer = 'cus_' . $tag . '_' . $n; $pi = 'pi_' . $tag . '_' . $n;
			$sessions[ $id ] = array( 'id' => $id, 'livemode' => false, 'metadata' => $body['metadata'], 'client_reference_id' => $body['client_reference_id'], 'mode' => $body['mode'], 'currency' => 'eur', 'amount_total' => $price['unit_amount'], 'customer' => $customer, 'subscription' => $payment ? null : 'sub_' . $tag . '_' . $n, 'payment_intent' => $payment ? $pi : null, 'status' => 'open', 'payment_status' => 'unpaid', 'url' => 'https://checkout.stripe.com/c/pay/' . $id, 'line_items' => array( 'has_more' => false, 'data' => array( array( 'quantity' => 1, 'amount_total' => $price['unit_amount'], 'price' => $price ) ) ) );
			if ( $payment ) {
				$charge = 'ch_' . $tag . '_' . $n;
				$payments[ $pi ] = array( 'id' => $pi, 'livemode' => false, 'metadata' => $body['payment_intent_data']['metadata'], 'customer' => $customer, 'invoice' => null, 'status' => 'processing', 'currency' => 'eur', 'amount' => $price['unit_amount'], 'amount_received' => 0, 'latest_charge' => $charge );
				$charges[ $charge ] = array( 'id' => $charge, 'livemode' => false, 'customer' => $customer, 'invoice' => null, 'payment_intent' => $pi, 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'currency' => 'eur', 'amount' => $price['unit_amount'], 'amount_refunded' => 0, 'refunded' => false, 'disputed' => false );
			}
			return $response( $sessions[ $id ] );
		}
		if ( 'GET' === $method && str_starts_with( $path, 'checkout/sessions/' ) && isset( $sessions[ substr( $path, 18 ) ] ) ) { return $response( $sessions[ substr( $path, 18 ) ] ); }
		if ( 'GET' === $method && str_starts_with( $path, 'payment_intents/' ) && isset( $payments[ substr( $path, 16 ) ] ) ) { return $response( $payments[ substr( $path, 16 ) ] ); }
		if ( 'GET' === $method && str_starts_with( $path, 'charges/' ) && isset( $charges[ substr( $path, 8 ) ] ) ) { return $response( $charges[ substr( $path, 8 ) ] ); }
		throw new RuntimeException( 'Unexpected Stripe endpoint blocked.' );
	}
	if ( ! str_starts_with( $url, 'https://discord.com/api/v10/' ) ) { throw new RuntimeException( 'Unexpected remote service blocked.' ); }
	$path = substr( $url, strlen( 'https://discord.com/api/v10' ) ); $authorization = $args['headers']['Authorization'] ?? '';
	if ( str_starts_with( $authorization, 'Bearer synthetic-facade-' ) && '/users/@me/guilds/' . $ids['guild'] . '/member' === $path && 'GET' === $method ) {
		$actor = 'Bearer synthetic-facade-other' === $authorization ? $ids['other'] : $ids['user'];
		return $response( array( 'user' => array( 'id' => $actor ), 'roles' => $actor === $ids['user'] ? $member_roles : array( $ids['free'] ) ) );
	}
	if ( 'Bot synthetic-local-facade-bot' !== $authorization ) { throw new RuntimeException( 'Unexpected Discord credentials.' ); }
	if ( 'GET' !== $method ) {
		if ( ! in_array( $method, array( 'PUT', 'DELETE' ), true ) || '/guilds/' . $ids['guild'] . '/members/' . $ids['user'] . '/roles/' . $ids['lifetime'] !== $path ) { throw new RuntimeException( 'Unsafe role mutation blocked.' ); }
		$member_roles = 'PUT' === $method ? array_values( array_unique( array_merge( $member_roles, array( $ids['lifetime'] ) ) ) ) : array_values( array_diff( $member_roles, array( $ids['lifetime'] ) ) );
		return array( 'response' => array( 'code' => 204 ), 'headers' => array(), 'body' => '' );
	}
	if ( '/users/@me' === $path ) { return $response( array( 'id' => $ids['bot'], 'bot' => true ) ); }
	if ( '/guilds/' . $ids['guild'] === $path ) { return $response( array( 'id' => $ids['guild'], 'owner_id' => $ids['guild_owner'] ) ); }
	if ( '/guilds/' . $ids['guild'] . '/roles' === $path ) {
		$roles = array(); foreach ( array( 'guild' => 0, 'free' => 1, 'monthly' => 3, 'lifetime' => 4, 'staff' => 10, 'owner' => 30, 'bot_role' => 20 ) as $role => $position ) { $roles[] = array( 'id' => $ids[ $role ], 'position' => $position, 'permissions' => 'bot_role' === $role ? '268435456' : '0', 'managed' => 'bot_role' === $role ); }
		return $response( $roles );
	}
	if ( '/guilds/' . $ids['guild'] . '/members/' . $ids['bot'] === $path ) { return $response( array( 'user' => array( 'id' => $ids['bot'], 'bot' => true ), 'roles' => array( $ids['bot_role'] ) ) ); }
	if ( '/guilds/' . $ids['guild'] . '/members/' . $ids['user'] === $path ) { return $response( array( 'user' => array( 'id' => $ids['user'] ), 'roles' => $member_roles ) ); }
	throw new RuntimeException( 'Unexpected Discord endpoint blocked.' );
};
$payload = static fn( string $plan = 'lifetime', string $provider = 'stripe' ): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'checkout', 'provider' => $provider, 'plan' => $plan, 'consent' => '1' );
$fresh_roles = static function ( int $uid ): void { delete_user_meta( $uid, '_tc_discord_status' ); delete_user_meta( $uid, '_tc_discord_attempt' ); delete_user_meta( $uid, '_tc_discord_retry_until' ); };
$deliver = static function ( string $name, string $object, string $type = 'checkout.session.completed', bool $valid = true ) use ( $tag, &$event_ids ): WP_REST_Response {
	$id = 'evt_' . $tag . '_' . $name; $event_ids[] = $id;
	$raw = wp_json_encode( array( 'id' => $id, 'type' => $type, 'created' => time(), 'livemode' => false, 'data' => array( 'object' => array( 'id' => $object, 'livemode' => false, 'payment_status' => 'paid', 'private_payload' => 'PRIVATE_WEBHOOK_BODY' ) ) ) );
	$stamp = time(); $signature = hash_hmac( 'sha256', $stamp . '.' . $raw, $valid ? TIMONCONFIGS_BILLING_TEST_STRIPE_WEBHOOK_SECRET : 'wrong-local-secret' );
	$request = new WP_REST_Request( 'POST', '/timonconfigs/v1/billing/stripe/test' ); $request->set_url_params( array( 'provider' => 'stripe', 'mode' => 'test' ) ); $request->set_body( $raw ); $request->set_header( 'Stripe-Signature', 't=' . $stamp . ',v1=' . $signature );
	return TC_Billing::webhook( $request );
};
$stored_event = static function ( string $name ) use ( $wpdb, $tables, $tag ): ?array { return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $tables['events'] . ' WHERE provider = %s AND mode = %s AND event_id = %s', 'stripe', 'test', 'evt_' . $tag . '_' . $name ), ARRAY_A ); };
$role_writes = static function () use ( &$requests ): array { return array_values( array_filter( $requests, static fn( $request ) => str_starts_with( $request[0], 'https://discord.com/api/v10/' ) && in_array( $request[1]['method'] ?? '', array( 'PUT', 'DELETE' ), true ) ) ); };
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 1, 3 );
try {
	foreach ( array( 'user' => 'subscriber', 'other' => 'subscriber', 'expiry' => 'subscriber', 'admin' => 'administrator' ) as $actor => $role ) {
		$uid = $must( wp_insert_user( array( 'user_login' => $tag . '_' . $actor, 'user_pass' => wp_generate_password( 48 ), 'role' => $role ) ) ); $users[ $actor ] = (int) $uid;
		if ( 'admin' !== $actor ) {
			add_option( '_tc_discord_binding_' . $ids[ $actor ], $uid, '', false ); update_user_meta( $uid, '_tc_discord_id', $ids[ $actor ] );
			update_user_meta( $uid, '_tc_discord_token', $encrypt( wp_json_encode( array( 'access_token' => 'synthetic-facade-' . $actor, 'refresh_token' => 'synthetic-refresh-' . $actor, 'expires' => time() + HOUR_IN_SECONDS ) ) ) );
		}
	}
	$uid = $users['user']; $other_uid = $users['other'];
	update_option( 'timonconfigs_discord', $oauth, false ); update_option( 'timonconfigs_billing', $billing, false ); wp_set_current_user( $uid );
	$assert( TC_Discord::is_ready() && TC_Billing::mode_allowed( 'test' ) && TC_Billing::direct_enabled(), 'The isolated HTTPS localhost fixture is ready for direct Test checkout.' );
	$assert( ! TC_Billing::mode_allowed( 'live' ) && ! TC_Billing::mode_allowed( 'invalid' ) && ! TC_Billing::provider_ready( 'stripe', 'live' ), 'No browser setting or test credential unlocks Live.' );
	$live_settings = $billing; $live_settings['mode'] = 'live'; $live_settings['stripe_test_confirmed'] = true; $live_settings['migration_confirmed'] = true; update_option( 'timonconfigs_billing', $live_settings, false );
	$assert( $error_is( TC_Billing::perform( $payload() ), 'tc_billing_disabled' ), 'A forged Live option remains blocked at the actual checkout service without server approval.' );
	update_option( 'timonconfigs_billing', $billing, false ); wp_set_current_user( 0 );
	$assert( $error_is( TC_Billing::perform( $payload() ), 'tc_billing_nonce' ), 'An anonymous visitor cannot purchase even with an anonymous nonce.' );
	wp_set_current_user( $uid );
	$assert( ! TC_Billing::provider_ready( 'paypal', 'test' ), 'PayPal is unavailable before explicit Stripe test confirmation.' );
	$not_ready = $billing; $not_ready['paypal_enabled'] = true; update_option( 'timonconfigs_billing', $not_ready, false );
	$assert( ! TC_Billing::provider_ready( 'paypal', 'test' ) && $error_is( TC_Billing::perform( $payload( 'lifetime', 'paypal' ) ), 'tc_billing_unconfigured' ), 'A PayPal enable checkbox cannot bypass Stripe test confirmation.' );
	update_option( 'timonconfigs_billing', $billing, false );
	$assert( $error_is( TC_Billing::perform( array_merge( $payload(), array( '_wpnonce' => 'invalid' ) ) ), 'tc_billing_nonce' ), 'Checkout rejects a forged nonce.' );
	$token = get_user_meta( $uid, '_tc_discord_token', true ); delete_user_meta( $uid, '_tc_discord_token' );
	$assert( $error_is( TC_Billing::perform( $payload() ), 'tc_billing_identity' ), 'A WordPress login alone cannot purchase without an OAuth token.' );
	update_user_meta( $uid, '_tc_discord_token', $token ); update_option( '_tc_discord_binding_' . $ids['user'], $other_uid, false );
	$assert( $error_is( TC_Billing::perform( $payload() ), 'tc_billing_identity' ), 'A conflicting Discord-to-WordPress binding cannot purchase.' );
	update_option( '_tc_discord_binding_' . $ids['user'], $uid, false );
	$assert( $error_is( TC_Billing::perform( array_merge( $payload(), array( 'consent' => '0' ) ) ), 'tc_billing_consent' ), 'Checkout requires explicit consent.' );
	$legacy = $billing; $legacy['routing'] = 'legacy'; update_option( 'timonconfigs_billing', $legacy, false );
	$assert( $error_is( TC_Billing::perform( $payload() ), 'tc_billing_disabled' ), 'Legacy routing prevents a new direct purchase.' );
	update_option( 'timonconfigs_billing', $billing, false );
	$assert( array() === TC_Billing_Store::orders_for_user( $uid, 'test' ) && array() === $checkout_posts, 'Rejected requests do not create orders or payment sessions.' );
	// Persisting consent is a prerequisite for contacting the checkout API.
	$consent_faulted = false;
	$consent_fault = static function ( string $sql ) use ( &$consent_faulted, $tables ): string {
		if ( ! $consent_faulted && str_starts_with( $sql, 'UPDATE ' ) && str_contains( $sql, $tables['orders'] ) && str_contains( $sql, '`consent_at`' ) ) { $consent_faulted = true; return 'UPDATE ' . $tables['orders'] . ' SET tc_fixture_missing_consent_column = 1'; }
		return $sql;
	};
	$old_suppress = $wpdb->suppress_errors( true ); add_filter( 'query', $consent_fault );
	try { $consent_failure = TC_Billing::perform( $payload() ); }
	finally { remove_filter( 'query', $consent_fault ); $wpdb->suppress_errors( $old_suppress ); }
	$assert( $consent_faulted && is_wp_error( $consent_failure ) && array() === $checkout_posts, 'A failed consent write aborts checkout before any Stripe session POST.' );
	$unconsented = TC_Billing_Store::orders_for_user( $uid, 'test' );
	$assert( 1 === count( $unconsented ) && 'pending' === $unconsented[0]['status'] && empty( $unconsented[0]['consent_at'] ) && empty( $unconsented[0]['checkout_id'] ), 'The failed consent attempt leaves only an unpaid, resumable local order.' );
	$checkout = $must( TC_Billing::perform( array_merge( $payload(), array( 'amount' => '1', 'currency' => 'usd', 'price_id' => 'price_attacker', 'user_id' => $other_uid, 'discord_id' => $ids['other'] ) ) ) );
	$order = TC_Billing_Store::order( $checkout['order_id'] ); $session_id = $order['checkout_id']; $payment_id = $order['payment_id']; $charge_id = $payments[ $payment_id ]['latest_charge'];
	$assert( 8000 === (int) $order['amount'] && 'eur' === $order['currency'] && $uid === (int) $order['user_id'] && $ids['user'] === $order['discord_id'], 'Client price, currency and ownership fields cannot change the stored Lifetime order.' );
	$sent = $checkout_posts[0]['body'];
	$assert( $billing['test_stripe_lifetime_price_id'] === $sent['line_items'][0]['price'] && '1' === $sent['line_items'][0]['quantity'] && ! isset( $sent['amount'] ), 'The actual Stripe request uses the configured EUR product and quantity.' );
	$assert( (string) $uid === $sent['metadata']['tc_user_id'] && $ids['user'] === $sent['metadata']['tc_discord_id'] && $order['id'] === $sent['payment_intent_data']['metadata']['tc_order_id'], 'Hosted Checkout and its payment intent carry the exact server-bound identity.' );
	$assert( 'checkout' === $order['status'] && ! empty( $order['consent_at'] ) && ! empty( $order['consent_version'] ) && array() === TC_Billing_Store::active( $uid, 'test' ), 'Creating hosted Checkout records consent but grants no access.' );
	$again = $must( TC_Billing::perform( $payload() ) );
	$assert( $again['order_id'] === $order['id'] && 1 === count( $checkout_posts ) && 1 === count( TC_Billing_Store::orders_for_user( $uid, 'test' ) ), 'Repeated checkout resumes the same persisted session without another payment creation.' );
	$before_requests = count( $requests ); $_GET = array( 'billing' => 'return', 'tc_billing_return' => '1', 'session_id' => $session_id, 'paid' => '1' );
	$html = TC_Billing::render_account( $uid );
	$assert( '' !== $html && count( $requests ) === $before_requests && array() === TC_Billing_Store::active( $uid, 'test' ), 'A forged or genuine return GET renders status without provider calls or entitlement activation.' );
	$assert( '' === TC_Billing::render_account( $other_uid ), 'The account renderer refuses another user ID.' );
	$die_status = 0;
	$die = static function () use ( &$die_status ) { return static function ( $message, $title, $args ) use ( &$die_status ): void { $die_status = (int) ( $args['response'] ?? 0 ); throw new RuntimeException( 'fixture_wp_die' ); }; };
	add_filter( 'wp_die_handler', $die ); $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = $payload();
	try { TC_Billing::handle(); } catch ( RuntimeException $error ) { if ( 'fixture_wp_die' !== $error->getMessage() ) { throw $error; } }
	finally { remove_filter( 'wp_die_handler', $die ); }
	$assert( 405 === $die_status && array() === TC_Billing_Store::active( $uid, 'test' ), 'The actual billing controller rejects GET even with a valid form nonce.' );
	$assert( 400 === $deliver( 'forged', $session_id, 'checkout.session.completed', false )->get_status() && null === $stored_event( 'forged' ), 'A forged webhook signature never enters the durable inbox.' );
	$before_requests = count( $requests );
	$assert( 200 === $deliver( 'processing', $session_id )->get_status(), 'A signed notification is accepted by the public webhook controller.' );
	$processing = $stored_event( 'processing' );
	$assert( $processing && 'pending' === $processing['status'] && count( $requests ) === $before_requests && array() === TC_Billing_Store::active( $uid, 'test' ), 'Webhook acknowledgment durably queues bounded identifiers without provider HTTP or immediate access.' );
	$assert( ! str_contains( wp_json_encode( $processing ), 'PRIVATE_WEBHOOK_BODY' ), 'The real webhook controller persists no raw signed payload.' );
	$assert( true === $must( TC_Billing::process_event( $processing ) ) && array() === TC_Billing_Store::active( $uid, 'test' ), 'A signed paid claim cannot override the canonical processing payment.' );
	$sessions[ $session_id ]['status'] = 'complete'; $sessions[ $session_id ]['payment_status'] = 'paid'; $payments[ $payment_id ]['status'] = 'succeeded'; $payments[ $payment_id ]['amount_received'] = 8000;
	$assert( 200 === $deliver( 'paid', $session_id )->get_status(), 'A later signed completion creates durable work after actual settlement.' );
	$paid_event = $stored_event( 'paid' ); $assert( true === $must( TC_Billing::process_event( $paid_event ) ), 'The worker reconciles canonical Checkout, price, payment and captured charge.' );
	$active = TC_Billing_Store::active( $uid, 'test' ); $source = 'stripe:test:payment:' . $payment_id;
	$assert( 1 === count( $active ) && $source === $active[0]['source'] && null === $active[0]['valid_until'] && 'done' === TC_Billing_Store::event( (int) $paid_event['id'] )['status'], 'Verified settlement commits one Lifetime source and completes its event.' );
	$assert( 'pending' === TC_Billing_Store::sync_state( $uid, 'test' )['status'] && array() === $role_writes() && array() === TC_Billing_Store::active( $uid, 'live' ), 'The payment creates a Test-only Discord job without writing a role in the webhook worker.' );
	$generation = TC_Billing_Store::sync_state( $uid, 'test' )['generation']; $assert( 200 === $deliver( 'paid', $session_id )->get_status(), 'Duplicate signed delivery receives an idempotent acknowledgment.' );
	$assert( true === TC_Billing::process_event( $stored_event( 'paid' ) ) && $generation === TC_Billing_Store::sync_state( $uid, 'test' )['generation'], 'Duplicate delivery and worker replay never duplicate grants or outbox work.' );
	$assert( $error_is( TC_Billing::perform( $payload() ), 'tc_billing_duplicate' ), 'An independently verified Lifetime entitlement prevents a second overlapping purchase.' );
	$post = $must( wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'Disposable billing access fixture', 'post_author' => $uid ), true ) ); $posts[] = $post;
	update_post_meta( $post, 'tc_release_status', 'available' ); update_post_meta( $post, 'tc_tier', 'lifetime' );
	$fresh_roles( $uid );
	$assert( false === TC_Discord::can_access( $post, $uid ), 'A paid ledger entitlement alone cannot bypass the required premium Discord role.' );
	$job = TC_Billing_Store::sync_state( $uid, 'test' ); $assert( true === $must( TC_Billing_Discord::sync( $job ) ), 'The durable job validates Discord binding, bot permissions and hierarchy before syncing.' );
	$writes = $role_writes(); $assert( 1 === count( $writes ) && 'PUT' === $writes[0][1]['method'] && str_ends_with( $writes[0][0], '/roles/' . $ids['lifetime'] ), 'The full service flow performs one exact Lifetime role PUT through mocked HTTP.' );
	TC_Billing_Store::sync_result( (int) $job['id'], 'done' ); $fresh_roles( $uid );
	$assert( true === TC_Discord::can_access( $post, $uid ) && 'done' === TC_Billing_Store::sync_state( $uid, 'test' )['status'], 'Verified payment plus the canonical Discord role opens paid access.' );
	$live_order = $must( TC_Billing_Store::create_order( $uid, $ids['user'], 'stripe', 'live', 'lifetime' ) );
	$assert( $error_is( TC_Billing::perform( array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'refresh', 'order_id' => $live_order['id'] ) ), 'tc_billing_order' ), 'A member cannot manage an order from another billing mode through the Test controller.' );

	wp_set_current_user( $other_uid );
	$manage = array( '_wpnonce' => wp_create_nonce( 'tc_billing_action' ), 'operation' => 'refresh', 'order_id' => $order['id'] );
	$before_requests = count( $requests );
	$assert( $error_is( TC_Billing::perform( $manage ), 'tc_billing_order' ) && count( $requests ) === $before_requests, 'Another OAuth-bound member cannot manage an order by guessing its UUID.' );
	$fault = 'stripe_create_network'; $first = TC_Billing::perform( $payload( 'monthly' ) ); $pending = TC_Billing_Store::orders_for_user( $other_uid, 'test' );
	$assert( is_wp_error( $first ) && 1 === count( $pending ) && 'pending' === $pending[0]['status'] && empty( $pending[0]['checkout_id'] ), 'A provider network failure preserves one retryable pending order.' );
	$fault = ''; $retry = $must( TC_Billing::perform( $payload( 'monthly' ) ) );
	$assert( $pending[0]['id'] === $retry['order_id'] && $checkout_posts[1]['key'] === $checkout_posts[2]['key'] && '' !== $checkout_posts[2]['key'], 'A network retry reuses both the persisted order and provider idempotency key.' );
	$post_count = count( $checkout_posts ); $resumed = $must( TC_Billing::perform( $payload( 'monthly' ) ) );
	$assert( $resumed['order_id'] === $retry['order_id'] && $post_count === count( $checkout_posts ), 'An existing monthly session resumes without creating another subscription.' );
	$confirmed = $billing; $confirmed['stripe_test_confirmed'] = true; $confirmed['paypal_enabled'] = true; update_option( 'timonconfigs_billing', $confirmed, false );
	$without_paypal_webhook = $confirmed; $without_paypal_webhook['test_paypal_webhook_id'] = ''; update_option( 'timonconfigs_billing', $without_paypal_webhook, false );
	$assert( ! TC_Billing::provider_ready( 'paypal', 'test' ), 'PayPal readiness requires the configured verification webhook ID even after Stripe confirmation.' );
	update_option( 'timonconfigs_billing', $confirmed, false );
	$before_requests = count( $requests );
	$assert( $error_is( TC_Billing::perform( $payload( 'monthly', 'paypal' ) ), 'tc_billing_existing_subscription' ) && count( $requests ) === $before_requests, 'An unfinished Stripe subscription prevents a competing PayPal checkout without PayPal HTTP.' );
	update_option( 'timonconfigs_billing', $billing, false );
	// A canceled renewal flag and expired paid time do not prove that the
	// provider contract stopped retrying. Older orders must still be checked.
	$old_subscription = TC_Billing_Store::order( $retry['order_id'] );
	$must( TC_Billing_Store::update_order( $old_subscription['id'], array( 'status' => 'expired', 'cancel_at_period_end' => 1 ) ) );
	$wpdb->update( $tables['orders'], array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), array( 'id' => $old_subscription['id'] ) );
	$must( TC_Billing_Store::upsert_entitlement( array( 'source' => $tag . ':expired-renewal', 'user_id' => $other_uid, 'discord_id' => $ids['other'], 'provider' => 'stripe', 'mode' => 'test', 'plan' => 'monthly', 'status' => 'expired', 'valid_until' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ), 'order_id' => $old_subscription['id'] ) ) );
	for ( $n = 0; $n < 21; ++$n ) { $newer = $must( TC_Billing_Store::create_order( $other_uid, $ids['other'], 'stripe', 'test', 'lifetime' ) ); $must( TC_Billing_Store::update_order( $newer['id'], array( 'status' => 'failed' ) ) ); }
	$assert( ! in_array( $old_subscription['id'], array_column( TC_Billing_Store::orders_for_user( $other_uid, 'test' ), 'id' ), true ) && array() === TC_Billing_Store::active( $other_uid, 'test' ), 'The unresolved subscription lies beyond the last twenty orders and has no paid access remaining.' );
	$before_posts = count( $checkout_posts );
	$assert( $error_is( TC_Billing::perform( $payload( 'monthly' ) ), 'tc_billing_existing_subscription' ) && $before_posts === count( $checkout_posts ), 'An expired subscription with only a cancellation request blocks a second renewal contract regardless of order-history pagination.' );
	$read_faulted = false;
	$read_fault = static function ( string $sql ) use ( &$read_faulted, $tables ): string {
		if ( ! $read_faulted && str_starts_with( $sql, 'SELECT ' ) && str_contains( $sql, $tables['orders'] ) && str_contains( $sql, "plan='monthly' AND subscription_id" ) ) { $read_faulted = true; return 'SELECT tc_fixture_missing_recurring_column FROM ' . $tables['orders']; }
		return $sql;
	};
	$old_suppress = $wpdb->suppress_errors( true ); add_filter( 'query', $read_fault );
	try { $read_failure = TC_Billing::perform( $payload( 'monthly' ) ); }
	finally { remove_filter( 'query', $read_fault ); $wpdb->suppress_errors( $old_suppress ); }
	$assert( $read_faulted && $error_is( $read_failure, 'billing_order_read_failed' ) && $before_posts === count( $checkout_posts ), 'Failure to read unresolved provider contracts fails closed before a checkout POST.' );
	$must( TC_Billing_Store::update_order( $old_subscription['id'], array( 'status' => 'canceled' ) ) );
	$after_stop = $must( TC_Billing::perform( $payload( 'monthly' ) ) );
	$assert( $after_stop['order_id'] !== $old_subscription['id'] && count( $checkout_posts ) === $before_posts + 1, 'A canonically stopped contract permits a fresh checkout once all paid time has expired.' );
	$admin_data = array( '_wpnonce' => wp_create_nonce( 'tc_billing_admin' ), 'operation' => 'review_legacy', 'user_id' => $uid, 'mode' => 'test', 'confirm' => '1' );
	$assert( $error_is( TC_Billing::admin_perform( $admin_data ), 'tc_billing_forbidden' ), 'A subscriber cannot mark another account as reviewed or import grants.' );
	wp_set_current_user( $users['admin'] ); $admin_data['_wpnonce'] = wp_create_nonce( 'tc_billing_admin' );
	$assert( $error_is( TC_Billing::admin_perform( array_merge( $admin_data, array( '_wpnonce' => 'forged' ) ) ), 'tc_billing_forbidden' ), 'Administrator mutations also require their dedicated nonce.' );
	$sanitized = TC_Billing::sanitize( array_merge( $confirmed, array( 'mode' => 'live', 'routing' => 'direct', 'migration_confirmed' => '1', 'stripe_test_confirmed' => '1' ) ) );
	$assert( 'test' === $sanitized['mode'] && 'legacy' === $sanitized['routing'], 'Saving confirmation checkboxes cannot replace the separate server-side Live approval.' );
	$assert( true === $must( TC_Billing::admin_perform( array_merge( $admin_data, array( 'mode' => 'live' ) ) ) ), 'An administrator can record a separate Live legacy inventory review.' );
	$assert( '1' === get_user_meta( $uid, '_tc_billing_legacy_reviewed_live', true ) && '' === get_user_meta( $uid, '_tc_billing_legacy_reviewed_test', true ), 'A Live review never authorizes Test role removal or access denial.' );
	$charges[ $charge_id ]['amount_refunded'] = 8000; $charges[ $charge_id ]['refunded'] = true;
	$assert( 200 === $deliver( 'refund', $charge_id, 'charge.refunded' )->get_status() && true === $must( TC_Billing::process_event( $stored_event( 'refund' ) ) ), 'A signed full refund is confirmed against the actual canonical charge.' );
	wp_set_current_user( $uid ); $fresh_roles( $uid );
	$assert( array() === TC_Billing_Store::active( $uid, 'test' ) && null === TC_Billing::access_constraint( 'lifetime', $uid ) && true === TC_Discord::can_access( $post, $uid ), 'An unrelated Live review does not override unreviewed Test legacy roles.' );
	wp_set_current_user( $users['admin'] ); $admin_data['_wpnonce'] = wp_create_nonce( 'tc_billing_admin' );
	$assert( true === $must( TC_Billing::admin_perform( $admin_data ) ), 'The administrator explicitly reviews the correct Test inventory.' );
	wp_set_current_user( $uid );
	$assert( false === TC_Billing::access_constraint( 'lifetime', $uid ) && false === TC_Discord::can_access( $post, $uid ), 'After a Test inventory review, a refunded source denies access even before its stale Discord role is removed.' );
	wp_set_current_user( $users['admin'] ); $admin_data['_wpnonce'] = wp_create_nonce( 'tc_billing_admin' );
	$grant = array_merge( $admin_data, array( 'operation' => 'external_grant', 'source_provider' => 'legacy', 'plan' => 'monthly', 'valid_until' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ) );
	$assert( $error_is( TC_Billing::admin_perform( array_merge( $grant, array( 'confirm' => '0' ) ) ), 'tc_billing_confirm' ), 'External coverage cannot be imported without explicit source verification.' );
	$assert( $error_is( TC_Billing::admin_perform( array_merge( $grant, array( 'valid_until' => '2000-01-01 00:00:00' ) ) ), 'tc_billing_date' ), 'Legacy Monthly import requires its actual future paid end date.' );
	$assert( true === $must( TC_Billing::admin_perform( $grant ) ), 'A verified legacy Monthly purchase becomes its own ledger source.' );
	$assert( true === $must( TC_Billing::admin_perform( array_merge( $grant, array( 'source_provider' => 'grant', 'plan' => 'lifetime', 'valid_until' => '' ) ) ) ), 'An approved Lifetime giveaway becomes an independent source.' );
	$active = TC_Billing_Store::active( $uid, 'test' ); $providers = array_column( $active, 'provider' );
	$assert( 2 === count( $active ) && in_array( 'legacy', $providers, true ) && in_array( 'grant', $providers, true ) && array() === TC_Billing_Store::active( $uid, 'live' ), 'The access union preserves independently verified legacy and giveaway coverage in the correct mode.' );
	$assert( 2 === count( get_user_meta( $uid, '_tc_billing_external_audit', false ) ) && 2 === count( get_user_meta( $uid, '_tc_billing_review_audit', false ) ), 'Grant imports and mode-specific reviews record the acting administrator and UTC audit time.' );
	wp_set_current_user( $uid );
	$assert( true === TC_Billing::access_constraint( 'lifetime', $uid ) && true === TC_Discord::can_access( $post, $uid ), 'An independent valid Lifetime grant protects access after a direct-payment refund.' );
	$member_roles = array( $ids['free'] ); $fresh_roles( $uid );
	$assert( false === TC_Discord::can_access( $post, $uid ), 'Even multiple valid ledger sources cannot bypass a missing premium Discord role.' );
	$member_roles[] = $ids['lifetime']; $fresh_roles( $uid );
	$assert( true === TC_Discord::can_access( $post, $uid ), 'The correct canonical premium role and valid independent ledger coverage restore access together.' );
	$coverage_faulted = false;
	$coverage_fault = static function ( string $sql ) use ( &$coverage_faulted, $tables ): string {
		if ( ! $coverage_faulted && str_starts_with( $sql, 'SELECT ' ) && str_contains( $sql, $tables['entitlements'] ) && str_contains( $sql, "AND status = 'active'" ) ) { $coverage_faulted = true; return 'SELECT tc_fixture_missing_active_coverage FROM ' . $tables['entitlements']; }
		return $sql;
	};
	$before_writes = count( $role_writes() ); $old_suppress = $wpdb->suppress_errors( true ); add_filter( 'query', $coverage_fault );
	try { $unavailable_sync = TC_Billing_Discord::sync( TC_Billing_Store::sync_state( $uid, 'test' ) ); }
	finally { remove_filter( 'query', $coverage_fault ); $wpdb->suppress_errors( $old_suppress ); }
	$assert( $coverage_faulted && is_wp_error( $unavailable_sync ) && count( $role_writes() ) === $before_writes && in_array( $ids['lifetime'], $member_roles, true ), 'An unavailable entitlement read cannot be interpreted as empty coverage and delete an owned VIP role.' );
	$coverage_faulted = false; $old_suppress = $wpdb->suppress_errors( true ); add_filter( 'query', $coverage_fault );
	try { $unavailable_access = TC_Discord::can_access( $post, $uid ); }
	finally { remove_filter( 'query', $coverage_fault ); $wpdb->suppress_errors( $old_suppress ); }
	$assert( $coverage_faulted && false === $unavailable_access, 'A ledger read failure denies a paid download even while the premium Discord role remains valid.' );
	$assert( 200 === $deliver( 'refund_again', $charge_id, 'charge.refunded' )->get_status() && true === $must( TC_Billing::process_event( $stored_event( 'refund_again' ) ) ) && 2 === count( TC_Billing_Store::active( $uid, 'test' ) ), 'A later direct refund cannot erase either external source.' );
	// Legacy checkout routing still has to expire independent grant-only access.
	$expiry_uid = $users['expiry']; $past = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
	$expiry = array( 'source' => $tag . ':grant-only-test', 'user_id' => $expiry_uid, 'discord_id' => $ids['expiry'], 'provider' => 'grant', 'mode' => 'test', 'plan' => 'monthly', 'status' => 'active', 'valid_until' => $past );
	$must( TC_Billing_Store::upsert_entitlement( $expiry ) ); $live_expiry = $expiry; $live_expiry['source'] = $tag . ':grant-only-live'; $live_expiry['mode'] = 'live'; $must( TC_Billing_Store::upsert_entitlement( $live_expiry ) );
	$assert( array() === TC_Billing_Store::orders_for_user( $expiry_uid, 'test' ) && array() === TC_Billing_Store::sync_state( $expiry_uid, 'test' ), 'The expiring fixture has only independently recorded grant coverage, with no order or existing outbox job.' );
	$legacy = $billing; $legacy['routing'] = 'legacy'; $legacy['role_sync_enabled'] = false; update_option( 'timonconfigs_billing', $legacy, false ); wp_clear_scheduled_hook( 'tc_billing_tick' ); TC_Billing::schedule();
	$scheduled = wp_get_scheduled_event( 'tc_billing_tick' );
	$assert( ! TC_Billing::direct_enabled() && TC_Billing_Store::maintenance_needed( 'test' ) && $scheduled && 'tc_billing_five_minutes' === $scheduled->schedule, 'Existing billing coverage keeps recurring maintenance scheduled while new checkout routing remains legacy.' );
	wp_set_current_user( $users['admin'] ); wp_clear_scheduled_hook( 'tc_billing_tick' );
	$wake = TC_Billing::admin_perform( array( '_wpnonce' => wp_create_nonce( 'tc_billing_admin' ), 'operation' => 'retry_sync', 'user_id' => $uid, 'mode' => 'test' ) );
	$wake_at = wp_next_scheduled( 'tc_billing_tick' );
	$assert( true === $must( $wake ) && false !== $wake_at && $wake_at <= time() + 2, 'An authorized administrator retry wakes the worker even under legacy routing.' );
	foreach ( $users as $fixture_uid ) { foreach ( array( 'test', 'live' ) as $fixture_mode ) { foreach ( TC_Billing_Store::orders_for_user( $fixture_uid, $fixture_mode, 100 ) as $fixture_order ) { TC_Billing_Store::mark_reconciled( $fixture_order['id'], 3600 ); } } }
	$before_writes = count( $role_writes() ); TC_Billing::tick(); $expiry_job = TC_Billing_Store::sync_state( $expiry_uid, 'test' );
	$assert( 'expired' === TC_Billing_Store::entitlement( $expiry['source'] )['status'] && ! empty( $expiry_job['id'] ) && count( $role_writes() ) === $before_writes, 'The actual legacy-maintenance worker expires grant-only Monthly access and leaves durable sync work while role writes are disabled.' );
	$assert( 'active' === TC_Billing_Store::entitlement( $live_expiry['source'] )['status'] && array() === TC_Billing_Store::sync_state( $expiry_uid, 'live' ) && array() === TC_Billing_Store::active( $expiry_uid, 'live' ), 'Test maintenance neither activates nor processes the other mode’s expired grant.' );
	$assert( ! str_contains( wp_json_encode( TC_Billing_Store::diagnostics( 100 ) ), 'PRIVATE_WEBHOOK_BODY' ) && ! str_contains( wp_json_encode( TC_Billing_Store::diagnostics( 100 ) ), 'PRIVATE_UPSTREAM_RESPONSE' ), 'Operational diagnostics contain neither webhook bodies nor upstream error text.' );
	WP_CLI::success( "Billing facade integration: $checks assertions; real WordPress services, synthetic credentials, all provider and Discord HTTP intercepted." );
} finally {
	remove_filter( 'pre_http_request', $http, 1 ); remove_filter( 'option_home', $https );
	foreach ( $posts as $post ) { wp_delete_post( $post, true ); }
	foreach ( array_unique( $event_ids ) as $event_id ) { $wpdb->delete( $tables['events'], array( 'event_id' => $event_id, 'provider' => 'stripe', 'mode' => 'test' ) ); }
	foreach ( $users as $id ) {
		foreach ( array( 'entitlements', 'orders', 'sync_jobs', 'owned_roles' ) as $table ) { $wpdb->delete( $tables[ $table ], array( 'user_id' => $id ) ); }
		delete_option( '_tc_discord_lock_' . $id ); delete_transient( 'tc_billing_feedback_' . $id ); delete_transient( 'tc_billing_admin_' . $id );
		require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id );
	}
	foreach ( array( 'user', 'other', 'expiry' ) as $actor ) { delete_option( '_tc_discord_binding_' . $ids[ $actor ] ); }
	null === $old_billing ? delete_option( 'timonconfigs_billing' ) : update_option( 'timonconfigs_billing', $old_billing, false );
	null === $old_oauth ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old_oauth, false );
	null === $old_cron ? delete_option( 'cron' ) : update_option( 'cron', $old_cron, false );
	null === $old_tick ? delete_option( 'timonconfigs_billing_last_tick' ) : update_option( 'timonconfigs_billing_last_tick', $old_tick, false );
	null === $old_worker_error ? delete_option( 'timonconfigs_billing_worker_error' ) : update_option( 'timonconfigs_billing_worker_error', $old_worker_error, false );
	false === $old_failure ? delete_transient( 'tc_discord_last_failure' ) : set_transient( 'tc_discord_last_failure', $old_failure, DAY_IN_SECONDS );
	$_GET = $old_get; $_POST = $old_post;
	if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
	wp_set_current_user( $original_user );
}
