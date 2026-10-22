<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$cta_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_cta_image', '' );
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-final-cta tc-home-final"><?php if ( $cta_image ) : ?><img class="tc-final-cta__art" src="<?php echo esc_url( $cta_image ); ?>" alt="" width="2172" height="724" loading="lazy" decoding="async"><?php endif; ?><div class="tc-shell tc-final-cta__inner"><div class="tc-heading-marker"><p class="tc-eyebrow">READY TO IMPROVE</p><h2><?php echo esc_html( $settings['title'] ?? get_theme_mod( 'timonconfigs_final_title', 'Join the TimonConfigs Community' ) ); ?></h2><p><?php echo esc_html( $settings['description'] ?? get_theme_mod( 'timonconfigs_final_copy', 'Get access to premium configs, regular updates, and an active community.' ) ); ?></p></div><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( $settings['primaryUrl'] ?? get_theme_mod( 'timonconfigs_hero_cta_url', home_url( '/pricing/' ) ) ); ?>"><?php echo esc_html( $settings['primaryLabel'] ?? get_theme_mod( 'timonconfigs_hero_cta', 'Get Started' ) ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $discord_url ); ?>"><?php echo timonconfigs_icon( 'discord' ); // phpcs:ignore ?>Join Discord</a></div></div></section>
