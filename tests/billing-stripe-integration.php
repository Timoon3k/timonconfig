<?php
/**
 * Standalone, in-memory Stripe contract tests: php tests/billing-stripe-integration.php.
 * Deliberately refuses a loaded WordPress instance; no database or real HTTP exists here.
 */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) || ! in_array( getenv( 'TC_BILLING_TEST_HOST' ) ?: '127.0.0.1', array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
	file_put_contents( 'php://stderr', "Run standalone on a localhost fixture, outside WordPress.\n" );
	exit( 1 );
}

// Conditional declarations avoid conflicting with WordPress if this file is included accidentally.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	class WP_Error {
		public function __construct( public string $code, public string $message = '', public mixed $data = null ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_data(): mixed { return $this->data; }
	}
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function home_url( string $path ): string { return 'http://127.0.0.1' . $path; }
	function wp_parse_url( string $url ): array|false { return parse_url( $url ); }
	function wp_remote_request( string $url, array $args ): array|WP_Error {
		global $tc_mock_http, $tc_http_calls;
		$tc_http_calls[] = array( $url, $args );
		if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/' ) || '2024-06-20' !== ( $args['headers']['Stripe-Version'] ?? '' ) || 0 !== $args['redirection'] || true !== $args['sslverify'] ) { throw new RuntimeException( 'Unpinned request.' ); }
		return $tc_mock_http( $url, $args );
	}
	function wp_remote_retrieve_response_code( array $response ): int { return $response['response']['code']; }
	function wp_remote_retrieve_header( array $response, string $name ): string { return $response['headers'][ $name ] ?? ''; }
	function wp_remote_retrieve_body( array $response ): string { return $response['body']; }
	final class TC_Billing {
		public static array $settings = array();
		public static array $secrets = array( 'STRIPE_SECRET_KEY' => 'sk_test_fixtureOnly', 'STRIPE_WEBHOOK_SECRET' => 'whsec_fixtureOnly' );
		public static function mode_allowed( string $mode ): bool { return 'test' === $mode; }
		public static function setting( string $key, string $mode ): string { return self::$settings[ $key ] ?? ''; }
		public static function secret( string $key, string $mode ): string { return self::$secrets[ $key ] ?? ''; }
		public static function error( string $code, string $message ): WP_Error { return new WP_Error( $code, $message ); }
	}
	final class TC_Billing_Store {
		public static array $orders = array();
		public static function order( string $id ): ?array { return self::$orders[ $id ] ?? null; }
	}
}

$tc_adapter = dirname( __DIR__ ) . '/wp-content/plugins/timonconfigs-core/includes/class-tc-billing-stripe.php';
require is_file( $tc_adapter ) ? $tc_adapter : '/var/www/html/wp-content/plugins/timonconfigs-core/includes/class-tc-billing-stripe.php';

$tc_checks = 0;
$tc_http_calls = array();
$tc_routes = array();
$tc_assert = static function ( bool $ok, string $message ) use ( &$tc_checks ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$tc_checks;
};
$tc_response = static fn( array $body, int $code = 200 ): array => array( 'response' => array( 'code' => $code ), 'headers' => array(), 'body' => json_encode( $body ) );
$tc_mock_http = static function ( string $url, array $args ) use ( &$tc_routes, $tc_response ): array|WP_Error {
	$route = $args['method'] . ' ' . substr( $url, strlen( 'https://api.stripe.com/v1/' ) );
	if ( ! array_key_exists( $route, $tc_routes ) ) { throw new RuntimeException( 'Unexpected mocked HTTP route: ' . $route ); }
	$result = $tc_routes[ $route ];
	return is_callable( $result ) ? $result( $url, $args ) : ( is_wp_error( $result ) ? $result : $tc_response( $result ) );
};

