<?php
/** Disposable localhost only: actual entitlement policy, fake Discord member HTTP. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
$checks = 0;
$expect = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Shared VIP: ' . $message ); } ++$checks; };
$old_settings = get_option( 'timonconfigs_discord', null );
$old_user = get_current_user_id(); $old_get = $_GET; $old_post = $GLOBALS['post'] ?? null;
$uid = 0; $config = 0; $roles = array();
$discord = '898888888888880001';
$binding = '_tc_discord_binding_' . $discord;
if ( false !== get_option( $binding, false ) ) { throw new RuntimeException( 'Shared VIP fixture binding must not exist.' ); }
$encrypt = new ReflectionMethod( TC_Discord::class, 'encrypt' );
$settings = array( 'enabled' => 1, 'staging_confirmed' => 1, 'client_id' => '898888888888880002', 'guild_id' => '898888888888880003', 'free_role' => '898888888888880004', 'monthly_role' => '898888888888880005', 'lifetime_role' => '898888888888880006', 'owner_role' => '898888888888880007', 'owner_ids' => $discord, 'secret_encrypted' => $encrypt->invoke( null, 'fixture-only-client-secret' ) );
$https = static fn(): string => 'https://localhost:8080';
$http = static function ( $pre, $args, $url ) use ( &$roles, $settings, $discord ) {
    if ( $url !== 'https://discord.com/api/v10/users/@me/guilds/' . $settings['guild_id'] . '/member' ) { throw new RuntimeException( 'Unexpected outbound request blocked.' ); }
    return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'user' => array( 'id' => $discord ), 'roles' => $roles ) ), 'headers' => array() );
};
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 10, 3 );
try {
    $uid = wp_insert_user( array( 'user_login' => 'tc_shared_vip_' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 64, true, true ), 'role' => 'subscriber' ) );
    if ( is_wp_error( $uid ) ) { $uid = 0; throw new RuntimeException( 'Unable to create fixture account.' ); }
    add_option( $binding, $uid, '', false ); update_user_meta( $uid, '_tc_discord_id', $discord );
    wp_set_current_user( $uid ); update_option( 'timonconfigs_discord', $settings, false );
    $config = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'LOCAL Shared VIP fixture', 'meta_input' => array( 'tc_tier' => 'monthly', 'tc_release_status' => 'available' ) ), true );
    if ( is_wp_error( $config ) ) { $config = 0; throw new RuntimeException( 'Unable to create fixture config.' ); }
    $seed = static function () use ( $uid, $encrypt ): void {
        update_user_meta( $uid, '_tc_discord_token', $encrypt->invoke( null, wp_json_encode( array( 'access_token' => 'fixture-access-only', 'refresh_token' => 'fixture-refresh-only', 'expires' => time() + 3600 ) ) ) );
        delete_user_meta( $uid, '_tc_discord_status' ); delete_user_meta( $uid, '_tc_discord_attempt' ); delete_option( '_tc_discord_lock_' . $uid );
    };
    $labels = TC_Catalog::tier_labels();
    $expect( array_keys( $labels ) === array( 'free', 'monthly', 'lifetime' ), 'Storage keys remain compatible with existing configs and URLs.' );
    $expect( 'VIP — Monthly & Lifetime' === $labels['monthly'] && 'VIP Lifetime only' === $labels['lifetime'], 'Shared and exclusive access are explicit choices.' );
    foreach ( array( 'free', 'monthly', 'lifetime', 'owner', 'unverified' ) as $member ) {
        $roles = isset( $settings[ $member . '_role' ] ) ? array( $settings[ $member . '_role' ] ) : array(); $seed();
        $status = TC_Discord::status();
        update_post_meta( $config, 'tc_tier', 'monthly' );
        $expect( in_array( $member, array( 'monthly', 'lifetime' ), true ) === TC_Discord::can_access( $config ), 'Shared VIP authorizes the correct member: ' . $member );
        update_post_meta( $config, 'tc_tier', 'lifetime' );
        $expect( ( 'lifetime' === $member ) === TC_Discord::can_access( $config ), 'Lifetime-only stays exclusive: ' . $member );
        update_post_meta( $config, 'tc_tier', 'free' );
        $expect( in_array( $member, array( 'free', 'monthly', 'lifetime' ), true ) === TC_Discord::can_access( $config ), 'Free remains independent of Owner privilege: ' . $member );
        if ( in_array( $member, array( 'monthly', 'lifetime' ), true ) ) {
            $expect( $member === $status['state'] && $settings[ $member . '_role' ] === get_option( 'timonconfigs_discord' )[ $member . '_role' ], 'Membership name/key/Discord role configuration are unchanged: ' . $member );
        }
    }
    wp_set_current_user( 0 ); update_post_meta( $config, 'tc_tier', 'monthly' );
    $expect( false === TC_Discord::can_access( $config ), 'Anonymous users cannot read shared VIP files.' );
    $GLOBALS['post'] = get_post( $config ); setup_postdata( $GLOBALS['post'] );
    ob_start(); get_template_part( 'template-parts/config-card' ); $card = ob_get_clean();
    $expect( str_contains( $card, 'VIP — Monthly &amp; Lifetime' ) && str_contains( $card, '>VIP</span>' ), 'Public card identifies shared VIP while keeping its compact badge.' );
    $roles = array( $settings['owner_role'] ); wp_set_current_user( $uid ); $seed();
    $_GET = array( 'tc_manage_configs' => '1', 'config_id' => (string) $config );
    $editor = TC_Owner_Editor::render();
    $expect( 1 === preg_match( '/<option\b(?=[^>]*\bvalue="monthly")(?=[^>]*\bselected=)[^>]*>VIP — Monthly &amp; Lifetime<\/option>/', $editor ), 'Owner can select and see the persisted shared VIP setting.' );
    $expect( str_contains( $editor, '>VIP Lifetime only</option>' ) && str_contains( $editor, '>Free Member</option>' ), 'Owner retains Free and Lifetime-only choices.' );
    $expect( 'monthly' === get_post_meta( $config, 'tc_tier', true ), 'Rendering/editing choices do not migrate the saved access key.' );
    WP_CLI::success( "Shared VIP integration: $checks assertions; actual entitlement policy, mocked Discord only." );
} finally {
    remove_filter( 'option_home', $https ); remove_filter( 'pre_http_request', $http, 10 );
    if ( $config ) { wp_delete_post( $config, true ); }
    if ( $uid ) { require_once ABSPATH . 'wp-admin/includes/user.php'; delete_option( $binding ); delete_option( '_tc_discord_lock_' . $uid ); wp_delete_user( $uid ); }
    null === $old_settings ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old_settings, false );
    $_GET = $old_get; $GLOBALS['post'] = $old_post; if ( $old_post instanceof WP_Post ) { setup_postdata( $old_post ); }
    wp_set_current_user( $old_user );
}
