<?php
/** Owner HTTP request boundaries on disposable localhost; no real files or provider writes. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost WordPress required.' );
}
if ( defined( 'DOING_AJAX' ) ) { throw new RuntimeException( 'Run this suite in a fresh WP-CLI process.' ); }

$checks = 0; $users = array(); $posts = array();
$original_user = get_current_user_id(); $original_get = $_GET; $original_post = $_POST; $original_files = $_FILES; $original_server = $_SERVER;
$version = get_option( 'timonconfigs_collections_version', null );
$tag = wp_generate_password( 12, false );
$discord_id = '8' . str_pad( (string) wp_rand( 100000000, 999999999 ), 17, '0', STR_PAD_LEFT );
$binding = '_tc_discord_binding_' . $discord_id;
$binding_created = false;
$expect = static function ( bool $ok, string $message ) use ( &$checks ): void {
	if ( ! $ok ) { throw new RuntimeException( 'Owner request: ' . $message ); } ++$checks;
};
$https = static fn(): string => 'https://localhost:8080';
$encrypt = static fn( string $value ): string => ( new ReflectionMethod( TC_Discord::class, 'encrypt' ) )->invoke( null, $value );
$settings = array( 'enabled' => 1, 'pilot_enabled' => 1, 'pilot_ids' => $discord_id, 'owner_ids' => $discord_id, 'pkce' => 0, 'client_id' => '899999999999999981', 'guild_id' => '899999999999999982', 'free_role' => '899999999999999983', 'monthly_role' => '899999999999999984', 'lifetime_role' => '899999999999999985', 'owner_role' => '899999999999999986', 'staff_role' => '899999999999999987', 'secret_encrypted' => $encrypt( 'request-test-only-secret' ) );
$settings_filter = static function () use ( &$settings ): array { return $settings; };
$http_calls = 0;
$http = static function () use ( &$http_calls ): WP_Error { ++$http_calls; return new WP_Error( 'owner_request_fixture', 'Outbound calls disabled in request tests.' ); };
$version_updates = 0;
$version_spy = static function ( mixed $value, mixed $old ) use ( &$version_updates ): mixed { ++$version_updates; return $old; };
$status_code = 0;
$status_spy = static function ( string $header, int $code ) use ( &$status_code ): string { $status_code = $code; return $header; };
$die_handler = static function (): never { throw new RuntimeException( 'tc_owner_request_json_complete' ); };
$die_filter = static fn(): Closure => $die_handler;

try {
	if ( false !== get_option( $binding, false ) ) { throw new RuntimeException( 'Fixture binding collision.' ); }
	foreach ( array( 'owner' => 'subscriber', 'admin' => 'administrator' ) as $kind => $role ) {
		$id = wp_insert_user( array( 'user_login' => 'tc_request_' . $tag . '_' . $kind, 'user_pass' => wp_generate_password( 64, true, true ), 'role' => $role ) );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Could not create request fixture actor.' ); }
		$users[ $kind ] = $id; update_user_meta( $id, '_tc_owner_request_fixture', $tag );
	}
	$binding_created = add_option( $binding, $users['owner'], '', false );
	if ( ! $binding_created ) { throw new RuntimeException( 'Could not reserve the test identity binding.' ); }
	update_user_meta( $users['owner'], '_tc_discord_id', $discord_id );
	update_user_meta( $users['owner'], '_tc_discord_token', $encrypt( wp_json_encode( array( 'access_token' => 'fixture-owner-access', 'refresh_token' => 'fixture-owner-refresh', 'expires' => time() + 3600 ) ) ) );
	add_filter( 'option_home', $https ); add_filter( 'pre_option_timonconfigs_discord', $settings_filter ); add_filter( 'pre_http_request', $http, 1, 3 );

	// A multipart submission must not run an unrelated automatic publication migration.
	wp_set_current_user( $users['admin'] );
	delete_option( 'timonconfigs_collections_version' );
	add_filter( 'pre_update_option_timonconfigs_collections_version', $version_spy, 10, 2 );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	TC_Collections::maybe_install();
	$expect( 0 === $version_updates && false === get_option( 'timonconfigs_collections_version', false ), 'An administrator POST never provisions collection pages or records a migration.' );
	remove_filter( 'pre_update_option_timonconfigs_collections_version', $version_spy, 10 );
	null === $version ? delete_option( 'timonconfigs_collections_version' ) : update_option( 'timonconfigs_collections_version', $version, false );

	// WP's JSON sender exits through this actual wp_die AJAX hook, captured only in this process.
	define( 'DOING_AJAX', true );
	add_filter( 'wp_die_ajax_handler', $die_filter ); add_filter( 'status_header', $status_spy, 10, 2 );
	$request = static function ( array $data, array $get, int $length, string $method = 'POST', string $accept = '' ) use ( &$status_code, $expect ): array {
		$_POST = $data; $_FILES = array(); $_GET = $get;
		$_SERVER['REQUEST_METHOD'] = $method; $_SERVER['CONTENT_LENGTH'] = (string) $length; $_SERVER['HTTP_ACCEPT'] = $accept;
		$status_code = 0; ob_start();
		try { TC_Owner_Editor::handle(); throw new RuntimeException( 'The request handler did not finish a JSON response.' ); }
		catch ( RuntimeException $error ) { if ( 'tc_owner_request_json_complete' !== $error->getMessage() ) { throw $error; } }
		finally { $body = ob_get_clean(); }
		$decoded = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		$expect( is_array( $decoded ) && is_bool( $decoded['success'] ?? null ) && is_array( $decoded['data'] ?? null ), 'The handler returns the expected structured JSON envelope.' );
		return array( 'status' => $status_code, 'json' => $decoded );
	};
	$limits = TC_Private_Files::upload_limits();
	$expect( is_int( $limits['file'] ) && $limits['file'] > 0 && $limits['file'] <= 20971520, 'Advertised per-file transport limit remains within private-vault limits.' );
	$expect( is_int( $limits['files'] ) && $limits['files'] >= 1 && $limits['files'] <= 10, 'Advertised upload count respects both PHP and the private-vault maximum.' );
	$expect( is_int( $limits['post'] ) && $limits['post'] >= 0, 'The complete-request limit distinguishes an unlimited PHP setting.' );
	wp_set_current_user( $users['owner'] );
	$expect( TC_Discord::can_manage_configs_local(), 'The test Owner has a real approved local OAuth binding.' );
	$before_posts = (array) wp_count_posts( 'tc_config' );
	$before_roles = get_userdata( $users['owner'] )->roles;
	if ( $limits['post'] > 0 ) {
		$oversized = $request( array(), array( 'tc_owner_submit' => '1', 'tc_owner_json' => '1' ), $limits['post'] + 1 );
		$expect( 413 === $oversized['status'] && false === $oversized['json']['success'] && 'tc_owner_request_too_large' === $oversized['json']['data']['code'], 'A PHP-discarded POST shape reaches the public endpoint and returns HTTP 413 before nonce or mutation handling.' );
		$expect( str_contains( $oversized['json']['data']['message'], 'Nothing was saved' ), 'Whole-request rejection explicitly reports a known, unsaved outcome.' );
	}
	$empty = $request( array(), array( 'tc_owner_submit' => '1', 'tc_owner_json' => '1' ), 100 );
	$expect( 403 === $empty['status'] && 'tc_owner_forbidden' === $empty['json']['data']['code'], 'A small empty request cannot bypass nonce validation.' );
	$wrong_method = $request( array(), array( 'tc_owner_json' => '1' ), 0, 'GET' );
	$expect( 405 === $wrong_method['status'] && 'tc_owner_method' === $wrong_method['json']['data']['code'], 'The JSON endpoint cannot mutate data through GET.' );
	$game = get_term_by( 'slug', 'apex-legends', 'tc_game' ); $device = get_term_by( 'slug', 'cronus-zen', 'tc_device' );
	if ( ! $game instanceof WP_Term || ! $device instanceof WP_Term ) { throw new RuntimeException( 'Seeded game and device required.' ); }
	$data = array( 'action' => 'tc_owner_config', 'operation' => 'save', 'config_id' => '0', '_wpnonce' => wp_create_nonce( 'tc_owner_config' ), 'title' => 'LOCAL OWNER REQUEST ' . $tag, 'status' => 'draft', 'tc_game' => array( (string) $game->term_id ), 'tc_device' => array( (string) $device->term_id ), 'tc_tier' => 'free', 'tc_release_status' => 'available', 'tc_input_method' => 'controller', 'tc_guide_content' => '', 'tc_guide_visibility' => 'free', 'tc_values_content' => '', 'tc_values_visibility' => 'free' );
	$invalid = $data; $invalid['_wpnonce'] = 'invalid';
	$nonce = $request( $invalid, array( 'tc_owner_submit' => '1', 'tc_owner_json' => '1' ), 100 );
	$expect( 403 === $nonce['status'] && false === $nonce['json']['success'], 'The new public route rejects an invalid nonce for an approved Owner.' );
	$legacy = $request( $invalid, array(), 100, 'POST', 'application/json' );
	$expect( 403 === $legacy['status'] && 'tc_owner_forbidden' === $legacy['json']['data']['code'], 'Legacy Accept-based JSON handling preserves nonce rejection.' );
	$settings['owner_ids'] = '';
	$unapproved = $request( $data, array( 'tc_owner_submit' => '1', 'tc_owner_json' => '1' ), 100 );
	$expect( 403 === $unapproved['status'] && 'tc_owner_forbidden' === $unapproved['json']['data']['code'], 'A valid session and nonce do not replace the approved Owner allowlist.' );
	$settings['owner_ids'] = $discord_id;
	$expect( $before_posts === (array) wp_count_posts( 'tc_config' ) && $before_roles === get_userdata( $users['owner'] )->roles, 'Rejected requests create no config and grant no lasting WordPress role.' );
	$success = $request( $data, array( 'tc_owner_submit' => '1', 'tc_owner_json' => '1' ), 100 );
	$expect( true === $success['json']['success'], 'An approved Owner with a genuine nonce can save through JSON handling.' );
	$query = array(); wp_parse_str( (string) wp_parse_url( $success['json']['data']['redirect'] ?? '', PHP_URL_QUERY ), $query );
	$id = absint( $query['config_id'] ?? 0 ); if ( $id ) { $posts[] = $id; }
	$expect( $id > 0 && 'draft' === get_post_status( $id ) && (int) get_post_field( 'post_author', $id ) === $users['owner'], 'A successful response points to the actual saved draft owned by this actor.' );
	$expect( ! TC_Owner_Editor::in_authorized_context() && ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_tc_configs' ), 'Request completion removes scoped editor capabilities.' );
	$expect( 0 === $http_calls, 'All local Owner request outcomes avoid Discord or other outbound API calls.' );
	WP_CLI::success( sprintf( 'Owner upload request integration: %d assertions passed.', $checks ) );
} finally {
	remove_filter( 'wp_die_ajax_handler', $die_filter ); remove_filter( 'status_header', $status_spy, 10 );
	remove_filter( 'pre_update_option_timonconfigs_collections_version', $version_spy, 10 );
	remove_filter( 'option_home', $https ); remove_filter( 'pre_option_timonconfigs_discord', $settings_filter ); remove_filter( 'pre_http_request', $http, 1 );
	null === $version ? delete_option( 'timonconfigs_collections_version' ) : update_option( 'timonconfigs_collections_version', $version, false );
	if ( isset( $users['owner'] ) ) {
		$created = get_posts( array( 'post_type' => 'tc_config', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'author' => $users['owner'], 'title' => 'LOCAL OWNER REQUEST ' . $tag, 'fields' => 'ids' ) );
		$posts = array_unique( array_merge( $posts, $created ) );
	}
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	if ( $binding_created && (int) get_option( $binding ) === (int) ( $users['owner'] ?? 0 ) ) { delete_option( $binding ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $uid ) {
		if ( get_user_meta( $uid, '_tc_owner_request_fixture', true ) === $tag ) { delete_transient( 'tc_owner_feedback_' . $uid ); wp_delete_user( $uid ); }
	}
	wp_set_current_user( $original_user ); $_GET = $original_get; $_POST = $original_post; $_FILES = $original_files; $_SERVER = $original_server;
}
