<?php
/** Fallback template. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main"><div class="tc-shell tc-entry"><h1><?php bloginfo( 'name' ); ?></h1><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?><article><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?></article><?php endwhile; else : ?><p><?php esc_html_e( 'Nothing found.', 'timonconfigs' ); ?></p><?php endif; ?></div></main>
<?php get_footer(); ?>
