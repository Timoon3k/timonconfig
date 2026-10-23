<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-section tc-supported"><div class="tc-shell"><div class="tc-section-heading"><div class="tc-heading-marker"><p class="tc-eyebrow">GAME COLLECTIONS</p><h2><?php echo isset( $settings['title'] ) ? esc_html( $settings['title'] ) : 'Play Your Favorite Games'; ?></h2></div><a class="tc-text-link" href="<?php echo esc_url( home_url( '/games/' ) ); ?>">COLLECTION ROADMAP <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div><?php get_template_part( 'template-parts/game-strip' ); ?><?php get_template_part( 'template-parts/home/search-intents' ); ?></div></section>