$tc_uuid = 'a1111111-1111-4111-8111-111111111111';
$tc_order = array( 'id' => $tc_uuid, 'user_id' => 17, 'discord_id' => '123456789012345678', 'provider' => 'stripe', 'mode' => 'test', 'plan' => 'monthly', 'amount' => 2999, 'currency' => 'eur', 'status' => 'pending', 'checkout_id' => 'cs_test_monthly', 'subscription_id' => 'sub_monthly', 'customer_id' => 'cus_monthly', 'payment_id' => '' );
$tc_meta = array( 'tc_order_id' => $tc_uuid, 'tc_user_id' => '17', 'tc_discord_id' => '123456789012345678', 'tc_plan' => 'monthly', 'tc_mode' => 'test' );
$tc_price = array( 'id' => 'price_monthly', 'livemode' => false, 'active' => true, 'product' => 'prod_monthly', 'currency' => 'eur', 'unit_amount' => 2999, 'billing_scheme' => 'per_unit', 'type' => 'recurring', 'recurring' => array( 'interval' => 'month', 'interval_count' => 1, 'usage_type' => 'licensed' ) );
$tc_end = time() + 15 * DAY_IN_SECONDS;
$tc_start = $tc_end - 30 * DAY_IN_SECONDS;
$tc_sub = array( 'id' => 'sub_monthly', 'livemode' => false, 'metadata' => $tc_meta, 'customer' => 'cus_monthly', 'currency' => 'eur', 'status' => 'active', 'cancel_at_period_end' => false, 'current_period_end' => $tc_end, 'items' => array( 'data' => array( array( 'quantity' => 1, 'price' => $tc_price ) ), 'has_more' => false ) );
$tc_invoice = array( 'id' => 'in_monthly', 'livemode' => false, 'subscription' => 'sub_monthly', 'customer' => 'cus_monthly', 'paid' => true, 'status' => 'paid', 'currency' => 'eur', 'amount_paid' => 2999, 'amount_due' => 2999, 'total' => 2999, 'billing_reason' => 'subscription_cycle', 'payment_intent' => 'pi_monthly', 'lines' => array( 'has_more' => false, 'data' => array( array( 'type' => 'subscription', 'subscription' => 'sub_monthly', 'quantity' => 1, 'proration' => false, 'amount' => 2999, 'currency' => 'eur', 'price' => $tc_price, 'period' => array( 'start' => $tc_start, 'end' => $tc_end ) ) ) ) );
$tc_pi = array( 'id' => 'pi_monthly', 'livemode' => false, 'customer' => 'cus_monthly', 'invoice' => 'in_monthly', 'status' => 'succeeded', 'currency' => 'eur', 'amount' => 2999, 'amount_received' => 2999, 'latest_charge' => 'ch_monthly' );
$tc_charge = array( 'id' => 'ch_monthly', 'livemode' => false, 'customer' => 'cus_monthly', 'invoice' => 'in_monthly', 'payment_intent' => 'pi_monthly', 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'currency' => 'eur', 'amount' => 2999, 'amount_refunded' => 0, 'refunded' => false, 'disputed' => false );
$tc_session = array( 'id' => 'cs_test_monthly', 'livemode' => false, 'metadata' => $tc_meta, 'client_reference_id' => $tc_uuid, 'mode' => 'subscription', 'currency' => 'eur', 'amount_total' => 2999, 'customer' => 'cus_monthly', 'subscription' => 'sub_monthly', 'status' => 'complete', 'payment_status' => 'paid', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_fixture', 'line_items' => array( 'has_more' => false, 'data' => array( array( 'quantity' => 1, 'amount_total' => 2999, 'price' => $tc_price ) ) ) );
TC_Billing::$settings = array( 'stripe_monthly_price_id' => 'price_monthly', 'stripe_monthly_product_id' => 'prod_monthly', 'stripe_lifetime_price_id' => 'price_lifetime', 'stripe_lifetime_product_id' => 'prod_lifetime' );
TC_Billing_Store::$orders[ $tc_uuid ] = $tc_order;
$tc_base_routes = array(
	'GET prices/price_monthly' => $tc_price,
	'GET products/prod_monthly' => array( 'id' => 'prod_monthly', 'livemode' => false, 'active' => true ),
	'POST checkout/sessions' => array_replace( $tc_session, array( 'status' => 'open' ) ),
	'GET checkout/sessions/cs_test_monthly?expand%5B0%5D=line_items.data.price' => $tc_session,
	'GET subscriptions/sub_monthly' => $tc_sub,
	'GET invoices/in_monthly' => $tc_invoice,
	'GET invoices?subscription=sub_monthly&status=paid&limit=100' => array( 'data' => array( $tc_invoice ), 'has_more' => false ),
	'GET payment_intents/pi_monthly' => $tc_pi,
	'GET charges/ch_monthly' => $tc_charge,
);
$tc_routes = $tc_base_routes;
TC_Billing_Store::$orders[ $tc_uuid ]['checkout_id'] = '';
$tc_event = array( 'provider' => 'stripe', 'mode' => 'test', 'type' => 'invoice.paid', 'object_id' => 'in_monthly' );

$tc_created = TC_Billing_Stripe::create_checkout( $tc_order );
$tc_assert( ! is_wp_error( $tc_created ) && 'cs_test_monthly' === $tc_created['id'], 'Hosted monthly checkout is created.' );
$tc_first_post = end( $tc_http_calls );
parse_str( $tc_first_post[1]['body'], $tc_body );
$tc_assert( 'price_monthly' === $tc_body['line_items'][0]['price'] && '1' === $tc_body['line_items'][0]['quantity'] && ! isset( $tc_body['amount'] ), 'Checkout takes only the fixed configured price and quantity.' );
$tc_assert( $tc_meta === $tc_body['metadata'] && $tc_meta === $tc_body['subscription_data']['metadata'], 'Both checkout and subscription carry exact stored OAuth identity.' );
TC_Billing_Stripe::create_checkout( $tc_order );
$tc_second_post = end( $tc_http_calls );
$tc_assert( $tc_first_post[1]['headers']['Idempotency-Key'] === $tc_second_post[1]['headers']['Idempotency-Key'], 'Duplicate checkout retries use the same idempotency key.' );
$tc_tamper = $tc_order; $tc_tamper['amount'] = 1;
$tc_before = count( $tc_http_calls );
$tc_assert( is_wp_error( TC_Billing_Stripe::create_checkout( $tc_tamper ) ) && count( $tc_http_calls ) === $tc_before, 'Tampered browser/order amount fails before HTTP.' );
foreach ( array( 'unit_amount' => 1, 'currency' => 'usd', 'product' => 'prod_other' ) as $tc_key => $tc_bad ) {
	$tc_routes = $tc_base_routes; $tc_routes['GET prices/price_monthly'][ $tc_key ] = $tc_bad;
	$tc_assert( is_wp_error( TC_Billing_Stripe::create_checkout( $tc_order ) ), 'Server rejects a changed configured price: ' . $tc_key );
}
$tc_routes = $tc_base_routes; $tc_routes['GET prices/price_monthly']['recurring']['interval'] = 'year';
$tc_assert( is_wp_error( TC_Billing_Stripe::create_checkout( $tc_order ) ), 'Monthly interval is immutable.' );
$tc_routes = $tc_base_routes; $tc_routes['POST checkout/sessions']['url'] = 'https://checkout.stripe.com.attacker.example/pay';
$tc_assert( is_wp_error( TC_Billing_Stripe::create_checkout( $tc_order ) ), 'Hosted checkout rejects a lookalike hostname.' );
$tc_routes = $tc_base_routes;
TC_Billing::$secrets['STRIPE_SECRET_KEY'] = 'sk_live_fixtureOnly';
$tc_assert( is_wp_error( TC_Billing_Stripe::create_checkout( $tc_order ) ), 'A live key cannot be used in test mode.' );
TC_Billing::$secrets['STRIPE_SECRET_KEY'] = 'sk_test_fixtureOnly';

$tc_signed_event = array( 'id' => 'evt_fixture', 'type' => 'invoice.paid', 'created' => time() - 3600, 'livemode' => false, 'data' => array( 'object' => array( 'id' => 'in_monthly' ) ) );
$tc_sign = static function ( array $event, int $timestamp ): array {
	$raw = json_encode( $event );
	return array( $raw, array( 'Stripe-Signature' => 't=' . $timestamp . ',v1=' . str_repeat( '0', 64 ) . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $raw, 'whsec_fixtureOnly' ) ) );
};
[ $tc_raw, $tc_headers ] = $tc_sign( $tc_signed_event, time() );
$tc_descriptor = TC_Billing_Stripe::verify_webhook( $tc_raw, $tc_headers, 'test' );
$tc_assert( ! is_wp_error( $tc_descriptor ) && 4 === count( $tc_descriptor ) && 'in_monthly' === $tc_descriptor['object_id'], 'Multiple v1 signatures work and the descriptor contains bounded identifiers only.' );
$tc_assert( is_wp_error( TC_Billing_Stripe::verify_webhook( $tc_raw . ' ', $tc_headers, 'test' ) ), 'Changed signed bytes fail authentication.' );
[ $tc_raw, $tc_headers ] = $tc_sign( $tc_signed_event, time() - 301 );
$tc_assert( is_wp_error( TC_Billing_Stripe::verify_webhook( $tc_raw, $tc_headers, 'test' ) ), 'Stale delivery signatures fail.' );
[ $tc_raw, $tc_headers ] = $tc_sign( $tc_signed_event, time() + 301 );
$tc_assert( is_wp_error( TC_Billing_Stripe::verify_webhook( $tc_raw, $tc_headers, 'test' ) ), 'Future delivery signatures fail.' );
$tc_signed_event['livemode'] = true;
[ $tc_raw, $tc_headers ] = $tc_sign( $tc_signed_event, time() );
$tc_assert( is_wp_error( TC_Billing_Stripe::verify_webhook( $tc_raw, $tc_headers, 'test' ) ), 'A signed live event cannot enter the test ledger.' );
$tc_signed_event['livemode'] = false; $tc_signed_event['type'] = 'customer.created';
[ $tc_raw, $tc_headers ] = $tc_sign( $tc_signed_event, time() );
$tc_assert( ! is_wp_error( TC_Billing_Stripe::verify_webhook( $tc_raw, $tc_headers, 'test' ) ), 'Unknown valid event families can be safely queued and ignored.' );
$tc_headers['Stripe-Signature'] .= ',t=' . time();
$tc_assert( is_wp_error( TC_Billing_Stripe::verify_webhook( $tc_raw, $tc_headers, 'test' ) ), 'Ambiguous duplicate timestamps fail.' );

