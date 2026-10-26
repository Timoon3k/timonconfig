<?php
/**
 * Standalone integration harness: php tests/billing-paypal-integration.php
 * All WordPress HTTP and private ledger calls are in-memory doubles. No database,
 * credentials, web server, or real PayPal endpoint is used. Not a sandbox smoke test.
 */
if ( defined( 'ABSPATH' ) ) { throw new RuntimeException( 'Run this test with standalone PHP, outside WordPress.' ); }
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
final class WP_Error {
	private string $code;
	private array $data = array();
	public function __construct( string $code, string $message ) { $this->code = $code; }
	public function get_error_code(): string { return $this->code; }
	public function add_data( array $data ): void { $this->data = $data; }
	public function get_error_data(): array { return $this->data; }
}
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function home_url( string $path = '' ): string { return 'https://localhost' . $path; }
function add_query_arg( array $query, string $url ): string { return $url . '?' . http_build_query( $query ); }
function wp_parse_url( string $url, int $component = -1 ) { return parse_url( $url, $component ); }
function wp_json_encode( $value ): string { return json_encode( $value, JSON_THROW_ON_ERROR ); }
function get_transient( string $key ) { return $GLOBALS['pp_transients'][ $key ] ?? false; }
function set_transient( string $key, $value, int $ttl ): void { $GLOBALS['pp_transients'][ $key ] = $value; }
function wp_remote_retrieve_response_code( array $response ): int { return $response['response']['code']; }
function wp_remote_retrieve_body( array $response ): string { return $response['body']; }
function wp_remote_retrieve_header( array $response, string $key ): string { return $response['headers'][ $key ] ?? ''; }
function pp_response( $body, int $code = 200, array $headers = array() ): array { return array( 'response' => array( 'code' => $code ), 'headers' => $headers, 'body' => null === $body ? '' : json_encode( $body, JSON_THROW_ON_ERROR ) ); }
function wp_remote_request( string $url, array $args ) {
	if ( ! str_starts_with( $url, 'https://api-m.sandbox.paypal.com/' ) ) { throw new RuntimeException( 'Unpinned or live URL: ' . $url ); }
	if ( 0 !== $args['redirection'] || true !== $args['sslverify'] ) { throw new RuntimeException( 'Unsafe HTTP options.' ); }
	$GLOBALS['pp_requests'][] = array( $url, $args );
	$path = (string) parse_url( $url, PHP_URL_PATH );
	if ( '/v1/oauth2/token' === $path ) {
		if ( 'grant_type=client_credentials' !== $args['body'] ) { throw new RuntimeException( 'Wrong OAuth grant.' ); }
		return pp_response( array( 'access_token' => 'mock-server-only-token', 'token_type' => 'Bearer', 'expires_in' => 3600 ) );
	}
	if ( 'Bearer mock-server-only-token' !== ( $args['headers']['Authorization'] ?? '' ) ) { throw new RuntimeException( 'Missing server-side access token.' ); }
	$handler = $GLOBALS['pp_routes'][ $args['method'] . ' ' . $path ] ?? null;
	if ( null === $handler ) { return pp_response( array(), 404 ); }
	return is_callable( $handler ) ? $handler( $url, $args ) : $handler;
}
final class TC_Billing {
	public static array $settings = array( 'paypal_enabled' => true, 'stripe_test_confirmed' => true );
	public static bool $allowed = true;
	public static function config(): array { return self::$settings; }
	public static function mode_allowed( string $mode ): bool { return self::$allowed && 'test' === $mode; }
	public static function secret( string $name, string $mode ): string { return 'PAYPAL_CLIENT_ID' === $name ? 'mock-client' : 'mock-secret'; }
	public static function setting( string $key, string $mode ): string { return array( 'paypal_monthly_plan_id' => 'P-MONTHLY29', 'paypal_monthly_product_id' => 'PROD-MONTHLY29', 'paypal_webhook_id' => 'WH-CONFIGURED1' )[ $key ] ?? ''; }
	public static function error( string $code, string $message ): WP_Error { return new WP_Error( $code, $message ); }
}
final class TC_Billing_Store {
	public static array $orders = array();
	public static array $entitlements = array();
	public static bool $active_error = false;
	public static function order( string $id ): ?array { return self::$orders[ $id ] ?? null; }
	public static function entitlement( string $source ): ?array { return self::$entitlements[ $source ] ?? null; }
	public static function active( int $uid, string $mode ): array|WP_Error {
		if ( self::$active_error ) { return new WP_Error( 'fixture_ledger_unavailable', 'PRIVATE_LEDGER_FAILURE' ); }
		return array_values( array_filter( self::$entitlements, static fn( array $source ): bool => (int) $source['user_id'] === $uid && $source['mode'] === $mode && 'active' === $source['status'] && ( null === $source['valid_until'] || strtotime( $source['valid_until'] . ' UTC' ) > time() ) ) );
	}
	public static function find_order( string $provider, string $mode, string $id ): ?array {
		foreach ( self::$orders as $order ) { if ( $provider === $order['provider'] && $mode === $order['mode'] && in_array( $id, array( $order['checkout_id'], $order['subscription_id'], $order['payment_id'] ), true ) ) { return $order; } }
		return null;
	}
	public static function apply( array $transition ): void {
		if ( empty( $transition['order_id'] ) ) { throw new RuntimeException( 'Transition missing order.' ); }
		self::$orders[ $transition['order_id'] ] = array_merge( self::$orders[ $transition['order_id'] ], $transition['order_fields'] );
		if ( isset( $transition['entitlement'] ) ) {
			$ent = $transition['entitlement'];
			$allowed = array( 'source', 'user_id', 'discord_id', 'provider', 'mode', 'plan', 'status', 'valid_until', 'external_id', 'order_id', 'customer_id', 'payment_id', 'last_invoice_id', 'cancel_at_period_end', 'next_payment_at', 'next_check_at' );
			if ( array_diff( array_keys( $ent ), $allowed ) ) { throw new RuntimeException( 'Non-contract entitlement fields.' ); }
			self::$entitlements[ $ent['source'] ] = $ent;
		}
	}
}
$adapter = $argv[1] ?? dirname( __DIR__ ) . '/wp-content/plugins/timonconfigs-core/includes/class-tc-billing-paypal.php';
require $adapter;
$checks = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; };
$error = static function ( $value, string $code, string $message ) use ( $assert ): void { $assert( is_wp_error( $value ) && 'tc_paypal_' . $code === $value->get_error_code(), $message ); };
$good = static function ( $value ) { if ( is_wp_error( $value ) ) { throw new RuntimeException( 'Unexpected ' . $value->get_error_code() ); } return $value; };
$GLOBALS['pp_requests'] = array();
$GLOBALS['pp_routes'] = array();
$GLOBALS['pp_transients'] = array();
$lifetime_id = '11111111-1111-4111-8111-111111111111';
$monthly_id = '22222222-2222-4222-8222-222222222222';
$make_order = static function ( string $id, string $plan ): array { return array( 'id' => $id, 'user_id' => 27, 'discord_id' => '123456789012345678', 'provider' => 'paypal', 'mode' => 'test', 'plan' => $plan, 'amount' => 'monthly' === $plan ? 2999 : 8000, 'currency' => 'eur', 'checkout_id' => '', 'subscription_id' => '', 'payment_id' => '', 'status' => 'pending' ); };
TC_Billing_Store::$orders[ $lifetime_id ] = $make_order( $lifetime_id, 'lifetime' );
TC_Billing_Store::$orders[ $monthly_id ] = $make_order( $monthly_id, 'monthly' );
$lifetime_binding = 'tc:' . $lifetime_id . ':27:123456789012345678';
$monthly_binding = 'tc:' . $monthly_id . ':27:123456789012345678';
$checkout = array( 'id' => 'ORDER-LIFETIME', 'intent' => 'CAPTURE', 'status' => 'CREATED', 'purchase_units' => array( array( 'reference_id' => $lifetime_id, 'custom_id' => $lifetime_binding, 'amount' => array( 'currency_code' => 'EUR', 'value' => '80.00' ), 'items' => array( array( 'sku' => 'tc-lifetime', 'quantity' => '1', 'unit_amount' => array( 'currency_code' => 'EUR', 'value' => '80.00' ) ) ) ) ), 'links' => array( array( 'rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-LIFETIME' ) ) );
$GLOBALS['pp_routes']['POST /v2/checkout/orders'] = static function ( $url, $args ) use ( &$checkout, $assert, $lifetime_binding, $lifetime_id ) {
	$body = json_decode( $args['body'], true );
	$assert( '80.00' === $body['purchase_units'][0]['amount']['value'] && 'EUR' === $body['purchase_units'][0]['amount']['currency_code'], 'Lifetime server fixes EUR80.' );
	$assert( $lifetime_binding === $body['purchase_units'][0]['custom_id'], 'Create binds exact stored user and Discord identity.' );
	$assert( 'tc-' . substr( hash( 'sha256', 'paypal:test:' . $lifetime_id . ':create' ), 0, 32 ) === $args['headers']['PayPal-Request-Id'], 'Create idempotency derives from UUID.' );
	$assert( str_starts_with( $body['application_context']['return_url'], 'https://localhost/account/?tc_billing_return=paypal&tc_billing_order=' ), 'Return uses controlled account route.' );
	return pp_response( $checkout, 201 );
};
TC_Billing::$settings['stripe_test_confirmed'] = false;
$error( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $lifetime_id ] ), 'disabled', 'Stripe real-test confirmation gates PayPal.' );
$assert( 0 === count( $GLOBALS['pp_requests'] ), 'Disabled adapter performs no OAuth or provider call.' );
TC_Billing::$settings['stripe_test_confirmed'] = true;
TC_Billing::$allowed = false;
$error( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $lifetime_id ] ), 'disabled', 'Environment mode blocks writes.' );
TC_Billing::$allowed = true;
$forged = TC_Billing_Store::$orders[ $lifetime_id ]; $forged['amount'] = 1;
$error( TC_Billing_PayPal::create_checkout( $forged ), 'order_binding', 'Browser amount cannot replace stored amount.' );
$result = $good( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $lifetime_id ] ) );
$assert( 'ORDER-LIFETIME' === $result['id'] && ! isset( $result['access_token'] ), 'Checkout exposes only ID and hosted approval URL.' );
$good( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $lifetime_id ] ) );
$create_requests = array_values( array_filter( $GLOBALS['pp_requests'], static fn( $request ) => '/v2/checkout/orders' === parse_url( $request[0], PHP_URL_PATH ) ) );
$assert( $create_requests[0][1]['headers']['PayPal-Request-Id'] === $create_requests[1][1]['headers']['PayPal-Request-Id'], 'Repeated create reuses provider idempotency key.' );
$assert( 35 === strlen( $create_requests[0][1]['headers']['PayPal-Request-Id'] ), 'Request IDs satisfy Checkout v2 maximum38 characters.' );
$checkout['links'][0]['href'] = 'https://www.sandbox.paypal.com.evil.example/checkoutnow';
$error( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $lifetime_id ] ), 'approval_url', 'Lookalike approval domain rejected.' );
$checkout['links'][0]['href'] = 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-LIFETIME';
TC_Billing_Store::$orders[ $lifetime_id ]['checkout_id'] = $checkout['id'];
$GLOBALS['pp_routes']['GET /v2/checkout/orders/ORDER-LIFETIME'] = static function () use ( &$checkout ) { return pp_response( $checkout ); };
$error( TC_Billing_PayPal::capture( TC_Billing_Store::$orders[ $lifetime_id ] ), 'capture_not_approved', 'Created orders cannot be captured.' );
$checkout['status'] = 'APPROVED';
$transition = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'CHECKOUT.ORDER.APPROVED', 'object_id' => $checkout['id'] ) ) );
$assert( ! isset( $transition['entitlement'] ), 'Approval alone never grants Lifetime access.' );
$GLOBALS['pp_routes']['POST /v2/checkout/orders/ORDER-LIFETIME/capture'] = static function ( $url, $args ) use ( &$checkout, $assert, $lifetime_id ) { $assert( 'tc-' . substr( hash( 'sha256', 'paypal:test:' . $lifetime_id . ':capture' ), 0, 32 ) === $args['headers']['PayPal-Request-Id'], 'Capture uses separate immutable UUID idempotency key.' ); return pp_response( $checkout, 201 ); };
$good( TC_Billing_PayPal::capture( TC_Billing_Store::$orders[ $lifetime_id ] ) );
$good( TC_Billing_PayPal::capture( TC_Billing_Store::$orders[ $lifetime_id ] ) );
$checkout['status'] = 'COMPLETED';
$checkout['purchase_units'][0]['payments']['captures'] = array( array( 'id' => 'CAPTURE-LIFETIME' ) );
$capture = array( 'id' => 'CAPTURE-LIFETIME', 'status' => 'COMPLETED', 'amount' => array( 'currency_code' => 'EUR', 'value' => '80.00' ), 'final_capture' => true, 'supplementary_data' => array( 'related_ids' => array( 'order_id' => 'ORDER-LIFETIME' ) ) );
$GLOBALS['pp_routes']['GET /v2/payments/captures/CAPTURE-LIFETIME'] = static function () use ( &$capture ) { return pp_response( $capture ); };
$event = array( 'provider' => 'paypal', 'mode' => 'test', 'type' => 'PAYMENT.CAPTURE.COMPLETED', 'object_id' => 'CAPTURE-LIFETIME' );
$transition = $good( TC_Billing_PayPal::reconcile( $event ) );
$assert( 'active' === $transition['entitlement']['status'] && null === $transition['entitlement']['valid_until'], 'Settled EUR80 canonical capture grants Lifetime.' );
TC_Billing_Store::apply( $transition );
$lifetime_source = $transition['entitlement']['source'];
$capture['amount']['value'] = '79.99';
$error( TC_Billing_PayPal::reconcile( $event ), 'capture_binding', 'Incorrect canonical capture amount rejected.' );
$capture['amount']['value'] = '80.00';
$checkout['purchase_units'][0]['custom_id'] = str_replace( ':27:', ':28:', $lifetime_binding );
$error( TC_Billing_PayPal::reconcile( $event ), 'metadata', 'Mismatched WordPress owner cannot grant another user.' );
$checkout['purchase_units'][0]['custom_id'] = $lifetime_binding;
$checkout['purchase_units'][0]['items'][0]['quantity'] = '2';
$error( TC_Billing_PayPal::reconcile( $event ), 'checkout_product', 'Unexpected quantity rejected.' );
$checkout['purchase_units'][0]['items'][0]['quantity'] = '1';
$capture['status'] = 'PARTIALLY_REFUNDED';
$assert( 'active' === $good( TC_Billing_PayPal::reconcile( $event ) )['entitlement']['status'], 'Partial refund retains independent Lifetime grant.' );
$capture['status'] = 'REFUNDED';
$GLOBALS['pp_routes']['GET /v2/payments/refunds/REFUND-LIFETIME'] = pp_response( array( 'id' => 'REFUND-LIFETIME', 'links' => array( array( 'rel' => 'up', 'href' => 'https://api-m.sandbox.paypal.com/v2/payments/captures/CAPTURE-LIFETIME' ) ) ) );
$refunded = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'PAYMENT.CAPTURE.REFUNDED', 'object_id' => 'REFUND-LIFETIME' ) ) );
$assert( 'revoked' === $refunded['entitlement']['status'] && $lifetime_source === $refunded['entitlement']['source'], 'Full refund revokes only affected capture source.' );
$capture['status'] = 'COMPLETED';
$error( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'PAYMENT.CAPTURE.REVERSED', 'object_id' => 'CAPTURE-LIFETIME' ) ), 'reversal_unproven', 'Reversal without canonical provider proof retries without inventing a revocation.' );
$dispute = array( 'dispute_id' => 'PP-DISPUTE1', 'status' => 'OPEN', 'disputed_transactions' => array( array( 'seller_transaction_id' => 'CAPTURE-LIFETIME' ) ) );
$GLOBALS['pp_routes']['GET /v1/customer/disputes/PP-DISPUTE1'] = static function () use ( &$dispute ) { return pp_response( $dispute ); };
$disputed = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'CUSTOMER.DISPUTE.CREATED', 'object_id' => 'PP-DISPUTE1' ) ) );
$assert( 'disputed' === $disputed['entitlement']['status'], 'Canonical OPEN chargeback removes affected access.' );
TC_Billing_Store::apply( $disputed );
$assert( 'disputed' === $good( TC_Billing_PayPal::reconcile( $event ) )['entitlement']['status'], 'Out-of-order completion cannot clear unresolved chargeback.' );
$dispute['status'] = 'RESOLVED'; $dispute['dispute_outcome']['outcome_code'] = 'RESOLVED_SELLER_FAVOUR';
$restored = $good( TC_Billing_PayPal::reconcile( $event ) );
$assert( 'active' === $restored['entitlement']['status'] && null === $restored['entitlement']['last_invoice_id'], 'Canonical seller resolution clears dispute restriction.' );
TC_Billing_Store::apply( $restored );
$plan = array( 'id' => 'P-MONTHLY29', 'product_id' => 'PROD-MONTHLY29', 'status' => 'ACTIVE', 'billing_cycles' => array( array( 'tenure_type' => 'REGULAR', 'sequence' => 1, 'total_cycles' => 0, 'frequency' => array( 'interval_unit' => 'MONTH', 'interval_count' => 1 ), 'pricing_scheme' => array( 'fixed_price' => array( 'currency_code' => 'EUR', 'value' => '29.99' ) ) ) ) );
$GLOBALS['pp_routes']['GET /v1/billing/plans/P-MONTHLY29'] = static function () use ( &$plan ) { return pp_response( $plan ); };
$GLOBALS['pp_routes']['GET /v1/catalogs/products/PROD-MONTHLY29'] = pp_response( array( 'id' => 'PROD-MONTHLY29', 'type' => 'SERVICE' ) );
$paid_at = time() - 5 * DAY_IN_SECONDS;
$paid_time = gmdate( 'Y-m-d\TH:i:s\Z', $paid_at );
$next_time = gmdate( 'Y-m-d\TH:i:s\Z', time() + 20 * DAY_IN_SECONDS );
$subscription = array( 'id' => 'I-MONTHLY29', 'plan_id' => 'P-MONTHLY29', 'custom_id' => $monthly_binding, 'quantity' => '1', 'status' => 'ACTIVE', 'billing_info' => array( 'last_payment' => array( 'amount' => array( 'currency_code' => 'EUR', 'value' => '29.99' ), 'time' => $paid_time ), 'next_billing_time' => $next_time ), 'links' => array( array( 'rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=mock' ) ) );
$GLOBALS['pp_routes']['POST /v1/billing/subscriptions'] = static function ( $url, $args ) use ( &$subscription, $assert, $monthly_binding ) { $body = json_decode( $args['body'], true ); $assert( 'P-MONTHLY29' === $body['plan_id'] && $monthly_binding === $body['custom_id'] && ! isset( $body['plan'] ), 'Subscription uses fixed configured plan and identity.' ); return pp_response( $subscription, 201 ); };
$monthly_checkout = $good( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $monthly_id ] ) );
$assert( 'I-MONTHLY29' === $monthly_checkout['subscription_id'], 'Monthly create returns bound subscription.' );
$plan['billing_cycles'][0]['frequency']['interval_unit'] = 'DAY';
$error( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $monthly_id ] ), 'plan_price', 'Non-monthly recurring plan rejected.' );
$plan['billing_cycles'][0]['frequency']['interval_unit'] = 'MONTH';
$plan['billing_cycles'][0]['pricing_scheme']['fixed_price']['currency_code'] = 'USD';
$error( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $monthly_id ] ), 'plan_price', 'Wrong recurring currency rejected.' );
$plan['billing_cycles'][0]['pricing_scheme']['fixed_price']['currency_code'] = 'EUR';
TC_Billing_Store::$orders[ $monthly_id ]['subscription_id'] = 'I-MONTHLY29';
TC_Billing_Store::$orders[ $monthly_id ]['checkout_id'] = 'I-MONTHLY29';
$GLOBALS['pp_routes']['GET /v1/billing/subscriptions/I-MONTHLY29'] = static function () use ( &$subscription ) { return pp_response( $subscription ); };
$plan['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] = '39.99';
$count_before = count( $GLOBALS['pp_requests'] );
$error( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $monthly_id ] ), 'plan_price', 'Resuming an existing subscription cannot approve changed recurring pricing.' );
$new_requests = array_slice( $GLOBALS['pp_requests'], $count_before );
$assert( 1 === count( $new_requests ) && '/v1/billing/plans/P-MONTHLY29' === parse_url( $new_requests[0][0], PHP_URL_PATH ), 'Changed plan blocks resume before subscription lookup or provider write.' );
$plan['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] = '29.99';
$resumed = $good( TC_Billing_PayPal::create_checkout( TC_Billing_Store::$orders[ $monthly_id ] ) );
$assert( 'I-MONTHLY29' === $resumed['id'], 'An unchanged fixed monthly plan can resume its existing approval.' );
$transactions = array();
$GLOBALS['pp_routes']['GET /v1/billing/subscriptions/I-MONTHLY29/transactions'] = static function () use ( &$transactions ) { return pp_response( array( 'transactions' => $transactions ) ); };
$monthly_event = array( 'provider' => 'paypal', 'mode' => 'test', 'type' => 'BILLING.SUBSCRIPTION.ACTIVATED', 'object_id' => 'I-MONTHLY29' );
$assert( ! isset( $good( TC_Billing_PayPal::reconcile( $monthly_event ) )['entitlement'] ), 'ACTIVE subscription without settled payment never grants.' );
$transactions[] = array( 'id' => 'SALE-MONTHLY1', 'status' => 'COMPLETED', 'time' => $paid_time, 'amount_with_breakdown' => array( 'gross_amount' => array( 'currency_code' => 'EUR', 'value' => '29.99' ) ) );
$sale = array( 'id' => 'SALE-MONTHLY1', 'billing_agreement_id' => 'I-MONTHLY29', 'amount' => array( 'currency' => 'EUR', 'total' => '29.99' ), 'state' => 'completed', 'create_time' => $paid_time );
$GLOBALS['pp_routes']['GET /v1/payments/sale/SALE-MONTHLY1'] = static function () use ( &$sale ) { return pp_response( $sale ); };
$monthly = $good( TC_Billing_PayPal::reconcile( $monthly_event ) );
$monthly_source = $monthly['entitlement']['source'];
$paid_until = $monthly['entitlement']['valid_until'];
$assert( 'active' === $monthly['entitlement']['status'] && gmdate( 'Y-m-d H:i:s', strtotime( $next_time ) ) === $paid_until, 'Verified recurring sale grants period capped by next billing date.' );
TC_Billing_Store::apply( $monthly );
$subscription['status'] = 'CANCELLED'; unset( $subscription['billing_info']['next_billing_time'] );
$cancelled = $good( TC_Billing_PayPal::reconcile( $monthly_event ) );
$assert( 'canceled' === $cancelled['order_fields']['status'] && 'active' === $cancelled['entitlement']['status'] && 1 === $cancelled['entitlement']['cancel_at_period_end'] && $cancelled['entitlement']['valid_until'] === $paid_until, 'Permanent cancellation marks contract stopped and retains verified prepaid period.' );
TC_Billing_Store::apply( $cancelled );
$preserved_end = $cancelled['entitlement']['valid_until'];
$subscription['status'] = 'SUSPENDED';
$suspended = $good( TC_Billing_PayPal::reconcile( $monthly_event ) );
$assert( ! in_array( $suspended['order_fields']['status'], array( 'canceled', 'cancelled' ), true ) && 0 === $suspended['order_fields']['cancel_at_period_end'], 'Suspended subscription is not treated as permanently stopped.' );
$assert( 'active' === $suspended['entitlement']['status'] && $preserved_end === $suspended['entitlement']['valid_until'], 'Suspension preserves the independently verified prepaid period.' );
$subscription['status'] = 'EXPIRED';
$ended = $good( TC_Billing_PayPal::reconcile( $monthly_event ) );
$assert( 'canceled' === $ended['order_fields']['status'] && 'active' === $ended['entitlement']['status'] && $preserved_end === $ended['entitlement']['valid_until'], 'Canonical permanent expiry stops renewals without erasing a future prepaid period.' );
$subscription['status'] = 'ACTIVE';
$transactions = array( array( 'id' => 'SALE-PENDING1', 'status' => 'PENDING', 'time' => gmdate( 'Y-m-d\TH:i:s\Z' ) ) );
TC_Billing_Store::$entitlements[ $monthly_source ]['id'] = 19;
TC_Billing_Store::$entitlements[ $monthly_source ]['created_at'] = '2026-01-01 00:00:00';
$pending = $good( TC_Billing_PayPal::reconcile( $monthly_event ) );
$assert( $preserved_end === $pending['entitlement']['valid_until'] && ! isset( $pending['entitlement']['id'] ), 'Pending renewal preserves exact prepaid end and strips ledger-only columns.' );
$assert( 'pending' === $pending['order_fields']['status'] && 0 === $pending['order_fields']['cancel_at_period_end'], 'Pending payment on an active contract is not permanent cancellation.' );
TC_Billing_Store::apply( $pending );
$transactions[0]['status'] = 'DENIED';
$failed = $good( TC_Billing_PayPal::reconcile( $monthly_event ) );
$assert( 'pending' === $failed['order_fields']['status'] && 'active' === $failed['entitlement']['status'] && $preserved_end === $failed['entitlement']['valid_until'], 'Failed renewal remains unresolved and preserves prepaid access.' );
$subscription['status'] = 'CANCELLED';
$transactions = array( array( 'id' => 'SALE-MONTHLY1', 'status' => 'COMPLETED', 'time' => $paid_time, 'amount_with_breakdown' => array( 'gross_amount' => array( 'currency_code' => 'EUR', 'value' => '29.99' ) ) ) );
$sale['state'] = 'refunded';
$GLOBALS['pp_routes']['GET /v1/payments/refund/REFUND-MONTHLY1'] = pp_response( array( 'id' => 'REFUND-MONTHLY1', 'sale_id' => 'SALE-MONTHLY1' ) );
$monthly_refund = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'PAYMENT.SALE.REFUNDED', 'object_id' => 'REFUND-MONTHLY1' ) ) );
$assert( 'revoked' === $monthly_refund['entitlement']['status'] && $monthly_source === $monthly_refund['entitlement']['source'], 'Canonical subscription sale refund revokes only subscription source.' );
$assert( 'canceled' === $monthly_refund['order_fields']['status'] && 1 === $monthly_refund['entitlement']['cancel_at_period_end'], 'Refund does not erase canonical permanent-stop proof on a canceled contract.' );
$assert( 'active' === TC_Billing_Store::$entitlements[ $lifetime_source ]['status'], 'Monthly refund does not modify independent Lifetime grant.' );
$transactions[] = array( 'id' => 'SALE-MONTHLY2', 'status' => 'COMPLETED', 'time' => gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS ), 'amount_with_breakdown' => array( 'gross_amount' => array( 'currency_code' => 'EUR', 'value' => '29.99' ) ) );
$new_sale = array( 'id' => 'SALE-MONTHLY2', 'billing_agreement_id' => 'I-MONTHLY29', 'amount' => array( 'currency' => 'EUR', 'total' => '29.99' ), 'state' => 'completed', 'create_time' => $transactions[1]['time'] );
$GLOBALS['pp_routes']['GET /v1/payments/sale/SALE-MONTHLY2'] = pp_response( $new_sale );
$subscription['billing_info']['last_payment']['time'] = $new_sale['create_time'];
$old_refund = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'PAYMENT.SALE.REFUNDED', 'object_id' => 'REFUND-MONTHLY1' ) ) );
$assert( 'active' === $old_refund['entitlement']['status'] && 'SALE-MONTHLY2' === $old_refund['entitlement']['payment_id'], 'Old sale refund cannot erase a newer independently paid subscription period.' );
array_pop( $transactions );
$subscription['billing_info']['last_payment']['time'] = $paid_time;
$sale['state'] = 'completed';
$monthly_dispute = array( 'dispute_id' => 'PP-MONTHLY-DISPUTE', 'status' => 'OPEN', 'disputed_transactions' => array( array( 'seller_transaction_id' => 'SALE-MONTHLY1' ) ) );
$GLOBALS['pp_routes']['GET /v1/customer/disputes/PP-MONTHLY-DISPUTE'] = pp_response( $monthly_dispute );
$stopped_dispute = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'CUSTOMER.DISPUTE.CREATED', 'object_id' => 'PP-MONTHLY-DISPUTE' ) ) );
$assert( 'canceled' === $stopped_dispute['order_fields']['status'] && 'disputed' === $stopped_dispute['entitlement']['status'], 'Dispute tracks financial access independently from canonical stopped renewal status.' );
$sale['billing_agreement_id'] = 'I-SOMEONEELSE';
$error( TC_Billing_PayPal::reconcile( $monthly_event ), 'sale_binding', 'Sale for another subscription cannot grant.' );
$sale['billing_agreement_id'] = 'I-MONTHLY29';
$subscription['plan_overridden'] = true;
$error( TC_Billing_PayPal::reconcile( $monthly_event ), 'subscription_binding', 'Overridden subscription pricing cannot grant.' );
unset( $subscription['plan_overridden'] );
$assert( 'https://www.sandbox.paypal.com/myaccount/autopay/' === $good( TC_Billing_PayPal::portal( TC_Billing_Store::$orders[ $monthly_id ] ) ), 'PayPal management URL is pinned to mode.' );
$subscription['status'] = 'ACTIVE';
$GLOBALS['pp_routes']['POST /v1/billing/subscriptions/I-MONTHLY29/cancel'] = static function ( $url, $args ) use ( $assert, $monthly_id ) { $assert( 'tc-' . substr( hash( 'sha256', 'paypal:test:' . $monthly_id . ':cancel' ), 0, 32 ) === $args['headers']['PayPal-Request-Id'], 'Cancel is idempotent and server-owned.' ); return pp_response( null, 204 ); };
$cancel = $good( TC_Billing_PayPal::cancel( TC_Billing_Store::$orders[ $monthly_id ] ) );
$assert( 1 === $cancel['cancel_at_period_end'] && $preserved_end === TC_Billing_Store::$entitlements[ $monthly_source ]['valid_until'], 'Cancel request never directly truncates paid access.' );
$old_period = TC_Billing_Store::$entitlements[ $monthly_source ]['valid_until'];
TC_Billing_Store::$entitlements[ $monthly_source ]['valid_until'] = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
$saved_transactions = $transactions; $transactions = array();
$expired = $good( TC_Billing_PayPal::reconcile( $monthly_event ) );
$assert( 'expired' === $expired['entitlement']['status'], 'No new successful payment expires the existing period without extension.' );
$transactions = $saved_transactions;
TC_Billing_Store::$entitlements[ $monthly_source ]['valid_until'] = $old_period;
$headers = array( 'PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-MOCK', 'PAYPAL-TRANSMISSION-ID' => 'transmission-123', 'PAYPAL-TRANSMISSION-SIG' => 'mock-signature', 'PAYPAL-TRANSMISSION-TIME' => gmdate( 'Y-m-d\TH:i:s\Z' ) );
$notification = array( 'id' => 'WH-EVENT1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'create_time' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'resource' => array( 'id' => 'CAPTURE-LIFETIME' ) );
$verification = 'SUCCESS';
$GLOBALS['pp_routes']['POST /v1/notifications/verify-webhook-signature'] = static function ( $url, $args ) use ( &$verification, $assert ) { $body = json_decode( $args['body'], true ); $assert( 'WH-CONFIGURED1' === $body['webhook_id'] && isset( $body['webhook_event']['resource'] ), 'Remote verification sends configured webhook and exact parsed event.' ); return pp_response( array( 'verification_status' => $verification ) ); };
$descriptor = $good( TC_Billing_PayPal::verify_webhook( json_encode( $notification ), $headers, 'test' ) );
$assert( 'CAPTURE-LIFETIME' === $descriptor['object_id'] && is_int( $descriptor['created'] ) && ! isset( $descriptor['resource'] ), 'Verified descriptor retains bounded IDs and Unix timestamp only.' );
$verification = 'FAILURE';
$error( TC_Billing_PayPal::verify_webhook( json_encode( $notification ), $headers, 'test' ), 'webhook_signature', 'Remote signature failure rejects notification.' );
$verification = 'SUCCESS';
$bad_headers = $headers; $bad_headers['PAYPAL-CERT-URL'] = 'https://127.0.0.1/private';
$count_before = count( $GLOBALS['pp_requests'] );
$error( TC_Billing_PayPal::verify_webhook( json_encode( $notification ), $bad_headers, 'test' ), 'webhook_certificate', 'Untrusted certificate URL rejected without fetching it.' );
$assert( $count_before === count( $GLOBALS['pp_requests'] ), 'Bad cert URL causes no HTTP call.' );
$notification['event_type'] = 'CUSTOMER.DISPUTE.CREATED'; $notification['resource'] = array( 'dispute_id' => 'PP-DISPUTE1' );
$assert( 'PP-DISPUTE1' === $good( TC_Billing_PayPal::verify_webhook( json_encode( $notification ), $headers, 'test' ) )['object_id'], 'Dispute webhook reads canonical dispute_id schema.' );
$notification['event_type'] = 'UNRECOGNIZED.EVENT'; $notification['resource'] = array( 'id' => 'UNKNOWN-OBJECT' );
$unknown = $good( TC_Billing_PayPal::verify_webhook( json_encode( $notification ), $headers, 'test' ) );
$assert( array() === TC_Billing_PayPal::reconcile( $unknown + array( 'mode' => 'test' ) ), 'Unknown signed events are safe no-ops.' );
$notification['resource'] = array();
$assert( 'ignored' === $good( TC_Billing_PayPal::verify_webhook( json_encode( $notification ), $headers, 'test' ) )['object_id'], 'Unknown notification shape verifies remotely and remains a bounded ignored descriptor.' );
$bad_headers = $headers; unset( $bad_headers['PAYPAL-TRANSMISSION-SIG'] );
$error( TC_Billing_PayPal::verify_webhook( json_encode( $notification ), $bad_headers, 'test' ), 'webhook_headers', 'Unsigned notification cannot queue a billing event.' );
$GLOBALS['pp_routes']['GET /v1/billing/plans/P-MONTHLY29'] = pp_response( array(), 429, array( 'retry-after' => '125' ) );
$limited = TC_Billing_PayPal::reconcile( $monthly_event );
$error( $limited, 'http_429', 'Provider throttling remains retryable.' );
$assert( 125 === $limited->get_error_data()['retry_after'], 'Provider retry delay is bounded and retained.' );
$calendar = new ReflectionMethod( TC_Billing_PayPal::class, 'calendar_month' );
$assert( '2027-02-28 12:34:56' === gmdate( 'Y-m-d H:i:s', $calendar->invoke( null, strtotime( '2027-01-31 12:34:56 UTC' ) ) ), 'Calendar month clamps January31 to February28.' );
$assert( '2028-02-29 12:34:56' === gmdate( 'Y-m-d H:i:s', $calendar->invoke( null, strtotime( '2028-01-31 12:34:56 UTC' ) ) ), 'Calendar month preserves leap-year last day and time.' );
$oauth_calls = array_filter( $GLOBALS['pp_requests'], static fn( $request ) => '/v1/oauth2/token' === parse_url( $request[0], PHP_URL_PATH ) );
$assert( 1 === count( $oauth_calls ), 'Server-only OAuth token reused without repeated authentication.' );

// Unpaid checkout closure is provider-confirmed and never a local status override.
$close_id = '33333333-3333-4333-8333-333333333333';
$close_order = $make_order( $close_id, 'monthly' ); $close_order['subscription_id'] = 'I-CLOSE-PENDING'; $close_order['checkout_id'] = 'I-CLOSE-PENDING';
TC_Billing_Store::$orders[ $close_id ] = $close_order;
$close_binding = 'tc:' . $close_id . ':27:123456789012345678';
$terminal_subscription = array( 'id' => 'I-CLOSE-PENDING', 'plan_id' => 'P-MONTHLY29', 'custom_id' => $close_binding, 'quantity' => '1', 'status' => 'CANCELLED', 'create_time' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 600 ), 'billing_info' => array( 'outstanding_balance' => array( 'currency_code' => 'EUR', 'value' => '0.00' ), 'cycle_executions' => array( array( 'cycles_completed' => 0 ) ) ) );
$close_subscription = $terminal_subscription; $close_history = array( 'transactions' => array(), 'total_pages' => 1, 'total_items' => 0 );
$GLOBALS['pp_routes']['GET /v1/billing/subscriptions/I-CLOSE-PENDING'] = static function () use ( &$close_subscription ) { return pp_response( $close_subscription ); };
$GLOBALS['pp_routes']['GET /v1/billing/subscriptions/I-CLOSE-PENDING/transactions'] = static function ( $url ) use ( &$close_history, $assert, &$close_subscription ) {
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $range );
	$assert( strtotime( $range['start_time'] ?? '' ) <= strtotime( $close_subscription['create_time'] ) && strtotime( $range['end_time'] ?? '' ) >= time() - 2, 'Closing inspects complete recent provider creation range, not only a possibly incomplete recent window.' );
	return pp_response( $close_history );
};
$GLOBALS['pp_routes']['POST /v1/billing/subscriptions/I-CLOSE-PENDING/cancel'] = static function () { throw new RuntimeException( 'Unpaid closure must not cancel a PayPal approval implicitly.' ); };
$mutations = static function (): int { return count( array_filter( $GLOBALS['pp_requests'], static fn( array $request ): bool => 'GET' !== $request[1]['method'] && '/v1/oauth2/token' !== parse_url( $request[0], PHP_URL_PATH ) ) ); };
$before = $mutations(); $forged = $close_order; $forged['user_id'] = 99;
$error( TC_Billing_PayPal::close_checkout( $forged ), 'order_binding', 'Closing cannot substitute another stored buyer.' );
$assert( $before === $mutations(), 'Forged unpaid closure sends no remote mutation.' );
foreach ( array( 'payment_id' => 'SALE-UNKNOWN', 'status' => 'paid' ) as $field => $value ) {
	TC_Billing_Store::$orders[ $close_id ][ $field ] = $value;
	$error( TC_Billing_PayPal::close_checkout( TC_Billing_Store::$orders[ $close_id ] ), 'checkout_paid_or_active', 'Known payment or paid local state cannot be discarded: ' . $field );
	TC_Billing_Store::$orders[ $close_id ] = $close_order;
}
TC_Billing_Store::$active_error = true;
$assert( 'fixture_ledger_unavailable' === TC_Billing_PayPal::close_checkout( $close_order )->get_error_code() && $before === $mutations(), 'A failed active-ledger read blocks closure before remote mutation.' ); TC_Billing_Store::$active_error = false;
$same_source = TC_Billing_Store::$entitlements[ $monthly_source ]; $same_source['order_id'] = $close_id; TC_Billing_Store::$entitlements['fixture-same-order'] = $same_source;
$error( TC_Billing_PayPal::close_checkout( $close_order ), 'checkout_paid_or_active', 'An independent active source for this exact order vetoes unpaid closure.' ); unset( TC_Billing_Store::$entitlements['fixture-same-order'] );
foreach ( array( 'ACTIVE', 'APPROVED', 'SUSPENDED' ) as $status ) {
	$close_subscription['status'] = $status;
	$error( TC_Billing_PayPal::close_checkout( $close_order ), 'checkout_paid_or_active', 'Potentially chargeable subscription requires ordinary subscription management: ' . $status );
}
$close_subscription = $terminal_subscription;
$close_subscription['custom_id'] = str_replace( ':27:', ':28:', $close_binding );
$error( TC_Billing_PayPal::close_checkout( $close_order ), 'subscription_binding', 'Canonical metadata must still match the exact buyer before cancellation.' ); $close_subscription = $terminal_subscription;
foreach ( array( 'last_payment' => array( 'time' => gmdate( 'c' ) ), 'cycle_executions' => array( array( 'cycles_completed' => 1 ) ), 'outstanding_balance' => array( 'currency_code' => 'EUR', 'value' => '1.00' ) ) as $field => $value ) {
	$close_subscription['billing_info'][ $field ] = $value;
	$error( TC_Billing_PayPal::close_checkout( $close_order ), 'checkout_paid_or_active', 'Payment evidence prevents unpaid cancellation: ' . $field ); $close_subscription = $terminal_subscription;
}
foreach ( array( 'COMPLETED', 'PENDING', 'REFUNDED' ) as $status ) {
	$close_history['transactions'] = array( array( 'status' => $status ) );
	$error( TC_Billing_PayPal::close_checkout( $close_order ), 'checkout_history', 'Any historical or in-flight transaction prevents closure: ' . $status );
}
$close_history = array( 'transactions' => array(), 'total_pages' => 2, 'total_items' => 0 );
$error( TC_Billing_PayPal::close_checkout( $close_order ), 'checkout_history', 'An incomplete transaction listing cannot prove no payments.' );
$close_history['total_pages'] = 1;
$close_subscription['create_time'] = gmdate( 'c', time() - 31 * DAY_IN_SECONDS );
$error( TC_Billing_PayPal::close_checkout( $close_order ), 'checkout_history', 'Older attempts outside the complete bounded history require review.' ); $close_subscription = $terminal_subscription;
$assert( $before === $mutations(), 'All rejected preconditions preserve the remote contract.' );
$close_subscription['status'] = 'APPROVAL_PENDING';
$error( TC_Billing_PayPal::close_checkout( $close_order ), 'checkout_cancel_required', 'A pending approval is never canceled automatically while switching payment provider.' );
$assert( $before === $mutations(), 'Pending approval must use the separate explicit subscription cancellation flow.' );
$close_subscription = $terminal_subscription;
$assert( true === TC_Billing_PayPal::close_checkout( $close_order ) && $before === $mutations(), 'An unpaid terminal subscription closes only after provider terminal and full-history confirmation, without writes.' );
$assert( true === TC_Billing_PayPal::close_checkout( $close_order ) && $before === $mutations(), 'Repeating a confirmed terminal closure is read-only and idempotent.' );
$close_subscription['status'] = 'EXPIRED';
$assert( true === TC_Billing_PayPal::close_checkout( $close_order ) && $before === $mutations(), 'Provider-confirmed expiry with no payment history is also read-only closure.' );
$assert( 'pending' === TC_Billing_Store::$orders[ $close_id ]['status'] && '' === TC_Billing_Store::$orders[ $close_id ]['payment_id'], 'Adapter never changes local status or grants access.' );
$close_subscription = $terminal_subscription;
$GLOBALS['pp_routes']['GET /v1/billing/plans/P-MONTHLY29'] = static function () use ( &$plan ) { return pp_response( $plan ); };
$snapshot = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'tc.reconcile', 'order_id' => $close_id ) ) );
$assert( 'canceled' === $snapshot['order_fields']['status'] && ! isset( $snapshot['entitlement'] ), 'Reconciling an unpaid terminal subscription cannot reopen it or invent access.' );

