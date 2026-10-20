<?php
/** Site footer. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$discord_url  = get_theme_mod( 'timonconfigs_discord_url', '' );
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
		<div>
			<a class="tc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php get_template_part( 'template-parts/brand' ); ?></a>
			<p><?php esc_html_e( 'Premium controller configurations and a focused community for players who care about every input.', 'timonconfigs' ); ?></p>
		</div>
		<nav aria-label="<?php echo esc_attr__( 'Footer navigation', 'timonconfigs' ); ?>">
			<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'fallback_cb' => false ) ); ?>
		</nav>
		<?php if ( $discord_url || $social_links ) : ?><nav class="tc-footer__social" aria-label="Social links"><ul><?php if ( $discord_url ) : ?><li><a href="<?php echo esc_url( $discord_url ); ?>">Discord</a></li><?php endif; ?><?php foreach ( $social_links as $label => $url ) : ?><li><a href="<?php echo esc_url( $url ); ?>" rel="me noopener"><?php echo esc_html( $label ); ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
	</div>
	<div class="tc-shell tc-footer__legal">
		<p>&copy; 2026 TimonConfigs created by <a href="<?php echo esc_url( get_theme_mod( 'timonconfigs_creator_url', 'https://majewskitomasz.pl/' ) ); ?>">majewskitomasz.pl</a>. All rights reserved.</p>
		<p><?php esc_html_e( 'Not affiliated with Respawn Entertainment, Cronus, XIM Technologies, or their respective owners.', 'timonconfigs' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

