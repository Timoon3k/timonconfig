<?php
/**
 * Opt-in Discord VIP role outbox and signed, website-only slash commands.
 *
 * Register commands manually in the Discord developer application: /buy with an
 * optional string "plan" choice (monthly or lifetime), /membership, /manage and
 * /link. This class never registers commands or runs a gateway connection.
 */
defined( 'ABSPATH' ) || exit;

final class TC_Billing_Discord {
    private const API = 'https://discord.com/api/v10';
    private const INTENT_TTL = 600;

    private static function error( string $reason, int $retry_after = 300, int $http = 0 ): WP_Error {
        $messages = array(
            'disabled' => 'Discord billing automation is disabled.',
            'configuration' => 'Discord billing configuration needs administrator review.',
            'binding' => 'A unique Discord OAuth account binding is required.',
            'permissions' => 'The Discord bot needs Manage Roles permission.',
            'hierarchy' => 'The Discord VIP role hierarchy needs administrator review.',
            'legacy_review' => 'Role removal is pending administrator review of this member’s legacy grants.',
            'external_role' => 'An existing Discord role is externally managed and needs manual review.',
            'signature' => 'Discord interaction signature verification failed.',
            'interaction' => 'This Discord command is unavailable.',
            'intent' => 'The checkout link is invalid, expired, or belongs to another Discord account.',
            'rate_limited' => 'Discord is rate limiting requests. The role update will retry.',
            'network' => 'Discord is temporarily unavailable. The role update will retry.',
        );
        return new WP_Error( 'tc_billing_discord_' . $reason, $messages[ $reason ] ?? 'Discord returned an unavailable or invalid response.', array( 'retry_after' => max( 1, min( 86400, $retry_after ) ), 'http' => $http ) );
    }

