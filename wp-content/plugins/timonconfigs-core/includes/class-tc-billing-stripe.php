<?php
/**
 * Hosted Stripe billing. All financial state is read from the pinned Stripe API.
 *
 * @package TimonConfigs_Core
 */
defined( 'ABSPATH' ) || exit;

final class TC_Billing_Stripe {

	private const API = 'https://api.stripe.com/v1/';
	private const VERSION = '2024-06-20';
	private const TOLERANCE = 300;

	public static function create_checkout( array $order ): array|WP_Error {
		$order = self::stored_order( $order );
		if ( is_wp_error( $order ) ) { return $order; }
		if ( ! in_array( $order['status'] ?? '', array( 'pending', 'checkout', 'failed' ), true ) ) { return self::error( 'stripe_order_closed' ); }
		$price = self::configured_price( $order, true );
		if ( is_wp_error( $price ) ) { return $price; }
		$metadata = self::metadata( $order );
		$args = array(
			'mode' => 'monthly' === $order['plan'] ? 'subscription' : 'payment',
			'client_reference_id' => $order['id'], 'metadata' => $metadata,
			'line_items' => array( array( 'price' => $price['id'], 'quantity' => 1 ) ),
			'success_url' => home_url( '/account/?tc_billing_return=success' ),
			'cancel_url' => home_url( '/account/?billing=cancelled' ),
			'allow_promotion_codes' => 'false',
			'automatic_tax' => array( 'enabled' => 'false' ),
		);
		if ( 'monthly' === $order['plan'] ) {
			$args['subscription_data'] = array( 'metadata' => $metadata );
		} else {
			$args['payment_intent_data'] = array( 'metadata' => $metadata );
			$args['customer_creation'] = 'always';
		}
		// Stripe retains idempotency keys for a bounded time. A saved session is never
		// replaced with a second session for the same order, even after that window.
		if ( ! empty( $order['checkout_id'] ) ) {
			$result = self::fetch( 'checkout/sessions', $order['checkout_id'], 'cs_', $order['mode'], array( 'expand' => array( 'line_items.data.price' ) ) );
			if ( ! is_wp_error( $result ) && is_wp_error( self::checkout_lines( $result, $order ) ) ) { return self::error( 'stripe_checkout_price' ); }
		} else {
			$result = self::api( 'POST', 'checkout/sessions', $order['mode'], $args, self::key( $order, 'checkout' ) );
		}
		if ( is_wp_error( $result ) ) { return $result; }
		$bound = self::bind( $result, $order );
		if ( is_wp_error( $bound ) || ( $result['client_reference_id'] ?? '' ) !== $order['id'] || ( $result['mode'] ?? '' ) !== $args['mode'] || ! self::id( $result['id'] ?? '', 'cs_' ) || 'open' !== ( $result['status'] ?? '' ) || ! self::hosted_url( $result['url'] ?? '', 'checkout.stripe.com' ) ) {
			return self::error( 'stripe_checkout_binding' );
		}
		$out = array( 'id' => $result['id'], 'url' => $result['url'] );
		foreach ( array( 'customer' => 'customer_id', 'subscription' => 'subscription_id', 'payment_intent' => 'payment_id' ) as $from => $to ) {
			$id = self::object_id( $result[ $from ] ?? '' );
			if ( $id ) { $out[ $to ] = $id; }
		}
		return $out;
	}

