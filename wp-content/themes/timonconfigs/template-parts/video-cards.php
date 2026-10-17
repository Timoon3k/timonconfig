<?php
/** Shared inline video cards. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
?>
<div class="tc-video-grid">
<?php for ( $index = 1; $index <= 3; $index++ ) :
    $title = get_theme_mod( 'timonconfigs_video_' . $index . '_title', sprintf( 'Apex Legends Gameplay %02d', $index ) );
    $device = get_theme_mod( 'timonconfigs_video_' . $index . '_device', 'Apex Legends · Gameplay showcase' );
    $video = get_theme_mod( 'timonconfigs_video_' . $index . '_url', get_template_directory_uri() . '/assets/video/apex-demo-0' . $index . '.mp4' );
    $poster = get_theme_mod( 'timonconfigs_video_' . $index . '_poster', get_template_directory_uri() . '/assets/images/apex-demo-0' . $index . '.jpg' );
    ?>
    <article class="tc-video-card">
        <div class="tc-inline-video" data-inline-video>
            <video controls playsinline preload="none" poster="<?php echo esc_url( $poster ); ?>" aria-label="<?php echo esc_attr( $title ); ?>" data-video-player data-video-src="<?php echo esc_url( $video ); ?>"></video>
            <button class="tc-inline-video__play" type="button" data-video-play aria-label="<?php echo esc_attr( sprintf( 'Play %s', $title ) ); ?>"><span class="tc-play"><?php echo timonconfigs_icon( 'play' ); // phpcs:ignore ?></span><span class="tc-inline-video__label">WATCH GAMEPLAY</span></button>
            <noscript><a class="tc-video-fallback" href="<?php echo esc_url( $video ); ?>">Watch <?php echo esc_html( $title ); ?></a></noscript>
        </div>
        <div class="tc-video-card__copy"><p>APEX LEGENDS / <?php echo esc_html( sprintf( '%02d', $index ) ); ?></p><h3><?php echo esc_html( $title ); ?></h3><span><?php echo esc_html( $device ); ?></span></div>
    </article>
<?php endfor; ?>
</div>
