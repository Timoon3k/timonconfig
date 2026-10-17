<?php
/** Video showcase. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>VIDEO SHOWCASE</p><h1>Apex Legends — see our flagship configs in action.</h1><p class="tc-lead">First-party TimonConfigs gameplay footage. Device and config labels remain provisional until their recording metadata is verified.</p></div></section>
	<section class="tc-section tc-showcase"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>APEX LEGENDS</p><h2>Recorded showcases</h2></div><p>Open any video in the focused player. The original files load only when requested.</p></div><div class="tc-video-grid">
	<?php for ( $index = 1; $index <= 3; $index++ ) :
		$title  = get_theme_mod( 'timonconfigs_video_' . $index . '_title', sprintf( 'Apex Legends Gameplay Demo %02d', $index ) );
		$device = get_theme_mod( 'timonconfigs_video_' . $index . '_device', 'Device details pending verification' );
		$video  = get_theme_mod( 'timonconfigs_video_' . $index . '_url', get_template_directory_uri() . '/assets/video/apex-demo-0' . $index . '.mp4' );
		$poster = get_template_directory_uri() . '/assets/images/apex-demo-0' . $index . '.jpg';
		?>
		<article class="tc-video-card"><button class="tc-video-card__media" type="button" data-video-open data-video-src="<?php echo esc_url( $video ); ?>" data-video-poster="<?php echo esc_url( $poster ); ?>" data-video-title="<?php echo esc_attr( $title ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Play %s', $title ) ); ?>"><img src="<?php echo esc_url( $poster ); ?>" alt="" loading="lazy" width="576" height="1024"><span class="tc-video-card__shade"></span><span class="tc-video-card__index"><?php echo esc_html( sprintf( '%02d', $index ) ); ?></span><span class="tc-play"><?php echo timonconfigs_icon( 'play' ); // phpcs:ignore ?></span><span class="tc-video-card__action">PLAY DEMO</span></button><div><p>APEX LEGENDS</p><h3><?php echo esc_html( $title ); ?></h3><span><?php echo esc_html( $device ); ?></span></div></article>
	<?php endfor; ?>
	</div><div class="tc-showcase__cta"><a class="tc-button" href="<?php echo esc_url( home_url( '/vip-monthly/' ) ); ?>">Explore VIP Monthly <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Browse configs</a></div></div></section>
	<dialog class="tc-video-modal" data-video-modal aria-labelledby="tc-video-title"><div class="tc-video-modal__bar"><h2 id="tc-video-title" data-video-modal-title>Apex Legends demo</h2><button type="button" data-video-close aria-label="Close video">&times;</button></div><video controls playsinline preload="none" data-video-player></video></dialog>
</main>
<?php get_footer(); ?>
