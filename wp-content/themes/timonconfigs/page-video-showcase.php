<?php
/** Video showcase. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>VIDEO SHOWCASE</p><h1>Apex Legends — <?php the_title(); ?></h1><div class="tc-lead"><?php if ( trim( (string) get_post_field( 'post_content', get_queried_object_id() ) ) ) { echo apply_filters( 'the_content', get_post_field( 'post_content', get_queried_object_id() ) ); } else { echo '<p>First-party TimonConfigs footage. Device and config labels remain provisional until their recording metadata is verified.</p>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div></section>
	<section class="tc-section tc-showcase"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>APEX LEGENDS</p><h2>Recorded showcases</h2></div><p>Open any video in the focused player. The original files load only when requested.</p></div><?php get_template_part( 'template-parts/video-cards' ); ?><div class="tc-showcase__cta"><a class="tc-button" href="<?php echo esc_url( home_url( '/vip-monthly/' ) ); ?>">Explore VIP Monthly <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Browse configs</a></div></div></section>
</main>
<?php get_footer(); ?>
