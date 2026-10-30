<?php
/** Discord identity and read-only guild entitlements. No payment or role automation. */
defined( 'ABSPATH' ) || exit;
final class TC_Discord {
    private const OPTION = 'timonconfigs_discord';
    private const TOKEN = '_tc_discord_token';
    private const CACHE = '_tc_discord_status';
    private const COOKIE = '__Host-tc_discord_state';
    private const API = 'https://discord.com/api/v10';

    public static function init(): void {
        add_action( 'init', static function (): void {
            if ( array_intersect( array( 'tc_discord_start', 'tc_discord_callback', 'tc_discord_logout', 'tc_discord_refresh' ), array_keys( $_GET ) ) && ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        }, 0 );
        add_action( 'template_redirect', array( __CLASS__, 'route' ), 0 );
        add_action( 'admin_menu', static function () { add_options_page( 'Discord membership', 'Discord membership', 'manage_options', 'tc-discord', array( __CLASS__, 'settings_page' ) ); } );
        add_action( 'admin_init', static function () { register_setting( 'tc_discord', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ), 'show_in_rest' => false ) ); } );
        add_action( 'tc_discord_clean_state', static function ( $key ) { if ( is_string( $key ) && str_starts_with( $key, '_tc_oauth_' ) ) { delete_option( $key ); } } );
    }
    private static function settings(): array {
        $s = get_option( self::OPTION, array() );
        $s = is_array( $s ) ? $s : array();
        $s['client_secret'] = defined( 'TIMONCONFIGS_DISCORD_CLIENT_SECRET' ) ? TIMONCONFIGS_DISCORD_CLIENT_SECRET : self::decrypt( $s['secret_encrypted'] ?? '' );
        return $s;
    }
    private static function snowflake( mixed $v ): bool { return is_string( $v ) && 1 === preg_match( '/^[1-9][0-9]{16,19}$/D', $v ); }
    /** A malformed allowlist disables the pilot instead of broadening access. */
    private static function pilot_ids( mixed $value ): array {
        if ( ! is_string( $value ) || strlen( $value ) > 512 ) { return array(); }
        $ids = preg_split( '/[\s,]+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY );
        if ( ! $ids || count( $ids ) > 10 ) { return array(); }
        foreach ( $ids as $id ) { if ( ! self::snowflake( $id ) ) { return array(); } }
        return array_values( array_unique( $ids ) );
    }
    /** Public billing approval covers member login even while new sales are paused. */
    private static function public_accounts(): bool {
        return class_exists( 'TC_Woo_Bridge' ) && TC_Woo_Bridge::public_accounts_enabled();
    }
    private static function identity_allowed( mixed $id ): bool {
        $s = self::settings();
        return self::snowflake( $id ) && ( self::public_accounts() || empty( $s['pilot_enabled'] ) || in_array( $id, self::pilot_ids( $s['pilot_ids'] ?? '' ), true ) );
    }
    public static function is_ready(): bool {
        $s = self::settings();
        $testing = in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
        $pilot = ! empty( $s['pilot_enabled'] );
        $public = self::public_accounts();
        if ( ! $public && $pilot && ! self::pilot_ids( $s['pilot_ids'] ?? '' ) ) { return false; }
        if ( empty( $s['enabled'] ) || ( ! $testing && ! $pilot && ! $public && empty( $s['staging_confirmed'] ) ) || ! is_string( $s['client_secret'] ) || '' === $s['client_secret'] || 'https' !== wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) || ! function_exists( 'openssl_encrypt' ) ) { return false; }
        foreach ( array( 'client_id', 'guild_id', 'free_role', 'monthly_role', 'lifetime_role' ) as $k ) { if ( ! self::snowflake( $s[ $k ] ?? null ) ) { return false; } }
        $role_ids = array( $s['free_role'], $s['monthly_role'], $s['lifetime_role'] );
        foreach ( array( 'staff_role', 'owner_role' ) as $key ) {
            if ( ! empty( $s[ $key ] ) ) {
                if ( ! self::snowflake( $s[ $key ] ) ) { return false; }
                $role_ids[] = $s[ $key ];
            }
        }
        return count( $role_ids ) === count( array_unique( $role_ids ) );
    }
    public static function sanitize_settings( mixed $value ): array {
        $old = get_option( self::OPTION, array() );
        if ( ! current_user_can( 'manage_options' ) || ! is_array( $value ) ) { return is_array( $old ) ? $old : array(); }
        $out = array();
        foreach ( array( 'enabled', 'staging_confirmed', 'pkce', 'pilot_enabled' ) as $k ) { $out[ $k ] = empty( $value[ $k ] ) ? 0 : 1; }
        foreach ( array( 'client_id', 'guild_id', 'free_role', 'monthly_role', 'lifetime_role', 'staff_role', 'owner_role' ) as $k ) { $out[ $k ] = self::snowflake( $value[ $k ] ?? null ) ? $value[ $k ] : ''; }
        $out['pilot_ids'] = implode( "\n", self::pilot_ids( $value['pilot_ids'] ?? '' ) );
        $out['owner_ids'] = implode( "\n", self::pilot_ids( $value['owner_ids'] ?? '' ) );
        $out['secret_encrypted'] = $old['secret_encrypted'] ?? '';
        // Settings API may sanitize twice when creating an option. Preserve only authenticated ciphertext.
        if ( ! empty( $value['secret_encrypted'] ) && '' !== self::decrypt( $value['secret_encrypted'] ) ) { $out['secret_encrypted'] = $value['secret_encrypted']; }
        if ( ! empty( $value['clear_secret'] ) ) { $out['secret_encrypted'] = ''; }
        if ( isset( $value['client_secret'] ) && is_string( $value['client_secret'] ) && '' !== trim( $value['client_secret'] ) ) { $out['secret_encrypted'] = self::encrypt( trim( $value['client_secret'] ) ); }
        return $out;
    }
    public static function settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $s = self::settings();
        $failure = get_transient( 'tc_discord_last_failure' );
        if ( is_array( $failure ) ) {
            echo '<div class="notice notice-warning"><p><strong>Last Discord verification failure (UTC)</strong>: ' . esc_html( (string) ( $failure['time'] ?? '' ) ) . ' — Stage: ' . esc_html( (string) ( $failure['stage'] ?? '' ) ) . '; Reason: ' . esc_html( (string) ( $failure['reason'] ?? '' ) ) . '; HTTP: ' . esc_html( (string) ( $failure['http'] ?? 0 ) ) . '</p><p>Only the most recent failed sign-in or membership verification is shown, for up to 24 hours. HTTP 0 means no HTTP status was recorded. No credentials or provider response bodies are stored.</p></div>';
        }
        echo '<div class="wrap"><h1>Discord membership</h1><p>Disabled until configured. Public production access can be approved in TimonConfigs billing for real customer purchases; this allows any Discord account to sign in and overrides the restricted pilot list below. It does not grant Free, VIP, Staff or Owner access. Otherwise, confirm real staging account tests or enable the restricted pilot for up to 10 specified Discord user IDs. Scope: identify guilds.members.read. No role writes. Free accepts all verified plans; Monthly accepts Monthly or Lifetime; Lifetime accepts Lifetime only. Role cache expires after 60 seconds; API errors deny access.</p><p>Register this exact callback: <code>' . esc_html( home_url( '/?tc_discord_callback=1' ) ) . '</code></p><form method="post" action="options.php">';
        settings_fields( 'tc_discord' );
        foreach ( array( 'enabled' => 'Enable OAuth', 'pilot_enabled' => 'Restricted pilot: only the listed Discord users may sign in and access member files', 'staging_confirmed' => 'I confirmed IDs, policy and real account tests on isolated staging', 'pkce' => 'Send PKCE S256 (enable only after Discord application support is verified; not live verified by this code)' ) as $k => $label ) { echo '<p><label><input type="checkbox" name="' . esc_attr( self::OPTION . '[' . $k . ']' ) . '" value="1" ' . checked( ! empty( $s[ $k ] ), true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
        echo '<p><label>Pilot Discord user IDs (one per line, maximum 10)<br><textarea name="' . esc_attr( self::OPTION . '[pilot_ids]' ) . '" rows="4" cols="30">' . esc_textarea( $s['pilot_ids'] ?? '' ) . '</textarea></label></p><p>The pilot requires Enable OAuth and a valid allowlist. Use a private browser window signed out of WordPress. When public billing access is not approved, other Discord users are denied before account creation and removing an ID also blocks its existing member access. Approved public billing access overrides this pilot list, including when new checkout routing is paused so existing customers can still manage their purchases. Disabling the pilot alone does not approve public production access.</p>';
        foreach ( array( 'client_id', 'guild_id', 'free_role', 'monthly_role', 'lifetime_role', 'staff_role', 'owner_role' ) as $k ) { echo '<p><label>' . esc_html( ucwords( str_replace( '_', ' ', $k ) ) ) . ' <input name="' . esc_attr( self::OPTION . '[' . $k . ']' ) . '" value="' . esc_attr( $s[ $k ] ?? '' ) . '" pattern="[0-9]{17,20}"></label></p>'; }
        echo '<p><label>Approved Owner Discord user IDs (one per line, maximum 10)<br><textarea name="' . esc_attr( self::OPTION . '[owner_ids]' ) . '" rows="4" cols="30">' . esc_textarea( $s['owner_ids'] ?? '' ) . '</textarea></label></p><p>Staff and Owner roles are optional and must use distinct role IDs. Badges show only these configured roles verified by Discord; arbitrary guild role names are not fetched. Frontend config management requires an approved Owner Discord user ID, its unique WordPress binding and an active WordPress login created through Discord OAuth2. It does not require a live Discord Owner role check on each edit or upload. Staff and Owner badges do not grant paid downloads, administrator access or additional Discord permissions. Pilot access remains a separate restriction.</p>';
        echo '<p>Client secret: ' . ( empty( $s['client_secret'] ) ? 'Not configured' : 'Configured (redacted)' ) . '</p><p><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION ) . '[client_secret]" value="" aria-label="Replace client secret"></p><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[clear_secret]" value="1">Clear stored secret (wp-config constant takes precedence)</label>';
        submit_button(); echo '</form></div>';
    }
    private static function encrypt( string $plain ): string {
        if ( ! function_exists( 'openssl_encrypt' ) ) { return ''; }
        $iv = random_bytes( 12 ); $tag = '';
        $cipher = openssl_encrypt( $plain, 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, $iv, $tag, 'tc-discord-v1' );
        return false === $cipher ? '' : base64_encode( $iv . $tag . $cipher );
    }
    private static function decrypt( mixed $blob ): string {
        if ( ! is_string( $blob ) || ! function_exists( 'openssl_decrypt' ) ) { return ''; }
        $raw = base64_decode( $blob, true );
        if ( false === $raw || strlen( $raw ) < 29 ) { return ''; }
        $plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), 'tc-discord-v1' );
        return false === $plain ? '' : $plain;
    }
    /** Safe UI-only hint; does not grant or remove any member access. */
    public static function is_pilot_enabled(): bool { return ! self::public_accounts() && ! empty( self::settings()['pilot_enabled'] ); }
    /** The effective waiting period for a fresh role check (not a bypass). */
    public static function retry_after( int $uid = 0 ): int {
        $uid = $uid ?: get_current_user_id();
        if ( ! $uid ) { return 0; }
        return max( 0, (int) get_user_meta( $uid, '_tc_discord_attempt', true ) + 10 - time(), (int) get_user_meta( $uid, '_tc_discord_retry_until', true ) - time() );
    }
    /** Only the two local membership checkouts can resume after OAuth. */
    private static function checkout_return( mixed $url ): string {
        if ( ! is_string( $url ) || strlen( $url ) > 2048 ) { return ''; }
        foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
            $base = home_url( '/checkout/' . $plan . '/' );
            if ( $url === $base ) { return $base; }
            if ( str_starts_with( $url, $base . '?' ) && preg_match( '/^tc_billing_intent=([a-f0-9]{64})$/D', substr( $url, strlen( $base ) + 1 ), $match ) ) {
                return add_query_arg( 'tc_billing_intent', $match[1], $base );
            }
        }
        return '';
    }
    public static function login_url( string $return_url = '' ): string {
        if ( ! self::is_ready() ) { return home_url( '/account/' ); }
        $url = home_url( '/?tc_discord_start=1' );
        $return_url = self::checkout_return( $return_url );
        return '' !== $return_url ? add_query_arg( 'tc_discord_return', $return_url, $url ) : $url;
    }
    public static function logout_url(): string { return add_query_arg( '_wpnonce', wp_create_nonce( 'tc_discord_logout' ), home_url( '/?tc_discord_logout=1' ) ); }
    public static function refresh_url(): string { return add_query_arg( '_wpnonce', wp_create_nonce( 'tc_discord_refresh' ), home_url( '/?tc_discord_refresh=1' ) ); }
    private static function error( string $stage = 'verification', string $reason = 'unavailable', int $http = 0 ): WP_Error { return new WP_Error( 'tc_discord_unavailable', 'Discord verification is unavailable. Please reconnect or try again shortly.', array( 'stage' => $stage, 'reason' => $reason, 'http' => $http ) ); }
    /** Store only our enumerated diagnostics, never provider bodies, URLs or credentials. */
    private static function record_failure( WP_Error $error ): void {
        $data = $error->get_error_data();
        $stages = array( 'token', 'identity', 'membership', 'binding', 'verification' );
        $reasons = array( 'transport', 'http', 'invalid_client', 'invalid_grant', 'invalid_request', 'unauthorized_client', 'invalid_scope', 'malformed_response', 'scope_mismatch', 'pilot_denied', 'identity_invalid', 'account_conflict', 'account_create_failed', 'rate_limited', 'unavailable' );
        set_transient( 'tc_discord_last_failure', array(
            'time' => gmdate( 'Y-m-d H:i:s' ),
            'stage' => in_array( $data['stage'] ?? '', $stages, true ) ? $data['stage'] : 'verification',
            'reason' => in_array( $data['reason'] ?? '', $reasons, true ) ? $data['reason'] : 'unavailable',
            'http' => is_int( $data['http'] ?? null ) && $data['http'] >= 100 && $data['http'] <= 599 ? $data['http'] : 0,
        ), DAY_IN_SECONDS );
    }
    private static function cookie( string $value, int $expires ): void { setcookie( self::COOKIE, $value, array( 'expires' => $expires, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' ) ); }
    /** Atomic single-use database claim; object-cache stale reads cannot replay state. */
    private static function consume( string $key, mixed $expected = null ): mixed {
        global $wpdb;
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $key ) );
        if ( null === $raw || ( null !== $expected && (string) $expected !== $raw ) ) { return false; }
        $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name = %s AND option_value = %s", $key, $raw ) );
        wp_cache_delete( $key, 'options' );
        return 1 === $deleted ? maybe_unserialize( $raw ) : false;
    }
    private static function create_state( string $return_url = '' ): array {
        $state = bin2hex( random_bytes( 32 ) ); $cookie = bin2hex( random_bytes( 32 ) );
        $verifier = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
        $s = self::settings();
        $data = array( 'cookie' => hash( 'sha256', $cookie ), 'expires' => time() + 300, 'uid' => get_current_user_id(), 'session' => hash( 'sha256', wp_get_session_token() ), 'verifier' => ! empty( $s['pkce'] ) ? $verifier : '', 'return_to' => self::checkout_return( $return_url ) );
        $key = '_tc_oauth_' . hash( 'sha256', $state );
        add_option( $key, self::encrypt( wp_json_encode( $data ) ), '', false );
        wp_schedule_single_event( time() + 360, 'tc_discord_clean_state', array( $key ) );
        $params = array( 'client_id' => $s['client_id'], 'redirect_uri' => home_url( '/?tc_discord_callback=1' ), 'response_type' => 'code', 'scope' => 'identify guilds.members.read', 'state' => $state );
        if ( $data['verifier'] ) { $params['code_challenge'] = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); $params['code_challenge_method'] = 'S256'; }
        return array( 'state' => $state, 'cookie' => $cookie, 'url' => 'https://discord.com/oauth2/authorize?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) );
    }
    private static function validate_state( string $state, string $cookie ): array|WP_Error {
        if ( ! preg_match( '/^[a-f0-9]{64}$/D', $state ) || ! preg_match( '/^[a-f0-9]{64}$/D', $cookie ) ) { return self::error(); }
        $data = json_decode( self::decrypt( self::consume( '_tc_oauth_' . hash( 'sha256', $state ) ) ), true );
        if ( ! is_array( $data ) || $data['expires'] <= time() || ! hash_equals( $data['cookie'], hash( 'sha256', $cookie ) ) || (int) $data['uid'] !== get_current_user_id() || ! hash_equals( $data['session'], hash( 'sha256', wp_get_session_token() ) ) ) { return self::error(); }
        return $data;
    }
    private static function request( string $path, ?array $body = null, string $token = '' ): array|WP_Error {
        $args = array( 'timeout' => 12, 'redirection' => 0, 'limit_response_size' => 65536, 'headers' => array( 'Accept' => 'application/json' ) );
        if ( null !== $body ) { $args['method'] = 'POST'; $args['body'] = $body; }
        if ( $token ) { $args['headers']['Authorization'] = 'Bearer ' . $token; }
        $endpoint = str_starts_with( $path, '/oauth2/' ) ? 'https://discord.com/api' . $path : self::API . $path;
        $r = wp_remote_request( $endpoint, $args );
        $stage = '/oauth2/token' === $path ? 'token' : ( '/users/@me' === $path ? 'identity' : 'membership' );
        if ( is_wp_error( $r ) ) { return self::error( $stage, 'transport' ); }
        $http = (int) wp_remote_retrieve_response_code( $r );
        $data = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( 200 !== $http ) {
            // Discord returns Retry-After with HTTP 429. Never retry immediately.
            $reason = 429 === $http ? 'rate_limited' : ( is_array( $data ) && in_array( $data['error'] ?? '', array( 'invalid_client', 'invalid_grant', 'invalid_request', 'unauthorized_client', 'invalid_scope' ), true ) ? $data['error'] : 'http' );
            $error = self::error( $stage, $reason, $http );
            if ( 429 === $http ) {
                $header = wp_remote_retrieve_header( $r, 'retry-after' );
                $delay = is_numeric( $header ) ? (float) $header : ( is_array( $data ) && is_numeric( $data['retry_after'] ?? null ) ? (float) $data['retry_after'] : 10.0 );
                $meta = $error->get_error_data();
                $meta['retry_after'] = max( 1, min( 300, (int) ceil( $delay ) ) );
                $error->add_data( $meta );
            }
            return $error;
        }
        return is_array( $data ) ? $data : self::error( $stage, 'malformed_response', $http );
    }
    private static function token_request( array $params ): array|WP_Error {
        $s = self::settings();
        $r = self::request( '/oauth2/token', array_merge( array( 'client_id' => $s['client_id'], 'client_secret' => $s['client_secret'] ), $params ) );
        if ( is_wp_error( $r ) ) { return $r; }
        if ( ! is_string( $r['access_token'] ?? null ) || '' === $r['access_token'] || ! is_string( $r['refresh_token'] ?? null ) || '' === $r['refresh_token'] || ! is_int( $r['expires_in'] ?? null ) || $r['expires_in'] <= 0 || ! is_string( $r['scope'] ?? null ) || 'Bearer' !== ( $r['token_type'] ?? '' ) ) { return self::error( 'token', 'malformed_response', 200 ); }
        $scopes = explode( ' ', $r['scope'] ?? '' );
        if ( array_diff( array( 'identify', 'guilds.members.read' ), $scopes ) || array_diff( $scopes, array( 'identify', 'guilds.members.read' ) ) ) { return self::error( 'token', 'scope_mismatch', 200 ); }
        return array( 'access_token' => $r['access_token'], 'refresh_token' => $r['refresh_token'], 'expires' => time() + min( 604800, absint( $r['expires_in'] ) ) );
    }
    /** Never links by email or username; binding options and WP login are unique. */
    private static function bind( array $identity ): int|WP_Error {
        $id = $identity['id'] ?? '';
        if ( ! self::snowflake( $id ) ) { return self::error( 'identity', 'identity_invalid' ); }
        if ( ! self::identity_allowed( $id ) ) { return self::error( 'binding', 'pilot_denied' ); }
        $key = '_tc_discord_binding_' . $id;
        $bound = (int) get_option( $key, 0 ); $current = get_current_user_id();
        if ( $bound ) { return get_userdata( $bound ) && ( ! $current || $current === $bound ) && $id === get_user_meta( $bound, '_tc_discord_id', true ) ? $bound : self::error( 'binding', 'account_conflict' ); }
        // Existing WordPress accounts require a separate administrative migration; no implicit attachment.
        if ( $current || ! add_option( $key, -1, '', false ) ) { return self::error( 'binding', 'account_conflict' ); }
        // Keep the Discord snowflake out of potentially public WordPress author slugs.
        $uid = wp_insert_user( array( 'user_login' => 'tc_member_' . bin2hex( random_bytes( 12 ) ), 'user_pass' => wp_generate_password( 64, true, true ), 'display_name' => sanitize_text_field( $identity['global_name'] ?? $identity['username'] ?? 'Discord member' ), 'role' => 'subscriber' ) );
        if ( is_wp_error( $uid ) ) { delete_option( $key ); return self::error( 'binding', 'account_create_failed' ); }
        update_user_meta( $uid, '_tc_discord_id', $id ); update_option( $key, $uid, false );
        return $uid;
    }
    public static function route(): void {
        $keys = array( 'tc_discord_start', 'tc_discord_callback', 'tc_discord_logout', 'tc_discord_refresh' );
        if ( ! array_intersect( $keys, array_keys( $_GET ) ) ) { return; }
        nocache_headers(); header( 'Referrer-Policy: no-referrer' ); header( 'X-Robots-Tag: noindex, nofollow' );
        if ( isset( $_GET['tc_discord_logout'] ) || isset( $_GET['tc_discord_refresh'] ) ) {
            $logout = isset( $_GET['tc_discord_logout'] );
            check_admin_referer( $logout ? 'tc_discord_logout' : 'tc_discord_refresh' );
            if ( ! get_current_user_id() ) { wp_die( 'Please sign in.', '', array( 'response' => 403 ) ); }
            if ( $logout ) { self::revoke( get_current_user_id() ); wp_logout(); } else { self::status( get_current_user_id(), true ); }
            wp_safe_redirect( home_url( '/account/' ) ); exit;
        }
        if ( ! self::is_ready() || ! is_ssl() ) { wp_die( 'Discord sign-in is not configured.', '', array( 'response' => 503 ) ); }
        if ( isset( $_GET['tc_discord_start'] ) ) {
            $return_url = is_string( $_GET['tc_discord_return'] ?? null ) ? wp_unslash( $_GET['tc_discord_return'] ) : '';
            $state = self::create_state( $return_url ); self::cookie( $state['cookie'], time() + 300 ); wp_redirect( $state['url'] ); exit;
        }
        $state = self::validate_state( is_string( $_GET['state'] ?? null ) ? $_GET['state'] : '', is_string( $_COOKIE[ self::COOKIE ] ?? null ) ? $_COOKIE[ self::COOKIE ] : '' );
        self::cookie( '', time() - 3600 );
        if ( is_wp_error( $state ) || ! is_string( $_GET['code'] ?? null ) || strlen( $_GET['code'] ) > 2048 ) { wp_die( 'Sign-in expired. Please try again.', '', array( 'response' => 403 ) ); }
        $params = array( 'grant_type' => 'authorization_code', 'code' => wp_unslash( $_GET['code'] ), 'redirect_uri' => home_url( '/?tc_discord_callback=1' ) );
        if ( $state['verifier'] ) { $params['code_verifier'] = $state['verifier']; }
        $tokens = self::token_request( $params );
        $identity = is_wp_error( $tokens ) ? $tokens : self::request( '/users/@me', null, $tokens['access_token'] );
        $uid = is_wp_error( $identity ) ? $identity : self::bind( $identity );
        if ( is_wp_error( $uid ) ) {
            self::record_failure( $uid );
            $data = $uid->get_error_data();
            $reason = is_array( $data ) ? (string) ( $data['reason'] ?? '' ) : '';
            if ( 'pilot_denied' === $reason ) {
                wp_die( 'Discord sign-in is currently limited to invited test accounts. Please contact the TimonConfigs team for access.', 'Limited beta access', array( 'response' => 403, 'link_url' => home_url( '/' ), 'link_text' => 'Back to TimonConfigs' ) );
            }
            if ( 'account_conflict' === $reason ) {
                wp_die( 'A different WordPress account is already signed in, or this Discord identity is already linked elsewhere. Sign out of WordPress and retry in a private browser tab, or contact support.', 'Account linking conflict', array( 'response' => 403, 'link_url' => home_url( '/account/' ), 'link_text' => 'Back to account' ) );
            }
            wp_die( self::error()->get_error_message(), '', array( 'response' => 403 ) );
        }
        update_user_meta( $uid, self::TOKEN, self::encrypt( wp_json_encode( $tokens ) ) ); delete_user_meta( $uid, self::CACHE );
        $name = sanitize_text_field( $identity['global_name'] ?? $identity['username'] ?? 'Discord member' );
        update_user_meta( $uid, '_tc_discord_name', $name );
        $avatar = $identity['avatar'] ?? '';
        update_user_meta( $uid, '_tc_discord_avatar', is_string( $avatar ) && preg_match( '/^(a_)?[a-f0-9]{32}$/D', $avatar ) ? 'https://cdn.discordapp.com/avatars/' . $identity['id'] . '/' . $avatar . '.png?size=128' : '' );
        wp_clear_auth_cookie(); wp_set_current_user( $uid ); wp_set_auth_cookie( $uid, false, true );
        // The callback never trusts a return URL from its own query string.
        // Checkout still requires its authenticated POST; signing in cannot charge.
        wp_safe_redirect( self::checkout_return( $state['return_to'] ?? '' ) ?: home_url( '/account/' ) ); exit;
    }
    /** Logout invalidates local tokens first, even when remote revocation fails. */
    public static function revoke( int $uid ): void {
        $tokens = json_decode( self::decrypt( get_user_meta( $uid, self::TOKEN, true ) ), true );
        delete_user_meta( $uid, self::TOKEN ); delete_user_meta( $uid, self::CACHE );
        if ( is_array( $tokens ) && ! empty( $tokens['refresh_token'] ) && self::is_ready() ) {
            $s = self::settings();
            self::request( '/oauth2/token/revoke', array( 'token' => $tokens['refresh_token'], 'token_type_hint' => 'refresh_token', 'client_id' => $s['client_id'], 'client_secret' => $s['client_secret'] ) );
        }
    }
    private static function unavailable( array $base ): array { $base['state'] = 'unavailable'; $base['error'] = self::error()->get_error_message(); return $base; }
    /** Owner-visible data only. TTL = 60 seconds; failed refresh never retains access. */
    public static function status( int $user_id = 0, bool $force = false ): array {
        $uid = $user_id ?: get_current_user_id();
        $base = array( 'state' => 'unverified', 'checked_at' => 0, 'expires_at' => 0, 'display_name' => '', 'avatar' => '', 'discord_id' => '', 'error' => '', 'badges' => array(), 'staff_verified' => false, 'owner_verified' => false, 'member_verified' => false );
        if ( ! $uid || ! self::is_ready() ) { return $base; }
        $id = get_user_meta( $uid, '_tc_discord_id', true );
        if ( ! self::identity_allowed( $id ) || (int) get_option( '_tc_discord_binding_' . $id ) !== $uid ) { return $base; }
        $base['discord_id'] = $id; $base['display_name'] = (string) get_user_meta( $uid, '_tc_discord_name', true );
        $avatar = (string) get_user_meta( $uid, '_tc_discord_avatar', true );
        $base['avatar'] = str_starts_with( $avatar, 'https://cdn.discordapp.com/avatars/' . $id . '/' ) ? esc_url_raw( $avatar ) : '';
        $blob = get_user_meta( $uid, self::TOKEN, true ); $tokens = json_decode( self::decrypt( $blob ), true );
        if ( ! is_array( $tokens ) ) { return $base; }
        $s = self::settings(); $fingerprint = hash( 'sha256', $blob . wp_json_encode( array_intersect_key( $s, array_flip( array( 'guild_id', 'free_role', 'monthly_role', 'lifetime_role', 'staff_role', 'owner_role', 'owner_ids', 'client_id' ) ) ) ) );
        $cache = get_user_meta( $uid, self::CACHE, true );
        if ( ! $force && is_array( $cache ) && ( $cache['fingerprint'] ?? '' ) === $fingerprint && ( $cache['expires_at'] ?? 0 ) > time() && $tokens['expires'] > time() + 30 ) { unset( $cache['fingerprint'] ); return $cache; }
        // Discard expired/mismatched credentials; keep only a truly valid short-lived
        // cache during a forced write verification or transient provider failure.
        if ( is_array( $cache ) && ( ( $cache['fingerprint'] ?? '' ) !== $fingerprint || ( $cache['expires_at'] ?? 0 ) <= time() || $tokens['expires'] <= time() + 30 ) ) {
            delete_user_meta( $uid, self::CACHE );
        }
        // Respect Discord rate limits. Successful cached reads are handled above;
        // privileged writes still require a fresh verification and fail closed.
        $rate_retry = (int) get_user_meta( $uid, '_tc_discord_retry_until', true );
        if ( $rate_retry > time() ) { return self::unavailable( $base ); }
        // Short lease acquired atomically. Never serve stale entitlements while refreshing.
        $lock = '_tc_discord_lock_' . $uid;
        $owner = ( time() + 30 ) . ':' . bin2hex( random_bytes( 16 ) );
        if ( ! add_option( $lock, $owner, '', false ) ) {
            $lease = get_option( $lock );
            if ( (int) $lease < time() ) { self::consume( $lock, $lease ); }
            return self::unavailable( $base );
        }
        try {
            $last = (int) get_user_meta( $uid, '_tc_discord_attempt', true );
            if ( $last > time() - 10 ) { return self::unavailable( $base ); }
            // Preserve the last successful short-lived result while verifying.
            // On failure the *write* must still fail closed; a GET may display
            // the existing 60-second cached Owner page without a fake logout.
            update_user_meta( $uid, '_tc_discord_attempt', time() );
            if ( $tokens['expires'] <= time() + 30 ) {
                $fresh = self::token_request( array( 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'] ) );
                if ( is_wp_error( $fresh ) ) { self::record_failure( $fresh ); return self::unavailable( $base ); }
                $new_blob = self::encrypt( wp_json_encode( $fresh ) );
                // Compare-and-swap cannot resurrect credentials deleted by a concurrent logout.
                global $wpdb;
                $changed = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->usermeta SET meta_value = %s WHERE user_id = %d AND meta_key = %s AND meta_value = %s", $new_blob, $uid, self::TOKEN, $blob ) );
                wp_cache_delete( $uid, 'user_meta' );
                if ( 1 !== $changed ) { return self::unavailable( $base ); }
                $tokens = $fresh; $blob = $new_blob;
                $fingerprint = hash( 'sha256', $blob . wp_json_encode( array_intersect_key( $s, array_flip( array( 'guild_id', 'free_role', 'monthly_role', 'lifetime_role', 'staff_role', 'owner_role', 'owner_ids', 'client_id' ) ) ) ) );
            }
            $member = self::request( '/users/@me/guilds/' . $s['guild_id'] . '/member', null, $tokens['access_token'] );
            if ( is_wp_error( $member ) ) {
                self::record_failure( $member );
                $info = $member->get_error_data();
                if ( is_array( $info ) && 'rate_limited' === ( $info['reason'] ?? '' ) ) {
                    update_user_meta( $uid, '_tc_discord_retry_until', time() + max( 1, min( 300, (int) ( $info['retry_after'] ?? 10 ) ) ) );
                }
                return self::unavailable( $base );
            }
            delete_user_meta( $uid, '_tc_discord_retry_until' );
            if ( ! is_array( $member['roles'] ?? null ) || ( $member['user']['id'] ?? '' ) !== $id || ( isset( $member['guild_id'] ) && $member['guild_id'] !== $s['guild_id'] ) ) { self::record_failure( self::error( 'membership', 'malformed_response', 200 ) ); return self::unavailable( $base ); }
            if ( $blob !== get_user_meta( $uid, self::TOKEN, true ) ) { return self::unavailable( $base ); }
            foreach ( $member['roles'] as $role ) { if ( ! self::snowflake( $role ) ) { self::record_failure( self::error( 'membership', 'malformed_response', 200 ) ); return self::unavailable( $base ); } }
            // Identity/server membership is separate from entitlement roles. This flag is presentation-only.
            $base['member_verified'] = true;
            foreach ( array( 'lifetime', 'monthly', 'free' ) as $tier ) { if ( in_array( $s[ $tier . '_role' ], $member['roles'], true ) ) { $base['state'] = $tier; break; } }
            // These are product labels for configured, verified immutable role IDs, not arbitrary guild role names.
            foreach ( array( 'free' => 'Free Member', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime', 'staff' => 'Staff', 'owner' => 'Owner' ) as $key => $label ) {
                if ( ! empty( $s[ $key . '_role' ] ) && in_array( $s[ $key . '_role' ], $member['roles'], true ) ) { $base['badges'][ $key ] = $label; }
            }
            $base['staff_verified'] = isset( $base['badges']['staff'] );
            $base['owner_verified'] = isset( $base['badges']['owner'] ) && in_array( $id, self::pilot_ids( $s['owner_ids'] ?? '' ), true );
            $base['checked_at'] = time(); $base['expires_at'] = min( time() + 60, $tokens['expires'] - 30 );
            update_user_meta( $uid, self::CACHE, array_merge( $base, array( 'fingerprint' => $fingerprint ) ) );
            // Observers may enqueue reconciliation, never infer paid access from identity alone.
            do_action( 'timonconfigs_discord_member_verified', $uid, $base );
            return $base;
        } finally { self::consume( $lock, $owner ); }
    }
    /**
     * Frontend Owner tools are restricted to authenticated, explicitly allowlisted
     * Discord IDs bound one-to-one to their current WordPress account.
     * Does NOT request guild roles or call Discord API on reads or writes.
     * Does not confer VIP downloads or WordPress administrator privileges.
     */
    public static function can_manage_configs_local( int $user_id = 0 ): bool {
        $current = get_current_user_id();
        $uid = $user_id ?: $current;
        if ( ! $current || $uid !== $current || ! is_user_logged_in() || ! self::is_ready() || ! get_userdata( $uid ) ) { return false; }
        $id = get_user_meta( $uid, '_tc_discord_id', true );
        if ( ! self::identity_allowed( $id ) || (int) get_option( '_tc_discord_binding_' . $id ) !== $uid ) { return false; }
        $owner_ids = self::pilot_ids( self::settings()['owner_ids'] ?? '' );
        if ( ! in_array( $id, $owner_ids, true ) ) { return false; }
        // A valid OAuth-bound WordPress session must still have the encrypted
        // credential set by our callback. Logout/revocation removes it.
        $blob = get_user_meta( $uid, self::TOKEN, true );
        $tokens = json_decode( self::decrypt( $blob ), true );
        return is_array( $tokens )
            && is_string( $tokens['access_token'] ?? null ) && '' !== $tokens['access_token']
            && is_string( $tokens['refresh_token'] ?? null ) && '' !== $tokens['refresh_token'];
    }

    /** Fresh Owner authorization for writes; no WordPress administrator or membership-tier bypass. */
    public static function can_manage_configs( int $user_id = 0, bool $force = true ): bool|WP_Error {
        $uid = $user_id ?: get_current_user_id();
        if ( ! $uid || ! get_userdata( $uid ) ) { return false; }
        $status = self::status( $uid, $force );
        if ( 'unavailable' === $status['state'] ) {
            $error = self::error();
            $data = $error->get_error_data();
            $data['retry_after'] = self::retry_after( $uid );
            $error->add_data( $data );
            return $error;
        }
        return ! empty( $status['owner_verified'] ) && ! empty( $status['checked_at'] ) && $status['expires_at'] > time();
    }
    public static function can_access( int $config_id, int $user_id = 0, bool $force = false ): bool|WP_Error {
        if ( 'tc_config' !== get_post_type( $config_id ) || 'publish' !== get_post_status( $config_id ) || 'available' !== get_post_meta( $config_id, 'tc_release_status', true ) || '' !== (string) get_post_field( 'post_password', $config_id ) ) { return false; }
        $tier = get_post_meta( $config_id, 'tc_tier', true );
        $allowed = array( 'free' => array( 'free', 'monthly', 'lifetime' ), 'monthly' => array( 'monthly', 'lifetime' ), 'lifetime' => array( 'lifetime' ) );
        if ( ! isset( $allowed[ $tier ] ) ) { return false; }
        $status = self::status( $user_id, $force );
        if ( 'unavailable' === $status['state'] ) { return self::error(); }
        $role_allowed = in_array( $status['state'], $allowed[ $tier ], true );
        $billing_allowed = class_exists( 'TC_Woo_Bridge' ) ? TC_Woo_Bridge::access_constraint( $tier, $user_id ?: get_current_user_id() ) : null;
        return $role_allowed && false !== $billing_allowed;
    }
}
