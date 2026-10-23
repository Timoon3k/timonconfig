<?php
/** Protected, separately entitled per-config guides and values. @package TimonConfigs_Core */
defined( 'ABSPATH' ) || exit;

final class TC_Config_Content {
    private const KINDS = array( 'guide', 'values' );
    private const LEVELS = array( 'public', 'free', 'monthly', 'lifetime' );
    private const LIMIT = 100000;

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
        add_action( 'add_meta_boxes_tc_config', static function (): void { add_meta_box( 'tc-config-content', 'Guides and values', array( __CLASS__, 'meta_box' ), 'tc_config', 'normal', 'default' ); } );
        add_action( 'save_post_tc_config', array( __CLASS__, 'save_admin' ), 30 );
        add_action( 'admin_notices', static function (): void {
            if ( ! current_user_can( 'edit_posts' ) ) { return; }
            $key = 'tc_content_editor_error_' . get_current_user_id(); $error = get_transient( $key );
            if ( is_string( $error ) && '' !== $error ) { delete_transient( $key ); echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
        } );
        foreach ( array( 'admin_post_', 'admin_post_nopriv_' ) as $prefix ) {
            add_action( $prefix . 'tc_content_nonce', array( __CLASS__, 'nonce_request' ) );
            add_action( $prefix . 'tc_config_content', array( __CLASS__, 'content_request' ) );
        }
        add_action( 'wp_footer', array( __CLASS__, 'dialog' ) );
    }

    public static function register_meta(): void {
        foreach ( self::KINDS as $kind ) {
            foreach ( array( 'content', 'visibility' ) as $field ) {
                register_post_meta( 'tc_config', '_tc_' . $kind . '_' . $field, array(
                    'type' => 'string', 'single' => true, 'show_in_rest' => false, 'revisions_enabled' => true,
                    'auth_callback' => static fn( bool $allowed, string $key, int $id ): bool => current_user_can( 'edit_post', $id ),
                    'sanitize_callback' => 'visibility' === $field ? static fn( $v ): string => is_string( $v ) && in_array( $v, self::LEVELS, true ) ? $v : 'monthly' : ( 'guide' === $kind ? array( __CLASS__, 'sanitize_guide' ) : array( __CLASS__, 'sanitize_values' ) ),
                ) );
            }
        }
    }

