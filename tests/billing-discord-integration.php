<?php
/** Disposable localhost WordPress only: real Ed25519, fake Discord HTTP, no remote writes. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) { throw new RuntimeException( 'Sodium is required for signature verification tests.' ); }
if ( defined( 'TIMONCONFIGS_BILLING_DISCORD_PUBLIC_KEY' ) || defined( 'TIMONCONFIGS_BILLING_TEST_DISCORD_BOT_TOKEN' ) ) { throw new RuntimeException( 'Run in a fresh local CLI process without configured Discord billing credentials.' ); }
$pair = sodium_crypto_sign_keypair();
$private_key = sodium_crypto_sign_secretkey( $pair );
define( 'TIMONCONFIGS_BILLING_DISCORD_PUBLIC_KEY', bin2hex( sodium_crypto_sign_publickey( $pair ) ) );
define( 'TIMONCONFIGS_BILLING_TEST_DISCORD_BOT_TOKEN', 'synthetic-local-discord-bot' );
foreach ( array( 'store', 'discord' ) as $class ) {
    if ( ! class_exists( 'TC_Billing_' . ucfirst( $class ) ) ) { require WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-billing-' . $class . '.php'; }
}
if ( ! class_exists( 'TC_Billing' ) ) { require WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-billing.php'; }

$checks = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$old_billing = get_option( 'timonconfigs_billing', null );
$old_oauth = get_option( 'timonconfigs_discord', null );
$original_user = get_current_user_id();
$uids = array(); $intents = array(); $requests = array();
$ids = array( 'user' => '940000000000000101', 'other' => '940000000000000102', 'bot' => '940000000000000103', 'application' => '940000000000000104', 'guild' => '940000000000000105', 'free' => '940000000000000106', 'monthly' => '940000000000000107', 'lifetime' => '940000000000000108', 'staff' => '940000000000000109', 'owner' => '940000000000000110', 'bot_role' => '940000000000000111', 'guild_owner' => '940000000000000112' );
foreach ( array( 'user', 'other' ) as $actor ) { if ( false !== get_option( '_tc_discord_binding_' . $ids[ $actor ] ) ) { throw new RuntimeException( 'A test Discord binding already exists; refusing to overwrite it.' ); } }
$member_roles = array(); $fault = ''; $source_ids = array();
$oauth = array( 'enabled' => 1, 'guild_id' => $ids['guild'], 'client_id' => $ids['application'], 'free_role' => $ids['free'], 'monthly_role' => $ids['monthly'], 'lifetime_role' => $ids['lifetime'], 'staff_role' => $ids['staff'], 'owner_role' => $ids['owner'] );
$billing = array( 'mode' => 'test', 'routing' => 'direct', 'role_sync_enabled' => 1, 'stripe_test_confirmed' => 1, 'migration_confirmed' => 1, 'test_discord_guild_id' => $ids['guild'], 'test_discord_monthly_role_id' => $ids['monthly'], 'test_discord_lifetime_role_id' => $ids['lifetime'] );
$http = static function ( $pre, $args, $url ) use ( &$requests, &$member_roles, &$fault, $ids, $billing ) {
    $requests[] = array( $url, $args );
    if ( ! str_starts_with( $url, 'https://discord.com/api/v10/' ) ) { throw new RuntimeException( 'Unexpected outbound request blocked.' ); }
    if ( 0 !== ( $args['redirection'] ?? null ) || 'Bot synthetic-local-discord-bot' !== ( $args['headers']['Authorization'] ?? '' ) ) { throw new RuntimeException( 'Unpinned Discord request.' ); }
    $method = $args['method'] ?? 'GET';
    if ( 'network' === $fault ) { return new WP_Error( 'upstream-secret', 'Never expose upstream credentials or response text.' ); }
    if ( 'write-429' === $fault && 'GET' !== $method ) { return array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '7' ), 'body' => '{"retry_after":12.5,"message":"UPSTREAM-SECRET"}' ); }
    $path = substr( $url, strlen( 'https://discord.com/api/v10' ) );
    if ( 'GET' !== $method ) {
        if ( ! preg_match( '~^/guilds/' . $ids['guild'] . '/members/' . $ids['user'] . '/roles/(' . $ids['monthly'] . '|' . $ids['lifetime'] . ')$~D', $path, $match ) || ! in_array( $method, array( 'PUT', 'DELETE' ), true ) ) { throw new RuntimeException( 'Unsafe Discord role mutation blocked.' ); }
        if ( 'PUT' === $method ) { $member_roles[] = $match[1]; $member_roles = array_values( array_unique( $member_roles ) ); }
        else { $member_roles = array_values( array_diff( $member_roles, array( $match[1] ) ) ); }
        return array( 'response' => array( 'code' => 204 ), 'headers' => array(), 'body' => '' );
    }
    if ( '/users/@me' === $path ) { $body = array( 'id' => $ids['bot'], 'bot' => true ); }
    elseif ( '/guilds/' . $ids['guild'] === $path ) { $body = array( 'id' => $ids['guild'], 'owner_id' => $ids['guild_owner'] ); }
    elseif ( '/guilds/' . $ids['guild'] . '/roles' === $path ) {
        $body = array();
        foreach ( array( 'guild' => 0, 'free' => 1, 'monthly' => 3, 'lifetime' => 4, 'staff' => 10, 'owner' => 30, 'bot_role' => 20 ) as $key => $position ) {
            $permissions = 'bot_role' === $key && 'permissions' !== $fault ? '268435456' : '0';
            // Large bitfields must not lose Manage Roles through an overflowing integer cast.
            if ( 'bot_role' === $key && 'large-permissions' === $fault ) { $permissions = '18446744073977987072'; }
            $body[] = array( 'id' => $ids[ $key ], 'position' => 'hierarchy' === $fault && 'monthly' === $key ? 20 : $position, 'permissions' => $permissions, 'managed' => 'bot_role' === $key || ( 'managed' === $fault && 'monthly' === $key ) );
        }
    }
    elseif ( '/guilds/' . $ids['guild'] . '/members/' . $ids['bot'] === $path ) { $body = array( 'user' => array( 'id' => $ids['bot'], 'bot' => true ), 'roles' => array( $ids['bot_role'] ) ); }
    elseif ( '/guilds/' . $ids['guild'] . '/members/' . $ids['user'] === $path ) {
        if ( 'disable-on-member' === $fault ) { update_option( 'timonconfigs_billing', array_merge( $billing, array( 'role_sync_enabled' => 0 ) ), false ); }
        if ( 'switch-mode-on-member' === $fault ) { update_option( 'timonconfigs_billing', array_merge( $billing, array( 'mode' => 'live' ) ), false ); }
        $body = array( 'user' => array( 'id' => 'wrong-member' === $fault ? $ids['other'] : $ids['user'] ), 'roles' => $member_roles );
    }
    else { throw new RuntimeException( 'Unexpected Discord endpoint blocked.' ); }
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $body ) );
};
$writes = static function () use ( &$requests ): array { return array_values( array_filter( $requests, static fn( $request ) => 'GET' !== ( $request[1]['method'] ?? 'GET' ) ) ); };
$signed = static function ( array $data, ?int $stamp = null ) use ( $private_key ): array {
    $raw = wp_json_encode( $data ); $timestamp = (string) ( $stamp ?? time() );
    return array( $raw, array( 'X-Signature-Ed25519' => bin2hex( sodium_crypto_sign_detached( $timestamp . $raw, $private_key ) ), 'X-Signature-Timestamp' => $timestamp ) );
};
$command = static fn( string $name, array $options = array() ) => array( 'type' => 2, 'application_id' => $ids['application'], 'guild_id' => $ids['guild'], 'member' => array( 'user' => array( 'id' => $ids['user'] ) ), 'data' => array( 'type' => 1, 'name' => $name, 'options' => $options ) );
$issue = static function ( string $plan = 'monthly' ) use ( $command, $signed, &$intents, $assert ): string {
    $response = TC_Billing_Discord::interaction( ...$signed( $command( 'buy', array( array( 'name' => 'plan', 'type' => 3, 'value' => $plan ) ) ) ) );
    $assert( is_array( $response ) && 64 === $response['data']['flags'], 'Buy response is ephemeral.' );
    $assert( 1 === preg_match( '/tc_billing_intent=([a-f0-9]{64})/', $response['data']['content'], $match ), 'Buy issues an opaque website intent.' );
    $assert( str_contains( $response['data']['content'], home_url( '/checkout/' . $plan . '/' ) ), 'Intent uses the existing controlled checkout route.' );
    $intents[] = $match[1]; return $match[1];
};
add_filter( 'pre_http_request', $http, 1, 3 );
try {
    TC_Billing_Store::install();
    foreach ( array( 'user', 'other' ) as $actor ) {
        $uid = wp_insert_user( array( 'user_login' => 'tc_discord_billing_' . bin2hex( random_bytes( 8 ) ), 'user_pass' => wp_generate_password( 48 ), 'role' => 'subscriber' ) );
        $assert( ! is_wp_error( $uid ), 'Created disposable OAuth-bound user.' );
        $uids[ $actor ] = (int) $uid;
        update_user_meta( $uid, '_tc_discord_id', $ids[ $actor ] ); add_option( '_tc_discord_binding_' . $ids[ $actor ], $uid, '', false );
    }
    $uid = $uids['user']; $job = array( 'id' => 1, 'user_id' => $uid, 'mode' => 'test' );
    $source = static function ( string $name, string $plan, string $status = 'active', ?string $discord = null, ?string $until = null ) use ( $uid, $ids, &$source_ids, $assert ): void {
        $source_ids[] = 'discord-local:' . $uid . ':' . $name;
        $result = TC_Billing_Store::upsert_entitlement( array( 'source' => end( $source_ids ), 'user_id' => $uid, 'discord_id' => $discord ?? $ids['user'], 'provider' => 'operator', 'mode' => 'test', 'plan' => $plan, 'status' => $status, 'valid_until' => 'lifetime' === $plan ? null : ( $until ?? gmdate( 'Y-m-d H:i:s', time() + 3600 ) ) ) );
        $assert( true === $result, 'Fixture entitlement is valid: ' . $name );
    };
    update_option( 'timonconfigs_discord', $oauth, false ); update_option( 'timonconfigs_billing', array_merge( $billing, array( 'role_sync_enabled' => 0 ) ), false );
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $requests, 'Role sync defaults disabled and performs no HTTP.' );
    update_option( 'timonconfigs_billing', $billing, false );
    $assert( TC_Billing::mode_allowed( 'test' ) && TC_Billing::direct_enabled(), 'Disposable local test mode is configured.' );
    $assert( is_wp_error( TC_Billing_Discord::sync( array_merge( $job, array( 'mode' => 'live' ) ) ) ) && ! $requests, 'Test settings do not permit live role automation.' );
    update_option( 'timonconfigs_billing', array_merge( $billing, array( 'mode' => 'live' ) ), false );
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $requests, 'A configured mode change pauses old Test jobs before canonical reads.' );
    update_option( 'timonconfigs_billing', $billing, false );
    update_option( 'timonconfigs_billing', array_merge( $billing, array( 'test_discord_guild_id' => $ids['other'] ) ), false );
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $requests, 'Mismatched OAuth guild is denied before HTTP.' );
    update_option( 'timonconfigs_billing', array_merge( $billing, array( 'test_discord_monthly_role_id' => $ids['free'] ) ), false );
    update_option( 'timonconfigs_discord', array_merge( $oauth, array( 'monthly_role' => $ids['free'] ) ), false );
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $requests, 'Protected Free role cannot become a billing role.' );
    update_option( 'timonconfigs_billing', $billing, false ); update_option( 'timonconfigs_discord', $oauth, false );
    update_option( '_tc_discord_binding_' . $ids['user'], $uids['other'], false );
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $requests, 'Conflicting OAuth binding prevents HTTP and writes.' );
    update_option( '_tc_discord_binding_' . $ids['user'], $uid, false );
    $source( 'monthly', 'monthly' );
    foreach ( array( 'permissions', 'hierarchy', 'managed', 'wrong-member' ) as $fault ) { $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $writes(), 'Canonical validation prevents writes: ' . $fault ); }
    $fault = 'disable-on-member';
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $writes(), 'Disabling automation while canonical HTTP is in flight prevents the later PUT.' );
    update_option( 'timonconfigs_billing', $billing, false );
    $fault = 'switch-mode-on-member';
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $writes(), 'Switching mode while canonical HTTP is in flight prevents the old-mode PUT.' );
    update_option( 'timonconfigs_billing', $billing, false );
    $fault = 'network'; $error = TC_Billing_Discord::sync( $job );
    $assert( is_wp_error( $error ) && 60 === $error->get_error_data()['retry_after'] && ! str_contains( wp_json_encode( $error ), 'upstream-secret' ), 'Network failure has a safe delayed retry.' );
    $fault = 'write-429'; $error = TC_Billing_Discord::sync( $job );
    $assert( is_wp_error( $error ) && 13 === $error->get_error_data()['retry_after'] && ! TC_Billing_Store::owned_roles( $uid, 'test' ), '429 respects the larger fractional retry delay and never marks a failed add owned.' );
    $fault = 'large-permissions'; $requests = array();
    $assert( true === TC_Billing_Discord::sync( $job ) && array( $ids['monthly'] ) === $member_roles && 1 === count( $writes() ), 'A valid Monthly source adds only Monthly, with large permission bitfields.' );
    $assert( array( array( 'guild_id' => $ids['guild'], 'role_id' => $ids['monthly'] ) ) === TC_Billing_Store::owned_roles( $uid, 'test' ), 'Only our successful actual add records ownership.' );
    $fault = ''; $requests = array();
    $assert( true === TC_Billing_Discord::sync( $job ) && ! $writes(), 'Already-present owned role does not cause another PUT.' );
    $source( 'lifetime', 'lifetime' ); $requests = array();
    $assert( true === TC_Billing_Discord::sync( $job ) && 1 === count( $writes() ) && str_ends_with( $writes()[0][0], '/' . $ids['lifetime'] ), 'Union of independent paid plans adds Lifetime.' );
    $source( 'monthly', 'monthly', 'revoked' ); $requests = array();
    $assert( true === TC_Billing_Discord::sync( $job ) && ! $writes() && in_array( $ids['monthly'], $member_roles, true ), 'Lifetime protects an existing Monthly role after Monthly revocation.' );
    $source( 'lifetime', 'lifetime', 'revoked' ); $requests = array();
    update_user_meta( $uid, '_tc_billing_legacy_reviewed', '1' );
    update_user_meta( $uid, '_tc_billing_legacy_reviewed_live', '1' );
    $error = TC_Billing_Discord::sync( $job );
    $assert( is_wp_error( $error ) && 'tc_billing_discord_legacy_review' === $error->get_error_code() && ! $writes(), 'Global and live review flags cannot authorize test role deletion.' );
    update_user_meta( $uid, '_tc_billing_legacy_reviewed_test', '1' );
    $assert( true === TC_Billing_Discord::sync( $job ) && 2 === count( $writes() ) && ! $member_roles && ! TC_Billing_Store::owned_roles( $uid, 'test' ), 'Reviewed expired owned VIP roles are deleted individually and ownership clears.' );
    $member_roles = array( $ids['monthly'] ); $requests = array();
    $error = TC_Billing_Discord::sync( $job );
    $assert( is_wp_error( $error ) && 'tc_billing_discord_external_role' === $error->get_error_code() && ! $writes() && ! TC_Billing_Store::owned_roles( $uid, 'test' ), 'A pre-existing legacy role is never adopted or removed, even after review.' );
    $source( 'monthly', 'monthly' );
    $assert( true === TC_Billing_Discord::sync( $job ) && ! $writes() && ! TC_Billing_Store::owned_roles( $uid, 'test' ), 'Active purchase still does not adopt a pre-existing role.' );
    $source( 'monthly', 'monthly', 'revoked' ); $member_roles = array();
    TC_Billing_Store::own_role( $uid, 'test', $ids['guild'], $ids['monthly'], true );
    $assert( true === TC_Billing_Discord::sync( $job ) && ! TC_Billing_Store::owned_roles( $uid, 'test' ), 'A canonically missing role clears its stale ownership before external re-addition.' );
    $source( 'lifetime', 'lifetime' ); $requests = array();
    $assert( true === TC_Billing_Discord::sync( $job ) && array( $ids['lifetime'] ) === $member_roles && 1 === count( $writes() ), 'Lifetime alone adds Lifetime without inventing a Monthly badge.' );
    $source( 'lifetime-independent', 'lifetime' ); $source( 'lifetime', 'lifetime', 'revoked' ); $requests = array();
    $assert( true === TC_Billing_Discord::sync( $job ) && ! $writes(), 'Another independent Lifetime entitlement protects the role.' );
    $source( 'lifetime-independent', 'lifetime', 'revoked' ); $fault = 'write-429';
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && 1 === count( TC_Billing_Store::owned_roles( $uid, 'test' ) ), 'Failed DELETE keeps ownership for a safe retry.' );
    $fault = ''; $assert( true === TC_Billing_Discord::sync( $job ), 'Queued role deletion can recover.' );
    $source( 'expired', 'monthly', 'active', null, gmdate( 'Y-m-d H:i:s', time() - 1 ) ); $requests = array();
    $assert( true === TC_Billing_Discord::sync( $job ) && ! $writes(), 'An expired active-status Monthly row never grants a role.' );
    $source( 'wrong-identity', 'monthly', 'active', $ids['other'] );
    $assert( is_wp_error( TC_Billing_Discord::sync( $job ) ) && ! $writes(), 'A source bound to another Discord ID cannot grant roles.' );
    $source( 'wrong-identity', 'monthly', 'revoked', $ids['other'] );

    $before = count( $requests );
    $ping = $signed( array( 'type' => 1 ) );
    $assert( array( 'type' => 1 ) === TC_Billing_Discord::interaction( ...$ping ), 'A real Ed25519-signed PING is accepted.' );
    $assert( is_wp_error( TC_Billing_Discord::interaction( $ping[0], array() ) ), 'Unsigned interaction is denied.' );
    $assert( is_wp_error( TC_Billing_Discord::interaction( $ping[0] . ' ', $ping[1] ) ), 'Any raw-body modification invalidates the signature.' );
    foreach ( array( time() - 301, time() + 301 ) as $stamp ) { $assert( is_wp_error( TC_Billing_Discord::interaction( ...$signed( array( 'type' => 1 ), $stamp ) ) ), 'Expired and future signatures are denied.' ); }
    $duplicate = $ping[1]; $duplicate['x-signature-ed25519'] = $duplicate['X-Signature-Ed25519'];
    $assert( is_wp_error( TC_Billing_Discord::interaction( $ping[0], $duplicate ) ), 'Duplicate signature header spellings are rejected.' );
    $array_headers = array_map( static fn( $value ) => array( $value ), $ping[1] );
    $assert( array( 'type' => 1 ) === TC_Billing_Discord::interaction( $ping[0], $array_headers ), 'WordPress REST single-value header arrays verify.' );
    $bad = $command( 'buy' ); $bad['guild_id'] = $ids['other'];
    $assert( is_wp_error( TC_Billing_Discord::interaction( ...$signed( $bad ) ) ), 'Signed commands from another guild are denied.' );
    $bad = $command( 'buy' ); $bad['application_id'] = $ids['other'];
    $assert( is_wp_error( TC_Billing_Discord::interaction( ...$signed( $bad ) ) ), 'An incorrect Discord application ID is denied.' );
    foreach ( array( 'membership', 'manage', 'link' ) as $name ) {
        $response = TC_Billing_Discord::interaction( ...$signed( $command( $name ) ) );
        $assert( is_array( $response ) && 64 === $response['data']['flags'] && str_contains( $response['data']['content'], home_url( '/account/' ) ) && array() === $response['data']['allowed_mentions']['parse'], 'Ephemeral controlled account link for /' . $name );
    }
    $assert( is_wp_error( TC_Billing_Discord::interaction( ...$signed( $command( 'buy', array( array( 'name' => 'plan', 'type' => 3, 'value' => 'owner' ) ) ) ) ) ), 'Unconfigured plans cannot create an intent.' );
    $token = $issue( 'lifetime' );
    $stored = get_transient( 'tc_billing_intent_' . hash( 'sha256', $token ) );
    $assert( $ids['user'] === $stored['discord_id'] && 'lifetime' === $stored['plan'] && $stored['expires'] > time() && ! str_contains( wp_json_encode( $stored ), $token ), 'Intent stores the signed actor and plan under a hash, never the bearer token.' );
    wp_set_current_user( 0 );
    $assert( is_wp_error( TC_Billing_Discord::consume_intent( $token, $ids['user'] ) ), 'A Discord ID argument without OAuth login cannot consume an intent.' );
    wp_set_current_user( $uids['other'] );
    $assert( is_wp_error( TC_Billing_Discord::consume_intent( $token, $ids['other'] ) ) && is_array( get_transient( 'tc_billing_intent_' . hash( 'sha256', $token ) ) ), 'Another OAuth actor cannot consume or destroy the original actor’s intent.' );
    wp_set_current_user( $uid );
    $assert( array( 'discord_id' => $ids['user'], 'plan' => 'lifetime', 'mode' => 'test' ) === TC_Billing_Discord::consume_intent( $token, $ids['user'] ), 'OAuth-bound actor consumes the exact stored plan and mode.' );
    $assert( is_wp_error( TC_Billing_Discord::consume_intent( $token, $ids['user'] ) ), 'A consumed intent cannot replay.' );
    $token = $issue(); $key = 'tc_billing_intent_' . hash( 'sha256', $token ); $stored = get_transient( $key ); $stored['expires'] = time() - 1; set_transient( $key, $stored, 30 );
    $assert( is_wp_error( TC_Billing_Discord::consume_intent( $token, $ids['user'] ) ), 'Explicit intent expiry is enforced even while its transient exists.' );
    update_option( 'timonconfigs_billing', array_merge( $billing, array( 'routing' => 'legacy' ) ), false );
    $assert( is_wp_error( TC_Billing_Discord::interaction( ...$signed( $command( 'buy' ) ) ) ), 'Legacy routing cannot create direct-checkout intents.' );
    $assert( count( $requests ) === $before, 'Signed commands and intent consumption never call Discord, billing providers, or collect payments.' );
    WP_CLI::success( "Discord billing integration: $checks assertions; local mock HTTP and real signature verification only." );
} finally {
    remove_filter( 'pre_http_request', $http, 1 );
    foreach ( $intents as $token ) { delete_transient( 'tc_billing_intent_' . hash( 'sha256', $token ) ); }
    global $wpdb;
    foreach ( $uids as $uid ) {
        foreach ( array( 'entitlements', 'owned_roles', 'sync_jobs' ) as $table ) { $wpdb->delete( $wpdb->prefix . 'tc_billing_' . $table, array( 'user_id' => $uid ) ); }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $uids as $actor => $uid ) { delete_option( '_tc_discord_binding_' . $ids[ $actor ] ); wp_delete_user( $uid ); }
    null === $old_billing ? delete_option( 'timonconfigs_billing' ) : update_option( 'timonconfigs_billing', $old_billing, false );
    null === $old_oauth ? delete_option( 'timonconfigs_discord' ) : update_option( 'timonconfigs_discord', $old_oauth, false );
    wp_set_current_user( $original_user );
}
