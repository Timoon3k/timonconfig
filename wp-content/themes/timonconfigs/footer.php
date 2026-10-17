<?php
/** Site footer. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
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
	</div>
	<div class="tc-shell tc-footer__legal">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> TimonConfigs. <?php esc_html_e( 'All rights reserved.', 'timonconfigs' ); ?></p>
		<p><?php esc_html_e( 'Not affiliated with Respawn Entertainment, Cronus, XIM Technologies, or their respective owners.', 'timonconfigs' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

