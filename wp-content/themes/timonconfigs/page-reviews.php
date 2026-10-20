<?php
/** Verified reviews archive page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
$reviews = get_posts( array( 'post_type' => 'tc_review', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>REVIEWS</p><h1>Verified community feedback</h1><p class="tc-lead">Feedback is published only after TimonConfigs has reviewed its source and permission to display it.</p></div></section>
	<section class="tc-section"><div class="tc-shell"><div class="tc-review-grid">
		<?php if ( $reviews ) : foreach ( $reviews as $review ) : ?><article class="tc-review-card"><?php echo get_the_post_thumbnail( $review, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?><div><h2><?php echo esc_html( get_the_title( $review ) ); ?></h2><div><?php echo wp_kses_post( apply_filters( 'the_content', $review->post_content ) ); ?></div></div></article><?php endforeach; else : ?><p class="tc-review-empty">Verified customer feedback will appear here after it has been approved for publication.</p><?php endif; ?>
	</div></div></section>
</main>
<?php get_footer(); ?>
