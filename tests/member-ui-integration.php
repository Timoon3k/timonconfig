<?php
/** Local real membership/rendering classes; only external Discord transport is mocked. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost required.' ); }
$checks = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$old = get_option( 'timonconfigs_discord', null );
$original_user = get_current_user_id();
$uid = 0;
$id = '8' . (string) random_int( 10000000000000000, 99999999999999999 );
$roles = array();
$mode = 'ok';
$fixtures = array();
$https = static fn() => 'https://localhost:8080';
$encrypt = new ReflectionMethod( TC_Discord::class, 'encrypt' );
$http = static function ( $pre, $args, $url ) use ( &$roles, &$mode, $id ) {
	if ( ! preg_match( '#^https://discord\.com/api/v10/users/@me/guilds/[0-9]+/member$#D', $url ) ) { throw new RuntimeException( 'Unexpected outbound request blocked.' ); }
	if ( 'error' === $mode ) { return new WP_Error( 'fixture', 'PRIVATE upstream error' ); }
	return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'user' => array( 'id' => $id ), 'roles' => $roles ) ) );
};
add_filter( 'pre_http_request', $http, 10, 3 );
add_filter( 'option_home', $https );
try {
	wp_set_current_user( 0 );
	delete_option( 'timonconfigs_discord' );
	$html = TC_Member_Area::render();
	$assert( str_contains( $html, 'disabled aria-describedby="tc-discord-unavailable"' ), 'Unconfigured sign-in stays disabled.' );
	$assert( ! str_contains( $html, 'tc-member-identity' ) && ! str_contains( $html, 'tc-member-quick__card' ), 'Guest receives no owner dashboard.' );
	$settings = array( 'enabled' => 1, 'staging_confirmed' => 1, 'client_id' => '800000000000000002', 'guild_id' => '800000000000000003', 'free_role' => '800000000000000004', 'monthly_role' => '800000000000000005', 'lifetime_role' => '800000000000000006', 'secret_encrypted' => $encrypt->invoke( null, 'synthetic-client-secret' ) );
	update_option( 'timonconfigs_discord', $settings, false );
	$html = TC_Member_Area::render();
	$assert( str_contains( $html, 'tc_discord_start=1' ) && ! str_contains( $html, 'tc-discord-unavailable' ), 'Configured guest uses the existing OAuth start route.' );
	$fixtures['guest-ready'] = $html;
	$bind = new ReflectionMethod( TC_Discord::class, 'bind' );
	$bound = $bind->invoke( null, array( 'id' => $id, 'username' => 'Disposable UI member' ) );
	$assert( is_int( $bound ), 'Disposable Discord account bound.' );
	$uid = $bound;
	wp_set_current_user( $uid );
	$name = str_repeat( 'LongMemberName', 8 ) . '<script>unsafe()</script>';
	update_user_meta( $uid, '_tc_discord_name', $name );
	$seed = static function () use ( $uid, $encrypt ): void {
		update_user_meta( $uid, '_tc_discord_token', $encrypt->invoke( null, wp_json_encode( array( 'access_token' => 'synthetic-access', 'refresh_token' => 'synthetic-refresh', 'expires' => time() + 3600 ) ) ) );
		delete_user_meta( $uid, '_tc_discord_status' );
		delete_user_meta( $uid, '_tc_discord_attempt' );
	};
	foreach ( array( 'free' => 'Free Member', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime', 'unverified' => 'Verification required', 'unavailable' => 'Verification unavailable' ) as $state => $label ) {
		$roles = isset( $settings[ $state . '_role' ] ) ? array( $settings[ $state . '_role' ] ) : array();
		if ( 'monthly' === $state ) { $roles[] = $settings['free_role']; }
		$mode = 'unavailable' === $state ? 'error' : 'ok';
		$seed();
		$html = TC_Member_Area::render();
		$assert( str_contains( $html, 'data-state="' . $state . '"' ) && str_contains( $html, $label ), 'Dashboard matches real server-derived state: ' . $state );
		$assert( str_contains( $html, esc_html( $name ) ) && ! str_contains( $html, '<script>' ), 'Owner name is escaped: ' . $state );
		$assert( 3 === substr_count( $html, 'class="tc-member-quick__card"' ), 'Three contextual profile actions: ' . $state );
		$assert( str_contains( $html, 'tc-member-avatar--placeholder' ), 'Missing avatar has a stable fallback: ' . $state );
		$assert( ! str_contains( $html, 'synthetic-access' ) && ! str_contains( $html, 'PRIVATE upstream error' ), 'No credentials/provider diagnostics in markup: ' . $state );
		$assert( ! str_contains( $html, 'tc_download=' ) && ! str_contains( $html, '/var/lib/' ), 'Dashboard has no download grants or storage paths: ' . $state );
		if ( in_array( $state, array( 'unverified', 'unavailable' ), true ) ) {
			$assert( str_contains( $html, 'Reconnect Discord' ) && ! str_contains( $html, 'Current membership' ) && ! str_contains( $html, 'Current license' ), 'Unverified/failed state has reconnect and no VIP claim.' );
		}
		$assert( ! str_contains( $html, 'My Downloads' ) && ! str_contains( $html, 'Recent downloads' ) && ! str_contains( $html, 'Manage Configs' ), 'Member dashboard has no download listings or unapproved Owner tools.' );
		if ( 'monthly' === $state ) { $assert( 2 === substr_count( $html, 'class="tc-member-state"' ), 'Multiple verified roles render without duplicating the current membership badge.' ); }
		$fixtures[ $state ] = $html;
	}
	wp_set_current_user( 0 );
	$assert( ! str_contains( TC_Member_Area::render(), $id ), 'Returning to guest cannot retain owner data.' );
	WP_CLI::line( wp_json_encode( array( 'passed' => true, 'assertions' => $checks, 'fixtures' => $fixtures ) ) );
} finally {
	remove_filter( 'pre_http_request', $http, 10 );
	remove_filter( 'option_home', $https );
	if ( $uid ) { require_once ABSPATH . 'wp-admin/includes/user.php'; delete_option( '_tc_discord_binding_' . $id ); delete_option( '_tc_discord_lock_' . $uid ); wp_delete_user( $uid ); }
	null === $old ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old, false );
	wp_set_current_user( $original_user );
}