$void_id = '44444444-4444-4444-8444-444444444444'; $void_order = $make_order( $void_id, 'lifetime' ); $void_order['checkout_id'] = 'ORDER-VOID-UNPAID'; TC_Billing_Store::$orders[ $void_id ] = $void_order;
$void_checkout = $checkout; $void_checkout['id'] = $void_order['checkout_id']; $void_checkout['purchase_units'][0]['reference_id'] = $void_id; $void_checkout['purchase_units'][0]['custom_id'] = 'tc:' . $void_id . ':27:123456789012345678'; unset( $void_checkout['purchase_units'][0]['payments'] );
$GLOBALS['pp_routes']['GET /v2/checkout/orders/ORDER-VOID-UNPAID'] = static function () use ( &$void_checkout ) { return pp_response( $void_checkout ); };
foreach ( array( 'CREATED', 'APPROVED', 'COMPLETED' ) as $status ) {
	$void_checkout['status'] = $status; $before = $mutations();
	$error( TC_Billing_PayPal::close_checkout( $void_order ), 'checkout_cannot_close', 'A v2 CAPTURE order has no invented expire endpoint: ' . $status );
	$assert( $before === $mutations(), 'Lifetime closure sends no unsupported void/cancel operation.' );
}
$void_checkout['status'] = 'VOIDED';
$assert( true === TC_Billing_PayPal::close_checkout( $void_order ), 'Only canonical empty VOIDED Lifetime checkout can be released.' );
$snapshot = $good( TC_Billing_PayPal::reconcile( array( 'mode' => 'test', 'type' => 'tc.reconcile', 'order_id' => $void_id ) ) );
$assert( 'canceled' === $snapshot['order_fields']['status'] && ! isset( $snapshot['entitlement'] ), 'Canonical VOIDED Lifetime reconciliation stays canceled instead of becoming pending again.' );
$void_checkout['purchase_units'][0]['payments']['captures'] = array( array( 'id' => 'CAPTURE-UNEXPECTED' ) );
$error( TC_Billing_PayPal::close_checkout( $void_order ), 'checkout_paid_or_active', 'Even VOIDED cannot discard a capture reference.' );
$assert( 'active' === TC_Billing_Store::$entitlements[ $lifetime_source ]['status'] && $old_period === TC_Billing_Store::$entitlements[ $monthly_source ]['valid_until'], 'Closing unrelated unpaid attempts preserves independent existing paid sources.' );