TC_Billing_Store::$orders[ $tc_uuid ] = $tc_order;
$tc_routes = $tc_base_routes;
$tc_routes['GET checkout/sessions/cs_test_monthly?expand%5B0%5D=line_items.data.price']['status'] = 'open';
TC_Billing_Store::$orders[ $tc_uuid ]['status'] = 'checkout';
$tc_before = count( $tc_http_calls );
$tc_resume = TC_Billing_Stripe::create_checkout( $tc_order );
$tc_assert( ! is_wp_error( $tc_resume ) && ! array_filter( array_slice( $tc_http_calls, $tc_before ), static fn( array $call ): bool => 'POST' === $call[1]['method'] ), 'A stored open checkout resumes without a second provider creation, even after provider idempotency expiry.' );
$tc_routes = $tc_base_routes;
$tc_assert( is_wp_error( TC_Billing_Stripe::create_checkout( $tc_order ) ), 'A completed stored Checkout cannot be replaced with another charge.' );
TC_Billing_Store::$orders[ $tc_uuid ] = $tc_order;
$tc_result = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( ! is_wp_error( $tc_result ) && 'active' === $tc_result['entitlement']['status'] && gmdate( 'Y-m-d H:i:s', $tc_end ) === $tc_result['entitlement']['valid_until'], 'Canonical settled invoice grants only its prepaid monthly period.' );
$tc_assert( 'stripe:test:subscription:sub_monthly' === $tc_result['entitlement']['source'] && 17 === $tc_result['entitlement']['user_id'], 'A stable provider source remains bound to the stored user.' );
$tc_duplicate = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( ! is_wp_error( $tc_duplicate ) && $tc_result['order_fields'] === $tc_duplicate['order_fields'] && $tc_result['entitlement']['source'] === $tc_duplicate['entitlement']['source'] && $tc_result['entitlement']['valid_until'] === $tc_duplicate['entitlement']['valid_until'], 'Duplicate provider events yield the same canonical financial transition.' );
$tc_periodic = TC_Billing_Stripe::reconcile( array( 'provider' => 'stripe', 'mode' => 'test', 'type' => 'tc.reconcile', 'order_id' => $tc_uuid ) );
$tc_assert( ! is_wp_error( $tc_periodic ) && $tc_result['entitlement']['valid_until'] === $tc_periodic['entitlement']['valid_until'], 'Periodic reconciliation uses the stored canonical subscription without relying on a webhook.' );
$tc_routes['GET prices/price_monthly']['active'] = false;
$tc_routes['GET products/prod_monthly']['active'] = false;
$tc_archived = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( ! is_wp_error( $tc_archived ) && 'active' === $tc_archived['entitlement']['status'], 'Archiving a configured product does not stop existing financial reconciliation.' );
$tc_routes = $tc_base_routes;
$tc_cancelled_sub = $tc_sub; $tc_cancelled_sub['cancel_at_period_end'] = true; $tc_cancelled_sub['status'] = 'canceled';
$tc_routes['GET subscriptions/sub_monthly'] = $tc_cancelled_sub;
$tc_cancelled = TC_Billing_Stripe::reconcile( array_replace( $tc_event, array( 'type' => 'customer.subscription.deleted', 'object_id' => 'sub_monthly' ) ) );
$tc_assert( ! is_wp_error( $tc_cancelled ) && 'active' === $tc_cancelled['entitlement']['status'] && $tc_result['entitlement']['valid_until'] === $tc_cancelled['entitlement']['valid_until'] && null === $tc_cancelled['entitlement']['next_payment_at'], 'Cancellation retains the prepaid period and stops the future billing display.' );
$tc_assert( 'canceled' === $tc_cancelled['order_fields']['status'], 'A canonically canceled contract records proof of stopped renewals independently of prepaid access.' );
$tc_routes = $tc_base_routes;
$tc_routes['GET subscriptions/sub_monthly']['status'] = 'incomplete_expired';
$tc_routes['GET invoices?subscription=sub_monthly&status=paid&limit=100']['data'] = array();
$tc_never_paid_stop = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( ! is_wp_error( $tc_never_paid_stop ) && 'canceled' === $tc_never_paid_stop['order_fields']['status'] && null === $tc_never_paid_stop['order_fields']['next_payment_at'] && ! isset( $tc_never_paid_stop['entitlement'] ), 'An incomplete-expired contract proves stopped renewals without fabricating paid access.' );
foreach ( array( 'active', 'past_due', 'unpaid' ) as $tc_running_status ) {
	$tc_routes = $tc_base_routes;
	$tc_routes['GET subscriptions/sub_monthly']['status'] = $tc_running_status;
	$tc_routes['GET subscriptions/sub_monthly']['cancel_at_period_end'] = true;
	$tc_scheduled_stop = TC_Billing_Stripe::reconcile( $tc_event );
	$tc_assert( ! is_wp_error( $tc_scheduled_stop ) && 'canceled' !== $tc_scheduled_stop['order_fields']['status'] && 'active' === $tc_scheduled_stop['entitlement']['status'] && $tc_result['entitlement']['valid_until'] === $tc_scheduled_stop['entitlement']['valid_until'], 'A scheduled cancellation does not prove a stopped contract while canonical status is ' . $tc_running_status . '.' );
}
$tc_routes = $tc_base_routes; $tc_routes['GET subscriptions/sub_monthly']['status'] = 'past_due';
$tc_failure = TC_Billing_Stripe::reconcile( array_replace( $tc_event, array( 'type' => 'invoice.payment_failed' ) ) );
$tc_assert( ! is_wp_error( $tc_failure ) && $tc_result['entitlement']['valid_until'] === $tc_failure['entitlement']['valid_until'] && 'active' === $tc_failure['entitlement']['status'], 'A failed renewal neither extends nor removes paid access.' );
$tc_assert( 'canceled' !== $tc_failure['order_fields']['status'], 'A failed renewal never records stopped-contract proof.' );
$tc_routes['GET invoices?subscription=sub_monthly&status=paid&limit=100']['data'] = array();
$tc_failure = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( ! is_wp_error( $tc_failure ) && ! isset( $tc_failure['entitlement'] ), 'Missing canonical paid evidence never replaces an existing entitlement.' );
foreach ( array( 'tc_user_id' => '18', 'tc_discord_id' => '987654321012345678', 'tc_mode' => 'live', 'tc_plan' => 'lifetime' ) as $tc_key => $tc_bad ) {
	$tc_routes = $tc_base_routes; $tc_routes['GET subscriptions/sub_monthly']['metadata'][ $tc_key ] = $tc_bad;
	$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_event ) ), 'Canonical identity rebinding fails: ' . $tc_key );
}
$tc_routes = $tc_base_routes; $tc_routes['GET subscriptions/sub_monthly']['items']['data'][0]['quantity'] = 2;
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_event ) ), 'A changed subscription quantity cannot grant access.' );
$tc_routes = $tc_base_routes; $tc_routes['GET invoices?subscription=sub_monthly&status=paid&limit=100']['data'][0]['amount_paid'] = 0;
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_event ) ), 'A paid flag with no actual payment cannot grant access.' );
$tc_routes = $tc_base_routes; $tc_routes['GET payment_intents/pi_monthly']['status'] = 'processing';
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_event ) ), 'An unsettled payment intent cannot grant access.' );
$tc_routes = $tc_base_routes; $tc_routes['GET invoices?subscription=sub_monthly&status=paid&limit=100']['has_more'] = true;
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_event ) ), 'Incomplete invoice history is an explicit diagnostic, never an assumed paid state.' );
$tc_routes = $tc_base_routes; $tc_routes['GET invoices?subscription=sub_monthly&status=paid&limit=100']['data'][0]['lines']['data'][0]['period']['end'] += 366 * DAY_IN_SECONDS;
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_event ) ), 'A malformed monthly invoice cannot grant a year of access.' );
$tc_routes = $tc_base_routes; $tc_routes['GET charges/ch_monthly']['captured'] = false;
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_event ) ), 'An uncaptured authorization cannot grant access.' );
$tc_routes = $tc_base_routes; $tc_routes['GET charges/ch_monthly']['amount_refunded'] = 100;
$tc_partial = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( ! is_wp_error( $tc_partial ) && 'active' === $tc_partial['entitlement']['status'], 'Partial refunds retain the paid source.' );
$tc_routes['GET charges/ch_monthly']['amount_refunded'] = 2999; $tc_routes['GET charges/ch_monthly']['refunded'] = true;
$tc_refund = TC_Billing_Stripe::reconcile( array_replace( $tc_event, array( 'type' => 'charge.refunded', 'object_id' => 'ch_monthly' ) ) );
$tc_assert( ! is_wp_error( $tc_refund ) && 'revoked' === $tc_refund['entitlement']['status'] && $tc_result['entitlement']['source'] === $tc_refund['entitlement']['source'], 'A full refund revokes only the affected provider source.' );
$tc_routes = $tc_base_routes; $tc_routes['GET charges/ch_monthly']['disputed'] = true;
$tc_dispute = array( 'id' => 'dp_fixture', 'livemode' => false, 'charge' => 'ch_monthly', 'status' => 'under_review' );
$tc_routes['GET disputes/dp_fixture'] = $tc_dispute;
$tc_routes['GET disputes?charge=ch_monthly&limit=100'] = array( 'data' => array( $tc_dispute ), 'has_more' => false );
$tc_disputed = TC_Billing_Stripe::reconcile( array_replace( $tc_event, array( 'type' => 'charge.dispute.created', 'object_id' => 'dp_fixture' ) ) );
$tc_assert( ! is_wp_error( $tc_disputed ) && 'disputed' === $tc_disputed['entitlement']['status'], 'Open canonical disputes suspend only the affected source.' );
$tc_routes['GET disputes?charge=ch_monthly&limit=100']['data'][0]['status'] = 'won';
$tc_won = TC_Billing_Stripe::reconcile( array_replace( $tc_event, array( 'type' => 'charge.dispute.closed', 'object_id' => 'dp_fixture' ) ) );
$tc_assert( ! is_wp_error( $tc_won ) && 'active' === $tc_won['entitlement']['status'], 'A won dispute recovers the canonical paid source even with an older payload.' );
$tc_routes['GET disputes?charge=ch_monthly&limit=100']['data'][0]['status'] = 'lost';
$tc_lost = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( ! is_wp_error( $tc_lost ) && 'revoked' === $tc_lost['entitlement']['status'], 'A lost dispute revokes that provider source.' );

