<?php
/** Review carousel shared by Home and Reviews. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$reviews = timonconfigs_review_items();
if ( ! $reviews ) {
	$channel_url = 'https://discord.com/channels/1557854161805115402/1560781856931315832';
	if ( class_exists( 'TC_Discord_Vouches' ) ) {
		$config = TC_Discord_Vouches::config();
		if ( preg_match( '/^[1-9][0-9]{16,19}$/D', (string) ( $config['guild_id'] ?? '' ) ) && preg_match( '/^[1-9][0-9]{16,19}$/D', (string) ( $config['channel_id'] ?? '' ) ) ) { $channel_url = 'https://discord.com/channels/' . $config['guild_id'] . '/' . $config['channel_id']; }
	}
	echo '<div class="tc-reviews-empty"><p>Read community feedback in our Discord vouches channel.</p><a class="tc-text-link" href="' . esc_url( $channel_url ) . '" rel="noopener noreferrer" target="_blank">Read Discord vouches</a></div>';
	return;
}
?>
<div class="tc-review-slider" data-review-slider>
	<div class="tc-review-controls"><button type="button" data-review-prev aria-label="Previous reviews"><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></button><button type="button" data-review-next aria-label="Next reviews"><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></button></div>
	<div class="tc-review-track" data-review-track tabindex="0" aria-label="Community reviews">
		<?php foreach ( $reviews as $review ) : ?>
			<article class="tc-review-card">
				<?php if ( $review['image'] ) : ?><img src="<?php echo esc_url( $review['image'] ); ?>" alt="" width="52" height="52" loading="lazy"><?php else : ?><span class="tc-review-avatar" aria-hidden="true"><?php echo esc_html( substr( $review['name'], 0, 1 ) ); ?></span><?php endif; ?>
				<div class="tc-review-card__body"><div class="tc-review-card__head"><h3><?php echo esc_html( $review['name'] ); ?></h3><?php if ( $review['rating'] > 0 ) : ?><span class="tc-review-stars" aria-label="<?php echo esc_attr( sprintf( '%d out of 5 stars', $review['rating'] ) ); ?>"><?php echo esc_html( str_repeat( '★', $review['rating'] ) . str_repeat( '☆', 5 - $review['rating'] ) ); ?></span><?php endif; ?></div>
				<div class="tc-review-quote"><?php echo wp_kses_post( $review['quote'] ); ?></div>
				<?php if ( $review['config'] ) : ?><small><?php echo esc_html( $review['config'] ); ?></small><?php endif; ?>
				<?php if ( $review['source_url'] ) : ?><div class="tc-review-source"><a href="<?php echo esc_url( $review['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $review['source_label'] ); ?></a><time datetime="<?php echo esc_attr( $review['date'] ); ?>"><?php echo esc_html( $review['date_label'] ); ?></time></div><?php endif; ?></div>
			</article>
		<?php endforeach; ?>
	</div>
</div>
