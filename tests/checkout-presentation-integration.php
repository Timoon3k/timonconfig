<?php
/**
 * Standalone regression tests for the public Discord-first WooCommerce VIP checkout.
 * No payment gateway, credentials, WordPress installation or external requests needed.
 */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );

final class FakeTCCart {
    public array $items = array( array( 'product_id' => 427 ) );
    public function get_cart(): array { return $this->items; }
    public function needs_shipping(): bool { return false; }
}
final class FakeTCWC {
    public FakeTCCart $cart;
    public function __construct() { $this->cart = new FakeTCCart(); }
}
$GLOBALS['tc_wc'] = new FakeTCWC();
$GLOBALS['tc_checkout_page'] = true;
$GLOBALS['tc_filters'] = array();
$GLOBALS['tc_actions'] = array();
function add_filter( $name, $callback, $priority = 10, $args = 1 ): void { $GLOBALS['tc_filters'][$name][] = $callback; }
function add_action( $name, $callback, $priority = 10, $args = 1 ): void { $GLOBALS['tc_actions'][$name][] = $callback; }
function WC(): FakeTCWC { return $GLOBALS['tc_wc']; }
function absint( $v ): int { return abs( (int) $v ); }
function wc_get_page_id( $slug ): int { return 42; }
function is_page( $id ): bool { return ! empty( $GLOBALS['tc_checkout_page'] ) && 42 === $id; }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function get_privacy_policy_url(): string { return 'https://timonconfigs.com/privacy-policy/'; }
function esc_url( $v ): string { return (string) $v; }
class TC_Woo_Bridge {
    public static function config(): array { return array( 'monthly_product' => 427, 'lifetime_product' => 430 ); }
}

require __DIR__ . '/../wp-content/plugins/timonconfigs-core/includes/class-tc-woo-checkout-presentation.php';
function verify( bool $ok, string $test ): void {
    if ( ! $ok ) { fwrite( STDERR, "FAILED: $test\n" ); exit( 1 ); }
    echo "PASS: $test\n";
}
TC_Woo_Checkout_Presentation::init();
verify( empty( $GLOBALS['tc_filters']['gettext'] ) && empty( $GLOBALS['tc_filters']['gettext_with_context'] ), 'Boot never registers global gettext filters' );
verify( TC_Woo_Checkout_Presentation::english_checkout_gettext( 'Polski tekst', 'Checkout', 'woocommerce' ) === 'Polski tekst', 'Translation filter is inert before late init' );
$base = array(
    'billing' => array(
        'billing_email' => array( 'required' => true ),
        'billing_first_name' => array( 'required' => true ),
        'billing_last_name' => array( 'required' => true ),
        'billing_country' => array( 'required' => true, 'class' => array( 'form-row-first', 'address-field', 'update_totals_on_change' ) ),
        'billing_address_1' => array( 'required' => true ),
        'billing_city' => array( 'required' => true ),
        'billing_state' => array( 'required' => false ),
        'billing_postcode' => array( 'required' => true ),
        'billing_company' => array( 'required' => false ),
        'billing_phone' => array( 'required' => false ),
    ),
    'shipping' => array( 'shipping_address_1' => array() ),
    'account' => array( 'account_password' => array() ),
    'order' => array( 'order_comments' => array() ),
);
$visible = TC_Woo_Checkout_Presentation::minimal_fields( $base );
verify( 8 === count( $visible['billing'] ), 'Preserves WC-required address, identity and email fields' );
verify( true === $visible['billing']['billing_first_name']['required'] && true === $visible['billing']['billing_address_1']['required'], 'Does not bypass payment-required billing validation' );
verify( ! isset( $visible['billing']['billing_phone'] ) && ! isset( $visible['billing']['billing_company'] ), 'Removes unnecessary optional contact details only' );
verify( ! $visible['shipping'] && ! $visible['account'], 'No checkout shipping or independent Woo account creation' );
verify( ! isset( $visible['order']['order_comments'] ), 'No unused order notes' );
verify( in_array( 'address-field', $visible['billing']['billing_country']['class'], true ) && in_array( 'update_totals_on_change', $visible['billing']['billing_country']['class'], true ), 'Country refresh and address classes survive checkout styling' );
$custom = $base;
$custom['billing']['billing_tax_id'] = array( 'required' => true );
verify( isset( TC_Woo_Checkout_Presentation::minimal_fields( $custom )['billing']['billing_tax_id'] ), 'Gateway-required custom billing field remains visible' );

