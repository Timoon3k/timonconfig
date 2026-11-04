<?php
/** Disposable localhost-only WooCommerce layout fixture; never deploy as a plugin. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'TC_CHECKOUT_LAYOUT_FIXTURE_TOKEN' ) || ! is_string( $_SERVER['HTTP_X_TC_CHECKOUT_LAYOUT'] ?? null ) || ! hash_equals( TC_CHECKOUT_LAYOUT_FIXTURE_TOKEN, $_SERVER['HTTP_X_TC_CHECKOUT_LAYOUT'] ) || '127.0.0.1:8080' !== ( $_SERVER['HTTP_HOST'] ?? '' ) || 'http://127.0.0.1:8080' !== get_option( 'home' ) ) { return; }
if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && 'update_order_review' !== ( $_GET['wc-ajax'] ?? '' ) ) { wp_die( 'The layout fixture never accepts an order or payment.', '', array( 'response' => 403 ) ); }
if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
add_filter( 'pre_http_request', static fn() => new WP_Error( 'tc_layout_no_network', 'External requests are disabled for the local layout fixture.' ), 1 );
add_filter( 'pre_option_timonconfigs_woo', static fn() => array( 'enabled' => 0, 'public_login' => 0, 'monthly_product' => 98765900, 'lifetime_product' => 98765901 ) );
add_filter( 'pre_option_woocommerce_currency', static fn() => 'EUR' );
add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );
add_filter( 'show_admin_bar', '__return_false' );
add_action( 'init', static function (): void { wp_set_current_user( 1 ); }, 0 );
add_action( 'plugins_loaded', static function (): void {
    if ( ! class_exists( 'WC_Session_Handler' ) || ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
    class TC_Layout_Memory_Session extends WC_Session_Handler {
        public function init() { $this->_customer_id = 'tc-layout-memory'; $this->_session_expiration = time() + 3600; $this->_session_expiring = time() + 3000; }
        public function save_data( $old_session_key = 0 ) {}
        public function set_customer_session_cookie( $set ) {}
        public function destroy_session() { $this->_data = array(); }
    }
    class TC_Layout_Card_Gateway extends WC_Payment_Gateway_CC {
        public function __construct() { $this->id = 'tc_layout_card'; $this->title = 'Card — local layout fixture'; $this->description = ''; $this->enabled = 'yes'; $this->has_fields = true; $this->supports = array( 'products' ); }
        public function payment_fields() {
            echo '<p>Native Woo card-field layout fixture. No provider connection or payment.</p><div class="wc-stripe-upe-element">';
            parent::payment_fields();
            echo '</div>';
        }
        public function process_payment( $order_id ) { throw new RuntimeException( 'Layout fixtures cannot process payments.' ); }
    }
    class TC_Layout_PayPal_Gateway extends WC_Payment_Gateway {
        public function __construct() { $this->id = 'tc_layout_paypal'; $this->title = 'PayPal — local layout fixture'; $this->description = ''; $this->enabled = 'yes'; $this->has_fields = false; $this->supports = array( 'products' ); }
        public function process_payment( $order_id ) { throw new RuntimeException( 'Layout fixtures cannot process payments.' ); }
    }
    add_filter( 'woocommerce_session_handler', static fn() => TC_Layout_Memory_Session::class );
    add_filter( 'woocommerce_payment_gateways', static fn() => array( TC_Layout_Card_Gateway::class, TC_Layout_PayPal_Gateway::class ), 999 );
}, 30 );
add_action( 'wp_loaded', static function (): void {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return; }
    if ( ! WC()->session instanceof TC_Layout_Memory_Session || 'WC_Customer_Data_Store_Session' !== WC()->customer->get_data_store()->get_current_class_name() ) { throw new RuntimeException( 'Layout fixtures require memory-backed session and customer storage.' ); }
    $product = new WC_Product_Simple(); $product->set_id( 98765901 ); $product->set_name( 'VIP Lifetime — local fixture' ); $product->set_status( 'publish' ); $product->set_virtual( true ); $product->set_price( '80.00' ); $product->set_regular_price( '80.00' ); $product->set_tax_status( 'none' );
    WC()->cart->get_cart();
    WC()->cart->set_cart_contents( array( 'tc_layout_synthetic' => array( 'key' => 'tc_layout_synthetic', 'product_id' => 98765901, 'variation_id' => 0, 'variation' => array(), 'quantity' => 1, 'data' => $product, 'data_hash' => wc_get_cart_item_data_hash( $product ) ) ) );
    WC()->customer->set_billing_country( 'US' ); WC()->customer->set_billing_state( 'CA' ); WC()->customer->set_billing_postcode( '92101' );
    WC()->cart->calculate_totals(); WC()->session->set( 'chosen_payment_method', 'tc_layout_card' );
}, 30 );
add_action( 'woocommerce_checkout_before_customer_details', static function (): void {
    $mode = is_string( $_GET['tc_layout_hooks'] ?? null ) ? $_GET['tc_layout_hooks'] : 'stripe';
    if ( 'none' === $mode ) { return; }
    if ( 'empty' === $mode ) { echo '<div id="wc-stripe-express-checkout-element"></div>'; return; }
    echo '<div id="wc-stripe-express-checkout-element"><button type="button" class="button">Express checkout · local layout fixture</button></div>';
    if ( 'all' === $mode ) { echo '<p id="wc-stripe-express-checkout-button-separator">or pay below</p><div class="tc-layout-hook-notice woocommerce-info">Gateway information from the before-customer hook.</div>'; }
} );
add_action( 'woocommerce_checkout_after_customer_details', static function (): void {
    if ( 'all' === ( $_GET['tc_layout_hooks'] ?? '' ) ) { echo '<p class="tc-layout-after-notice">Billing information from the after-customer hook.</p>'; }
} );