    public static function sanitize_guide( mixed $value ): string {
        if ( ! is_string( $value ) ) { return ''; }
        // No embeds, scripts, inline handlers, forms or executable SVG. Public screenshots only.
        return wp_kses( $value, array(
            'p' => array(), 'br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(),
            'h2' => array(), 'h3' => array(), 'h4' => array(), 'ul' => array(), 'ol' => array( 'start' => true ), 'li' => array(),
            'pre' => array(), 'code' => array(), 'blockquote' => array(), 'hr' => array(),
            'table' => array(), 'caption' => array(), 'thead' => array(), 'tbody' => array(), 'tr' => array(),
            'th' => array( 'scope' => true, 'colspan' => true, 'rowspan' => true ), 'td' => array( 'colspan' => true, 'rowspan' => true ),
            'a' => array( 'href' => true, 'title' => true ),
            'img' => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true ),
        ), array( 'http', 'https' ) );
    }

    public static function sanitize_values( mixed $value ): string {
        // Plain configuration text: preserve line breaks, spacing, angle brackets and command syntax.
        return is_string( $value ) ? str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", '' ), $value ) : '';
    }

    public static function editor_fields( int $id ): string {
        ob_start();
        echo '<div class="tc-content-editor"><p>Content stays out of public REST metadata. Set access for each item independently. Guide supports safe HTML lists, tables, code and public screenshot URLs; Values is plain text. Never paste paid file URLs here.</p>';
        foreach ( self::KINDS as $kind ) {
            $content = (string) get_post_meta( $id, '_tc_' . $kind . '_content', true );
            $visibility = (string) get_post_meta( $id, '_tc_' . $kind . '_visibility', true );
            if ( ! in_array( $visibility, self::LEVELS, true ) ) { $visibility = 'monthly'; }
            echo '<p><label>' . esc_html( ucfirst( $kind ) ) . ' access <select name="tc_' . esc_attr( $kind ) . '_visibility">';
            foreach ( array( 'public' => 'Public teaser', 'free' => 'Free Member (including VIP)', 'monthly' => 'VIP Monthly or Lifetime', 'lifetime' => 'VIP Lifetime only' ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $visibility, $key, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select></label></p><p><label>' . esc_html( ucfirst( $kind ) ) . ' content<br><textarea class="widefat" name="tc_' . esc_attr( $kind ) . '_content" rows="9" maxlength="100000">' . esc_textarea( $content ) . '</textarea></label></p>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function meta_box( WP_Post $post ): void {
        wp_nonce_field( 'tc_config_content_save_' . $post->ID, 'tc_config_content_nonce' );
        echo self::editor_fields( $post->ID ); // Escaped field markup.
    }

    /** Resolve and validate all touched items before either a post or its metadata changes. */
    private static function prepared_fields( array $input, int $id ): array|WP_Error {
        if ( $id && 'tc_config' !== get_post_type( $id ) ) { return new WP_Error( 'tc_content_invalid', 'Choose a valid configuration.' ); }
        $changes = array();
        foreach ( self::KINDS as $kind ) {
            $content_key = 'tc_' . $kind . '_content'; $visibility_key = 'tc_' . $kind . '_visibility';
            if ( ! array_key_exists( $content_key, $input ) && ! array_key_exists( $visibility_key, $input ) ) { continue; }
            $content = array_key_exists( $content_key, $input ) ? $input[ $content_key ] : ( $id && metadata_exists( 'post', $id, '_' . $content_key ) ? get_post_meta( $id, '_' . $content_key, true ) : '' );
            // A missing policy receives the conservative default. A stored corrupt policy is rejected.
            $visibility = array_key_exists( $visibility_key, $input ) ? $input[ $visibility_key ] : ( $id && metadata_exists( 'post', $id, '_' . $visibility_key ) ? get_post_meta( $id, '_' . $visibility_key, true ) : 'monthly' );
            if ( ! is_string( $content ) || strlen( $content ) > self::LIMIT || ! is_string( $visibility ) || ! in_array( $visibility, self::LEVELS, true ) ) { return new WP_Error( 'tc_content_invalid', 'Guide and values require valid text and access levels (maximum 100 KB each).' ); }
            $changes[ '_' . $content_key ] = 'guide' === $kind ? self::sanitize_guide( $content ) : self::sanitize_values( $content );
            $changes[ '_' . $visibility_key ] = $visibility;
        }
        return $changes;
    }

    /** Pure validation for callers that must reject an entire edit before publishing. */
    public static function validate_fields( array $input, int $id = 0 ): bool|WP_Error {
        $changes = self::prepared_fields( $input, $id );
        return is_wp_error( $changes ) ? $changes : true;
    }

    /** Caller must authorize the config write and CSRF before using this internal persistence API. */
    public static function save_fields( int $id, array $input ): bool|WP_Error {
        if ( 'tc_config' !== get_post_type( $id ) ) { return new WP_Error( 'tc_content_invalid', 'Choose a valid configuration.' ); }
        $changes = self::prepared_fields( $input, $id );
        if ( is_wp_error( $changes ) ) { return $changes; }
        foreach ( $changes as $key => $value ) { update_post_meta( $id, $key, wp_slash( $value ) ); }
        if ( $changes && wp_revisions_enabled( get_post( $id ) ) ) { wp_save_post_revision( $id ); }
        return true;
    }

    public static function save_admin( int $id ): void {
        if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'edit_post', $id ) ) { return; }
        $nonce = $_POST['tc_config_content_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'tc_config_content_save_' . $id ) ) { return; }
        $result = self::save_fields( $id, wp_unslash( $_POST ) );
        if ( is_wp_error( $result ) ) { set_transient( 'tc_content_editor_error_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS ); }
    }

    private static function exists( int $id, string $kind ): bool { return '' !== trim( (string) get_post_meta( $id, '_tc_' . $kind . '_content', true ) ); }

    public static function buttons( int $id ): string {
        if ( 'tc_config' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || '' !== (string) get_post_field( 'post_password', $id ) ) { return ''; }
        $html = '';
        foreach ( self::KINDS as $kind ) {
            if ( self::exists( $id, $kind ) ) { $html .= '<button type="button" class="tc-content-trigger" data-tc-content="' . esc_attr( $kind ) . '" data-config-id="' . esc_attr( (string) $id ) . '" aria-haspopup="dialog">' . esc_html( ucfirst( $kind ) ) . '</button>'; }
        }
        return $html ? '<div class="tc-content-actions">' . $html . '</div>' : '';
    }

    /** Authorization is independent of the file tier: each item has its own configured visibility. */
    public static function read( int $id, string $kind ): array|WP_Error {
        if ( ! in_array( $kind, self::KINDS, true ) || 'tc_config' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || '' !== (string) get_post_field( 'post_password', $id ) || ! in_array( get_post_meta( $id, 'tc_release_status', true ), array( 'available', 'coming-soon' ), true ) || ! self::exists( $id, $kind ) ) { return new WP_Error( 'tc_content_missing', 'This content is not currently available.', array( 'status' => 404 ) ); }
        $level = get_post_meta( $id, '_tc_' . $kind . '_visibility', true );
        if ( ! in_array( $level, self::LEVELS, true ) ) { return new WP_Error( 'tc_content_denied', 'This content requires verified access.', array( 'status' => 403 ) ); }
        if ( 'public' !== $level ) {
            if ( ! get_current_user_id() ) { return new WP_Error( 'tc_content_login', 'Sign in with Discord to view this content.', array( 'status' => 401 ) ); }
            $status = TC_Discord::status();
            $tiers = array( 'free' => array( 'free', 'monthly', 'lifetime' ), 'monthly' => array( 'monthly', 'lifetime' ), 'lifetime' => array( 'lifetime' ) );
            if ( ! in_array( $status['state'] ?? '', $tiers[ $level ], true ) ) { return new WP_Error( 'tc_content_denied', 'Your verified membership does not currently include this content.', array( 'status' => 403 ) ); }
        }
        $content = (string) get_post_meta( $id, '_tc_' . $kind . '_content', true );
        $html = 'guide' === $kind ? wpautop( self::sanitize_guide( $content ) ) : '<pre><code>' . esc_html( self::sanitize_values( $content ) ) . '</code></pre>';
        return array( 'title' => get_the_title( $id ) . ' — ' . ucfirst( $kind ), 'html' => $html, 'text' => 'values' === $kind ? self::sanitize_values( $content ) : html_entity_decode( trim( wp_strip_all_tags( preg_replace( '/<\/(p|li|tr|h[234]|pre)>|<br\s*\/?\s*>/i', "\n", self::sanitize_guide( $content ) ) ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    }

    private static function headers(): void {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        nocache_headers();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        header( 'Referrer-Policy: no-referrer', true );
    }

    public static function nonce_request(): void {
        self::headers();
        if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_send_json_error( array( 'message' => 'Use the content controls on a configuration page.' ), 405 ); }
        wp_send_json_success( array( 'nonce' => wp_create_nonce( 'tc_config_content_read' ) ) );
    }

    public static function content_request(): void {
        self::headers();
        $nonce = $_POST['_wpnonce'] ?? '';
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_string( $nonce ) || ! wp_verify_nonce( wp_unslash( $nonce ), 'tc_config_content_read' ) ) { wp_send_json_error( array( 'message' => 'Reload the page and try again.' ), 403 ); }
        $id = is_string( $_POST['config_id'] ?? null ) && ctype_digit( $_POST['config_id'] ) ? (int) $_POST['config_id'] : 0;
        $kind = is_string( $_POST['kind'] ?? null ) ? $_POST['kind'] : '';
        $result = self::read( $id, $kind );
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) ( $result->get_error_data()['status'] ?? 403 ) ); }
        wp_send_json_success( $result );
    }

    public static function dialog(): void {
        if ( is_admin() || is_feed() ) { return; }
        echo '<dialog class="tc-content-dialog" id="tc-config-content-dialog" aria-labelledby="tc-content-dialog-title" data-endpoint="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><div class="tc-content-dialog__shell"><header><h2 id="tc-content-dialog-title">Configuration content</h2><button type="button" class="tc-content-close" aria-label="Close content dialog">×</button></header><p class="tc-content-status" role="status" aria-live="polite"></p><div class="tc-content-body" tabindex="0"></div><footer><button type="button" class="tc-button tc-content-copy" disabled>Copy content</button><a class="tc-text-link" href="' . esc_url( home_url( '/account/' ) ) . '">Your account</a><a class="tc-text-link" href="' . esc_url( home_url( '/pricing/' ) ) . '">Browse Memberships</a></footer></div></dialog>';
    }
}
