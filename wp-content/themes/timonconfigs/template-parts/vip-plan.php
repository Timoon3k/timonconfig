<?php
/** VIP plan landing content. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

$plan = isset( $args['plan'] ) && 'lifetime' === $args['plan'] ? 'lifetime' : 'monthly';
$monthly = 'monthly' === $plan;
$plan_defaults = timonconfigs_plan_defaults()[ $plan ];
$content = $monthly
	? array(
		'name'        => 'VIP Monthly',
		'eyebrow'     => 'FULL PREMIUM ACCESS',
		'headline'    => 'Premium configs that stay current.',
		'price'       => timonconfigs_display_price( 'monthly' ),
		'suffix'      => 'per month',
		'intro'       => $plan_defaults['description'],
		'cta'         => 'Continue to VIP Monthly checkout',
		'features'    => $plan_defaults['features'],
		'best_for'    => 'Players who want current premium releases, ongoing updates, and the flexibility of a monthly membership.',
		'billing'     => 'This is a recurring monthly subscription. Review the amount, renewal, and consent before continuing with an available hosted payment provider. Your account provides website subscription management.',
	)
	: array(
		'name'        => 'VIP Lifetime',
		'eyebrow'     => 'ONE-TIME LICENSE',
		'headline'    => 'Long-term access with one payment.',
		'price'       => timonconfigs_display_price( 'lifetime' ),
		'suffix'      => 'one time',
		'intro'       => $plan_defaults['description'],
		'cta'         => 'Continue to VIP Lifetime checkout',
		'features'    => $plan_defaults['features'],
		'best_for'    => 'Players who prefer a single payment and access governed by the Lifetime license rather than a recurring subscription.',
		'billing'     => 'This is a one-time purchase. Review the exact access and update scope in the Lifetime license terms before secure hosted checkout.',
	);
$content['name']   = (string) get_theme_mod( 'timonconfigs_plan_' . $plan . '_name', $content['name'] );
$content['suffix'] = (string) get_theme_mod( 'timonconfigs_plan_' . $plan . '_suffix', $content['suffix'] );
$content['intro']  = (string) get_theme_mod( 'timonconfigs_plan_' . $plan . '_description', $content['intro'] );
$features = get_theme_mod( 'timonconfigs_plan_' . $plan . '_features', implode( "\n", $content['features'] ) );
$content['features'] = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $features ) ?: array() ) ) );
$editor_content = (string) get_post_field( 'post_content', get_queried_object_id() );
?>
<main id="main">
	<section class="tc-page-hero tc-vip-hero">
		<div class="tc-shell">
			<?php timonconfigs_breadcrumbs(); ?>
			<div class="tc-vip-hero__grid">
				<div><p class="tc-eyebrow"><span></span><?php echo esc_html( $content['eyebrow'] ); ?></p><h1><?php the_title(); ?></h1><p class="tc-lead"><?php echo esc_html( $content['intro'] ); ?></p><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( $plan ) ); ?>"><?php echo esc_html( $content['cta'] ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Browse configs</a></div></div>
				<aside class="tc-vip-summary"><p class="tc-price-card__name"><?php echo esc_html( strtoupper( $content['name'] ) ); ?></p><p class="tc-price"><strong><?php echo esc_html( $content['price'] ); ?></strong><span><?php echo esc_html( $content['suffix'] ); ?></span></p><p class="tc-price-card__library"><?php echo esc_html( timonconfigs_vip_library_copy() ); ?></p><ul><?php foreach ( $content['features'] as $feature ) : ?><li><?php echo timonconfigs_icon( 'check' ); // phpcs:ignore ?><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul></aside>
			</div>
		</div>
	</section>
	<section class="tc-section"><div class="tc-shell tc-vip-details"><article><p class="tc-eyebrow"><span></span>WHO IT IS FOR</p><h2>Choose access with clear terms.</h2><?php if ( '' !== trim( $editor_content ) ) : ?><div class="tc-entry__content"><?php echo apply_filters( 'the_content', $editor_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php else : ?><p><?php echo esc_html( $content['best_for'] ); ?></p><?php endif; ?></article><article><p class="tc-eyebrow"><span></span>BILLING</p><h2>Review your checkout and billing.</h2><p><?php echo esc_html( $content['billing'] ); ?></p><p><?php if ( $monthly ) : ?>Manage website subscription cancellation in your account before the next renewal. Your current paid period remains available after cancellation. Contact membership support for earlier purchases.<?php else : ?>There are no recurring monthly payments for this license.<?php endif; ?></p><a class="tc-text-link" href="<?php echo esc_url( home_url( $monthly ? '/refund-policy/' : '/terms-of-service/#lifetime-license' ) ); ?>"><?php echo esc_html( $monthly ? 'Cancellation and refunds' : 'Read the Lifetime license terms' ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article></div></section>
	<section class="tc-section tc-comparison"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>COMPARE PLANS</p><h2>Choose how you access the library.</h2></div><a class="tc-text-link" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Full pricing comparison <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div><div class="tc-table-wrap"><table><thead><tr><th scope="col">Access</th><th scope="col">Free</th><th scope="col">VIP Monthly</th><th scope="col">VIP Lifetime</th></tr></thead><tbody><tr><th scope="row">Library</th><td>Selected free materials</td><td>Covered premium materials</td><td>Materials covered by the license</td></tr><tr><th scope="row">Billing</th><td>No charge</td><td>Recurring monthly subscription</td><td>One-time purchase</td></tr><tr><th scope="row">Updates</th><td>Selected releases</td><td>Covered while subscribed</td><td>As expressly covered by the license</td></tr><tr><th scope="row">Community</th><td>Public community</td><td>Private VIP channels and support</td><td>Private VIP channels and support</td></tr></tbody></table></div></div></section>
	<section class="tc-section tc-comparison"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>HOW ACCESS WORKS</p><h2>From checkout to your Discord role.</h2></div><p>Sign in with the Discord account that should receive access. Your account shows verified payments and Discord access sync status.</p></div><div class="tc-access-steps"><article><span>01</span><h3>Choose your plan</h3><p>Review the current price and terms on TimonConfigs before continuing.</p></article><article><span>02</span><h3>Complete checkout</h3><p>Review the plan and continue with an available payment provider through secure website checkout.</p></article><article><span>03</span><h3>Access VIP</h3><p>Your current Discord membership and roles determine access to protected material.</p></article></div></div></section>
	<section class="tc-section tc-faq"><div class="tc-shell tc-content-narrow"><p class="tc-eyebrow"><span></span>PLAN QUESTIONS</p><h2>Before choosing <?php echo esc_html( $content['name'] ); ?></h2><details><summary><?php echo esc_html( $monthly ? 'How do renewals and cancellation work?' : 'Does Lifetime have a monthly renewal?' ); ?></summary><p><?php echo esc_html( $monthly ? 'VIP Monthly renews each calendar month at the amount shown before checkout. Manage website subscription cancellation in your account to stop future renewals. Your current paid period remains available. Contact support for an earlier purchase.' : 'No. VIP Lifetime is a one-time purchase. Its access and update scope follows the Lifetime license accepted at checkout.' ); ?></p></details><details><summary>Which configurations are included?</summary><p>Check the current catalogue, each configuration’s access tier, and the purchase terms. Do not assume that every future product or separate release is included.</p></details><details><summary>Which Discord account receives VIP?</summary><p>The Discord account linked before checkout receives the membership. Returning to this website does not confirm a successful payment or grant access. Check your account for verified payment and access sync status.</p></details><details><summary>Where can I review the terms?</summary><p>Read our <a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>">Terms of Service</a>, <a href="<?php echo esc_url( home_url( '/refund-policy/' ) ); ?>">Refund Policy</a>, and <a href="<?php echo esc_url( home_url( '/terms-of-service/#lifetime-license' ) ); ?>">Lifetime License Terms</a>, together with the final terms displayed before hosted checkout.</p></details></div></section>
	<section class="tc-section tc-final-cta"><div class="tc-shell tc-final-cta__inner"><div><p class="tc-eyebrow"><span></span><?php echo esc_html( strtoupper( $content['name'] ) ); ?></p><h2>Ready to join?</h2><p>Review pricing, recurring billing where applicable, cancellation, and license terms before secure website checkout.</p></div><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( $plan ) ); ?>"><?php echo esc_html( $content['cta'] ); ?> <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div></section>
</main>
