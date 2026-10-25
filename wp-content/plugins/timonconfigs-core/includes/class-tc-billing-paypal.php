<?php
/**
 * Direct PayPal billing. Checkout v2, Subscriptions v1 and Payments v1/v2.
 *
 * No notification payload, approval redirect, or ACTIVE subscription alone grants access.
 * PayPal does not expose a sale's service-period end. We conservatively cap an
 * independently verified monthly payment at one clamped calendar month from its
 * canonical payment time (and at next_billing_time when earlier). Cancellation
 * retains that paid period. Provider sandbox acceptance still requires real keys.
 *
 * @package TimonConfigs_Core
 */
defined( 'ABSPATH' ) || exit;

final class TC_Billing_PayPal {
	private const HOSTS = array( 'test' => 'https://api-m.sandbox.paypal.com', 'live' => 'https://api-m.paypal.com' );
	private const TYPES = array(
		'CHECKOUT.ORDER.APPROVED', 'CHECKOUT.ORDER.COMPLETED',
		'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.PENDING', 'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED',
		'PAYMENT.SALE.COMPLETED', 'PAYMENT.SALE.PENDING', 'PAYMENT.SALE.DENIED', 'PAYMENT.SALE.REFUNDED', 'PAYMENT.SALE.REVERSED',
		'BILLING.SUBSCRIPTION.CREATED', 'BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.UPDATED', 'BILLING.SUBSCRIPTION.CANCELLED',
		'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.EXPIRED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
		'CUSTOMER.DISPUTE.CREATED', 'CUSTOMER.DISPUTE.UPDATED', 'CUSTOMER.DISPUTE.RESOLVED',
	);

