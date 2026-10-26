<?php
/**
 * Plugin Name: TimonConfigs Core
 * Description: TimonConfigs private configuration library, Discord membership and website billing and protected membership access.
 * Version: 0.9.3
 * Author: TimonConfigs
 * Text Domain: timonconfigs-core
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMONCONFIGS_CORE_VERSION', '0.9.3' );
define( 'TIMONCONFIGS_CORE_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-tc-checkout.php';
require_once __DIR__ . '/includes/class-tc-catalog.php';
require_once __DIR__ . '/includes/class-tc-private-files.php';
require_once __DIR__ . '/includes/class-tc-discord.php';
require_once __DIR__ . '/includes/class-tc-downloads.php';
require_once __DIR__ . '/includes/class-tc-member-area.php';
require_once __DIR__ . '/includes/class-tc-billing-secrets.php';
require_once __DIR__ . '/includes/class-tc-billing-store.php';
require_once __DIR__ . '/includes/class-tc-billing-stripe.php';
require_once __DIR__ . '/includes/class-tc-billing-paypal.php';
require_once __DIR__ . '/includes/class-tc-billing-discord.php';
require_once __DIR__ . '/includes/class-tc-billing-ui.php';
require_once __DIR__ . '/includes/class-tc-billing.php';
require_once __DIR__ . '/includes/class-tc-config-content.php';
require_once __DIR__ . '/includes/class-tc-owner-editor.php';
require_once __DIR__ . '/includes/class-tc-settings.php';
require_once __DIR__ . '/includes/class-tc-editorial.php';
require_once __DIR__ . '/includes/class-tc-seo.php';
require_once __DIR__ . '/includes/class-tc-videos.php';
require_once __DIR__ . '/includes/class-tc-site.php';

add_action(
	'plugins_loaded',
	static function (): void {
		TC_Checkout::init();
		TC_Catalog::init();
		TC_Private_Files::init();
		TC_Discord::init();
		TC_Downloads::init();
		TC_Member_Area::init();
		TC_Config_Content::init();
		TC_Owner_Editor::init();
		TC_Settings::init();
		TC_Billing::init();
		TC_Editorial::init();
		TC_SEO::init();
		TC_Videos::init();
		TC_Site::init();
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
		TC_Videos::register_content_type();
		TC_Catalog::seed_flagship_drafts();
		TC_Site::install();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

