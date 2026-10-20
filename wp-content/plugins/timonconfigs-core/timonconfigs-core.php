<?php
/**
 * Plugin Name: TimonConfigs Core
 * Description: Business features for TimonConfigs, including managed Upgrade.chat checkout links.
 * Version: 0.3.0
 * Author: TimonConfigs
 * Text Domain: timonconfigs-core
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMONCONFIGS_CORE_VERSION', '0.3.0' );
define( 'TIMONCONFIGS_CORE_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-tc-checkout.php';
require_once __DIR__ . '/includes/class-tc-catalog.php';
require_once __DIR__ . '/includes/class-tc-settings.php';
require_once __DIR__ . '/includes/class-tc-editorial.php';

add_action(
	'plugins_loaded',
	static function (): void {
		TC_Checkout::init();
		TC_Catalog::init();
		TC_Settings::init();
		TC_Editorial::init();
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		TC_Checkout::register_rewrite_rules();
		TC_Catalog::register_content_types();
		TC_Catalog::register_landing_rewrites();
		TC_Catalog::seed_core_terms();
		TC_Editorial::register_content_types();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

