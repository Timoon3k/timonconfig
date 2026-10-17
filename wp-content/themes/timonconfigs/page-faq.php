<?php
/** Standalone FAQ. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main">
	<section class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span>HELP CENTER</p><h1>Questions before you choose a config?</h1><p class="tc-lead">Clear answers about devices, membership, checkout, updates, and access.</p></div></section>
	<section class="tc-section tc-faq"><div class="tc-shell tc-content-narrow">
		<h2>Configs and compatibility</h2>
		<details><summary>Which devices does TimonConfigs support?</summary><p>The current catalogue covers Cronus Zen and XIM Matrix. Check each config page for its device, platform, input method, version, and last verified date.</p></details>
		<details><summary>Which games are available?</summary><p>Apex Legends is the current flagship collection. Additional games will appear only when their releases and compatibility details are ready.</p></details>
		<details><summary>Do configs work with every setup?</summary><p>Results depend on your device, platform, game version, input method, and personal settings. Always review the compatibility details before choosing access.</p></details>
		<h2>Membership and checkout</h2>
		<details><summary>How does VIP checkout work?</summary><p>Purchase buttons send you to the official Upgrade.chat checkout. Upgrade.chat and its payment providers handle payment and Discord role delivery.</p></details>
		<details><summary>Does returning to this site confirm payment?</summary><p>No. A browser return never proves payment. Premium access must be verified from your current Discord membership and roles.</p></details>
		<details><summary>What is the difference between Monthly and Lifetime?</summary><p>VIP Monthly is a recurring €40 membership with covered updates while subscribed. VIP Lifetime is a €100 one-time purchase governed by the Lifetime license terms shown at checkout.</p></details>
		<details><summary>How do I cancel VIP Monthly?</summary><p>Manage the subscription through Upgrade.chat. The effective cancellation date and access changes follow the terms shown with your subscription.</p></details>
		<h2>Access and support</h2>
		<details><summary>Which Discord account should I use?</summary><p>Use the Discord account that should receive your membership role. Keeping the same account throughout checkout and access verification prevents avoidable delays.</p></details>
		<details><summary>Where can I get help?</summary><p>Use the contact page to reach the TimonConfigs community and support channels. Include the config name, device, platform, and version when reporting an issue.</p></details>
		<div class="tc-inline-cta"><h2>Still need help?</h2><p>Contact TimonConfigs or compare the plans before you continue.</p><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us</a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Compare plans</a></div></div>
	</div></section>
</main>
<?php get_footer(); ?>
