<?php
/** Review carousel shared by Home and Reviews. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$reviews = timonconfigs_review_items();
?>
<div class="tc-review-slider" data-review-slider>
	<div class="tc-review-controls"><button type="button" data-review-prev aria-label="Previous reviews"><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></button><button type="button" data-review-next aria-label="Next reviews"><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></button></div>
	<div class="tc-review-track" data-review-track tabindex="0" aria-label="Customer reviews">
		<?php foreach ( $reviews as $review ) : ?>
			<article class="tc-review-card">
				<?php if ( $review['image'] ) : ?><img src="<?php echo esc_url( $review['image'] ); ?>" alt="" width="52" height="52" loading="lazy"><?php else : ?><span class="tc-review-avatar" aria-hidden="true"><?php echo esc_html( substr( $review['name'], 0, 1 ) ); ?></span><?php endif; ?>
				<div class="tc-review-card__body"><div class="tc-review-card__head"><h3><?php echo esc_html( $review['name'] ); ?></h3><span class="tc-review-stars" aria-label="<?php echo esc_attr( sprintf( '%d out of 5 stars', $review['rating'] ) ); ?>"><?php echo esc_html( str_repeat( '★', $review['rating'] ) . str_repeat( '☆', 5 - $review['rating'] ) ); ?></span></div>
				<?php if ( $review['demo'] ) : ?><span class="tc-review-demo">SAMPLE REVIEW · DEMO CONTENT</span><?php endif; ?>
				<div class="tc-review-quote"><?php echo wp_kses_post( $review['quote'] ); ?></div>
				<?php if ( $review['config'] ) : ?><small><?php echo esc_html( $review['config'] ); ?></small><?php endif; ?></div>
			</article>
		<?php endforeach; ?>
	</div>
</div>
