<?php
/**
 * Title: Premium call to action
 * Slug: timonconfigs/premium-cta
 * Categories: call-to-action
 * Description: A dark TimonConfigs call to action with two links.
 */
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"48px","right":"48px","bottom":"48px","left":"48px"}},"border":{"radius":"16px","color":"#252c36"},"color":{"background":"#0e1218"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide has-border-color has-background" style="border-color:#252c36;border-radius:16px;background-color:#0e1218;padding-top:48px;padding-right:48px;padding-bottom:48px;padding-left:48px"><!-- wp:heading --><h2 class="wp-block-heading">Ready to improve your setup?</h2><!-- /wp:heading --><!-- wp:paragraph {"textColor":"contrast"} --><p class="has-contrast-color has-text-color">Compare current configs and choose the access level that fits you.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Compare plans</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Browse configs</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div>
<!-- /wp:group -->
