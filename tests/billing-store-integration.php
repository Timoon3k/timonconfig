<?php
/** Disposable localhost WordPress only. No provider HTTP or Discord writes. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Billing_Store' ) || ! class_exists( 'TC_Billing' ) ) { throw new RuntimeException( 'Billing classes are required.' ); }

global $wpdb;
$checks = 0; $uids = array(); $orders = array(); $events = array(); $sources = array();
$tag = 'tc_fixture_' . strtolower( wp_generate_password( 12, false ) );
$discord = '899999999999999971'; $other_discord = '899999999999999972';
$guild = '899999999999999973'; $role = '899999999999999974';
$tables = array();
foreach ( array( 'orders', 'entitlements', 'events', 'sync_jobs', 'owned_roles', 'leases' ) as $suffix ) { $tables[ $suffix ] = $wpdb->prefix . 'tc_billing_' . $suffix; }
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void { if ( ! $condition ) { throw new RuntimeException( 'Billing ledger: ' . $message ); } ++$checks; };
$must = static function ( $result ) { if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Unexpected safe ledger error: ' . $result->get_error_code() ); } return $result; };
$make_order = static function ( int $uid, string $mode = 'test', string $plan = 'monthly', string $provider = 'stripe' ) use ( &$orders, $discord, $must ) {
	$order = $must( TC_Billing_Store::create_order( $uid, $discord, $provider, $mode, $plan ) ); $orders[] = $order['id']; return $order;
};
$make_event = static function ( array $order, string $name ) use ( &$events, $tag, $must ) {
	$event = $must( TC_Billing_Store::enqueue_event( $order['provider'], $order['mode'], array( 'id' => $tag . '_' . $name, 'type' => 'invoice.paid', 'object_id' => 'in_' . $tag, 'created' => time(), 'order_id' => $order['id'] ) ) ); $events[] = (int) $event['id']; return $event;
};
$entitlement = static function ( array $order, string $source, string $status = 'active', ?string $until = null ) use ( &$sources ) {
	$sources[] = $source;
	return array( 'source' => $source, 'user_id' => (int) $order['user_id'], 'discord_id' => $order['discord_id'], 'provider' => $order['provider'], 'mode' => $order['mode'], 'plan' => $order['plan'], 'order_id' => $order['id'], 'status' => $status, 'valid_until' => $until, 'external_id' => 'sub_' . $source, 'payment_id' => 'pi_' . $source, 'last_invoice_id' => 'in_' . $source );
};
$network_calls = 0;
$block_http = static function () use ( &$network_calls ) { ++$network_calls; throw new RuntimeException( 'Billing store must never make HTTP requests.' ); };
add_filter( 'pre_http_request', $block_http, 1 );
try {
	TC_Billing_Store::install(); TC_Billing_Store::init();
	$assert( '1' === get_option( 'timonconfigs_billing_db_version' ), 'Successful installation records a version.' );
	foreach ( $tables as $table ) {
		$state = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
		$assert( $state && 'InnoDB' === $state['Engine'], 'Every ledger/outbox/lease table is InnoDB.' );
	}
	foreach ( array( 'first', 'second' ) as $name ) {
		$uid = wp_insert_user( array( 'user_login' => $tag . '_' . $name, 'user_pass' => wp_generate_password( 40, true, true ), 'role' => 'subscriber' ) );
		if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Could not create disposable ledger fixture.' ); } $uids[] = $uid;
	}
	list( $uid, $other_uid ) = $uids;
	$monthly = $make_order( $uid ); $lifetime = $make_order( $uid, 'test', 'lifetime' ); $live = $make_order( $uid, 'live' );
	$assert( 2999 === (int) $monthly['amount'] && 'eur' === $monthly['currency'] && 'pending' === $monthly['status'], 'Monthly price/currency/status come from the immutable central plan.' );
	$assert( 8000 === (int) $lifetime['amount'], 'Lifetime price comes from the immutable central plan.' );
	$assert( 36 === strlen( $monthly['id'] ) && $monthly['id'] !== $lifetime['id'], 'Each order has an independent UUID.' );
	$assert( 2 === count( TC_Billing_Store::orders_for_user( $uid, 'test' ) ) && 1 === count( TC_Billing_Store::orders_for_user( $uid, 'live' ) ), 'Order reads isolate test and live.' );
	$assert( array() === TC_Billing_Store::orders_for_user( $other_uid, 'test' ) && array() === TC_Billing_Store::orders_for_user( $uid, 'invalid' ), 'Order reads isolate accounts and reject unknown modes.' );
	foreach ( array( 'id' => wp_generate_uuid4(), 'user_id' => $other_uid, 'discord_id' => $other_discord, 'provider' => 'paypal', 'mode' => 'live', 'plan' => 'lifetime', 'amount' => 1, 'currency' => 'usd', 'created_at' => '2000-01-01 00:00:00' ) as $key => $value ) {
		$assert( is_wp_error( TC_Billing_Store::update_order( $monthly['id'], array( $key => $value ) ) ), 'An order identity, immutable amount or creation time cannot be rebound.' );
	}
	$assert( is_wp_error( TC_Billing_Store::update_order( $monthly['id'], array( 'consent_at' => '2026-02-30 00:00:00' ) ) ), 'Invalid dates are rejected.' );
	$assert( is_wp_error( TC_Billing_Store::update_order( $monthly['id'], array( 'customer_id' => 'customer@example.com' ) ) ), 'Email addresses cannot enter identifier columns.' );
	$assert( is_wp_error( TC_Billing_Store::create_order( $uid, $discord, 'stripe', 'test', 'browser-price' ) ), 'Unknown plans cannot create orders.' );
	$must( TC_Billing_Store::update_order( $monthly['id'], array( 'checkout_id' => 'cs_' . $tag, 'subscription_id' => 'sub_' . $tag, 'consent_at' => gmdate( 'Y-m-d H:i:s' ), 'consent_version' => 'recurring-v1' ) ) );
	$assert( $monthly['id'] === TC_Billing_Store::find_order( 'stripe', 'test', 'sub_' . $tag )['id'], 'Trusted canonical subscription IDs resolve an order.' );
	$assert( null === TC_Billing_Store::find_order( 'stripe', 'live', 'sub_' . $tag ) && null === TC_Billing_Store::find_order( 'paypal', 'test', 'sub_' . $tag ), 'External ID lookups isolate mode and provider.' );
	$assert( null === TC_Billing_Store::find_order( 'stripe', 'test', strtoupper( 'sub_' . $tag ) ), 'Opaque external identifiers are case-sensitive.' );
	$event = $make_event( $monthly, 'paid' );
	$duplicate = $must( TC_Billing_Store::enqueue_event( 'stripe', 'test', array( 'id' => $event['event_id'], 'type' => 'different.type', 'object_id' => 'different_object', 'created' => 1, 'raw_body' => 'PRIVATE_PAYLOAD' ) ) );
	$assert( $duplicate['id'] === $event['id'] && 'invoice.paid' === $duplicate['type'] && '0' === (string) $duplicate['attempts'], 'Duplicate delivery returns the original event without changing its descriptor.' );
	$assert( ! array_key_exists( 'raw_body', $duplicate ), 'Only the bounded webhook descriptor is persisted.' );
	$other_mode_event = $must( TC_Billing_Store::enqueue_event( 'stripe', 'live', array( 'id' => $event['event_id'], 'type' => 'invoice.paid', 'object_id' => 'in_live', 'created' => time() ) ) ); $events[] = (int) $other_mode_event['id'];
	$assert( $other_mode_event['id'] !== $event['id'], 'Event uniqueness includes mode.' );
	$case_event = $must( TC_Billing_Store::enqueue_event( 'stripe', 'test', array( 'id' => strtoupper( $event['event_id'] ), 'type' => 'invoice.paid', 'object_id' => 'in_case', 'created' => time() ) ) ); $events[] = (int) $case_event['id'];
	$assert( $case_event['id'] !== $event['id'], 'Event uniqueness treats opaque IDs as case-sensitive.' );
	$assert( is_wp_error( TC_Billing_Store::enqueue_event( 'stripe', 'test', array( 'id' => str_repeat( 'x', 192 ), 'type' => 'invoice.paid', 'object_id' => 'in_test', 'created' => time() ) ) ), 'Oversized webhook identifiers are rejected before SQL.' );
	$until = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
	$paid = $entitlement( $monthly, $tag . ':stripe:monthly', 'active', $until );
	$assert( true === $must( TC_Billing_Store::apply_event( (int) $event['id'], array( 'order_id' => $monthly['id'], 'order_fields' => array( 'status' => 'active', 'payment_id' => 'pi_' . $tag ), 'entitlement' => $paid ) ) ), 'Canonical payment commits a full transition.' );
	$assert( 'active' === TC_Billing_Store::order( $monthly['id'] )['status'] && 'done' === TC_Billing_Store::event( (int) $event['id'] )['status'], 'Order and inbox event commit together.' );
	$assert( 1 === count( TC_Billing_Store::active( $uid, 'test' ) ) && 'pending' === TC_Billing_Store::sync_state( $uid, 'test' )['status'], 'The entitlement and role-sync outbox commit together.' );
	$before_generation = TC_Billing_Store::sync_state( $uid, 'test' )['generation'];
	$assert( true === TC_Billing_Store::apply_event( (int) $event['id'], array( 'order_id' => $lifetime['id'], 'order_fields' => array( 'status' => 'failed' ) ) ), 'Replaying a completed event is a no-op.' );
	$assert( 'pending' === TC_Billing_Store::order( $lifetime['id'] )['status'] && $before_generation === TC_Billing_Store::sync_state( $uid, 'test' )['generation'], 'Replay never mutates another order or requeues side effects.' );
	foreach ( array( 'user_id' => $other_uid, 'discord_id' => $other_discord, 'provider' => 'paypal', 'mode' => 'live', 'plan' => 'lifetime' ) as $key => $value ) {
		$changed = $paid; $changed[ $key ] = $value;
		$assert( is_wp_error( TC_Billing_Store::upsert_entitlement( $changed ) ), 'An entitlement source cannot be rebound to another identity or plan.' );
	}
	$failed_renewal = $paid; $failed_renewal['status'] = 'pending'; $failed_renewal['valid_until'] = null; $failed_renewal['payment_id'] = 'pi_failed';
	$must( TC_Billing_Store::upsert_entitlement( $failed_renewal ) );
	$kept = TC_Billing_Store::entitlement( $paid['source'] );
	$assert( 'active' === $kept['status'] && $until === $kept['valid_until'] && $paid['payment_id'] === $kept['payment_id'], 'Failed renewal preserves the paid period and successful payment anchor.' );
	$assert( array() === TC_Billing_Store::active( $uid, 'live' ) && array() === TC_Billing_Store::active( $other_uid, 'test' ), 'Entitlement reads isolate user and mode.' );
	$operator = array( 'source' => $tag . ':operator', 'user_id' => $uid, 'discord_id' => $discord, 'provider' => 'operator', 'mode' => 'test', 'plan' => 'lifetime', 'status' => 'active', 'valid_until' => null ); $sources[] = $operator['source'];
	$must( TC_Billing_Store::upsert_entitlement( $operator ) );
	$revoked = $paid; $revoked['status'] = 'revoked'; $must( TC_Billing_Store::upsert_entitlement( $revoked ) );
	$active = TC_Billing_Store::active( $uid, 'test' );
	$assert( 1 === count( $active ) && $operator['source'] === $active[0]['source'], 'Refund/revocation of one source preserves the independent operator Lifetime entitlement.' );
	$assert( 2 === count( TC_Billing_Store::entitlements( $uid, 'test' ) ), 'Revoked sources remain in the durable ledger.' );
	$invalid_month = $paid; $invalid_month['source'] = $tag . ':unpaid'; $invalid_month['valid_until'] = null;
	$assert( is_wp_error( TC_Billing_Store::upsert_entitlement( $invalid_month ) ), 'An active monthly entitlement cannot have unlimited access.' );
	$invalid_life = $operator; $invalid_life['valid_until'] = $until;
	$assert( is_wp_error( TC_Billing_Store::upsert_entitlement( $invalid_life ) ), 'Lifetime uses a null expiration.' );

	// Fail the final inbox write, after the order, entitlement and outbox writes.
	// A rollback must remove all three prior writes rather than grant access.
	$rollback_order = $make_order( $other_uid ); $rollback_event = $make_event( $rollback_order, 'rollback' );
	$rollback_paid = $entitlement( $rollback_order, $tag . ':rollback', 'active', $until );
	$faulted = false;
	$fault = static function ( string $sql ) use ( &$faulted, $tables, $rollback_event ): string {
		if ( ! $faulted && str_starts_with( $sql, 'UPDATE ' . $tables['events'] . " SET status = 'done'" ) ) { $faulted = true; return 'UPDATE ' . $tables['events'] . ' SET tc_fixture_missing_column = 1 WHERE id = ' . (int) $rollback_event['id']; }
		return $sql;
	};
	$old_suppress = $wpdb->suppress_errors( true ); add_filter( 'query', $fault );
	try { $rolled_back = TC_Billing_Store::apply_event( (int) $rollback_event['id'], array( 'order_id' => $rollback_order['id'], 'order_fields' => array( 'status' => 'active' ), 'entitlement' => $rollback_paid ) ); }
	finally { remove_filter( 'query', $fault ); $wpdb->suppress_errors( $old_suppress ); }
	$assert( $faulted && is_wp_error( $rolled_back ), 'A failure in the final transaction write returns a safe failure.' );
	$assert( 'pending' === TC_Billing_Store::order( $rollback_order['id'] )['status'], 'A failed transaction rolls back the order update.' );
	$assert( null === TC_Billing_Store::entitlement( $rollback_paid['source'] ) && array() === TC_Billing_Store::sync_state( $other_uid, 'test' ), 'A failed transaction rolls back the entitlement and sync outbox.' );
	$assert( 'pending' === TC_Billing_Store::event( (int) $rollback_event['id'] )['status'] && '0' === (string) TC_Billing_Store::event( (int) $rollback_event['id'] )['attempts'], 'A failed transaction leaves the event retryable.' );
	$assert( true === $must( TC_Billing_Store::apply_event( (int) $rollback_event['id'], array( 'order_id' => $rollback_order['id'], 'order_fields' => array( 'status' => 'active' ), 'entitlement' => $rollback_paid ) ) ), 'The exact same event succeeds after the transient failure is removed.' );
	$cross_event = $make_event( $monthly, 'wrong_order' );
	$assert( is_wp_error( TC_Billing_Store::apply_event( (int) $cross_event['id'], array( 'order_id' => $live['id'], 'order_fields' => array( 'status' => 'paid' ) ) ) ), 'A test event cannot activate a live order.' );
	$cross = $paid; $cross['user_id'] = $other_uid;
	$assert( is_wp_error( TC_Billing_Store::apply_event( (int) $cross_event['id'], array( 'order_id' => $monthly['id'], 'order_fields' => array( 'status' => 'failed' ), 'entitlement' => $cross ) ) ) && 'active' === TC_Billing_Store::order( $monthly['id'] )['status'], 'Mismatched entitlement identity rolls back the order update.' );
	$pending_event = $make_event( $lifetime, 'pending' );
	$assert( true === $must( TC_Billing_Store::apply_event( (int) $pending_event['id'], array( 'order_id' => $lifetime['id'], 'order_fields' => array( 'status' => 'failed' ) ) ) ), 'An unpaid/failed order transition requires no entitlement.' );
	$assert( 1 === count( TC_Billing_Store::active( $uid, 'test' ) ), 'An unpaid order cannot activate a new source.' );
	TC_Billing_Store::event_result( (int) $cross_event['id'], 'error', 'PRIVATE_PAYLOAD token=secret@example.com', 120 );
	$retried = TC_Billing_Store::event( (int) $cross_event['id'] );
	$assert( 'error' === $retried['status'] && 1 === (int) $retried['attempts'] && $retried['next_attempt'] > gmdate( 'Y-m-d H:i:s' ), 'Event failures retain attempts and a future retry time.' );
	$assert( str_starts_with( $retried['last_error'], 'billing_error_' ) && ! str_contains( $retried['last_error'], 'PRIVATE' ), 'Unknown errors are converted to safe deterministic codes.' );
	TC_Billing_Store::event_result( (int) $event['id'], 'pending', 'must_not_reopen' );
	$assert( 'done' === TC_Billing_Store::event( (int) $event['id'] )['status'], 'A completed event is never reopened by a stale worker result.' );
	$assert( count( TC_Billing_Store::due_events( 1 ) ) <= 1, 'Event batches honor their limit.' );
	$due_ids = array_map( 'intval', array_column( TC_Billing_Store::due_events( 100 ), 'id' ) );
	$assert( ! in_array( (int) $cross_event['id'], $due_ids, true ) && ! in_array( (int) $event['id'], $due_ids, true ), 'Backoff and completed events are excluded from due work.' );

	TC_Billing_Store::enqueue_sync( $uid, 'test' ); $job = TC_Billing_Store::sync_state( $uid, 'test' );
	TC_Billing_Store::enqueue_sync( $uid, 'test' );
	$assert( $job['id'] === TC_Billing_Store::sync_state( $uid, 'test' )['id'], 'Role sync has one durable job per user/mode.' );
	TC_Billing_Store::enqueue_sync( $uid, 'live' );
	$assert( $job['id'] !== TC_Billing_Store::sync_state( $uid, 'live' )['id'], 'Test/live role jobs are isolated.' );
	TC_Billing_Store::due_syncs( 100 );
	TC_Billing_Store::enqueue_sync( $uid, 'test' ); TC_Billing_Store::sync_result( (int) $job['id'], 'done' );
	$assert( 'pending' === TC_Billing_Store::sync_state( $uid, 'test' )['status'], 'A stale sync completion cannot consume a newly enqueued generation.' );
	TC_Billing_Store::due_syncs( 100 ); TC_Billing_Store::sync_result( (int) $job['id'], 'error', 'bot_token_secret_with_valid_code_shape', 180 );
	$state = TC_Billing_Store::sync_state( $uid, 'test' );
	$assert( 'error' === $state['status'] && 1 === (int) $state['attempts'] && str_starts_with( $state['last_error'], 'billing_error_' ), 'Sync errors retry with redacted codes, including strings that resemble safe codes.' );
	TC_Billing_Store::enqueue_sync( $uid, 'test' ); TC_Billing_Store::due_syncs( 100 ); TC_Billing_Store::sync_result( (int) $job['id'], 'done' );
	$assert( 'done' === TC_Billing_Store::sync_state( $uid, 'test' )['status'], 'The current generation can complete.' );
	$assert( array() === TC_Billing_Store::owned_roles( $uid, 'test' ), 'Observing an account never adopts an external role.' );
	TC_Billing_Store::own_role( $uid, 'test', $guild, $role, true ); TC_Billing_Store::own_role( $uid, 'test', $guild, $role, true );
	$owned = TC_Billing_Store::owned_roles( $uid, 'test' );
	$assert( 1 === count( $owned ) && $guild === $owned[0]['guild_id'] && $role === $owned[0]['role_id'], 'Module role ownership is exact and idempotent.' );
	$assert( array() === TC_Billing_Store::owned_roles( $uid, 'live' ) && array() === TC_Billing_Store::owned_roles( $other_uid, 'test' ), 'Role ownership is isolated by user and mode.' );
	TC_Billing_Store::own_role( $uid, 'test', $guild, $role, false );
	$assert( array() === TC_Billing_Store::owned_roles( $uid, 'test' ), 'Only the exact ownership marker is removed.' );
	$reconcile_ids = array_column( TC_Billing_Store::due_reconcile( 100 ), 'id' );
	$assert( in_array( $monthly['id'], $reconcile_ids, true ) && ! in_array( $live['id'], $reconcile_ids, true ), 'Only orders with canonical external IDs are reconciled.' );
	TC_Billing_Store::mark_reconciled( $monthly['id'], 3600 );
	$assert( ! in_array( $monthly['id'], array_column( TC_Billing_Store::due_reconcile( 100 ), 'id' ), true ), 'Periodic reconciliation honors its next-check deadline.' );

	$key = $tag . ':lease';
	$inside = TC_Billing_Store::locked( $key, static function () use ( $key ) { return TC_Billing_Store::locked( $key, static fn() => 'unsafe concurrent execution' ); } );
	$assert( is_wp_error( $inside ) && 'billing_busy' === $inside->get_error_code(), 'An unexpired lease excludes a competing worker.' );
	$assert( 'released' === TC_Billing_Store::locked( $key, static fn() => 'released' ), 'A completed callback releases its own lease.' );
	try { TC_Billing_Store::locked( $key, static function () { throw new RuntimeException( 'Fixture exception' ); } ); } catch ( RuntimeException $error ) {}
	$assert( 'released' === TC_Billing_Store::locked( $key, static fn() => 'released' ), 'An exceptional callback still releases its own lease.' );
	$hash = hash( 'sha256', $key );
	$wpdb->insert( $tables['leases'], array( 'lease_key' => $hash, 'nonce' => wp_generate_uuid4(), 'expires_at' => '2000-01-01 00:00:00' ) );
	$assert( 'taken_over' === TC_Billing_Store::locked( $key, static fn() => 'taken_over' ), 'A crashed worker lease expires and can be atomically taken over.' );
	$successor = wp_generate_uuid4();
	TC_Billing_Store::locked( $key, static function () use ( $wpdb, $tables, $hash, $successor ) { $wpdb->update( $tables['leases'], array( 'nonce' => $successor ), array( 'lease_key' => $hash ) ); return true; } );
	$assert( $successor === $wpdb->get_var( $wpdb->prepare( 'SELECT nonce FROM ' . $tables['leases'] . ' WHERE lease_key = %s', $hash ) ), 'A stale lease owner cannot delete its successor lease.' );
	$wpdb->delete( $tables['leases'], array( 'lease_key' => $hash ) );
	$diagnostics = TC_Billing_Store::diagnostics( 1 );
	$assert( count( $diagnostics['events'] ) <= 1 && count( $diagnostics['jobs'] ) <= 1, 'Admin diagnostic lists are bounded.' );
	$all = wp_json_encode( TC_Billing_Store::diagnostics( 100 ) );
	$assert( ! str_contains( $all, 'PRIVATE_PAYLOAD' ) && ! str_contains( $all, 'token=secret' ) && ! str_contains( $all, 'bot_token_secret' ), 'Admin diagnostics contain no raw payload or error secrets.' );

	// Explicitly reviewed external coverage lives in the entitlement ledger.
	// It cannot be mistaken for a checkout provider or webhook event source.
	$external_sources = array();
	foreach ( array( 'legacy', 'grant' ) as $provider ) {
		$external = array( 'source' => $tag . ':external:' . $provider, 'user_id' => $uid, 'discord_id' => $discord, 'provider' => $provider, 'mode' => 'test', 'plan' => 'legacy' === $provider ? 'monthly' : 'lifetime', 'status' => 'active', 'valid_until' => 'legacy' === $provider ? $until : null );
		$sources[] = $external['source']; $external_sources[ $provider ] = $external;
		$assert( true === $must( TC_Billing_Store::upsert_entitlement( $external ) ), 'An explicitly reviewed legacy subscription or grant is preserved as an independent source.' );
		$assert( is_wp_error( TC_Billing_Store::create_order( $uid, $discord, $provider, 'test', 'monthly' ) ), 'Ledger-only providers cannot create checkout orders.' );
		$assert( is_wp_error( TC_Billing_Store::enqueue_event( $provider, 'test', array( 'id' => $tag . '_external_' . $provider, 'type' => 'invoice.paid', 'object_id' => 'external_fixture', 'created' => time() ) ) ), 'Ledger-only providers cannot enter the payment webhook inbox.' );
		$assert( null === TC_Billing_Store::find_order( $provider, 'test', 'sub_' . $tag ), 'Ledger-only providers cannot resolve payment orders.' );
	}
	$active_sources = array_column( TC_Billing_Store::active( $uid, 'test' ), 'source' );
	$assert( 3 === count( $active_sources ) && in_array( $external_sources['legacy']['source'], $active_sources, true ) && in_array( $external_sources['grant']['source'], $active_sources, true ), 'The desired access union contains operator, reviewed legacy and grant coverage.' );
	$must( TC_Billing_Store::upsert_entitlement( $paid ) ); $must( TC_Billing_Store::upsert_entitlement( $revoked ) );
	$active_sources = array_column( TC_Billing_Store::active( $uid, 'test' ), 'source' );
	$assert( 3 === count( $active_sources ) && in_array( $external_sources['legacy']['source'], $active_sources, true ) && in_array( $external_sources['grant']['source'], $active_sources, true ), 'Revoking a later direct payment preserves both reviewed external coverage sources.' );
	$rebound_external = $external_sources['legacy']; $rebound_external['provider'] = 'grant';
	$assert( is_wp_error( TC_Billing_Store::upsert_entitlement( $rebound_external ) ), 'An imported external source retains its immutable provider identity.' );
	TC_Billing_Store::enqueue_sync( $uid, 'test' ); TC_Billing_Store::due_syncs( 100 );
	TC_Billing_Store::sync_result( (int) $job['id'], 'error', 'tc_billing_discord_legacy_review', 3600 );
	$assert( 'tc_billing_discord_legacy_review' === TC_Billing_Store::sync_state( $uid, 'test' )['last_error'], 'A hardcoded manual-review diagnostic remains explicit while arbitrary errors are redacted.' );
	$assert( 0 === $network_calls, 'All store operations complete without any remote HTTP.' );
	WP_CLI::success( "Billing store integration: $checks assertions; durable transaction/outbox rollback, source isolation, deduplication, retry and CAS lease checks." );
} finally {
	remove_filter( 'pre_http_request', $block_http, 1 );
	foreach ( $events as $id ) { $wpdb->delete( $tables['events'], array( 'id' => $id ) ); }
	foreach ( array_unique( $sources ) as $source ) { $wpdb->delete( $tables['entitlements'], array( 'source' => $source ) ); }
	foreach ( $orders as $id ) { $wpdb->delete( $tables['orders'], array( 'id' => $id ) ); }
	foreach ( $uids as $id ) {
		$wpdb->delete( $tables['sync_jobs'], array( 'user_id' => $id ) ); $wpdb->delete( $tables['owned_roles'], array( 'user_id' => $id ) );
		require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $id );
	}
	$wpdb->delete( $tables['leases'], array( 'lease_key' => hash( 'sha256', $tag . ':lease' ) ) );
}
