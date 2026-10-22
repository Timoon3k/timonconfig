<?php
/** First-party videos: native inline playback, optional modal, and click-to-load YouTube. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$videos = class_exists( 'TC_Videos' ) ? TC_Videos::get_public_videos() : array();
if ( ! class_exists( 'TC_Videos' ) ) {
    // Keep this theme usable with the previous Core plugin during a staged update.
    for ( $index = 1; $index <= 3; $index++ ) {
        $videos[] = array(
            'title' => get_theme_mod( 'timonconfigs_video_' . $index . '_title', sprintf( 'Apex Legends Gameplay %02d', $index ) ),
            'device' => get_theme_mod( 'timonconfigs_video_' . $index . '_device', 'Apex Legends · Gameplay showcase' ),
            'url' => get_theme_mod( 'timonconfigs_video_' . $index . '_url', get_template_directory_uri() . '/assets/video/apex-demo-0' . $index . '.mp4' ),
            'poster' => get_theme_mod( 'timonconfigs_video_' . $index . '_poster', get_template_directory_uri() . '/assets/images/apex-demo-0' . $index . '.jpg' ),
            'type' => 'mp4', 'youtube_id' => '', 'mode' => 'inline', 'captions' => '', 'description' => '',
        );
    }
}
?>
<div class="tc-video-grid">
<?php foreach ( $videos as $index => $video ) : ?>
    <article class="tc-video-card">
        <div class="tc-inline-video" data-inline-video data-video-kind="<?php echo esc_attr( $video['type'] ); ?>" data-video-mode="<?php echo esc_attr( $video['mode'] ); ?>" data-video-title="<?php echo esc_attr( $video['title'] ); ?>"<?php if ( 'youtube' === $video['type'] ) : ?> data-youtube-id="<?php echo esc_attr( $video['youtube_id'] ); ?>"<?php endif; ?>>
            <?php if ( 'youtube' === $video['type'] ) : ?>
                <?php if ( $video['poster'] ) : ?><img class="tc-youtube-poster" src="<?php echo esc_url( $video['poster'] ); ?>" alt="" width="960" height="540" loading="lazy" decoding="async"><?php endif; ?>
            <?php else : ?>
                <video controls playsinline preload="none" poster="<?php echo esc_url( $video['poster'] ); ?>" aria-label="<?php echo esc_attr( $video['title'] ); ?>" data-video-player data-video-src="<?php echo esc_url( $video['url'] ); ?>">
                    <?php if ( $video['captions'] ) : ?><track kind="captions" srclang="en" label="English" data-video-track-src="<?php echo esc_url( $video['captions'] ); ?>"><?php endif; ?>
                </video>
            <?php endif; ?>
            <button class="tc-inline-video__play" type="button" data-video-play aria-label="<?php echo esc_attr( sprintf( 'Play %s', $video['title'] ) ); ?>"><span class="tc-play"><?php echo timonconfigs_icon( 'play' ); // phpcs:ignore ?></span><span class="tc-inline-video__label"><?php echo 'youtube' === $video['type'] ? 'LOAD YOUTUBE VIDEO' : 'WATCH GAMEPLAY'; ?></span><?php if ( 'youtube' === $video['type'] ) : ?><span class="tc-youtube-notice">Loads YouTube after you click.</span><?php endif; ?></button>
            <noscript><a class="tc-video-fallback" href="<?php echo esc_url( $video['url'] ); ?>">Watch <?php echo esc_html( $video['title'] ); ?></a></noscript>
        </div>
        <div class="tc-video-card__copy"><p>APEX LEGENDS / <?php echo esc_html( sprintf( '%02d', $index + 1 ) ); ?></p><h3><?php echo esc_html( $video['title'] ); ?></h3><span><?php echo esc_html( $video['device'] ); ?></span><?php if ( $video['description'] && ! is_front_page() ) : ?><p class="tc-video-description"><?php echo esc_html( $video['description'] ); ?></p><?php endif; ?></div>
    </article>
<?php endforeach; ?>
<?php if ( ! $videos ) : ?><p class="tc-empty-state">The Apex showcase is being updated. New first-party videos will appear here after publication.</p><?php endif; ?>
</div>
