<?php
/** Front page template. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();

$discord_url = get_theme_mod( 'timonconfigs_discord_url', '' );
?>
<main id="main">
	<section class="tc-hero">
		<div class="tc-hero__glow" aria-hidden="true"></div>
		<div class="tc-shell tc-hero__grid">
			<div class="tc-hero__content">
				<p class="tc-eyebrow"><span></span><?php echo esc_html( get_theme_mod( 'timonconfigs_hero_eyebrow', 'BUILT FOR PLAYERS WHO WANT MORE' ) ); ?></p>
				<h1><?php echo esc_html( get_theme_mod( 'timonconfigs_hero_title', 'Precision configs. Competitive confidence.' ) ); ?></h1>
				<p class="tc-lead"><?php echo esc_html( get_theme_mod( 'timonconfigs_hero_copy', 'Premium Cronus Zen and XIM Matrix configurations, refined for responsive control and supported by a focused Discord community.' ) ); ?></p>
				<div class="tc-actions">
					<a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( 'monthly' ) ); ?>"><?php esc_html_e( 'Unlock VIP Monthly', 'timonconfigs' ); ?><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<a class="tc-button tc-button--ghost" href="#showcase"><?php echo timonconfigs_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'See configs in action', 'timonconfigs' ); ?></a>
				</div>
				<ul class="tc-trust" aria-label="<?php echo esc_attr__( 'Membership highlights', 'timonconfigs' ); ?>">
					<li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>Secure Upgrade.chat checkout</li>
					<li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>Private VIP channels</li>
					<li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>Ongoing config updates</li>
				</ul>
			</div>
			<div class="tc-hero-card" aria-label="<?php echo esc_attr__( 'Featured Apex Legends config', 'timonconfigs' ); ?>">
				<div class="tc-hero-card__visual">
					<div class="tc-crosshair" aria-hidden="true"></div>
					<span class="tc-badge">FLAGSHIP CONFIG</span>
					<div class="tc-hero-card__device">APEX<br><strong>S30</strong></div>
				</div>
				<div class="tc-hero-card__meta"><span>CRONUS ZEN</span><span>CONTROLLER</span><span>LIVE TUNING</span></div>
			</div>
		</div>
	</section>

	<section class="tc-section tc-section--tight" aria-labelledby="platforms-title">
		<div class="tc-shell">
			<p class="tc-section-kicker" id="platforms-title"><?php esc_html_e( 'Purpose-built for your setup', 'timonconfigs' ); ?></p>
			<div class="tc-platforms">
				<article><span class="tc-number">01</span><h2>Cronus Zen</h2><p>Refined controller configurations with clear setup guidance and support.</p><a href="<?php echo esc_url( home_url( '/cronus-zen-configs/' ) ); ?>">Explore Zen configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article>
				<article><span class="tc-number">02</span><h2>XIM Matrix Controller</h2><p>Responsive Smart Action setups designed around controller play.</p><a href="<?php echo esc_url( home_url( '/xim-matrix-configs/' ) ); ?>">Explore Matrix configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article>
				<article><span class="tc-number">03</span><h2>XIM Matrix MnK</h2><p>Dedicated mouse and keyboard profiles with practical tuning notes.</p><a href="<?php echo esc_url( home_url( '/xim-matrix-configs/' ) ); ?>">View MnK options <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article>
			</div>
		</div>
	</section>

	<section class="tc-section tc-showcase" id="showcase" aria-labelledby="showcase-title">
		<div class="tc-shell">
			<div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>APEX LEGENDS</p><h2 id="showcase-title">See our flagship configs in action</h2></div><p>Real TimonConfigs demonstrations will appear here. Video fields are prepared for first-party YouTube or MP4 footage without fabricated claims.</p></div>
			<div class="tc-video-grid">
				<?php for ( $index = 1; $index <= 3; $index++ ) :
					$title  = get_theme_mod( 'timonconfigs_video_' . $index . '_title', sprintf( 'Apex Legends Gameplay Demo %02d', $index ) );
					$device = get_theme_mod( 'timonconfigs_video_' . $index . '_device', 'Device details pending verification' );
					$video  = get_theme_mod( 'timonconfigs_video_' . $index . '_url', get_template_directory_uri() . '/assets/video/apex-demo-0' . $index . '.mp4' );
					$poster = get_template_directory_uri() . '/assets/images/apex-demo-0' . $index . '.jpg';
					?>
					<article class="tc-video-card">
						<button class="tc-video-card__media" type="button" data-video-open data-video-src="<?php echo esc_url( $video ); ?>" data-video-poster="<?php echo esc_url( $poster ); ?>" data-video-title="<?php echo esc_attr( $title ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Play %s', 'timonconfigs' ), $title ) ); ?>">
							<img src="<?php echo esc_url( $poster ); ?>" alt="" loading="lazy" width="576" height="1024">
							<span class="tc-video-card__shade"></span><span class="tc-video-card__index"><?php echo esc_html( sprintf( '%02d', $index ) ); ?></span><span class="tc-play"><?php echo timonconfigs_icon( 'play' ); // phpcs:ignore ?></span><span class="tc-video-card__action">PLAY DEMO</span>
						</button>
						<div><p>APEX LEGENDS</p><h3><?php echo esc_html( $title ); ?></h3><span><?php echo esc_html( $device ); ?></span></div>
					</article>
				<?php endfor; ?>
			</div>
			<div class="tc-showcase__cta"><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( 'monthly' ) ); ?>">Get VIP Monthly — €40/month <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( timonconfigs_plan_url( 'lifetime' ) ); ?>">Get Lifetime — €100</a></div>
		</div>
	</section>
	<dialog class="tc-video-modal" data-video-modal aria-labelledby="tc-video-title">
		<div class="tc-video-modal__bar"><h2 id="tc-video-title" data-video-modal-title>Apex Legends demo</h2><button type="button" data-video-close aria-label="<?php echo esc_attr__( 'Close video', 'timonconfigs' ); ?>">&times;</button></div>
		<video controls playsinline preload="none" data-video-player></video>
	</dialog>

	<section class="tc-section tc-pricing-preview" aria-labelledby="pricing-title">
		<div class="tc-shell">
			<div class="tc-section-heading tc-section-heading--center"><div><p class="tc-eyebrow"><span></span>SIMPLE ACCESS</p><h2 id="pricing-title">Choose how you want to play</h2></div><p>Start free, subscribe for continuous VIP access, or choose a one-time Lifetime license.</p></div>
			<?php get_template_part( 'template-parts/pricing-cards' ); ?>
		</div>
	</section>

	<section class="tc-section tc-final-cta">
		<div class="tc-shell tc-final-cta__inner">
			<div><p class="tc-eyebrow"><span></span>READY TO GET STARTED?</p><h2>Build your setup with confidence.</h2><p>Join VIP for premium configs, covered updates, and access to private Discord channels.</p></div>
			<div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( 'monthly' ) ); ?>">Get VIP Monthly <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php if ( $discord_url ) : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $discord_url ); ?>" target="_blank" rel="noopener noreferrer">Join Discord</a><?php endif; ?></div>
		</div>
	</section>
</main>
<?php get_footer(); ?>

