<?php
/** Two-column TimonConfigs checkout, powered by WooCommerce's ordinary checkout hooks. */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_before_checkout_form', $checkout );
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo esc_html__( 'Please sign in with Discord to continue.', 'timonconfigs' );
    return;
}
?>
<form name="checkout" method="post" class="checkout woocommerce-checkout tc-vip-checkout__grid" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="Secure VIP checkout">
    <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
    <section id="customer_details" class="tc-vip-checkout__customer" aria-label="Account and billing details">
        <div class="tc-vip-checkout__step"><span>01</span><div><h2>Account &amp; contact</h2><p>Signed in with Discord. Enter billing details for secure payment processing.</p></div></div>
        <div class="tc-vip-checkout__connected"><span class="tc-vip-checkout__connected-dot" aria-hidden="true"></span><div><strong>Discord account connected</strong><small>VIP roles are delivered to your linked Discord profile after successful payment.</small></div></div>
        <?php if ( $checkout->get_checkout_fields() ) : ?>
            <?php do_action( 'woocommerce_checkout_billing' ); ?>
        <?php endif; ?>
    </section>
    <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
    <aside class="tc-vip-checkout__summary" aria-label="Order and payment">
        <div class="tc-vip-checkout__step"><span>02</span><div><h2>Review &amp; pay</h2><p>Confirm your membership and choose a payment method.</p></div></div>
        <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
        <h3 id="order_review_heading">Order summary</h3>
        <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
        <div id="order_review" class="woocommerce-checkout-review-order">
            <?php do_action( 'woocommerce_checkout_order_review' ); ?>
        </div>
        <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
        <div class="tc-vip-checkout__safe"><span aria-hidden="true">&#9679;</span> Encrypted payment &middot; Stripe / PayPal</div>
    </aside>
</form>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
