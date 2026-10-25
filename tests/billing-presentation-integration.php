<?php
/**
 * Local WordPress with --skip-plugins=timonconfigs-core. Read-only fixtures.
 * Exercises real theme/member templates with the documented billing facade doubled.
 * Provider state, checkout authorization, and entitlements have separate real-class suites.
 */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost WordPress required.' );
}
if ( class_exists( 'TC_Billing' ) || class_exists( 'TC_Discord' ) ) {
	throw new RuntimeException( 'Run billing presentation with --skip-plugins=timonconfigs-core.' );
}

$checks = 0;
$expect = static function ( bool $ok, string $message ) use ( &$checks ): void {
	if ( ! $ok ) { throw new RuntimeException( 'Billing presentation: ' . $message ); }
	++$checks;
};
$expect( '€29.99' === timonconfigs_display_price( 'monthly' ) && '€80' === timonconfigs_display_price( 'lifetime' ), 'Plugin-free theme fallback uses the new EUR catalogue.' );
$expect( '€0' === timonconfigs_display_price( 'free' ) && '' === timonconfigs_display_price( 'unknown' ), 'Free and unknown plan fallbacks are bounded.' );

if ( ! class_exists( 'TC_Billing' ) ) {
	final class TC_Billing {
		public static bool $direct = false;
		public static bool $records = false;
		public static int $rendered_for = 0;
		public static function direct_enabled(): bool { return self::$direct; }
		public static function display_price( string $plan ): string {
			return ( self::$direct ? array( 'monthly' => '€29.99', 'lifetime' => '€80' ) : array( 'monthly' => '€40', 'lifetime' => '€100' ) )[ $plan ] ?? '';
		}
		public static function render_account( int $uid ): string {
			self::$rendered_for = $uid;
			return self::$direct || self::$records ? '<section class="tc-billing"><h2>Billing fixture</h2><p>Verified order and access sync status</p></section>' : '';
		}
	}
}
if ( ! class_exists( 'TC_Discord' ) ) {
	final class TC_Discord {
		public static function status( int $uid ): array { return array( 'state' => 'unverified', 'display_name' => 'Billing presentation member', 'checked_at' => 0, 'avatar' => '', 'discord_id' => '', 'error' => '' ); }
		public static function is_ready(): bool { return false; }
		public static function logout_url(): string { return home_url( '/account/' ); }
	}
}
if ( ! class_exists( 'TC_Checkout' ) ) {
	final class TC_Checkout {
		public static function get_destination( string $plan ): string { return 'https://upgrade.chat/fixture-' . $plan; }
	}
}
require_once WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-member-area.php';

$old_user = get_current_user_id();
$old_get = $_GET;
$old_query = $GLOBALS['wp_query'];
$old_main = $GLOBALS['wp_the_query'];
$old_post = $GLOBALS['post'] ?? null;
$wrong_price = static fn(): string => '€987.65';
$empty_faqs = static fn( mixed $posts, WP_Query $query ): mixed => 'tc_faq' === $query->get( 'post_type' ) ? array() : $posts;
$render = static function ( string $part, array $args = array() ): string {
	ob_start();
	get_template_part( $part, null, $args );
	return (string) ob_get_clean();
};
$price_filters = array( 'theme_mod_timonconfigs_plan_free_price', 'theme_mod_timonconfigs_plan_monthly_price', 'theme_mod_timonconfigs_plan_lifetime_price' );
foreach ( $price_filters as $filter ) { add_filter( $filter, $wrong_price ); }
add_filter( 'posts_pre_query', $empty_faqs, 10, 2 );

