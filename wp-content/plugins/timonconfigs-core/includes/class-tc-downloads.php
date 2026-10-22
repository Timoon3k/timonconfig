<?php
/** Session-bound, one-use private downloads. @package TimonConfigs_Core */
defined( 'ABSPATH' ) || exit;

final class TC_Downloads {
	private const TTL = 60;
	private const CLAIM_PREFIX = '_tc_download_claim_';
	private const RECENT_META = '_tc_recent_downloads';

	public static function init(): void {
		add_action( 'admin_post_tc_request_download', array( __CLASS__, 'request' ) );
		add_action( 'admin_post_nopriv_tc_request_download', array( __CLASS__, 'request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'deliver' ), 0 );
		add_action( 'init', array( __CLASS__, 'protect_cache' ), 0 );
		add_action( 'tc_download_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	public static function protect_cache(): void {
		if ( isset( $_GET['tc_download'] ) || ( isset( $_POST['action'] ) && 'tc_request_download' === $_POST['action'] ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		}
	}

	private static function headers(): void {
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	private static function error( string $code, string $message ): WP_Error {
		return new WP_Error( $code, $message );
	}

	private static function binding(): string|WP_Error {
		$user = get_current_user_id();
		$token = wp_get_session_token();
		if ( ! $user || ! $token || ! WP_Session_Tokens::get_instance( $user )->verify( $token ) ) {
			return self::error( 'tc_download_session', 'Please sign in again before downloading.' );
		}
		return hash_hmac( 'sha256', $user . '|' . get_current_blog_id() . '|' . $token, wp_salt( 'logged_in' ) );
	}

	private static function encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/** The URL has no storage path and works only with the issuing login session. */
	public static function mint( int $config_id, string $file_id ): string|WP_Error {
		$binding = self::binding();
		if ( is_wp_error( $binding ) ) { return $binding; }
		$allowed = TC_Discord::can_access( $config_id );
		if ( true !== $allowed ) { return self::error( 'tc_download_access', 'Your verified membership does not currently include this release.' ); }
		$path = TC_Private_Files::resolve( $config_id, $file_id );
		if ( is_wp_error( $path ) ) { return self::error( 'tc_download_file', 'This file is not currently available.' ); }
		$verified = false;
		foreach ( TC_Private_Files::files( $config_id ) as $file ) { if ( $file['id'] === $file_id && preg_match( '/^[a-f0-9]{64}$/D', $file['checksum'] ?? '' ) ) { $verified = true; break; } }
		if ( ! $verified ) { return self::error( 'tc_download_integrity', 'The publisher must revalidate this legacy file before downloading.' ); }
		$payload = self::encode( (string) wp_json_encode( array( 'v' => 1, 'u' => get_current_user_id(), 'b' => get_current_blog_id(), 'c' => $config_id, 'f' => $file_id, 'e' => time() + self::TTL, 'n' => bin2hex( random_bytes( 24 ) ), 's' => $binding ) ) );
		return $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/** Server-only preparation; never expose this result through REST or HTML. */
	public static function prepare( mixed $token ): array|WP_Error {
		if ( ! is_string( $token ) || strlen( $token ) > 2048 || ! preg_match( '/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D', $token, $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[1], wp_salt( 'auth' ) ), $parts[2] ) ) {
			return self::error( 'tc_download_signature', 'This download link is invalid or has expired.' );
		}
		$decoded = base64_decode( strtr( $parts[1], '-_', '+/' ), true );
		$data = false !== $decoded ? json_decode( $decoded, true, 8 ) : null;
		if ( ! is_array( $data ) || 1 !== ( $data['v'] ?? null ) || ! is_int( $data['u'] ?? null ) || $data['u'] !== get_current_user_id() || ! $data['u'] || ( $data['b'] ?? null ) !== get_current_blog_id() || ! is_int( $data['c'] ?? null ) || $data['c'] <= 0 || ! is_string( $data['f'] ?? null ) || ! preg_match( '/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D', $data['f'] ) || ! is_int( $data['e'] ?? null ) || $data['e'] <= time() || $data['e'] > time() + self::TTL || ! is_string( $data['n'] ?? null ) || ! preg_match( '/^[a-f0-9]{48}$/D', $data['n'] ) || ! is_string( $data['s'] ?? null ) ) {
			return self::error( 'tc_download_payload', 'This download link is invalid or has expired.' );
		}
		$binding = self::binding();
		if ( is_wp_error( $binding ) || ! hash_equals( $binding, $data['s'] ) ) { return self::error( 'tc_download_session', 'This link belongs to another or expired login session.' ); }
		// A unique option name is an atomic database claim, unlike get/delete transient.
		$claim = self::CLAIM_PREFIX . hash( 'sha256', $data['n'] );
		if ( ! add_option( $claim, $data['e'], '', false ) ) { return self::error( 'tc_download_replay', 'This download link was already used. Request a new download.' ); }
		if ( ! wp_next_scheduled( 'tc_download_cleanup' ) ) { wp_schedule_single_event( time() + self::TTL + 30, 'tc_download_cleanup' ); }
		// Reuse only the current bounded role cache (60s); expired/error states deny.
		// Manual Refresh Roles has its own throttle and must not block multi-file use.
		$allowed = TC_Discord::can_access( $data['c'], get_current_user_id() );
		if ( true !== $allowed ) { return self::error( 'tc_download_access', 'Your verified membership does not currently include this release. Refresh your account and try again.' ); }
		$path = TC_Private_Files::resolve( $data['c'], $data['f'] );
		if ( is_wp_error( $path ) ) { return self::error( 'tc_download_file', 'This file is not currently available.' ); }
		$metadata = null;
		foreach ( TC_Private_Files::files( $data['c'] ) as $file ) { if ( $file['id'] === $data['f'] ) { $metadata = $file; break; } }
		if ( ! $metadata || ! preg_match( '/^[a-f0-9]{64}$/D', $metadata['checksum'] ?? '' ) ) { return self::error( 'tc_download_file', 'This file is not currently available.' ); }
		return array( 'path' => $path, 'config_id' => $data['c'], 'file' => $metadata, 'user_id' => get_current_user_id() );
	}

	public static function request(): void {
		self::headers();
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { self::fail( 'Use the download button on the release page.', 405 ); }
		$id = isset( $_POST['config_id'] ) && is_scalar( $_POST['config_id'] ) ? absint( $_POST['config_id'] ) : 0;
		$file = isset( $_POST['file_id'] ) && is_string( $_POST['file_id'] ) ? sanitize_text_field( wp_unslash( $_POST['file_id'] ) ) : '';
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! get_current_user_id() || ! wp_verify_nonce( $nonce, 'tc_download_' . $id . '_' . $file ) ) { self::fail( 'Sign in and use the download button on the release page.', 403 ); }
		$token = self::mint( $id, $file );
		if ( is_wp_error( $token ) ) { self::fail( $token->get_error_message(), 403 ); }
		wp_safe_redirect( add_query_arg( 'tc_download', $token, home_url( '/' ) ), 303 );
		exit;
	}

	private static function fail( string $message, int $status ): never {
		wp_die( esc_html( $message ), esc_html__( 'Download unavailable', 'timonconfigs-core' ), array( 'response' => $status, 'link_url' => home_url( '/account/' ), 'link_text' => 'Open your account' ) );
	}

	public static function deliver(): void {
		if ( ! isset( $_GET['tc_download'] ) ) { return; }
		self::headers();
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { self::fail( 'Use a new download link from the release page.', 405 ); }
		$prepared = self::prepare( is_string( $_GET['tc_download'] ) ? wp_unslash( $_GET['tc_download'] ) : null );
		if ( is_wp_error( $prepared ) ) { self::fail( $prepared->get_error_message(), 403 ); }
		$handle = @fopen( $prepared['path'], 'rb' );
		if ( false === $handle ) { self::fail( 'This file is not currently available.', 404 ); }
		$stat = fstat( $handle );
		$metadata = $prepared['file'];
		if ( ! $stat || ( $stat['mode'] & 0170000 ) !== 0100000 || (int) $stat['size'] !== (int) $metadata['size'] ) { fclose( $handle ); self::fail( 'This file changed. Request the current release.', 409 ); }
		$hash = hash_init( 'sha256' );
		$hashed = @hash_update_stream( $hash, $handle );
		if ( false === $hashed || ! hash_equals( $metadata['checksum'], hash_final( $hash ) ) || ! rewind( $handle ) ) { fclose( $handle ); self::fail( 'This file changed. Request the current release.', 409 ); }
		while ( ob_get_level() ) { ob_end_clean(); }
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Length: ' . (int) $stat['size'] );
		$name = sanitize_file_name( $metadata['name'] );
		$fallback = preg_replace( '/[^A-Za-z0-9._-]/', '_', $name ) ?: 'timonconfigs-script';
		header( 'Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		$sent = fpassthru( $handle );
		fclose( $handle );
		if ( false !== $sent && (int) $sent === (int) $stat['size'] && ! connection_aborted() ) { self::record( $prepared['user_id'], $prepared['config_id'], $name ); }
		exit;
	}

	public static function render_button( int $config_id, string $file_id, string $label = 'Download' ): void {
		echo '<form class="tc-download-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="tc_request_download"><input type="hidden" name="config_id" value="' . esc_attr( (string) $config_id ) . '"><input type="hidden" name="file_id" value="' . esc_attr( $file_id ) . '">';
		wp_nonce_field( 'tc_download_' . $config_id . '_' . $file_id );
		echo '<button class="tc-button" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	private static function record( int $user_id, int $config_id, string $name ): void {
		$recent = self::recent( $user_id, 49 );
		array_unshift( $recent, array( 'config_id' => $config_id, 'title' => get_the_title( $config_id ), 'file_name' => $name, 'downloaded_at' => time() ) );
		update_user_meta( $user_id, self::RECENT_META, $recent );
	}

	public static function recent( int $user_id, int $limit = 10 ): array {
		if ( $user_id !== get_current_user_id() || $user_id <= 0 ) { return array(); }
		$records = get_user_meta( $user_id, self::RECENT_META, true );
		if ( ! is_array( $records ) ) { return array(); }
		$safe = array();
		foreach ( array_slice( $records, 0, 50 ) as $item ) {
			if ( ! is_array( $item ) || ! is_scalar( $item['config_id'] ?? null ) || ! is_string( $item['title'] ?? null ) || ! is_string( $item['file_name'] ?? null ) || ! is_scalar( $item['downloaded_at'] ?? null ) ) { continue; }
			$safe[] = array( 'config_id' => absint( $item['config_id'] ), 'title' => sanitize_text_field( $item['title'] ), 'file_name' => sanitize_file_name( basename( $item['file_name'] ) ), 'downloaded_at' => absint( $item['downloaded_at'] ) );
		}
		return array_slice( $safe, 0, min( 50, max( 1, $limit ) ) );
	}

	public static function cleanup(): void {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT 1000", $wpdb->esc_like( self::CLAIM_PREFIX ) . '%', time() ) );
		foreach ( $names as $name ) { delete_option( $name ); } // Also evicts persistent object-cache entries.
		if ( 1000 === count( $names ) && ! wp_next_scheduled( 'tc_download_cleanup' ) ) { wp_schedule_single_event( time() + 30, 'tc_download_cleanup' ); }
	}
}
