<?php
/**
 * TimonConfigs VIP checkout: server-verified reCAPTCHA v2 with WooCommerce-session proof.
 * Protects classic checkout and WooCommerce Store API / wallet checkout.
 */
defined( 'ABSPATH' ) || exit;

final class TC_Woo_Captcha {
    private const SITE_KEY = 'TIMONCONFIGS_RECAPTCHA_V2_SITE_KEY';
    private const SECRET_KEY = 'TIMONCONFIGS_RECAPTCHA_V2_SECRET_KEY';
    private const SESSION_KEY = 'tc_woo_recaptcha_verified';
    private const TTL = 600;

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'woocommerce_checkout_before_customer_details', array( __CLASS__, 'render' ), 7 );
        add_action( 'wc_ajax_tc_recaptcha_verify', array( __CLASS__, 'verify_ajax' ) );
        add_action( 'woocommerce_checkout_process', array( __CLASS__, 'require_classic' ), 5 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'require_store' ), 5, 1 );
    }

    private static function key( string $name ): string {
        return defined( $name ) && is_string( constant( $name ) ) ? trim( constant( $name ) ) : '';
    }

    public static function configured(): bool {
        return '' !== self::key( self::SITE_KEY ) && '' !== self::key( self::SECRET_KEY );
    }

    private static function vip_cart(): bool {
        if ( ! is_user_logged_in() || ! class_exists( 'TC_Woo_Bridge' ) ||
             ! function_exists( 'WC' ) || ! WC()->cart ) { return false; }
        $c = TC_Woo_Bridge::config();
        $vip = array_filter( array( absint( $c['monthly_product'] ?? 0 ), absint( $c['lifetime_product'] ?? 0 ) ) );
        if ( ! $vip || ! WC()->cart->get_cart() ) { return false; }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! in_array( absint( $item['product_id'] ?? 0 ), $vip, true ) ) { return false; }
        }
        return true;
    }

    private static function vip_order( $order ): bool {
        if ( ! $order || ! method_exists( $order, 'get_items' ) || ! class_exists( 'TC_Woo_Bridge' ) ) { return false; }
        $c = TC_Woo_Bridge::config();
        $ids = array_filter( array( absint( $c['monthly_product'] ?? 0 ), absint( $c['lifetime_product'] ?? 0 ) ) );
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( in_array( absint( $item->get_product_id() ), $ids, true ) ) { return true; }
        }
        return false;
    }

    public static function enqueue(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! self::vip_cart() || ! self::configured() ) { return; }
        $url = add_query_arg( 'wc-ajax', 'tc_recaptcha_verify', home_url( '/' ) );
        $site = self::key( self::SITE_KEY );
        $nonce = wp_create_nonce( 'tc_woo_captcha' );
        $js = 'window.tcCaptchaReady=function(){var box=document.getElementById("tc-woo-recaptcha");if(!box||!window.grecaptcha)return;grecaptcha.render(box,{sitekey:' .
              wp_json_encode( $site ) . ',callback:function(token){var f=new FormData();f.append("token",token);f.append("_wpnonce",' . wp_json_encode( $nonce ) . ');fetch(' . wp_json_encode( $url ) . ',{method:"POST",credentials:"same-origin",body:f}).then(function(r){return r.json()}).then(function(j){var t=document.getElementById("tc-woo-captcha-state");if(t)t.textContent=j.success?"Verification completed. You can continue with card, Apple Pay, Google Pay or PayPal.":"Verification failed. Please try again.";}).catch(function(){var t=document.getElementById("tc-woo-captcha-state");if(t)t.textContent="Verification failed. Please try again."});},"expired-callback":function(){var t=document.getElementById("tc-woo-captcha-state");if(t)t.textContent="Verification expired. Complete the checkbox again.";}});};';
        wp_enqueue_script( 'tc-woo-recaptcha-v2', 'https://www.google.com/recaptcha/api.js?onload=tcCaptchaReady&render=explicit', array(), null, true );
        wp_add_inline_script( 'tc-woo-recaptcha-v2', $js, 'before' );
    }

    public static function render(): void {
        if ( ! self::vip_cart() ) { return; }
        echo '<div class="tc-woo-checkout-captcha" style="margin:20px 0;padding:15px;border:1px solid #34435a;border-radius:10px">';
        echo '<strong>Security verification</strong><p>Complete reCAPTCHA before paying, including Apple Pay or Google Pay.</p>';
        if ( self::configured() ) { echo '<div id="tc-woo-recaptcha"></div><p id="tc-woo-captcha-state" role="status">Verification required before payment.</p>'; }
        else { echo '<p role="alert">Checkout verification is not configured. Please contact support; no payment can start.</p>'; }
        echo '</div>';
    }

    private static function verified(): bool {
        if ( ! self::configured() || ! function_exists( 'WC' ) || ! WC()->session ) { return false; }
        $v = WC()->session->get( self::SESSION_KEY );
        return is_array( $v ) && absint( $v['user'] ?? 0 ) === get_current_user_id()
            && (int) ( $v['at'] ?? 0 ) > time() - self::TTL;
    }

    public static function verify_ajax(): void {
        if ( ! is_user_logged_in() || ! self::vip_cart() ||
             ! check_ajax_referer( 'tc_woo_captcha', '_wpnonce', false ) ||
             ! self::configured() || ! function_exists( 'WC' ) || ! WC()->session ) {
            wp_send_json_error( array( 'message' => 'Verification unavailable.' ), 403 );
        }
        WC()->session->set( self::SESSION_KEY, null ); // No stale proof from earlier attempts.
        $token = isset( $_POST['token'] ) && is_string( $_POST['token'] )
            ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        if ( ! $token || strlen( $token ) > 4096 ) {
            wp_send_json_error( array( 'message' => 'Verification required.' ), 400 );
        }
        $res = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'timeout' => 10,
            'redirection' => 0,
            'body' => array( 'secret' => self::key( self::SECRET_KEY ), 'response' => $token ),
        ) );
        if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
            wp_send_json_error( array( 'message' => 'Verification service unavailable.' ), 503 );
        }
        $reply = json_decode( (string) wp_remote_retrieve_body( $res ), true );
        $hostname = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        if ( ! is_array( $reply ) || empty( $reply['success'] ) ||
             ! is_string( $reply['hostname'] ?? null ) || ! hash_equals( (string) $hostname, $reply['hostname'] ) ) {
            wp_send_json_error( array( 'message' => 'Verification failed.' ), 403 );
        }
        WC()->session->set( self::SESSION_KEY, array( 'user' => get_current_user_id(), 'at' => time() ) );
        wp_send_json_success( array( 'message' => 'Verified.' ) );
    }

    public static function require_classic(): void {
        if ( ! self::vip_cart() ) { return; }
        if ( ! self::verified() ) {
            wc_add_notice( 'Please complete the security verification before checkout.', 'error' );
        }
    }

    public static function require_store( $order ): void {
        if ( ! self::vip_order( $order ) ) { return; }
        if ( self::verified() ) { return; }
        // Store API / Express wallet must fail closed, too; a visual badge alone is not security.
        if ( class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException' ) ) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'tc_woo_captcha_required',
                'Complete reCAPTCHA on the TimonConfigs checkout before paying.',
                403
            );
        }
        throw new \RuntimeException( 'VIP checkout reCAPTCHA is required.' );
    }
}
