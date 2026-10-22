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
    public static function is_ready(): bool {
        $s = self::settings();
        $testing = in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
        if ( empty( $s['enabled'] ) || ( ! $testing && empty( $s['staging_confirmed'] ) ) || ! is_string( $s['client_secret'] ) || '' === $s['client_secret'] || 'https' !== wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) || ! function_exists( 'openssl_encrypt' ) ) { return false; }
        foreach ( array( 'client_id', 'guild_id', 'free_role', 'monthly_role', 'lifetime_role' ) as $k ) { if ( ! self::snowflake( $s[ $k ] ?? null ) ) { return false; } }
        return 3 === count( array_unique( array( $s['free_role'], $s['monthly_role'], $s['lifetime_role'] ) ) );
    }
    public static function sanitize_settings( mixed $value ): array {
        $old = get_option( self::OPTION, array() );
        if ( ! current_user_can( 'manage_options' ) || ! is_array( $value ) ) { return is_array( $old ) ? $old : array(); }
        $out = array();
        foreach ( array( 'enabled', 'staging_confirmed', 'pkce' ) as $k ) { $out[ $k ] = empty( $value[ $k ] ) ? 0 : 1; }
        foreach ( array( 'client_id', 'guild_id', 'free_role', 'monthly_role', 'lifetime_role' ) as $k ) { $out[ $k ] = self::snowflake( $value[ $k ] ?? null ) ? $value[ $k ] : ''; }
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
        echo '<div class="wrap"><h1>Discord membership</h1><p>Disabled until configured. Production additionally requires confirmed real staging account tests. Set WP_ENVIRONMENT_TYPE to staging on an isolated HTTPS staging site to run those tests before production confirmation. Scope: identify guilds.members.read. No role writes. Free accepts all verified plans; Monthly accepts Monthly or Lifetime; Lifetime accepts Lifetime only. Role cache expires after 60 seconds; API errors deny access.</p><p>Register this exact callback: <code>' . esc_html( home_url( '/?tc_discord_callback=1' ) ) . '</code></p><form method="post" action="options.php">';
        settings_fields( 'tc_discord' );
        foreach ( array( 'enabled' => 'Enable OAuth', 'staging_confirmed' => 'I confirmed IDs, policy and real account tests on isolated staging', 'pkce' => 'Send PKCE S256 (enable only after Discord application support is verified; not live verified by this code)' ) as $k => $label ) { echo '<p><label><input type="checkbox" name="' . esc_attr( self::OPTION . '[' . $k . ']' ) . '" value="1" ' . checked( ! empty( $s[ $k ] ), true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
        foreach ( array( 'client_id', 'guild_id', 'free_role', 'monthly_role', 'lifetime_role' ) as $k ) { echo '<p><label>' . esc_html( ucwords( str_replace( '_', ' ', $k ) ) ) . ' <input name="' . esc_attr( self::OPTION . '[' . $k . ']' ) . '" value="' . esc_attr( $s[ $k ] ?? '' ) . '" pattern="[0-9]{17,20}"></label></p>'; }
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
    public static function login_url(): string { return self::is_ready() ? home_url( '/?tc_discord_start=1' ) : home_url( '/account/' ); }
    public static function logout_url(): string { return add_query_arg( '_wpnonce', wp_create_nonce( 'tc_discord_logout' ), home_url( '/?tc_discord_logout=1' ) ); }
    public static function refresh_url(): string { return add_query_arg( '_wpnonce', wp_create_nonce( 'tc_discord_refresh' ), home_url( '/?tc_discord_refresh=1' ) ); }
    private static function error(): WP_Error { return new WP_Error( 'tc_discord_unavailable', 'Discord verification is unavailable. Please reconnect or try again shortly.' ); }
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
    private static function create_state(): array {
        $state = bin2hex( random_bytes( 32 ) ); $cookie = bin2hex( random_bytes( 32 ) );
        $verifier = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
        $s = self::settings();
        $data = array( 'cookie' => hash( 'sha256', $cookie ), 'expires' => time() + 300, 'uid' => get_current_user_id(), 'session' => hash( 'sha256', wp_get_session_token() ), 'verifier' => ! empty( $s['pkce'] ) ? $verifier : '' );
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
        if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) { return self::error(); }
        $data = json_decode( wp_remote_retrieve_body( $r ), true );
        return is_array( $data ) ? $data : self::error();
    }
    private static function token_request( array $params ): array|WP_Error {
        $s = self::settings();
        $r = self::request( '/oauth2/token', array_merge( array( 'client_id' => $s['client_id'], 'client_secret' => $s['client_secret'] ), $params ) );
        if ( is_wp_error( $r ) || ! is_string( $r['access_token'] ?? null ) || '' === $r['access_token'] || ! is_string( $r['refresh_token'] ?? null ) || '' === $r['refresh_token'] || ! is_int( $r['expires_in'] ?? null ) || $r['expires_in'] <= 0 || ! is_string( $r['scope'] ?? null ) || 'Bearer' !== ( $r['token_type'] ?? '' ) ) { return self::error(); }
        $scopes = explode( ' ', $r['scope'] ?? '' );
        if ( array_diff( array( 'identify', 'guilds.members.read' ), $scopes ) || array_diff( $scopes, array( 'identify', 'guilds.members.read' ) ) ) { return self::error(); }
        return array( 'access_token' => $r['access_token'], 'refresh_token' => $r['refresh_token'], 'expires' => time() + min( 604800, absint( $r['expires_in'] ) ) );
    }
    /** Never links by email or username; binding options and WP login are unique. */
    private static function bind( array $identity ): int|WP_Error {
        $id = $identity['id'] ?? '';
        if ( ! self::snowflake( $id ) ) { return self::error(); }
        $key = '_tc_discord_binding_' . $id;
        $bound = (int) get_option( $key, 0 ); $current = get_current_user_id();
        if ( $bound ) { return get_userdata( $bound ) && ( ! $current || $current === $bound ) && $id === get_user_meta( $bound, '_tc_discord_id', true ) ? $bound : self::error(); }
        // Existing WordPress accounts require a separate administrative migration; no implicit attachment.
        if ( $current || ! add_option( $key, -1, '', false ) ) { return self::error(); }
        // Keep the Discord snowflake out of potentially public WordPress author slugs.
        $uid = wp_insert_user( array( 'user_login' => 'tc_member_' . bin2hex( random_bytes( 12 ) ), 'user_pass' => wp_generate_password( 64, true, true ), 'display_name' => sanitize_text_field( $identity['global_name'] ?? $identity['username'] ?? 'Discord member' ), 'role' => 'subscriber' ) );
        if ( is_wp_error( $uid ) ) { delete_option( $key ); return self::error(); }
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
        if ( isset( $_GET['tc_discord_start'] ) ) { $state = self::create_state(); self::cookie( $state['cookie'], time() + 300 ); wp_redirect( $state['url'] ); exit; }
        $state = self::validate_state( is_string( $_GET['state'] ?? null ) ? $_GET['state'] : '', is_string( $_COOKIE[ self::COOKIE ] ?? null ) ? $_COOKIE[ self::COOKIE ] : '' );
        self::cookie( '', time() - 3600 );
        if ( is_wp_error( $state ) || ! is_string( $_GET['code'] ?? null ) || strlen( $_GET['code'] ) > 2048 ) { wp_die( 'Sign-in expired. Please try again.', '', array( 'response' => 403 ) ); }
        $params = array( 'grant_type' => 'authorization_code', 'code' => wp_unslash( $_GET['code'] ), 'redirect_uri' => home_url( '/?tc_discord_callback=1' ) );
        if ( $state['verifier'] ) { $params['code_verifier'] = $state['verifier']; }
        $tokens = self::token_request( $params );
        $identity = is_wp_error( $tokens ) ? $tokens : self::request( '/users/@me', null, $tokens['access_token'] );
        $uid = is_wp_error( $identity ) ? $identity : self::bind( $identity );
        if ( is_wp_error( $uid ) ) { wp_die( self::error()->get_error_message(), '', array( 'response' => 403 ) ); }
        update_user_meta( $uid, self::TOKEN, self::encrypt( wp_json_encode( $tokens ) ) ); delete_user_meta( $uid, self::CACHE );
        $name = sanitize_text_field( $identity['global_name'] ?? $identity['username'] ?? 'Discord member' );
        update_user_meta( $uid, '_tc_discord_name', $name );
        $avatar = $identity['avatar'] ?? '';
        update_user_meta( $uid, '_tc_discord_avatar', is_string( $avatar ) && preg_match( '/^(a_)?[a-f0-9]{32}$/D', $avatar ) ? 'https://cdn.discordapp.com/avatars/' . $identity['id'] . '/' . $avatar . '.png?size=128' : '' );
        wp_clear_auth_cookie(); wp_set_current_user( $uid ); wp_set_auth_cookie( $uid, false, true );
        wp_safe_redirect( home_url( '/account/' ) ); exit;
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
        $base = array( 'state' => 'unverified', 'checked_at' => 0, 'expires_at' => 0, 'display_name' => '', 'avatar' => '', 'discord_id' => '', 'error' => '' );
        if ( ! $uid || ! self::is_ready() ) { return $base; }
        $id = get_user_meta( $uid, '_tc_discord_id', true );
        if ( ! self::snowflake( $id ) || (int) get_option( '_tc_discord_binding_' . $id ) !== $uid ) { return $base; }
        $base['discord_id'] = $id; $base['display_name'] = (string) get_user_meta( $uid, '_tc_discord_name', true );
        $avatar = (string) get_user_meta( $uid, '_tc_discord_avatar', true );
        $base['avatar'] = str_starts_with( $avatar, 'https://cdn.discordapp.com/avatars/' . $id . '/' ) ? esc_url_raw( $avatar ) : '';
        $blob = get_user_meta( $uid, self::TOKEN, true ); $tokens = json_decode( self::decrypt( $blob ), true );
        if ( ! is_array( $tokens ) ) { return $base; }
        $s = self::settings(); $fingerprint = hash( 'sha256', $blob . wp_json_encode( array_intersect_key( $s, array_flip( array( 'guild_id', 'free_role', 'monthly_role', 'lifetime_role', 'client_id' ) ) ) ) );
        $cache = get_user_meta( $uid, self::CACHE, true );
        if ( ! $force && is_array( $cache ) && ( $cache['fingerprint'] ?? '' ) === $fingerprint && ( $cache['expires_at'] ?? 0 ) > time() && $tokens['expires'] > time() + 30 ) { unset( $cache['fingerprint'] ); return $cache; }
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
            update_user_meta( $uid, '_tc_discord_attempt', time() ); delete_user_meta( $uid, self::CACHE );
            if ( $tokens['expires'] <= time() + 30 ) {
                $fresh = self::token_request( array( 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'] ) );
                if ( is_wp_error( $fresh ) ) { return self::unavailable( $base ); }
                $new_blob = self::encrypt( wp_json_encode( $fresh ) );
                // Compare-and-swap cannot resurrect credentials deleted by a concurrent logout.
                global $wpdb;
                $changed = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->usermeta SET meta_value = %s WHERE user_id = %d AND meta_key = %s AND meta_value = %s", $new_blob, $uid, self::TOKEN, $blob ) );
                wp_cache_delete( $uid, 'user_meta' );
                if ( 1 !== $changed ) { return self::unavailable( $base ); }
                $tokens = $fresh; $blob = $new_blob;
                $fingerprint = hash( 'sha256', $blob . wp_json_encode( array_intersect_key( $s, array_flip( array( 'guild_id', 'free_role', 'monthly_role', 'lifetime_role', 'client_id' ) ) ) ) );
            }
            $member = self::request( '/users/@me/guilds/' . $s['guild_id'] . '/member', null, $tokens['access_token'] );
            if ( is_wp_error( $member ) || ! is_array( $member['roles'] ?? null ) || ( $member['user']['id'] ?? '' ) !== $id || ( isset( $member['guild_id'] ) && $member['guild_id'] !== $s['guild_id'] ) || $blob !== get_user_meta( $uid, self::TOKEN, true ) ) { return self::unavailable( $base ); }
            foreach ( $member['roles'] as $role ) { if ( ! self::snowflake( $role ) ) { return self::unavailable( $base ); } }
            foreach ( array( 'lifetime', 'monthly', 'free' ) as $tier ) { if ( in_array( $s[ $tier . '_role' ], $member['roles'], true ) ) { $base['state'] = $tier; break; } }
            $base['checked_at'] = time(); $base['expires_at'] = min( time() + 60, $tokens['expires'] - 30 );
            update_user_meta( $uid, self::CACHE, array_merge( $base, array( 'fingerprint' => $fingerprint ) ) );
            return $base;
        } finally { self::consume( $lock, $owner ); }
    }
    public static function can_access( int $config_id, int $user_id = 0, bool $force = false ): bool|WP_Error {
        if ( 'tc_config' !== get_post_type( $config_id ) || 'publish' !== get_post_status( $config_id ) || 'available' !== get_post_meta( $config_id, 'tc_release_status', true ) || '' !== (string) get_post_field( 'post_password', $config_id ) ) { return false; }
        $tier = get_post_meta( $config_id, 'tc_tier', true );
        $allowed = array( 'free' => array( 'free', 'monthly', 'lifetime' ), 'monthly' => array( 'monthly', 'lifetime' ), 'lifetime' => array( 'lifetime' ) );
        if ( ! isset( $allowed[ $tier ] ) ) { return false; }
        $status = self::status( $user_id, $force );
        return 'unavailable' === $status['state'] ? self::error() : in_array( $status['state'], $allowed[ $tier ], true );
    }
}