// A delayed old refund cannot revoke a later settled monthly service period.
$tc_routes = $tc_base_routes;
$tc_old_invoice = $tc_invoice; $tc_old_invoice['id'] = 'in_old'; $tc_old_invoice['payment_intent'] = 'pi_old';
$tc_old_invoice['lines']['data'][0]['period'] = array( 'start' => $tc_start - 30 * DAY_IN_SECONDS, 'end' => $tc_start );
$tc_old_pi = $tc_pi; $tc_old_pi['id'] = 'pi_old'; $tc_old_pi['invoice'] = 'in_old'; $tc_old_pi['latest_charge'] = 'ch_old';
$tc_old_charge = $tc_charge; $tc_old_charge['id'] = 'ch_old'; $tc_old_charge['invoice'] = 'in_old'; $tc_old_charge['payment_intent'] = 'pi_old'; $tc_old_charge['amount_refunded'] = 2999; $tc_old_charge['refunded'] = true;
$tc_routes['GET invoices/in_old'] = $tc_old_invoice;
$tc_routes['GET invoices?subscription=sub_monthly&status=paid&limit=100']['data'][] = $tc_old_invoice;
$tc_routes['GET payment_intents/pi_old'] = $tc_old_pi; $tc_routes['GET charges/ch_old'] = $tc_old_charge;
$tc_old_refund = TC_Billing_Stripe::reconcile( array_replace( $tc_event, array( 'type' => 'charge.refunded', 'object_id' => 'ch_old' ) ) );
$tc_assert( ! is_wp_error( $tc_old_refund ) && 'active' === $tc_old_refund['entitlement']['status'] && $tc_result['entitlement']['valid_until'] === $tc_old_refund['entitlement']['valid_until'], 'A delayed old refund preserves a newer independently paid cycle.' );