	public static function create_checkout( array $input ) {
		$order = self::stored( $input );
		if ( is_wp_error( $order ) ) { return $order; }
		$guard = self::guard( $order['mode'] );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$mode = $order['mode'];
		if ( ! empty( $order['checkout_id'] ) || ! empty( $order['subscription_id'] ) ) {
			$id = 'monthly' === $order['plan'] ? (string) $order['subscription_id'] : (string) $order['checkout_id'];
			$existing = self::api( $mode, 'GET', ( 'monthly' === $order['plan'] ? '/v1/billing/subscriptions/' : '/v2/checkout/orders/' ) . rawurlencode( $id ) );
			if ( is_wp_error( $existing ) ) { return $existing; }
			$valid = 'monthly' === $order['plan'] ? self::subscription_binding( $existing, $order ) : self::order_binding( $existing, $order );
			if ( is_wp_error( $valid ) ) { return $valid; }
			return self::checkout_result( $existing, $order );
		}
		$context = array(
			'brand_name' => 'TimonConfigs', 'locale' => 'en-GB', 'shipping_preference' => 'NO_SHIPPING',
			'user_action' => 'monthly' === $order['plan'] ? 'SUBSCRIBE_NOW' : 'PAY_NOW',
			'return_url' => add_query_arg( array( 'tc_billing_return' => 'paypal', 'tc_billing_order' => $order['id'] ), home_url( '/account/' ) ),
			'cancel_url' => add_query_arg( array( 'tc_billing_return' => 'cancel', 'tc_billing_order' => $order['id'] ), home_url( '/account/' ) ),
		);
		if ( 'monthly' === $order['plan'] ) {
			$valid = self::validate_plan( $mode );
			if ( is_wp_error( $valid ) ) { return $valid; }
			$path = '/v1/billing/subscriptions';
			$body = array( 'plan_id' => TC_Billing::setting( 'paypal_monthly_plan_id', $mode ), 'custom_id' => self::binding( $order ), 'application_context' => $context );
		} else {
			$path = '/v2/checkout/orders';
			$money = array( 'currency_code' => 'EUR', 'value' => '80.00' );
			$body = array( 'intent' => 'CAPTURE', 'application_context' => $context, 'purchase_units' => array( array(
				'reference_id' => $order['id'], 'custom_id' => self::binding( $order ), 'invoice_id' => 'tc-' . $order['id'],
				'amount' => $money + array( 'breakdown' => array( 'item_total' => $money ) ),
				'items' => array( array( 'name' => 'TimonConfigs Lifetime', 'sku' => 'tc-lifetime', 'quantity' => '1', 'category' => 'DIGITAL_GOODS', 'unit_amount' => $money ) ),
			) ) );
		}
		$result = self::api( $mode, 'POST', $path, $body, self::request_id( $order, 'create' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		// Verify all returned binding fields before the facade stores provider IDs.
		$valid = 'monthly' === $order['plan'] ? self::subscription_binding( $result, $order ) : self::order_binding( $result, $order );
		if ( is_wp_error( $valid ) ) { return $valid; }
		return self::checkout_result( $result, $order );
	}

	public static function capture( array $input ) {
		$order = self::stored( $input );
		if ( is_wp_error( $order ) ) { return $order; }
		$guard = self::guard( $order['mode'] );
		if ( is_wp_error( $guard ) ) { return $guard; }
		if ( 'lifetime' !== $order['plan'] || ! self::id( $order['checkout_id'] ?? '' ) ) { return self::error( 'capture_order' ); }
		$canonical = self::api( $order['mode'], 'GET', '/v2/checkout/orders/' . rawurlencode( $order['checkout_id'] ) );
		if ( is_wp_error( $canonical ) ) { return $canonical; }
		$valid = self::order_binding( $canonical, $order );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( 'COMPLETED' === ( $canonical['status'] ?? '' ) ) { return array( 'id' => $order['checkout_id'], 'status' => 'COMPLETED' ); }
		if ( 'APPROVED' !== ( $canonical['status'] ?? '' ) ) { return self::error( 'capture_not_approved' ); }
		$result = self::api( $order['mode'], 'POST', '/v2/checkout/orders/' . rawurlencode( $order['checkout_id'] ) . '/capture', new stdClass(), self::request_id( $order, 'capture' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		$valid = self::order_binding( $result, $order );
		if ( is_wp_error( $valid ) ) { return $valid; }
		return array( 'id' => $order['checkout_id'], 'status' => (string) ( $result['status'] ?? 'PENDING' ) );
	}

	public static function portal( array $input ) {
		$order = self::stored( $input );
		if ( is_wp_error( $order ) ) { return $order; }
		$guard = self::guard( $order['mode'] );
		if ( is_wp_error( $guard ) ) { return $guard; }
		if ( 'monthly' !== $order['plan'] || ! self::id( $order['subscription_id'] ?? '' ) ) { return self::error( 'subscription_required' ); }
		return 'test' === $order['mode'] ? 'https://www.sandbox.paypal.com/myaccount/autopay/' : 'https://www.paypal.com/myaccount/autopay/';
	}

	public static function cancel( array $input ) {
		$order = self::stored( $input );
		if ( is_wp_error( $order ) ) { return $order; }
		$guard = self::guard( $order['mode'] );
		if ( is_wp_error( $guard ) ) { return $guard; }
		if ( 'monthly' !== $order['plan'] || ! self::id( $order['subscription_id'] ?? '' ) ) { return self::error( 'subscription_required' ); }
		$subscription = self::api( $order['mode'], 'GET', '/v1/billing/subscriptions/' . rawurlencode( $order['subscription_id'] ) );
		if ( is_wp_error( $subscription ) ) { return $subscription; }
		$valid = self::subscription_binding( $subscription, $order );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( in_array( $subscription['status'] ?? '', array( 'CANCELLED', 'EXPIRED' ), true ) ) { return array( 'subscription_id' => $order['subscription_id'], 'cancel_at_period_end' => 1 ); }
		$result = self::api( $order['mode'], 'POST', '/v1/billing/subscriptions/' . rawurlencode( $order['subscription_id'] ) . '/cancel', array( 'reason' => 'Customer requested cancellation of future renewals.' ), self::request_id( $order, 'cancel' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'subscription_id' => $order['subscription_id'], 'cancel_at_period_end' => 1 );
	}

	public static function verify_webhook( string $raw, array $headers, string $mode ) {
		$guard = self::guard( $mode );
		if ( is_wp_error( $guard ) ) { return $guard; }
		if ( strlen( $raw ) > 1048576 ) { return self::error( 'webhook_size' ); }
		$event = json_decode( $raw, true );
		if ( ! is_array( $event ) || ! self::event_id( $event['id'] ?? '' ) || ! self::event_id( $event['event_type'] ?? '' ) ) { return self::error( 'webhook_body' ); }
		$object_id = str_starts_with( $event['event_type'], 'CUSTOMER.DISPUTE.' ) ? ( $event['resource']['dispute_id'] ?? '' ) : ( $event['resource']['id'] ?? '' );
		if ( ! in_array( $event['event_type'], self::TYPES, true ) && ! self::id( $object_id ) ) { $object_id = 'ignored'; }
		if ( ! self::id( $object_id ) ) { return self::error( 'webhook_body' ); }
		$normalized = array();
		foreach ( $headers as $key => $value ) {
			$key = strtolower( str_replace( '_', '-', (string) $key ) );
			if ( is_array( $value ) ) { $value = count( $value ) === 1 ? reset( $value ) : ''; }
			if ( is_string( $value ) && strlen( $value ) <= 4096 ) { $normalized[ $key ] = $value; }
		}
		$required = array( 'paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time' );
		foreach ( $required as $key ) { if ( empty( $normalized[ $key ] ) ) { return self::error( 'webhook_headers' ); } }
		$cert = wp_parse_url( $normalized['paypal-cert-url'] );
		if ( ! is_array( $cert ) || 'https' !== ( $cert['scheme'] ?? '' ) || ! in_array( $cert['host'] ?? '', array( 'api.paypal.com', 'api.sandbox.paypal.com', 'api-m.paypal.com', 'api-m.sandbox.paypal.com' ), true ) || isset( $cert['user'] ) || isset( $cert['pass'] ) || isset( $cert['port'] ) || isset( $cert['query'] ) || isset( $cert['fragment'] ) || ! preg_match( '#^/v1/notifications/certs/[A-Za-z0-9-]+$#D', $cert['path'] ?? '' ) ) { return self::error( 'webhook_certificate' ); }
		$sent = self::timestamp( $normalized['paypal-transmission-time'] );
		$created = self::timestamp( $event['create_time'] ?? '' );
		$webhook = TC_Billing::setting( 'paypal_webhook_id', $mode );
		if ( ! $sent || ! $created || $sent > time() + 300 || $created > time() + 300 || ! self::id( $webhook ) || 'SHA256withRSA' !== $normalized['paypal-auth-algo'] ) { return self::error( 'webhook_headers' ); }
		// cert_url is a signed input to PayPal's pinned verification endpoint. Never fetch it ourselves.
		$result = self::api( $mode, 'POST', '/v1/notifications/verify-webhook-signature', array(
			'auth_algo' => $normalized['paypal-auth-algo'], 'cert_url' => $normalized['paypal-cert-url'],
			'transmission_id' => $normalized['paypal-transmission-id'], 'transmission_sig' => $normalized['paypal-transmission-sig'],
			'transmission_time' => $normalized['paypal-transmission-time'], 'webhook_id' => $webhook, 'webhook_event' => $event,
		) );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( 'SUCCESS' !== ( $result['verification_status'] ?? '' ) ) { return self::error( 'webhook_signature' ); }
		return array( 'id' => $event['id'], 'type' => $event['event_type'], 'object_id' => $object_id, 'created' => $created );
	}

	public static function reconcile( array $event ) {
		$mode = (string) ( $event['mode'] ?? '' );
		$guard = self::guard( $mode );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$type = (string) ( $event['type'] ?? '' );
		if ( isset( $event['provider'] ) && 'paypal' !== $event['provider'] ) { return self::error( 'event_provider' ); }
		if ( 'tc.reconcile' === $type ) {
			$order = TC_Billing_Store::order( (string) ( $event['order_id'] ?? '' ) );
			if ( ! $order ) { return self::error( 'order_missing' ); }
			$order = self::stored( $order );
			if ( is_wp_error( $order ) || $mode !== $order['mode'] ) { return self::error( 'order_binding' ); }
			if ( 'monthly' === $order['plan'] ) {
				if ( ! self::id( $order['subscription_id'] ?? '' ) ) { return array(); }
				$subscription = self::api( $mode, 'GET', '/v1/billing/subscriptions/' . rawurlencode( $order['subscription_id'] ) );
				return is_wp_error( $subscription ) ? $subscription : self::monthly( $subscription, $mode );
			}
			if ( ! self::id( $order['checkout_id'] ?? '' ) ) { return array(); }
			$checkout = self::api( $mode, 'GET', '/v2/checkout/orders/' . rawurlencode( $order['checkout_id'] ) );
			return is_wp_error( $checkout ) ? $checkout : self::lifetime( $checkout, $mode );
		}
		if ( ! in_array( $type, self::TYPES, true ) ) { return array(); }
		$id = (string) ( $event['object_id'] ?? '' );
		if ( ! self::id( $id ) ) { return self::error( 'event_object' ); }
		if ( str_starts_with( $type, 'CUSTOMER.DISPUTE.' ) ) { return self::dispute( $id, $mode ); }
		if ( str_starts_with( $type, 'BILLING.SUBSCRIPTION.' ) ) {
			$subscription = self::api( $mode, 'GET', '/v1/billing/subscriptions/' . rawurlencode( $id ) );
			return is_wp_error( $subscription ) ? $subscription : self::monthly( $subscription, $mode );
		}
		if ( str_starts_with( $type, 'PAYMENT.SALE.' ) ) {
			if ( 'PAYMENT.SALE.REFUNDED' === $type ) {
				$refund = self::api( $mode, 'GET', '/v1/payments/refund/' . rawurlencode( $id ) );
				if ( ! is_wp_error( $refund ) ) {
					$id = self::id( $refund['sale_id'] ?? '' ) ? $refund['sale_id'] : self::related_link_id( $refund, '/v1/payments/sale/', $mode, 'sale' );
					if ( ! $id ) { return self::error( 'refund_parent' ); }
				} elseif ( 'tc_paypal_http_404' !== $refund->get_error_code() ) { return $refund; }
			}
			$sale = self::api( $mode, 'GET', '/v1/payments/sale/' . rawurlencode( $id ) );
			if ( is_wp_error( $sale ) ) { return $sale; }
			if ( 'PAYMENT.SALE.REVERSED' === $type && ! in_array( $sale['state'] ?? '', array( 'refunded', 'reversed' ), true ) ) { return self::error( 'reversal_unproven' ); }
			if ( ! self::id( $sale['billing_agreement_id'] ?? '' ) ) { return array(); }
			$subscription = self::api( $mode, 'GET', '/v1/billing/subscriptions/' . rawurlencode( $sale['billing_agreement_id'] ) );
			return is_wp_error( $subscription ) ? $subscription : self::monthly( $subscription, $mode );
		}
		if ( str_starts_with( $type, 'CHECKOUT.ORDER.' ) ) {
			$checkout = self::api( $mode, 'GET', '/v2/checkout/orders/' . rawurlencode( $id ) );
			return is_wp_error( $checkout ) ? $checkout : self::lifetime( $checkout, $mode );
		}
		if ( 'PAYMENT.CAPTURE.REFUNDED' === $type ) {
			$refund = self::api( $mode, 'GET', '/v2/payments/refunds/' . rawurlencode( $id ) );
			if ( is_wp_error( $refund ) ) { return $refund; }
			$id = self::related_link_id( $refund, '/v2/payments/captures/', $mode );
			if ( ! $id ) { return self::error( 'refund_parent' ); }
		}
		return self::from_capture( $id, $mode, 'PAYMENT.CAPTURE.REVERSED' === $type );
	}

	private static function lifetime( array $checkout, string $mode ) {
		$order = self::bound_order( $checkout['purchase_units'][0]['custom_id'] ?? '', $mode, 'lifetime' );
		if ( is_wp_error( $order ) ) { return $order; }
		$valid = self::order_binding( $checkout, $order );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$transition = array( 'order_id' => $order['id'], 'order_fields' => array( 'checkout_id' => $checkout['id'], 'status' => 'pending' ) );
		$captures = $checkout['purchase_units'][0]['payments']['captures'] ?? array();
		if ( ! is_array( $captures ) || count( $captures ) > 1 ) { return self::error( 'capture_quantity' ); }
		if ( ! $captures ) { return $transition; }
		$id = $captures[0]['id'] ?? '';
		if ( ! self::id( $id ) || ( ! empty( $order['payment_id'] ) && $order['payment_id'] !== $id ) ) { return self::error( 'capture_binding' ); }
		$capture = self::api( $mode, 'GET', '/v2/payments/captures/' . rawurlencode( $id ) );
		if ( is_wp_error( $capture ) ) { return $capture; }
		if ( $id !== ( $capture['id'] ?? '' ) || ! self::money( $capture['amount'] ?? array(), 8000 ) || ( isset( $capture['supplementary_data']['related_ids']['order_id'] ) && $capture['supplementary_data']['related_ids']['order_id'] !== $checkout['id'] ) || ( isset( $capture['final_capture'] ) && true !== $capture['final_capture'] ) ) { return self::error( 'capture_binding' ); }
		$status = $capture['status'] ?? '';
		$transition['order_fields']['payment_id'] = $id;
		if ( in_array( $status, array( 'REFUNDED', 'REVERSED' ), true ) ) {
			$entitlement_status = 'revoked';
			$transition['order_fields']['status'] = 'refunded';
		} elseif ( 'COMPLETED' === ( $checkout['status'] ?? '' ) && in_array( $status, array( 'COMPLETED', 'PARTIALLY_REFUNDED' ), true ) ) {
			$entitlement_status = 'active';
			$transition['order_fields']['status'] = 'paid';
		} else {
			if ( in_array( $status, array( 'DECLINED', 'FAILED' ), true ) ) { $transition['order_fields']['status'] = 'failed'; }
			return $transition;
		}
		$source = 'paypal:' . $mode . ':payment:' . $id;
		$transition['entitlement'] = self::entitlement( $order, $source, $checkout['id'], $id, $entitlement_status, null );
		return self::remembered_dispute( $transition, TC_Billing_Store::entitlement( $source ), $mode );
	}

	private static function monthly( array $subscription, string $mode ) {
		$order = self::bound_order( $subscription['custom_id'] ?? '', $mode, 'monthly' );
		if ( is_wp_error( $order ) ) { return $order; }
		$valid = self::subscription_binding( $subscription, $order );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$valid = self::validate_plan( $mode );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$id = $subscription['id'];
		$source = 'paypal:' . $mode . ':subscription:' . $id;
		$old = TC_Billing_Store::entitlement( $source );
		$cancelled = in_array( $subscription['status'] ?? '', array( 'CANCELLED', 'EXPIRED', 'SUSPENDED' ), true );
		$next = $cancelled ? null : self::sql_time( $subscription['billing_info']['next_billing_time'] ?? '' );
		$fields = array( 'subscription_id' => $id, 'checkout_id' => $id, 'status' => $cancelled ? 'cancelled' : 'pending', 'cancel_at_period_end' => $cancelled ? 1 : 0, 'next_payment_at' => $next );
		$transition = array( 'order_id' => $order['id'], 'order_fields' => $fields );
		if ( ! in_array( $subscription['status'] ?? '', array( 'ACTIVE', 'CANCELLED', 'EXPIRED', 'SUSPENDED' ), true ) ) { return self::preserve_monthly( $transition, $old, $cancelled, $next ); }
		// Query canonical recent payments; status ACTIVE without a paid transaction is insufficient.
		$query = '?start_time=' . rawurlencode( gmdate( 'Y-m-d\TH:i:s\Z', time() - 31 * DAY_IN_SECONDS ) ) . '&end_time=' . rawurlencode( gmdate( 'Y-m-d\TH:i:s\Z' ) );
		$transactions = self::api( $mode, 'GET', '/v1/billing/subscriptions/' . rawurlencode( $id ) . '/transactions' . $query );
		if ( is_wp_error( $transactions ) ) { return $transactions; }
		$payments = $transactions['transactions'] ?? null;
		if ( ! is_array( $payments ) || count( $payments ) > 200 || ! empty( $transactions['has_more'] ) || (int) ( $transactions['total_pages'] ?? 1 ) > 1 ) { return self::error( 'transactions_shape' ); }
		$paid = array();
		foreach ( $payments as $payment ) {
			if ( ! is_array( $payment ) ) { return self::error( 'transaction_shape' ); }
			if ( in_array( $payment['status'] ?? '', array( 'COMPLETED', 'REFUNDED', 'PARTIALLY_REFUNDED', 'REVERSED' ), true ) ) { $paid[] = $payment; }
		}
		usort( $paid, static function ( $a, $b ) { return strcmp( (string) ( $b['time'] ?? '' ), (string) ( $a['time'] ?? '' ) ); } );
		if ( ! $paid ) { return self::preserve_monthly( $transition, $old, $cancelled, $next ); }
		$payment = $paid[0];
		$payment_id = $payment['id'] ?? '';
		if ( ! self::id( $payment_id ) || ! self::money( $payment['amount_with_breakdown']['gross_amount'] ?? array(), 2999 ) || ! self::timestamp( $payment['time'] ?? '' ) ) { return self::error( 'transaction_price' ); }
		$sale = self::api( $mode, 'GET', '/v1/payments/sale/' . rawurlencode( $payment_id ) );
		if ( is_wp_error( $sale ) ) { return $sale; }
		$paid_at = self::timestamp( $sale['create_time'] ?? '' );
		if ( $payment_id !== ( $sale['id'] ?? '' ) || $id !== ( $sale['billing_agreement_id'] ?? '' ) || ! self::money( $sale['amount'] ?? array(), 2999, true ) || ! $paid_at || $paid_at > time() + 300 || abs( $paid_at - self::timestamp( $payment['time'] ) ) > 300 ) { return self::error( 'sale_binding' ); }
		$fields['payment_id'] = $payment_id;
		$transition['order_fields'] = $fields;
		if ( in_array( $sale['state'] ?? '', array( 'refunded', 'reversed' ), true ) ) {
			$transition['order_fields']['status'] = 'refunded';
			$transition['entitlement'] = self::entitlement( $order, $source, $id, $payment_id, 'revoked', $old['valid_until'] ?? gmdate( 'Y-m-d H:i:s', self::calendar_month( $paid_at ) ) );
			return self::remembered_dispute( $transition, $old, $mode );
		}
		if ( ! in_array( $sale['state'] ?? '', array( 'completed', 'partially_refunded' ), true ) ) { return self::preserve_monthly( $transition, $old, $cancelled, $next ); }
		$last = $subscription['billing_info']['last_payment'] ?? array();
		if ( ! self::money( $last['amount'] ?? array(), 2999 ) || abs( self::timestamp( $last['time'] ?? '' ) - $paid_at ) > 300 ) { return self::error( 'last_payment_binding' ); }
		$end = self::calendar_month( $paid_at );
		if ( $next && self::timestamp( $next ) > $paid_at ) { $end = min( $end, self::timestamp( $next ) ); }
		// Keep the independently established period when no newer successful payment exists.
		if ( $old && $payment_id === ( $old['payment_id'] ?? '' ) && ! empty( $old['valid_until'] ) ) { $end = self::timestamp( $old['valid_until'] ); }
		$fields['status'] = $cancelled ? 'cancelled' : ( $end > time() ? 'paid' : 'expired' );
		$transition['order_fields'] = $fields;
		$transition['entitlement'] = self::entitlement( $order, $source, $id, $payment_id, $end > time() ? 'active' : 'expired', gmdate( 'Y-m-d H:i:s', $end ) );
		$transition['entitlement']['cancel_at_period_end'] = $cancelled ? 1 : 0;
		$transition['entitlement']['next_payment_at'] = $next;
		return self::remembered_dispute( $transition, $old, $mode );
	}

	private static function preserve_monthly( array $transition, $old, bool $cancelled, $next ): array {
		if ( is_array( $old ) && in_array( $old['status'] ?? '', array( 'active', 'expired' ), true ) ) {
			$old = array_intersect_key( $old, array_flip( array( 'source', 'user_id', 'discord_id', 'provider', 'mode', 'plan', 'status', 'valid_until', 'external_id', 'order_id', 'customer_id', 'payment_id', 'last_invoice_id', 'cancel_at_period_end', 'next_payment_at', 'next_check_at' ) ) );
			$old['status'] = ! empty( $old['valid_until'] ) && self::timestamp( $old['valid_until'] ) > time() ? 'active' : 'expired';
			$old['cancel_at_period_end'] = $cancelled ? 1 : 0;
			$old['next_payment_at'] = $next;
			$transition['entitlement'] = $old;
		}
		return $transition;
	}

	private static function from_capture( string $id, string $mode, bool $reversed = false ) {
		$capture = self::api( $mode, 'GET', '/v2/payments/captures/' . rawurlencode( $id ) );
		if ( is_wp_error( $capture ) ) { return $capture; }
		if ( $reversed && ! in_array( $capture['status'] ?? '', array( 'REFUNDED', 'REVERSED' ), true ) ) { return self::error( 'reversal_unproven' ); }
		$checkout_id = $capture['supplementary_data']['related_ids']['order_id'] ?? '';
		if ( ! self::id( $checkout_id ) ) {
			$known = TC_Billing_Store::find_order( 'paypal', $mode, $id );
			$checkout_id = $known['checkout_id'] ?? '';
		}
		if ( ! self::id( $checkout_id ) ) { return self::error( 'capture_parent' ); }
		$checkout = self::api( $mode, 'GET', '/v2/checkout/orders/' . rawurlencode( $checkout_id ) );
		if ( is_wp_error( $checkout ) ) { return $checkout; }
		if ( $id !== ( $checkout['purchase_units'][0]['payments']['captures'][0]['id'] ?? '' ) ) { return self::error( 'capture_parent' ); }
		return self::lifetime( $checkout, $mode );
	}

	private static function dispute( string $id, string $mode ) {
		$dispute = self::api( $mode, 'GET', '/v1/customer/disputes/' . rawurlencode( $id ) );
		if ( is_wp_error( $dispute ) ) { return $dispute; }
		$transactions = $dispute['disputed_transactions'] ?? array();
		if ( $id !== ( $dispute['dispute_id'] ?? '' ) || ! is_array( $transactions ) || count( $transactions ) !== 1 || ! self::id( $transactions[0]['seller_transaction_id'] ?? '' ) ) { return self::error( 'dispute_binding' ); }
		$payment_id = $transactions[0]['seller_transaction_id'];
		$order = TC_Billing_Store::find_order( 'paypal', $mode, $payment_id );
		if ( $order && 'monthly' === $order['plan'] ) {
			$transition = self::reconcile( array( 'provider' => 'paypal', 'mode' => $mode, 'type' => 'tc.reconcile', 'order_id' => $order['id'] ) );
		} else {
			$transition = self::from_capture( $payment_id, $mode );
			if ( is_wp_error( $transition ) && 'tc_paypal_http_404' === $transition->get_error_code() ) {
				$transition = self::reconcile( array( 'provider' => 'paypal', 'mode' => $mode, 'type' => 'PAYMENT.SALE.COMPLETED', 'object_id' => $payment_id ) );
			}
		}
		return self::apply_dispute( $transition, $dispute, $payment_id );
	}

	private static function remembered_dispute( array $transition, $old, string $mode ) {
		if ( ! is_array( $old ) || empty( $transition['entitlement'] ) || ( $old['payment_id'] ?? '' ) !== $transition['entitlement']['payment_id'] || ! str_starts_with( (string) ( $old['last_invoice_id'] ?? '' ), 'paypal-dispute:' ) ) { return $transition; }
		$id = substr( $old['last_invoice_id'], strlen( 'paypal-dispute:' ) );
		if ( ! self::id( $id ) ) { return self::error( 'dispute_binding' ); }
		$dispute = self::api( $mode, 'GET', '/v1/customer/disputes/' . rawurlencode( $id ) );
		if ( is_wp_error( $dispute ) ) { return $dispute; }
		if ( $id !== ( $dispute['dispute_id'] ?? '' ) ) { return self::error( 'dispute_binding' ); }
		return self::apply_dispute( $transition, $dispute, $old['payment_id'] );
	}

	private static function apply_dispute( $transition, array $dispute, string $payment_id ) {
		if ( is_wp_error( $transition ) || empty( $transition['entitlement'] ) || $payment_id !== ( $transition['entitlement']['payment_id'] ?? '' ) ) { return $transition; }
		$transactions = $dispute['disputed_transactions'] ?? array();
		if ( ! is_array( $transactions ) || count( $transactions ) !== 1 || $payment_id !== ( $transactions[0]['seller_transaction_id'] ?? '' ) ) { return self::error( 'dispute_binding' ); }
		$transition['entitlement']['last_invoice_id'] = 'paypal-dispute:' . $dispute['dispute_id'];
		$status = $dispute['status'] ?? '';
		if ( in_array( $status, array( 'OPEN', 'WAITING_FOR_BUYER_RESPONSE', 'WAITING_FOR_SELLER_RESPONSE', 'UNDER_REVIEW' ), true ) ) {
			$transition['entitlement']['status'] = 'disputed';
			$transition['order_fields']['status'] = 'disputed';
		} elseif ( 'RESOLVED' === $status ) {
			$outcome = $dispute['dispute_outcome']['outcome_code'] ?? '';
			if ( in_array( $outcome, array( 'RESOLVED_BUYER_FAVOUR', 'ACCEPTED', 'RESOLVED_WITH_PAYOUT' ), true ) ) {
				$transition['entitlement']['status'] = 'revoked';
				$transition['order_fields']['status'] = 'refunded';
			} elseif ( in_array( $outcome, array( 'RESOLVED_SELLER_FAVOUR', 'DENIED', 'CANCELED' ), true ) ) {
				$transition['entitlement']['last_invoice_id'] = null;
			} else { return self::error( 'dispute_outcome' ); }
		} else { return self::error( 'dispute_state' ); }
		return $transition;
	}

	private static function stored( array $input ) {
		$id = $input['id'] ?? '';
		if ( ! is_string( $id ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $id ) ) { return self::error( 'order_missing' ); }
		$order = TC_Billing_Store::order( $id );
		if ( ! is_array( $order ) || 'paypal' !== ( $order['provider'] ?? '' ) || ! isset( self::HOSTS[ $order['mode'] ?? '' ] ) || ! in_array( $order['plan'] ?? '', array( 'monthly', 'lifetime' ), true ) || (int) ( $order['user_id'] ?? 0 ) < 1 || ! preg_match( '/^[0-9]{17,20}$/D', (string) ( $order['discord_id'] ?? '' ) ) || (int) ( $order['amount'] ?? 0 ) !== ( 'monthly' === $order['plan'] ? 2999 : 8000 ) || 'eur' !== strtolower( (string) ( $order['currency'] ?? '' ) ) ) { return self::error( 'order_binding' ); }
		foreach ( array( 'user_id', 'discord_id', 'provider', 'mode', 'plan', 'amount', 'currency' ) as $key ) {
			if ( array_key_exists( $key, $input ) && (string) $input[ $key ] !== (string) $order[ $key ] ) { return self::error( 'order_binding' ); }
		}
		return $order;
	}

	private static function bound_order( $custom, string $mode, string $plan ) {
		if ( ! is_string( $custom ) || ! preg_match( '/^tc:([0-9a-f-]{36}):([1-9][0-9]*):([0-9]{17,20})$/Di', $custom, $matches ) ) { return self::error( 'metadata' ); }
		$order = self::stored( array( 'id' => $matches[1] ) );
		if ( is_wp_error( $order ) || $mode !== $order['mode'] || $plan !== $order['plan'] || ! hash_equals( self::binding( $order ), $custom ) ) { return self::error( 'metadata' ); }
		return $order;
	}

	private static function binding( array $order ): string { return 'tc:' . $order['id'] . ':' . $order['user_id'] . ':' . $order['discord_id']; }

	/** Checkout v2 limits PayPal-Request-Id to 38 characters. */
	private static function request_id( array $order, string $action ): string { return 'tc-' . substr( hash( 'sha256', 'paypal:' . $order['mode'] . ':' . $order['id'] . ':' . $action ), 0, 32 ); }

	private static function order_binding( array $checkout, array $order ) {
		$units = $checkout['purchase_units'] ?? array();
		if ( 'lifetime' !== $order['plan'] || ! self::id( $checkout['id'] ?? '' ) || ( ! empty( $order['checkout_id'] ) && $checkout['id'] !== $order['checkout_id'] ) || 'CAPTURE' !== ( $checkout['intent'] ?? '' ) || ! is_array( $units ) || count( $units ) !== 1 || ( $units[0]['reference_id'] ?? '' ) !== $order['id'] || ( $units[0]['custom_id'] ?? '' ) !== self::binding( $order ) || ! self::money( $units[0]['amount'] ?? array(), 8000 ) ) { return self::error( 'checkout_binding' ); }
		$items = $units[0]['items'] ?? array();
		if ( ! is_array( $items ) || count( $items ) !== 1 || 'tc-lifetime' !== ( $items[0]['sku'] ?? '' ) || '1' !== (string) ( $items[0]['quantity'] ?? '' ) || ! self::money( $items[0]['unit_amount'] ?? array(), 8000 ) ) { return self::error( 'checkout_product' ); }
		return true;
	}

	private static function subscription_binding( array $subscription, array $order ) {
		if ( 'monthly' !== $order['plan'] || ! self::id( $subscription['id'] ?? '' ) || ( ! empty( $order['subscription_id'] ) && $subscription['id'] !== $order['subscription_id'] ) || TC_Billing::setting( 'paypal_monthly_plan_id', $order['mode'] ) !== ( $subscription['plan_id'] ?? '' ) || ( $subscription['custom_id'] ?? '' ) !== self::binding( $order ) || ( isset( $subscription['quantity'] ) && '1' !== (string) $subscription['quantity'] ) || ! empty( $subscription['plan_overridden'] ) ) { return self::error( 'subscription_binding' ); }
		return true;
	}

	private static function validate_plan( string $mode ) {
		$id = TC_Billing::setting( 'paypal_monthly_plan_id', $mode );
		$product = TC_Billing::setting( 'paypal_monthly_product_id', $mode );
		if ( ! self::id( $id ) || ! self::id( $product ) ) { return self::error( 'plan_config' ); }
		$plan = self::api( $mode, 'GET', '/v1/billing/plans/' . rawurlencode( $id ) );
		if ( is_wp_error( $plan ) ) { return $plan; }
		$cycles = $plan['billing_cycles'] ?? array();
		if ( $id !== ( $plan['id'] ?? '' ) || $product !== ( $plan['product_id'] ?? '' ) || 'ACTIVE' !== ( $plan['status'] ?? '' ) || ! is_array( $cycles ) || count( $cycles ) !== 1 ) { return self::error( 'plan_product' ); }
		$cycle = $cycles[0];
		if ( 'REGULAR' !== ( $cycle['tenure_type'] ?? '' ) || 1 !== (int) ( $cycle['sequence'] ?? 0 ) || 0 !== (int) ( $cycle['total_cycles'] ?? -1 ) || 'MONTH' !== ( $cycle['frequency']['interval_unit'] ?? '' ) || 1 !== (int) ( $cycle['frequency']['interval_count'] ?? 0 ) || ! self::money( $cycle['pricing_scheme']['fixed_price'] ?? array(), 2999 ) || ! empty( $plan['quantity_supported'] ) || ! empty( $plan['taxes']['percentage'] ) || ( isset( $plan['payment_preferences']['setup_fee'] ) && ! self::money( $plan['payment_preferences']['setup_fee'], 0 ) ) ) { return self::error( 'plan_price' ); }
		$canonical_product = self::api( $mode, 'GET', '/v1/catalogs/products/' . rawurlencode( $product ) );
		if ( is_wp_error( $canonical_product ) ) { return $canonical_product; }
		if ( $product !== ( $canonical_product['id'] ?? '' ) || ! in_array( $canonical_product['type'] ?? '', array( 'SERVICE', 'DIGITAL' ), true ) ) { return self::error( 'plan_product' ); }
		return true;
	}

	private static function checkout_result( array $result, array $order ) {
		foreach ( $result['links'] ?? array() as $link ) {
			if ( 'approve' === ( $link['rel'] ?? '' ) && self::hosted_url( $link['href'] ?? '', $order['mode'] ) ) {
				$return = array( 'id' => $result['id'], 'url' => $link['href'] );
				if ( 'monthly' === $order['plan'] ) { $return['subscription_id'] = $result['id']; }
				return $return;
			}
		}
		return self::error( 'approval_url' );
	}

	private static function hosted_url( $url, string $mode ): bool {
		if ( ! is_string( $url ) || strlen( $url ) > 2048 ) { return false; }
		$p = wp_parse_url( $url );
		$host = 'test' === $mode ? 'www.sandbox.paypal.com' : 'www.paypal.com';
		return is_array( $p ) && 'https' === ( $p['scheme'] ?? '' ) && $host === ( $p['host'] ?? '' ) && ! isset( $p['port'] ) && ! isset( $p['user'] ) && ! isset( $p['pass'] ) && ! isset( $p['fragment'] ) && in_array( $p['path'] ?? '', array( '/checkoutnow', '/webapps/billing/subscriptions' ), true );
	}

	private static function entitlement( array $order, string $source, string $external, string $payment, string $status, $until ): array {
		return array( 'source' => $source, 'user_id' => (int) $order['user_id'], 'discord_id' => $order['discord_id'], 'provider' => 'paypal', 'mode' => $order['mode'], 'plan' => $order['plan'], 'status' => $status, 'valid_until' => $until, 'external_id' => $external, 'order_id' => $order['id'], 'payment_id' => $payment, 'last_invoice_id' => null, 'cancel_at_period_end' => 0, 'next_payment_at' => null, 'next_check_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );
	}

	private static function guard( string $mode ) {
		$config = TC_Billing::config();
		if ( ! isset( self::HOSTS[ $mode ] ) || ! TC_Billing::mode_allowed( $mode ) || empty( $config['stripe_test_confirmed'] ) || empty( $config['paypal_enabled'] ) ) { return self::error( 'disabled' ); }
		return true;
	}

	private static function api( string $mode, string $method, string $path, $body = null, string $request_id = '' ) {
		if ( ! isset( self::HOSTS[ $mode ] ) || ! preg_match( '#^/v[12]/[A-Za-z0-9/_-]+(?:\?start_time=[A-Za-z0-9%:.-]+&end_time=[A-Za-z0-9%:.-]+)?$#D', $path ) ) { return self::error( 'api_path' ); }
		$token = self::token( $mode );
		if ( is_wp_error( $token ) ) { return $token; }
		$headers = array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'Prefer' => 'return=representation' );
		if ( $request_id ) { $headers['PayPal-Request-Id'] = $request_id; }
		$args = array( 'method' => $method, 'timeout' => 20, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 1048576, 'headers' => $headers );
		if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
		$result = wp_remote_request( self::HOSTS[ $mode ] . $path, $args );
		return self::response( $result );
	}

	private static function token( string $mode ) {
		$client = TC_Billing::secret( 'PAYPAL_CLIENT_ID', $mode );
		$secret = TC_Billing::secret( 'PAYPAL_CLIENT_SECRET', $mode );
		if ( ! $client || ! $secret || preg_match( '/[\r\n:]/', $client ) || preg_match( '/[\r\n]/', $secret ) ) { return self::error( 'credentials' ); }
		$key = 'tc_pp_token_' . $mode . '_' . substr( hash( 'sha256', $client . ':' . $secret ), 0, 24 );
		$cached = get_transient( $key );
		if ( is_string( $cached ) && $cached ) { return $cached; }
		$result = wp_remote_request( self::HOSTS[ $mode ] . '/v1/oauth2/token', array( 'method' => 'POST', 'timeout' => 20, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 16384, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $client . ':' . $secret ), 'Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json' ), 'body' => 'grant_type=client_credentials' ) );
		$data = self::response( $result );
		if ( is_wp_error( $data ) ) { return $data; }
		if ( 'Bearer' !== ( $data['token_type'] ?? '' ) || ! is_string( $data['access_token'] ?? null ) || ! preg_match( '/^[A-Za-z0-9._~-]{10,4096}$/D', $data['access_token'] ) || (int) ( $data['expires_in'] ?? 0 ) < 120 ) { return self::error( 'token_response' ); }
		set_transient( $key, $data['access_token'], min( 28800, (int) $data['expires_in'] - 60 ) );
		return $data['access_token'];
	}

	private static function response( $result ) {
		if ( is_wp_error( $result ) ) { return self::error( 'network' ); }
		$status = (int) wp_remote_retrieve_response_code( $result );
		if ( $status < 200 || $status >= 300 ) {
			$error = self::error( 'http_' . $status );
			if ( 429 === $status || $status >= 500 ) { $error->add_data( array( 'retry_after' => min( 3600, max( 60, (int) wp_remote_retrieve_header( $result, 'retry-after' ) ) ) ) ); }
			return $error;
		}
		$raw = wp_remote_retrieve_body( $result );
		if ( 204 === $status && '' === $raw ) { return array(); }
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : self::error( 'response_json' );
	}

	private static function related_link_id( array $object, string $prefix, string $mode, string $relation = 'up' ): string {
		foreach ( $object['links'] ?? array() as $link ) {
			if ( $relation !== ( $link['rel'] ?? '' ) || ! is_string( $link['href'] ?? null ) ) { continue; }
			$expected = self::HOSTS[ $mode ] . $prefix;
			if ( str_starts_with( $link['href'], $expected ) && self::id( substr( $link['href'], strlen( $expected ) ) ) ) { return substr( $link['href'], strlen( $expected ) ); }
		}
		return '';
	}

	private static function id( $value ): bool { return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9-]{5,80}$/D', $value ); }
	private static function event_id( $value ): bool { return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_.:-]{1,128}$/D', $value ); }
	private static function money( $money, int $cents, bool $v1 = false ): bool {
		if ( ! is_array( $money ) ) { return false; }
		$currency = $money[ $v1 ? 'currency' : 'currency_code' ] ?? '';
		$value = $money[ $v1 ? 'total' : 'value' ] ?? '';
		if ( 'EUR' !== $currency || ! is_string( $value ) || ! preg_match( '/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D', $value, $parts ) ) { return false; }
		return ( (int) $parts[1] * 100 + (int) str_pad( $parts[2] ?? '', 2, '0' ) ) === $cents;
	}
	private static function timestamp( $value ): int {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?$/D', $value ) ) { return 0; }
		try { return ( new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) ) )->getTimestamp(); } catch ( Exception $e ) { return 0; }
	}
	private static function sql_time( $value ) { $time = self::timestamp( $value ); return $time ? gmdate( 'Y-m-d H:i:s', $time ) : null; }
	private static function calendar_month( int $time ): int {
		$date = ( new DateTimeImmutable( '@' . $time ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$next = $date->modify( 'first day of next month' );
		$day = min( (int) $date->format( 'j' ), (int) $next->format( 't' ) );
		return $next->setDate( (int) $next->format( 'Y' ), (int) $next->format( 'n' ), $day )->getTimestamp();
	}
	private static function error( string $code ) { return TC_Billing::error( 'tc_paypal_' . $code, 'PayPal billing could not be verified. Please retry or contact support.' ); }
}
