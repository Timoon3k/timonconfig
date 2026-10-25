<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<div class="tc-shell"><ul class="tc-assurance-strip" aria-label="Membership highlights">
        <li><?php echo timonconfigs_icon( 'users' ); // phpcs:ignore ?><div><strong>Cronus Zen</strong><span>CONTROLLER CONFIGS</span></div></li>
        <li><?php echo timonconfigs_icon( 'crosshair' ); // phpcs:ignore ?><div><strong>XIM Matrix</strong><span>CONTROLLER & MNK</span></div></li>
        <li><?php echo timonconfigs_icon( 'download' ); // phpcs:ignore ?><div><strong>Free + VIP</strong><span>CHOOSE YOUR ACCESS</span></div></li>
        <li><?php echo timonconfigs_icon( 'shield' ); // phpcs:ignore ?><div><strong><?php echo timonconfigs_direct_billing() ? 'Secure payment' : 'Upgrade.chat'; ?></strong><span>OFFICIAL CHECKOUT</span></div></li>
    </ul></div>
