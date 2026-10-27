<?php
/** Pricing cards shared by home and pricing page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

$plans = timonconfigs_plan_defaults();
$featured_plan = get_theme_mod( 'timonconfigs_featured_plan', 'lifetime' );
$featured_plan = in_array( $featured_plan, array( 'monthly', 'lifetime' ), true ) ? $featured_plan : 'lifetime';
foreach ( $plans as $slug => &$plan ) {
	$plan['slug'] = $slug;
	$plan['url'] = 'free' === $slug ? home_url( '/configs/?tier=free' ) : timonconfigs_plan_url( $slug );
	$plan['featured'] = $slug === $featured_plan;
	$plan['price'] = timonconfigs_display_price( $plan['slug'] );
	foreach ( array( 'name', 'tagline', 'suffix', 'description', 'cta' ) as $field ) {
		$plan[ $field ] = get_theme_mod( 'timonconfigs_plan_' . $plan['slug'] . '_' . $field, $plan[ $field ] );
	}
	$feature_text    = get_theme_mod( 'timonconfigs_plan_' . $plan['slug'] . '_features', implode( "\n", $plan['features'] ) );
	$plan['features'] = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $feature_text ) ?: array() ) ) );
}
unset( $plan );
?>
<div class="tc-pricing-grid">
	<?php foreach ( $plans as $plan ) : ?>
		<article class="tc-price-card tc-price-card--<?php echo esc_attr( $plan['slug'] ); ?><?php echo ! empty( $plan['featured'] ) ? ' tc-price-card--featured' : ''; ?>">
			<?php if ( ! empty( $plan['featured'] ) ) : ?><span class="tc-price-card__flag">MOST POPULAR</span><?php endif; ?>
			<div class="tc-price-card__heading"><?php echo timonconfigs_icon( 'free' === $plan['slug'] ? 'gift' : 'crown' ); // phpcs:ignore ?><div><p class="tc-price-card__name"><?php echo esc_html( $plan['name'] ); ?></p><p class="tc-price-card__tagline"><?php echo esc_html( $plan['tagline'] ); ?></p></div></div>
			<p class="tc-price"><strong><?php echo esc_html( $plan['price'] ); ?></strong><span><?php echo esc_html( $plan['suffix'] ); ?></span></p>
			<p class="tc-price-card__description"><?php echo esc_html( $plan['description'] ); ?></p>
			<?php if ( 'free' !== $plan['slug'] ) : ?><p class="tc-price-card__library"><?php echo esc_html( timonconfigs_vip_library_copy() ); ?></p><?php endif; ?>
			<ul><?php foreach ( $plan['features'] as $feature ) : ?><li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul>
			<a class="tc-button<?php echo empty( $plan['featured'] ) ? ' tc-button--ghost' : ''; ?>" href="<?php echo esc_url( $plan['url'] ); ?>"><?php echo esc_html( $plan['cta'] ); ?><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php if ( in_array( $plan['slug'], array( 'monthly', 'lifetime' ), true ) ) : ?><a class="tc-plan-details" href="<?php echo esc_url( home_url( '/vip-' . $plan['slug'] . '/' ) ); ?>">View <?php echo esc_html( $plan['name'] ); ?> details</a><?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>