	/** Verify exact signed bytes before decoding; descriptors contain no customer data. */
	public static function verify_webhook( string $raw, array $headers, string $mode ): array|WP_Error {
		if ( ! TC_Billing::mode_allowed( $mode ) || strlen( $raw ) > 1048576 ) { return self::error( 'stripe_webhook_mode' ); }
		$headers = array_change_key_case( $headers, CASE_LOWER );
		$header = $headers['stripe-signature'] ?? '';
		if ( is_array( $header ) && 1 === count( $header ) ) { $header = reset( $header ); }
		if ( ! is_string( $header ) || strlen( $header ) > 4096 ) { return self::error( 'stripe_webhook_signature' ); }
		$timestamp = null;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) { continue; }
			if ( 't' === $pair[0] ) {
				if ( null !== $timestamp || ! ctype_digit( $pair[1] ) || strlen( $pair[1] ) > 12 ) { return self::error( 'stripe_webhook_signature' ); }
				$timestamp = (int) $pair[1];
			}
			if ( 'v1' === $pair[0] && preg_match( '/^[a-f0-9]{64}$/D', $pair[1] ) ) { $signatures[] = $pair[1]; }
		}
		$secret = TC_Billing::secret( 'STRIPE_WEBHOOK_SECRET', $mode );
		if ( null === $timestamp || abs( time() - $timestamp ) > self::TOLERANCE || ! str_starts_with( $secret, 'whsec_' ) || ! $signatures ) { return self::error( 'stripe_webhook_signature' ); }
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw, $secret );
		$verified = false;
		foreach ( $signatures as $signature ) { $verified = hash_equals( $expected, $signature ) || $verified; }
		if ( ! $verified ) { return self::error( 'stripe_webhook_signature' ); }
		$event = json_decode( $raw, true );
		if ( ! is_array( $event ) || ! self::id( $event['id'] ?? '', 'evt_' ) || ! is_string( $event['type'] ?? null ) || ! preg_match( '/^[a-z][a-z0-9_.]{1,120}$/D', $event['type'] ) || ! is_int( $event['created'] ?? null ) || $event['created'] < 1 || ! self::matches_mode( $event, $mode ) || ! self::id( $event['data']['object']['id'] ?? '', '' ) || ( isset( $event['data']['object']['livemode'] ) && ! self::matches_mode( $event['data']['object'], $mode ) ) ) { return self::error( 'stripe_webhook_object' ); }
		return array( 'id' => $event['id'], 'type' => $event['type'], 'object_id' => $event['data']['object']['id'], 'created' => $event['created'] );
	}

	public static function reconcile( array $event ): array|WP_Error {
		$mode = $event['mode'] ?? '';
		if ( ( $event['provider'] ?? 'stripe' ) !== 'stripe' || ! is_string( $mode ) || ! TC_Billing::mode_allowed( $mode ) ) { return self::error( 'stripe_mode' ); }
		$type = $event['type'] ?? '';
		$id = $event['object_id'] ?? '';
		$order = null;
		$session = null;
		$subscription = null;
		$payment = null;
		if ( 'tc.reconcile' === $type ) {
			$order = TC_Billing_Store::order( (string) ( $event['order_id'] ?? '' ) );
			if ( ! $order || ( $order['mode'] ?? '' ) !== $mode ) { return self::error( 'stripe_order_missing' ); }
			$order = self::stored_order( $order );
			if ( is_wp_error( $order ) ) { return $order; }
			if ( ! empty( $order['subscription_id'] ) ) { $type = 'customer.subscription.updated'; $id = $order['subscription_id']; }
			elseif ( ! empty( $order['payment_id'] ) ) { $type = 'payment_intent.succeeded'; $id = $order['payment_id']; }
			elseif ( ! empty( $order['checkout_id'] ) ) { $type = 'checkout.session.completed'; $id = $order['checkout_id']; }
			else { return self::error( 'stripe_order_external_missing' ); }
		}
		if ( ! is_string( $type ) || ! is_string( $id ) ) { return self::error( 'stripe_event' ); }
		if ( str_starts_with( $type, 'checkout.session.' ) ) {
			$session = self::fetch( 'checkout/sessions', $id, 'cs_', $mode, array( 'expand' => array( 'line_items.data.price' ) ) );
			if ( is_wp_error( $session ) ) { return $session; }
			$order = self::resolve( $session, $mode );
			if ( is_wp_error( $order ) ) { return $order; }
			if ( ( $session['client_reference_id'] ?? '' ) !== $order['id'] || ( ! empty( $order['checkout_id'] ) && $order['checkout_id'] !== $id ) ) { return self::error( 'stripe_checkout_binding' ); }
			$valid = self::checkout_lines( $session, $order );
			if ( is_wp_error( $valid ) ) { return $valid; }
			if ( 'monthly' === $order['plan'] ) { $id = self::object_id( $session['subscription'] ?? '' ); $type = 'customer.subscription.updated'; }
			else { $id = self::object_id( $session['payment_intent'] ?? '' ); $type = 'payment_intent.succeeded'; }
			if ( ! $id ) { return self::pending( $order, $session ); }
		} elseif ( str_starts_with( $type, 'invoice.' ) ) {
			$invoice = self::fetch( 'invoices', $id, 'in_', $mode );
			if ( is_wp_error( $invoice ) ) { return $invoice; }
			$id = self::object_id( $invoice['subscription'] ?? '' );
			$type = 'customer.subscription.updated';
		} elseif ( str_starts_with( $type, 'charge.dispute.' ) ) {
			$dispute = self::fetch( 'disputes', $id, 'dp_', $mode );
			if ( is_wp_error( $dispute ) ) { return $dispute; }
			$id = self::object_id( $dispute['charge'] ?? '' );
			$type = 'charge.refunded';
		}
		if ( str_starts_with( $type, 'charge.' ) ) {
			$charge = self::fetch( 'charges', $id, 'ch_', $mode );
			if ( is_wp_error( $charge ) ) { return $charge; }
			if ( ! empty( $charge['invoice'] ) ) {
				$invoice = self::fetch( 'invoices', self::object_id( $charge['invoice'] ), 'in_', $mode );
				if ( is_wp_error( $invoice ) ) { return $invoice; }
				$id = self::object_id( $invoice['subscription'] ?? '' ); $type = 'customer.subscription.updated';
			} else { $id = self::object_id( $charge['payment_intent'] ?? '' ); $type = 'payment_intent.succeeded'; }
		}
		if ( str_starts_with( $type, 'customer.subscription.' ) ) {
			$subscription = self::fetch( 'subscriptions', $id, 'sub_', $mode );
			if ( is_wp_error( $subscription ) ) { return $subscription; }
			$resolved = self::resolve( $subscription, $mode );
			if ( is_wp_error( $resolved ) ) { return $resolved; }
			if ( $order && $order['id'] !== $resolved['id'] ) { return self::error( 'stripe_order_binding' ); }
			return self::monthly( $resolved, $subscription, $session );
		}
		if ( str_starts_with( $type, 'payment_intent.' ) ) {
			$payment = self::fetch( 'payment_intents', $id, 'pi_', $mode );
			if ( is_wp_error( $payment ) ) { return $payment; }
			// Recurring payment events can be routed through their canonical invoice.
			if ( ! empty( $payment['invoice'] ) ) {
				return self::reconcile( array( 'provider' => 'stripe', 'mode' => $mode, 'type' => 'invoice.paid', 'object_id' => self::object_id( $payment['invoice'] ) ) );
			}
			$resolved = self::resolve( $payment, $mode );
			if ( is_wp_error( $resolved ) ) { return $resolved; }
			if ( $order && $order['id'] !== $resolved['id'] ) { return self::error( 'stripe_order_binding' ); }
			return self::lifetime( $resolved, $payment, $session );
		}
		return self::error( 'stripe_event_ignored' );
	}

	public static function portal( array $order ): string|WP_Error {
		$order = self::stored_order( $order );
		if ( is_wp_error( $order ) ) { return $order; }
		$verified = self::management_object( $order );
		if ( is_wp_error( $verified ) ) { return $verified; }
		$result = self::api( 'POST', 'billing_portal/sessions', $order['mode'], array( 'customer' => $order['customer_id'], 'return_url' => home_url( '/account/' ) ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return self::hosted_url( $result['url'] ?? '', 'billing.stripe.com' ) ? $result['url'] : self::error( 'stripe_portal_url' );
	}

	public static function cancel( array $order ): array|WP_Error {
		$order = self::stored_order( $order );
		if ( is_wp_error( $order ) ) { return $order; }
		if ( 'monthly' !== $order['plan'] || ! self::id( $order['subscription_id'] ?? '', 'sub_' ) ) { return self::error( 'stripe_subscription_missing' ); }
		$subscription = self::management_object( $order );
		if ( is_wp_error( $subscription ) ) { return $subscription; }
		if ( in_array( $subscription['status'] ?? '', array( 'canceled', 'incomplete_expired' ), true ) ) { return array( 'id' => $subscription['id'], 'cancel_at_period_end' => true ); }
		$result = self::api( 'POST', 'subscriptions/' . rawurlencode( $subscription['id'] ), $order['mode'], array( 'cancel_at_period_end' => 'true' ), self::key( $order, 'cancel' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		$bound = self::bind( $result, $order );
		if ( is_wp_error( $bound ) || ( $result['id'] ?? '' ) !== $subscription['id'] || true !== ( $result['cancel_at_period_end'] ?? false ) ) { return self::error( 'stripe_cancel_binding' ); }
		return array( 'id' => $result['id'], 'cancel_at_period_end' => true );
	}

	private static function monthly( array $order, array $subscription, ?array $session ): array|WP_Error {
		if ( 'monthly' !== $order['plan'] || ( ! empty( $order['subscription_id'] ) && $order['subscription_id'] !== $subscription['id'] ) ) { return self::error( 'stripe_subscription_binding' ); }
		$price = self::configured_price( $order );
		if ( is_wp_error( $price ) ) { return $price; }
		$items = $subscription['items'] ?? array();
		if ( ! empty( $items['has_more'] ) || 1 !== count( $items['data'] ?? array() ) || 1 !== ( $items['data'][0]['quantity'] ?? null ) || is_wp_error( self::validate_price( $items['data'][0]['price'] ?? array(), $order, $price['id'] ) ) || ( $subscription['currency'] ?? '' ) !== 'eur' ) { return self::error( 'stripe_subscription_price' ); }
		$customer = self::object_id( $subscription['customer'] ?? '' );
		if ( ! self::id( $customer, 'cus_' ) || ( ! empty( $order['customer_id'] ) && $customer !== $order['customer_id'] ) || ( $session && self::object_id( $session['customer'] ?? '' ) !== $customer ) ) { return self::error( 'stripe_customer_binding' ); }
		$invoices = self::api( 'GET', 'invoices', $order['mode'], array( 'subscription' => $subscription['id'], 'status' => 'paid', 'limit' => 100 ) );
		if ( is_wp_error( $invoices ) ) { return $invoices; }
		// Do not silently reconcile an incomplete financial history.
		if ( ! is_array( $invoices['data'] ?? null ) || ! empty( $invoices['has_more'] ) ) { return self::error( 'stripe_invoice_history_incomplete' ); }
		$history = array();
		foreach ( $invoices['data'] as $invoice ) {
			$validated = self::paid_invoice( $invoice, $subscription, $order, $price['id'], false );
			if ( is_wp_error( $validated ) ) { return $validated; }
			$history[] = array( 'invoice' => $invoice, 'end' => $validated['end'] );
		}
		usort( $history, static fn( array $a, array $b ): int => $b['end'] <=> $a['end'] );
		$best = null;
		$affected = null;
		foreach ( $history as $entry ) {
			$validated = self::paid_invoice( $entry['invoice'], $subscription, $order, $price['id'] );
			if ( is_wp_error( $validated ) ) { return $validated; }
			if ( ! $affected ) { $affected = $validated; }
			if ( 'active' === $validated['status'] ) { $best = $validated; break; }
		}
		$cancel = ! empty( $subscription['cancel_at_period_end'] ) || 'canceled' === ( $subscription['status'] ?? '' );
		$fields = array( 'subscription_id' => $subscription['id'], 'customer_id' => $customer, 'cancel_at_period_end' => $cancel ? 1 : 0, 'next_payment_at' => $cancel ? null : self::date( $subscription['current_period_end'] ?? 0 ) );
		if ( $session ) { $fields['checkout_id'] = $session['id']; }
		if ( ! $best && ! $affected ) { return self::pending( $order, $session, $fields ); }
		// An expired older cycle cannot conceal a current refund or lost dispute.
		if ( $best && $best['end'] <= time() && $affected && 'active' !== $affected['status'] ) { $best = null; }
		$paid = $best ?? $affected;
		$status = $best ? ( $best['end'] > time() ? 'active' : 'expired' ) : $affected['status'];
		$fields['status'] = $status;
		$fields['payment_id'] = $paid['payment_id'];
		$entitlement = self::entitlement( $order, 'subscription:' . $subscription['id'], $subscription['id'], $customer, $paid['payment_id'] );
		$entitlement += array( 'status' => $status, 'valid_until' => self::date( $paid['end'] ), 'last_invoice_id' => $paid['invoice_id'], 'cancel_at_period_end' => $fields['cancel_at_period_end'], 'next_payment_at' => $fields['next_payment_at'], 'next_check_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) );
		return array( 'order_id' => $order['id'], 'order_fields' => $fields, 'entitlement' => $entitlement );
	}

	private static function paid_invoice( array $invoice, array $subscription, array $order, string $price_id, bool $settle = true ): array|WP_Error {
		if ( ! self::matches_mode( $invoice, $order['mode'] ) || ! self::id( $invoice['id'] ?? '', 'in_' ) || self::object_id( $invoice['subscription'] ?? '' ) !== $subscription['id'] || self::object_id( $invoice['customer'] ?? '' ) !== self::object_id( $subscription['customer'] ?? '' ) || true !== ( $invoice['paid'] ?? false ) || 'paid' !== ( $invoice['status'] ?? '' ) || 'eur' !== ( $invoice['currency'] ?? '' ) || 2999 !== ( $invoice['amount_paid'] ?? null ) || 2999 !== ( $invoice['amount_due'] ?? null ) || 2999 !== ( $invoice['total'] ?? null ) || ! in_array( $invoice['billing_reason'] ?? '', array( 'subscription_create', 'subscription_cycle' ), true ) ) { return self::error( 'stripe_invoice_payment' ); }
		$lines = $invoice['lines'] ?? array();
		if ( ! empty( $lines['has_more'] ) || 1 !== count( $lines['data'] ?? array() ) ) { return self::error( 'stripe_invoice_lines' ); }
		$line = $lines['data'][0];
		$start = $line['period']['start'] ?? 0;
		$end = $line['period']['end'] ?? 0;
		if ( 'subscription' !== ( $line['type'] ?? '' ) || self::object_id( $line['subscription'] ?? '' ) !== $subscription['id'] || 1 !== ( $line['quantity'] ?? null ) || ! empty( $line['proration'] ) || 2999 !== ( $line['amount'] ?? null ) || 'eur' !== ( $line['currency'] ?? '' ) || ! is_int( $start ) || ! is_int( $end ) || $start < 1 || $end <= $start || $end - $start < 28 * DAY_IN_SECONDS || $end - $start > 32 * DAY_IN_SECONDS || $start > time() + self::TOLERANCE || is_wp_error( self::validate_price( $line['price'] ?? array(), $order, $price_id ) ) ) { return self::error( 'stripe_invoice_lines' ); }
		$payment_id = self::object_id( $invoice['payment_intent'] ?? '' );
		if ( ! $settle ) { return array( 'end' => $end ); }
		$payment = self::fetch( 'payment_intents', $payment_id, 'pi_', $order['mode'] );
		if ( is_wp_error( $payment ) ) { return $payment; }
		if ( self::object_id( $payment['invoice'] ?? '' ) !== $invoice['id'] || self::object_id( $payment['customer'] ?? '' ) !== self::object_id( $subscription['customer'] ?? '' ) ) { return self::error( 'stripe_invoice_binding' ); }
		$settled = self::settled( $payment, $order );
		if ( is_wp_error( $settled ) ) { return $settled; }
		return array( 'invoice_id' => $invoice['id'], 'payment_id' => $payment_id, 'end' => $end, 'status' => $settled );
	}

	private static function lifetime( array $order, array $payment, ?array $session ): array|WP_Error {
		if ( 'lifetime' !== $order['plan'] || ( ! empty( $order['payment_id'] ) && $order['payment_id'] !== $payment['id'] ) ) { return self::error( 'stripe_payment_binding' ); }
		// A PI alone has no price/product. Verify its original bound Checkout line items too.
		if ( ! $session ) {
			$session = self::fetch( 'checkout/sessions', $order['checkout_id'] ?? '', 'cs_', $order['mode'], array( 'expand' => array( 'line_items.data.price' ) ) );
			if ( is_wp_error( $session ) ) { return $session; }
		}
		$bound = self::bind( $session, $order );
		$lines = self::checkout_lines( $session, $order );
		if ( is_wp_error( $bound ) || is_wp_error( $lines ) || self::object_id( $session['payment_intent'] ?? '' ) !== $payment['id'] || ( $session['client_reference_id'] ?? '' ) !== $order['id'] || ( ! empty( $order['checkout_id'] ) && $order['checkout_id'] !== $session['id'] ) ) { return self::error( 'stripe_checkout_binding' ); }
		$customer = self::object_id( $payment['customer'] ?? '' );
		if ( ! self::id( $customer, 'cus_' ) || self::object_id( $session['customer'] ?? '' ) !== $customer || ( ! empty( $order['customer_id'] ) && $order['customer_id'] !== $customer ) ) { return self::error( 'stripe_customer_binding' ); }
		if ( 'succeeded' !== ( $payment['status'] ?? '' ) ) { return self::pending( $order, $session, array( 'payment_id' => $payment['id'], 'customer_id' => $customer ) ); }
		$status = self::settled( $payment, $order );
		if ( is_wp_error( $status ) ) { return $status; }
		if ( 'paid' !== ( $session['payment_status'] ?? '' ) || 'complete' !== ( $session['status'] ?? '' ) ) { return self::error( 'stripe_checkout_unpaid' ); }
		$entitlement = self::entitlement( $order, 'payment:' . $payment['id'], $payment['id'], $customer, $payment['id'] );
		$entitlement += array( 'status' => $status, 'valid_until' => null, 'cancel_at_period_end' => 0, 'next_payment_at' => null, 'next_check_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) );
		return array( 'order_id' => $order['id'], 'order_fields' => array( 'status' => $status, 'checkout_id' => $session['id'], 'payment_id' => $payment['id'], 'customer_id' => $customer ), 'entitlement' => $entitlement );
	}

	/** Return canonical financial standing, never an access grant from subscription status. */
	private static function settled( array $payment, array $order ): string|WP_Error {
		$amount = 'monthly' === $order['plan'] ? 2999 : 8000;
		if ( ! self::matches_mode( $payment, $order['mode'] ) || 'succeeded' !== ( $payment['status'] ?? '' ) || 'eur' !== ( $payment['currency'] ?? '' ) || $amount !== ( $payment['amount'] ?? null ) || $amount !== ( $payment['amount_received'] ?? null ) ) { return self::error( 'stripe_payment_unsettled' ); }
		$charge = self::fetch( 'charges', self::object_id( $payment['latest_charge'] ?? '' ), 'ch_', $order['mode'] );
		if ( is_wp_error( $charge ) ) { return $charge; }
		if ( self::object_id( $charge['payment_intent'] ?? '' ) !== $payment['id'] || self::object_id( $charge['customer'] ?? '' ) !== self::object_id( $payment['customer'] ?? '' ) || true !== ( $charge['paid'] ?? false ) || true !== ( $charge['captured'] ?? false ) || 'succeeded' !== ( $charge['status'] ?? '' ) || 'eur' !== ( $charge['currency'] ?? '' ) || $amount !== ( $charge['amount'] ?? null ) || ! is_int( $charge['amount_refunded'] ?? null ) || $charge['amount_refunded'] < 0 || $charge['amount_refunded'] > $amount || ! is_bool( $charge['disputed'] ?? null ) || ! is_bool( $charge['refunded'] ?? null ) ) { return self::error( 'stripe_charge_unsettled' ); }
		if ( $charge['amount_refunded'] === $amount ) { return $charge['refunded'] ? 'revoked' : self::error( 'stripe_refund_unsettled' ); }
		if ( ! empty( $charge['disputed'] ) ) {
			$disputes = self::api( 'GET', 'disputes', $order['mode'], array( 'charge' => $charge['id'], 'limit' => 100 ) );
			if ( is_wp_error( $disputes ) ) { return $disputes; }
			if ( ! is_array( $disputes['data'] ?? null ) || ! $disputes['data'] || ! empty( $disputes['has_more'] ) ) { return self::error( 'stripe_dispute_unknown' ); }
			$status = 'active';
			foreach ( $disputes['data'] as $dispute ) {
				if ( ! self::matches_mode( $dispute, $order['mode'] ) || self::object_id( $dispute['charge'] ?? '' ) !== $charge['id'] || ! in_array( $dispute['status'] ?? '', array( 'won', 'lost', 'needs_response', 'under_review', 'warning_needs_response', 'warning_under_review', 'warning_closed' ), true ) ) { return self::error( 'stripe_dispute_unknown' ); }
				if ( 'lost' === $dispute['status'] ) { return 'revoked'; }
				if ( ! in_array( $dispute['status'], array( 'won', 'warning_closed' ), true ) ) { $status = 'disputed'; }
			}
			return $status;
		}
		return 'active';
	}

	private static function checkout_lines( array $session, array $order ): bool|WP_Error {
		$price = self::configured_price( $order );
		if ( is_wp_error( $price ) ) { return $price; }
		$lines = $session['line_items'] ?? array();
		$expected = 'monthly' === $order['plan'] ? 'subscription' : 'payment';
		if ( ( $session['mode'] ?? '' ) !== $expected || ( $session['currency'] ?? '' ) !== 'eur' || ( $session['amount_total'] ?? null ) !== (int) $order['amount'] || ! empty( $lines['has_more'] ) || 1 !== count( $lines['data'] ?? array() ) || 1 !== ( $lines['data'][0]['quantity'] ?? null ) || ( $lines['data'][0]['amount_total'] ?? null ) !== (int) $order['amount'] || is_wp_error( self::validate_price( $lines['data'][0]['price'] ?? array(), $order, $price['id'] ) ) ) { return self::error( 'stripe_checkout_price' ); }
		return true;
	}

	private static function configured_price( array $order, bool $require_active = false ): array|WP_Error {
		$id = TC_Billing::setting( 'stripe_' . $order['plan'] . '_price_id', $order['mode'] );
		$price = self::fetch( 'prices', $id, 'price_', $order['mode'] );
		if ( is_wp_error( $price ) ) { return $price; }
		$valid = self::validate_price( $price, $order, $id );
		if ( is_wp_error( $valid ) || ( $require_active && true !== ( $price['active'] ?? false ) ) ) { return self::error( 'stripe_configured_price' ); }
		$product_id = TC_Billing::setting( 'stripe_' . $order['plan'] . '_product_id', $order['mode'] );
		$product = self::fetch( 'products', $product_id, 'prod_', $order['mode'] );
		if ( is_wp_error( $product ) ) { return $product; }
		if ( $require_active && true !== ( $product['active'] ?? false ) ) { return self::error( 'stripe_configured_product' ); }
		return $price;
	}

	private static function validate_price( mixed $price, array $order, string $id ): bool|WP_Error {
		if ( ! is_array( $price ) || ! self::matches_mode( $price, $order['mode'] ) || ( $price['id'] ?? '' ) !== $id || self::object_id( $price['product'] ?? '' ) !== TC_Billing::setting( 'stripe_' . $order['plan'] . '_product_id', $order['mode'] ) || ( $price['currency'] ?? '' ) !== 'eur' || ( $price['unit_amount'] ?? null ) !== ( 'monthly' === $order['plan'] ? 2999 : 8000 ) || ( $price['billing_scheme'] ?? '' ) !== 'per_unit' || ! empty( $price['transform_quantity'] ) || ! empty( $price['custom_unit_amount'] ) ) { return self::error( 'stripe_price_mismatch' ); }
		if ( 'monthly' === $order['plan'] ) {
			if ( 'recurring' !== ( $price['type'] ?? '' ) || 'month' !== ( $price['recurring']['interval'] ?? '' ) || 1 !== ( $price['recurring']['interval_count'] ?? null ) || 'licensed' !== ( $price['recurring']['usage_type'] ?? '' ) ) { return self::error( 'stripe_price_interval' ); }
		} elseif ( 'one_time' !== ( $price['type'] ?? '' ) || ! empty( $price['recurring'] ) ) { return self::error( 'stripe_price_interval' ); }
		return true;
	}

	private static function management_object( array $order ): array|WP_Error {
		if ( ! self::id( $order['customer_id'] ?? '', 'cus_' ) ) { return self::error( 'stripe_customer_missing' ); }
		$monthly = 'monthly' === $order['plan'];
		$object = self::fetch( $monthly ? 'subscriptions' : 'payment_intents', $order[ $monthly ? 'subscription_id' : 'payment_id' ] ?? '', $monthly ? 'sub_' : 'pi_', $order['mode'] );
		if ( is_wp_error( $object ) ) { return $object; }
		$bound = self::bind( $object, $order );
		if ( is_wp_error( $bound ) || self::object_id( $object['customer'] ?? '' ) !== $order['customer_id'] ) { return self::error( 'stripe_customer_binding' ); }
		return $object;
	}

	private static function stored_order( array $order ): array|WP_Error {
		if ( ! is_string( $order['id'] ?? null ) || ! preg_match( '/^[a-f0-9-]{36}$/Di', $order['id'] ) ) { return self::error( 'stripe_order' ); }
		$stored = TC_Billing_Store::order( $order['id'] );
		if ( ! $stored ) { return self::error( 'stripe_order_missing' ); }
		foreach ( array( 'id', 'user_id', 'discord_id', 'provider', 'mode', 'plan', 'amount', 'currency' ) as $key ) {
			if ( ! array_key_exists( $key, $order ) || ! array_key_exists( $key, $stored ) || (string) $order[ $key ] !== (string) $stored[ $key ] ) { return self::error( 'stripe_order_binding' ); }
		}
		if ( 'stripe' !== $stored['provider'] || ! in_array( $stored['mode'], array( 'test', 'live' ), true ) || ! TC_Billing::mode_allowed( $stored['mode'] ) || ! in_array( $stored['plan'], array( 'monthly', 'lifetime' ), true ) || (int) $stored['amount'] !== ( 'monthly' === $stored['plan'] ? 2999 : 8000 ) || 'eur' !== $stored['currency'] || (int) $stored['user_id'] < 1 || ! preg_match( '/^[0-9]{5,25}$/D', (string) $stored['discord_id'] ) ) { return self::error( 'stripe_order_binding' ); }
		return $stored;
	}

	private static function metadata( array $order ): array {
		return array( 'tc_order_id' => $order['id'], 'tc_user_id' => (string) $order['user_id'], 'tc_discord_id' => (string) $order['discord_id'], 'tc_plan' => $order['plan'], 'tc_mode' => $order['mode'] );
	}

	private static function bind( array $object, array $order ): bool|WP_Error {
		if ( ! self::matches_mode( $object, $order['mode'] ) ) { return self::error( 'stripe_object_mode' ); }
		foreach ( self::metadata( $order ) as $key => $value ) {
			if ( ( $object['metadata'][ $key ] ?? null ) !== $value ) { return self::error( 'stripe_identity_binding' ); }
		}
		return true;
	}

	private static function resolve( array $object, string $mode ): array|WP_Error {
		$id = $object['metadata']['tc_order_id'] ?? '';
		if ( ! is_string( $id ) ) { return self::error( 'stripe_order_missing' ); }
		$order = TC_Billing_Store::order( $id );
		if ( ! $order || ( $order['mode'] ?? '' ) !== $mode ) { return self::error( 'stripe_order_missing' ); }
		$order = self::stored_order( $order );
		if ( is_wp_error( $order ) ) { return $order; }
		$valid = self::bind( $object, $order );
		return is_wp_error( $valid ) ? $valid : $order;
	}

	private static function pending( array $order, ?array $session, array $fields = array() ): array {
		if ( $session ) { $fields['checkout_id'] = $session['id']; }
		// No entitlement update: a failed or incomplete renewal cannot erase prepaid time.
		if ( ! in_array( $order['status'] ?? '', array( 'active', 'expired', 'revoked', 'disputed' ), true ) ) { $fields['status'] = 'pending'; }
		return array( 'order_id' => $order['id'], 'order_fields' => $fields );
	}

	private static function entitlement( array $order, string $source, string $external, string $customer, string $payment ): array {
		return array( 'source' => 'stripe:' . $order['mode'] . ':' . $source, 'user_id' => (int) $order['user_id'], 'discord_id' => (string) $order['discord_id'], 'provider' => 'stripe', 'mode' => $order['mode'], 'plan' => $order['plan'], 'external_id' => $external, 'order_id' => $order['id'], 'customer_id' => $customer, 'payment_id' => $payment );
	}

	private static function fetch( string $collection, string $id, string $prefix, string $mode, array $args = array() ): array|WP_Error {
		if ( ! self::id( $id, $prefix ) ) { return self::error( 'stripe_external_id' ); }
		$result = self::api( 'GET', $collection . '/' . rawurlencode( $id ), $mode, $args );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( ( $result['id'] ?? '' ) !== $id || ! self::matches_mode( $result, $mode ) ) { return self::error( 'stripe_object_binding' ); }
		return $result;
	}

	private static function api( string $method, string $path, string $mode, array $params = array(), string $key = '' ): array|WP_Error {
		if ( ! TC_Billing::mode_allowed( $mode ) || ! in_array( $mode, array( 'test', 'live' ), true ) || ! preg_match( '~^[a-z_]+(?:/[a-zA-Z0-9_]+){0,2}$~D', $path ) ) { return self::error( 'stripe_request' ); }
		$secret = TC_Billing::secret( 'STRIPE_SECRET_KEY', $mode );
		if ( ! preg_match( '/^(?:sk|rk)_' . $mode . '_[a-zA-Z0-9]+$/D', $secret ) ) { return self::error( 'stripe_key_mode' ); }
		$headers = array( 'Authorization' => 'Bearer ' . $secret, 'Stripe-Version' => self::VERSION, 'Content-Type' => 'application/x-www-form-urlencoded' );
		if ( $key ) { $headers['Idempotency-Key'] = $key; }
		$args = array( 'method' => $method, 'headers' => $headers, 'timeout' => 20, 'redirection' => 0, 'sslverify' => true, 'reject_unsafe_urls' => true, 'limit_response_size' => 1048576 );
		$url = self::API . $path;
		$query = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		if ( 'GET' === $method ) { if ( $query ) { $url .= '?' . $query; } }
		else { $args['body'] = $query; }
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) { return self::error( 'stripe_network' ); }
		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			$retry = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			return new WP_Error( 429 === $status ? 'stripe_rate_limited' : 'stripe_api_error', 'Stripe is temporarily unavailable.', array( 'retry_after' => max( 60, min( 3600, $retry ) ) ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && ! isset( $data['error'] ) ? $data : self::error( 'stripe_response' );
	}

	private static function matches_mode( array $object, string $mode ): bool { return isset( $object['livemode'] ) && is_bool( $object['livemode'] ) && $object['livemode'] === ( 'live' === $mode ); }
	private static function object_id( mixed $object ): string { return is_string( $object ) ? $object : ( is_array( $object ) && is_string( $object['id'] ?? null ) ? $object['id'] : '' ); }
	private static function id( mixed $id, string $prefix ): bool { return is_string( $id ) && strlen( $id ) <= 255 && str_starts_with( $id, $prefix ) && (bool) preg_match( '/^[a-zA-Z0-9_]{3,255}$/D', $id ); }
	private static function date( mixed $timestamp ): ?string { return is_int( $timestamp ) && $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null; }
	private static function key( array $order, string $operation ): string { return 'tc-' . $operation . '-' . hash( 'sha256', $order['mode'] . ':' . $order['id'] ); }
	private static function error( string $code ): WP_Error { return TC_Billing::error( $code, 'Stripe billing could not be verified. Please try again or contact support.' ); }
	private static function hosted_url( mixed $url, string $host ): bool {
		if ( ! is_string( $url ) ) { return false; }
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && $host === strtolower( $parts['host'] ?? '' ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ( ! isset( $parts['port'] ) || 443 === $parts['port'] );
	}
}
