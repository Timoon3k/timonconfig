<?php
/** WooCommerce catalogue/product pages in the native TimonConfigs shell. */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="tc-woo-page"><div class="tc-shell tc-entry">
    <?php if ( function_exists( 'woocommerce_content' ) ) { woocommerce_content(); } ?>
</div></main>
<?php get_footer(); ?>
