<?php
/** Editable Home section. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$discord_url = timonconfigs_community_url();
$hero_target = timonconfigs_collection_target( (string) ( $settings['primaryUrl'] ?? get_theme_mod( 'timonconfigs_apex_cta_url', timonconfigs_collection_url( 'apex-legends', 'xim-matrix' ) ) ) );
$hero_media = function_exists( 'timonconfigs_hero_media' ) ? timonconfigs_hero_media( $settings ) : array(
    'src' => $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/apex-legends-crimson-hero.webp' ),
    'alt' => $settings['imageAlt'] ?? '', 'width' => 1536, 'height' => 1024, 'srcset' => '', 'sizes' => '100vw',
);
$motion_enabled = array_key_exists( 'motion', $settings ) ? (bool) $settings['motion'] : (bool) get_theme_mod( 'timonconfigs_hero_motion', true );
?>
<section class="tc-hero tc-hero--crimson" aria-labelledby="hero-title" data-hero-atmosphere data-motion-enabled="<?php echo $motion_enabled ? '1' : '0'; ?>" data-motion-state="paused">
        <div class="tc-hero__visual">
            <picture class="tc-hero__picture">
                <img class="tc-hero__art" src="<?php echo esc_url( $hero_media['src'] ); ?>"<?php if ( ! empty( $hero_media['srcset'] ) ) : ?> srcset="<?php echo esc_attr( $hero_media['srcset'] ); ?>" sizes="<?php echo esc_attr( $hero_media['sizes'] ?? '100vw' ); ?>"<?php endif; ?> alt="<?php echo esc_attr( $hero_media['alt'] ); ?>"<?php if ( ! empty( $hero_media['width'] ) && ! empty( $hero_media['height'] ) ) : ?> width="<?php echo esc_attr( (string) $hero_media['width'] ); ?>" height="<?php echo esc_attr( (string) $hero_media['height'] ); ?>"<?php endif; ?> fetchpriority="high" decoding="async">
            </picture>
            <div class="tc-hero__energy" aria-hidden="true"><span class="tc-hero__haze"></span><span class="tc-hero__spark tc-hero__spark--one"></span><span class="tc-hero__spark tc-hero__spark--two"></span><span class="tc-hero__spark tc-hero__spark--three"></span></div>
        </div>
        <div class="tc-shell tc-hero__inner"><div class="tc-hero__content">
            <p class="tc-eyebrow"><?php echo esc_html( $settings['eyebrow'] ?? get_theme_mod( 'timonconfigs_hero_eyebrow', 'PRECISION × CONSISTENCY × REAL SETUPS' ) ); ?></p>
            <h1 id="hero-title"><?php echo esc_html( $settings['title'] ?? get_theme_mod( 'timonconfigs_hero_title', 'XIM Matrix & Cronus Zen Configs for Apex Legends' ) ); ?></h1>
            <p class="tc-hero__tagline"><?php echo esc_html( $settings['tagline'] ?? get_theme_mod( 'timonconfigs_hero_tagline', 'Play Smarter. Hit Different.' ) ); ?></p>
            <p class="tc-hero__description"><?php echo esc_html( $settings['description'] ?? get_theme_mod( 'timonconfigs_hero_copy', 'Explore Apex Legends configurations for XIM Matrix and Cronus Zen, including controller and mouse & keyboard setups. Check release-specific compatibility, setup guidance, and Free or VIP access.' ) ); ?></p>
            <div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( $hero_target ); ?>"><?php echo esc_html( $settings['primaryLabel'] ?? get_theme_mod( 'timonconfigs_apex_cta', 'Explore XIM Matrix Apex Configs' ) ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php if ( $discord_url ) : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $discord_url ); ?>"><?php echo timonconfigs_icon( 'discord' ); // phpcs:ignore ?>Join Discord</a><?php else : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Explore Configs</a><?php endif; ?></div>
            <ul class="tc-trust"><li><?php echo timonconfigs_icon( 'bolt' ); // phpcs:ignore ?><?php echo esc_html( get_theme_mod( 'timonconfigs_trust_access', 'Discord Member Access' ) ); ?></li><li><?php echo timonconfigs_icon( 'refresh' ); // phpcs:ignore ?><?php echo esc_html( get_theme_mod( 'timonconfigs_trust_updates', 'Regular Updates' ) ); ?></li><li><?php echo timonconfigs_icon( 'shield' ); // phpcs:ignore ?><?php echo esc_html( get_theme_mod( 'timonconfigs_trust_support', 'Active Support' ) ); ?></li></ul>
        </div></div>
        <?php if ( $motion_enabled ) : ?><button class="tc-hero__motion-toggle" type="button" data-hero-motion-toggle aria-pressed="false" aria-label="Pause background animation" hidden><svg aria-hidden="true" viewBox="0 0 16 16" fill="none"><path d="M5 3v10M11 3v10" stroke="currentColor" stroke-width="2"/></svg><span data-motion-label>Pause animation</span></button><?php endif; ?>
    </section>
