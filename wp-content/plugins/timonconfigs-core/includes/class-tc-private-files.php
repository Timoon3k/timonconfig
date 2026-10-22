<?php
/**
 * Private script-file uploads for admin-managed Config posts.
 *
 * The files are stored outside the web root. This class does not expose a
 * download URL; member authentication and entitlement checks are required
 * before a download endpoint can be enabled.
 *
 * @package TimonConfigs_Core
 */
defined( 'ABSPATH' ) || exit;

final class TC_Private_Files {
    private const META_KEY = '_tc_private_files';
    private const NONCE = 'tc_private_files_nonce';
    private const EXTENSIONS = array( 'znbk', 'gpc', 'ximmr', 'zip', 'json', 'txt' );
    private const MAX_BYTES = 20971520;
    private const MAX_PER_UPLOAD = 10;

    public static function init(): void {
        add_action( 'add_meta_boxes_tc_config', array( __CLASS__, 'add_box' ) );
        add_action( 'post_edit_form_tag', array( __CLASS__, 'enctype' ) );
        add_action( 'save_post_tc_config', array( __CLASS__, 'save' ), 30, 2 );
    }

    public static function enctype(): void {
        global $post;
        if ( $post instanceof WP_Post && 'tc_config' === $post->post_type && current_user_can( 'manage_options' ) ) {
            echo ' enctype="multipart/form-data"';
        }
    }

    public static function add_box(): void {
        if ( current_user_can( 'manage_options' ) ) {
            add_meta_box( 'tc_private_script_files', 'Private script files', array( __CLASS__, 'render' ), 'tc_config', 'normal', 'default' );
        }
    }

    /** @return string|WP_Error */
    private static function root(): string|WP_Error {
        if ( ! defined( 'TIMONCONFIGS_PRIVATE_STORAGE_DIR' ) || ! is_string( TIMONCONFIGS_PRIVATE_STORAGE_DIR ) ) {
            return new WP_Error( 'tc_private_storage_unconfigured', 'Set TIMONCONFIGS_PRIVATE_STORAGE_DIR in wp-config.php to an existing directory outside the web root.' );
        }
        $root = realpath( TIMONCONFIGS_PRIVATE_STORAGE_DIR );
        if ( false === $root || ! is_dir( $root ) || ! is_writable( $root ) ) {
            return new WP_Error( 'tc_private_storage_missing', 'The configured private directory is missing or not writable.' );
        }
        foreach ( array( ABSPATH, WP_CONTENT_DIR, $_SERVER['DOCUMENT_ROOT'] ?? '' ) as $public ) {
            if ( ! is_string( $public ) || '' === $public ) { continue; }
            $canonical = realpath( $public );
            if ( false !== $canonical && ( $root === $canonical || str_starts_with( $root . DIRECTORY_SEPARATOR, rtrim( $canonical, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) {
                return new WP_Error( 'tc_private_storage_public', 'Private storage cannot be inside the public website or WordPress directory.' );
            }
        }
        return $root;
    }

    public static function render( WP_Post $post ): void {
        wp_nonce_field( 'tc_private_files_' . $post->ID, self::NONCE );
        $root = self::root();
        if ( is_wp_error( $root ) ) {
            echo '<p><strong>Private uploads disabled.</strong> ' . esc_html( $root->get_error_message() ) . '</p>';
        } else {
            echo '<p>Upload script files to private storage. No public download links are created until Discord membership authorization is ready.</p>';
            echo '<p><input type="file" name="tc_private_files[]" multiple accept=".znbk,.gpc,.ximmr,.zip,.json,.txt"></p>';
            echo '<p class="description">Up to 10 files per save, each up to 20 MB. Allowed: ZNBK, GPC, XIMMR, ZIP, JSON, TXT.</p>';
        }
        $files = get_post_meta( $post->ID, self::META_KEY, true );
        if ( is_array( $files ) && $files ) {
            echo '<strong>Stored private files (not yet downloadable):</strong><ul>';
            foreach ( $files as $file ) {
                if ( ! is_array( $file ) ) { continue; }
                echo '<li>' . esc_html( $file['name'] ?? 'unnamed file' ) . ' — ' . esc_html( $file['uploaded_at'] ?? '' ) . '</li>';
            }
            echo '</ul>';
        }
    }

    public static function save( int $post_id, WP_Post $post ): void {
        if ( 'tc_config' !== $post->post_type || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE ] ) || ! is_string( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'tc_private_files_' . $post_id ) ) {
            return;
        }
        if ( ! isset( $_FILES['tc_private_files'] ) || ! is_array( $_FILES['tc_private_files'] ) ) {
            return;
        }
        $uploads = $_FILES['tc_private_files'];
        if ( ! isset( $uploads['name'], $uploads['error'], $uploads['size'], $uploads['tmp_name'] ) || ! is_array( $uploads['name'] ) || count( $uploads['name'] ) > self::MAX_PER_UPLOAD ) {
            return;
        }
        $root = self::root();
        if ( is_wp_error( $root ) ) { return; }
        $destination = $root . DIRECTORY_SEPARATOR . $post_id;
        if ( ! is_dir( $destination ) && ! wp_mkdir_p( $destination ) ) { return; }
        @chmod( $destination, 0700 );
        $canonical_destination = realpath( $destination );
        if ( false === $canonical_destination || ! str_starts_with( $canonical_destination . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR ) ) { return; }
        $records = get_post_meta( $post_id, self::META_KEY, true );
        $records = is_array( $records ) ? $records : array();
        foreach ( $uploads['name'] as $i => $raw_name ) {
            if ( ! is_string( $raw_name ) || ! isset( $uploads['error'][ $i ], $uploads['size'][ $i ], $uploads['tmp_name'][ $i ] ) ) { continue; }
            if ( UPLOAD_ERR_OK !== (int) $uploads['error'][ $i ] || (int) $uploads['size'][ $i ] <= 0 || (int) $uploads['size'][ $i ] > self::MAX_BYTES ) { continue; }
            $name = sanitize_file_name( $raw_name );
            $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            if ( '' === $name || ! in_array( $ext, self::EXTENSIONS, true ) ) { continue; }
            $temporary = (string) $uploads['tmp_name'][ $i ];
            if ( ! is_uploaded_file( $temporary ) ) { continue; }
            $file_id = wp_generate_uuid4();
            $stored = $canonical_destination . DIRECTORY_SEPARATOR . $file_id . '.' . $ext;
            if ( ! move_uploaded_file( $temporary, $stored ) ) { continue; }
            @chmod( $stored, 0600 );
            $records[] = array(
                'id' => $file_id,
                'name' => $name,
                'stored_name' => basename( $stored ),
                'size' => filesize( $stored ),
                'uploaded_at' => current_time( 'mysql', true ),
            );
        }
        update_post_meta( $post_id, self::META_KEY, $records );
    }
}
