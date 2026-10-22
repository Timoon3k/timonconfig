<?php
/** Standalone FAQ managed from the FAQ editor. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>HELP CENTER</p><h1><?php the_title(); ?></h1><div class="tc-lead"><?php if ( trim( (string) get_post_field( 'post_content', get_queried_object_id() ) ) ) { echo apply_filters( 'the_content', get_post_field( 'post_content', get_queried_object_id() ) ); } else { echo '<p>Clear answers about devices, membership, checkout, updates, and access.</p>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div></section>
	<section class="tc-section tc-faq"><div class="tc-shell tc-content-narrow">
		<div class="tc-faq-list"><?php timonconfigs_render_faqs(); ?></div>
		<div class="tc-inline-cta"><h2>Still need help?</h2><p>Contact TimonConfigs or compare the plans before you continue.</p><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us</a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Compare plans</a></div></div>
	</div></section>
</main>
<?php get_footer(); ?>
