<?php
/** Verified reviews archive page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>REVIEWS</p><h1><?php the_title(); ?></h1><div class="tc-lead"><?php if ( trim( (string) get_post_field( 'post_content', get_queried_object_id() ) ) ) { echo apply_filters( 'the_content', get_post_field( 'post_content', get_queried_object_id() ) ); } else { echo '<p>Explore community feedback. Cards marked “Sample Review · Demo Content” are illustrations of the layout, not customer testimonials.</p>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div></section>
	<section class="tc-section"><div class="tc-shell"><?php get_template_part( 'template-parts/review-cards' ); ?></div></section>
</main>
<?php get_footer(); ?>