TC_Billing::$settings['stripe_test_confirmed'] = false;
$not_created = TC_Billing_PayPal::create_checkout( $close_order );
$assert( true === ( $not_created->get_error_data()['checkout_not_created'] ?? false ), 'A disabled early guard explicitly proves no checkout creation was sent.' ); TC_Billing::$settings['stripe_test_confirmed'] = true;
$plan['billing_cycles'][0]['frequency']['interval_unit'] = 'DAY';
$not_created = TC_Billing_PayPal::create_checkout( $close_order );
$assert( true === ( $not_created->get_error_data()['checkout_not_created'] ?? false ), 'Plan validation failure before creation retains an explicit not-created marker.' ); $plan['billing_cycles'][0]['frequency']['interval_unit'] = 'MONTH';
$new_id = '55555555-5555-4555-8555-555555555555'; $new_order = $make_order( $new_id, 'monthly' ); TC_Billing_Store::$orders[ $new_id ] = $new_order;
$GLOBALS['pp_routes']['POST /v1/billing/subscriptions'] = new WP_Error( 'network-private', 'PRIVATE_PROVIDER_BODY' );
$unknown = TC_Billing_PayPal::create_checkout( $new_order );
$assert( is_wp_error( $unknown ) && empty( $unknown->get_error_data()['checkout_not_created'] ), 'A network failure after attempted creation never claims that no provider checkout exists.' );
echo 'PayPal mocked integration: ' . $checks . " assertions passed; no real provider or database calls.\n";
