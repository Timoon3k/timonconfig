<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_image = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
?>
<section class="tc-hero" aria-labelledby="hero-title">
        <img class="tc-hero__art" src="<?php echo esc_url( $hero_image ); ?>" alt="" width="2038" height="771" fetchpriority="high" decoding="async">
        <div class="tc-shell tc-hero__inner"><div class="tc-hero__content">
            <p class="tc-eyebrow"><?php echo esc_html( $settings['eyebrow'] ?? get_theme_mod( 'timonconfigs_hero_eyebrow', 'PRECISION × CONSISTENCY × REAL SETUPS' ) ); ?></p>
            <h1 id="hero-title"><?php echo esc_html( $settings['title'] ?? get_theme_mod( 'timonconfigs_hero_title', 'Elite Configs for Serious Players' ) ); ?></h1>
            <p class="tc-hero__tagline"><?php echo esc_html( $settings['tagline'] ?? get_theme_mod( 'timonconfigs_hero_tagline', 'Play Smarter. Hit Different.' ) ); ?></p>
            <p class="tc-hero__description"><?php echo esc_html( $settings['description'] ?? get_theme_mod( 'timonconfigs_hero_copy', 'Professional configs for Cronus Zen and XIM Matrix. Find the right setup, explore real gameplay, and join the TimonConfigs community.' ) ); ?></p>
            <div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( $settings['primaryUrl'] ?? get_theme_mod( 'timonconfigs_hero_cta_url', home_url( '/pricing/' ) ) ); ?>"><?php echo esc_html( $settings['primaryLabel'] ?? get_theme_mod( 'timonconfigs_hero_cta', 'Get Started' ) ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php if ( $discord_url ) : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $discord_url ); ?>"><?php echo timonconfigs_icon( 'discord' ); // phpcs:ignore ?>Join Discord</a><?php else : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Explore Configs</a><?php endif; ?></div>
            <ul class="tc-trust"><li><?php echo timonconfigs_icon( 'bolt' ); // phpcs:ignore ?><?php echo esc_html( get_theme_mod( 'timonconfigs_trust_access', 'Instant Access' ) ); ?></li><li><?php echo timonconfigs_icon( 'refresh' ); // phpcs:ignore ?><?php echo esc_html( get_theme_mod( 'timonconfigs_trust_updates', 'Regular Updates' ) ); ?></li><li><?php echo timonconfigs_icon( 'shield' ); // phpcs:ignore ?><?php echo esc_html( get_theme_mod( 'timonconfigs_trust_support', 'Active Support' ) ); ?></li></ul>
        </div></div>
    </section>
