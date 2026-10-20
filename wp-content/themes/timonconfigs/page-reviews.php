<?php
/** Verified reviews archive page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>REVIEWS</p><h1>What our clients say</h1><p class="tc-lead">Explore community feedback. Cards marked “Sample Review · Demo Content” are illustrations of the review layout, not customer testimonials.</p></div></section>
	<section class="tc-section"><div class="tc-shell"><?php get_template_part( 'template-parts/review-cards' ); ?></div></section>
</main>
<?php get_footer(); ?>