$tc_routes = $tc_base_routes;
$tc_routes['POST subscriptions/sub_monthly'] = array_replace( $tc_sub, array( 'cancel_at_period_end' => true ) );
$tc_cancel = TC_Billing_Stripe::cancel( $tc_order );
$tc_assert( ! is_wp_error( $tc_cancel ) && true === $tc_cancel['cancel_at_period_end'], 'Cancellation schedules period-end cancellation.' );
$tc_cancel_call = end( $tc_http_calls ); parse_str( $tc_cancel_call[1]['body'], $tc_cancel_body );
$tc_assert( array( 'cancel_at_period_end' => 'true' ) === $tc_cancel_body && 'POST' === $tc_cancel_call[1]['method'], 'Cancellation never deletes the subscription or changes the paid period.' );
$tc_routes['POST billing_portal/sessions'] = array( 'url' => 'https://billing.stripe.com/p/session/test_fixture' );
$tc_assert( 'https://billing.stripe.com/p/session/test_fixture' === TC_Billing_Stripe::portal( $tc_order ), 'Verified stored customer opens the pinned hosted customer portal.' );
$tc_routes['POST billing_portal/sessions']['url'] = 'https://billing.stripe.com@attacker.example/';
$tc_assert( is_wp_error( TC_Billing_Stripe::portal( $tc_order ) ), 'Portal URLs reject URL credentials and foreign hosts.' );
$tc_routes['GET subscriptions/sub_monthly']['metadata']['tc_discord_id'] = '999999999999999999';
$tc_assert( is_wp_error( TC_Billing_Stripe::cancel( $tc_order ) ) && is_wp_error( TC_Billing_Stripe::portal( $tc_order ) ), 'Management actions revalidate the canonical identity before writes.' );

