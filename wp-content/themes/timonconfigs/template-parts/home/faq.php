<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-section tc-home-faq"><div class="tc-shell tc-faq-layout"><div class="tc-heading-marker"><p class="tc-eyebrow">FAQ</p><h2><?php echo isset( $settings['title'] ) ? esc_html( $settings['title'] ) : 'Frequently<br>Asked Questions'; ?></h2><a class="tc-text-link" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">View all questions <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div><div class="tc-faq-list"><?php timonconfigs_render_faqs( 5 ); ?></div></div></section>
