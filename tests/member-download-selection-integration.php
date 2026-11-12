<?php
/** Disposable localhost only: real member-library, authorization and storage APIs; mocked Discord transport. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost required.' );
}

$checks = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
$discord = static function ( string $method, ...$args ) {
	$reflection = new ReflectionMethod( TC_Discord::class, $method );
	$reflection->setAccessible( true );
	return $reflection->invoke( null, ...$args );
};
$tag = 'member-download-' . bin2hex( random_bytes( 8 ) );
$discord_id = (string) random_int( 600000000000000000, 699999999999999999 );
$old_user = get_current_user_id();
$old_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$old_get = $_GET;
$missing = new stdClass();
$options = array();
foreach ( array( 'timonconfigs_discord', 'timonconfigs_private_formats', 'timonconfigs_page_seo_queue', 'timonconfigs_page_seo_state', '_tc_page_seo_lock' ) as $name ) {
	$options[ $name ] = get_option( $name, $missing );
}
$old_cleanup = array();
foreach ( _get_cron_array() as $timestamp => $events ) {
	foreach ( $events['tc_download_cleanup'] ?? array() as $event ) { $old_cleanup[] = array( $timestamp, $event ); }
}
$posts = array();
$paths = array();
$dirs = array();
$claims = array();
$roles = array();
$uid = 0;
$binding_owned = false;
$https = static fn() => 'https://localhost:8080';
$http = static function ( $pre, $args, $url ) use ( &$roles, $discord_id ) {
	if ( ! preg_match( '#^https://discord\.com/api/v10/users/@me/guilds/[0-9]+/member$#D', $url ) ) {
		throw new RuntimeException( 'Unexpected external request blocked.' );
	}
	return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'user' => array( 'id' => $discord_id ), 'roles' => $roles ) ) );
};
$only_fixtures = static function ( WP_Query $query ) use ( &$posts ): void {
	if ( in_array( 'tc_config', (array) $query->get( 'post_type' ), true ) ) { $query->set( 'post__in', $posts ?: array( 0 ) ); }
};
add_filter( 'option_home', $https );
add_filter( 'pre_http_request', $http, 10, 3 );
add_action( 'pre_get_posts', $only_fixtures );

try {
	$settings = array(
		'enabled' => 1, 'staging_confirmed' => 1, 'client_id' => '800000000000000002', 'guild_id' => '800000000000000003',
		'free_role' => '800000000000000004', 'monthly_role' => '800000000000000005', 'lifetime_role' => '800000000000000006',
		'owner_role' => '800000000000000007', 'owner_ids' => $discord_id, 'secret_encrypted' => $discord( 'encrypt', 'synthetic-secret' ),
	);
	update_option( 'timonconfigs_discord', $settings, false );
	update_option( 'timonconfigs_private_formats', array( 'txt', 'ximmr', 'gpc', 'znbk' ), false );
	$assert( false === get_option( '_tc_discord_binding_' . $discord_id, false ), 'Fixture Discord identity is unused.' );
	$binding_owned = true;
	wp_set_current_user( 0 );
	$bound = $discord( 'bind', array( 'id' => $discord_id, 'username' => 'Local member download QA' ) );
	$assert( is_int( $bound ) && $bound > 0, 'Create isolated subscriber through actual Discord binding.' );
	$uid = $bound;
	update_user_meta( $uid, '_tc_member_download_fixture', $tag );
	wp_set_current_user( $uid );
	$expires = time() + 3600;
	$session = WP_Session_Tokens::get_instance( $uid )->create( $expires );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $uid, $expires, 'logged_in', $session );
	unset( $_GET['library_page'] );
	$seed = static function ( string $state ) use ( $uid, $settings, $discord, &$roles ): void {
		$roles = 'unverified' === $state ? array() : array( $settings[ $state . '_role' ] );
		update_user_meta( $uid, '_tc_discord_token', $discord( 'encrypt', wp_json_encode( array( 'access_token' => 'synthetic-access', 'refresh_token' => 'synthetic-refresh', 'expires' => time() + 3600 ) ) ) );
		foreach ( array( '_tc_discord_status', '_tc_discord_attempt', '_tc_discord_retry_until' ) as $key ) { delete_user_meta( $uid, $key ); }
	};
	$root = TC_Private_Files::root();
	$assert( is_string( $root ), 'Real private storage is configured outside the document root.' );
	$matrix = get_term_by( 'slug', 'xim-matrix', 'tc_device' );
	$cronus = get_term_by( 'slug', 'cronus-zen', 'tc_device' );
	$assert( $matrix instanceof WP_Term && $cronus instanceof WP_Term, 'Existing core device terms are available without changing taxonomy data.' );
	$create = static function ( string $label, WP_Term $device, string $tier = 'free', string $release = 'available', string $values = '', string $visibility = 'free' ) use ( &$posts, $tag, $assert ): int {
		$id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => $tag . ' ' . $label, 'post_excerpt' => 'Public fixture release details.', 'meta_input' => array( 'tc_release_status' => $release, 'tc_tier' => $tier, '_tc_values_content' => $values, '_tc_values_visibility' => $visibility, '_tc_member_download_fixture' => $tag ) ), true );
		$assert( is_int( $id ) && $id > 0, 'Create isolated release fixture: ' . $label );
		$posts[] = $id;
		$assert( ! is_wp_error( wp_set_object_terms( $id, array( $device->term_id ), 'tc_device' ) ), 'Assign existing device: ' . $label );
		return $id;
	};
	$store = static function ( int $id, string $name, string $bytes ) use ( $root, &$paths, &$dirs, $assert ): array {
		$directory = $root . '/' . $id;
		if ( ! in_array( $directory, $dirs, true ) ) {
			$assert( ! file_exists( $directory ) && mkdir( $directory, 0700 ), 'Create own isolated private directory.' );
			$dirs[] = $directory;
		}
		$file_id = wp_generate_uuid4();
		$path = $directory . '/' . $file_id . '.' . pathinfo( $name, PATHINFO_EXTENSION );
		$paths[] = $path;
		$assert( strlen( $bytes ) === file_put_contents( $path, $bytes ), 'Write exact synthetic private bytes.' );
		chmod( $path, 0600 );
		$record = array( 'id' => $file_id, 'stored_name' => basename( $path ), 'name' => $name, 'size' => strlen( $bytes ), 'checksum' => hash( 'sha256', $bytes ), 'uploaded_at' => gmdate( 'Y-m-d H:i:s' ) );
		$records = get_post_meta( $id, '_tc_private_files', true );
		$records = is_array( $records ) ? $records : array();
		$records[] = $record;
		update_post_meta( $id, '_tc_private_files', $records );
		return $record;
	};
	$secret = 'LOCAL_QA_VALUES_' . $tag;
	$values = $create( 'Matrix Values only', $matrix, 'free', 'available', $secret . "\nSensitivity=43\n" );
	$preferred = $create( 'Matrix actual TXT preferred', $matrix, 'free', 'available', $secret . '_unused' );
	$txt_bytes = "Synthetic importable text fixture.\r\nNo licensed script.\r\n";
	$txt = $store( $preferred, 'actual-matrix-code.txt', $txt_bytes );
	$ximmr = $store( $preferred, 'matrix-backup.ximmr', "\x00\xFF\x80XIMMR-QA\x00" );
	$backup_only = $create( 'Matrix backup only', $matrix );
	$store( $backup_only, 'backup-only.ximmr', "\x00\xFFXIMMR-QA" );
	$restricted = $create( 'Matrix monthly Values', $matrix, 'free', 'available', $secret . '_monthly', 'monthly' );
	$lifetime = $create( 'Matrix lifetime release', $matrix, 'lifetime', 'available', $secret . '_lifetime' );
	$opaque = $create( 'Cronus original files', $cronus );
	$gpc_bytes = "\xFF\x00\x80Synthetic GPC\r\n";
	$znbk_bytes = "\x00\xFF\x81ZNBK-QA\x00\r\n";
	$gpc = $store( $opaque, 'original-script.gpc', $gpc_bytes );
	$znbk = $store( $opaque, 'original-backup.znbk', $znbk_bytes );
	$coming = $create( 'Cronus coming soon', $cronus, 'free', 'coming-soon' );
	$store( $coming, 'future-release.znbk', 'Synthetic coming-soon fixture.' );
	$archived = $create( 'Cronus archived', $cronus, 'free', 'archived' );
	$store( $archived, 'archived-release.gpc', 'Synthetic archived fixture.' );

	$library = new ReflectionMethod( TC_Member_Area::class, 'library' );
	$library->setAccessible( true );
	$render_library = static function ( string $state ) use ( $library ): string {
		ob_start();
		try { $library->invoke( null, $state ); return (string) ob_get_contents(); }
		finally { ob_end_clean(); }
	};
	$assert( shortcode_exists( 'timonconfigs_member_downloads' ), 'The actual public downloads shortcode is registered.' );
	$render = static fn(): string => do_shortcode( '[timonconfigs_member_downloads]' );
	$article = static function ( string $html, int $id ): string {
		$title = esc_html( get_the_title( $id ) );
		foreach ( explode( '</article>', $html ) as $part ) {
			$start = strrpos( $part, '<article ' );
			if ( false !== $start && str_contains( substr( $part, $start ), $title ) ) { return substr( $part, $start ); }
		}
		return '';
	};
	$has_file = static fn( string $html, string $id ): bool => str_contains( $html, 'name="file_id" value="' . esc_attr( $id ) . '"' );
	$seed( 'free' );
	$html = $render();
	$assert( str_contains( $html, 'tc-member-profile' ) && str_contains( $html, 'tc-member-library' ) && strpos( $html, 'tc-member-library' ) < strpos( $html, 'class="tc-member-memberships"' ), 'Public My Downloads renders the member library between profile and memberships.' );
	$value_article = $article( $html, $values );
	$assert( $has_file( $value_article, TC_Downloads::MATRIX_TEXT_ID ), 'Values-only Matrix release offers a protected TXT export in the library.' );
	$assert( str_contains( $value_article, 'TXT code' ) && ! str_contains( $value_article, '0 B' ), 'Synthetic TXT export has a truthful label instead of an unknown zero-byte size.' );
	$preferred_article = $article( $html, $preferred );
	$assert( $has_file( $preferred_article, $txt['id'] ) && ! $has_file( $preferred_article, $ximmr['id'] ) && ! $has_file( $preferred_article, TC_Downloads::MATRIX_TEXT_ID ), 'Actual Matrix TXT is preferred over binary backup and synthetic Values export.' );
	$assert( '' === $article( $html, $backup_only ), 'Matrix backup without TXT or Values is not offered as a TXT download.' );
	$assert( '' === $article( $html, $restricted ), 'Free library does not expose independently monthly-protected Values exports.' );
	$assert( '' === $article( $html, $lifetime ), 'Free library excludes lifetime releases.' );
	$opaque_article = $article( $html, $opaque );
	$assert( $has_file( $opaque_article, $gpc['id'] ) && $has_file( $opaque_article, $znbk['id'] ) && str_contains( $opaque_article, 'original-script.gpc' ) && str_contains( $opaque_article, 'original-backup.znbk' ), 'Cronus library retains the original GPC and ZNBK file choices and names.' );
	$assert( '' === $article( $html, $coming ) && '' === $article( $html, $archived ), 'Unreleased and archived files are never offered.' );
	$assert( ! str_contains( $html, $secret ) && ! str_contains( $html, $root ) && ! str_contains( $html, 'stored_name' ), 'Library markup does not leak protected Values or private storage paths.' );
	$assert( '' === $article( $render_library( 'lifetime' ), $lifetime ), 'A forged display state cannot bypass actual Free authorization.' );
	$seed( 'monthly' );
	$html = $render();
	$assert( $has_file( $article( $html, $restricted ), TC_Downloads::MATRIX_TEXT_ID ) && '' === $article( $html, $lifetime ), 'Monthly membership unlocks monthly Values but not lifetime releases.' );
	$seed( 'lifetime' );
	$assert( $has_file( $article( $render(), $lifetime ), TC_Downloads::MATRIX_TEXT_ID ), 'Lifetime membership unlocks the lifetime Matrix export through the public shortcode.' );

	$prepare = static function ( int $id, string $file_id ) use ( &$claims, $assert ): array {
		$token = TC_Downloads::mint( $id, $file_id );
		$assert( is_string( $token ), 'A library-selected file can enter the real signed download pipeline.' );
		$data = json_decode( base64_decode( strtr( explode( '.', $token )[0], '-_', '+/' ) ), true );
		$claims[] = '_tc_download_claim_' . hash( 'sha256', $data['n'] );
		$result = TC_Downloads::prepare( $token );
		$assert( is_array( $result ), 'A library-selected file prepares under the same actual WP session.' );
		return $result;
	};
	$export = $prepare( $values, TC_Downloads::MATRIX_TEXT_ID );
	$assert( ! isset( $export['path'] ) && str_contains( $export['text'] ?? '', $secret ) && str_ends_with( $export['file']['name'] ?? '', '.txt' ), 'Values-only selection prepares real TXT content without a storage path.' );
	foreach ( array( array( $preferred, $txt, $txt_bytes ), array( $opaque, $gpc, $gpc_bytes ), array( $opaque, $znbk, $znbk_bytes ) ) as [ $id, $file, $bytes ] ) {
		$prepared = $prepare( $id, $file['id'] );
		$assert( ( $prepared['file']['name'] ?? '' ) === $file['name'] && $bytes === file_get_contents( $prepared['path'] ), 'Original selected filename and bytes are preserved for ' . pathinfo( $file['name'], PATHINFO_EXTENSION ) . '.' );
	}
	$seed( 'lifetime' );
	$roles = array( $settings['owner_role'], $settings['free_role'], $settings['monthly_role'], $settings['lifetime_role'] );
	$html = $render();
	$assert( str_contains( $html, 'Manage Configs' ) && $has_file( $article( $html, $values ), TC_Downloads::MATRIX_TEXT_ID ) && $has_file( $article( $html, $preferred ), $txt['id'] ) && $has_file( $article( $html, $opaque ), $znbk['id'] ) && $has_file( $article( $html, $lifetime ), TC_Downloads::MATRIX_TEXT_ID ), 'Owner with verified membership roles receives actual Matrix and Cronus download forms in public My Downloads.' );
	// Exercise a connected member without Owner identity, then a former paid member awaiting roles.
	$ordinary_settings = $settings;
	$ordinary_settings['owner_ids'] = '';
	update_option( 'timonconfigs_discord', $ordinary_settings, false );
	$seed( 'unverified' );
	$html = $render();
	$assert( str_contains( $html, 'data-state="unverified"' ) && str_contains( $html, 'tc-member-library' ) && ! str_contains( $html, 'name="file_id"' ) && ! str_contains( $html, $tag ), 'Public My Downloads keeps unverified members locked without exposing fixture releases.' );
	// Reuse the valid cached role result so the observer cannot queue billing reconciliation fixtures.
	update_user_meta( $uid, '_tc_woo_tracked', '1' );
	update_user_meta( $uid, '_tc_woo_role_granted_at', array( 'monthly' => time() - 60 ) );
	$html = $render();
	$assert( str_contains( $html, 'data-state="syncing"' ) && ! str_contains( $html, 'name="file_id"' ) && ! str_contains( $html, $tag ), 'Public My Downloads keeps pending membership synchronization locked.' );
	delete_user_meta( $uid, '_tc_woo_tracked' );
	delete_user_meta( $uid, '_tc_woo_role_granted_at' );
	update_option( 'timonconfigs_discord', $settings, false );
	$seed( 'owner' );
	$status = TC_Discord::status();
	$assert( ! empty( $status['owner_verified'] ) && 'unverified' === $status['state'], 'Fixture Owner has management identity but no Free or paid membership role.' );
	$assert( TC_Discord::can_manage_configs_local(), 'The same Owner remains allowed to use local management tools.' );
	$html = $render();
	$assert( str_contains( $html, 'Owner tools enabled' ) && ! str_contains( $html, 'name="file_id"' ) && ! str_contains( $html, $tag ), 'Public My Downloads preserves Owner tools without granting membership downloads.' );
	$assert( ! str_contains( $render_library( 'lifetime' ), 'name="file_id"' ), 'Owner identity and a forged display state do not grant protected downloads.' );
	$assert( is_wp_error( TC_Downloads::mint( $values, TC_Downloads::MATRIX_TEXT_ID ) ) && is_wp_error( TC_Downloads::mint( $opaque, $znbk['id'] ) ), 'Actual protected download authorization still rejects Owner without membership.' );
	wp_set_current_user( 0 );
	$html = $render();
	$assert( str_contains( $html, 'Continue with Discord' ) && ! str_contains( $html, 'tc-member-library' ) && ! str_contains( $html, 'tc-member-profile' ) && ! str_contains( $html, 'class="tc-member-memberships"' ) && ! str_contains( $html, 'name="file_id"' ) && ! str_contains( $html, $tag ), 'Anonymous public My Downloads shows the login card without private library, profile or billing sections.' );
	WP_CLI::success( "Member library selection: $checks assertions; real WP sessions, storage and authorization; mocked Discord only." );
} finally {
	remove_filter( 'option_home', $https );
	remove_filter( 'pre_http_request', $http, 10 );
	remove_action( 'pre_get_posts', $only_fixtures );
	foreach ( array_unique( $claims ) as $claim ) { delete_option( $claim ); }
	wp_unschedule_hook( 'tc_download_cleanup' );
	foreach ( $old_cleanup as [ $timestamp, $event ] ) {
		if ( $event['schedule'] ) { wp_schedule_event( $timestamp, $event['schedule'], 'tc_download_cleanup', $event['args'] ); }
		else { wp_schedule_single_event( $timestamp, 'tc_download_cleanup', $event['args'] ); }
	}
	foreach ( $paths as $path ) { if ( is_file( $path ) || is_link( $path ) ) { unlink( $path ); } }
	foreach ( $dirs as $directory ) { if ( is_dir( $directory ) ) { rmdir( $directory ); } }
	foreach ( $posts as $id ) { if ( $tag === get_post_meta( $id, '_tc_member_download_fixture', true ) ) { wp_delete_post( $id, true ); } }
	if ( $uid && $tag === get_user_meta( $uid, '_tc_member_download_fixture', true ) ) {
		WP_Session_Tokens::get_instance( $uid )->destroy_all();
		delete_option( '_tc_discord_lock_' . $uid );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
	}
	if ( $binding_owned ) { delete_option( '_tc_discord_binding_' . $discord_id ); }
	foreach ( $options as $name => $value ) {
		if ( $missing === $value ) { delete_option( $name ); }
		else { update_option( $name, $value ); }
	}
	wp_set_current_user( $old_user );
	$_GET = $old_get;
	if ( null === $old_cookie ) { unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); }
	else { $_COOKIE[ LOGGED_IN_COOKIE ] = $old_cookie; }
}
