<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-section tc-why"><div class="tc-shell tc-benefits-layout"><div class="tc-heading-marker"><p class="tc-eyebrow">WHY TIMONCONFIGS</p><h2><?php echo isset( $settings['title'] ) ? esc_html( $settings['title'] ) : 'A Higher<br>Standard'; ?></h2></div><?php foreach ( $settings['items'] ?? array(
        array( 'settings', 'Professionally Tuned Configs', 'Clear setup guidance for your supported device.' ),
        array( 'crosshair', 'Setup with Confidence', 'Device, input, and version details in every listing.' ),
        array( 'refresh', 'Regular Updates', 'Updates covered by your membership or license.' ),
        array( 'users', 'Community & Support', 'Connect with the TimonConfigs Discord community.' ),
        array( 'shield', 'Clear Access Terms', 'Free, Monthly, and Lifetime options with clear terms.' ),
    ) as $benefit ) : ?><article><?php echo timonconfigs_icon( $benefit[0] ); // phpcs:ignore ?><h3><?php echo esc_html( $benefit[1] ); ?></h3><p><?php echo esc_html( $benefit[2] ); ?></p></article><?php endforeach; ?></div></section>
