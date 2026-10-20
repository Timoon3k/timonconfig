<?php
/** Template Name: Pricing @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><p class="tc-eyebrow"><span></span>MEMBERSHIP</p><h1>Clear plans. Serious value.</h1><p class="tc-lead">Choose the access level that fits you. Checkout and Discord role management remain securely handled by Upgrade.chat.</p></div></section>
	<section class="tc-section tc-section--topless"><div class="tc-shell"><?php get_template_part( 'template-parts/pricing-cards' ); ?><p class="tc-checkout-note">Use the same Discord account at Upgrade.chat that you want to use for TimonConfigs access. Signing in here does not automatically sign you in to Upgrade.chat.</p></div></section>
	<section class="tc-section tc-comparison"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow"><span></span>COMPARE ACCESS</p><h2>What each plan includes</h2></div></div><div class="tc-table-wrap"><table><thead><tr><th>Feature</th><th>Free</th><th>VIP Monthly</th><th>VIP Lifetime</th></tr></thead><tbody><tr><th>Selected free configs</th><td>Included</td><td>Included</td><td>Included</td></tr><tr><th>Premium config library</th><td>—</td><td>Included</td><td>Included</td></tr><tr><th>Private VIP channels</th><td>—</td><td>Included</td><td>Included</td></tr><tr><th>Billing</th><td>Free</td><td>Monthly</td><td>One time</td></tr><tr><th>Updates</th><td>Selected releases</td><td>While subscribed</td><td>As covered by license</td></tr></tbody></table></div></div></section>
	<section class="tc-section tc-faq" id="faq"><div class="tc-shell tc-content-narrow"><p class="tc-eyebrow"><span></span>FAQ</p><h2>Before you join</h2><details><summary>How does checkout work?</summary><p>Purchase buttons redirect you to the official Upgrade.chat checkout. Stripe or PayPal payment and Discord role delivery are handled there.</p></details><details><summary>Does returning to this site confirm payment?</summary><p>No. TimonConfigs never grants access from a browser return alone. Access is verified against your current Discord membership and roles.</p></details><details><summary>Can I cancel VIP Monthly?</summary><p>Your subscription is managed through Upgrade.chat. Cancellation affects access according to the billing and membership terms shown at checkout.</p></details><details><summary>What does Lifetime include?</summary><p>Lifetime access follows the license terms presented at purchase, including the updates explicitly covered by that license.</p></details></div></section>
</main>
<?php get_footer(); ?>

