<?php
/**
 * WooCommerce + ArraySubs are the ONLY payment source of truth. This class never creates or
 * captures charges; it reconciles independently verified paid WooCommerce data
 * with only the Discord roles this installation actually assigned.
 *
 * @package TimonConfigs_Core
 */
defined( 'ABSPATH' ) || exit;

final class TC_Woo_Bridge {
    private const OPTION = 'timonconfigs_woo';
    private const TRACKED = '_tc_woo_tracked';
    private const OWNED = '_tc_woo_owned_roles';
    private const REVOKED = '_tc_woo_revoked';
    private const ROLE_GRANTED_AT = '_tc_woo_role_granted_at';
    private const TOKEN = 'TIMONCONFIGS_WOO_DISCORD_BOT_TOKEN';

    public static function init(): void {
        add_filter( 'cron_schedules', static function ( array $s ): array {
            $s['tc_woo_5min'] = array( 'interval' => 300, 'display' => 'TimonConfigs Woo Discord reconciliation' );
            return $s;
        } );
        add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
        add_action( 'tc_woo_reconcile', array( __CLASS__, 'reconcile' ) );
        add_action( 'tc_woo_sync_one', array( __CLASS__, 'sync_user' ), 10, 1 );
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'order_paid' ) );
        add_filter( 'woocommerce_get_return_url', array( __CLASS__, 'order_return_url' ), 50, 2 );
        add_action( 'admin_post_tc_woo_cancel_monthly', array( __CLASS__, 'cancel_monthly' ) );
        add_action( 'template_redirect', array( __CLASS__, 'hide_woo_customer_dashboard' ), 30 );
        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_changed' ), 10, 4 );
        add_action( 'woocommerce_order_refunded', array( __CLASS__, 'order_refunded' ), 10, 2 );
        // Native ArraySubs 2.0.9 hooks (the documented normalized lifecycle event
        // is not guaranteed in the released 2.0.9 source tree).
        add_action( 'arraysubs_data_activated', array( __CLASS__, 'array_native_event' ), 50, 1 );
        add_action( 'arraysubs_data_status_changed', array( __CLASS__, 'array_native_event' ), 50, 1 );
        add_action( 'arraysubs_renewal_payment_complete', array( __CLASS__, 'array_native_event' ), 50, 1 );
        add_action( 'arraysubs_lifecycle_event', array( __CLASS__, 'array_event' ), 10, 2 );
        // Observe committed WordPress subscription changes even when a gateway omits a lifecycle hook.
        add_action( 'transition_post_status', array( __CLASS__, 'array_status_changed' ), 50, 3 );
        add_action( 'updated_post_meta', array( __CLASS__, 'array_meta_changed' ), 50, 4 );
        add_action( 'added_post_meta', array( __CLASS__, 'array_meta_changed' ), 50, 4 );
        add_action( 'timonconfigs_discord_member_verified', array( __CLASS__, 'observe_member' ), 10, 2 );
        add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_checkout' ) );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'validate_store_api_checkout' ) );
        add_action( 'admin_menu', static function (): void {
            add_options_page( 'TimonConfigs Woo + Discord', 'TimonConfigs Woo + Discord', 'manage_options', 'tc-woo', array( __CLASS__, 'admin' ) );
        } );
        add_action( 'admin_init', static function (): void {
            register_setting( 'tc_woo', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'show_in_rest' => false ) );
        } );
        add_action( 'before_woocommerce_init', static function (): void {
            if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TIMONCONFIGS_CORE_FILE, true );
            }
        } );
    }

    public static function config(): array {
        $saved = get_option( self::OPTION, array() );
        return array_merge( array( 'enabled' => 0, 'public_login' => 1, 'monthly_product' => 0, 'lifetime_product' => 0 ), is_array( $saved ) ? $saved : array() );
    }
    public static function sanitize( mixed $input ): array {
        $old = self::config();
        if ( ! current_user_can( 'manage_options' ) || ! is_array( $input ) ) { return $old; }
        $out = array( 'enabled' => empty( $input['enabled'] ) ? 0 : 1, 'public_login' => empty( $input['public_login'] ) ? 0 : 1 );
        foreach ( array( 'monthly_product', 'lifetime_product' ) as $key ) {
            $out[ $key ] = absint( $input[ $key ] ?? 0 );
        }
        if ( $out['monthly_product'] && $out['monthly_product'] === $out['lifetime_product'] ) { $out['enabled'] = 0; }
        return $out;
    }
    public static function public_accounts_enabled(): bool {
        return (bool) self::config()['public_login'] && is_ssl();
    }
    private static function woo_loaded(): bool { return function_exists( 'wc_get_product' ) && function_exists( 'wc_get_orders' ); }
    /** ArraySubs 2.0.9 ships a procedural helper API, not Developer classes. */
    private static function subscriptions_loaded(): bool {
        return defined( 'ARRAYSUBS_VERSION' ) && did_action( 'arraysubs_after_boot' ) > 0
            && function_exists( 'arraysubs_is_subscription_product' )
            && function_exists( 'arraysubs_get_product_subscription_data' )
            && function_exists( 'arraysubs_get_customer_subscriptions' )
            && function_exists( 'arraysubs_get_subscription' );
    }
    private static function discord_config(): array {
        $s = get_option( 'timonconfigs_discord', array() );
        return is_array( $s ) ? $s : array();
    }
    private static function bot_token(): string {
        if ( defined( self::TOKEN ) && is_string( constant( self::TOKEN ) ) ) { return trim( constant( self::TOKEN ) ); }
        // Temporary migration fallback; never expose in HTML or logs.
        if ( defined( 'TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN' ) ) { return trim( (string) TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN ); }
        return '';
    }
    private static function snowflake( mixed $s ): bool { return is_string( $s ) && 1 === preg_match( '/^[1-9][0-9]{16,19}$/D', $s ); }
    private static function linked_discord( int $uid ): string {
        $id = get_user_meta( $uid, '_tc_discord_id', true );
        return self::snowflake( $id ) && (int) get_option( '_tc_discord_binding_' . $id ) === $uid ? $id : '';
    }
    private static function product( string $plan ): int {
        $key = 'monthly' === $plan ? 'monthly_product' : 'lifetime_product';
        return absint( self::config()[ $key ] );
    }
    /** ArraySubs 2.0.9 storefront helpers: monthly is Subscription [AS], lifetime is ordinary WooCommerce. */
    private static function product_issues(): array {
        $issues = array();
        if ( ! self::woo_loaded() ) { $issues[] = 'WooCommerce functions unavailable'; return $issues; }
        if ( ! self::subscriptions_loaded() ) {
            $issues[] = 'ArraySubs 2.0.9 helper API not loaded — check activation, dependencies, and plugin version; unsupported Developer classes are not required';
            return $issues;
        }
        if ( get_woocommerce_currency() !== 'EUR' ) { $issues[] = 'Store currency must be EUR'; }
        $monthly_id = self::product( 'monthly' ); $lifetime_id = self::product( 'lifetime' );
        if ( ! $monthly_id || ! $lifetime_id || $monthly_id === $lifetime_id ) { $issues[] = 'Set two different product IDs'; return $issues; }
        $monthly = wc_get_product( $monthly_id ); $lifetime = wc_get_product( $lifetime_id );
        if ( ! $monthly || ! $lifetime ) { $issues[] = 'Missing WooCommerce product'; return $issues; }
        if ( ! $monthly->is_type( 'simple' ) || ! $lifetime->is_type( 'simple' ) ) { $issues[] = 'Both VIP products must be Simple products'; }
        if ( 'publish' !== $monthly->get_status() || 'publish' !== $lifetime->get_status() ) { $issues[] = 'Both VIP products must be published'; }
        if ( ! $monthly->is_virtual() || ! $lifetime->is_virtual() ) { $issues[] = 'Both VIP products must be Virtual'; }
        if ( ! self::money( $monthly->get_price(), '29.99' ) || ! self::money( $lifetime->get_price(), '80.00' ) ) { $issues[] = 'Product prices must be €29.99 and €80.00'; }
        try {
            // Use real helpers shipped in the public ArraySubs 2.0.9 release.
            if ( ! arraysubs_is_subscription_product( $monthly_id ) ) {
                $issues[] = 'VIP Monthly must have Subscription [AS] enabled';
            } else {
                $terms = arraysubs_get_product_subscription_data( $monthly_id );
                if ( ! is_array( $terms ) ) {
                    $issues[] = 'ArraySubs could not read VIP Monthly terms';
                } else {
                    if ( 'month' !== ( $terms['period'] ?? '' ) || 1 !== (int) ( $terms['interval'] ?? 0 )
                         || 0 !== (int) ( $terms['length'] ?? -1 ) ) {
                        $issues[] = 'VIP Monthly must renew every 1 month without an automatic end';
                    }
                    if ( 0 !== (int) ( $terms['trial_length'] ?? -1 ) || ! self::money( $terms['signup_fee'] ?? null, '0.00' ) ) {
                        $issues[] = 'VIP Monthly must have no trial and no signup fee';
                    }
                    if ( ! self::money( $terms['price'] ?? null, '29.99' ) ) {
                        $issues[] = 'ArraySubs VIP Monthly price differs from WooCommerce';
                    }
                }
            }
            if ( arraysubs_is_subscription_product( $lifetime_id ) ) {
                $issues[] = 'VIP Lifetime must be an ordinary product without Subscription [AS]';
            }
        } catch ( \Throwable $e ) {
            $issues[] = 'ArraySubs 2.0.9 helper API read failed (check plugin compatibility)';
        }
        return $issues;
    }
    private static function valid_products(): bool { return array() === self::product_issues(); }
    private static function money( mixed $actual, string $expected ): bool {
        return is_numeric( $actual ) && abs( (float) $actual - (float) $expected ) < 0.00001;
    }
    private static function roles_configured(): bool {
        $s = self::discord_config();
        return '' !== self::bot_token() && self::snowflake( $s['guild_id'] ?? null )
            && self::snowflake( $s['monthly_role'] ?? null ) && self::snowflake( $s['lifetime_role'] ?? null )
            && $s['monthly_role'] !== $s['lifetime_role'];
    }
    public static function checkout_ready(): bool {
        return ! empty( self::config()['enabled'] ) && self::valid_products() && self::roles_configured()
            && class_exists( 'TC_Discord' ) && TC_Discord::is_ready() && is_ssl();
    }
    public static function display_price( string $plan ): string {
        if ( 'free' === $plan ) { return '€0'; }
        // Display amounts do not set prices. WooCommerce product data sets the charge.
        return 'monthly' === $plan ? '€29.99' : ( 'lifetime' === $plan ? '€80' : '' );
    }
    public static function checkout_url( string $plan ): string {
        return in_array( $plan, array( 'monthly', 'lifetime' ), true ) ? home_url( '/checkout/' . $plan . '/' ) : '';
    }
    public static function checkout( string $plan ): void {
        if ( ! in_array( $plan, array( 'monthly', 'lifetime' ), true ) ) { wp_die( 'Unknown membership.', '', array( 'response' => 404 ) ); }
        nocache_headers();
        if ( ! self::checkout_ready() ) {
            wp_die( 'Checkout setup is not complete. Please contact support.', 'Checkout unavailable', array( 'response' => 503 ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( TC_Discord::login_url( self::checkout_url( $plan ) ), 302 ); exit;
        }
        $uid = get_current_user_id();
        if ( ! self::linked_discord( $uid ) || ! self::current_discord_verified( $uid ) ) {
            wp_die( 'Please reconnect and verify your Discord account before purchasing.', 'Discord required', array( 'response' => 403 ) );
        }
        if ( ! self::delivery_ready( $uid ) ) {
            wp_die( 'Discord VIP delivery is temporarily unavailable. No payment was started. Please try again later.', '', array( 'response' => 503 ) );
        }
        $desired = self::paid_access( $uid );
        if ( ! empty( $desired['unknown'] ) ) { wp_die( 'Subscription status is temporarily unavailable. No checkout was started.', '', array( 'response' => 503 ) ); }
        if ( ( 'monthly' === $plan && ( $desired['monthly'] || $desired['lifetime'] ) ) || ( 'lifetime' === $plan && $desired['lifetime'] ) ) {
            wp_safe_redirect( home_url( '/account/' ), 302 ); exit;
        }
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_die( 'Shopping cart is unavailable. Contact support.', '', array( 'response' => 503 ) );
        }
        // Lifetime does not silently cancel an already-running PayPal recurring agreement.
        if ( 'lifetime' === $plan && $desired['monthly'] && function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( 'VIP Lifetime does not automatically cancel your existing Monthly subscription. Cancel Monthly in My Account to avoid further renewals.', 'notice' );
        }
        // PayPal Subscriptions cannot be mixed with other products.
        WC()->cart->empty_cart();
        if ( ! WC()->cart->add_to_cart( self::product( $plan ) ) ) {
            wp_die( 'Could not add the selected membership to checkout.', '', array( 'response' => 503 ) );
        }
        wp_safe_redirect( wc_get_checkout_url(), 303 ); exit;
    }
    private static function current_discord_verified( int $uid ): bool {
        if ( ! class_exists( 'TC_Discord' ) ) { return false; }
        $state = TC_Discord::status( $uid, true );
        return is_array( $state ) && in_array( $state['state'] ?? '', array( 'free', 'monthly', 'lifetime' ), true );
    }
    /** Enforce Discord ownership even when buyer uses Woo's Checkout Block API. */
    public static function validate_store_api_checkout( $order ): void {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_customer_id' ) ) { return; }
        if ( ! self::has_product( $order, self::product( 'monthly' ) ) && ! self::has_product( $order, self::product( 'lifetime' ) ) ) { return; }
        $uid = get_current_user_id();
        if ( $uid < 1 || (int) $order->get_customer_id() !== $uid || ! self::linked_discord( $uid ) ) {
            self::checkout_error( 'A linked Discord account is required to purchase a VIP membership.' );
        }
        $paid = self::paid_access( $uid );
        if ( ! empty( $paid['unknown'] ) ) { self::checkout_error( 'Subscription service unavailable. Please try later.' ); }
        if ( ( self::has_product( $order, self::product( 'monthly' ) ) && ( $paid['monthly'] || $paid['lifetime'] ) ) ||
            ( self::has_product( $order, self::product( 'lifetime' ) ) && $paid['lifetime'] ) ) {
            self::checkout_error( 'This VIP membership is already active. Manage your existing subscription in My Account.' );
        }
    }
    private static function checkout_error( string $message ): void {
        $klass = '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException';
        if ( class_exists( $klass ) ) { throw new $klass( 'tc_discord_checkout', $message, 403 ); }
        throw new RuntimeException( $message );
    }
    public static function validate_checkout(): void {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return; }
        $vip = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( in_array( (int) ( $item['product_id'] ?? 0 ), array( self::product( 'monthly' ), self::product( 'lifetime' ) ), true ) ) { $vip = true; break; }
        }
        if ( $vip && ( ! is_user_logged_in() || ! self::linked_discord( get_current_user_id() ) ) ) {
            wc_add_notice( 'Please sign in through Discord before buying a VIP membership.', 'error' );
            return;
        }
        if ( $vip ) {
            $access = self::paid_access( get_current_user_id() );
            if ( ! empty( $access['unknown'] ) ) { wc_add_notice( 'Subscription status unavailable. Please try again later.', 'error' ); return; }
            foreach ( WC()->cart->get_cart() as $item ) {
                $pid = (int) ( $item['product_id'] ?? 0 );
                if ( ( $pid === self::product( 'monthly' ) && ( $access['monthly'] || $access['lifetime'] ) ) || ( $pid === self::product( 'lifetime' ) && $access['lifetime'] ) ) {
                    wc_add_notice( 'You already have this VIP membership. Manage your subscription in My Account.', 'error' );
                    break;
                }
            }
        }
    }
    private static function has_product( $order, int $product_id ): bool {
        if ( ! $product_id || ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) { return false; }
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( (int) $item->get_product_id() === $product_id ) { return true; }
        }
        return false;
    }
    /** Hide WooCommerce's account hub while keeping payment/receipt endpoints. */
    public static function hide_woo_customer_dashboard(): void {
        if ( is_admin() || ! function_exists( 'is_account_page' ) || ! is_account_page() ||
             ! function_exists( 'wc_get_page_permalink' ) ) { return; }
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) { return; }
        $official = wc_get_page_permalink( 'myaccount' );
        if ( ! $official || untrailingslashit( $official ) === untrailingslashit( home_url( '/account/' ) ) ) { return; }
        wp_safe_redirect( home_url( '/account/' ), 302 ); exit;
    }

    /** WooCommerce redirect after checkout: always land on our own member dashboard. */
    public static function order_return_url( $url, $order ) {
        if ( ! $order || ! method_exists( $order, 'get_customer_id' ) ) { return $url; }
        if ( ! self::has_product( $order, self::product( 'monthly' ) ) &&
             ! self::has_product( $order, self::product( 'lifetime' ) ) ) { return $url; }
        return home_url( '/account/?tc_payment=return' );
    }

    /** An authenticated customer can cancel their own recurring agreement. */
    public static function cancel_monthly(): void {
        if ( ! is_user_logged_in() ) { wp_die( 'Sign in through Discord.', 'Access denied', array( 'response' => 403 ) ); }
        check_admin_referer( 'tc_woo_cancel_monthly' );
        $uid = get_current_user_id();
        if ( ! self::linked_discord( $uid ) || ! self::subscriptions_loaded() ||
             ! function_exists( 'arraysubs_schedule_cancellation' ) ) {
            wp_safe_redirect( home_url( '/account/?tc_cancel=unavailable' ), 303 ); exit;
        }
        $result = 'invalid';
        try {
            $id = absint( $_POST['tc_subscription_id'] ?? 0 );
            $owned = $id > 0 && $uid === absint( get_post_meta( $id, '_customer_id', true ) ) &&
                     self::product( 'monthly' ) === absint( get_post_meta( $id, '_product_id', true ) );
            $sub = $owned ? arraysubs_get_subscription( $id ) : null;
            if ( $sub && in_array( $sub->post_status, array( 'arraysubs-active', 'arraysubs-paused', 'arraysubs-on-hold' ), true ) ) {
                if ( function_exists( 'arraysubs_is_waiting_cancellation' ) && arraysubs_is_waiting_cancellation( $id ) ) {
                    $result = 'scheduled';
                } else {
                    $ok = arraysubs_schedule_cancellation( $id, 'Customer cancellation from TimonConfigs member dashboard', 'customer' );
                    $result = $ok && ! is_wp_error( $ok ) ? 'scheduled' : 'failed';
                    if ( 'scheduled' === $result ) { self::track( $uid ); }
                }
            }
        } catch ( \Throwable $e ) {
            // A provider/dependency error must never claim that future charges stopped.
            update_user_meta( $uid, '_tc_woo_cancel_error', 'subscription_cancellation_requires_review' );
            $result = 'failed';
        }
        wp_safe_redirect( home_url( '/account/?tc_cancel=' . $result ), 303 ); exit;
    }

    private static function track( int $uid ): void {
        if ( $uid && self::linked_discord( $uid ) ) {
            update_user_meta( $uid, self::TRACKED, '1' );
            if ( function_exists( 'as_enqueue_async_action' ) ) {
                if ( ! function_exists( 'as_has_scheduled_action' ) ||
                     ! as_has_scheduled_action( 'tc_woo_sync_one', array( $uid ), 'timonconfigs' ) ) {
                    as_enqueue_async_action( 'tc_woo_sync_one', array( $uid ), 'timonconfigs' );
                }
            }
            if ( ! wp_next_scheduled( 'tc_woo_sync_one', array( $uid ) ) ) { wp_schedule_single_event( time() + 5, 'tc_woo_sync_one', array( $uid ) ); }
        }
    }
    public static function order_paid( int $order_id ): void {
        if ( ! self::woo_loaded() ) { return; }
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->is_paid() || ! $order->get_date_paid() ) { return; }
        if ( self::has_product( $order, self::product( 'lifetime' ) ) || self::has_product( $order, self::product( 'monthly' ) ) ) { self::track( (int) $order->get_customer_id() ); }
    }
    public static function order_changed( int $order_id, string $old, string $new, $order ): void {
        if ( self::has_product( $order, self::product( 'lifetime' ) ) || self::has_product( $order, self::product( 'monthly' ) ) ) { self::track( (int) $order->get_customer_id() ); }
        if ( 'refunded' === $new ) { self::order_refunded( $order_id, 0 ); }
    }
    public static function order_refunded( int $order_id, int $refund_id ): void {
        if ( ! self::woo_loaded() ) { return; }
        $order = wc_get_order( $order_id );
        if ( $order && self::has_product( $order, self::product( 'lifetime' ) ) ) { self::track( (int) $order->get_customer_id() ); }
        // ArraySubs publishes a lifecycle event for its own subscription refunds.
        // Never mutate ArraySubs private meta or the provider's subscription.
    }
    /** Native 2.0.9 lifecycle events enqueue a recheck; no payment is trusted here. */
    public static function array_native_event( $subscription_id ): void {
        $raw_id = is_object( $subscription_id ) ? ( $subscription_id->ID ?? 0 ) : $subscription_id;
        $id = is_numeric( $raw_id ) ? absint( $raw_id ) : 0;
        if ( ! $id || ! self::subscriptions_loaded() ) { return; }
        try {
            if ( ! arraysubs_get_subscription( $id ) ) { return; }
            $uid = absint( get_post_meta( $id, '_customer_id', true ) );
            $pid = absint( get_post_meta( $id, '_variation_id', true ) );
            if ( ! $pid ) { $pid = absint( get_post_meta( $id, '_product_id', true ) ); }
            if ( $uid && $pid === self::product( 'monthly' ) ) { self::track( $uid ); }
        } catch ( \Throwable $e ) { return; }
    }
    public static function array_status_changed( string $new, string $old, $post ): void {
        if ( $new === $old || ( ! str_starts_with( $new, 'arraysubs-' ) && ! str_starts_with( $old, 'arraysubs-' ) ) ) { return; }
        self::array_native_event( $post );
    }
    public static function array_meta_changed( $meta_id, $post_id, $key, $value ): void {
        if ( ! in_array( $key, array( '_next_payment_date', '_end_date', '_completed_payments', '_scheduled_cancel_date' ), true ) ) { return; }
        self::array_native_event( $post_id );
    }
    /** A fresh member read queues reconciliation; it never grants access or writes Discord roles inline. */
    public static function observe_member( int $uid, array $status ): void {
        if ( empty( $status['member_verified'] ) || '1' !== get_user_meta( $uid, self::TRACKED, true ) ||
             (int) get_user_meta( $uid, '_tc_woo_observe_after', true ) > time() ) { return; }
        update_user_meta( $uid, '_tc_woo_observe_after', time() + 60 );
        try { self::track( $uid ); }
        catch ( \Throwable $e ) { update_user_meta( $uid, '_tc_woo_sync_error', 'sync_queue_unavailable' ); }
    }
    /** Events only queue work; authoritative paid state is reread in sync_user(). */
    public static function array_event( string $event, array $payload ): void {
        $id = absint( $payload['subscription_id'] ?? 0 );
        if ( ! $id || ! self::subscriptions_loaded() ) { return; }
        try {
            $subscription = arraysubs_get_subscription( $id );
            if ( ! $subscription ) { return; }
            $owner = absint( get_post_meta( $id, '_customer_id', true ) );
            $incoming = absint( $payload['user_id'] ?? 0 );
            if ( ! $owner || ( $incoming && $owner !== $incoming ) ) { return; }
            $pid = absint( get_post_meta( $id, '_variation_id', true ) );
            if ( ! $pid ) { $pid = absint( get_post_meta( $id, '_product_id', true ) ); }
            if ( self::product( 'monthly' ) === $pid ) { self::track( $owner ); }
        } catch ( \Throwable $e ) { return; }
    }
    /** Only a genuinely NEW purchase may undo a manual VIP removal. */
    private static function revocation_cutoff( int $uid, string $plan ): int {
        $data = get_user_meta( $uid, self::REVOKED, true );
        return is_array( $data ) ? absint( $data[ $plan ] ?? 0 ) : 0;
    }

    /** Read-only ArraySubs 2.0.9 subscription status plus independently paid initial WC order. */
    private static function monthly_status( int $uid ): array {
        $value = array( 'active' => false, 'until' => 0, 'unknown' => false );
        if ( ! self::subscriptions_loaded() ) { $value['unknown'] = true; return $value; }
        try {
            $ids = arraysubs_get_customer_subscriptions( $uid, array( 'arraysubs-active' ) );
            if ( ! is_array( $ids ) ) { $value['unknown'] = true; return $value; }
            foreach ( $ids as $raw_id ) {
                $id = absint( $raw_id );
                $sub = $id ? arraysubs_get_subscription( $id ) : null;
                if ( ! $sub || 'arraysubs-active' !== $sub->post_status ) { continue; }
                if ( $uid !== absint( get_post_meta( $id, '_customer_id', true ) ) ) {
                    $value['unknown'] = true; return $value;
                }
                $pid = absint( get_post_meta( $id, '_variation_id', true ) );
                if ( ! $pid ) { $pid = absint( get_post_meta( $id, '_product_id', true ) ); }
                if ( $pid !== self::product( 'monthly' ) ) { continue; }
                if ( 'month' !== get_post_meta( $id, '_billing_period', true ) ||
                     1 !== (int) get_post_meta( $id, '_billing_interval', true ) ||
                     'EUR' !== get_post_meta( $id, '_currency', true ) ||
                     ! self::money( get_post_meta( $id, '_recurring_amount', true ), '29.99' ) ) {
                    $value['unknown'] = true; return $value;
                }
                if ( (int) get_post_meta( $id, '_trial_length', true ) > 0 ||
                     (int) get_post_meta( $id, '_completed_payments', true ) < 1 ) { continue; }
                $initial_id = absint( get_post_meta( $id, '_parent_order_id', true ) );
                $initial = $initial_id ? wc_get_order( $initial_id ) : null;
                if ( $initial && $initial->get_date_paid() &&
                     $initial->get_date_paid()->getTimestamp() <= self::revocation_cutoff( $uid, 'monthly' ) ) { continue; }
                if ( ! $initial || (int) $initial->get_customer_id() !== $uid ||
                     ! $initial->is_paid() || ! $initial->get_date_paid() ||
                     ! self::has_product( $initial, self::product( 'monthly' ) ) ) {
                    $value['unknown'] = true; return $value;
                }
                $payments = (int) get_post_meta( $id, '_completed_payments', true );
                if ( (float) $initial->get_total() > 0 &&
                     (float) $initial->get_total_refunded() + 0.0001 >= (float) $initial->get_total()
                     && $payments === 1 ) { continue; }
                $renewal = self::utc_time( get_post_meta( $id, '_next_payment_date', true ) );
                $ending = self::utc_time( get_post_meta( $id, '_end_date', true ) );
                $cancel = 0;
                if ( function_exists( 'arraysubs_is_waiting_cancellation' ) && arraysubs_is_waiting_cancellation( $id ) &&
                     function_exists( 'arraysubs_get_scheduled_cancel_date' ) ) {
                    $cancel = self::utc_time( arraysubs_get_scheduled_cancel_date( $id ) );
                }
                $dates = array_filter( array( $renewal, $ending, $cancel ), static fn( int $t ): bool => $t > 0 );
                if ( ! $dates ) { $value['unknown'] = true; return $value; }
                $until = min( $dates );
                if ( $until > time() ) {
                    $value['active'] = true;
                    $value['until'] = max( $value['until'], $until );
                }
            }
        } catch ( \Throwable $e ) { $value['unknown'] = true; }
        return $value;
    }
    private static function utc_time( mixed $date ): int {
        if ( is_int( $date ) ) { return $date > 0 ? $date : 0; }
        if ( ! is_string( $date ) || '' === $date ) { return 0; }
        try { return ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->getTimestamp(); }
        catch ( \Throwable $e ) { return 0; }
    }
    public static function paid_access( int $uid ): array {
        $access = array( 'monthly' => false, 'lifetime' => false, 'monthly_until' => 0, 'unknown' => false );
        if ( ! $uid || ! self::woo_loaded() || ! self::linked_discord( $uid ) ) { return $access; }
        $life = self::product( 'lifetime' );
        if ( $life ) {
            // HPOS safe: Woo order CRUD, never query wp_posts / postmeta directly.
            $page = 1;
            do {
                $orders = wc_get_orders( array( 'customer_id' => $uid, 'limit' => 50, 'paged' => $page, 'orderby' => 'date', 'order' => 'DESC', 'status' => array( 'processing', 'completed' ), 'return' => 'objects' ) );
                foreach ( $orders as $order ) {
                    if ( ! $order->get_date_paid() || ! $order->is_paid() || ! self::has_product( $order, $life ) ||
                         $order->get_date_paid()->getTimestamp() <= self::revocation_cutoff( $uid, 'lifetime' ) ) { continue; }
                    if ( (float) $order->get_total() <= 0.0 || (float) $order->get_total_refunded() + 0.0001 >= (float) $order->get_total() ) { continue; }
                    $access['lifetime'] = true; break;
                }
                if ( $access['lifetime'] || count( $orders ) < 50 ) { break; }
                $page++;
            } while ( $page <= 10 );
        }
        $monthly = self::monthly_status( $uid );
        $access['monthly'] = $monthly['active'];
        $access['monthly_until'] = $monthly['until'];
        $access['unknown'] = $monthly['unknown'];
        return $access;
    }
    /** Woo purchase state cannot be bypassed by a stale Discord role. */
    public static function access_constraint( string $tier, int $uid ): ?bool {
        if ( '1' !== get_user_meta( $uid, self::TRACKED, true ) ) { return null; } // Preserve externally granted / historical roles.
        if ( ! in_array( $tier, array( 'monthly', 'lifetime' ), true ) ) { return null; }
        $a = self::paid_access( $uid );
        return 'lifetime' === $tier ? $a['lifetime'] : ( $a['lifetime'] || ( empty( $a['unknown'] ) && $a['monthly'] ) );
    }
    private static function discord_api( string $method, string $path ): array|WP_Error {
        $token = self::bot_token();
        if ( ! $token ) { return new WP_Error( 'discord_token_missing', 'Discord bot token is not configured.' ); }
        $res = wp_remote_request( 'https://discord.com/api/v10' . $path, array( 'method' => $method, 'timeout' => 12, 'redirection' => 0, 'headers' => array( 'Authorization' => 'Bot ' . $token, 'Accept' => 'application/json' ) ) );
        if ( is_wp_error( $res ) ) { return new WP_Error( 'discord_transport', 'Discord API request failed.' ); }
        $code = (int) wp_remote_retrieve_response_code( $res );
        if ( $code < 200 || $code >= 300 ) { return new WP_Error( 'discord_http_' . $code, 'Discord API refused the role operation.' ); }
        if ( 'GET' !== $method ) { return array(); }
        $raw = json_decode( (string) wp_remote_retrieve_body( $res ), true );
        return is_array( $raw ) ? $raw : new WP_Error( 'discord_response', 'Invalid Discord member response.' );
    }
    /** Discord permissions are decimal bitfields that may exceed PHP_INT_MAX. */
    private static function has_permission( string $decimal, int $bit ): bool {
        if ( ! ctype_digit( $decimal ) ) { return false; }
        for ( $position = 0; $position <= $bit; ++$position ) {
            $quotient = ''; $carry = 0;
            foreach ( str_split( $decimal ) as $digit ) {
                $value = $carry * 10 + (int) $digit;
                $quotient .= (string) intdiv( $value, 2 );
                $carry = $value % 2;
            }
            if ( $position === $bit ) { return 1 === $carry; }
            $decimal = ltrim( $quotient, '0' );
            if ( '' === $decimal ) { return false; }
        }
        return false;
    }
    /** Reject a new purchase when the bot cannot deliver roles. */
    private static function delivery_ready( int $uid ): bool {
        $settings = self::discord_config(); $discord_id = self::linked_discord( $uid );
        if ( ! $discord_id || ! self::roles_configured() ) { return false; }
        $guild = $settings['guild_id'];
        $bot = self::discord_api( 'GET', '/users/@me' );
        if ( is_wp_error( $bot ) || ! self::snowflake( $bot['id'] ?? null ) || true !== ( $bot['bot'] ?? null ) ) { return false; }
        $roles = self::discord_api( 'GET', '/guilds/' . $guild . '/roles' );
        $bot_member = self::discord_api( 'GET', '/guilds/' . $guild . '/members/' . $bot['id'] );
        $buyer = self::discord_api( 'GET', '/guilds/' . $guild . '/members/' . $discord_id );
        if ( is_wp_error( $roles ) || is_wp_error( $bot_member ) || is_wp_error( $buyer ) ||
             ! is_array( $bot_member['roles'] ?? null ) || (string) ( $buyer['user']['id'] ?? '' ) !== $discord_id ) { return false; }
        $map = array(); foreach ( $roles as $role ) { if ( is_array( $role ) && self::snowflake( $role['id'] ?? null ) ) { $map[ $role['id'] ] = $role; } }
        $high = -1; $manage = false;
        foreach ( $bot_member['roles'] as $bot_role ) {
            if ( ! isset( $map[ $bot_role ] ) ) { continue; }
            $high = max( $high, (int) $map[ $bot_role ]['position'] );
            $bits = (string) ( $map[ $bot_role ]['permissions'] ?? '0' );
            $manage = $manage || self::has_permission( $bits, 28 ) || self::has_permission( $bits, 3 );
        }
        foreach ( array( 'monthly_role', 'lifetime_role' ) as $key ) {
            $role = $map[ $settings[ $key ] ] ?? null;
            if ( ! $role || ! empty( $role['managed'] ) || (int) $role['position'] >= $high ) { return false; }
        }
        return $manage;
    }
    /** Discord audit-verified Staff/Owner manual role removal; never infer the actor from a missing role. */
    private static function staff_removed_role( string $guild, string $discord_id, string $role, int $after = 0 ): bool {
        $audit = self::discord_api( 'GET', '/guilds/' . rawurlencode( $guild ) . '/audit-logs?action_type=25&limit=50' );
        if ( is_wp_error( $audit ) || ! is_array( $audit['audit_log_entries'] ?? null ) ) { return false; }
        $settings = self::discord_config();
        foreach ( $audit['audit_log_entries'] as $entry ) {
            if ( ! is_array( $entry ) || (string) ( $entry['target_id'] ?? '' ) !== $discord_id ) { continue; }
            $event_id = (string) ( $entry['id'] ?? '' );
            if ( ! self::snowflake( $event_id ) ) { continue; }
            $event_time = intdiv( (int) $event_id, 4194304 ) / 1000 + 1420070400;
            if ( $event_time < time() - 900 || $event_time > time() + 60 ) { continue; }
            // A removal preceding the newest grant must never revoke a new purchase.
            if ( $event_time <= $after ) { continue; }
            $removed = false;
            foreach ( $entry['changes'] ?? array() as $change ) {
                if ( ( $change['key'] ?? '' ) !== '$remove' ) { continue; }
                foreach ( $change['new_value'] ?? array() as $removed_role ) {
                    if ( (string) ( $removed_role['id'] ?? '' ) === $role ) { $removed = true; }
                }
            }
            if ( ! $removed ) { continue; }
            $actor = (string) ( $entry['user_id'] ?? '' );
            if ( ! self::snowflake( $actor ) || $actor === $discord_id ) { continue; }
            $staff = self::discord_api( 'GET', '/guilds/' . rawurlencode( $guild ) . '/members/' . rawurlencode( $actor ) );
            if ( is_wp_error( $staff ) || ! is_array( $staff['roles'] ?? null ) ||
                 ! empty( $staff['user']['bot'] ) ) { continue; }
            $power = array_filter( array( $settings['owner_role'] ?? '', $settings['staff_role'] ?? '' ) );
            if ( array_intersect( array_map( 'strval', $staff['roles'] ), $power ) ) { return true; }
        }
        return false;
    }

    /** Stop recurring charges where ArraySubs can safely retire the subscription. Preserve orders and payments. */
    private static function stop_moderated_monthly( int $uid ): void {
        if ( ! function_exists( 'arraysubs_cancel_subscription_immediately' ) ) {
            update_user_meta( $uid, '_tc_woo_sync_error', 'moderation_cancel_api_unavailable' ); return;
        }
        try {
            $subs = arraysubs_get_customer_subscriptions( $uid, array( 'arraysubs-active', 'arraysubs-paused', 'arraysubs-on-hold' ) );
            if ( ! is_array( $subs ) ) { throw new \RuntimeException( 'Subscription list unavailable.' ); }
        } catch ( \Throwable $e ) {
            update_user_meta( $uid, '_tc_woo_sync_error', 'moderation_remote_payment_cancellation_requires_review' ); return;
        }
        foreach ( is_array( $subs ) ? $subs : array() as $sid ) {
            $sid = absint( $sid );
            if ( $uid !== absint( get_post_meta( $sid, '_customer_id', true ) ) ||
                 self::product( 'monthly' ) !== absint( get_post_meta( $sid, '_product_id', true ) ) ) { continue; }
            try {
                $cancelled = arraysubs_cancel_subscription_immediately( $sid, 'Staff/Owner revoked VIP on Discord', 'system' );
                if ( $cancelled && ! is_wp_error( $cancelled ) ) { continue; }
            } catch ( \Throwable $e ) {
                // Preserve the entitlement cutoff, but leave billing for operator review.
            }
            update_user_meta( $uid, '_tc_woo_sync_error', 'moderation_remote_payment_cancellation_requires_review' );
        }
    }

    private static function record_manual_revocation( int $uid, string $plan ): void {
        $data = get_user_meta( $uid, self::REVOKED, true );
        $data = is_array( $data ) ? $data : array();
        $granted = get_user_meta( $uid, self::ROLE_GRANTED_AT, true );
        $last_grant = is_array( $granted ) ? absint( $granted[ $plan ] ?? 0 ) : 0;
        if ( ! empty( $data[ $plan ] ) && $last_grant <= absint( $data[ $plan ] ) ) { return; }
        $data[ $plan ] = time();
        update_user_meta( $uid, self::REVOKED, $data );
        delete_user_meta( $uid, '_tc_discord_status' );
        if ( 'monthly' === $plan ) { self::stop_moderated_monthly( $uid ); }
    }

    /** One worker per member; compare-and-delete cannot release a newer worker's lease. */
    private static function sync_lock_value( int $uid ): string {
        global $wpdb;
        return (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", '_tc_woo_sync_lock_' . $uid ) );
    }
    private static function release_sync( int $uid, string $lease ): void {
        global $wpdb;
        $key = '_tc_woo_sync_lock_' . $uid;
        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s", $key, $lease ) );
        wp_cache_delete( $key, 'options' );
    }
    private static function owns_sync( int $uid, string $lease ): bool {
        return (int) $lease > time() && hash_equals( $lease, self::sync_lock_value( $uid ) );
    }
    private static function role_change( int $uid, string $lease, string $method, string $path ): array|WP_Error {
        if ( ! self::owns_sync( $uid, $lease ) ) { return new WP_Error( 'sync_lease_lost', 'Membership synchronization will be retried.' ); }
        return self::discord_api( $method, $path );
    }
    public static function sync_user( int $uid ): void {
        if ( $uid < 1 || ! self::roles_configured() || ! self::woo_loaded() || ! self::subscriptions_loaded() ) { return; }
        $key = '_tc_woo_sync_lock_' . $uid;
        $lease = ( time() + 300 ) . ':' . bin2hex( random_bytes( 16 ) );
        if ( ! add_option( $key, $lease, '', false ) ) {
            $old = self::sync_lock_value( $uid );
            if ( ! $old || (int) $old >= time() ) { return; }
            self::release_sync( $uid, $old );
            if ( ! add_option( $key, $lease, '', false ) ) { return; }
        }
        try { self::sync_member( $uid, $lease ); }
        catch ( \Throwable $e ) { update_user_meta( $uid, '_tc_woo_sync_error', 'sync_unavailable' ); }
        finally { self::release_sync( $uid, $lease ); }
    }
    private static function sync_member( int $uid, string $lease ): void {
        if ( $uid < 1 || ! self::roles_configured() || ! self::woo_loaded() || ! self::subscriptions_loaded() ) { return; }
        $id = self::linked_discord( $uid ); if ( ! $id ) { return; }
        $settings = self::discord_config(); $guild = $settings['guild_id'];
        $roles = array( 'monthly' => $settings['monthly_role'], 'lifetime' => $settings['lifetime_role'] );
        $free_role = (string) ( $settings['free_role'] ?? '' );
        $path = '/guilds/' . rawurlencode( $guild ) . '/members/' . rawurlencode( $id );
        $member = self::discord_api( 'GET', $path );
        if ( is_wp_error( $member ) || ! is_array( $member['roles'] ?? null ) || (string) ( $member['user']['id'] ?? '' ) !== $id ) {
            update_user_meta( $uid, '_tc_woo_sync_error', is_wp_error( $member ) ? $member->get_error_code() : 'member_unverified' ); return;
        }
        $present = array_fill_keys( array_map( 'strval', $member['roles'] ), true );
        $role_changed = false; $free_attempted = false;
        $owned = get_user_meta( $uid, self::OWNED, true ); $owned = is_array( $owned ) ? $owned : array();
        $wanted = self::paid_access( $uid );
        if ( ! self::owns_sync( $uid, $lease ) ) { return; }
        if ( ! empty( $wanted['unknown'] ) ) { update_user_meta( $uid, '_tc_woo_sync_error', 'arraysubs_read_failed' ); return; }
        foreach ( $roles as $plan => $role ) {
            if ( ! self::owns_sync( $uid, $lease ) ) { return; }
            $exists = isset( $present[ $role ] ); $is_owned = ! empty( $owned[ $role ] );
            if ( ! $exists && $is_owned ) {
                // A previous TimonConfigs grant disappeared. Never blindly restore it.
                // Only a verified Discord audit entry from Staff/Owner triggers cancellation.
                $grants = get_user_meta( $uid, self::ROLE_GRANTED_AT, true );
                $last_grant = is_array( $grants ) ? absint( $grants[ $plan ] ?? 0 ) : 0;
                if ( self::staff_removed_role( $guild, $id, $role, $last_grant ) ) {
                    if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                    self::record_manual_revocation( $uid, $plan );
                    if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                    unset( $owned[ $role ] );
                    $is_owned = false;
                    $wanted = self::paid_access( $uid );
                    if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                    if ( ! empty( $wanted['unknown'] ) ) {
                        update_user_meta( $uid, self::OWNED, $owned );
                        update_user_meta( $uid, '_tc_woo_sync_error', 'arraysubs_read_failed' ); return;
                    }
                } else {
                    update_user_meta( $uid, '_tc_woo_sync_error', 'vip_role_missing_manual_review_required' );
                    continue;
                }
            }
            $need = (bool) $wanted[ $plan ];
            // Lifetime supersedes Monthly access; do not newly grant Monthly,
            // but preserve an owned Monthly badge until its own period ends.
            if ( 'monthly' === $plan && $wanted['lifetime'] && ! $wanted['monthly'] ) { $need = false; }
            if ( $need && ! $exists ) {
                $fresh = self::paid_access( $uid );
                if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                if ( ! empty( $fresh['unknown'] ) || ! $fresh[ $plan ] ) { continue; }
                $owned[ $role ] = 1;
                if ( ! update_user_meta( $uid, self::OWNED, $owned ) ) {
                    update_user_meta( $uid, '_tc_woo_sync_error', 'owned_marker_write_failed' ); continue;
                }
                $grant_started = time();
                $resp = self::role_change( $uid, $lease, 'PUT', $path . '/roles/' . rawurlencode( $role ) );
                if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                if ( is_wp_error( $resp ) ) { unset( $owned[ $role ] ); update_user_meta( $uid, self::OWNED, $owned ); update_user_meta( $uid, '_tc_woo_sync_error', $resp->get_error_code() ); continue; }
                // An entitlement may have changed while the remote PUT was in flight.
                $after_grant = self::paid_access( $uid );
                if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                if ( ! empty( $after_grant['unknown'] ) || ! $after_grant[ $plan ] ) {
                    $undo = self::role_change( $uid, $lease, 'DELETE', $path . '/roles/' . rawurlencode( $role ) );
                    if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                    if ( ! is_wp_error( $undo ) ) { unset( $owned[ $role ] ); }
                    update_user_meta( $uid, self::OWNED, $owned );
                    delete_user_meta( $uid, '_tc_discord_status' );
                    update_user_meta( $uid, '_tc_woo_sync_error', is_wp_error( $undo ) ? $undo->get_error_code() : 'entitlement_changed_during_grant' );
                    return;
                }
                $present[ $role ] = true; $role_changed = true;
                $granted = get_user_meta( $uid, self::ROLE_GRANTED_AT, true );
                $granted = is_array( $granted ) ? $granted : array();
                $granted[ $plan ] = $grant_started;
                update_user_meta( $uid, self::ROLE_GRANTED_AT, $granted );
            } elseif ( ! $need && $exists && $is_owned ) {
                $fresh = self::paid_access( $uid );
                if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                if ( ! empty( $fresh['unknown'] ) || $fresh[ $plan ] || ( 'monthly' === $plan && $fresh['lifetime'] && $fresh['monthly'] ) ) { continue; }
                // Add the fallback before removing the last paid role, when possible.
                if ( ! $fresh['monthly'] && ! $fresh['lifetime'] && self::snowflake( $free_role ) &&
                     ! in_array( $free_role, $roles, true ) && ! isset( $present[ $free_role ] ) ) {
                    $free_attempted = true;
                    $fallback = self::role_change( $uid, $lease, 'PUT', $path . '/roles/' . rawurlencode( $free_role ) );
                    if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                    if ( is_wp_error( $fallback ) ) { update_user_meta( $uid, '_tc_woo_sync_error', $fallback->get_error_code() ); }
                    else { $present[ $free_role ] = true; $role_changed = true; }
                    $fresh = self::paid_access( $uid );
                    if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                    if ( ! empty( $fresh['unknown'] ) ) {
                        update_user_meta( $uid, self::OWNED, $owned );
                        if ( $role_changed ) { delete_user_meta( $uid, '_tc_discord_status' ); }
                        update_user_meta( $uid, '_tc_woo_sync_error', 'arraysubs_read_failed' ); return;
                    }
                    if ( $fresh[ $plan ] ) { continue; }
                }
                $resp = self::role_change( $uid, $lease, 'DELETE', $path . '/roles/' . rawurlencode( $role ) );
                if ( ! self::owns_sync( $uid, $lease ) ) { return; }
                if ( is_wp_error( $resp ) ) { update_user_meta( $uid, '_tc_woo_sync_error', $resp->get_error_code() ); continue; }
                unset( $owned[ $role ] ); unset( $present[ $role ] ); $role_changed = true;
            }
            // Pre-existing, externally granted Discord roles are never adopted
            // or deleted by our service, even if there is no paid Woo access.
        }
        if ( ! self::owns_sync( $uid, $lease ) ) { return; }
        $wanted = self::paid_access( $uid );
        if ( ! self::owns_sync( $uid, $lease ) ) { return; }
        if ( ! empty( $wanted['unknown'] ) ) {
            update_user_meta( $uid, self::OWNED, $owned );
            if ( $role_changed ) { delete_user_meta( $uid, '_tc_discord_status' ); }
            update_user_meta( $uid, '_tc_woo_sync_error', 'arraysubs_read_failed' ); return;
        }
        // Free Member is a fallback, never simultaneous with an owned VIP role.
        // Do not remove Free until the VIP grant has actually been confirmed.
        if ( self::snowflake( $free_role ) && $free_role !== $settings['monthly_role'] &&
             $free_role !== $settings['lifetime_role'] ) {
            $has_verified_vip = ( isset( $present[ $settings['monthly_role'] ] ) && ! empty( $wanted['monthly'] ) ) ||
                                ( isset( $present[ $settings['lifetime_role'] ] ) && ! empty( $wanted['lifetime'] ) );
            if ( $has_verified_vip && isset( $present[ $free_role ] ) ) {
                $response = self::role_change( $uid, $lease, 'DELETE', $path . '/roles/' . rawurlencode( $free_role ) );
                if ( is_wp_error( $response ) ) { update_user_meta( $uid, '_tc_woo_sync_error', $response->get_error_code() ); }
                else { unset( $present[ $free_role ] ); $role_changed = true; }
            } elseif ( ! $has_verified_vip && ! $wanted['monthly'] && ! $wanted['lifetime'] &&
                       ! isset( $present[ $free_role ] ) && ! $free_attempted ) {
                $response = self::role_change( $uid, $lease, 'PUT', $path . '/roles/' . rawurlencode( $free_role ) );
                if ( is_wp_error( $response ) ) { update_user_meta( $uid, '_tc_woo_sync_error', $response->get_error_code() ); }
                else { $present[ $free_role ] = true; $role_changed = true; }
            }
        }
        if ( ! self::owns_sync( $uid, $lease ) ) { return; }
        update_user_meta( $uid, self::OWNED, $owned );
        // Invalidate the member page's 60-second role badge cache after successful bot changes.
        if ( $role_changed ) { delete_user_meta( $uid, '_tc_discord_status' ); }
        update_user_meta( $uid, '_tc_woo_last_sync', gmdate( 'Y-m-d H:i:s' ) );
        // If any operation fails it will be retried on the next cron tick.
    }
    public static function schedule(): void {
        if ( self::woo_loaded() && ! wp_next_scheduled( 'tc_woo_reconcile' ) ) {
            wp_schedule_event( time() + 60, 'tc_woo_5min', 'tc_woo_reconcile' );
        }
        if ( get_option( 'tc_woo_legacy_cron_cleanup' ) !== '1' ) {
            wp_clear_scheduled_hook( 'tc_billing_tick' );
            update_option( 'tc_woo_legacy_cron_cleanup', '1', false );
        }
    }
    public static function reconcile(): void {
        if ( ! self::woo_loaded() || ! self::subscriptions_loaded() ) { return; }
        $offset = absint( get_option( 'tc_woo_reconcile_offset', 0 ) );
        $users = get_users( array( 'fields' => 'ID', 'meta_key' => self::TRACKED, 'meta_value' => '1', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 100, 'offset' => $offset ) );
        foreach ( $users as $uid ) { self::sync_user( (int) $uid ); }
        update_option( 'tc_woo_reconcile_offset', count( $users ) < 100 ? 0 : $offset + count( $users ), false );
    }
    /** Private, Discord-authenticated billing status without the WooCommerce account dashboard. */
    public static function account( int $uid ): string {
        if ( ! self::woo_loaded() || ! self::subscriptions_loaded() ) { return '<section class="tc-member-management"><p>Membership information is temporarily unavailable.</p></section>'; }
        try {
            $access = self::paid_access( $uid );
        } catch ( \Throwable $e ) {
            $access = array( 'monthly' => false, 'lifetime' => false, 'monthly_until' => 0, 'unknown' => true );
        }
        $title = ! empty( $access['unknown'] ) ? 'Membership verification pending' :
            ( $access['lifetime'] ? 'VIP Lifetime' : ( $access['monthly'] ? 'VIP Monthly' : 'Free Member' ) );
        $status = ! empty( $access['unknown'] )
            ? 'Subscription information is temporarily unavailable. Your subscription may still renew. Please contact support.'
            : ( $access['monthly'] && ! $access['lifetime']
                ? 'VIP Monthly is paid through ' . wp_date( 'j M Y', $access['monthly_until'] )
                : ( $access['lifetime'] ? 'Your Lifetime access is active.' : 'No current paid membership.' ) );
        $message = '';
        if ( isset( $_GET['tc_cancel'] ) ) {
            $notices = array(
                'scheduled' => 'Your subscription cancellation is scheduled. VIP access remains until the paid period ends.',
                'failed' => 'Cancellation could not be confirmed. Your subscription may still renew. Please contact support.',
                'invalid' => 'This subscription cannot be cancelled from this account.',
                'unavailable' => 'Cancellation is temporarily unavailable. Please contact support.',
            );
            $message = $notices[ sanitize_key( wp_unslash( (string) $_GET['tc_cancel'] ) ) ] ?? '';
        } elseif ( isset( $_GET['tc_payment'] ) ) {
            $message = 'Payment returned. Your Discord VIP role appears after Stripe or PayPal confirms the order. Refresh your roles if needed.';
        }
        $html = '<section class="tc-member-management"><h2>My membership</h2><p><strong>' . esc_html( $title ) . '</strong></p><p>' . esc_html( $status ) . '</p>';
        if ( $message ) { $html .= '<p role="status">' . esc_html( $message ) . '</p>'; }
        // Lifetime does not cancel an existing Monthly agreement. Keep its controls available.
        try {
            $controls = '';
            $subs = arraysubs_get_customer_subscriptions( $uid, array( 'arraysubs-active', 'arraysubs-paused', 'arraysubs-on-hold' ) );
            if ( ! is_array( $subs ) ) { throw new \RuntimeException( 'Subscription list unavailable.' ); }
            foreach ( is_array( $subs ) ? $subs : array() as $sid ) {
                $sid = absint( $sid );
                $sub = arraysubs_get_subscription( $sid );
                if ( ! $sub || $uid !== absint( get_post_meta( $sid, '_customer_id', true ) ) ||
                    self::product( 'monthly' ) !== absint( get_post_meta( $sid, '_product_id', true ) ) ) { continue; }
                if ( function_exists( 'arraysubs_is_waiting_cancellation' ) && arraysubs_is_waiting_cancellation( $sid ) ) {
                    $controls .= '<p>Automatic renewal is cancelled. Access ends after the already-paid period.</p>'; continue;
                }
                $controls .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                $controls .= '<input type="hidden" name="action" value="tc_woo_cancel_monthly">';
                $controls .= '<input type="hidden" name="tc_subscription_id" value="' . esc_attr( (string) $sid ) . '">';
                $controls .= wp_nonce_field( 'tc_woo_cancel_monthly', '_wpnonce', true, false );
                $controls .= '<button type="submit" class="tc-button tc-button--ghost" onclick="return confirm(&quot;Cancel future monthly renewals? Your paid access continues until its expiry date.&quot;)">Cancel monthly subscription</button></form>';
            }
            $html .= $controls;
        } catch ( \Throwable $e ) {
            $html .= '<p role="status">Subscription controls are temporarily unavailable. Your subscription may still renew. Please contact support.</p>';
        }
        $html .= '<p>Manage your VIP access here. Payments and renewal history remain securely stored by the payment provider and WooCommerce. If your role is missing, use Refresh Roles above or contact support.</p></section>';
        return $html;
    }
    public static function admin(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not authorized.' ); }
        $c = self::config();
        echo '<div class="wrap"><h1>TimonConfigs — WooCommerce + Discord</h1><p>WooCommerce, Stripe Gateway and ArraySubs payment gateways handle charges and renewals. TimonConfigs handles Discord identity and roles ONLY. The old Stripe/PayPal checkout and webhooks are disabled in this plugin.</p>';
        echo '<p><strong>WooCommerce:</strong> ' . esc_html( self::woo_loaded() ? 'installed' : 'missing' ) . ' | <strong>ArraySubs Free:</strong> ' . esc_html( self::subscriptions_loaded() ? 'installed' : 'missing' ) . ' | <strong>Discord bot:</strong> ' . esc_html( self::roles_configured() ? 'configured' : 'missing or invalid roles/token' ) . '</p>';
        echo '<p><strong>Product validation:</strong> ' . esc_html( self::valid_products() ? 'OK — ArraySubs monthly 1 month €29.99 without trial + Lifetime €80, virtual products, EUR' : 'not ready'  ) . '</p>';
        $issues = self::product_issues();
        if ( $issues ) {
            echo '<div class="notice notice-warning"><p><strong>Product validation details:</strong></p><ul>';
            foreach ( $issues as $issue ) { echo '<li>' . esc_html( $issue ) . '</li>'; }
            echo '</ul></div>';
        }
        if ( ! class_exists( 'TC_Discord' ) || ! TC_Discord::is_ready() ) {
            echo '<p><strong>Discord OAuth:</strong> not ready — check Discord membership settings.</p>';
        }
        echo '<p><strong>ArraySubs version:</strong> ' . esc_html( defined( 'ARRAYSUBS_VERSION' ) ? ARRAYSUBS_VERSION : 'not defined' ) . ' | <strong>Boot:</strong> ' . esc_html( did_action( 'arraysubs_after_boot' ) ? 'complete' : 'not complete' ) . '</p>';
        echo '<p><strong>ArraySubs API:</strong> ' . esc_html( self::subscriptions_loaded() ? 'Developer API available' : 'Developer API not loaded' ) . '</p>';
        echo '<p><strong>Checkout:</strong> ' . esc_html( self::checkout_ready() ? 'enabled' : 'not available yet' ) . '</p>';
        // Display read-only merchant guidance; never expose Stripe API or webhook secrets.
        $stripe_admin = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' );
        $webhook_url = home_url( '/?wc-api=wc_stripe' );
        $tax_based_on = (string) get_option( 'woocommerce_tax_based_on', 'shipping' );
        $tax_enabled = 'yes' === get_option( 'woocommerce_calc_taxes', 'no' );
        echo '<div class="notice notice-info" style="padding:10px 14px;max-width:820px"><h3>Stripe wallets & webhook diagnostics</h3>';
        echo '<p><strong>Official Stripe webhook endpoint:</strong> <code>' . esc_html( $webhook_url ) . '</code></p>';
        echo '<p>Webhook status cannot be confirmed from TimonConfigs. Verify <strong>Live</strong> and <strong>Test</strong> as <strong>Configured</strong> in <a href="' . esc_url( $stripe_admin ) . '">WooCommerce &rarr; Stripe &rarr; Settings &rarr; Account details &rarr; Configure connection</a>. Use the plugin\'s Reconfigure webhooks action if necessary; do not create a second TimonConfigs webhook.</p>';
        echo '<p>Apple Pay and Google Pay are shown only on compatible devices with configured wallets. Enable their combined Express checkout method and Checkout placement in the official Stripe plugin.</p>';
        if ( $tax_enabled && 'billing' === $tax_based_on ) {
            echo '<p><strong>Possible wallet blocker:</strong> WooCommerce taxes are calculated from the customer billing address and VIP products are virtual. The official Stripe extension may suppress Apple Pay and Google Pay to avoid inaccurate taxes. Do not override this protection without testing taxes.</p>';
        }
        echo '</div>';
        echo '<form action="options.php" method="post">'; settings_fields( 'tc_woo' );
        foreach ( array( 'monthly_product' => 'WooCommerce VIP Monthly (Simple product + Subscription [AS]) product ID', 'lifetime_product' => 'WooCommerce VIP Lifetime (Simple Product) product ID' ) as $key => $label ) {
            echo '<p><label>' . esc_html( $label ) . ' <input type="number" name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" min="0" value="' . esc_attr( (string) $c[ $key ] ) . '"></label></p>';
        }
        echo '<p><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[public_login]" value="1" ' . checked( ! empty( $c['public_login'] ), true, false ) . '> Allow public Discord OAuth login independently of old billing</label></p>';
        echo '<p><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[enabled]" value="1" ' . checked( ! empty( $c['enabled'] ), true, false ) . '> Enable NEW WooCommerce VIP checkout (only after tests)</label></p>';
        submit_button( 'Save WooCommerce mapping' ); echo '</form>';
        echo '<p>Bot token: add <code>define(\'TIMONCONFIGS_WOO_DISCORD_BOT_TOKEN\', \'YOUR_TOKEN\');</code> privately to wp-config.php. Existing live token constant is temporarily supported. Never put secrets in GitHub.</p>';
        echo '<p>Use the existing Discord membership settings for Guild, Monthly and Lifetime role IDs. Keep the bot role above VIP roles with Manage Roles permission. The role remover never deletes unowned/external roles.</p>';
        echo '<p>Configure guest checkout OFF in WooCommerce. For recurring PayPal use PayPal (ArraySubs). For recurring Stripe use WooCommerce Stripe Gateway with the ArraySubs Config tab. Keep Action Scheduler / WP-Cron running.</p>';
        echo '<p><strong>Last Woo reconciliation:</strong> ' . esc_html( wp_next_scheduled( 'tc_woo_reconcile' ) ? wp_date( 'Y-m-d H:i:s T', wp_next_scheduled( 'tc_woo_reconcile' ) ) : 'not scheduled yet' ) . '</p></div>';
    }
}
