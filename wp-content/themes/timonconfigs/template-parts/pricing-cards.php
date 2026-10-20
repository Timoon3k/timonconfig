<?php
/** Pricing cards shared by home and pricing page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

$plans = array(
	array( 'slug' => 'free', 'name' => 'Free', 'tagline' => 'Perfect to get started', 'price' => '€0', 'suffix' => 'Forever', 'description' => 'Explore selected configs and join the community.', 'features' => array( 'Selected free configurations', 'Basic config library', 'Community Discord access' ), 'cta' => 'Get Free Configs', 'url' => home_url( '/configs/?tier=free' ) ),
	array( 'slug' => 'monthly', 'name' => 'VIP Monthly', 'tagline' => 'For serious players', 'price' => '€40', 'suffix' => '/ month', 'description' => 'Full premium access with ongoing updates and VIP support.', 'features' => array( 'Premium configuration library', 'Regular updates covered', 'Private VIP Discord channels', 'Monthly recurring access' ), 'cta' => 'Get Started', 'url' => timonconfigs_plan_url( 'monthly' ), 'featured' => true ),
	array( 'slug' => 'lifetime', 'name' => 'VIP Lifetime', 'tagline' => 'One payment. Lifetime license.', 'price' => '€100', 'suffix' => 'one time', 'description' => 'A one-time license for long-term access under the Lifetime terms.', 'features' => array( 'Premium configuration library', 'Updates covered by your license', 'Private VIP Discord channels', 'One-time payment' ), 'cta' => 'Get Lifetime Access', 'url' => timonconfigs_plan_url( 'lifetime' ) ),
);
foreach ( $plans as &$plan ) {
	foreach ( array( 'name', 'tagline', 'price', 'suffix', 'description', 'cta' ) as $field ) {
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
			<ul><?php foreach ( $plan['features'] as $feature ) : ?><li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul>
			<a class="tc-button<?php echo empty( $plan['featured'] ) ? ' tc-button--ghost' : ''; ?>" href="<?php echo esc_url( $plan['url'] ); ?>"><?php echo esc_html( $plan['cta'] ); ?><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php if ( in_array( $plan['slug'], array( 'monthly', 'lifetime' ), true ) ) : ?><a class="tc-plan-details" href="<?php echo esc_url( home_url( '/vip-' . $plan['slug'] . '/' ) ); ?>">View <?php echo esc_html( $plan['name'] ); ?> details</a><?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>

