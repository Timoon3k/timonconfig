<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-section tc-showcase" id="showcase"><div class="tc-shell"><div class="tc-section-heading"><div class="tc-heading-marker"><p class="tc-eyebrow">APEX LEGENDS</p><h2><?php echo isset( $settings['title'] ) ? esc_html( $settings['title'] ) : 'See Our Flagship Configs in Action'; ?></h2></div><a class="tc-text-link" href="<?php echo esc_url( home_url( '/video-showcase/' ) ); ?>">ALL VIDEOS <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div><?php get_template_part( 'template-parts/video-cards' ); ?></div></section>
