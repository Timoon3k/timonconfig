<?php
/** Shared editable plan copy; payment amounts remain in the billing catalogue. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

function timonconfigs_plan_defaults(): array {
	return array(
		'free' => array( 'name' => 'Free', 'tagline' => 'Perfect to get started', 'suffix' => 'Forever', 'description' => 'Explore selected configs and join the community.', 'features' => array( 'Selected free configurations', 'Basic config library', 'Community Discord access' ), 'cta' => 'Get Free Configs' ),
		'monthly' => array( 'name' => 'VIP Monthly', 'tagline' => 'Unique scripts. Monthly access.', 'suffix' => '/ month', 'description' => 'Access unique VIP scripts, covered updates while subscribed, and private VIP support.', 'features' => array( 'Access to unique VIP scripts', 'Regular updates while subscribed', 'Private VIP Discord channels', 'Monthly recurring access' ), 'cta' => 'Choose VIP Monthly' ),
		'lifetime' => array( 'name' => 'VIP Lifetime', 'tagline' => 'One payment. Lifetime license.', 'suffix' => 'one time', 'description' => 'Access unique VIP scripts with one payment under the Lifetime license.', 'features' => array( 'Access to unique VIP scripts', 'Updates covered by your license', 'Private VIP Discord channels', 'One-time payment' ), 'cta' => 'Get Lifetime Access' ),
	);
}

/** Library quantity and valuation supplied by the owner, separate from the purchase price. */
function timonconfigs_vip_library_copy(): string {
	return (string) get_theme_mod( 'timonconfigs_vip_library_copy', 'Access to 250 unique VIP scripts. Library valued at €10,000 by TimonConfigs.' );
}
