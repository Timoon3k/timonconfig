<?php
/** Verified reviews archive page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>REVIEWS</p><h1>Verified community feedback</h1><p class="tc-lead">Feedback is published only after TimonConfigs has reviewed its source and permission to display it.</p></div></section>
	<section class="tc-section"><div class="tc-shell"><?php get_template_part( 'template-parts/review-cards' ); ?></div></section>
</main>
<?php get_footer(); ?>
