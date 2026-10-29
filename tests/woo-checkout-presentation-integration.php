<?php
/** Actual WooCommerce fields/validation; synthetic in-memory cart, no payment or order writes. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Run against disposable localhost WordPress only.' ); }
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'TC_Woo_Checkout_Presentation' ) ) { throw new RuntimeException( 'Activate WooCommerce and TimonConfigs Core first.' ); }
if ( ! in_array( 'pl_PL', get_available_languages(), true ) ) { throw new RuntimeException( 'Install the pl_PL WordPress language pack to verify the checkout locale regression.' ); }
if ( $GLOBALS['wp_locale_switcher']->is_switched() ) { throw new RuntimeException( 'Run with a fresh WordPress locale stack.' ); }
$page_id = wc_get_page_id( 'checkout' );
if ( $page_id < 1 || ! get_post( $page_id ) ) { throw new RuntimeException( 'Create the ordinary WooCommerce checkout page before this test.' ); }
if ( ! WC()->cart ) { wc_load_cart(); }
WC()->cart->get_cart();
$checks = 0; $network_calls = 0;
$assert = static function ( bool $value, string $message ) use ( &$checks ): void { if ( ! $value ) { throw new RuntimeException( 'Woo checkout presentation: ' . $message ); } ++$checks; };
$old_cart = WC()->cart->get_cart(); $old_get = $_GET; $old_post = $_POST; $old_uri = $_SERVER['REQUEST_URI'] ?? null;
$old_query = $GLOBALS['wp_query']; $old_screen = $GLOBALS['current_screen'] ?? null;
$old_locale = get_locale();
$configuration = static fn(): array => array( 'enabled' => 0, 'public_login' => 0, 'monthly_product' => 98765001, 'lifetime_product' => 98765002 );
$no_network = static function () use ( &$network_calls ): WP_Error { ++$network_calls; return new WP_Error( 'tc_woo_fixture_network', 'No external traffic is permitted during checkout fixtures.' ); };
add_filter( 'pre_option_timonconfigs_woo', $configuration ); add_filter( 'pre_http_request', $no_network, 1, 3 );
$product = new WC_Product_Simple(); $product->set_id( 98765001 ); $product->set_virtual( true ); $product->set_price( '29.99' );
$item = array( 'product_id' => 98765001, 'variation_id' => 0, 'quantity' => 1, 'data' => $product );
WC()->cart->set_cart_contents( array( 'tc_woo_checkout_fixture' => $item ) );
$checkout = WC()->checkout();
$fields_property = new ReflectionProperty( WC_Checkout::class, 'fields' );
$old_fields = $fields_property->getValue( $checkout );
$validate = new ReflectionMethod( WC_Checkout::class, 'validate_posted_data' );
$fixture = array( 'billing_first_name' => 'Synthetic', 'billing_last_name' => 'Buyer', 'billing_email' => 'receipt@example.invalid', 'billing_country' => 'US', 'billing_address_1' => '10 Test Road', 'billing_address_2' => '', 'billing_city' => 'San Diego', 'billing_state' => 'CA', 'billing_postcode' => '92101', 'billing_phone' => '+15555550100', 'shipping_country' => '', 'ship_to_different_address' => false );
try {
    foreach ( array( 'page', 'update_order_review', 'checkout' ) as $request ) {
        restore_current_locale(); $assert( switch_to_locale( 'pl_PL' ), 'The fixture starts in Polish: ' . $request );
        $assert( str_contains( __( '%s is a required field.', 'woocommerce' ), 'wymaganym' ), 'The Polish validation catalog is actually loaded before switching: ' . $request );
        $_GET = array(); $_POST = $fixture; $_SERVER['REQUEST_URI'] = (string) wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH );
        $GLOBALS['wp_query'] = new WP_Query();
        if ( 'page' === $request ) {
            $GLOBALS['wp_query']->is_page = true; $GLOBALS['wp_query']->is_singular = true;
            $GLOBALS['wp_query']->queried_object = get_post( $page_id ); $GLOBALS['wp_query']->queried_object_id = $page_id;
        } elseif ( 'early-page' !== $request ) { $_GET['wc-ajax'] = $request; $_SERVER['REQUEST_URI'] = '/?wc-ajax=' . $request; }
        TC_Woo_Checkout_Presentation::ajax_checkout_locale();
        TC_Woo_Checkout_Presentation::checkout_locale();
        $assert( 'en_US' === get_locale(), 'Checkout page and AJAX switch to English before fields/notices: ' . $request );
        $assert( 'November' === wp_date( 'F', gmmktime( 12, 0, 0, 11, 15, 2026 ) ), 'Checkout dates use English month names: ' . $request );
        $fields_property->setValue( $checkout, null );
        $fields = $checkout->get_checkout_fields();
        foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_country', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode' ) as $key ) {
            $assert( isset( $fields['billing'][ $key ] ) && ! empty( $fields['billing'][ $key ]['required'] ), 'A real required US billing field stays available: ' . $request . '/' . $key );
        }
        $assert( 'Email for your receipt' === $fields['billing']['billing_email']['label'] && 'Country / Region' === $fields['billing']['billing_country']['label'], 'The visible English billing labels match the request: ' . $request );
        $assert( in_array( 'address-field', $fields['billing']['billing_country']['class'], true ) && in_array( 'update_totals_on_change', $fields['billing']['billing_country']['class'], true ), 'Country changes retain the WooCommerce address and total-refresh hooks: ' . $request );
        $assert( in_array( 'postcode', $fields['billing']['billing_postcode']['validate'], true ) && in_array( 'state', $fields['billing']['billing_state']['validate'], true ), 'WooCommerce country validation remains enabled: ' . $request );
        $missing = $fixture;
        foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode' ) as $key ) { $missing[ $key ] = ''; }
        $errors = new WP_Error(); $validate->invokeArgs( $checkout, array( &$missing, &$errors ) );
        foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode' ) as $key ) {
            $message = $errors->get_error_message( $key . '_required' );
            $assert( str_contains( $message, 'is a required field' ) && ! str_contains( $message, 'wymaganym' ), 'Missing fields are genuinely rejected in English: ' . $request . '/' . $key );
        }
        $complete_errors = new WP_Error(); $validate->invokeArgs( $checkout, array( &$fixture, &$complete_errors ) );
        $assert( ! $complete_errors->has_errors(), 'Supplying the visible real billing fields passes WooCommerce validation: ' . $request );
        $bad_postcode = $fixture; $bad_postcode['billing_postcode'] = 'not a ZIP';
        $postcode_errors = new WP_Error(); $validate->invokeArgs( $checkout, array( &$bad_postcode, &$postcode_errors ) );
        $assert( $postcode_errors->has_errors(), 'Invalid postal data remains rejected: ' . $request );
    }
    // Scope: another gateway/cart or an administrative page keeps its own rules.
    $sentinel = array( 'billing' => array( 'custom_required' => array( 'required' => true, 'label' => 'Custom billing evidence' ) ), 'shipping' => array( 'sentinel' => array() ) );
    WC()->cart->set_cart_contents( array( 'unrelated' => array_merge( $item, array( 'product_id' => 98765003 ) ) ) );
    $assert( $sentinel === TC_Woo_Checkout_Presentation::minimal_fields( $sentinel ), 'Non-VIP carts keep the original fields.' );
    WC()->cart->set_cart_contents( array( 'tc_woo_checkout_fixture' => $item ) );
    $_GET = array( 'wc-ajax' => array( 'checkout' ) ); $GLOBALS['wp_query'] = new WP_Query();
    $assert( $sentinel === TC_Woo_Checkout_Presentation::minimal_fields( $sentinel ), 'Malformed AJAX context does not activate checkout field changes.' );
    restore_current_locale(); switch_to_locale( 'pl_PL' ); $_GET = array(); $_SERVER['REQUEST_URI'] = '/unrelated-public-page/';
    TC_Woo_Checkout_Presentation::checkout_locale();
    $assert( 'pl_PL' === get_locale(), 'An unrelated page retains its existing language.' );
    if ( ! class_exists( 'WP_Screen' ) ) { require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php'; }
    $GLOBALS['current_screen'] = WP_Screen::get( 'dashboard' ); $_GET = array();
    $_SERVER['REQUEST_URI'] = '/wp-admin/options-general.php?page=tc-woo';
    TC_Woo_Checkout_Presentation::checkout_locale();
    $assert( 'pl_PL' === get_locale(), 'The administrator dashboard keeps its chosen language.' );
    $assert( $sentinel === TC_Woo_Checkout_Presentation::minimal_fields( $sentinel ), 'Checkout field changes do not run in WordPress administration.' );
    $assert( 0 === $network_calls, 'No provider, Discord or other external request was attempted.' );
    WP_CLI::success( "Woo checkout presentation: $checks assertions; real WooCommerce page/AJAX fields and address validation, no orders or payments created." );
} finally {
    WC()->cart->set_cart_contents( $old_cart ); $fields_property->setValue( $checkout, $old_fields );
    remove_filter( 'pre_option_timonconfigs_woo', $configuration ); remove_filter( 'pre_http_request', $no_network, 1 );
    restore_current_locale();
    $_GET = $old_get; $_POST = $old_post; $GLOBALS['wp_query'] = $old_query;
    if ( null === $old_screen ) { unset( $GLOBALS['current_screen'] ); } else { $GLOBALS['current_screen'] = $old_screen; }
    if ( null === $old_uri ) { unset( $_SERVER['REQUEST_URI'] ); } else { $_SERVER['REQUEST_URI'] = $old_uri; }
    if ( get_locale() !== $old_locale ) { throw new RuntimeException( 'The locale fixture did not restore the initial language.' ); }
}
