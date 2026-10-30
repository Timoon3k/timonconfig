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
$config_ids = array();
$member_events = 0;
$member_observer = static function ( int $event_uid, array $event_status ) use ( &$member_events ): void { if ( true === ( $event_status['member_verified'] ?? false ) ) { ++$member_events; } };
add_action( 'timonconfigs_discord_member_verified', $member_observer, 20, 2 );
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
		$identity = TC_Discord::status();
		$assert( ( 'unavailable' !== $state ) === ( $identity['member_verified'] ?? false ), 'Verified server membership is independent of entitlement roles and false on API failure: ' . $state );
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
	// A previously paid member may be between VIP and Free roles during reconciliation.
	$roles = array(); $mode = 'ok';
	update_user_meta( $uid, '_tc_woo_tracked', '1' );
	update_user_meta( $uid, '_tc_woo_role_granted_at', array( 'monthly' => time() - 60 ) );
	$events_before = $member_events;
	$seed(); $html = TC_Member_Area::render(); $events_after_fresh_read = $member_events; $transition = TC_Discord::status();
	$assert( $events_after_fresh_read === $events_before + 1 && $member_events === $events_after_fresh_read, 'Successful fresh member read emits one observer event; cache reads do not enqueue again.' );
	$assert( 'unverified' === $transition['state'] && true === $transition['member_verified'], 'Sync presentation never changes the role-based authorization state.' );
	$assert( str_contains( $html, 'data-state="syncing"' ) && str_contains( $html, 'Membership update pending' ) && str_contains( $html, 'Discord account connected; roles are updating' ), 'A known prior VIP member sees a membership update rather than lost Discord verification.' );
	$assert( ! str_contains( $html, 'Reconnect Discord' ) && ! str_contains( $html, 'Complete verification in the TimonConfigs Discord server' ), 'A connected transitioning member is not sent through OAuth or verification again.' );
	$assert( str_contains( $html, 'Refresh Roles' ) && str_contains( $html, 'contact support</a>' ), 'Transition offers refresh and support without exposing privileged actions.' );
	$assert( ! str_contains( $html, 'data-state="free"' ) && ! str_contains( $html, 'data-state="monthly"' ) && ! str_contains( $html, 'Current membership' ) && ! str_contains( $html, 'Current license' ), 'Transition never invents a Free or paid membership claim.' );
	foreach ( array( 'free', 'monthly', 'lifetime' ) as $tier ) {
		$config_id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'Disposable role transition access ' . $tier, 'meta_input' => array( 'tc_tier' => $tier, 'tc_release_status' => 'available' ) ) ); $config_ids[] = $config_id;
		$assert( false === TC_Discord::can_access( $config_id ), 'Pending role transition cannot grant ' . $tier . ' protected-file access.' );
	}
	$fixtures['syncing'] = $html;
	delete_user_meta( $uid, '_tc_woo_tracked' ); $seed(); $html = TC_Member_Area::render();
	$assert( str_contains( $html, 'data-state="unverified"' ) && str_contains( $html, 'Reconnect Discord' ) && ! str_contains( $html, 'data-state="syncing"' ), 'A nontracked roleless member retains the normal verification requirement.' );
	update_user_meta( $uid, '_tc_woo_tracked', '1' ); delete_user_meta( $uid, '_tc_woo_role_granted_at' );
	update_user_meta( $uid, '_tc_woo_revoked', array( 'monthly' => time() - 30 ) );
	$seed(); $html = TC_Member_Area::render();
	$assert( str_contains( $html, 'data-state="syncing"' ), 'A recorded revocation also identifies the prior-membership transition.' );
	$mode = 'error'; $events_before_error = $member_events; $seed(); $html = TC_Member_Area::render();
	$assert( $member_events === $events_before_error, 'Failed Discord verification never emits a successful member observer event.' );
	$assert( str_contains( $html, 'data-state="unavailable"' ) && ! str_contains( $html, 'data-state="syncing"' ) && false === TC_Discord::status()['member_verified'], 'A Discord API failure is unavailable, never a falsely verified transition.' );
	delete_user_meta( $uid, '_tc_woo_tracked' ); delete_user_meta( $uid, '_tc_woo_revoked' );
	$settings['owner_role'] = '800000000000000007'; $settings['staff_role'] = '800000000000000008'; $settings['owner_ids'] = $id;
	update_option( 'timonconfigs_discord', $settings, false ); $roles = array( $settings['owner_role'] ); $mode = 'ok'; $seed();
	$html = TC_Member_Area::render(); $owner_status = TC_Discord::status();
	$assert( 'unverified' === $owner_status['state'] && true === $owner_status['owner_verified'], 'Owner management does not change paid membership state.' );
	$assert( str_contains( $html, 'Owner tools enabled' ) && str_contains( $html, 'data-state="owner"' ), 'Approved Owner receives a clear management status.' );
	$assert( str_contains( $html, 'Owner account approved; downloads need membership' ) && str_contains( $html, 'You can manage configs. Downloads follow your Free or VIP membership.' ), 'Owner copy separates approved account management from membership downloads.' );
	$assert( ! str_contains( $html, 'Reconnect Discord' ) && ! str_contains( $html, 'Complete verification in the TimonConfigs Discord server' ), 'Verified Owner is not sent through an unrelated verification or reconnect flow.' );
	$assert( str_contains( $html, '<a class="tc-button" href="' . esc_url( TC_Owner_Editor::url() ) . '">Manage Configs</a>' ), 'Approved Owner has a prominent primary management action.' );
	$assert( 1 === substr_count( $html, 'class="tc-member-state"' ) && 4 === substr_count( $html, 'class="tc-member-quick__card"' ), 'Owner role is not duplicated and management is available among shortcuts.' );
	$assert( ! str_contains( $html, 'Current membership' ) && ! str_contains( $html, 'Current license' ), 'Owner without membership receives no paid-plan claim.' );
	foreach ( array( 'free', 'monthly' ) as $tier ) {
		$config_id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'Disposable Owner UI access ' . $tier, 'meta_input' => array( 'tc_tier' => $tier, 'tc_release_status' => 'available' ) ) ); $config_ids[] = $config_id;
		$assert( false === TC_Discord::can_access( $config_id ), 'Owner UI change does not grant ' . $tier . ' file access.' );
	}
	$fixtures['owner-tools'] = $html;
	$roles = array( $settings['owner_role'], $settings['monthly_role'] ); $seed(); $html = TC_Member_Area::render();
	$assert( 'monthly' === TC_Discord::status()['state'] && str_contains( $html, 'data-state="monthly"' ) && str_contains( $html, 'data-state="owner"' ), 'Owner with real paid membership keeps both independent badges.' );
	$assert( str_contains( $html, 'Manage Configs' ) && str_contains( $html, 'Current membership' ), 'Approved paid Owner sees management and their genuine membership.' );
	$fixtures['owner-monthly'] = $html;
	$settings['owner_ids'] = ''; update_option( 'timonconfigs_discord', $settings, false ); $roles = array( $settings['owner_role'] ); $seed(); $html = TC_Member_Area::render();
	$assert( ! str_contains( $html, 'Owner tools enabled' ) && ! str_contains( $html, 'Manage Configs' ), 'Owner badge without approval cannot receive management UI.' );
	$roles = array( $settings['staff_role'] ); $seed(); $html = TC_Member_Area::render();
	$assert( str_contains( $html, 'data-state="staff"' ) && ! str_contains( $html, 'Owner tools enabled' ) && ! str_contains( $html, 'Manage Configs' ), 'Staff badge never receives Owner management actions.' );
	wp_set_current_user( 0 );
	$assert( ! str_contains( TC_Member_Area::render(), $id ), 'Returning to guest cannot retain owner data.' );
	WP_CLI::line( wp_json_encode( array( 'passed' => true, 'assertions' => $checks, 'fixtures' => $fixtures ) ) );
} finally {
	foreach ( $config_ids as $config_id ) { wp_delete_post( $config_id, true ); }
	remove_action( 'timonconfigs_discord_member_verified', $member_observer, 20 );
	remove_filter( 'pre_http_request', $http, 10 );
	remove_filter( 'option_home', $https );
	if ( $uid ) { wp_clear_scheduled_hook( 'tc_woo_sync_one', array( $uid ) ); if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( 'tc_woo_sync_one', array( $uid ), 'timonconfigs' ); } require_once ABSPATH . 'wp-admin/includes/user.php'; delete_option( '_tc_discord_binding_' . $id ); delete_option( '_tc_discord_lock_' . $uid ); wp_delete_user( $uid ); }
	null === $old ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old, false );
	wp_set_current_user( $original_user );
}
