<?php
/** Editable storefront with backwards-compatible default sections. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<?php
	while ( have_posts() ) :
		the_post();
		$content = (string) get_the_content();
		if ( trim( $content ) ) {
			the_content();
		} else {
			echo do_blocks( timonconfigs_default_home_content() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block renderers escape each field.
		}
	endwhile;
	?>
</main>
<?php get_footer(); ?>
