<?php
/** VIP plan landing content. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

$plan = isset( $args['plan'] ) && 'lifetime' === $args['plan'] ? 'lifetime' : 'monthly';
$monthly = 'monthly' === $plan;
$content = $monthly
	? array(
		'name'        => 'VIP Monthly',
		'eyebrow'     => 'FULL PREMIUM ACCESS',
		'headline'    => 'Premium configs that stay current.',
		'price'       => '€40',
		'suffix'      => 'per month',
		'intro'       => 'Get the full premium configuration library, regular updates covered while your subscription is active, and access to private VIP Discord channels.',
		'cta'         => 'Continue to VIP Monthly checkout',
		'features'    => array( 'Full premium config library', 'Regular updates while subscribed', 'Private VIP Discord channels', 'Recurring monthly membership' ),
		'best_for'    => 'Players who want current premium releases, ongoing updates, and the flexibility of a monthly membership.',
		'billing'     => 'This is a recurring monthly subscription. Upgrade.chat displays the final checkout terms and manages cancellation.',
	)
	: array(
		'name'        => 'VIP Lifetime',
		'eyebrow'     => 'ONE-TIME LICENSE',
		'headline'    => 'Long-term access with one payment.',
		'price'       => '€100',
		'suffix'      => 'one time',
		'intro'       => 'Unlock the premium configuration library and private VIP channels under the Lifetime license terms presented when you purchase.',
		'cta'         => 'Continue to VIP Lifetime checkout',
		'features'    => array( 'Full premium config library', 'Updates covered by your license', 'Private VIP Discord channels', 'One-time payment' ),
		'best_for'    => 'Players who prefer a single payment and access governed by the Lifetime license rather than a recurring subscription.',
		'billing'     => 'This is a one-time purchase. The exact access and update scope follows the license terms displayed at Upgrade.chat checkout.',
	);
?>
<main id="main">
	<section class="tc-page-hero tc-vip-hero">
		<div class="tc-shell">
			<?php timonconfigs_breadcrumbs(); ?>
			<div class="tc-vip-hero__grid">
				<div><p class="tc-eyebrow"><span></span><?php echo esc_html( $content['eyebrow'] ); ?></p><h1><?php echo esc_html( $content['headline'] ); ?></h1><p class="tc-lead"><?php echo esc_html( $content['intro'] ); ?></p><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( $plan ) ); ?>"><?php echo esc_html( $content['cta'] ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Browse configs</a></div></div>
				<aside class="tc-vip-summary"><p class="tc-price-card__name"><?php echo esc_html( strtoupper( $content['name'] ) ); ?></p><p class="tc-price"><strong><?php echo esc_html( $content['price'] ); ?></strong><span><?php echo esc_html( $content['suffix'] ); ?></span></p><ul><?php foreach ( $content['features'] as $feature ) : ?><li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore ?><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul></aside>
			</div>
		</div>
	</section>
	<section class="tc-section"><div class="tc-shell tc-vip-details"><article><p class="tc-eyebrow"><span></span>WHO IT IS FOR</p><h2>Choose access with clear terms.</h2><p><?php echo esc_html( $content['best_for'] ); ?></p></article><article><p class="tc-eyebrow"><span></span>BILLING</p><h2>Checkout stays with Upgrade.chat.</h2><p><?php echo esc_html( $content['billing'] ); ?></p></article></div></section>
	<section class="tc-section tc-comparison"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>HOW ACCESS WORKS</p><h2>From checkout to your Discord role.</h2></div><p>Use the Discord account that should receive access. Upgrade.chat remains responsible for payment and VIP role delivery.</p></div><div class="tc-access-steps"><article><span>01</span><h3>Choose your plan</h3><p>Review the current price and terms before leaving TimonConfigs.</p></article><article><span>02</span><h3>Complete checkout</h3><p>Pay through the official Upgrade.chat flow using its available payment methods.</p></article><article><span>03</span><h3>Access VIP</h3><p>Your current Discord membership and roles determine access to protected material.</p></article></div></div></section>
	<section class="tc-section tc-final-cta"><div class="tc-shell tc-final-cta__inner"><div><p class="tc-eyebrow"><span></span><?php echo esc_html( strtoupper( $content['name'] ) ); ?></p><h2>Ready to join?</h2><p>Final pricing, renewal, cancellation, and license terms are shown at Upgrade.chat before payment.</p></div><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( $plan ) ); ?>"><?php echo esc_html( $content['cta'] ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div></section>
</main>
