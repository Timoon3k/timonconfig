<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-section tc-home-pricing" id="pricing"><div class="tc-shell tc-pricing-layout"><div class="tc-heading-marker"><p class="tc-eyebrow">PRICING</p><h2><?php echo isset( $settings['title'] ) ? esc_html( $settings['title'] ) : 'Choose Your Plan'; ?></h2><p>Simple pricing.<br>Maximum performance.</p><a class="tc-text-link" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Compare plans <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div><?php get_template_part( 'template-parts/pricing-cards' ); ?></div></section>
