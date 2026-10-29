<?php
/** Read-only checkout contract: retired external URLs are ignored; never starts a purchase. */
defined( 'ABSPATH' ) || exit;
$checks = 0;
$original_user = get_current_user_id();
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
$retired_urls = array( 'monthly' => 'https://upgrade.chat/old-monthly', 'lifetime' => 'https://upgrade.chat/old-lifetime' );
$stale_options = static fn() => $retired_urls;
add_filter( 'pre_option_timonconfigs_checkout_urls', $stale_options );
try {
	foreach ( array( 'https://upgrade.chat/1557854161805115402', 'https://shop.upgrade.chat/old', 'http://upgrade.chat/example', 'https://upgrade.chat.evil.example/example', 'https://evilupgrade.chat/example', 'https://user:pass@upgrade.chat/example', 'https://upgrade.chat:444/example', 'https://checkout.stripe.com/fixture', home_url( '/checkout/monthly/' ), 'javascript:alert(1)', array( 'unexpected' ), null, false ) as $retired_url ) {
		$assert( '' === TC_Checkout::sanitize_checkout_url( $retired_url ), 'Retired redirect sanitization cannot accept any provider or local destination.' );
	}
	foreach ( array( 'monthly', 'lifetime', 'unknown-plan' ) as $plan ) {
		$assert( '' === TC_Checkout::get_destination( $plan ), 'Stored external destinations are ignored for ' . $plan . '.' );
	}
	$assert( '' === TC_Checkout::get_checkout_url( 'unknown-plan' ), 'Unknown plan cannot create a checkout route.' );
	foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
		$assert( home_url( '/checkout/' . $plan . '/' ) === TC_Checkout::get_checkout_url( $plan ), 'Ticket Tool can link to the preserved local ' . $plan . ' route.' );
	}
	$vars = TC_Checkout::register_query_var( array( 'existing' ) );
	$assert( in_array( 'existing', $vars, true ) && in_array( 'tc_checkout_plan', $vars, true ), 'Local checkout routes preserve existing query variables.' );
	$assert( $retired_urls === get_option( 'timonconfigs_checkout_urls' ), 'Resolving local routes does not rewrite historical checkout options.' );
	if ( is_callable( array( 'TC_Settings', 'register_settings' ) ) ) { TC_Settings::register_settings(); }
	$assert( ! isset( get_registered_settings()['timonconfigs_checkout_urls'] ), 'Retired provider URLs are not exposed as a writable WordPress option.' );
	wp_set_current_user( 0 ); ob_start(); TC_Settings::render_page(); $guest_settings = (string) ob_get_clean();
	$assert( '' === $guest_settings, 'Anonymous callers cannot render checkout administration.' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $admins ) { throw new RuntimeException( 'Existing local administrator required for read-only settings rendering.' ); }
	wp_set_current_user( (int) $admins[0] ); ob_start(); TC_Settings::render_page(); $settings = (string) ob_get_clean();
	foreach ( array( '/pricing/', '/checkout/monthly/', '/checkout/lifetime/' ) as $route ) { $assert( str_contains( $settings, esc_attr( home_url( $route ) ) ), 'Ticket Tool copy panel provides the actual local ' . $route . ' address.' ); }
	$assert( 3 === substr_count( $settings, ' readonly ' ) && ! str_contains( $settings, '<form' ), 'Checkout link settings are read-only and cannot overwrite payment configuration.' );
	$assert( str_contains( $settings, esc_url( admin_url( 'options-general.php?page=tc-woo' ) ) ) && ! str_contains( strtolower( $settings ), 'upgrade.chat' ), 'The panel links to the actual billing settings and never advertises a retired checkout.' );
	WP_CLI::success( sprintf( 'Checkout validation: %d assertions passed without transactions or database writes.', $checks ) );
} finally {
	remove_filter( 'pre_option_timonconfigs_checkout_urls', $stale_options );
	wp_set_current_user( $original_user );
}
