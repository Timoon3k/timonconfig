<?php
/** Standard page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main"><div class="tc-shell tc-entry"><?php while ( have_posts() ) : the_post(); ?><article <?php post_class(); ?>><h1><?php the_title(); ?></h1><div class="tc-entry__content"><?php the_content(); ?></div></article><?php endwhile; ?></div></main>
<?php get_footer(); ?>

