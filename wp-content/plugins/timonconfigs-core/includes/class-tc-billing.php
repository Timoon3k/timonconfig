<?php
/** Direct billing orchestration. Disabled by default; provider confirmations are the only payment authority. */
defined( 'ABSPATH' ) || exit;

final class TC_Billing {
	public static function init(): void {
		TC_Billing_Store::init();
		add_filter( 'cron_schedules', static function ( array $schedules ): array { $schedules['tc_billing_five_minutes'] = array( 'interval' => 300, 'display' => 'TimonConfigs billing queue' ); return $schedules; } );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'tc_billing_tick', array( __CLASS__, 'tick' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_tc_billing_action', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_tc_billing_action', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_tc_billing_admin', array( __CLASS__, 'handle_admin' ) );
		add_action( 'admin_menu', static function (): void { add_options_page( 'TimonConfigs billing', 'TimonConfigs billing', 'manage_options', 'tc-billing', array( 'TC_Billing_UI', 'admin' ) ); } );
		add_action( 'admin_init', static function (): void { register_setting( 'tc_billing', 'timonconfigs_billing', array( 'type' => 'array', 'show_in_rest' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) ); } );
	}

	public static function config(): array {
		$value = get_option( 'timonconfigs_billing', array() );
		return array_merge( array( 'mode' => 'test', 'routing' => 'legacy', 'stripe_test_confirmed' => false, 'migration_confirmed' => false, 'legal_confirmed' => false, 'paypal_enabled' => false, 'role_sync_enabled' => false ), is_array( $value ) ? $value : array() );
	}
	public static function setting( string $key, string $mode ): string { $value = self::config()[ $mode . '_' . $key ] ?? ''; return in_array( $mode, array( 'test', 'live' ), true ) && is_string( $value ) ? $value : ''; }
	public static function secret( string $name, string $mode ): string {
		if ( ! in_array( $mode, array( 'test', 'live' ), true ) || ! in_array( $name, array( 'STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET', 'PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'DISCORD_BOT_TOKEN' ), true ) ) { return ''; }
		$key = 'TIMONCONFIGS_BILLING_' . strtoupper( $mode ) . '_' . $name;
		return defined( $key ) && is_string( constant( $key ) ) ? trim( constant( $key ) ) : '';
	}
	public static function plan( string $plan ): array {
		return match ( $plan ) {
			'free' => array( 'plan' => 'free', 'name' => 'Free Member', 'amount' => 0, 'currency' => 'eur', 'interval' => 'none' ),
			'monthly' => array( 'plan' => 'monthly', 'name' => 'VIP Monthly', 'amount' => 2999, 'currency' => 'eur', 'interval' => 'month' ),
			'lifetime' => array( 'plan' => 'lifetime', 'name' => 'VIP Lifetime', 'amount' => 8000, 'currency' => 'eur', 'interval' => 'one_time' ),
			default => array(),
		};
	}
	public static function display_price( string $plan ): string {
		$product = self::plan( $plan );
		if ( ! $product ) { return ''; }
		// Existing provider routing keeps its published offer until the approved cutover.
		if ( ! self::direct_enabled() && 'free' !== $plan ) { return 'monthly' === $plan ? '€40' : '€100'; }
		return '€' . ( $product['amount'] % 100 ? number_format( $product['amount'] / 100, 2, '.', '' ) : (string) (int) ( $product['amount'] / 100 ) );
	}
	public static function mode_allowed( string $mode ): bool {
		if ( 'test' === $mode ) {
			return in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true ) || in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true );
		}
		$config = self::config();
		return 'live' === $mode && defined( 'TIMONCONFIGS_BILLING_LIVE_APPROVED' ) && true === TIMONCONFIGS_BILLING_LIVE_APPROVED && ! empty( $config['stripe_test_confirmed'] ) && ! empty( $config['migration_confirmed'] ) && ! empty( $config['legal_confirmed'] ) && 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
	}
	public static function direct_enabled(): bool { $config = self::config(); return 'direct' === $config['routing'] && self::mode_allowed( $config['mode'] ); }
	public static function error( string $code, string $message ): WP_Error { return new WP_Error( $code, $message ); }
	public static function adapter( string $provider ): string|WP_Error { return match ( $provider ) { 'stripe' => TC_Billing_Stripe::class, 'paypal' => TC_Billing_PayPal::class, default => self::error( 'tc_billing_provider', 'Choose a supported payment provider.' ) }; }
	public static function provider_ready( string $provider, string $mode ): bool {
		if ( ! self::mode_allowed( $mode ) ) { return false; }
		$config = self::config();
		if ( 'live' === $mode && ( empty( $config['role_sync_enabled'] ) || '' === self::secret( 'DISCORD_BOT_TOKEN', $mode ) || '' === self::setting( 'discord_guild_id', $mode ) || '' === self::setting( 'discord_monthly_role_id', $mode ) || '' === self::setting( 'discord_lifetime_role_id', $mode ) ) ) { return false; }
		if ( 'stripe' === $provider ) { return '' !== self::secret( 'STRIPE_SECRET_KEY', $mode ) && '' !== self::secret( 'STRIPE_WEBHOOK_SECRET', $mode ); }
		return 'paypal' === $provider && ! empty( $config['paypal_enabled'] ) && ! empty( $config['stripe_test_confirmed'] ) && '' !== self::secret( 'PAYPAL_CLIENT_ID', $mode ) && '' !== self::secret( 'PAYPAL_CLIENT_SECRET', $mode ) && '' !== self::setting( 'paypal_webhook_id', $mode );
	}
	public static function public_keys(): array { return array( 'stripe_monthly_price_id', 'stripe_monthly_product_id', 'stripe_lifetime_price_id', 'stripe_lifetime_product_id', 'paypal_monthly_plan_id', 'paypal_monthly_product_id', 'paypal_webhook_id', 'discord_guild_id', 'discord_monthly_role_id', 'discord_lifetime_role_id' ); }
	public static function sanitize( mixed $input ): array {
		$old = self::config();
		if ( ! current_user_can( 'manage_options' ) || ! is_array( $input ) ) { return $old; }
		$result = array( 'mode' => in_array( $input['mode'] ?? '', array( 'test', 'live' ), true ) ? $input['mode'] : 'test', 'routing' => 'direct' === ( $input['routing'] ?? '' ) ? 'direct' : 'legacy' );
		foreach ( array( 'stripe_test_confirmed', 'migration_confirmed', 'legal_confirmed', 'paypal_enabled', 'role_sync_enabled' ) as $flag ) { $result[ $flag ] = in_array( $input[ $flag ] ?? null, array( true, 1, '1' ), true ); }
		foreach ( array( 'test', 'live' ) as $mode ) {
			foreach ( self::public_keys() as $key ) {
				$value = $input[ $mode . '_' . $key ] ?? '';
				$result[ $mode . '_' . $key ] = is_string( $value ) && preg_match( '/^[A-Za-z0-9_-]{0,180}$/D', $value ) ? $value : '';
			}
		}
		if ( 'live' === $result['mode'] && ( ! defined( 'TIMONCONFIGS_BILLING_LIVE_APPROVED' ) || true !== TIMONCONFIGS_BILLING_LIVE_APPROVED || ! $result['stripe_test_confirmed'] || ! $result['migration_confirmed'] || ! $result['legal_confirmed'] ) ) {
			$result['mode'] = 'test'; $result['routing'] = 'legacy';
			add_settings_error( 'tc_billing', 'live_disabled', 'Live remains locked until verified test results, approved migration and server-side approval are configured.' );
		}
		return $result;
	}
	/** OAuth identity, never an email match or browser-supplied user ID. */
	public static function identity(): array|WP_Error {
		$uid = get_current_user_id(); $id = get_user_meta( $uid, '_tc_discord_id', true );
		if ( ! $uid || ! is_user_logged_in() || ! get_userdata( $uid ) || ! is_string( $id ) || ! preg_match( '/^[0-9]{17,20}$/D', $id ) || (int) get_option( '_tc_discord_binding_' . $id ) !== $uid || '' === (string) get_user_meta( $uid, '_tc_discord_token', true ) || ! TC_Discord::is_ready() ) { return self::error( 'tc_billing_identity', 'Continue with Discord before managing billing.' ); }
		return array( 'user_id' => $uid, 'discord_id' => $id );
	}
	private static function scalar( array $data, string $key ): string { return is_string( $data[ $key ] ?? null ) ? $data[ $key ] : ''; }
	public static function trusted_url( string $url, string $provider ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$hosts = 'stripe' === $provider ? array( 'checkout.stripe.com', 'billing.stripe.com' ) : array( 'www.paypal.com', 'www.sandbox.paypal.com', 'paypal.com', 'sandbox.paypal.com' );
		return 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && in_array( $host, $hosts, true ) && in_array( wp_parse_url( $url, PHP_URL_PORT ), array( null, 443 ), true ) && null === wp_parse_url( $url, PHP_URL_USER ) && null === wp_parse_url( $url, PHP_URL_PASS );
	}
	/** Testable POST service; web entry point below always requires POST. */
	public static function perform( array $data ): array|WP_Error {
		if ( ! is_user_logged_in() || ! wp_verify_nonce( self::scalar( $data, '_wpnonce' ), 'tc_billing_action' ) ) { return self::error( 'tc_billing_nonce', 'Your billing session expired. Reload and try again.' ); }
		$identity = self::identity(); if ( is_wp_error( $identity ) ) { return $identity; }
		$config = self::config(); $mode = $config['mode'];
		if ( ! self::mode_allowed( $mode ) ) { return self::error( 'tc_billing_disabled', 'Direct billing is not enabled in this environment.' ); }
		$operation = self::scalar( $data, 'operation' );
		if ( 'checkout' === $operation ) {
			if ( ! self::direct_enabled() ) { return self::error( 'tc_billing_disabled', 'New direct purchases have not been enabled.' ); }
			$provider = self::scalar( $data, 'provider' ); $plan = self::scalar( $data, 'plan' );
			if ( ! in_array( $plan, array( 'monthly', 'lifetime' ), true ) || ! self::provider_ready( $provider, $mode ) ) { return self::error( 'tc_billing_unconfigured', 'This payment option is not available yet.' ); }
			if ( '1' !== self::scalar( $data, 'consent' ) ) { return self::error( 'tc_billing_consent', 'Review and accept the price, license and renewal terms before continuing.' ); }
			$status = TC_Discord::status( $identity['user_id'] );
			if ( ! in_array( $status['state'] ?? '', array( 'free', 'monthly', 'lifetime' ), true ) || ( $status['discord_id'] ?? '' ) !== $identity['discord_id'] ) { return self::error( 'tc_billing_verification', 'Complete Free Member verification in Discord before purchasing.' ); }
			$intent = self::scalar( $data, 'intent' );
			if ( '' !== $intent ) {
				$verified = TC_Billing_Discord::consume_intent( $intent, $identity['discord_id'] );
				if ( is_wp_error( $verified ) ) { return $verified; }
				if ( ( $verified['plan'] ?? '' ) !== $plan || ( $verified['mode'] ?? '' ) !== $mode ) { return self::error( 'tc_billing_intent', 'This purchase link does not match the selected membership.' ); }
			}
			return TC_Billing_Store::locked( 'checkout:' . $mode . ':' . $identity['user_id'], static function () use ( $identity, $plan, $provider, $mode, $status ): array|WP_Error {
				if ( 'monthly' === $plan ) {
					$recurring = TC_Billing_Store::unresolved_recurring( $identity['user_id'], $mode );
					if ( is_wp_error( $recurring ) ) { return $recurring; }
					$resumable = 1 === count( $recurring ) && $recurring[0]['provider'] === $provider && in_array( $recurring[0]['status'], array( 'pending', 'checkout' ), true ) && strtotime( $recurring[0]['created_at'] . ' UTC' ) > time() - DAY_IN_SECONDS;
					if ( $recurring && ! $resumable ) { return self::error( 'tc_billing_existing_subscription', 'An existing subscription may still renew or retry payment. Manage it and confirm it has stopped before starting another.' ); }
				}
				$active = TC_Billing_Store::active( $identity['user_id'], $mode ); if ( is_wp_error( $active ) ) { return $active; }
				foreach ( $active as $entitlement ) {
					if ( 'monthly' === $plan || 'lifetime' === $entitlement['plan'] ) { return self::error( 'tc_billing_duplicate', 'You already have covered access. Manage your existing subscription before starting another.' ); }
				}
				$covered_role = 'lifetime' === $status['state'] || ( 'monthly' === $plan && 'monthly' === $status['state'] );
				if ( $covered_role && '1' !== get_user_meta( $identity['user_id'], '_tc_billing_legacy_reviewed_' . $mode, true ) ) {
					return self::error( 'tc_billing_legacy_review', 'Your Discord account already has paid access. Contact support to review the existing purchase before starting another. Monthly members may separately upgrade to Lifetime.' );
				}
				$attempts = TC_Billing_Store::checkout_attempts( $identity['user_id'], $mode, $plan );
				if ( is_wp_error( $attempts ) ) { return $attempts; }
				if ( count( $attempts ) > 1 ) { return self::error( 'tc_billing_pending', 'Multiple unfinished purchases need support review before a new payment.' ); }
				$order = $attempts[0] ?? null;
				if ( $order ) {
					if ( $order['provider'] !== $provider ) { return self::error( 'tc_billing_pending', 'An unfinished checkout already exists with another provider. Contact support before creating another payment.' ); }
					if ( empty( $order['checkout_id'] ) && empty( $order['subscription_id'] ) && strtotime( $order['created_at'] . ' UTC' ) <= time() - 600 ) { return self::error( 'tc_billing_pending', 'An earlier checkout has an uncertain provider response. Contact support to reconcile it before retrying payment.' ); }
				}
				if ( null === $order ) { $order = TC_Billing_Store::create_order( $identity['user_id'], $identity['discord_id'], $provider, $mode, $plan ); }
				if ( is_wp_error( $order ) ) { return $order; }
				$consent = TC_Billing_Store::update_order( $order['id'], array( 'consent_at' => gmdate( 'Y-m-d H:i:s' ), 'consent_version' => '2026-10-25-v1' ) );
				if ( is_wp_error( $consent ) ) { return $consent; }
				$adapter = self::adapter( $provider ); $checkout = $adapter::create_checkout( $order );
				if ( is_wp_error( $checkout ) ) { return $checkout; }
				if ( ! is_string( $checkout['id'] ?? null ) || ! is_string( $checkout['url'] ?? null ) || ! self::trusted_url( $checkout['url'], $provider ) ) { return self::error( 'tc_billing_url', 'The provider did not return a valid hosted checkout.' ); }
				$fields = array_intersect_key( $checkout, array_flip( array( 'customer_id', 'subscription_id', 'payment_id' ) ) ); $fields['checkout_id'] = $checkout['id']; $fields['status'] = 'checkout';
				$saved = TC_Billing_Store::update_order( $order['id'], $fields );
				return is_wp_error( $saved ) ? $saved : array( 'redirect' => $checkout['url'], 'order_id' => $order['id'] );
			} );
		}
		$order = TC_Billing_Store::order( self::scalar( $data, 'order_id' ) );
		if ( ! $order || (int) $order['user_id'] !== $identity['user_id'] || $order['discord_id'] !== $identity['discord_id'] || $order['mode'] !== $mode ) { return self::error( 'tc_billing_order', 'This order is not available to your account.' ); }
		if ( ! self::provider_ready( $order['provider'], $mode ) ) { return self::error( 'tc_billing_unconfigured', 'This provider is not available yet.' ); }
		$adapter = self::adapter( $order['provider'] );
		return TC_Billing_Store::locked( 'manage:' . $order['id'], static function () use ( $data, $operation, $order, $adapter ): array|WP_Error {
			if ( 'portal' === $operation ) {
				$url = $adapter::portal( $order );
				return is_wp_error( $url ) ? $url : ( self::trusted_url( $url, $order['provider'] ) ? array( 'redirect' => $url ) : self::error( 'tc_billing_url', 'The provider did not return a valid management link.' ) );
			}
			if ( 'cancel' === $operation ) {
				if ( 'monthly' !== $order['plan'] || '1' !== self::scalar( $data, 'confirm_cancel' ) ) { return self::error( 'tc_billing_consent', 'Confirm that you want to stop future renewals.' ); }
				$result = $adapter::cancel( $order );
				if ( is_wp_error( $result ) ) { return $result; }
				self::queue_reconcile( $order );
				return array( 'message' => 'Cancellation requested. Your verified prepaid access remains until its paid end date.' );
			}
			if ( 'capture' === $operation && 'paypal' === $order['provider'] && 'lifetime' === $order['plan'] ) {
				$result = $adapter::capture( $order );
				if ( is_wp_error( $result ) ) { return $result; }
				self::queue_reconcile( $order );
				return array( 'message' => 'Payment submitted to PayPal. Access updates after verified payment and Discord synchronization.' );
			}
			if ( 'refresh' === $operation ) { self::queue_reconcile( $order ); return array( 'message' => 'A provider verification has been queued.' ); }
			return self::error( 'tc_billing_action', 'Unknown billing action.' );
		} );
	}
	public static function handle(): void {
		self::headers();
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'POST required.', '', array( 'response' => 405 ) ); }
		$result = self::perform( wp_unslash( $_POST ) );
		if ( is_wp_error( $result ) ) {
			if ( ! is_user_logged_in() ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) ); }
			set_transient( 'tc_billing_feedback_' . get_current_user_id(), $result->get_error_message(), 120 );
			wp_safe_redirect( home_url( '/account/' ), 303 ); exit;
		}
		if ( isset( $result['redirect'] ) ) { wp_redirect( $result['redirect'], 303, 'TimonConfigs billing' ); exit; } // Provider URLs were strictly validated by perform().
		set_transient( 'tc_billing_feedback_' . get_current_user_id(), (string) ( $result['message'] ?? 'Billing request accepted.' ), 120 );
		wp_safe_redirect( home_url( '/account/' ), 303 ); exit;
	}
	public static function admin_perform( array $data ): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( self::scalar( $data, '_wpnonce' ), 'tc_billing_admin' ) ) { return self::error( 'tc_billing_forbidden', 'Administrator authorization is required.' ); }
		$operation = self::scalar( $data, 'operation' ); $uid = absint( $data['user_id'] ?? 0 ); $mode = self::scalar( $data, 'mode' );
		if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) { return self::error( 'tc_billing_mode', 'Choose Test or Live.' ); }
		if ( 'retry_event' === $operation ) {
			$event = TC_Billing_Store::event( absint( $data['id'] ?? 0 ) );
			if ( ! $event || $event['mode'] !== $mode || 'done' === $event['status'] ) { return self::error( 'tc_billing_event', 'Choose an unprocessed event.' ); }
			TC_Billing_Store::event_result( (int) $event['id'], 'pending', '', 1 ); return true;
		}
		if ( ! $uid || ! get_userdata( $uid ) ) { return self::error( 'tc_billing_user', 'Choose an existing member account.' ); }
		$id = get_user_meta( $uid, '_tc_discord_id', true );
		if ( ! is_string( $id ) || ! preg_match( '/^[0-9]{17,20}$/D', $id ) || (int) get_option( '_tc_discord_binding_' . $id ) !== $uid ) { return self::error( 'tc_billing_identity', 'This account has no verified Discord binding.' ); }
		if ( 'retry_sync' === $operation ) { TC_Billing_Store::enqueue_sync( $uid, $mode ); self::wake(); return true; }
		if ( '1' !== self::scalar( $data, 'confirm' ) ) { return self::error( 'tc_billing_confirm', 'Confirm that you verified the original entitlement inventory.' ); }
		if ( 'review_legacy' === $operation ) {
			update_user_meta( $uid, '_tc_billing_legacy_reviewed_' . $mode, '1' );
			add_user_meta( $uid, '_tc_billing_review_audit', array( 'mode' => $mode, 'by' => get_current_user_id(), 'at' => gmdate( 'Y-m-d H:i:s' ) ) );
			TC_Billing_Store::enqueue_sync( $uid, $mode ); self::wake(); return true;
		}
		if ( 'external_grant' === $operation ) {
			$plan = self::scalar( $data, 'plan' ); $provider = self::scalar( $data, 'source_provider' );
			if ( ! in_array( $plan, array( 'monthly', 'lifetime' ), true ) || ! in_array( $provider, array( 'legacy', 'grant' ), true ) ) { return self::error( 'tc_billing_grant', 'Choose a verified legacy purchase or approved grant.' ); }
			$until = self::scalar( $data, 'valid_until' );
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $until, new DateTimeZone( 'UTC' ) );
			if ( 'monthly' === $plan && ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $until || $date->getTimestamp() <= time() ) ) { return self::error( 'tc_billing_date', 'Enter the actual future paid end date in UTC.' ); }
			$source = $provider . ':' . $mode . ':' . wp_generate_uuid4();
			$saved = TC_Billing_Store::upsert_entitlement( array( 'source' => $source, 'user_id' => $uid, 'discord_id' => $id, 'provider' => $provider, 'mode' => $mode, 'plan' => $plan, 'status' => 'active', 'valid_until' => 'monthly' === $plan ? $until : null, 'external_id' => $source ) );
			if ( is_wp_error( $saved ) ) { return $saved; }
			add_user_meta( $uid, '_tc_billing_external_audit', array( 'source' => $source, 'by' => get_current_user_id(), 'at' => gmdate( 'Y-m-d H:i:s' ) ) ); TC_Billing_Store::enqueue_sync( $uid, $mode ); self::wake(); return true;
		}
		return self::error( 'tc_billing_action', 'Unknown administrator action.' );
	}
	public static function handle_admin(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'POST required.', '', array( 'response' => 405 ) ); }
		$result = self::admin_perform( wp_unslash( $_POST ) );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator authorization required.', '', array( 'response' => 403 ) ); }
		set_transient( 'tc_billing_admin_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : 'Billing operation recorded. Queue processing remains subject to the configured mode and role permissions.', 120 );
		wp_safe_redirect( admin_url( 'options-general.php?page=tc-billing' ), 303 ); exit;
	}
	public static function headers(): void { if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); } nocache_headers(); if ( ! headers_sent() ) { header( 'Cache-Control: private, no-store, max-age=0', true ); header( 'X-Robots-Tag: noindex, nofollow', true ); header( 'Referrer-Policy: no-referrer', true ); } }
	public static function render_checkout( string $plan ): void { global $wp_query; self::headers(); if ( $wp_query instanceof WP_Query ) { $wp_query->is_404 = false; } status_header( 200 ); get_header(); echo '<main id="main" class="tc-shell tc-entry">' . TC_Billing_UI::checkout( $plan ) . '</main>'; get_footer(); exit; }
	public static function render_account( int $uid ): string { return TC_Billing_UI::account( $uid ); }
	public static function wake(): void { if ( ! wp_next_scheduled( 'tc_billing_tick' ) ) { wp_schedule_single_event( time() + 1, 'tc_billing_tick' ); } }
	public static function schedule(): void { $mode = self::config()['mode']; if ( self::mode_allowed( $mode ) && ( self::direct_enabled() || TC_Billing_Store::maintenance_needed( $mode ) ) && ! wp_next_scheduled( 'tc_billing_tick' ) ) { wp_schedule_event( time() + 60, 'tc_billing_five_minutes', 'tc_billing_tick' ); } }
	public static function queue_reconcile( array $order ): void {
		TC_Billing_Store::enqueue_event( $order['provider'], $order['mode'], array( 'id' => 'reconcile:' . $order['id'] . ':' . (string) (int) ( time() / 60 ), 'type' => 'tc.reconcile', 'object_id' => $order['subscription_id'] ?: ( $order['payment_id'] ?: $order['checkout_id'] ), 'order_id' => $order['id'], 'created' => time() ) );
		if ( ! wp_next_scheduled( 'tc_billing_tick' ) ) { wp_schedule_single_event( time() + 1, 'tc_billing_tick' ); }
	}
	public static function process_event( array $event ): mixed {
		// Canonical reads and their application share a provider/mode lease: two
		// different notifications cannot apply stale snapshots in reverse order.
		return TC_Billing_Store::locked( 'provider:' . $event['provider'] . ':' . $event['mode'], static function () use ( $event ): bool|WP_Error {
			$current = TC_Billing_Store::event( (int) $event['id'] ); if ( ! $current || 'done' === $current['status'] ) { return true; }
			if ( self::config()['mode'] !== $event['mode'] || ! self::provider_ready( $event['provider'], $event['mode'] ) ) { TC_Billing_Store::event_result( (int) $event['id'], 'error', 'provider_disabled', 3600 ); return false; }
			$adapter = self::adapter( $event['provider'] ); $result = $adapter::reconcile( $event );
			if ( is_wp_error( $result ) ) {
				if ( 'stripe_event_ignored' === $result->get_error_code() ) { TC_Billing_Store::event_result( (int) $event['id'], 'done' ); return true; }
				TC_Billing_Store::event_result( (int) $event['id'], 'error', sanitize_key( $result->get_error_code() ), self::retry( $event, $result ) ); return $result;
			}
			if ( ! $result ) { TC_Billing_Store::event_result( (int) $event['id'], 'done' ); return true; }
			$applied = TC_Billing_Store::apply_event( (int) $event['id'], $result, 'provider:' . $event['provider'] . ':' . $event['mode'] );
			if ( is_wp_error( $applied ) ) { TC_Billing_Store::event_result( (int) $event['id'], 'error', sanitize_key( $applied->get_error_code() ), 300 ); }
			return $applied;
		} );
	}
	private static function retry( array $job, WP_Error $error ): int { $data = $error->get_error_data(); return max( 10, min( DAY_IN_SECONDS, max( (int) ( is_array( $data ) ? ( $data['retry_after'] ?? 0 ) : 0 ), 60 * ( 2 ** min( 6, (int) ( $job['attempts'] ?? 0 ) ) ) ) ) ); }
	public static function tick(): void {
		TC_Billing_Store::locked( 'tick', static function (): bool {
			update_option( 'timonconfigs_billing_last_tick', gmdate( 'Y-m-d H:i:s' ) . ' UTC', false );
			$mode = self::config()['mode'];
			if ( self::mode_allowed( $mode ) ) {
				$expired = TC_Billing_Store::expire_entitlements( $mode );
				update_option( 'timonconfigs_billing_worker_error', is_wp_error( $expired ) ? sanitize_key( $expired->get_error_code() ) : '', false );
			}
			foreach ( TC_Billing_Store::due_reconcile( 5, $mode ) as $order ) {
				if ( $order['mode'] !== self::config()['mode'] || ! self::mode_allowed( $order['mode'] ) ) { continue; }
				self::queue_reconcile( $order ); TC_Billing_Store::enqueue_sync( (int) $order['user_id'], $order['mode'] );
				$interval = 3600;
				$sources = TC_Billing_Store::entitlements( (int) $order['user_id'], $order['mode'] );
				if ( is_wp_error( $sources ) ) { continue; }
				foreach ( $sources as $source ) {
					if ( ! empty( $source['valid_until'] ) && 'active' === $source['status'] ) { $interval = min( $interval, max( 60, strtotime( $source['valid_until'] . ' UTC' ) - time() ) ); }
				}
				TC_Billing_Store::mark_reconciled( $order['id'], $interval );
			}
			foreach ( TC_Billing_Store::due_events( 5, $mode ) as $event ) { self::process_event( $event ); }
			foreach ( TC_Billing_Store::due_syncs( 5, $mode ) as $job ) {
				$result = TC_Billing_Discord::sync( $job );
				TC_Billing_Store::sync_result( (int) $job['id'], true === $result ? 'done' : 'error', is_wp_error( $result ) ? sanitize_key( $result->get_error_code() ) : '', is_wp_error( $result ) ? self::retry( $job, $result ) : 60 );
			}
			return true;
		} );
	}
	public static function routes(): void {
		register_rest_route( 'timonconfigs/v1', '/billing/(?P<provider>stripe|paypal)/(?P<mode>test|live)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'webhook' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'timonconfigs/v1', '/billing/discord/interactions', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'interaction' ), 'permission_callback' => '__return_true' ) );
	}
	public static function webhook( WP_REST_Request $request ): WP_REST_Response {
		self::headers();
		$provider = (string) $request['provider']; $mode = (string) $request['mode'];
		if ( ! self::provider_ready( $provider, $mode ) ) { return new WP_REST_Response( array( 'code' => 'provider_disabled' ), 503 ); }
		$raw = $request->get_body(); if ( '' === $raw || strlen( $raw ) > 262144 ) { return new WP_REST_Response( array( 'code' => 'invalid_notification' ), 400 ); }
		$headers = array(); foreach ( $request->get_headers() as $key => $value ) { $headers[ str_replace( '_', '-', strtolower( $key ) ) ] = is_array( $value ) ? implode( ',', $value ) : $value; }
		$adapter = self::adapter( $provider ); $event = $adapter::verify_webhook( $raw, $headers, $mode );
		if ( is_wp_error( $event ) ) { return new WP_REST_Response( array( 'code' => 'invalid_notification' ), 400 ); }
		$saved = TC_Billing_Store::enqueue_event( $provider, $mode, $event );
		if ( is_wp_error( $saved ) ) { return new WP_REST_Response( array( 'code' => 'queue_unavailable' ), 503 ); }
		if ( ! wp_next_scheduled( 'tc_billing_tick' ) ) { wp_schedule_single_event( time() + 1, 'tc_billing_tick' ); }
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}
	public static function interaction( WP_REST_Request $request ): WP_REST_Response {
		self::headers();
		if ( strlen( $request->get_body() ) > 65536 ) { return new WP_REST_Response( array( 'code' => 'invalid_interaction' ), 400 ); }
		$headers = array( 'x-signature-ed25519' => $request->get_header( 'x-signature-ed25519' ), 'x-signature-timestamp' => $request->get_header( 'x-signature-timestamp' ) );
		$result = TC_Billing_Discord::interaction( $request->get_body(), $headers );
		return is_wp_error( $result ) ? new WP_REST_Response( array( 'code' => 'invalid_interaction' ), 401 ) : new WP_REST_Response( $result, 200 );
	}
	/** Extra denial only after an explicit legacy inventory review; Discord roles are still mandatory. */
	public static function access_constraint( string $tier, int $uid ): ?bool {
		$mode = self::config()['mode'];
		if ( ! self::mode_allowed( $mode ) || '1' !== get_user_meta( $uid, '_tc_billing_legacy_reviewed_' . $mode, true ) ) { return null; }
		$sources = TC_Billing_Store::entitlements( $uid, $mode ); if ( is_wp_error( $sources ) ) { return false; } if ( ! $sources ) { return null; }
		if ( 'free' === $tier ) { return true; }
		$active = TC_Billing_Store::active( $uid, $mode ); if ( is_wp_error( $active ) ) { return false; }
		foreach ( $active as $source ) { if ( 'lifetime' === $source['plan'] || 'monthly' === $tier ) { return true; } }
		return false;
	}
}
