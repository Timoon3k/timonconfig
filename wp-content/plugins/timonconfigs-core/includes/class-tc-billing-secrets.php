<?php
/** Write-only billing credentials, encrypted at rest with a server-held WordPress salt key. */
defined( 'ABSPATH' ) || exit;

final class TC_Billing_Secrets {
	private const OPTION = 'timonconfigs_billing_secrets';
	private const VERSION = 1;
	private const MAX_LENGTH = 4096;
	private const NAMES = array( 'STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET', 'PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'DISCORD_BOT_TOKEN' );

	public static function init(): void {
		add_action( 'admin_post_tc_billing_secrets', array( __CLASS__, 'handle' ) );
	}
	public static function names(): array { return self::NAMES; }
	public static function storage_available(): bool { return self::crypto_ready(); }
	private static function valid( string $name, string $mode ): bool { return in_array( $name, self::NAMES, true ) && in_array( $mode, array( 'test', 'live' ), true ); }
	private static function constant_name( string $name, string $mode ): string { return 'TIMONCONFIGS_BILLING_' . strtoupper( $mode ) . '_' . $name; }
	private static function empty_store(): array { return array( 'version' => self::VERSION, 'values' => array() ); }
	private static function valid_store( mixed $store ): bool {
		if ( ! is_array( $store ) || self::VERSION !== ( $store['version'] ?? null ) || ! is_array( $store['values'] ?? null ) || array_diff( array_keys( $store ), array( 'version', 'values' ) ) ) { return false; }
		foreach ( $store['values'] as $mode => $values ) {
			if ( ! in_array( $mode, array( 'test', 'live' ), true ) || ! is_array( $values ) || array_diff( array_keys( $values ), self::NAMES ) ) { return false; }
		}
		return true;
	}
	private static function server_salts_ready( mixed $key, mixed $salt ): bool {
		foreach ( array( $key, $salt ) as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) < 32 || preg_match( '/put[ _-]+your[ _-]+unique[ _-]+phrase[ _-]+here|change[ _-]*(?:me|this)|replace[ _-]*(?:me|this)|placeholder/i', $value ) ) { return false; }
		}
		return true;
	}
	private static function crypto_ready(): bool {
		// wp_salt may otherwise silently use a database-backed fallback, defeating DB-only protection.
		return defined( 'AUTH_KEY' ) && defined( 'AUTH_SALT' ) && self::server_salts_ready( AUTH_KEY, AUTH_SALT ) && function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) && function_exists( 'hash_hkdf' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
	}
	private static function key(): string { return hash_hkdf( 'sha256', wp_salt( 'auth' ), 32, 'timonconfigs-billing-credentials-v1', wp_salt( 'secure_auth' ) ); }
	private static function origin(): string {
		$home = get_option( 'home', '' ); $parts = is_string( $home ) ? wp_parse_url( $home ) : false;
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return ''; }
		$scheme = strtolower( $parts['scheme'] ); $host = strtolower( $parts['host'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) { return ''; }
		$port = $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 );
		if ( ! is_int( $port ) || $port < 1 || $port > 65535 ) { return ''; }
		return $scheme . '://' . $host . ':' . $port;
	}
	private static function aad( string $name, string $mode ): string { return 'tc-billing-credentials-v1' . "\0" . self::origin() . "\0" . $mode . "\0" . $name; }
	private static function encrypt( string $plain, string $name, string $mode ): string {
		if ( ! self::crypto_ready() || '' === self::origin() ) { return ''; }
		try {
			$nonce = random_bytes( 12 ); $tag = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag, self::aad( $name, $mode ), 16 );
			return false === $cipher || 16 !== strlen( $tag ) ? '' : base64_encode( chr( self::VERSION ) . $nonce . $tag . $cipher );
		} catch ( Throwable $error ) { return ''; }
	}
	private static function decrypt( mixed $blob, string $name, string $mode ): string {
		if ( ! self::crypto_ready() || '' === self::origin() || ! is_string( $blob ) || strlen( $blob ) > 6000 ) { return ''; }
		$raw = base64_decode( $blob, true );
		if ( false === $raw || strlen( $raw ) < 30 || ord( $raw[0] ) !== self::VERSION ) { return ''; }
		try {
			$plain = openssl_decrypt( substr( $raw, 29 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 1, 12 ), substr( $raw, 13, 16 ), self::aad( $name, $mode ) );
			return is_string( $plain ) && '' !== $plain && strlen( $plain ) <= self::MAX_LENGTH ? $plain : '';
		} catch ( Throwable $error ) { return ''; }
	}

	/** Constants take precedence even when empty or malformed; there is no plaintext fallback. */
	public static function get( string $name, string $mode ): string {
		if ( ! self::valid( $name, $mode ) ) { return ''; }
		$constant = self::constant_name( $name, $mode );
		if ( defined( $constant ) ) { $value = constant( $constant ); return is_string( $value ) ? trim( $value ) : ''; }
		$store = get_option( self::OPTION, false );
		return self::valid_store( $store ) ? self::decrypt( $store['values'][ $mode ][ $name ] ?? null, $name, $mode ) : '';
	}
	/** Safe display metadata only: never a value, prefix, fingerprint or exception text. */
	public static function info( string $name, string $mode ): array {
		$empty = array( 'source' => 'none', 'configured' => false, 'unreadable' => false );
		if ( ! self::valid( $name, $mode ) ) { return $empty; }
		$constant = self::constant_name( $name, $mode );
		if ( defined( $constant ) ) { return array( 'source' => 'constant', 'configured' => '' !== self::get( $name, $mode ), 'unreadable' => ! is_string( constant( $constant ) ) ); }
		$store = get_option( self::OPTION, false );
		if ( false === $store ) { return $empty; }
		if ( ! self::valid_store( $store ) ) { return array( 'source' => 'database', 'configured' => false, 'unreadable' => true ); }
		if ( ! array_key_exists( $name, $store['values'][ $mode ] ?? array() ) ) { return $empty; }
		$readable = '' !== self::decrypt( $store['values'][ $mode ][ $name ], $name, $mode );
		return array( 'source' => 'database', 'configured' => $readable, 'unreadable' => ! $readable );
	}
	public static function secure_transport(): bool {
		if ( '' === self::origin() ) { return false; }
		$home = get_option( 'home', '' );
		$host = is_string( $home ) ? strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) ) : '';
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true ) || ( is_string( $home ) && 'https' === wp_parse_url( $home, PHP_URL_SCHEME ) && is_ssl() );
	}
	private static function error( string $reason ): WP_Error {
		$messages = array(
			'permission' => 'Administrator permission is required to save billing credentials.',
			'nonce' => 'Your configuration session expired. Reload this page and try again.',
			'https' => 'Save billing credentials over HTTPS. Verify the website address and HTTPS proxy settings.',
			'input' => 'The credential fields are invalid. Review them and try again.',
			'managed' => 'A server constant manages this credential. Change it in the server configuration.',
			'crypto' => 'Secure credential encryption is unavailable. The existing settings were retained.',
			'storage' => 'The credential store is unavailable. The existing settings were retained.',
			'changed' => 'Credentials changed during this request. Reload the page before trying again.',
		);
		return new WP_Error( 'tc_billing_credentials_' . $reason, $messages[ $reason ] ?? 'Billing credentials could not be saved.' );
	}

	/** Testable admin service. The web handler below additionally requires POST. */
	public static function perform( array $payload ): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) { return self::error( 'permission' ); }
		if ( ! is_string( $payload['_wpnonce'] ?? null ) || strlen( $payload['_wpnonce'] ) > 128 || ! wp_verify_nonce( $payload['_wpnonce'], 'tc_billing_secrets' ) ) { return self::error( 'nonce' ); }
		if ( ! self::secure_transport() ) { return self::error( 'https' ); }
		if ( array_diff( array_keys( $payload ), array( '_wpnonce', '_wp_http_referer', 'action', 'credentials', 'clear', 'submit' ) ) ) { return self::error( 'input' ); }
		if ( isset( $payload['action'] ) && 'tc_billing_secrets' !== $payload['action'] ) { return self::error( 'input' ); }
		foreach ( array( '_wp_http_referer' => 2048, 'submit' => 256 ) as $key => $limit ) {
			if ( isset( $payload[ $key ] ) && ( ! is_string( $payload[ $key ] ) || strlen( $payload[ $key ] ) > $limit ) ) { return self::error( 'input' ); }
		}
		$credentials = $payload['credentials'] ?? array(); $clear = $payload['clear'] ?? array();
		if ( ! is_array( $credentials ) || ! is_array( $clear ) ) { return self::error( 'input' ); }
		$changes = array();
		foreach ( array( 'credentials' => $credentials, 'clear' => $clear ) as $kind => $modes ) {
			foreach ( $modes as $mode => $values ) {
				if ( ! in_array( $mode, array( 'test', 'live' ), true ) || ! is_array( $values ) ) { return self::error( 'input' ); }
				foreach ( $values as $name => $value ) {
					if ( ! in_array( $name, self::NAMES, true ) ) { return self::error( 'input' ); }
					if ( 'credentials' === $kind ) {
						if ( ! is_string( $value ) || strlen( $value ) > self::MAX_LENGTH ) { return self::error( 'input' ); }
						$value = trim( $value );
						if ( '' !== $value && preg_match( '/[^\x21-\x7e]/', $value ) ) { return self::error( 'input' ); }
						if ( '' !== $value ) { $changes[ $mode ][ $name ]['value'] = $value; }
					} else {
						if ( ! in_array( $value, array( true, 1, '1', false, 0, '0', '' ), true ) ) { return self::error( 'input' ); }
						if ( in_array( $value, array( true, 1, '1' ), true ) ) { $changes[ $mode ][ $name ]['clear'] = true; }
					}
				}
			}
		}
		foreach ( $changes as $mode => $values ) {
			foreach ( $values as $name => $change ) {
				if ( defined( self::constant_name( $name, $mode ) ) ) { return self::error( 'managed' ); }
				if ( ! empty( $change['clear'] ) && isset( $change['value'] ) ) { return self::error( 'input' ); }
			}
		}
		global $wpdb;
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name = %s", self::OPTION ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) { return self::error( 'storage' ); }
		$before = $record ? maybe_unserialize( $record['option_value'] ) : self::empty_store();
		if ( ! self::valid_store( $before ) ) { return self::error( 'storage' ); }
		$next = $before;
		foreach ( $changes as $mode => $values ) {
			foreach ( $values as $name => $change ) {
				if ( ! empty( $change['clear'] ) ) { unset( $next['values'][ $mode ][ $name ] ); continue; }
				$encrypted = self::encrypt( $change['value'], $name, $mode );
				if ( '' === $encrypted ) { return self::error( 'crypto' ); }
				$next['values'][ $mode ][ $name ] = $encrypted;
			}
		}
		if ( ! $record ) {
			if ( ! $changes ) { return true; }
			return add_option( self::OPTION, $next, '', false ) ? true : self::error( 'changed' );
		}
		if ( $next === $before && in_array( $record['autoload'], array( 'off', 'no', 'auto-off' ), true ) ) { return true; }
		// A concurrent admin save must never be silently overwritten by a stale blank form.
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s, autoload = 'off' WHERE option_name = %s AND BINARY option_value = BINARY %s", maybe_serialize( $next ), self::OPTION, $record['option_value'] ) );
		if ( false === $result ) { return self::error( 'storage' ); }
		if ( 1 !== $result ) { return self::error( 'changed' ); }
		wp_cache_delete( self::OPTION, 'options' ); wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' );
		return true;
	}
	public static function handle(): void {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true ); header( 'Referrer-Policy: no-referrer', true );
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'Use POST to save billing credentials.', '', array( 'response' => 405 ) ); }
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator permission is required.', '', array( 'response' => 403 ) ); }
		$result = self::perform( wp_unslash( $_POST ) );
		$feedback = true === $result ? 'saved' : ( is_wp_error( $result ) ? $result->get_error_code() : 'tc_billing_credentials_storage' );
		set_transient( 'tc_billing_credentials_feedback_' . get_current_user_id(), $feedback, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=tc-billing#tc-billing-credentials' ) );
		exit;
	}
}
