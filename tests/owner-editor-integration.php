<?php
/** Disposable localhost only: real Owner policy/editor, mocked Discord HTTP, no external writes. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Owner_Editor' ) ) { throw new RuntimeException( 'Owner editor must be registered by Core.' ); }
$checks = 0;
$expect = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Owner editor: ' . $message ); } ++$checks; };
$old_option = get_option( 'timonconfigs_discord', null ); $original_user = get_current_user_id();
$posts = array(); $uid = 0; $temporary = ''; $discord_id = '899999999999999991';
$binding_key = '_tc_discord_binding_' . $discord_id;
if ( false !== get_option( $binding_key, false ) ) { throw new RuntimeException( 'Test identity collision; no existing binding will be modified.' ); }
$https = static fn(): string => 'https://localhost:8080';
$encrypt = static fn( string $value ): string => ( new ReflectionMethod( TC_Discord::class, 'encrypt' ) )->invoke( null, $value );
$roles = array(); $mode = 'ok'; $http_calls = 0;
$settings = array( 'enabled' => 1, 'staging_confirmed' => 1, 'pilot_enabled' => 1, 'pilot_ids' => $discord_id, 'owner_ids' => $discord_id, 'pkce' => 0, 'client_id' => '899999999999999992', 'guild_id' => '899999999999999993', 'free_role' => '899999999999999994', 'monthly_role' => '899999999999999995', 'lifetime_role' => '899999999999999996', 'staff_role' => '899999999999999997', 'owner_role' => '899999999999999998', 'secret_encrypted' => $encrypt( 'owner-test-only-secret' ) );
$http = static function ( $pre, $args, $url ) use ( &$roles, &$mode, &$http_calls, $discord_id, $settings ) {
    ++$http_calls;
    if ( $url !== 'https://discord.com/api/v10/users/@me/guilds/' . $settings['guild_id'] . '/member' ) { throw new RuntimeException( 'Unexpected outbound request blocked.' ); }
    if ( 'error' === $mode ) { return new WP_Error( 'owner_fixture', 'Never expose provider details.' ); }
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'user' => array( 'id' => $discord_id ), 'guild_id' => 'wrong-guild' === $mode ? '899999999999999999' : $settings['guild_id'], 'roles' => $roles ) ) );
};
add_filter( 'option_home', $https ); add_filter( 'pre_http_request', $http, 10, 3 );
try {
    $uid = wp_insert_user( array( 'user_login' => 'tc_owner_fixture_' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 64, true, true ), 'display_name' => 'Disposable Owner', 'role' => 'subscriber' ) );
    if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Could not create disposable subscriber.' ); }
    add_option( $binding_key, $uid, '', false ); update_user_meta( $uid, '_tc_discord_id', $discord_id );
    update_option( 'timonconfigs_discord', $settings, false ); wp_set_current_user( $uid );
    $seed = static function () use ( $uid, $encrypt ): void {
        update_user_meta( $uid, '_tc_discord_token', $encrypt( wp_json_encode( array( 'access_token' => 'owner-test-access', 'refresh_token' => 'owner-test-refresh', 'expires' => time() + 3600 ) ) ) );
        delete_user_meta( $uid, '_tc_discord_status' ); delete_user_meta( $uid, '_tc_discord_attempt' ); delete_option( '_tc_discord_lock_' . $uid );
    };
    TC_Catalog::seed_core_terms();
    $game = get_term_by( 'slug', 'apex-legends', 'tc_game' );
    $device = get_term_by( 'slug', 'cronus-zen', 'tc_device' );
    if ( ! ( $game instanceof WP_Term ) || ! ( $device instanceof WP_Term ) ) { throw new RuntimeException( 'Missing test-only game/device fixture terms.' ); }
    $data = static function ( string $operation = 'save', int $id = 0 ) use ( $uid, $game, $device ): array {
        return array( '_wpnonce' => wp_create_nonce( 'tc_owner_config' ), 'operation' => $operation, 'config_id' => (string) $id, 'title' => 'Disposable owner configuration', 'description' => '<p>Test-only description</p>', 'excerpt' => 'Test-only summary', 'status' => 'draft', 'tc_game' => array( (string) $game->term_id ), 'tc_device' => array( (string) $device->term_id ), 'tc_tier' => 'free', 'tc_release_status' => 'available', 'tc_input_method' => 'controller', 'tc_guide_content' => '', 'tc_guide_visibility' => 'free', 'tc_values_content' => '', 'tc_values_visibility' => 'free' );
    };
    $expect( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_tc_configs' ), 'Subscriber starts without generic or config administration.' );
    $operations = array( 'save', 'trash', 'upload', 'replace', 'delete', 'update', 'image' );
    foreach ( array( 'anonymous', 'free', 'monthly', 'lifetime', 'staff', 'unverified', 'wrong-guild', 'not-approved' ) as $actor ) {
        wp_set_current_user( 'anonymous' === $actor ? 0 : $uid );
        $roles = isset( $settings[ $actor . '_role' ] ) ? array( $settings[ $actor . '_role' ] ) : array();
        $mode = 'wrong-guild' === $actor ? 'wrong-guild' : 'ok';
        if ( in_array( $actor, array( 'wrong-guild', 'not-approved' ), true ) ) { $roles = array( $settings['owner_role'] ); }
        $current_settings = $settings; if ( 'not-approved' === $actor ) { $current_settings['owner_ids'] = ''; }
        update_option( 'timonconfigs_discord', $current_settings, false );
        foreach ( $operations as $operation ) {
            $seed(); $result = TC_Owner_Editor::perform( $data( $operation ) );
            $expect( is_wp_error( $result ), "$actor cannot perform $operation." );
            $expected_error = 'anonymous' === $actor ? 'Your management session expired. Reload and try again.' : ( 'wrong-guild' === $actor ? 'Discord verification is unavailable. Please reconnect or try again shortly.' : 'Verified Owner access is required.' );
            $expect( $expected_error === $result->get_error_message(), "$actor/$operation is rejected by identity policy before operation-specific validation." );
            $expect( ! TC_Owner_Editor::in_authorized_context() && ! current_user_can( 'edit_tc_configs' ), "Rejected $actor/$operation leaves no scoped capabilities." );
        }
    }
    update_option( 'timonconfigs_discord', $settings, false ); wp_set_current_user( $uid ); $mode = 'ok'; $roles = array( $settings['owner_role'] ); $seed();
    $before = $http_calls;
    $bad = $data(); $bad['_wpnonce'] = 'invalid';
    $expect( is_wp_error( TC_Owner_Editor::perform( $bad ) ) && $before === $http_calls, 'CSRF fails before Discord calls or mutations.' );
    $bad['_wpnonce'] = array( 'malformed' );
    $expect( is_wp_error( TC_Owner_Editor::perform( $bad ) ), 'Array-shaped nonce is safely rejected.' );
    // Hostinger private storage not configured: preserve a draft and expose its ID.
    $missing_storage = static fn(): string => '';
    add_filter( 'timonconfigs_private_storage_directory', $missing_storage );
    $seed(); $new_with_files = $data(); $new_with_files['status'] = 'publish';
    $attempt = TC_Owner_Editor::perform( $new_with_files, array( 'tc_private_files' => array(
        'name' => array( 'fixture.txt' ),
        'error' => array( UPLOAD_ERR_OK ),
        'tmp_name' => array( '/not-an-upload' ),
        'size' => array( 15 ),
    ) ) );
    remove_filter( 'timonconfigs_private_storage_directory', $missing_storage );
    $draft_data = is_wp_error( $attempt ) ? $attempt->get_error_data() : null;
    $saved_draft_id = is_array( $draft_data ) ? absint( $draft_data['config_id'] ?? 0 ) : 0;
    $expect( is_wp_error( $attempt ) && 'tc_owner_draft_saved' === $attempt->get_error_code() && $saved_draft_id > 0, 'Missing storage preserves newly created draft and returns recoverable ID.' );
    $posts[] = $saved_draft_id;
    $expect( 'draft' === get_post_status( $saved_draft_id ) && 'Disposable owner configuration' === get_the_title( $saved_draft_id ), 'Failed private upload never publishes config but preserves title and metadata.' );
    $expect( ! get_post_meta( $saved_draft_id, '_tc_private_files', true ), 'No private file reference is fabricated for rejected uploads.' );
    $seed();
    $created = TC_Owner_Editor::perform( $data() );
    $expect( is_int( $created ) && $created > 0, 'Approved Owner subscriber can create a draft.' ); $posts[] = $created;
    $expect( 'draft' === get_post_status( $created ) && (int) get_post_field( 'post_author', $created ) === $uid, 'Draft belongs to the authenticated Owner.' );
    $expect( ! TC_Owner_Editor::in_authorized_context() && ! current_user_can( 'edit_tc_configs' ) && ! current_user_can( 'manage_options' ) && ! current_user_can( 'upload_files' ), 'Successful write grants no lasting or generic WP capabilities.' );
    $expect( is_wp_error( TC_Owner_Editor::perform( $data( 'save', $created ) ) ), 'Rapid repeated write is rate-limited.' );
    $seed(); $edit = $data( 'save', $created ); $edit['title'] = 'Owner-edited release'; $edit['status'] = 'publish';
    $edit['description'] = '<p>Preserved safe text</p><script>alert(1)</script>'; $edit['tc_tier'] = 'lifetime';
    $result = TC_Owner_Editor::perform( $edit );
    $expect( $created === $result && 'publish' === get_post_status( $created ) && 'Owner-edited release' === get_the_title( $created ), 'Owner can edit and publish the existing config.' );
    $expect( ! str_contains( get_post_field( 'post_content', $created ), '<script' ), 'Unsafe description markup is removed.' );
    $expect( 'game' === get_post_meta( $created, '_tc_card_art', true ), 'Shared game artwork is the default for new Owner cards.' );
    $seed(); $broken = $data( 'save', $created ); $broken['status'] = 'publish'; $broken['tc_game'] = array();
    $expect( is_wp_error( TC_Owner_Editor::perform( $broken ) ), 'Published config without a game is rejected with a clear message.' );
    $seed(); $broken = $data( 'save', $created ); $broken['status'] = 'publish'; $broken['tc_device'] = array();
    $expect( is_wp_error( TC_Owner_Editor::perform( $broken ) ), 'Published config without a device is rejected.' );
    $seed(); $custom = $data( 'save', $created ); $custom['status'] = 'publish'; $custom['title'] = 'Owner-edited release'; $custom['card_art'] = 'custom';
    $expect( $created === TC_Owner_Editor::perform( $custom ) && 'custom' === get_post_meta( $created, '_tc_card_art', true ), 'Owner can opt into a custom cover.' );
    $seed(); $shared = $data( 'save', $created ); $shared['status'] = 'publish'; $shared['title'] = 'Owner-edited release'; $shared['card_art'] = 'game';
    $expect( $created === TC_Owner_Editor::perform( $shared ) && 'game' === get_post_meta( $created, '_tc_card_art', true ), 'Owner can restore the common game cover.' );
    $expect( false === TC_Discord::can_access( $created ), 'Owner privilege does not grant VIP file entitlement.' );
    $rest = new WP_REST_Request( 'POST', '/wp/v2/tc_config' ); $rest->set_param( 'title', 'Forbidden REST config' );
    $expect( rest_do_request( $rest )->get_status() === 403, 'Owner subscriber cannot bypass the editor through generic REST create.' );
    foreach ( array(
        array( 'tc_game' => array( '2147483647' ) ),
        array( 'tc_game' => 'malformed' ),
        array( 'tc_device' => array( array( 'nested' ) ) ),
        array( 'tc_tier' => 'owner' ),
        array( 'tc_release_status' => 'invented' ),
        array( 'status' => 'private' ),
        array( 'title' => array( 'invalid' ) ),
        array( 'config_id' => '../1' ),
        array( 'tc_input_method' => 'invalid' ),
        array( 'tc_release_date' => '2026-99-99' ),
        array( 'title' => 'Must never replace title', 'status' => 'publish', 'tc_guide_visibility' => 'invalid' ),
        array( 'title' => 'Must never replace title', 'status' => 'publish', 'tc_values_content' => array( 'invalid' ) ),
    ) as $changes ) {
        $seed(); $bad = array_merge( $data( 'save', $created ), $changes );
        $expect( is_wp_error( TC_Owner_Editor::perform( $bad ) ), 'Malformed metadata/taxonomy/ID cannot mutate a config.' );
        $expect( 'Owner-edited release' === get_the_title( $created ) && ! TC_Owner_Editor::in_authorized_context(), 'Rejected mutation preserves content and clears authorization context (' . implode( ',', array_keys( $changes ) ) . '); actual title: ' . get_the_title( $created ) );
    }
    $seed(); $mode = 'error';
    $expect( is_wp_error( TC_Owner_Editor::perform( $data( 'save', $created ) ) ), 'Discord outage denies write.' );
    $mode = 'ok'; $roles = array( $settings['owner_role'] ); $seed(); TC_Discord::status();
    delete_user_meta( $uid, '_tc_discord_attempt' ); $roles = array( $settings['free_role'] );
    $expect( is_wp_error( TC_Owner_Editor::perform( $data( 'save', $created ) ) ), 'Fresh verification rejects revoked Owner despite cached Owner badge.' );
    $roles = array( $settings['owner_role'] ); $seed();
    $temporary = wp_tempnam( 'owner-fixture.gpc' ); file_put_contents( $temporary, "// test-only\nmain {}\n" );
    $fake_upload = array( 'name' => array( 'fixture.gpc' ), 'tmp_name' => array( $temporary ), 'error' => array( UPLOAD_ERR_OK ), 'size' => array( filesize( $temporary ) ) );
    $expect( is_wp_error( TC_Owner_Editor::perform( $data( 'upload', $created ), array( 'tc_private_files' => $fake_upload ) ) ), 'Local file path cannot impersonate an HTTP private upload.' );
    $seed();
    $expect( is_wp_error( TC_Owner_Editor::perform( $data( 'image', $created ), array( 'preview_image' => array( 'name' => 'fixture.png', 'tmp_name' => $temporary, 'error' => UPLOAD_ERR_OK ) ) ) ), 'Local file path cannot impersonate an HTTP public-image upload.' );
    $seed();
    $expect( is_wp_error( TC_Owner_Editor::perform( $data( 'trash', $created ) ) ) && 'publish' === get_post_status( $created ), 'Trash requires explicit confirmation.' );
    $seed(); $trash = $data( 'trash', $created ); $trash['confirm'] = '1';
    $expect( 0 === TC_Owner_Editor::perform( $trash ) && 'trash' === get_post_status( $created ), 'Approved Owner can move a config to Trash.' );
    $audit = get_post_meta( $created, '_tc_owner_audit', false );
    $expect( array( 'created', 'edited', 'edited', 'edited', 'trashed' ) === wp_list_pluck( $audit, 'action' ) && array( $uid ) === array_values( array_unique( wp_list_pluck( $audit, 'actor' ) ) ), 'Successful writes alone record actor/time/action audit.' );
    $expect( ! TC_Owner_Editor::in_authorized_context() && ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_tc_configs' ) && array( 'subscriber' ) === get_userdata( $uid )->roles, 'Owner remains a subscriber after all success/failure paths.' );
    WP_CLI::success( "Owner editor integration: $checks assertions; disposable fixtures, mocked Discord only." );
} finally {
    remove_filter( 'pre_http_request', $http, 10 ); remove_filter( 'option_home', $https );
    foreach ( $posts as $post ) { wp_delete_post( $post, true ); }
    if ( $temporary && is_file( $temporary ) ) { unlink( $temporary ); }
    if ( $uid ) { delete_option( '_tc_discord_lock_' . $uid ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid ); }
    delete_option( $binding_key );
    null === $old_option ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old_option, false ); wp_set_current_user( $original_user );
}
