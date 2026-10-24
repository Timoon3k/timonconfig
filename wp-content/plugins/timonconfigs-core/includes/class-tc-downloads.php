<?php
/** Session-bound, one-use private downloads. @package TimonConfigs_Core */
defined( 'ABSPATH' ) || exit;

final class TC_Downloads {
	private const TTL = 60;
	/** Synthetic file ID; only an authorized XIM Matrix Values export may use it. */
	public const MATRIX_TEXT_ID = '00000000-0000-4000-8000-000000000001';
	private const CLAIM_PREFIX = '_tc_download_claim_';
	private const RECENT_META = '_tc_recent_downloads';
	private const COUNT_PREFIX = '_tc_delivery_count_';

	public static function init(): void {
		add_action( 'admin_post_tc_request_download', array( __CLASS__, 'request' ) );
		add_action( 'admin_post_nopriv_tc_request_download', array( __CLASS__, 'request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'deliver' ), 0 );
		add_action( 'init', array( __CLASS__, 'protect_cache' ), 0 );
		add_action( 'tc_download_cleanup', array( __CLASS__, 'cleanup' ) );
		add_action( 'deleted_post', static function ( int $id ): void { delete_option( self::COUNT_PREFIX . $id ); } );
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


	/** XIM Matrix values can be exported as a private, plain UTF-8 TXT code. */
	private static function matrix_text( int $config_id ): string|WP_Error {
		if ( 'tc_config' !== get_post_type( $config_id ) || 'publish' !== get_post_status( $config_id ) || 'available' !== get_post_meta( $config_id, 'tc_release_status', true ) || ! has_term( 'xim-matrix', 'tc_device', $config_id ) || ! class_exists( 'TC_Config_Content' ) ) {
			return self::error( 'tc_download_matrix', 'This XIM Matrix text code is not available.' );
		}
		$code = TC_Config_Content::read( $config_id, 'values' );
		if ( is_wp_error( $code ) || ! is_string( $code['text'] ?? null ) || '' === trim( $code['text'] ) || strlen( $code['text'] ) > 100000 ) {
			return self::error( 'tc_download_matrix', 'A verified XIM Matrix Values text code is required.' );
		}
		return $code['text'];
	}

	/** The URL has no storage path and works only with the issuing login session. */
	public static function mint( int $config_id, string $file_id ): string|WP_Error {
		$binding = self::binding();
		if ( is_wp_error( $binding ) ) { return $binding; }
		$allowed = TC_Discord::can_access( $config_id );
		if ( true !== $allowed ) { return self::error( 'tc_download_access', 'Your verified membership does not currently include this release.' ); }

		$extra = array();
		if ( self::MATRIX_TEXT_ID === $file_id ) {
			$text = self::matrix_text( $config_id );
			if ( is_wp_error( $text ) ) { return $text; }
			$extra['h'] = hash( 'sha256', $text );
		} else {
			$path = TC_Private_Files::resolve( $config_id, $file_id );
			if ( is_wp_error( $path ) ) { return self::error( 'tc_download_file', 'This file is not currently available.' ); }
			$verified = false;
			foreach ( TC_Private_Files::files( $config_id ) as $file ) { if ( $file['id'] === $file_id && preg_match( '/^[a-f0-9]{64}$/D', $file['checksum'] ?? '' ) ) { $verified = true; break; } }
			if ( ! $verified ) { return self::error( 'tc_download_integrity', 'The publisher must revalidate this legacy file before downloading.' ); }
		}
		$payload = self::encode( (string) wp_json_encode( array_merge( array( 'v' => 1, 'u' => get_current_user_id(), 'b' => get_current_blog_id(), 'c' => $config_id, 'f' => $file_id, 'e' => time() + self::TTL, 'n' => bin2hex( random_bytes( 24 ) ), 's' => $binding ), $extra ) ) );
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

		if ( self::MATRIX_TEXT_ID === $data['f'] ) {
			$text = self::matrix_text( $data['c'] );
			if ( is_wp_error( $text ) || ! is_string( $data['h'] ?? null ) || ! hash_equals( hash( 'sha256', $text ), $data['h'] ) ) {
				return self::error( 'tc_download_matrix', 'The XIM Matrix code has changed or is no longer accessible. Request a new download.' );
			}
			$name = sanitize_file_name( sanitize_title( get_the_title( $data['c'] ) ) . '-xim-matrix.txt' );
			return array( 'text' => $text, 'config_id' => $data['c'], 'file' => array( 'name' => $name ), 'user_id' => get_current_user_id() );
		}
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

		if ( isset( $prepared['text'] ) ) {
			// No private path or content is included in the public catalogue or signed URL.
			while ( ob_get_level() ) { ob_end_clean(); }
			$name = $prepared['file']['name'];
			header( 'Content-Type: text/plain; charset=UTF-8' );
			header( 'Content-Length: ' . strlen( $prepared['text'] ) );
			header( 'Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
			echo $prepared['text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- deliberately served as a plain-text attachment.
			if ( ! connection_aborted() ) { self::record( $prepared['user_id'], $prepared['config_id'], $name ); }
			exit;
		}
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
		self::increment_count( $config_id );
		$recent = self::recent( $user_id, 49 );
		array_unshift( $recent, array( 'config_id' => $config_id, 'title' => get_the_title( $config_id ), 'file_name' => $name, 'downloaded_at' => time() ) );
		update_user_meta( $user_id, self::RECENT_META, $recent );
	}

	/** Aggregate completed server deliveries only; no identity, IP or retroactive estimate. */
	private static function increment_count( int $config_id ): void {
		if ( 'tc_config' !== get_post_type( $config_id ) ) { return; }
		global $wpdb;
		$key = self::COUNT_PREFIX . $config_id;
		// option_name is unique in WordPress, so concurrent first/increment requests are atomic.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = LEAST(CAST(option_value AS UNSIGNED), 2147483646) + 1", $key ) );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_set_posts_last_changed(); // Invalidate cached catalogue queries sorted by this aggregate.
	}

	public static function count( int $config_id ): int {
		if ( 'tc_config' !== get_post_type( $config_id ) || 'publish' !== get_post_status( $config_id ) ) { return 0; }
		return min( 2147483647, max( 0, (int) get_option( self::COUNT_PREFIX . $config_id, 0 ) ) );
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
