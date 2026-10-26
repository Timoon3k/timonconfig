<?php
/** Site footer. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$discord_url  = timonconfigs_community_url();
$social_links = array_filter(
	array(
		'X'         => get_theme_mod( 'timonconfigs_x_url', '' ),
		'YouTube'   => get_theme_mod( 'timonconfigs_youtube_url', '' ),
		'Instagram' => get_theme_mod( 'timonconfigs_instagram_url', '' ),
	)
);
?>
<footer class="tc-footer">
	<div class="tc-shell tc-footer__grid">
		<div class="tc-footer__brand">
			<a class="tc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php get_template_part( 'template-parts/brand' ); ?></a>
			<p><?php echo esc_html( get_theme_mod( 'timonconfigs_footer_description', 'Professional game configs for Cronus Zen and XIM Matrix.' ) ); ?></p>
		</div>
		<nav class="tc-footer__nav" aria-label="<?php echo esc_attr__( 'Footer navigation', 'timonconfigs' ); ?>">
			<?php wp_nav_menu( array( 'theme_location' => 'primary', 'menu_id' => 'footer-primary-menu', 'container' => false, 'fallback_cb' => false ) ); ?>
		</nav>
		<nav class="tc-footer__social" aria-label="Social links"><ul><li><a href="<?php echo esc_url( $discord_url ); ?>" aria-label="Discord"><?php echo timonconfigs_icon( 'discord' ); // phpcs:ignore ?></a></li><?php foreach ( $social_links as $label => $url ) : ?><li><a href="<?php echo esc_url( $url ); ?>" rel="me noopener" aria-label="<?php echo esc_attr( $label ); ?>"><?php echo timonconfigs_icon( strtolower( $label ) ); // phpcs:ignore ?></a></li><?php endforeach; ?></ul></nav>
	</div>
	<div class="tc-shell tc-footer__legal">
		<p>&copy; 2026 TimonConfigs created by <a href="<?php echo esc_url( get_theme_mod( 'timonconfigs_creator_url', 'https://majewskitomasz.pl/' ) ); ?>">majewskitomasz.pl</a>. All rights reserved.</p>
		<nav class="tc-footer__policies" aria-label="Legal information"><?php wp_nav_menu( array( 'theme_location' => 'footer', 'menu_id' => 'footer-legal-menu', 'container' => false, 'fallback_cb' => false ) ); ?></nav>
		<p class="tc-footer__disclaimer"><?php esc_html_e( 'Not affiliated with Respawn Entertainment, Cronus, XIM Technologies, or their respective owners.', 'timonconfigs' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

