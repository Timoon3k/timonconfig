<?php
/** Premium checkout shell for the WooCommerce checkout page; payment logic stays with WooCommerce. */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="tc-vip-checkout" aria-label="Secure VIP checkout">
    <div class="tc-shell tc-vip-checkout__shell">
        <div class="tc-vip-checkout__heading">
            <a class="tc-vip-checkout__back" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">&larr; Back to VIP plans</a>
            <div class="tc-vip-checkout__eyebrow"><span></span> TIMONCONFIGS / SECURE CHECKOUT</div>
            <h1>Activate your <em>VIP access.</em></h1>
            <p>One secure checkout. Discord access after your payment is verified. Billing details may be required by the provider.</p>
            <div class="tc-vip-checkout__benefits" aria-label="VIP checkout details">
                <span><strong>&#10003;</strong> Discord-linked account</span>
                <span><strong>&#10003;</strong> Secure Stripe &amp; PayPal payments</span>
                <span><strong>&#10003;</strong> Digital delivery</span>
            </div>
        </div>
        <?php if ( function_exists( 'WC' ) && function_exists( 'do_shortcode' ) ) : ?>
            <div class="tc-vip-checkout__form"><?php echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php else : ?>
            <div class="tc-vip-checkout__unavailable" role="alert">Checkout is currently unavailable. Please try again later.</div>
        <?php endif; ?>
        <div class="tc-vip-checkout__trust">Your purchase is processed by Stripe or PayPal. TimonConfigs never stores your card details. Monthly VIP renews automatically unless cancelled; Lifetime is a one-time payment.</div>
    </div>
</main>
<?php get_footer(); ?>