try {
	$_GET = array();
	$monthly_page = get_page_by_path( 'vip-monthly', OBJECT, 'page' );
	$lifetime_page = get_page_by_path( 'vip-lifetime', OBJECT, 'page' );
	if ( ! $monthly_page instanceof WP_Post || ! $lifetime_page instanceof WP_Post ) { throw new RuntimeException( 'Local VIP pages must exist before this read-only suite.' ); }
	foreach ( array( false, true ) as $direct ) {
		TC_Billing::$direct = $direct;
		$monthly = $direct ? '€29.99' : '€40';
		$lifetime = $direct ? '€80' : '€100';
		$expect( $monthly === timonconfigs_display_price( 'monthly' ) && $lifetime === timonconfigs_display_price( 'lifetime' ), 'The active billing catalogue owns both paid display amounts.' );
		$cards = $render( 'template-parts/pricing-cards' );
		$expect( str_contains( $cards, $monthly ) && str_contains( $cards, $lifetime ) && str_contains( $cards, '€0' ) && ! str_contains( $cards, '€987.65' ), 'Home/pricing cards ignore saved price overrides.' );
		ob_start(); include get_template_directory() . '/patterns/pricing.php'; $pattern = (string) ob_get_clean();
		$expect( str_contains( $pattern, $monthly ) && str_contains( $pattern, $lifetime ), 'Newly inserted patterns snapshot the current catalogue.' );
		$metadata = timonconfigs_seo_page_defaults();
		$expect( str_contains( $metadata['pricing']['description'], $monthly ) && str_contains( $metadata['pricing']['description'], $lifetime ) && str_contains( $metadata['vip-monthly']['title'], $monthly ) && str_contains( $metadata['vip-lifetime']['title'], $lifetime ), 'Default metadata agrees with visible pricing.' );
		$faq = timonconfigs_faq_items();
		$expect( str_contains( $faq[3]['answer'], $lifetime ), 'Default Lifetime FAQ agrees with the catalogue.' );
		foreach ( array( 'monthly' => $monthly_page, 'lifetime' => $lifetime_page ) as $plan => $page ) {
			$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page->ID ) );
			$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
			$GLOBALS['post'] = $page;
			setup_postdata( $page );
			$html = $render( 'template-parts/vip-plan', array( 'plan' => $plan ) );
			$expected = 'monthly' === $plan ? $monthly : $lifetime;
			$expect( str_contains( $html, $expected ) && ! str_contains( $html, '€987.65' ), 'Plan details ignore old price controls: ' . $plan );
			$products = array_values( array_filter( timonconfigs_schema_graph(), static fn( array $node ): bool => 'Product' === $node['@type'] ) );
			$expect( 1 === count( $products ) && substr( $expected, strlen( '€' ) ) === $products[0]['offers']['price'] && 'EUR' === $products[0]['offers']['priceCurrency'], 'Schema Offer agrees with the visible amount: ' . $plan );
		}
	}
	$GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main;
	$users = get_users( array( 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $users ) { throw new RuntimeException( 'A local user is required for read-only account rendering.' ); }
	wp_set_current_user( (int) $users[0] );
	foreach ( array( array( false, false ), array( true, false ), array( false, true ) ) as $case ) {
		[ TC_Billing::$direct, TC_Billing::$records ] = $case;
		$html = TC_Member_Area::render();
		$has_billing = $case[0] || $case[1];
		$expect( get_current_user_id() === TC_Billing::$rendered_for, 'Account renderer receives the current user only.' );
		$expect( $has_billing === str_contains( $html, 'class="tc-billing"' ), 'Direct routing or retained direct records show the billing renderer.' );
		$expect( $has_billing !== str_contains( $html, 'class="tc-member-management"' ), 'Legacy management appears only without direct routing/data.' );
		$expect( ! str_contains( $html, '€987.65' ) && str_contains( $html, 'tc-member-profile' ), 'Account keeps identity UI and ignores old price controls.' );
	}
	wp_set_current_user( 0 );
	$guest = TC_Member_Area::render();
	$expect( ! str_contains( $guest, 'Billing fixture' ) && ! str_contains( $guest, 'Billing presentation member' ), 'Guest rendering exposes no billing or profile data.' );
	WP_CLI::success( "Billing presentation: $checks assertions; real templates, facade double, no DB writes or provider calls." );
} finally {
	foreach ( $price_filters as $filter ) { remove_filter( $filter, $wrong_price ); }
	remove_filter( 'posts_pre_query', $empty_faqs, 10 );
	wp_set_current_user( $old_user ); $_GET = $old_get;
	$GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main; $GLOBALS['post'] = $old_post;
	if ( $old_post instanceof WP_Post ) { setup_postdata( $old_post ); }
}
