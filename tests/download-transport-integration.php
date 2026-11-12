<?php
/** Frontend download request boundaries; disposable localhost and reversible fixtures only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Downloads' ) || ! is_callable( array( 'TC_Downloads', 'request_url' ) ) || ! is_callable( array( 'TC_Downloads', 'dispatch' ) ) ) { throw new RuntimeException( 'Load the frontend download transport first.' ); }

$checks = 0; $post_id = 0; $denied_user = 0; $http_calls = 0;
$old_user = get_current_user_id(); $old_get = $_GET; $old_post = $_POST; $old_request = $_REQUEST; $old_server = $_SERVER; $old_cookie = $_COOKIE;
$queue_exists = false !== get_option( 'timonconfigs_page_seo_queue', false ); $old_queue = get_option( 'timonconfigs_page_seo_queue', false );
$scheduled = wp_next_scheduled( 'tc_download_cleanup' );
$tag = wp_generate_uuid4(); $die_message = ''; $die_status = 0; $die_link = '';
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Download transport: ' . $message ); } ++$checks; };
$home_filter = static fn(): string => 'https://download.example.invalid/store';
$admin_filter = static fn(): string => 'https://admin.example.invalid/private-admin/admin-post.php';
$no_http = static function () use ( &$http_calls ): WP_Error { ++$http_calls; return new WP_Error( 'tc_download_fixture_http', 'Outbound requests disabled in this transport fixture.' ); };
$die_filter = static function () use ( &$die_message, &$die_status, &$die_link ): Closure {
	return static function ( mixed $message, mixed $title = '', mixed $args = array() ) use ( &$die_message, &$die_status, &$die_link ): never {
		$die_message = wp_strip_all_tags( is_scalar( $message ) ? (string) $message : '' );
		$die_status = is_array( $args ) ? (int) ( $args['response'] ?? 500 ) : ( is_int( $args ) ? $args : 500 );
		$die_link = is_array( $args ) && is_string( $args['link_url'] ?? null ) ? $args['link_url'] : '';
		throw new RuntimeException( 'tc_download_transport_finished' );
	};
};
$invoke = static function ( array $get, array $post = array(), string $method = 'GET' ) use ( &$die_message, &$die_status, &$die_link ): array {
	$_GET = $get; $_POST = $post; $_REQUEST = array_merge( $get, $post ); $_SERVER['REQUEST_METHOD'] = $method;
	$die_message = ''; $die_status = 0; $die_link = ''; ob_start();
	try { TC_Downloads::dispatch(); }
	catch ( RuntimeException $error ) { if ( 'tc_download_transport_finished' !== $error->getMessage() ) { throw $error; } }
	finally { $body = ob_get_clean(); }
	return array( 'status' => $die_status, 'message' => $die_message, 'body' => $body, 'link' => $die_link );
};
add_filter( 'pre_http_request', $no_http, 1, 3 );
add_filter( 'wp_die_handler', $die_filter, 999 );
try {
	$assert( 0 === has_action( 'parse_request', array( 'TC_Downloads', 'dispatch' ) ), 'Download dispatch runs before the main public page query.' );
	$assert( false !== has_action( 'admin_post_tc_request_download', array( 'TC_Downloads', 'request' ) ) && false !== has_action( 'admin_post_nopriv_tc_request_download', array( 'TC_Downloads', 'request' ) ), 'Previously rendered admin-post forms retain their authenticated handler and guest denial.' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $admins ) { throw new RuntimeException( 'An existing local administrator is required.' ); }
	wp_set_current_user( (int) $admins[0] ); unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	$post_id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'LOCAL QA download transport fixture', 'post_content' => 'LOCAL QA only; no licensed file or public download.', 'meta_input' => array( '_tc_download_transport_fixture' => $tag, 'tc_release_status' => 'available', 'tc_tier' => 'free' ) ), true );
	if ( is_wp_error( $post_id ) ) { throw new RuntimeException( 'Could not create the marked transport fixture.' ); }
	$file_id = wp_generate_uuid4();
	add_filter( 'option_home', $home_filter ); add_filter( 'admin_url', $admin_filter );
	$url = TC_Downloads::request_url(); $parts = wp_parse_url( $url ); parse_str( $parts['query'] ?? '', $query );
	$assert( 'https' === $parts['scheme'] && 'download.example.invalid' === $parts['host'] && '/store/' === $parts['path'] && array( 'tc_download_request' => '1' ) === $query, 'The request URL uses the exact public home origin and installation path.' );
	$assert( false === strpos( $url, 'admin.example.invalid' ) && false === strpos( $url, '/wp-admin/' ), 'A different administration host/path cannot redirect member download POSTs away from their public session.' );
	$_SERVER['REQUEST_URI'] = '/store/config/local-qa/';
	ob_start(); TC_Downloads::render_button( $post_id, $file_id, 'Download QA file' ); $html = ob_get_clean();
	$assert( str_contains( $html, 'action="' . esc_url( $url ) . '"' ) && str_contains( $html, 'method="post"' ), 'The actual public download form posts to the frontend request URL.' );
	$assert( str_contains( $html, 'name="action" value="tc_request_download"' ) && str_contains( $html, 'name="config_id" value="' . $post_id . '"' ) && str_contains( $html, 'name="file_id" value="' . $file_id . '"' ), 'The new transport preserves the action, release ID and opaque file ID.' );
	preg_match( '/name="_wpnonce" value="([^"]+)"/', $html, $matches ); $nonce = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
	$assert( '' !== $nonce && false !== wp_verify_nonce( $nonce, 'tc_download_' . $post_id . '_' . $file_id ), 'The rendered button retains its session/user-specific file-and-release nonce.' );
	$assert( ! str_contains( $html, 'stored_name' ) && ! str_contains( $html, '/var/lib/' ) && ! str_contains( $html, 'tc_download=' ), 'The public form contains no storage path or pre-minted one-use token.' );
	remove_filter( 'option_home', $home_filter ); remove_filter( 'admin_url', $admin_filter );
	$before_count = get_option( '_tc_delivery_count_' . $post_id, false );
	$assert( 0 === $invoke( array(), array( 'unrelated' => 'value' ), 'POST' )['status'], 'Normal frontend POSTs are not intercepted as download requests.' );
	foreach ( array( array( 'tc_download_request' => '0' ), array( 'tc_download_request' => '' ), array( 'tc_download_request' => array( '1' ) ) ) as $get ) {
		$result = $invoke( $get );
		$assert( $result['status'] >= 400 && $result['status'] < 500, 'Malformed or ambiguous frontend request markers fail closed.' );
		$assert( ! preg_match( '/Warning:|Fatal error:|Deprecated:/', $result['body'] . $result['message'] ), 'Malformed query arrays reveal no PHP diagnostics.' );
	}
	foreach ( array( 'GET', 'HEAD', 'PUT' ) as $method ) {
		$result = $invoke( array( 'tc_download_request' => '1' ), array(), $method );
		$assert( 405 === $result['status'], 'Minting a frontend download requires POST, including for ' . $method . '.' );
	}
	wp_set_current_user( 0 );
	$payload = array( 'action' => 'tc_request_download', 'config_id' => (string) $post_id, 'file_id' => $file_id, '_wpnonce' => $nonce );
	$assert( 403 === $invoke( array( 'tc_download_request' => '1' ), $payload, 'POST' )['status'], 'Guests cannot request an attachment even with a copied valid administrator nonce.' );
	wp_set_current_user( (int) $admins[0] );
	foreach ( array( '', 'wrong-nonce', array( 'malformed' ) ) as $invalid ) {
		$payload['_wpnonce'] = $invalid; $result = $invoke( array( 'tc_download_request' => '1' ), $payload, 'POST' );
		$assert( 403 === $result['status'] && ! preg_match( '/Warning:|Fatal error:/', $result['message'] . $result['body'] ), 'Missing, invalid and array-shaped nonces are denied before any private file preparation.' );
	}
	$payload['_wpnonce'] = wp_create_nonce( 'tc_download_' . $post_id . '_' . $file_id );
	$result = $invoke( array( 'tc_download_request' => '1' ), $payload, 'POST' );
	$assert( 403 === $result['status'] && str_contains( strtolower( $result['message'] ), 'sign in' ), 'A WordPress user without a live bound login session cannot obtain a signed download.' );
	$denied_user = wp_insert_user( array( 'user_login' => 'tc_download_transport_' . str_replace( '-', '', $tag ), 'user_pass' => wp_generate_password( 64, true, true ), 'role' => 'subscriber' ) );
	if ( is_wp_error( $denied_user ) ) { throw new RuntimeException( 'Could not create a non-entitled local actor.' ); }
	update_user_meta( $denied_user, '_tc_download_transport_fixture', $tag );
	$session = WP_Session_Tokens::get_instance( $denied_user )->create( time() + 3600 );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $denied_user, time() + 3600, 'logged_in', $session ); wp_set_current_user( $denied_user );
	$payload['_wpnonce'] = wp_create_nonce( 'tc_download_' . $post_id . '_' . $file_id );
	$result = $invoke( array( 'tc_download_request' => '1' ), $payload, 'POST' );
	$assert( 403 === $result['status'] && str_contains( $result['message'], 'verified membership' ) && '' === $result['body'], 'A real signed-in Subscriber without verified release entitlement still receives a denial, not attachment bytes.' );
	$assert( home_url( '/account/' ) === $result['link'], 'Denied delivery retains the account recovery link instead of forcing a download.' );
	foreach ( array( 'POST', 'HEAD' ) as $method ) { $assert( 405 === $invoke( array( 'tc_download' => 'invalid' ), array(), $method )['status'], 'Signed delivery requires GET and rejects ' . $method . ' without consuming a claim.' ); }
	$assert( 403 === $invoke( array( 'tc_download' => 'invalid' ) )['status'], 'An invalid signed token is denied by the early public dispatcher.' );
	$_GET = array( 'tc_download_request' => '1' ); $_POST = array(); TC_Downloads::protect_cache();
	$assert( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'The public request marker bypasses page caching even before a POST action is available.' );
	$assert( $before_count === get_option( '_tc_delivery_count_' . $post_id, false ) && $scheduled === wp_next_scheduled( 'tc_download_cleanup' ), 'Denied requests record no delivery and create no one-use claims cleanup job.' );
	$assert( 0 === $http_calls, 'Request validation makes no Discord or other external requests.' );
	WP_CLI::success( "Download transport integration: $checks assertions passed; local denial boundaries and reversible fixture only." );
} finally {
	remove_filter( 'option_home', $home_filter ); remove_filter( 'admin_url', $admin_filter ); remove_filter( 'pre_http_request', $no_http, 1 ); remove_filter( 'wp_die_handler', $die_filter, 999 );
	wp_set_current_user( 0 );
	if ( is_int( $post_id ) && $post_id > 0 && get_post_meta( $post_id, '_tc_download_transport_fixture', true ) === $tag ) { wp_delete_post( $post_id, true ); }
	if ( is_int( $denied_user ) && $denied_user > 0 && get_user_meta( $denied_user, '_tc_download_transport_fixture', true ) === $tag ) { WP_Session_Tokens::get_instance( $denied_user )->destroy_all(); delete_option( '_tc_discord_lock_' . $denied_user ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $denied_user ); }
	$queue_exists ? update_option( 'timonconfigs_page_seo_queue', $old_queue, false ) : delete_option( 'timonconfigs_page_seo_queue' );
	wp_set_current_user( $old_user ); $_GET = $old_get; $_POST = $old_post; $_REQUEST = $old_request; $_SERVER = $old_server; $_COOKIE = $old_cookie;
}
