<?php
/** Community reviews with links to their original source. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$review_intro = (string) get_post_field( 'post_content', get_queried_object_id() );
$legacy_intros = array( 'Explore community feedback. Cards marked “Sample Review / Demo Content” are examples of the layout rather than real customer testimonials.', 'Explore community feedback. Cards marked “Sample Review · Demo Content” are illustrations of the layout, not customer testimonials.' );
if ( ! trim( $review_intro ) || in_array( trim( wp_strip_all_tags( $review_intro ) ), $legacy_intros, true ) ) { $review_intro = '<p>Read community feedback from the TimonConfigs Discord vouches channel. Imported messages include their original date and a link to the source post.</p>'; }
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>COMMUNITY VOUCHES</p><h1><?php the_title(); ?></h1><div class="tc-lead"><?php echo apply_filters( 'the_content', $review_intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div></section>
	<section class="tc-section"><div class="tc-shell"><?php get_template_part( 'template-parts/review-cards' ); ?></div></section>
</main>
<?php get_footer(); ?>