    private static function snowflake( mixed $id ): bool {
        return is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]{16,19}$/D', $id );
    }

    /** OAuth settings are the authority for guild and protected role IDs. */
    private static function configuration( string $mode, bool $roles ): array|WP_Error {
        if ( ! in_array( $mode, array( 'test', 'live' ), true ) || ! TC_Billing::mode_allowed( $mode ) ) { return self::error( 'disabled', 3600 ); }
        $oauth = get_option( 'timonconfigs_discord', array() );
        $guild = TC_Billing::setting( 'discord_guild_id', $mode );
        if ( ! is_array( $oauth ) || empty( $oauth['enabled'] ) || ! self::snowflake( $guild ) || $guild !== ( $oauth['guild_id'] ?? '' ) || ! self::snowflake( $oauth['client_id'] ?? null ) ) { return self::error( 'configuration', 3600 ); }
        $out = array( 'guild_id' => $guild, 'application_id' => $oauth['client_id'] );
        if ( ! $roles ) { return $out; }
        foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
            $role = TC_Billing::setting( 'discord_' . $plan . '_role_id', $mode );
            if ( ! self::snowflake( $role ) || $role !== ( $oauth[ $plan . '_role' ] ?? '' ) || $role === $guild ) { return self::error( 'configuration', 3600 ); }
            $out[ $plan ] = $role;
        }
        if ( $out['monthly'] === $out['lifetime'] ) { return self::error( 'configuration', 3600 ); }
        foreach ( array( 'free_role', 'staff_role', 'owner_role' ) as $key ) {
            $protected = $oauth[ $key ] ?? '';
            if ( ( 'free_role' === $key || '' !== $protected ) && ! self::snowflake( $protected ) ) { return self::error( 'configuration', 3600 ); }
            if ( in_array( $protected, array( $out['monthly'], $out['lifetime'] ), true ) ) { return self::error( 'configuration', 3600 ); }
        }
        return $out;
    }

    private static function bound_discord( int $uid ): string|WP_Error {
        $id = get_user_meta( $uid, '_tc_discord_id', true );
        if ( $uid <= 0 || ! get_userdata( $uid ) || ! self::snowflake( $id ) || (int) get_option( '_tc_discord_binding_' . $id, 0 ) !== $uid ) { return self::error( 'binding', 3600 ); }
        return $id;
    }

    /** No arbitrary endpoints, redirects, response bodies or credentials escape this method. */
    private static function request( string $mode, string $path, string $method = 'GET' ): array|bool|WP_Error {
        $token = TC_Billing::secret( 'DISCORD_BOT_TOKEN', $mode );
        if ( '' === $token || preg_match( '/[\r\n]/', $token ) || ! in_array( $method, array( 'GET', 'PUT', 'DELETE' ), true ) ) { return self::error( 'configuration', 3600 ); }
        $response = wp_remote_request( self::API . $path, array(
            'method' => $method, 'timeout' => 12, 'redirection' => 0, 'reject_unsafe_urls' => true,
            'sslverify' => true, 'limit_response_size' => 262144,
            'headers' => array( 'Authorization' => 'Bot ' . $token, 'Accept' => 'application/json' ),
        ) );
        if ( is_wp_error( $response ) ) { return self::error( 'network', 60 ); }
        $http = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 429 === $http ) {
            $delays = array();
            $header = wp_remote_retrieve_header( $response, 'retry-after' );
            if ( is_numeric( $header ) && (float) $header > 0 ) { $delays[] = (float) $header; }
            if ( is_array( $data ) && is_numeric( $data['retry_after'] ?? null ) && (float) $data['retry_after'] > 0 ) { $delays[] = (float) $data['retry_after']; }
            return self::error( 'rate_limited', (int) min( 86400, ceil( $delays ? max( $delays ) : 10 ) ), 429 );
        }
        if ( 'GET' !== $method ) { return 204 === $http ? true : self::error( 'http', $http >= 500 ? 120 : 3600, $http ); }
        if ( 200 !== $http ) { return self::error( 'http', $http >= 500 ? 120 : 3600, $http ); }
        return is_array( $data ) ? $data : self::error( 'invalid_response', 300, $http );
    }

    /** Inspect a bit in an arbitrarily large decimal permission string without integer casts. */
    private static function permission( string $decimal, int $bit ): bool {
        for ( $position = 0; $position <= $bit; ++$position ) {
            $quotient = ''; $carry = 0;
            foreach ( str_split( $decimal ) as $digit ) {
                $value = $carry * 10 + (int) $digit;
                $quotient .= (string) intdiv( $value, 2 );
                $carry = $value % 2;
            }
            if ( $position === $bit ) { return 1 === $carry; }
            $decimal = ltrim( $quotient, '0' );
            if ( '' === $decimal ) { return false; }
        }
        return false;
    }

    private static function member_roles( array $member, string $id, array $roles, string $guild ): array|WP_Error {
        if ( ( $member['user']['id'] ?? '' ) !== $id || ! is_array( $member['roles'] ?? null ) || ( isset( $member['guild_id'] ) && $guild !== $member['guild_id'] ) ) { return self::error( 'invalid_response' ); }
        $out = array();
        foreach ( $member['roles'] as $role ) {
            if ( ! self::snowflake( $role ) || ! isset( $roles[ $role ] ) || isset( $out[ $role ] ) ) { return self::error( 'invalid_response' ); }
            $out[ $role ] = true;
        }
        return $out;
    }

    /** Fresh union: one expired/refunded source cannot erase another valid purchase. */
    private static function desired( int $uid, string $mode, string $discord ): array|WP_Error {
        $desired = array( 'monthly' => false, 'lifetime' => false );
        $sources = TC_Billing_Store::active( $uid, $mode );
        if ( is_wp_error( $sources ) ) { return $sources; }
        foreach ( $sources as $source ) {
            if ( ! is_array( $source ) || (int) ( $source['user_id'] ?? 0 ) !== $uid || ( $source['mode'] ?? '' ) !== $mode || ( $source['discord_id'] ?? '' ) !== $discord || ! isset( $desired[ $source['plan'] ?? '' ] ) || 'active' !== ( $source['status'] ?? '' ) ) { return self::error( 'binding', 3600 ); }
            $until = $source['valid_until'] ?? null;
            if ( null === $until && 'lifetime' === $source['plan'] ) { $desired['lifetime'] = true; continue; }
            if ( ! is_string( $until ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $until ) ) { return self::error( 'invalid_response' ); }
            $end = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $until, new DateTimeZone( 'UTC' ) );
            if ( ! $end || $end->format( 'Y-m-d H:i:s' ) !== $until ) { return self::error( 'invalid_response' ); }
            if ( $end->getTimestamp() > time() ) { $desired[ $source['plan'] ] = true; }
        }
        return $desired;
    }

    /** A settings change or revoked binding while canonical reads are in flight stops writes. */
    private static function write_allowed( int $uid, string $mode, string $discord, array $config ): bool|WP_Error {
        $settings = TC_Billing::config();
        if ( empty( $settings['role_sync_enabled'] ) || ( $settings['mode'] ?? '' ) !== $mode ) { return self::error( 'disabled', 3600 ); }
        $current = self::configuration( $mode, true );
        if ( is_wp_error( $current ) ) { return $current; }
        if ( $current !== $config ) { return self::error( 'configuration', 3600 ); }
        return $discord === self::bound_discord( $uid ) ? true : self::error( 'binding', 3600 );
    }

    /** Execute one outbox job. Root records success/retry; all role requests are exact PUT/DELETE. */
    public static function sync( array $job ): bool|WP_Error {
        $uid = (int) ( $job['user_id'] ?? 0 );
        $mode = is_string( $job['mode'] ?? null ) ? $job['mode'] : '';
        $settings = TC_Billing::config();
        if ( empty( $settings['role_sync_enabled'] ) || ( $settings['mode'] ?? '' ) !== $mode ) { return self::error( 'disabled', 3600 ); }
        $config = self::configuration( $mode, true );
        if ( is_wp_error( $config ) ) { return $config; }
        if ( $uid <= 0 ) { return self::error( 'binding', 3600 ); }
        return TC_Billing_Store::locked( 'discord-sync:' . $mode . ':' . $uid, static function () use ( $uid, $mode, $config ): bool|WP_Error {
            $discord = self::bound_discord( $uid );
            if ( is_wp_error( $discord ) ) { return $discord; }
            $guild = $config['guild_id'];
            $bot = self::request( $mode, '/users/@me' );
            if ( is_wp_error( $bot ) ) { return $bot; }
            if ( ! self::snowflake( $bot['id'] ?? null ) || true !== ( $bot['bot'] ?? false ) ) { return self::error( 'invalid_response' ); }
            $guild_data = self::request( $mode, '/guilds/' . $guild );
            if ( is_wp_error( $guild_data ) ) { return $guild_data; }
            if ( ( $guild_data['id'] ?? '' ) !== $guild || ! self::snowflake( $guild_data['owner_id'] ?? null ) ) { return self::error( 'invalid_response' ); }
            if ( $guild_data['owner_id'] === $discord ) { return self::error( 'hierarchy', 3600 ); }
            $list = self::request( $mode, '/guilds/' . $guild . '/roles' );
            if ( is_wp_error( $list ) ) { return $list; }
            $roles = array();
            foreach ( $list as $role ) {
                if ( ! is_array( $role ) || ! self::snowflake( $role['id'] ?? null ) || isset( $roles[ $role['id'] ] ) || ! is_int( $role['position'] ?? null ) || $role['position'] < 0 || ! is_string( $role['permissions'] ?? null ) || ! preg_match( '/^[0-9]{1,32}$/D', $role['permissions'] ) || ! is_bool( $role['managed'] ?? null ) ) { return self::error( 'invalid_response' ); }
                $roles[ $role['id'] ] = $role;
            }
            if ( ! isset( $roles[ $guild ] ) ) { return self::error( 'invalid_response' ); }
            $bot_member = self::request( $mode, '/guilds/' . $guild . '/members/' . $bot['id'] );
            if ( is_wp_error( $bot_member ) ) { return $bot_member; }
            $bot_roles = self::member_roles( $bot_member, $bot['id'], $roles, $guild );
            if ( is_wp_error( $bot_roles ) ) { return $bot_roles; }
            $bot_roles[ $guild ] = true;
            $manage = false; $highest = -1;
            foreach ( array_keys( $bot_roles ) as $role ) {
                $manage = $manage || self::permission( $roles[ $role ]['permissions'], 28 ) || self::permission( $roles[ $role ]['permissions'], 3 );
                $highest = max( $highest, $roles[ $role ]['position'] );
            }
            if ( ! $manage ) { return self::error( 'permissions', 3600 ); }
            foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
                $role = $roles[ $config[ $plan ] ] ?? null;
                if ( ! is_array( $role ) || $role['managed'] || $role['position'] >= $highest ) { return self::error( 'hierarchy', 3600 ); }
            }
            $member = self::request( $mode, '/guilds/' . $guild . '/members/' . $discord );
            if ( is_wp_error( $member ) ) { return $member; }
            $present = self::member_roles( $member, $discord, $roles, $guild );
            if ( is_wp_error( $present ) ) { return $present; }
            if ( $discord !== self::bound_discord( $uid ) ) { return self::error( 'binding', 3600 ); }
            $desired = self::desired( $uid, $mode, $discord );
            if ( is_wp_error( $desired ) ) { return $desired; }
            $owned = array();
            $ownership = TC_Billing_Store::owned_roles( $uid, $mode );
            if ( is_wp_error( $ownership ) ) { return $ownership; }
            foreach ( $ownership as $record ) {
                if ( ( $record['guild_id'] ?? '' ) === $guild && in_array( $record['role_id'] ?? '', array( $config['monthly'], $config['lifetime'] ), true ) ) { $owned[ $record['role_id'] ] = true; }
            }
            // A missing role ends our ownership before any later external grant can inherit it.
            foreach ( array_keys( $owned ) as $role ) {
                if ( ! isset( $present[ $role ] ) ) {
                    $saved = TC_Billing_Store::own_role( $uid, $mode, $guild, $role, false );
                    if ( is_wp_error( $saved ) ) { return $saved; }
                    unset( $owned[ $role ] );
                }
            }
            foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
                $role = $config[ $plan ];
                if ( $desired[ $plan ] && ! isset( $present[ $role ] ) ) {
                    $allowed = self::write_allowed( $uid, $mode, $discord, $config );
                    if ( is_wp_error( $allowed ) ) { return $allowed; }
                    $fresh = self::desired( $uid, $mode, $discord );
                    if ( is_wp_error( $fresh ) ) { return $fresh; }
                    if ( ! $fresh[ $plan ] ) { continue; }
                    $result = self::request( $mode, '/guilds/' . $guild . '/members/' . $discord . '/roles/' . $role, 'PUT' );
                    if ( is_wp_error( $result ) ) { return $result; }
                    $saved = TC_Billing_Store::own_role( $uid, $mode, $guild, $role, true );
                    if ( is_wp_error( $saved ) ) { return $saved; }
                    $present[ $role ] = true; $owned[ $role ] = true;
                }
            }
            $blocked = '';
            foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
                $role = $config[ $plan ];
                if ( ! isset( $present[ $role ] ) ) { continue; }
                // A successful remote PUT followed by a failed marker write is uncertain too.
                // Never silently adopt that role on retry, even while payment access is active.
                if ( ! isset( $owned[ $role ] ) ) { $blocked = $blocked ?: 'external_role'; continue; }
                $fresh = self::desired( $uid, $mode, $discord );
                if ( is_wp_error( $fresh ) ) { return $fresh; }
                // Lifetime protects an existing Monthly role, but does not create an extra badge.
                if ( $fresh[ $plan ] || ( 'monthly' === $plan && $fresh['lifetime'] ) ) { continue; }
                if ( '1' !== get_user_meta( $uid, '_tc_billing_legacy_reviewed_' . $mode, true ) ) { $blocked = 'legacy_review'; continue; }
                $allowed = self::write_allowed( $uid, $mode, $discord, $config );
                if ( is_wp_error( $allowed ) ) { return $allowed; }
                $result = self::request( $mode, '/guilds/' . $guild . '/members/' . $discord . '/roles/' . $role, 'DELETE' );
                if ( is_wp_error( $result ) ) { return $result; }
                $saved = TC_Billing_Store::own_role( $uid, $mode, $guild, $role, false );
                if ( is_wp_error( $saved ) ) { return $saved; }
            }
            return '' === $blocked ? true : self::error( $blocked, 86400 );
        } );
    }

    private static function headers( array $headers ): array|WP_Error {
        $out = array();
        foreach ( $headers as $name => $value ) {
            $name = strtolower( str_replace( '_', '-', (string) $name ) );
            if ( ! in_array( $name, array( 'x-signature-ed25519', 'x-signature-timestamp' ), true ) ) { continue; }
            if ( is_array( $value ) && 1 === count( $value ) ) { $value = reset( $value ); }
            if ( ! is_string( $value ) || isset( $out[ $name ] ) ) { return self::error( 'signature' ); }
            $out[ $name ] = $value;
        }
        return $out;
    }

    private static function response( string $content ): array {
        return array( 'type' => 4, 'data' => array( 'content' => $content, 'flags' => 64, 'allowed_mentions' => array( 'parse' => array() ) ) );
    }

    /** Signature is checked over untouched bytes before JSON parsing or storage changes. */
    public static function interaction( string $raw, array $headers ): array|WP_Error {
        $headers = self::headers( $headers );
        if ( is_wp_error( $headers ) ) { return $headers; }
        $stamp = $headers['x-signature-timestamp'] ?? '';
        $signature = $headers['x-signature-ed25519'] ?? '';
        $key = defined( 'TIMONCONFIGS_BILLING_DISCORD_PUBLIC_KEY' ) ? TIMONCONFIGS_BILLING_DISCORD_PUBLIC_KEY : '';
        if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) || ! is_string( $key ) || ! preg_match( '/^[a-fA-F0-9]{64}$/D', $key ) || ! preg_match( '/^[a-fA-F0-9]{128}$/D', $signature ) || ! preg_match( '/^[0-9]{10,11}$/D', $stamp ) || abs( time() - (int) $stamp ) > 300 || '' === $raw || strlen( $raw ) > 65536 ) { return self::error( 'signature' ); }
        try { $valid = sodium_crypto_sign_verify_detached( hex2bin( $signature ), $stamp . $raw, hex2bin( $key ) ); }
        catch ( Throwable $error ) { $valid = false; }
        if ( ! $valid ) { return self::error( 'signature' ); }
        $data = json_decode( $raw, true, 32 );
        if ( ! is_array( $data ) ) { return self::error( 'interaction' ); }
        if ( 1 === ( $data['type'] ?? null ) ) { return array( 'type' => 1 ); }
        $mode = TC_Billing::config()['mode'] ?? 'test';
        if ( ! is_string( $mode ) ) { return self::error( 'configuration', 3600 ); }
        $config = self::configuration( $mode, false );
        if ( is_wp_error( $config ) ) { return $config; }
        $actor = $data['member']['user']['id'] ?? '';
        if ( 2 !== ( $data['type'] ?? null ) || ( $data['guild_id'] ?? '' ) !== $config['guild_id'] || ( $data['application_id'] ?? '' ) !== $config['application_id'] || ! self::snowflake( $actor ) || ( isset( $data['data']['type'] ) && 1 !== $data['data']['type'] ) ) { return self::error( 'interaction' ); }
        $command = $data['data']['name'] ?? '';
        $options = $data['data']['options'] ?? array();
        if ( ! is_string( $command ) || ! in_array( $command, array( 'buy', 'membership', 'manage', 'link' ), true ) || ! is_array( $options ) ) { return self::error( 'interaction' ); }
        if ( 'buy' !== $command ) {
            if ( $options ) { return self::error( 'interaction' ); }
            $text = array( 'membership' => 'View your membership and verified Discord account: ', 'manage' => 'Manage your membership securely on the website: ', 'link' => 'Sign in with Discord on the website to link your account: ' );
            if ( 'membership' === $command ) {
                $uid = (int) get_option( '_tc_discord_binding_' . $actor, 0 );
                if ( $uid < 1 || $actor !== self::bound_discord( $uid ) ) {
                    return self::response( 'Link this Discord account to view your membership: <' . home_url( '/account/' ) . '>' );
                }
                $desired = self::desired( $uid, $mode, $actor );
                $status = is_wp_error( $desired ) ? 'Billing verification is temporarily unavailable.' : ( $desired['lifetime'] ? 'Verified billing access: VIP Lifetime.' : ( $desired['monthly'] ? 'Verified billing access: VIP Monthly.' : 'No current direct or imported paid access. Existing Discord roles may still provide legacy access.' ) );
                return self::response( ( 'test' === $mode ? 'TEST environment. ' : '' ) . $status . ' Check current Discord access and manage your membership: <' . home_url( '/account/' ) . '>' );
            }
            return self::response( $text[ $command ] . '<' . home_url( '/account/' ) . '>' );
        }
        if ( ! TC_Billing::direct_enabled() ) { return self::error( 'disabled', 3600 ); }
        if ( ! TC_Billing::actor_can_purchase( $actor ) ) { return self::response( 'This Live pilot is limited to its invited Discord account. You can browse memberships at <' . home_url( '/pricing/' ) . '>' ); }
        $plan = 'monthly';
        if ( $options ) {
            $option = 1 === count( $options ) ? reset( $options ) : null;
            if ( ! is_array( $option ) || 'plan' !== ( $option['name'] ?? '' ) || 3 !== ( $option['type'] ?? null ) || ! in_array( $option['value'] ?? '', array( 'monthly', 'lifetime' ), true ) || isset( $option['options'] ) ) { return self::error( 'interaction' ); }
            $plan = $option['value'];
        }
        try { $token = bin2hex( random_bytes( 32 ) ); }
        catch ( Throwable $error ) { return self::error( 'intent' ); }
        $intent = array( 'discord_id' => $actor, 'plan' => $plan, 'mode' => $mode, 'guild_id' => $config['guild_id'], 'expires' => time() + self::INTENT_TTL );
        if ( ! set_transient( 'tc_billing_intent_' . hash( 'sha256', $token ), $intent, self::INTENT_TTL ) ) { return self::error( 'intent' ); }
        $url = add_query_arg( 'tc_billing_intent', $token, home_url( '/checkout/' . $plan . '/' ) );
        return self::response( 'Continue to ' . ( 'monthly' === $plan ? 'VIP Monthly' : 'VIP Lifetime' ) . ' checkout: <' . $url . '>. Sign in with this Discord account, then review the price and payment consent on the website. This link expires in 10 minutes.' );
    }

    /** Root calls after OAuth, with the uniquely bound Discord ID; no email matching. */
    public static function consume_intent( string $token, string $discord ): array|WP_Error {
        if ( ! preg_match( '/^[a-f0-9]{64}$/D', $token ) || ! self::snowflake( $discord ) || ! get_current_user_id() || $discord !== self::bound_discord( get_current_user_id() ) ) { return self::error( 'intent' ); }
        $hash = hash( 'sha256', $token );
        return TC_Billing_Store::locked( 'discord-intent:' . $hash, static function () use ( $hash, $discord ): array|WP_Error {
            $key = 'tc_billing_intent_' . $hash;
            $intent = get_transient( $key );
            if ( ! is_array( $intent ) || ! is_string( $intent['discord_id'] ?? null ) || ! hash_equals( $intent['discord_id'], $discord ) || ! is_int( $intent['expires'] ?? null ) || $intent['expires'] <= time() || ! in_array( $intent['plan'] ?? '', array( 'monthly', 'lifetime' ), true ) || ! is_string( $intent['mode'] ?? null ) || $intent['mode'] !== ( TC_Billing::config()['mode'] ?? '' ) || ! TC_Billing::actor_can_purchase( $discord ) ) { return self::error( 'intent' ); }
            $config = self::configuration( $intent['mode'], false );
            if ( is_wp_error( $config ) || ( $intent['guild_id'] ?? '' ) !== $config['guild_id'] ) { return self::error( 'intent' ); }
            if ( ! delete_transient( $key ) ) { return self::error( 'intent' ); }
            return array( 'discord_id' => $discord, 'plan' => $intent['plan'], 'mode' => $intent['mode'] );
        } );
    }
}
