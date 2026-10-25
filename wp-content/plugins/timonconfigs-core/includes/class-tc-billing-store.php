<?php
/** Private, source-preserving billing ledger and transactional outbox. */
defined( 'ABSPATH' ) || exit;

final class TC_Billing_Store {
	private const VERSION = '1';
	private const VERSION_OPTION = 'timonconfigs_billing_db_version';
	private const ORDER_FIELDS = array( 'checkout_id', 'customer_id', 'subscription_id', 'payment_id', 'status', 'cancel_at_period_end', 'next_payment_at', 'consent_at', 'consent_version' );
	private const ENTITLEMENT_FIELDS = array( 'status', 'valid_until', 'external_id', 'order_id', 'customer_id', 'payment_id', 'last_invoice_id', 'cancel_at_period_end', 'next_payment_at', 'next_check_at' );
	private const ORDER_STATUSES = array( 'pending', 'checkout', 'completed', 'paid', 'active', 'failed', 'canceled', 'cancelled', 'expired', 'refunded', 'revoked', 'disputed' );
	private const ENTITLEMENT_STATUSES = array( 'active', 'expired', 'revoked', 'disputed', 'pending' );
	private static bool $in_transaction = false;
	private static array $sync_generations = array();
	private static array $held_leases = array();

	private static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'tc_billing_' . $name;
	}

	public static function init(): void {
		if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) { self::install(); }
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$orders = self::table( 'orders' ); $entitlements = self::table( 'entitlements' ); $events = self::table( 'events' );
		$jobs = self::table( 'sync_jobs' ); $roles = self::table( 'owned_roles' ); $leases = self::table( 'leases' );
		$schemas = array(
			"CREATE TABLE $orders (
				id char(36) NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				discord_id varchar(22) NOT NULL,
				provider varchar(24) NOT NULL,
				mode varchar(4) NOT NULL,
				plan varchar(12) NOT NULL,
				amount int(10) unsigned NOT NULL,
				currency char(3) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				checkout_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				customer_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				subscription_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				payment_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				cancel_at_period_end tinyint(1) NOT NULL DEFAULT 0,
				next_payment_at datetime DEFAULT NULL,
				consent_at datetime DEFAULT NULL,
				consent_version varchar(80) DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				next_check_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_mode (user_id,mode),
				KEY checkout_lookup (provider,mode,checkout_id),
				KEY subscription_lookup (provider,mode,subscription_id),
				KEY payment_lookup (provider,mode,payment_id),
				KEY reconcile_due (next_check_at)
			) ENGINE=InnoDB $charset;",
			"CREATE TABLE $entitlements (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				discord_id varchar(22) NOT NULL,
				provider varchar(24) NOT NULL,
				mode varchar(4) NOT NULL,
				plan varchar(12) NOT NULL,
				status varchar(12) NOT NULL DEFAULT 'pending',
				valid_until datetime DEFAULT NULL,
				external_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				order_id char(36) DEFAULT NULL,
				customer_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				payment_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				last_invoice_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
				cancel_at_period_end tinyint(1) NOT NULL DEFAULT 0,
				next_payment_at datetime DEFAULT NULL,
				next_check_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source (source),
				KEY user_mode (user_id,mode),
				KEY order_lookup (order_id)
			) ENGINE=InnoDB $charset;",
			"CREATE TABLE $events (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider varchar(24) NOT NULL,
				mode varchar(4) NOT NULL,
				event_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				type varchar(100) NOT NULL,
				object_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				order_id char(36) DEFAULT NULL,
				provider_created bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(12) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				next_attempt datetime NOT NULL,
				last_error varchar(80) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_event (provider,mode,event_id),
				KEY due (status,next_attempt)
			) ENGINE=InnoDB $charset;",
			"CREATE TABLE $jobs (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				mode varchar(4) NOT NULL,
				status varchar(12) NOT NULL DEFAULT 'pending',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				generation bigint(20) unsigned NOT NULL DEFAULT 1,
				next_attempt datetime NOT NULL,
				last_error varchar(80) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_mode (user_id,mode),
				KEY due (status,next_attempt)
			) ENGINE=InnoDB $charset;",
			"CREATE TABLE $roles (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				mode varchar(4) NOT NULL,
				guild_id varchar(22) NOT NULL,
				role_id varchar(22) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY owned_role (user_id,mode,guild_id,role_id)
			) ENGINE=InnoDB $charset;",
			"CREATE TABLE $leases (
				lease_key char(64) NOT NULL,
				nonce char(36) NOT NULL,
				expires_at datetime NOT NULL,
				PRIMARY KEY  (lease_key)
			) ENGINE=InnoDB $charset;",
		);
		foreach ( $schemas as $schema ) { dbDelta( $schema ); }
		// A transaction is only meaningful if every participating table is InnoDB.
		foreach ( array( $orders, $entitlements, $events, $jobs, $roles, $leases ) as $table ) {
			$engine = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! $engine || 'innodb' !== strtolower( (string) $engine['Engine'] ) ) { return; }
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	private static function now(): string { return gmdate( 'Y-m-d H:i:s' ); }
	private static function after( int $seconds ): string { return gmdate( 'Y-m-d H:i:s', time() + max( 1, min( 604800, $seconds ) ) ); }
	private static function error( string $code ): WP_Error { return new WP_Error( $code, 'Billing ledger could not complete this operation.' ); }
	private static function mode( string $mode ): bool { return in_array( $mode, array( 'test', 'live' ), true ); }
	private static function provider( string $provider ): bool { return in_array( $provider, array( 'stripe', 'paypal' ), true ); }
	private static function entitlement_provider( string $provider ): bool { return in_array( $provider, array( 'stripe', 'paypal', 'operator', 'manual', 'legacy', 'grant' ), true ); }
	private static function identifier( $value, int $max = 191 ): bool { return is_string( $value ) && strlen( $value ) <= $max && 1 === preg_match( '/\A[A-Za-z0-9_.:\/-]+\z/D', $value ); }
	private static function discord( string $value ): bool { return 1 === preg_match( '/\A[0-9]{5,22}\z/D', $value ); }
	private static function uuid( string $value ): bool { return 1 === preg_match( '/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/Di', $value ); }
	private static function date( $value ): bool {
		if ( null === $value ) { return true; }
		if ( ! is_string( $value ) || ! preg_match( '/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/D', $value ) ) { return false; }
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		return $date && $date->format( 'Y-m-d H:i:s' ) === $value;
	}
	private static function safe_code( string $error ): string {
		if ( '' === $error ) { return ''; }
		$known = array( 'billing_busy', 'billing_mode_disabled', 'billing_sync_disabled', 'billing_operation_failed', 'billing_rate_limited', 'discord_rate_limited', 'stripe_rate_limited', 'paypal_rate_limited', 'provider_disabled' );
		foreach ( array( 'disabled', 'configuration', 'binding', 'permissions', 'hierarchy', 'legacy_review', 'external_role', 'signature', 'interaction', 'intent', 'rate_limited', 'network', 'http', 'invalid_response' ) as $suffix ) { $known[] = 'tc_billing_discord_' . $suffix; }
		// An unknown error may itself contain a token, even if it looks like a code.
		return in_array( $error, $known, true ) ? $error : 'billing_error_' . substr( hash( 'sha256', $error ), 0, 16 );
	}
	private static function limit( int $limit ): int { return max( 1, min( 100, $limit ) ); }

	/** No provider response, payload, DB error or exception text is persisted. */
	private static function transaction( callable $callback ) {
		global $wpdb;
		if ( self::$in_transaction ) { return $callback(); }
		if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) { return self::error( 'billing_schema_unavailable' ); }
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::error( 'billing_transaction_failed' ); }
		self::$in_transaction = true;
		try {
			$result = $callback();
			if ( is_wp_error( $result ) || false === $result ) { $wpdb->query( 'ROLLBACK' ); return $result; }
			if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); return self::error( 'billing_commit_failed' ); }
			return $result;
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return self::error( 'billing_transaction_failed' );
		} finally { self::$in_transaction = false; }
	}

	public static function create_order( int $uid, string $discord_id, string $provider, string $mode, string $plan ): array|WP_Error {
		global $wpdb;
		if ( $uid < 1 || ! self::discord( $discord_id ) || ! in_array( $provider, array( 'stripe', 'paypal' ), true ) || ! self::mode( $mode ) || ! in_array( $plan, array( 'monthly', 'lifetime' ), true ) ) { return self::error( 'billing_invalid_order' ); }
		$price = TC_Billing::plan( $plan );
		if ( ! isset( $price['amount'], $price['currency'] ) || ! is_numeric( $price['amount'] ) || (int) $price['amount'] < 1 || 'eur' !== $price['currency'] ) { return self::error( 'billing_invalid_plan' ); }
		$row = array( 'id' => wp_generate_uuid4(), 'user_id' => $uid, 'discord_id' => $discord_id, 'provider' => $provider, 'mode' => $mode, 'plan' => $plan, 'amount' => (int) $price['amount'], 'currency' => $price['currency'], 'status' => 'pending', 'created_at' => self::now(), 'updated_at' => self::now(), 'next_check_at' => self::now() );
		if ( false === $wpdb->insert( self::table( 'orders' ), $row ) ) { return self::error( 'billing_order_write_failed' ); }
		return self::order( $row['id'] ) ?? self::error( 'billing_order_missing' );
	}

	public static function order( string $id ): ?array {
		global $wpdb;
		if ( ! self::uuid( $id ) ) { return null; }
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE id = %s', $id ), ARRAY_A );
	}

	private static function order_fields( array $fields ): array|WP_Error {
		if ( array_diff( array_keys( $fields ), self::ORDER_FIELDS ) ) { return self::error( 'billing_immutable_order' ); }
		foreach ( $fields as $key => $value ) {
			if ( 'status' === $key ) { if ( ! in_array( $value, self::ORDER_STATUSES, true ) ) { return self::error( 'billing_invalid_status' ); } }
			elseif ( 'cancel_at_period_end' === $key ) { if ( ! in_array( $value, array( true, false, 0, 1, '0', '1' ), true ) ) { return self::error( 'billing_invalid_flag' ); } $fields[ $key ] = (int) $value; }
			elseif ( in_array( $key, array( 'consent_at', 'next_payment_at' ), true ) ) { if ( ! self::date( $value ) ) { return self::error( 'billing_invalid_date' ); } }
			elseif ( null !== $value && ! self::identifier( $value, 'consent_version' === $key ? 80 : 191 ) ) { return self::error( 'billing_invalid_identifier' ); }
		}
		return $fields;
	}

	public static function update_order( string $id, array $fields ): bool|WP_Error {
		global $wpdb;
		$fields = self::order_fields( $fields );
		if ( is_wp_error( $fields ) ) { return $fields; }
		if ( ! self::uuid( $id ) || ! self::order( $id ) ) { return self::error( 'billing_order_missing' ); }
		if ( ! $fields ) { return true; }
		$fields['updated_at'] = self::now();
		return false === $wpdb->update( self::table( 'orders' ), $fields, array( 'id' => $id ) ) ? self::error( 'billing_order_write_failed' ) : true;
	}

	public static function orders_for_user( int $uid, string $mode, int $limit = 20 ): array {
		global $wpdb;
		if ( $uid < 1 || ! self::mode( $mode ) ) { return array(); }
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE user_id = %d AND mode = %s ORDER BY created_at DESC, id DESC LIMIT %d', $uid, $mode, self::limit( $limit ) ), ARRAY_A );
	}

	public static function find_order( string $provider, string $mode, string $external_id ): ?array {
		global $wpdb;
		if ( ! self::provider( $provider ) || ! self::mode( $mode ) || ! self::identifier( $external_id ) ) { return null; }
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE provider = %s AND mode = %s AND (checkout_id = %s OR subscription_id = %s OR payment_id = %s) LIMIT 2', $provider, $mode, $external_id, $external_id, $external_id ), ARRAY_A );
		// An ambiguous external ID must never resolve to an arbitrary account.
		return 1 === count( $rows ) ? $rows[0] : null;
	}

	public static function entitlements( int $uid, string $mode ): array {
		global $wpdb;
		if ( $uid < 1 || ! self::mode( $mode ) ) { return array(); }
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'entitlements' ) . ' WHERE user_id = %d AND mode = %s ORDER BY id ASC', $uid, $mode ), ARRAY_A );
	}

	public static function active( int $uid, string $mode ): array {
		global $wpdb;
		if ( $uid < 1 || ! self::mode( $mode ) ) { return array(); }
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'entitlements' ) . " WHERE user_id = %d AND mode = %s AND status = 'active' AND ((plan = 'lifetime' AND valid_until IS NULL) OR valid_until > %s) ORDER BY id ASC", $uid, $mode, self::now() ), ARRAY_A );
	}

	public static function entitlement( string $source ): ?array {
		global $wpdb;
		if ( ! self::identifier( $source ) ) { return null; }
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'entitlements' ) . ' WHERE source = %s', $source ), ARRAY_A );
	}

	public static function upsert_entitlement( array $data ): bool|WP_Error {
		return self::transaction( static function () use ( $data ) { return self::write_entitlement( $data ); } );
	}

	private static function same_identity( array $a, array $b ): bool {
		foreach ( array( 'user_id', 'discord_id', 'provider', 'mode', 'plan' ) as $key ) { if ( ! isset( $a[ $key ], $b[ $key ] ) || (string) $a[ $key ] !== (string) $b[ $key ] ) { return false; } }
		return true;
	}

	private static function write_entitlement( array $data ): bool|WP_Error {
		global $wpdb;
		$identity = array( 'source', 'user_id', 'discord_id', 'provider', 'mode', 'plan' );
		if ( array_diff( array_keys( $data ), array_merge( $identity, self::ENTITLEMENT_FIELDS ) ) || array_diff( $identity, array_keys( $data ) ) ) { return self::error( 'billing_invalid_entitlement' ); }
		if ( ! self::identifier( $data['source'] ) || ( ! is_int( $data['user_id'] ) && ! ( is_string( $data['user_id'] ) && ctype_digit( $data['user_id'] ) ) ) || (int) $data['user_id'] < 1 || ! is_string( $data['discord_id'] ) || ! self::discord( $data['discord_id'] ) || ! is_string( $data['provider'] ) || ! self::entitlement_provider( $data['provider'] ) || ! is_string( $data['mode'] ) || ! self::mode( $data['mode'] ) || ! in_array( $data['plan'], array( 'monthly', 'lifetime' ), true ) ) { return self::error( 'billing_invalid_entitlement' ); }
		foreach ( self::ENTITLEMENT_FIELDS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) { continue; }
			$value = $data[ $key ];
			if ( 'status' === $key ) { if ( ! in_array( $value, self::ENTITLEMENT_STATUSES, true ) ) { return self::error( 'billing_invalid_status' ); } }
			elseif ( 'cancel_at_period_end' === $key ) { if ( ! in_array( $value, array( true, false, 0, 1, '0', '1' ), true ) ) { return self::error( 'billing_invalid_flag' ); } $data[ $key ] = (int) $value; }
			elseif ( in_array( $key, array( 'valid_until', 'next_payment_at', 'next_check_at' ), true ) ) { if ( ! self::date( $value ) ) { return self::error( 'billing_invalid_date' ); } }
			elseif ( null !== $value && ( 'order_id' === $key ? ! is_string( $value ) || ! self::uuid( $value ) : ! self::identifier( $value ) ) ) { return self::error( 'billing_invalid_identifier' ); }
		}
		$table = self::table( 'entitlements' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE source = %s FOR UPDATE", $data['source'] ), ARRAY_A );
		if ( $existing && ! self::same_identity( $existing, $data ) ) { return self::error( 'billing_immutable_entitlement' ); }
		$row = array_merge( $existing ?: array( 'status' => 'pending', 'valid_until' => null ), $data );
		if ( ! empty( $row['order_id'] ) ) {
			$order = self::order( $row['order_id'] );
			if ( ! $order || ! self::same_identity( $row, $order ) || ( $existing && ! empty( $existing['order_id'] ) && $existing['order_id'] !== $row['order_id'] ) ) { return self::error( 'billing_entitlement_order_mismatch' ); }
		}
		// A failed renewal cannot erase the time already paid for. Revocation and
		// disputes remain explicit states and affect this source only.
		if ( $existing && 'active' === $existing['status'] && 'pending' === $row['status'] && ( ( 'lifetime' === $existing['plan'] && null === $existing['valid_until'] ) || $existing['valid_until'] > self::now() ) ) {
			$row['status'] = 'active'; $row['valid_until'] = $existing['valid_until'];
			foreach ( array( 'payment_id', 'last_invoice_id' ) as $key ) { $row[ $key ] = $existing[ $key ]; }
		}
		if ( ( 'lifetime' === $row['plan'] && null !== $row['valid_until'] ) || ( 'monthly' === $row['plan'] && 'active' === $row['status'] && null === $row['valid_until'] ) ) { return self::error( 'billing_invalid_paid_period' ); }
		$row['updated_at'] = self::now();
		if ( $existing ) {
			$fields = array_intersect_key( $row, array_fill_keys( array_merge( self::ENTITLEMENT_FIELDS, array( 'updated_at' ) ), true ) );
			$result = $wpdb->update( $table, $fields, array( 'id' => $existing['id'] ) );
		} else { $row['created_at'] = self::now(); $result = $wpdb->insert( $table, $row ); }
		return false === $result ? self::error( 'billing_entitlement_write_failed' ) : true;
	}

	public static function enqueue_event( string $provider, string $mode, array $descriptor ): array|WP_Error {
		global $wpdb;
		if ( ! in_array( $provider, array( 'stripe', 'paypal' ), true ) || ! self::mode( $mode ) || ! self::identifier( $descriptor['id'] ?? null ) || ! self::identifier( $descriptor['type'] ?? null, 100 ) || ! self::identifier( $descriptor['object_id'] ?? null ) || ! isset( $descriptor['created'] ) || ! is_numeric( $descriptor['created'] ) || (float) $descriptor['created'] < 0 || (float) $descriptor['created'] > PHP_INT_MAX ) { return self::error( 'billing_invalid_event' ); }
		$order_id = $descriptor['order_id'] ?? null;
		if ( null !== $order_id ) {
			$order = is_string( $order_id ) ? self::order( $order_id ) : null;
			if ( ! $order || $order['provider'] !== $provider || $order['mode'] !== $mode ) { return self::error( 'billing_event_order_mismatch' ); }
		}
		$table = self::table( 'events' );
		// Ignore unrecognized descriptor fields; raw webhook bodies have no column.
		$sql = $wpdb->prepare( "INSERT INTO $table (provider,mode,event_id,type,object_id,order_id,provider_created,status,attempts,next_attempt,last_error,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,NULLIF(%s,''),%d,'pending',0,%s,'',%s,%s) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)", $provider, $mode, $descriptor['id'], $descriptor['type'], $descriptor['object_id'], $order_id ?? '', (int) $descriptor['created'], self::now(), self::now(), self::now() );
		if ( false === $wpdb->query( $sql ) ) { return self::error( 'billing_event_write_failed' ); }
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE provider = %s AND mode = %s AND event_id = %s", $provider, $mode, $descriptor['id'] ), ARRAY_A ) ?? self::error( 'billing_event_missing' );
	}

	public static function due_events( int $limit = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'events' ) . " WHERE status IN ('pending','error') AND next_attempt <= %s ORDER BY next_attempt ASC, id ASC LIMIT %d", self::now(), self::limit( $limit ) ), ARRAY_A );
	}
	public static function event( int $id ): ?array {
		global $wpdb;
		return $id > 0 ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'events' ) . ' WHERE id = %d', $id ), ARRAY_A ) : null;
	}
	public static function event_result( int $id, string $status, string $error = '', int $retry_after = 60 ): void {
		global $wpdb;
		if ( $id < 1 || ! in_array( $status, array( 'done', 'error', 'pending' ), true ) ) { return; }
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table( 'events' ) . " SET status = %s, attempts = attempts + 1, next_attempt = %s, last_error = %s, updated_at = %s WHERE id = %d AND status <> 'done'", $status, self::after( $retry_after ), self::safe_code( $error ), self::now(), $id ) );
	}

	public static function apply_event( int $event_id, array $transition, ?string $lease_key = null ): bool|WP_Error {
		global $wpdb;
		return self::transaction( static function () use ( $wpdb, $event_id, $transition, $lease_key ) {
			if ( null !== $lease_key ) {
				$key = hash( 'sha256', $lease_key ); $nonce = self::$held_leases[ $key ] ?? '';
				// Fence a slow provider read: expired/replaced leases may not commit an
				// old financial snapshot. The row lock blocks takeover until commit.
				$valid = $wpdb->get_var( $wpdb->prepare( 'SELECT nonce FROM ' . self::table( 'leases' ) . ' WHERE lease_key=%s AND nonce=%s AND expires_at>UTC_TIMESTAMP() FOR UPDATE', $key, $nonce ) );
				if ( '' === $nonce || $valid !== $nonce ) { return self::error( 'billing_busy' ); }
			}
			$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'events' ) . ' WHERE id = %d FOR UPDATE', $event_id ), ARRAY_A );
			if ( ! $event ) { return self::error( 'billing_event_missing' ); }
			if ( 'done' === $event['status'] ) { return true; }
			$id = $transition['order_id'] ?? '';
			if ( ! is_string( $id ) || ! self::uuid( $id ) || array_diff( array_keys( $transition ), array( 'order_id', 'order_fields', 'entitlement' ) ) ) { return self::error( 'billing_invalid_transition' ); }
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE id = %s FOR UPDATE', $id ), ARRAY_A );
			if ( ! $order || $order['provider'] !== $event['provider'] || $order['mode'] !== $event['mode'] || ( ! empty( $event['order_id'] ) && $id !== $event['order_id'] ) ) { return self::error( 'billing_event_order_mismatch' ); }
			$fields = $transition['order_fields'] ?? array();
			if ( ! is_array( $fields ) ) { return self::error( 'billing_invalid_transition' ); }
			$result = self::update_order( $id, $fields );
			if ( is_wp_error( $result ) ) { return $result; }
			if ( isset( $transition['entitlement'] ) ) {
				$data = $transition['entitlement'];
				if ( ! is_array( $data ) || ! self::same_identity( $data, $order ) || ( isset( $data['order_id'] ) && $data['order_id'] !== $id ) ) { return self::error( 'billing_entitlement_order_mismatch' ); }
				$data['order_id'] = $id;
				$result = self::write_entitlement( $data );
				if ( is_wp_error( $result ) ) { return $result; }
			}
			if ( ! self::write_sync( (int) $order['user_id'], $order['mode'] ) ) { return self::error( 'billing_sync_write_failed' ); }
			$result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table( 'events' ) . " SET status = 'done', attempts = attempts + 1, last_error = '', updated_at = %s WHERE id = %d", self::now(), $event_id ) );
			return false === $result ? self::error( 'billing_event_write_failed' ) : true;
		} );
	}

	private static function write_sync( int $uid, string $mode ): bool {
		global $wpdb;
		if ( $uid < 1 || ! self::mode( $mode ) ) { return false; }
		$table = self::table( 'sync_jobs' );
		return false !== $wpdb->query( $wpdb->prepare( "INSERT INTO $table (user_id,mode,status,attempts,generation,next_attempt,last_error,created_at,updated_at) VALUES (%d,%s,'pending',0,1,%s,'',%s,%s) ON DUPLICATE KEY UPDATE status = 'pending', generation = generation + 1, next_attempt = VALUES(next_attempt), last_error = '', updated_at = VALUES(updated_at)", $uid, $mode, self::now(), self::now(), self::now() ) );
	}
	public static function enqueue_sync( int $uid, string $mode ): void { self::write_sync( $uid, $mode ); }
	public static function due_syncs( int $limit = 10 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'sync_jobs' ) . " WHERE status IN ('pending','error') AND next_attempt <= %s ORDER BY next_attempt ASC, id ASC LIMIT %d", self::now(), self::limit( $limit ) ), ARRAY_A );
		foreach ( $rows as $row ) { self::$sync_generations[ (int) $row['id'] ] = (int) $row['generation']; }
		return $rows;
	}
	public static function sync_result( int $job_id, string $status, string $error = '', int $retry_after = 60 ): void {
		global $wpdb;
		if ( $job_id < 1 || ! in_array( $status, array( 'done', 'error', 'pending' ), true ) ) { return; }
		$table = self::table( 'sync_jobs' );
		$generation = self::$sync_generations[ $job_id ] ?? (int) $wpdb->get_var( $wpdb->prepare( "SELECT generation FROM $table WHERE id = %d", $job_id ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = %s, attempts = attempts + 1, next_attempt = %s, last_error = %s, updated_at = %s WHERE id = %d AND generation = %d", $status, self::after( $retry_after ), self::safe_code( $error ), self::now(), $job_id, $generation ) );
		unset( self::$sync_generations[ $job_id ] );
	}
	public static function sync_state( int $uid, string $mode ): array {
		global $wpdb;
		if ( $uid < 1 || ! self::mode( $mode ) ) { return array(); }
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'sync_jobs' ) . ' WHERE user_id = %d AND mode = %s', $uid, $mode ), ARRAY_A ) ?: array();
	}

	public static function owned_roles( int $uid, string $mode ): array {
		global $wpdb;
		if ( $uid < 1 || ! self::mode( $mode ) ) { return array(); }
		return $wpdb->get_results( $wpdb->prepare( 'SELECT guild_id,role_id FROM ' . self::table( 'owned_roles' ) . ' WHERE user_id = %d AND mode = %s ORDER BY id ASC', $uid, $mode ), ARRAY_A );
	}
	public static function own_role( int $uid, string $mode, string $guild, string $role, bool $owned ): void {
		global $wpdb;
		if ( $uid < 1 || ! self::mode( $mode ) || ! self::discord( $guild ) || ! self::discord( $role ) ) { return; }
		$table = self::table( 'owned_roles' );
		if ( $owned ) { $wpdb->query( $wpdb->prepare( "INSERT INTO $table (user_id,mode,guild_id,role_id,created_at) VALUES (%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE id = id", $uid, $mode, $guild, $role, self::now() ) ); }
		else { $wpdb->delete( $table, array( 'user_id' => $uid, 'mode' => $mode, 'guild_id' => $guild, 'role_id' => $role ) ); }
	}

	public static function due_reconcile( int $limit = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE next_check_at <= %s AND (checkout_id IS NOT NULL OR subscription_id IS NOT NULL OR payment_id IS NOT NULL) ORDER BY next_check_at ASC, id ASC LIMIT %d', self::now(), self::limit( $limit ) ), ARRAY_A );
	}
	public static function mark_reconciled( string $order_id, int $retry_after = 3600 ): void {
		global $wpdb;
		if ( self::uuid( $order_id ) ) { $wpdb->update( self::table( 'orders' ), array( 'next_check_at' => self::after( $retry_after ) ), array( 'id' => $order_id ) ); }
	}
	public static function diagnostics( int $limit = 20 ): array {
		global $wpdb;
		$limit = self::limit( $limit );
		return array(
			'events' => $wpdb->get_results( $wpdb->prepare( 'SELECT id,provider,mode,event_id,type,status,attempts,next_attempt,last_error,updated_at FROM ' . self::table( 'events' ) . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ),
			'jobs' => $wpdb->get_results( $wpdb->prepare( 'SELECT id,user_id,mode,status,attempts,next_attempt,last_error,updated_at FROM ' . self::table( 'sync_jobs' ) . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ),
		);
	}

	/** Atomic acquisition/takeover and compare-and-delete release of our nonce. */
	public static function locked( string $key, callable $callback ): mixed {
		global $wpdb;
		if ( '' === $key || strlen( $key ) > 512 ) { return self::error( 'billing_invalid_lock' ); }
		$table = self::table( 'leases' ); $key = hash( 'sha256', $key ); $nonce = wp_generate_uuid4();
		$result = $wpdb->query( $wpdb->prepare( "INSERT INTO $table (lease_key,nonce,expires_at) VALUES (%s,%s,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 180 SECOND)) ON DUPLICATE KEY UPDATE nonce = IF(expires_at <= UTC_TIMESTAMP(), VALUES(nonce), nonce), expires_at = IF(expires_at <= UTC_TIMESTAMP(), VALUES(expires_at), expires_at)", $key, $nonce ) );
		if ( false === $result ) { return self::error( 'billing_lock_failed' ); }
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT nonce FROM $table WHERE lease_key = %s", $key ) );
		if ( ! is_string( $owner ) || ! hash_equals( $nonce, $owner ) ) { return self::error( 'billing_busy' ); }
		$previous = self::$held_leases[ $key ] ?? null; self::$held_leases[ $key ] = $nonce;
		try { return $callback(); }
		finally { $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE lease_key = %s AND nonce = %s", $key, $nonce ) ); if ( null === $previous ) { unset( self::$held_leases[ $key ] ); } else { self::$held_leases[ $key ] = $previous; } }
	}
}