// Lifetime uses a separately bound one-time Checkout + succeeded PI + captured charge.
$tc_life_uuid = 'b2222222-2222-4222-8222-222222222222';
$tc_life_order = array_replace( $tc_order, array( 'id' => $tc_life_uuid, 'plan' => 'lifetime', 'amount' => 8000, 'checkout_id' => 'cs_test_lifetime', 'subscription_id' => '', 'payment_id' => 'pi_lifetime', 'customer_id' => 'cus_lifetime' ) );
$tc_life_meta = array_replace( $tc_meta, array( 'tc_order_id' => $tc_life_uuid, 'tc_plan' => 'lifetime' ) );
$tc_life_price = array_replace( $tc_price, array( 'id' => 'price_lifetime', 'product' => 'prod_lifetime', 'unit_amount' => 8000, 'type' => 'one_time', 'recurring' => null ) );
$tc_life_session = array_replace( $tc_session, array( 'id' => 'cs_test_lifetime', 'metadata' => $tc_life_meta, 'client_reference_id' => $tc_life_uuid, 'mode' => 'payment', 'amount_total' => 8000, 'customer' => 'cus_lifetime', 'subscription' => null, 'payment_intent' => 'pi_lifetime', 'line_items' => array( 'has_more' => false, 'data' => array( array( 'quantity' => 1, 'amount_total' => 8000, 'price' => $tc_life_price ) ) ) ) );
$tc_life_pi = array_replace( $tc_pi, array( 'id' => 'pi_lifetime', 'metadata' => $tc_life_meta, 'customer' => 'cus_lifetime', 'invoice' => null, 'amount' => 8000, 'amount_received' => 8000, 'latest_charge' => 'ch_lifetime' ) );
$tc_life_charge = array_replace( $tc_charge, array( 'id' => 'ch_lifetime', 'customer' => 'cus_lifetime', 'invoice' => null, 'payment_intent' => 'pi_lifetime', 'amount' => 8000 ) );
TC_Billing_Store::$orders[ $tc_life_uuid ] = $tc_life_order;
TC_Billing_Store::$orders[ $tc_life_uuid ]['checkout_id'] = '';
$tc_routes = $tc_base_routes + array(
	'GET prices/price_lifetime' => $tc_life_price,
	'GET products/prod_lifetime' => array( 'id' => 'prod_lifetime', 'livemode' => false, 'active' => true ),
	'GET checkout/sessions/cs_test_lifetime?expand%5B0%5D=line_items.data.price' => $tc_life_session,
	'GET payment_intents/pi_lifetime' => $tc_life_pi,
	'GET charges/ch_lifetime' => $tc_life_charge,
);
$tc_routes['POST checkout/sessions'] = array_replace( $tc_life_session, array( 'status' => 'open' ) );
$tc_life_created = TC_Billing_Stripe::create_checkout( $tc_life_order );
$tc_assert( ! is_wp_error( $tc_life_created ), 'Lifetime creates a validated one-time hosted Checkout.' );
$tc_life_post = end( $tc_http_calls ); parse_str( $tc_life_post[1]['body'], $tc_life_body );
$tc_assert( 'payment' === $tc_life_body['mode'] && $tc_life_meta === $tc_life_body['payment_intent_data']['metadata'], 'Lifetime payment intent inherits exact stored identity.' );
TC_Billing_Store::$orders[ $tc_life_uuid ] = $tc_life_order;
$tc_life_event = array( 'provider' => 'stripe', 'mode' => 'test', 'type' => 'checkout.session.completed', 'object_id' => 'cs_test_lifetime' );
$tc_life_result = TC_Billing_Stripe::reconcile( $tc_life_event );
$tc_assert( ! is_wp_error( $tc_life_result ) && 'active' === $tc_life_result['entitlement']['status'] && null === $tc_life_result['entitlement']['valid_until'] && 'stripe:test:payment:pi_lifetime' === $tc_life_result['entitlement']['source'], 'Settled Lifetime access has an independent source and no expiry.' );
$tc_routes['GET checkout/sessions/cs_test_lifetime?expand%5B0%5D=line_items.data.price']['payment_status'] = 'unpaid';
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_life_event ) ), 'A Lifetime return/session marker cannot override canonical unpaid Checkout state.' );
$tc_routes['GET checkout/sessions/cs_test_lifetime?expand%5B0%5D=line_items.data.price'] = $tc_life_session;
$tc_routes['GET payment_intents/pi_lifetime']['status'] = 'processing';
$tc_life_pending = TC_Billing_Stripe::reconcile( $tc_life_event );
$tc_assert( ! is_wp_error( $tc_life_pending ) && ! isset( $tc_life_pending['entitlement'] ), 'A processing Lifetime payment never grants or removes an entitlement.' );
$tc_routes['GET payment_intents/pi_lifetime'] = $tc_life_pi;
$tc_routes['GET checkout/sessions/cs_test_lifetime?expand%5B0%5D=line_items.data.price']['line_items']['data'][0]['price']['currency'] = 'usd';
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_life_event ) ), 'Lifetime rejects a tampered canonical line currency.' );
$tc_routes['GET checkout/sessions/cs_test_lifetime?expand%5B0%5D=line_items.data.price'] = $tc_life_session;
$tc_routes['GET charges/ch_lifetime']['amount_refunded'] = 8000; $tc_routes['GET charges/ch_lifetime']['refunded'] = true;
$tc_life_refund = TC_Billing_Stripe::reconcile( array_replace( $tc_life_event, array( 'type' => 'charge.refunded', 'object_id' => 'ch_lifetime' ) ) );
$tc_assert( ! is_wp_error( $tc_life_refund ) && 'revoked' === $tc_life_refund['entitlement']['status'] && $tc_life_refund['entitlement']['source'] !== $tc_result['entitlement']['source'], 'Refunded Lifetime cannot erase Monthly or operator sources.' );
$tc_routes['GET payment_intents/pi_lifetime']['metadata']['tc_user_id'] = '18';
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( $tc_life_event ) ), 'Lifetime PI metadata cannot rebind the stored user.' );
$tc_routes = $tc_base_routes;
$tc_routes['GET subscriptions/sub_monthly'] = static fn(): WP_Error => new WP_Error( 'transport_failure', 'Sensitive upstream text must not escape.' );
$tc_network_error = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( is_wp_error( $tc_network_error ) && 'stripe_network' === $tc_network_error->get_error_code(), 'Provider failures become safe redacted retryable errors.' );
$tc_routes['GET subscriptions/sub_monthly'] = static fn(): array => array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '120' ), 'body' => '{"error":"private"}' );
$tc_rate_error = TC_Billing_Stripe::reconcile( $tc_event );
$tc_assert( is_wp_error( $tc_rate_error ) && 'stripe_rate_limited' === $tc_rate_error->get_error_code() && 120 === $tc_rate_error->get_error_data()['retry_after'], 'Rate limits preserve a bounded retry delay without leaking provider bodies.' );
$tc_assert( is_wp_error( TC_Billing_Stripe::reconcile( array_replace( $tc_event, array( 'type' => 'customer.created', 'object_id' => 'cus_ignored' ) ) ) ), 'Unsupported event processing is a safe explicit ignore result.' );

file_put_contents( 'php://stdout', sprintf( "Stripe isolated integration: %d assertions passed; all HTTP mocked, no database or payment.\n", $tc_checks ) );
