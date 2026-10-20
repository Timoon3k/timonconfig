<?php
/** Pricing cards shared by home and pricing page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

$plans = array(
	array( 'slug' => 'free', 'name' => 'Free', 'price' => '€0', 'suffix' => 'forever', 'description' => 'Explore selected configs and join the community.', 'features' => array( 'Selected free configurations', 'Basic config library', 'Community Discord access' ), 'cta' => 'Explore free configs', 'url' => home_url( '/configs/' ) ),
	array( 'slug' => 'monthly', 'name' => 'VIP Monthly', 'price' => '€40', 'suffix' => 'per month', 'description' => 'Full premium access with ongoing updates and VIP support.', 'features' => array( 'Premium configuration library', 'Regular updates covered', 'Private VIP Discord channels', 'Monthly recurring access' ), 'cta' => 'Choose VIP Monthly', 'url' => timonconfigs_plan_url( 'monthly' ), 'featured' => true ),
	array( 'slug' => 'lifetime', 'name' => 'VIP Lifetime', 'price' => '€100', 'suffix' => 'one time', 'description' => 'A one-time license for long-term access under the Lifetime terms.', 'features' => array( 'Premium configuration library', 'Updates covered by your license', 'Private VIP Discord channels', 'One-time payment' ), 'cta' => 'Choose VIP Lifetime', 'url' => timonconfigs_plan_url( 'lifetime' ) ),
);
foreach ( $plans as &$plan ) {
	foreach ( array( 'name', 'price', 'suffix', 'description', 'cta' ) as $field ) {
		$plan[ $field ] = get_theme_mod( 'timonconfigs_plan_' . $plan['slug'] . '_' . $field, $plan[ $field ] );
	}
	$feature_text    = get_theme_mod( 'timonconfigs_plan_' . $plan['slug'] . '_features', implode( "\n", $plan['features'] ) );
	$plan['features'] = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $feature_text ) ?: array() ) ) );
}
unset( $plan );
?>
<div class="tc-pricing-grid">
	<?php foreach ( $plans as $plan ) : ?>
		<article class="tc-price-card<?php echo ! empty( $plan['featured'] ) ? ' tc-price-card--featured' : ''; ?>">
			<?php if ( ! empty( $plan['featured'] ) ) : ?><span class="tc-price-card__flag">RECOMMENDED</span><?php endif; ?>
			<div class="tc-price-card__heading"><?php echo timonconfigs_icon( 'free' === $plan['slug'] ? 'gift' : 'crown' ); // phpcs:ignore ?><p class="tc-price-card__name"><?php echo esc_html( $plan['name'] ); ?></p></div>
			<p class="tc-price"><strong><?php echo esc_html( $plan['price'] ); ?></strong><span><?php echo esc_html( $plan['suffix'] ); ?></span></p>
			<p><?php echo esc_html( $plan['description'] ); ?></p>
			<ul><?php foreach ( $plan['features'] as $feature ) : ?><li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul>
			<a class="tc-button<?php echo empty( $plan['featured'] ) ? ' tc-button--ghost' : ''; ?>" href="<?php echo esc_url( $plan['url'] ); ?>"><?php echo esc_html( $plan['cta'] ); ?><?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php if ( in_array( $plan['slug'], array( 'monthly', 'lifetime' ), true ) ) : ?><a class="tc-plan-details" href="<?php echo esc_url( home_url( '/vip-' . $plan['slug'] . '/' ) ); ?>">View <?php echo esc_html( $plan['name'] ); ?> details</a><?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>

