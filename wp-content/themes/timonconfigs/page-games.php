<?php
/** Games overview. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();

$apex = get_term_by( 'slug', 'apex-legends', 'tc_game' );
$apex_url = $apex instanceof WP_Term ? get_term_link( $apex ) : home_url( '/apex-legends-configs/' );
$apex_url = is_wp_error( $apex_url ) ? home_url( '/apex-legends-configs/' ) : $apex_url;
?>
<main id="main">
	<section class="tc-page-hero tc-page-hero--games">
		<div class="tc-shell">
			<?php timonconfigs_breadcrumbs(); ?>
			<p class="tc-eyebrow"><span></span>SUPPORTED GAMES</p>
			<h1>Configs built around the games you play.</h1>
			<p class="tc-lead">Explore current TimonConfigs collections with clear device, input, platform, and access details. New games appear here only when their releases are ready.</p>
		</div>
	</section>
	<section class="tc-section">
		<div class="tc-shell">
			<div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>AVAILABLE NOW</p><h2>Apex Legends</h2></div><p>Our flagship collection covers Cronus Zen and XIM Matrix setups for controller and mouse and keyboard players.</p></div>
			<a class="tc-game-feature" href="<?php echo esc_url( $apex_url ); ?>">
				<span class="tc-game-feature__number">01</span>
				<div><span class="tc-price-card__name">FLAGSHIP COLLECTION</span><h2>Apex Legends configs</h2><p>Compare verified releases, supported devices, input methods, and Free or VIP access.</p></div>
				<span class="tc-text-link">Browse Apex configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</a>
		</div>
	</section>
	<section class="tc-section tc-games-devices">
		<div class="tc-shell">
			<div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>CHOOSE YOUR DEVICE</p><h2>Find your setup faster.</h2></div></div>
			<div class="tc-platforms">
				<article><span class="tc-number">01 / CRONUS</span><h2>Cronus Zen</h2><p>Browse compatible Cronus Zen releases with their current access and verification details.</p><a href="<?php echo esc_url( home_url( '/cronus-zen-configs/' ) ); ?>">View Cronus Zen configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article>
				<article><span class="tc-number">02 / XIM</span><h2>XIM Matrix</h2><p>Explore controller and mouse and keyboard releases prepared for XIM Matrix.</p><a href="<?php echo esc_url( home_url( '/xim-matrix-configs/' ) ); ?>">View XIM Matrix configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article>
				<article><span class="tc-number">03 / ALL RELEASES</span><h2>Config library</h2><p>Search every published release by game, device, input method, and membership tier.</p><a href="<?php echo esc_url( get_post_type_archive_link( 'tc_config' ) ); ?>">Open the library <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article>
			</div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
