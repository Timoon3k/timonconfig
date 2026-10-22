<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-section tc-home-reviews" id="reviews"><div class="tc-shell"><div class="tc-section-heading"><div class="tc-heading-marker"><p class="tc-eyebrow">REVIEWS</p><h2><?php echo isset( $settings['title'] ) ? esc_html( $settings['title'] ) : 'What Our Clients Say'; ?></h2></div></div><?php get_template_part( 'template-parts/review-cards' ); ?></div></section>
