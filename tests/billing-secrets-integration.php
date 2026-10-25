<?php
/** Disposable localhost only: synthetic credentials, real WordPress storage/crypto, no network. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Billing_Secrets' ) ) { require WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-billing-secrets.php'; }
foreach ( array( 'test', 'live' ) as $mode ) {
	foreach ( TC_Billing_Secrets::names() as $name ) {
		if ( defined( 'TIMONCONFIGS_BILLING_' . strtoupper( $mode ) . '_' . $name ) ) { throw new RuntimeException( 'Fresh CLI process without billing credential constants required.' ); }
	}
}
define( 'TIMONCONFIGS_BILLING_TEST_PAYPAL_CLIENT_ID', 'synthetic-local-constant-client' );
define( 'TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN', '' );

global $wpdb;
$option = 'timonconfigs_billing_secrets';
$old_record = $wpdb->get_row( $wpdb->prepare( "SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name = %s", $option ), ARRAY_A );
$original_user = get_current_user_id(); $old_https = $_SERVER['HTTPS'] ?? null;
$uids = array(); $checks = 0; $network = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Billing credentials: ' . $message ); } ++$checks; };
$must = static function ( mixed $value ): mixed { if ( is_wp_error( $value ) ) { throw new RuntimeException( 'Unexpected credential fixture error: ' . $value->get_error_code() ); } return $value; };
$flush = static function () use ( $option ): void { wp_cache_delete( $option, 'options' ); wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' ); };
$store = static function ( mixed $value ) use ( $wpdb, $option, $flush ): void {
	$serialized = maybe_serialize( $value );
	if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload='off'", $option, $serialized ) ) ) { throw new RuntimeException( 'Could not write disposable encrypted-store fixture.' ); }
	$flush();
};
$raw = static fn(): mixed => get_option( $option, false );
$payload = static fn( array $values = array(), array $clear = array() ): array => array( '_wpnonce' => wp_create_nonce( 'tc_billing_secrets' ), 'credentials' => $values, 'clear' => $clear );
$error_is = static fn( mixed $result, string $reason ): bool => is_wp_error( $result ) && 'tc_billing_credentials_' . $reason === $result->get_error_code();
$block_http = static function () use ( &$network ): void { ++$network; throw new RuntimeException( 'Credential configuration must never contact a provider.' ); };
add_filter( 'pre_http_request', $block_http, 1 );
$home = null;
// Capture by reference so the same filter can exercise both local and HTTPS production contexts.
$home_filter = static function ( mixed $value ) use ( &$home ): mixed { return $home ?? $value; };
add_filter( 'option_home', $home_filter );
try {
	delete_option( $option ); $flush();
	foreach ( array( 'administrator', 'subscriber' ) as $role ) {
		$uid = $must( wp_insert_user( array( 'user_login' => 'tc_credentials_' . $role . '_' . bin2hex( random_bytes( 8 ) ), 'user_pass' => wp_generate_password( 48 ), 'role' => $role ) ) ); $uids[ $role ] = (int) $uid;
	}
	TC_Billing_Secrets::init();
	$assert( 10 === has_action( 'admin_post_tc_billing_secrets', array( 'TC_Billing_Secrets', 'handle' ) ) && false === has_action( 'admin_post_nopriv_tc_billing_secrets' ), 'Only the authenticated dedicated POST handler is registered.' );
	$assert( ! isset( get_registered_settings()[ $option ] ), 'Encrypted credentials are not registered as a Settings/REST option.' );
	$assert( 5 === count( TC_Billing_Secrets::names() ) && '' === TC_Billing_Secrets::get( 'UNKNOWN', 'test' ) && '' === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'invalid' ), 'Credential names and environments are bounded.' );
	$salt_validator = new ReflectionMethod( TC_Billing_Secrets::class, 'server_salts_ready' );
	$assert( true === $salt_validator->invoke( null, str_repeat( 'server-held-key-', 4 ), str_repeat( 'server-held-salt-', 4 ) ), 'Independent server-held key/salt material can derive an encryption key.' );
	foreach ( array( array( '', str_repeat( 's', 64 ) ), array( str_repeat( 'k', 64 ), null ), array( 'put your unique phrase here', str_repeat( 's', 64 ) ), array( str_repeat( 'placeholder_', 4 ), str_repeat( 's', 64 ) ) ) as $pair ) {
		$assert( false === $salt_validator->invoke( null, ...$pair ), 'Missing/default server salt material cannot silently fall back to the database.' );
	}
	$assert( array( 'source' => 'none', 'configured' => false, 'unreadable' => false ) === TC_Billing_Secrets::info( 'STRIPE_SECRET_KEY', 'test' ), 'Absent credentials expose only an empty readiness status.' );
	$assert( 'synthetic-local-constant-client' === TC_Billing_Secrets::get( 'PAYPAL_CLIENT_ID', 'test' ) && 'constant' === TC_Billing_Secrets::info( 'PAYPAL_CLIENT_ID', 'test' )['source'], 'A server constant is read without persisting or exposing it in status metadata.' );
	wp_set_current_user( 0 );
	$assert( $error_is( TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => 'synthetic-unauthorized' ) ) ) ), 'permission' ), 'Anonymous callers cannot save credentials.' );
	wp_set_current_user( $uids['subscriber'] );
	$assert( $error_is( TC_Billing_Secrets::perform( $payload() ), 'permission' ), 'A subscriber with its own nonce cannot save credentials.' );
	wp_set_current_user( $uids['administrator'] );
	$assert( $error_is( TC_Billing_Secrets::perform( array( '_wpnonce' => 'invalid' ) ), 'nonce' ) && false === $raw(), 'Forged nonce does not create a secret option.' );
	$assert( $error_is( TC_Billing_Secrets::perform( array( '_wpnonce' => array( 'invalid' ) ) ), 'nonce' ), 'Malformed nonce is safely rejected.' );
	$home = 'https://credentials-fixture.example.test'; $_SERVER['HTTPS'] = 'off';
	$assert( ! TC_Billing_Secrets::secure_transport() && $error_is( TC_Billing_Secrets::perform( $payload() ), 'https' ), 'A claimed HTTPS website alone is insufficient without a secure request.' );
	$home = 'http://credentials-fixture.example.test'; $_SERVER['HTTPS'] = 'on';
	$assert( ! TC_Billing_Secrets::secure_transport() && $error_is( TC_Billing_Secrets::perform( $payload() ), 'https' ), 'A canonical non-HTTPS production website cannot save credentials.' );
	$home = 'http://localhost.evil.example'; $_SERVER['HTTPS'] = 'off';
	$assert( ! TC_Billing_Secrets::secure_transport(), 'Localhost-looking external hosts do not receive the development exception.' );
	$home = 'https://credentials-fixture.example.test'; $_SERVER['HTTPS'] = 'on';
	$assert( TC_Billing_Secrets::secure_transport(), 'A canonical HTTPS website with a secure request is accepted.' );
	$home = null;
	$assert( TC_Billing_Secrets::secure_transport(), 'The strict disposable localhost fixture can save without TLS.' );
	$invalid = array(
		array_merge( $payload(), array( 'unexpected' => 'synthetic-private-payload' ) ),
		$payload( array( 'unknown' => array( 'STRIPE_SECRET_KEY' => 'synthetic-private-payload' ) ) ),
		$payload( array( 'test' => array( 'UNKNOWN' => 'synthetic-private-payload' ) ) ),
		$payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => array( 'synthetic-private-payload' ) ) ) ),
		$payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => str_repeat( 'x', 4097 ) ) ) ),
		$payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => "synthetic\nprivate-payload" ) ) ),
		$payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => 'synthetic-private-payload' ) ), array( 'test' => array( 'STRIPE_SECRET_KEY' => '1' ) ) ),
		$payload( array(), array( 'live' => array( 'UNKNOWN' => '1' ) ) ),
		$payload( array(), array( 'test' => array( 'STRIPE_SECRET_KEY' => 'yes' ) ) ),
	);
	foreach ( $invalid as $attempt ) {
		ob_start(); $error = TC_Billing_Secrets::perform( $attempt ); $output = (string) ob_get_clean();
		$assert( $error_is( $error, 'input' ) && '' === $output && ! str_contains( wp_json_encode( $error ), 'synthetic-private-payload' ) && false === $raw(), 'Invalid payloads are atomic, silent and never echo credential data.' );
	}
	$assert( $error_is( TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'PAYPAL_CLIENT_ID' => 'synthetic-db-override' ) ) ) ), 'managed' ), 'A server-managed credential cannot be replaced from a form.' );
	$assert( $error_is( TC_Billing_Secrets::perform( $payload( array(), array( 'live' => array( 'DISCORD_BOT_TOKEN' => '1' ) ) ) ), 'managed' ), 'An empty server constant still prevents a database replacement or clear.' );
	$values = array(
		'test' => array( 'STRIPE_SECRET_KEY' => 'sk_test_synthetic_local_credential', 'STRIPE_WEBHOOK_SECRET' => 'whsec_synthetic_local_credential', 'PAYPAL_CLIENT_SECRET' => 'synthetic-local-paypal-secret', 'DISCORD_BOT_TOKEN' => 'synthetic.local.bot-token' ),
		'live' => array( 'STRIPE_SECRET_KEY' => 'sk_live_synthetic_local_credential' ),
	);
	ob_start(); $saved = TC_Billing_Secrets::perform( $payload( $values ) ); $output = (string) ob_get_clean();
	$assert( true === $must( $saved ) && '' === $output, 'An administrator can store synthetic credentials without emitting any values.' );
	$encrypted_store = $raw();
	$assert( 1 === $encrypted_store['version'] && ! str_contains( wp_json_encode( $encrypted_store ), 'synthetic' ), 'The database contains ciphertext envelopes, never plaintext tokens.' );
	foreach ( $values as $mode => $credentials ) {
		foreach ( $credentials as $name => $plain ) {
			$assert( $plain === TC_Billing_Secrets::get( $name, $mode ) && array( 'source' => 'database', 'configured' => true, 'unreadable' => false ) === TC_Billing_Secrets::info( $name, $mode ), 'Encrypted round-trip and value-free status are correct for each context.' );
		}
	}
	$assert( in_array( $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) ), array( 'off', 'no', 'auto-off' ), true ) && ! array_key_exists( $option, wp_load_alloptions( true ) ), 'Saved credentials are excluded from autoloaded options.' );
	$assert( true === $must( TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => '   ' ) ) ) ) ) && $encrypted_store === $raw(), 'An empty password field keeps the exact saved ciphertext.' );
	$assert( true === $must( TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => $values['test']['STRIPE_SECRET_KEY'] ) ) ) ) ) && $encrypted_store['values']['test']['STRIPE_SECRET_KEY'] !== $raw()['values']['test']['STRIPE_SECRET_KEY'], 'Saving identical plaintext generates a fresh random nonce.' );
	$clean_store = $raw();
	$home = 'https://cloned-credentials-fixture.example.test'; $_SERVER['HTTPS'] = 'on';
	$assert( '' === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ) && TC_Billing_Secrets::info( 'STRIPE_SECRET_KEY', 'test' )['unreadable'], 'A database copied to another canonical origin cannot decrypt the saved credentials.' );
	$home = null;
	$assert( $values['test']['STRIPE_SECRET_KEY'] === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ), 'The original origin retains decryptability after a cloned-origin lookup.' );
	$rotated_salt = static fn( string $salt, string $scheme ): string => 'auth' === $scheme ? 'process-only-rotated-fixture:' . $salt : $salt;
	add_filter( 'salt', $rotated_salt, 100, 2 );
	try { $assert( '' === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ) && TC_Billing_Secrets::info( 'STRIPE_SECRET_KEY', 'test' )['unreadable'], 'Changing derived salt material makes credentials unreadable rather than enabling a plaintext fallback.' ); }
	finally { remove_filter( 'salt', $rotated_salt, 100 ); }
	$assert( $values['test']['STRIPE_SECRET_KEY'] === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ), 'A read-only salt-rotation simulation leaves the real server salts and stored values untouched.' );
	$swapped = $clean_store; $swapped['values']['live']['STRIPE_SECRET_KEY'] = $clean_store['values']['test']['STRIPE_SECRET_KEY']; $store( $swapped );
	$assert( '' === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'live' ) && TC_Billing_Secrets::info( 'STRIPE_SECRET_KEY', 'live' )['unreadable'], 'A ciphertext moved between Test and Live cannot authenticate.' );
	$swapped = $clean_store; $swapped['values']['test']['STRIPE_WEBHOOK_SECRET'] = $clean_store['values']['test']['STRIPE_SECRET_KEY']; $store( $swapped );
	$assert( '' === TC_Billing_Secrets::get( 'STRIPE_WEBHOOK_SECRET', 'test' ) && TC_Billing_Secrets::info( 'STRIPE_WEBHOOK_SECRET', 'test' )['unreadable'], 'A ciphertext moved between credential names cannot authenticate.' );
	$tampered = $clean_store; $bytes = base64_decode( $tampered['values']['test']['STRIPE_SECRET_KEY'], true ); $bytes[15] = chr( ord( $bytes[15] ) ^ 1 ); $tampered['values']['test']['STRIPE_SECRET_KEY'] = base64_encode( $bytes ); $store( $tampered );
	$assert( '' === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ) && ! TC_Billing_Secrets::info( 'STRIPE_SECRET_KEY', 'test' )['configured'], 'Authentication-tag tampering fails closed instead of falling back to plaintext.' );
	$assert( true === $must( TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => $values['test']['STRIPE_SECRET_KEY'] ) ) ) ) ) && $values['test']['STRIPE_SECRET_KEY'] === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ), 'Re-entering a credential repairs an unreadable ciphertext.' );
	$corrupt = $clean_store; $corrupt['values']['test']['STRIPE_SECRET_KEY'] = 'sk_test_plaintext_fallback_is_forbidden'; $store( $corrupt );
	$assert( '' === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ) && TC_Billing_Secrets::info( 'STRIPE_SECRET_KEY', 'test' )['unreadable'], 'A plaintext-looking stored value is never accepted as a fallback.' );
	$constant_shadow = $clean_store; $constant_shadow['values']['live']['DISCORD_BOT_TOKEN'] = $clean_store['values']['test']['DISCORD_BOT_TOKEN']; $store( $constant_shadow );
	$assert( '' === TC_Billing_Secrets::get( 'DISCORD_BOT_TOKEN', 'live' ) && 'constant' === TC_Billing_Secrets::info( 'DISCORD_BOT_TOKEN', 'live' )['source'], 'An explicitly empty constant never falls through to saved database data.' );
	$store( $clean_store );
	$assert( true === $must( TC_Billing_Secrets::perform( $payload( array(), array( 'test' => array( 'STRIPE_WEBHOOK_SECRET' => '1' ) ) ) ) ) && '' === TC_Billing_Secrets::get( 'STRIPE_WEBHOOK_SECRET', 'test' ), 'Only an explicit clear removes the selected credential.' );
	$assert( $values['test']['STRIPE_SECRET_KEY'] === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ) && $values['live']['STRIPE_SECRET_KEY'] === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'live' ), 'Clear preserves all other names and environments.' );
	$before_failure = $raw(); $faulted = false;
	$fail_write = static function ( string $sql ) use ( $wpdb, $option, &$faulted ): string {
		if ( ! $faulted && str_starts_with( $sql, "UPDATE {$wpdb->options} SET option_value" ) && str_contains( $sql, $option ) ) { $faulted = true; return "UPDATE {$wpdb->options} SET tc_missing_credentials_fixture_column = 1"; }
		return $sql;
	};
	$suppress = $wpdb->suppress_errors( true ); add_filter( 'query', $fail_write );
	try { $failed = TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => 'synthetic-failed-write-never-stored' ) ) ) ); }
	finally { remove_filter( 'query', $fail_write ); $wpdb->suppress_errors( $suppress ); }
	$assert( $faulted && $error_is( $failed, 'storage' ) && $before_failure === $raw() && ! str_contains( wp_json_encode( $failed ), 'synthetic-failed-write-never-stored' ), 'A failed database write retains the complete previous store and emits only a safe diagnostic.' );
	$assert( true === $must( TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'PAYPAL_CLIENT_SECRET' => 'synthetic-concurrent-admin-value' ) ) ) ) ), 'Prepared a separate valid concurrent admin save.' );
	$concurrent_store = $raw(); $store( $before_failure ); $raced = false;
	$concurrent_save = static function ( string $sql ) use ( $wpdb, $option, $concurrent_store, $flush, &$raced ): string {
		if ( ! $raced && str_starts_with( $sql, "UPDATE {$wpdb->options} SET option_value" ) && str_contains( $sql, $option ) ) {
			$raced = true; $wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $concurrent_store ) ), array( 'option_name' => $option ) ); $flush();
		}
		return $sql;
	};
	add_filter( 'query', $concurrent_save );
	try { $conflict = TC_Billing_Secrets::perform( $payload( array( 'test' => array( 'STRIPE_SECRET_KEY' => 'synthetic-stale-admin-value' ) ) ) ); }
	finally { remove_filter( 'query', $concurrent_save ); }
	$assert( $raced && $error_is( $conflict, 'changed' ) && $concurrent_store === $raw() && 'synthetic-concurrent-admin-value' === TC_Billing_Secrets::get( 'PAYPAL_CLIENT_SECRET', 'test' ), 'Compare-and-swap protects an intervening administrator save from a stale form.' );
	$store( array( 'version' => 99, 'values' => array() ) );
	$assert( '' === TC_Billing_Secrets::get( 'STRIPE_SECRET_KEY', 'test' ) && TC_Billing_Secrets::info( 'STRIPE_SECRET_KEY', 'test' )['unreadable'] && $error_is( TC_Billing_Secrets::perform( $payload() ), 'storage' ), 'An unknown encrypted-store format is neither guessed nor overwritten.' );
	$assert( 0 === $network, 'Saving and reading credentials never contact any payment provider or Discord.' );
	WP_CLI::success( "Billing credentials integration: $checks assertions; synthetic secrets, real local encryption/storage, no provider calls." );
} finally {
	remove_filter( 'pre_http_request', $block_http, 1 ); remove_filter( 'option_home', $home_filter );
	if ( null === $old_https ) { unset( $_SERVER['HTTPS'] ); } else { $_SERVER['HTTPS'] = $old_https; }
	if ( $old_record ) {
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload=VALUES(autoload)", $option, $old_record['option_value'], $old_record['autoload'] ) );
	} else { $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) ); }
	$flush();
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $uids as $uid ) { wp_delete_user( $uid ); }
	wp_set_current_user( $original_user );
}
