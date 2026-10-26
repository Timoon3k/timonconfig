<?php
/** Shared legal policy template, with native block content as the source. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$policy = isset( $args['policy'] ) && is_string( $args['policy'] ) ? $args['policy'] : 'terms';
$slugs  = array( 'terms' => 'terms-of-service', 'privacy' => 'privacy-policy', 'refund' => 'refund-policy', 'cookies' => 'privacy-policy', 'lifetime' => 'terms-of-service' );
$slug   = $slugs[ $policy ] ?? 'terms-of-service';
$page_id = get_queried_object_id();
$content = (string) get_post_field( 'post_content', $page_id );
if ( '' === trim( $content ) && class_exists( 'TC_Site' ) ) {
	$content = TC_Site::default_content( $slug );
}
?>
<main id="main">
	<section class="tc-page-hero tc-page-hero--legal"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>LEGAL</p><h1><?php the_title(); ?></h1><p class="tc-lead">Last updated: <?php echo esc_html( get_the_modified_date( 'F j, Y', $page_id ) ); ?></p></div></section>
	<section class="tc-section"><div class="tc-shell tc-legal-content tc-entry__content">
		<?php if ( '' !== trim( $content ) ) : ?>
			<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress authored post content. ?>
		<?php else : ?>
			<p>The current policy is being prepared for owner review. Please <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact TimonConfigs</a> before purchasing if you need the applicable terms.</p>
		<?php endif; ?>
	</div></section>
</main>
