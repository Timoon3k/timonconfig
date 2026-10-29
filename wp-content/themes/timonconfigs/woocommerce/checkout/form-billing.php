<?php
/** Digital-only VIP checkout details; fields are filtered by TimonConfigs Core. */
defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-billing-fields tc-vip-checkout__billing">
    <?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>
    <div class="woocommerce-billing-fields__field-wrapper">
        <?php foreach ( $checkout->get_checkout_fields( 'billing' ) as $key => $field ) : ?>
            <?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
        <?php endforeach; ?>
    </div>
    <?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>
