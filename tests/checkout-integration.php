<?php
/** Pure checkout validation; never contacts a payment provider or completes a transaction. */
defined( 'ABSPATH' ) || exit;
$checks = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
$assert( 'https://upgrade.chat/1557854161805115402' === TC_Checkout::sanitize_checkout_url( 'https://upgrade.chat/1557854161805115402' ), 'The supplied official store URL remains allowed.' );
foreach ( array( 'http://upgrade.chat/example', 'https://upgrade.chat.evil.example/example', 'https://evilupgrade.chat/example', 'https://user:pass@upgrade.chat/example', 'https://upgrade.chat:444/example', 'javascript:alert(1)', array( 'unexpected' ), null, false ) as $bad_url ) {
	$assert( '' === TC_Checkout::sanitize_checkout_url( $bad_url ), 'Invalid scheme/host/credentials/port/type is rejected.' );
}
$assert( '' === TC_Checkout::get_checkout_url( 'unknown-plan' ), 'Unknown plan cannot create a checkout handoff.' );
$assert( str_ends_with( TC_Checkout::get_checkout_url( 'monthly' ), '/checkout/monthly/' ), 'Monthly handoff retains the existing route.' );
$assert( str_ends_with( TC_Checkout::get_checkout_url( 'lifetime' ), '/checkout/lifetime/' ), 'Lifetime handoff retains the existing route.' );
$assert( array() === TC_Settings::sanitize_urls( array( 'monthly' => array( 'bad' ), 'lifetime' => 'https://evil.example/' ) ), 'Settings reject malformed values without warnings.' );
WP_CLI::success( sprintf( 'Checkout validation: %d assertions passed without transactions.', $checks ) );
