<?php
/**
 * Minimal, English-only, Discord-first frontend for digital VIP checkouts.
 * WooCommerce remains the authoritative payment, tax and order processor.
 * Never fabricate billing address values or intercept gateway data.
 */
defined( 'ABSPATH' ) || exit;

final class TC_Woo_Checkout_Presentation {
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'ajax_checkout_locale' ), 0 );
        add_action( 'wp', array( __CLASS__, 'checkout_locale' ), 5 );
        add_action( 'template_redirect', array( __CLASS__, 'require_discord' ), 8 );
        add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'minimal_fields' ), 50 );
        add_filter( 'woocommerce_checkout_registration_enabled', '__return_false' );
        add_filter( 'pre_option_woocommerce_enable_guest_checkout', array( __CLASS__, 'disable_guest_checkout' ) );
        add_action( 'wp', array( __CLASS__, 'hide_woocommerce_login' ), 20 );
        add_filter( 'woocommerce_cart_item_thumbnail', array( __CLASS__, 'remove_checkout_thumbnail' ), 10, 3 );
        add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'plan_gateways' ), 999 );
        add_filter( 'woocommerce_gateway_title', array( __CLASS__, 'gateway_title' ), 10, 2 );
        add_filter( 'woocommerce_order_button_text', array( __CLASS__, 'order_button_text' ) );
    }

    private static function checkout_page(): bool {
        if ( ! function_exists( 'wc_get_page_id' ) || ! function_exists( 'is_page' ) ) { return false; }
        $id = wc_get_page_id( 'checkout' );
        return $id > 0 && is_page( $id );
    }

    /** Only this site's digital VIP membership checkout is simplified. */
    private static function vip_cart(): bool {
        if ( ! function_exists( 'WC' ) || ! WC()->cart || ! class_exists( 'TC_Woo_Bridge' ) ) { return false; }
        $config = TC_Woo_Bridge::config();
        $ids = array_filter( array( absint( $config['monthly_product'] ?? 0 ), absint( $config['lifetime_product'] ?? 0 ) ) );
        if ( ! $ids || ! WC()->cart->get_cart() || WC()->cart->needs_shipping() ) { return false; }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! in_array( absint( $item['product_id'] ?? 0 ), $ids, true ) ) { return false; }
        }
        return true;
    }

    /** WC-AJAX does not have a queried page: apply English before validation starts. */
    private static function checkout_ajax(): bool {
        if ( ! isset( $_GET['wc-ajax'] ) ) { return false; }
        $endpoint = sanitize_key( wp_unslash( (string) $_GET['wc-ajax'] ) );
        return in_array( $endpoint, array( 'checkout', 'update_order_review' ), true );
    }

    public static function ajax_checkout_locale(): void {
        if ( self::checkout_ajax() && function_exists( 'switch_to_locale' ) && 'en_US' !== determine_locale() ) {
            switch_to_locale( 'en_US' );
        }
    }

    public static function order_button_text( $text ) {
        return self::checkout_page() && self::vip_cart() ? 'Pay securely' : $text;
    }

    /** Force English labels without changing the admin dashboard's language. */
    public static function checkout_locale(): void {
        if ( self::checkout_page() && function_exists( 'switch_to_locale' ) && 'en_US' !== determine_locale() ) {
            switch_to_locale( 'en_US' );
        }
    }

    public static function require_discord(): void {
        if ( ! self::checkout_page() && ! ( function_exists( 'is_account_page' ) && is_account_page() ) ) { return; }
        if ( is_user_logged_in() || ! class_exists( 'TC_Discord' ) || ! TC_Discord::is_ready() ) { return; }
        if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) ) { return; }
        nocache_headers();
        // Discord owns the login flow; the OAuth callback returns to /account/.
        // Preserve the selected VIP product through the OAuth round trip when possible.
        $target = '';
        if ( self::checkout_page() && self::vip_cart() && function_exists( 'WC' ) ) {
            $config = TC_Woo_Bridge::config();
            $first = reset( WC()->cart->get_cart() );
            $id = absint( $first['product_id'] ?? 0 );
            if ( $id && $id === absint( $config['monthly_product'] ?? 0 ) ) { $target = home_url( '/checkout/monthly/' ); }
            if ( $id && $id === absint( $config['lifetime_product'] ?? 0 ) ) { $target = home_url( '/checkout/lifetime/' ); }
        }
        wp_safe_redirect( TC_Discord::login_url( $target ), 302 );
        exit;
    }

    public static function hide_woocommerce_login(): void {
        if ( self::checkout_page() && function_exists( 'woocommerce_checkout_login_form' ) ) {
            remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
        }
    }

    public static function disable_guest_checkout( $enabled ) {
        return self::vip_cart() ? 'no' : $enabled;
    }

    public static function minimal_fields( array $fields ): array {
        // Same fields for checkout HTML and wc-ajax=checkout; don't remove required billing fields.
        if ( ! self::vip_cart() || ( ! self::checkout_page() && ! self::checkout_ajax() ) ) { return $fields; }
        $allowed = array( 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_country',
            'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode' );
        foreach ( array_keys( $fields['billing'] ?? array() ) as $key ) {
            if ( ! in_array( $key, $allowed, true ) ) { unset( $fields['billing'][ $key ] ); }
        }
        $labels = array(
            'billing_first_name' => array( 'First name', 'First name' ),
            'billing_last_name' => array( 'Last name', 'Last name' ),
            'billing_email' => array( 'Email for your receipt', 'you@example.com' ),
            'billing_country' => array( 'Country / Region', '' ),
            'billing_address_1' => array( 'Street address', 'Street and house number' ),
            'billing_city' => array( 'City', 'City' ),
            'billing_state' => array( 'State / Province', '' ),
            'billing_postcode' => array( 'ZIP / Postal code', 'ZIP / Postal code' ),
        );
        $priority = 10;
        foreach ( $labels as $key => $label ) {
            if ( ! isset( $fields['billing'][ $key ] ) ) { continue; }
            $fields['billing'][ $key ]['label'] = $label[0];
            $fields['billing'][ $key ]['placeholder'] = $label[1];
            $fields['billing'][ $key ]['class'] = array( 'form-row-wide' );
            $fields['billing'][ $key ]['priority'] = $priority;
            $priority += 10;
            // Keep required flags and country locale rules owned by WooCommerce.
        }
        $fields['shipping'] = array();
        $fields['account'] = array();
        unset( $fields['order']['order_comments'] );
        return $fields;
    }

    /** A single PayPal method per plan: official PayPal cannot automatically renew ArraySubs. */
    public static function plan_gateways( array $gateways ): array {
        if ( ! self::vip_cart() ) { return $gateways; }
        $config = TC_Woo_Bridge::config();
        $monthly = absint( $config['monthly_product'] ?? 0 );
        $lifetime = absint( $config['lifetime_product'] ?? 0 );
        $items = WC()->cart->get_cart();
        $pids = array_unique( array_map( static fn( $item ): int => absint( $item['product_id'] ?? 0 ), $items ) );
        if ( 1 !== count( $pids ) ) { return $gateways; }
        if ( $monthly && $monthly === reset( $pids ) ) {
            // Do not offer the official one-off PayPal on recurring orders.
            foreach ( array( 'ppcp-gateway', 'ppcp-credit-card-gateway', 'ppcp-pay-later', 'paypal' ) as $key ) {
                unset( $gateways[ $key ] );
            }
        } elseif ( $lifetime && $lifetime === reset( $pids ) ) {
            unset( $gateways['arraysubs_paypal'] );
        }
        return $gateways;
    }

    public static function gateway_title( $title, $gateway_id ) {
        if ( ! self::checkout_page() || ! self::vip_cart() ) { return $title; }
        if ( 'arraysubs_paypal' === $gateway_id ) { return 'PayPal — Automatic monthly subscription'; }
        if ( 'ppcp-gateway' === $gateway_id ) { return 'PayPal — One-time payment'; }
        return $title;
    }

    public static function remove_checkout_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
        return self::checkout_page() && self::vip_cart() ? '' : $thumbnail;
    }
}
