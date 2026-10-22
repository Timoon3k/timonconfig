<?php
/** Disposable localhost only. Real authorization/storage/session APIs, mocked Discord transport. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost required.' ); }
$checks = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$call = static function ( string $method, ...$args ) { $m = new ReflectionMethod( TC_Discord::class, $method ); $m->setAccessible( true ); return $m->invoke( null, ...$args ); };
$old = get_option( 'timonconfigs_discord', null ); $old_user = get_current_user_id(); $old_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$uids = array(); $posts = array(); $claims = array(); $paths = array(); $dirs = array(); $roles = array(); $mode = 'ok'; $id = '800000000000000001'; $uid = 0;
$had_cleanup = wp_next_scheduled( 'tc_download_cleanup' );
$https = static fn() => 'https://localhost:8080';
$http = static function ( $pre, $args, $url ) use ( &$roles, &$mode, $id ) {
    if ( ! preg_match( '#^https://discord\.com/api/v10/users/@me/guilds/[0-9]+/member$#D', $url ) ) { throw new RuntimeException( 'Unexpected external request blocked.' ); }
    if ( 'error' === $mode ) { return new WP_Error( 'fake_provider', 'Private upstream detail' ); }
    $body = array( 'user' => array( 'id' => $id ), 'roles' => $roles );
    if ( 'wrong-guild' === $mode ) { $body['guild_id'] = '800000000000000099'; }
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $body ) );
};
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 10, 3 );
try {
    $settings = array( 'enabled' => 1, 'staging_confirmed' => 1, 'client_id' => '800000000000000002', 'guild_id' => '800000000000000003', 'free_role' => '800000000000000004', 'monthly_role' => '800000000000000005', 'lifetime_role' => '800000000000000006', 'secret_encrypted' => $call( 'encrypt', 'synthetic-secret' ) );
    update_option( 'timonconfigs_discord', $settings, false ); wp_set_current_user( 0 );
    $uid = $call( 'bind', array( 'id' => $id, 'username' => 'Disposable download member' ) );
    $assert( is_int( $uid ), 'Create unique test Discord identity.' ); $uids[] = $uid;
    $login = static function ( int $user ) { wp_set_current_user( $user ); $session = WP_Session_Tokens::get_instance( $user )->create( time() + 3600 ); $_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $session ); return $session; };
    $session = $login( $uid );
    $seed = static function ( string $tier = 'lifetime' ) use ( $uid, $call, $settings, &$roles, &$mode ) { $mode = 'ok'; $roles = 'unverified' === $tier ? array() : array( $settings[ $tier . '_role' ] ); update_user_meta( $uid, '_tc_discord_token', $call( 'encrypt', wp_json_encode( array( 'access_token' => 'synthetic-access', 'refresh_token' => 'synthetic-refresh', 'expires' => time() + 3600 ) ) ) ); delete_user_meta( $uid, '_tc_discord_status' ); delete_user_meta( $uid, '_tc_discord_attempt' ); };
    $seed();
    $root = TC_Private_Files::root(); $assert( is_string( $root ), 'Real shared private storage configured.' );
    $config = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'Disposable private-download fixture', 'meta_input' => array( 'tc_release_status' => 'available', 'tc_tier' => 'free' ) ) ); $posts[] = $config;
    $directory = $root . '/' . $config; $assert( mkdir( $directory, 0700 ), 'Own isolated fixture directory.' ); $dirs[] = $directory;
    $file_id = wp_generate_uuid4(); $path = $directory . '/' . $file_id . '.txt'; $paths[] = $path; $content = "Synthetic integration fixture. No licensed script.\n";
    file_put_contents( $path, $content ); chmod( $path, 0600 );
    $record = array( 'id' => $file_id, 'stored_name' => basename( $path ), 'name' => 'fixture.txt', 'size' => strlen( $content ), 'checksum' => hash( 'sha256', $content ), 'uploaded_at' => gmdate( 'Y-m-d H:i:s' ) );
    update_post_meta( $config, '_tc_private_files', array( $record ) );
    $track = static function ( string $token ) use ( &$claims ) { $data = json_decode( base64_decode( strtr( explode( '.', $token )[0], '-_', '+/' ) ), true ); $claims[] = '_tc_download_claim_' . hash( 'sha256', $data['n'] ); return $token; };
    $mint = static function () use ( $config, $file_id, $track ) { $token = TC_Downloads::mint( $config, $file_id ); if ( ! is_string( $token ) ) { throw new RuntimeException( 'Expected mint: ' . $token->get_error_code() ); } return $track( $token ); };
    // Re-sign server-side malformed fixtures to exercise payload checks independently of signature checks.
    $alter = static function ( string $token, array $changes ) use ( $track ) { $data = json_decode( base64_decode( strtr( explode( '.', $token )[0], '-_', '+/' ) ), true ); $data = array_merge( $data, $changes, array( 'n' => bin2hex( random_bytes( 24 ) ) ) ); $payload = rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' ); return $track( $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ) ); };
    $token = $mint(); $prepared = TC_Downloads::prepare( $token );
    $assert( is_array( $prepared ) && $prepared['path'] === $path && $prepared['user_id'] === $uid, 'Mint through real APIs resolves authorized private file.' );
    $assert( is_array( TC_Downloads::prepare( $mint() ) ), 'Immediate second file request succeeds with bounded role cache.' );
    $assert( 'tc_download_replay' === TC_Downloads::prepare( $token )->get_error_code(), 'Atomic single-use claim rejects replay.' );
    $assert( is_wp_error( TC_Downloads::prepare( substr( $token, 0, -1 ) . ( str_ends_with( $token, '0' ) ? '1' : '0' ) ) ), 'Signature alteration rejected.' );
    foreach ( array( null, array(), '', str_repeat( 'a', 2049 ) ) as $invalid ) { $assert( is_wp_error( TC_Downloads::prepare( $invalid ) ), 'Invalid token input denied.' ); }
    foreach ( array( array( 'e' => time() - 1 ), array( 'e' => time() + 120 ), array( 'b' => get_current_blog_id() + 1 ), array( 'u' => $uid + 99999 ), array( 's' => 'other-session' ), array( 'c' => 0 ), array( 'c' => $config + 99999 ), array( 'f' => wp_generate_uuid4() ), array( 'f' => '../fixture.txt' ), array( 'v' => 2 ) ) as $change ) { $assert( is_wp_error( TC_Downloads::prepare( $alter( $token, $change ) ) ), 'Invalid payload denied: ' . key( $change ) ); }
    $token = $mint(); $session = $login( $uid ); $assert( is_wp_error( TC_Downloads::prepare( $token ) ), 'Another valid session of same user cannot use link.' );
    $token = $mint(); WP_Session_Tokens::get_instance( $uid )->destroy( $session ); $assert( is_wp_error( TC_Downloads::prepare( $token ) ), 'Revoked WP session cannot redeem.' );
    $session = $login( $uid ); $token = $mint(); wp_set_current_user( 0 ); unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
    $assert( is_wp_error( TC_Downloads::prepare( $token ) ) && is_wp_error( TC_Downloads::mint( $config, $file_id ) ), 'Anonymous cannot mint or redeem.' ); $session = $login( $uid );
    foreach ( array( 'unverified', 'free', 'monthly', 'lifetime' ) as $member ) {
        $seed( $member );
        foreach ( array( 'free', 'monthly', 'lifetime' ) as $tier ) {
            update_post_meta( $config, 'tc_tier', $tier );
            $expected = 'free' === $tier ? 'unverified' !== $member : ( 'monthly' === $tier ? in_array( $member, array( 'monthly', 'lifetime' ), true ) : 'lifetime' === $member );
            $minted = TC_Downloads::mint( $config, $file_id ); $assert( $expected === is_string( $minted ), 'Download tier matrix ' . $member . '/' . $tier );
            if ( is_string( $minted ) ) { $assert( is_array( TC_Downloads::prepare( $track( $minted ) ) ), 'Authorized tier prepares actual bytes.' ); }
        }
    }
    $seed(); update_post_meta( $config, 'tc_tier', 'monthly' );
    foreach ( array( 'revoked', 'error', 'wrong-guild' ) as $failure ) {
        $seed(); $token = $mint(); $cache = get_user_meta( $uid, '_tc_discord_status', true ); $cache['expires_at'] = time() - 1; update_user_meta( $uid, '_tc_discord_status', $cache ); delete_user_meta( $uid, '_tc_discord_attempt' );
        if ( 'revoked' === $failure ) { $roles = array(); } else { $mode = $failure; }
        $assert( is_wp_error( TC_Downloads::prepare( $token ) ), 'Expired role cache fails closed: ' . $failure );
    }
    $seed();
    $token = $mint(); delete_user_meta( $uid, '_tc_discord_token' ); delete_user_meta( $uid, '_tc_discord_status' ); $assert( is_wp_error( TC_Downloads::prepare( $token ) ), 'Discord logout invalidates minted download.' ); $seed();
    foreach ( array( 'missing', 'changed', 'symlink', 'traversal', 'legacy' ) as $failure ) {
        $token = $mint();
        if ( 'missing' === $failure ) { unlink( $path ); }
        elseif ( 'changed' === $failure ) { file_put_contents( $path, str_repeat( 'X', strlen( $content ) ) ); }
        elseif ( 'symlink' === $failure ) { unlink( $path ); symlink( '/etc/hosts', $path ); }
        else { $bad = $record; if ( 'traversal' === $failure ) { $bad['stored_name'] = '../' . basename( $path ); } else { unset( $bad['checksum'] ); } update_post_meta( $config, '_tc_private_files', array( $bad ) ); }
        clearstatcache();
        $assert( is_wp_error( TC_Downloads::prepare( $token ) ), 'Existing link denied after file mutation: ' . $failure );
        $assert( is_wp_error( TC_Downloads::mint( $config, $file_id ) ), 'New mint denied for unsafe file: ' . $failure );
        if ( is_link( $path ) ) { unlink( $path ); } file_put_contents( $path, $content ); chmod( $path, 0600 ); clearstatcache(); update_post_meta( $config, '_tc_private_files', array( $record ) );
    }
    $token = $mint(); wp_update_post( array( 'ID' => $config, 'post_status' => 'draft' ) ); $assert( is_wp_error( TC_Downloads::prepare( $token ) ), 'Unpublishing invalidates existing signed download.' );
    $assert( array() === TC_Downloads::recent( $uid + 1 ), 'Recent downloads owner-only.' );
    WP_CLI::success( "Private downloads integration: $checks assertions; real private storage and WP sessions, mocked Discord only." );
} finally {
    remove_filter( 'option_home', $https ); remove_filter( 'pre_http_request', $http, 10 );
    foreach ( array_unique( $claims ) as $claim ) { delete_option( $claim ); }
    if ( ! $had_cleanup ) { wp_clear_scheduled_hook( 'tc_download_cleanup' ); }
    foreach ( $paths as $path ) { if ( is_file( $path ) || is_link( $path ) ) { unlink( $path ); } }
    foreach ( $dirs as $directory ) { if ( is_dir( $directory ) ) { rmdir( $directory ); } }
    foreach ( $posts as $post ) { wp_delete_post( $post, true ); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $uids as $user ) { WP_Session_Tokens::get_instance( $user )->destroy_all(); delete_option( '_tc_discord_binding_' . get_user_meta( $user, '_tc_discord_id', true ) ); delete_option( '_tc_discord_lock_' . $user ); wp_delete_user( $user ); }
    null === $old ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old, false );
    wp_set_current_user( $old_user ); if ( null === $old_cookie ) { unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); } else { $_COOKIE[ LOGGED_IN_COOKIE ] = $old_cookie; }
}