$GLOBALS['tc_checkout_page'] = false;
$_GET['wc-ajax'] = array( 'checkout' );
verify( TC_Woo_Checkout_Presentation::minimal_fields( $base ) === $base, 'Malformed AJAX endpoint is ignored without array-to-string warnings' );
$_GET['wc-ajax'] = 'checkout';
$ajax = TC_Woo_Checkout_Presentation::minimal_fields( $base );
verify( $ajax === $visible, 'Checkout AJAX validates exactly the same fields as HTML' );
TC_Woo_Checkout_Presentation::enable_checkout_translations();
verify( count( $GLOBALS['tc_filters']['gettext'] ?? array() ) === 1, 'Gettext filters register once only on checkout' );
verify( TC_Woo_Checkout_Presentation::english_checkout_gettext( 'Zamówienie', 'Checkout', 'woocommerce' ) === 'Checkout', 'WooCommerce notices are English in checkout AJAX' );
verify( TC_Woo_Checkout_Presentation::english_checkout_gettext_context( 'Polskie', 'Email address', 'label', 'woocommerce' ) === 'Email address', 'Woo context translations stay English' );
verify( TC_Woo_Checkout_Presentation::english_checkout_gettext( 'Polskie', 'Example', 'unrelated-plugin' ) === 'Polskie', 'Unrelated plugin strings unaffected' );
verify( str_contains( TC_Woo_Checkout_Presentation::checkout_privacy_notice( 'Polski tekst', 'checkout' ), 'Privacy Policy</a>' ), 'English privacy notice links current policy' );
verify( TC_Woo_Checkout_Presentation::checkout_privacy_notice( 'Other notice', 'account' ) === 'Other notice', 'Noncheckout legal text unchanged' );

verify( TC_Woo_Checkout_Presentation::no_woo_registration( 'yes' ) === 'no', 'No separate Woo registration' );
$account_menu = TC_Woo_Checkout_Presentation::account_menu( array( 'orders' => 'Orders', 'customer-logout' => 'Log out', 'subscriptions' => 'Subscriptions' ) );
verify( ! isset( $account_menu['customer-logout'] ) && isset( $account_menu['orders'] ) && isset( $account_menu['subscriptions'] ), 'No Woo logout menu; receipts and subscriptions remain' );

unset( $_GET['wc-ajax'] );
$GLOBALS['tc_checkout_page'] = true;
verify( TC_Woo_Checkout_Presentation::order_button_text( 'Place order' ) === 'Pay securely', 'English pay button' );

$GLOBALS['tc_checkout_page'] = false;
verify( count( $GLOBALS['tc_filters']['gettext'] ?? array() ) === 1, 'Repeated gettext does not install extra filters' );
verify( TC_Woo_Checkout_Presentation::minimal_fields( $base ) === $base, 'Unrelated WooCommerce pages unaffected' );

$css = (string) file_get_contents( __DIR__ . '/../wp-content/themes/timonconfigs/assets/css/checkout-premium.css' );
verify( str_contains( $css, '#payment div.payment_box fieldset' ), 'Removes only nested card fieldset border' );
verify( str_contains( $css, 'wc-stripe-payment-request-wrapper' ) && ! str_contains( $css, 'wc-stripe-payment-request-button-separator,.tc-vip-checkout #wc-stripe-payment-request-wrapper' ), 'Stripe wallet containers are not hidden' );
echo "Checkout presentation regression tests passed.\n";
