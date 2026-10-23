<?php
/** Frontend config management. Owner policy never grants generic WordPress administration. */
defined( 'ABSPATH' ) || exit;
final class TC_Owner_Editor {
    private static bool $authorized = false;
    private const ACTION = 'tc_owner_config';
    private const CAPS = array( 'edit_tc_configs', 'edit_others_tc_configs', 'edit_private_tc_configs', 'edit_published_tc_configs', 'publish_tc_configs', 'read_private_tc_configs', 'delete_tc_configs', 'delete_private_tc_configs', 'delete_published_tc_configs', 'delete_others_tc_configs' );
    private const FIELDS = array( 'tc_tier' => 'sanitize_tier', 'tc_input_method' => 'sanitize_input', 'tc_release_status' => 'sanitize_release_status', 'tc_config_version' => 'sanitize_text', 'tc_game_version' => 'sanitize_text', 'tc_creator' => 'sanitize_text', 'tc_publisher' => 'sanitize_text', 'tc_release_date' => 'sanitize_date', 'tc_compatibility' => 'sanitize_multiline', 'tc_changelog' => 'sanitize_multiline', 'tc_tutorial_url' => 'sanitize_public_url' );
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'administrator_caps' ), 5 );
        add_filter( 'user_has_cap', array( __CLASS__, 'scoped_caps' ), 20, 4 );
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
        add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
        add_action( 'template_redirect', static function (): void { if ( isset( $_GET['tc_manage_configs'] ) ) { self::headers(); } }, -1 );
        add_shortcode( 'timonconfigs_manage_configs', array( __CLASS__, 'render' ) );
    }
    public static function administrator_caps(): void {
        $role = get_role( 'administrator' );
        if ( $role ) { foreach ( self::CAPS as $cap ) { if ( ! $role->has_cap( $cap ) ) { $role->add_cap( $cap ); } } }
    }
    public static function scoped_caps( array $caps, array $required, array $args, WP_User $user ): array {
        if ( self::$authorized && $user->ID === get_current_user_id() ) { foreach ( self::CAPS as $cap ) { $caps[ $cap ] = true; } }
        return $caps;
    }
    public static function in_authorized_context(): bool { return self::$authorized; }
    public static function url( int $id = 0 ): string { return add_query_arg( array( 'tc_manage_configs' => 1, 'config_id' => $id ), home_url( '/account/' ) ); }
    private static function headers(): void {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        nocache_headers();
        if ( ! headers_sent() ) { header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true ); header( 'X-Robots-Tag: noindex, nofollow', true ); }
    }
    private static function forbidden( string $message = 'Verified Owner access is required.' ): WP_Error { return new WP_Error( 'tc_owner_forbidden', $message ); }
    private static function scalar( array $data, string $key ): string { return is_string( $data[ $key ] ?? null ) ? $data[ $key ] : ''; }
    /** Single policy check per mutation; scoped capability grant is removed even on failure. */
    public static function perform( array $data, array $files = array() ): int|WP_Error {
        if ( ! is_user_logged_in() || ! wp_verify_nonce( self::scalar( $data, '_wpnonce' ), self::ACTION ) ) { return self::forbidden( 'Your management session expired. Reload and try again.' ); }
        $policy = TC_Discord::can_manage_configs();
        if ( true !== $policy ) { return is_wp_error( $policy ) ? $policy : self::forbidden(); }
        $operation = self::scalar( $data, 'operation' );
        if ( ! in_array( $operation, array( 'save', 'trash', 'upload', 'replace', 'delete', 'update', 'image' ), true ) ) { return self::forbidden( 'Unknown configuration action.' ); }
        $raw_id = self::scalar( $data, 'config_id' );
        if ( ! preg_match( '/^[0-9]+$/D', $raw_id ) ) { return self::forbidden( 'Invalid configuration ID.' ); }
        $id = (int) $raw_id;
        if ( $id && ( 'tc_config' !== get_post_type( $id ) || in_array( get_post_status( $id ), array( 'trash', 'auto-draft' ), true ) ) ) { return self::forbidden( 'This configuration cannot be managed.' ); }
        if ( ! $id && 'save' !== $operation ) { return self::forbidden( 'Save a draft before attaching files.' ); }
        self::$authorized = true;
        try {
            if ( $id && ! current_user_can( 'edit_post', $id ) ) { return self::forbidden(); }
            if ( 'save' === $operation ) {
                // New config wizard: optional first batch of protected files in the same form.
                // Store the record as a draft until validation and private upload succeed.
                $incoming = $files['tc_private_files'] ?? null;
                $names = is_array( $incoming ) && is_array( $incoming['name'] ?? null ) ? $incoming['name'] : array();
                $errors = is_array( $incoming ) && is_array( $incoming['error'] ?? null ) ? $incoming['error'] : array();
                $has_files = count( array_filter( $names, static fn( mixed $name ): bool => is_string( $name ) && '' !== trim( $name ) ) ) > 0
                    || count( array_filter( $errors, static fn( mixed $err ): bool => UPLOAD_ERR_NO_FILE !== (int) $err ) ) > 0;
                if ( ! $id && $has_files ) {
                    $root = TC_Private_Files::root();
                    if ( is_wp_error( $root ) ) { return self::forbidden( $root->get_error_message() ); }
                    $requested_status = self::scalar( $data, 'status' );
                    if ( ! in_array( $requested_status, array( 'draft', 'publish' ), true ) ) {
                        return self::forbidden( 'Choose Draft or Published.' );
                    }
                    if ( 'publish' === $requested_status && ( empty( array_filter( (array) ( $data['tc_game'] ?? array() ) ) ) || empty( array_filter( (array) ( $data['tc_device'] ?? array() ) ) ) ) ) {
                        return self::forbidden( 'Choose a Game and Device before publishing your new configuration.' );
                    }
                    $draft_data = $data;
                    $draft_data['status'] = 'draft';
                    $created = self::save( 0, $draft_data );
                    if ( is_wp_error( $created ) ) { return $created; }
                    $stored = TC_Private_Files::manage( $created, 'upload', array(
                        'version' => self::scalar( $data, 'file_version' ),
                        'notes' => self::scalar( $data, 'file_notes' ),
                    ), $incoming );
                    if ( is_wp_error( $stored ) ) {
                        return self::forbidden( 'Config #' . $created . ' remains a draft because the file upload failed: ' . $stored->get_error_message() . ' Open it from Existing configurations to retry.' );
                    }
                    if ( 'publish' === $requested_status ) {
                        $published = wp_update_post( array( 'ID' => $created, 'post_status' => 'publish' ), true );
                        if ( is_wp_error( $published ) ) {
                            return self::forbidden( 'Config #' . $created . ' and its files were saved as draft, but publication failed. Open the draft to retry.' );
                        }
                    }
                    return $created;
                }
                return self::save( $id, $data );
            }
            if ( 'trash' === $operation ) {
                if ( '1' !== self::scalar( $data, 'confirm' ) || ! current_user_can( 'delete_post', $id ) ) { return self::forbidden( 'Confirm moving this config to Trash.' ); }
                if ( ! wp_trash_post( $id ) ) { return self::forbidden( 'The config could not be moved to Trash.' ); }
                self::audit( $id, 'trashed' ); return 0;
            }
            if ( 'image' === $operation ) { $result = self::image( $id, $files['preview_image'] ?? null ); }
            else { $result = TC_Private_Files::manage( $id, $operation, $data, $files['tc_private_files'] ?? null ); }
            if ( is_wp_error( $result ) ) { return $result; }
            self::audit( $id, $operation ); return $id;
        } finally { self::$authorized = false; }
    }
    private static function save( int $id, array $data ): int|WP_Error {
        $title = sanitize_text_field( self::scalar( $data, 'title' ) );
        $status = self::scalar( $data, 'status' );
        if ( '' === $title || strlen( $title ) > 240 || ! in_array( $status, array( 'draft', 'publish' ), true ) ) { return self::forbidden( 'Enter a title and select Draft or Published.' ); }
        $meta = array();
        foreach ( self::FIELDS as $key => $method ) {
            $raw = self::scalar( $data, $key ); $value = TC_Catalog::$method( $raw );
            if ( in_array( $key, array( 'tc_tier', 'tc_release_status' ), true ) && '' === $value ) { return self::forbidden( 'Select a valid access tier and release status.' ); }
            if ( in_array( $key, array( 'tc_input_method', 'tc_release_date' ), true ) && '' !== $raw && '' === $value ) { return self::forbidden( 'Check the input method and release date.' ); }
            $meta[ $key ] = $value;
        }
        $taxes = array();
        foreach ( array( 'tc_game', 'tc_device', 'tc_platform' ) as $tax ) {
            $values = $data[ $tax ] ?? array();
            if ( ! is_array( $values ) || count( $values ) > 30 ) { return self::forbidden( 'Invalid collection selection.' ); }
            $taxes[ $tax ] = array();
            foreach ( $values as $value ) {
                if ( '' === $value && in_array( $tax, array( 'tc_game', 'tc_device' ), true ) ) { continue; }
                if ( ! is_string( $value ) || ! ctype_digit( $value ) || ! term_exists( (int) $value, $tax ) ) { return self::forbidden( 'Select existing games, devices and platforms.' ); }
                $taxes[ $tax ][] = (int) $value;
            }
        }
        // A public release must belong to a browsable game and device collection.
        // Drafts may be incomplete while the Owner prepares artwork and private files.
        if ( 'publish' === $status && ( empty( $taxes['tc_game'] ) || empty( $taxes['tc_device'] ) ) ) {
            return self::forbidden( 'Choose one Game and one Device before publishing. Otherwise the card cannot appear in the correct collection.' );
        }
        // Validate protected editorial fields before changing a post.
        if ( class_exists( 'TC_Config_Content' ) ) {
            $valid = TC_Config_Content::validate_fields( $data, $id ); if ( is_wp_error( $valid ) ) { return $valid; }
        }
        if ( ! current_user_can( $id ? 'edit_post' : 'edit_tc_configs', $id ) || ( 'publish' === $status && ! current_user_can( 'publish_tc_configs' ) ) ) { return self::forbidden(); }
        $post = array( 'post_type' => 'tc_config', 'post_status' => $status, 'post_title' => $title, 'post_content' => wp_kses_post( self::scalar( $data, 'description' ) ), 'post_excerpt' => sanitize_textarea_field( self::scalar( $data, 'excerpt' ) ) );
        if ( $id ) { $post['ID'] = $id; }
        $result = wp_insert_post( wp_slash( $post ), true );
        if ( is_wp_error( $result ) ) { return self::forbidden( 'The config could not be saved.' ); }
        foreach ( $meta as $key => $value ) { update_post_meta( $result, $key, $value ); }
        // All configs in a game inherit one branded cover unless Owner explicitly selects custom.
        update_post_meta( $result, '_tc_card_art', 'custom' === self::scalar( $data, 'card_art' ) ? 'custom' : 'game' );
        foreach ( $taxes as $tax => $ids ) { wp_set_object_terms( $result, $ids, $tax ); }
        if ( class_exists( 'TC_Config_Content' ) ) { $saved = TC_Config_Content::save_fields( $result, $data ); if ( is_wp_error( $saved ) ) { return $saved; } }
        self::audit( $result, $id ? 'edited' : 'created' ); return $result;
    }
    private static function image( int $id, mixed $file ): bool|WP_Error {
        if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? -1 ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) || ! is_string( $file['name'] ?? null ) ) { return self::forbidden( 'Choose an image received through the upload form.' ); }
        $path = $file['tmp_name']; $size = filesize( $path );
        $info = wp_getimagesize( $path );
        if ( ! $size || $size > 10485760 || ! $info || $info[0] * $info[1] > 40000000 || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) ) { return self::forbidden( 'Use a genuine JPG, PNG, WebP or AVIF under 10 MB / 40 megapixels.' ); }
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) { return self::forbidden( 'The host cannot process this image format.' ); }
        if ( $info[0] > 1920 || $info[1] > 1920 ) {
            $resized = $editor->resize( 1920, 1920, false );
            if ( is_wp_error( $resized ) ) { return self::forbidden( 'The preview could not be resized.' ); }
        }
        $temp = wp_tempnam( 'tc-public-preview.jpg' );
        if ( ! is_string( $temp ) || '' === $temp ) { return self::forbidden( 'Temporary image storage is unavailable.' ); }
        $encoded = $editor->save( $temp, 'image/jpeg' );
        if ( is_wp_error( $encoded ) ) { @unlink( $temp ); return self::forbidden( 'The preview could not be processed.' ); }
        $sideload = array( 'name' => 'config-preview-' . $id . '.jpg', 'tmp_name' => $encoded['path'] );
        $attachment = media_handle_sideload( $sideload, $id );
        if ( is_file( $encoded['path'] ) ) { unlink( $encoded['path'] ); }
        if ( $temp !== $encoded['path'] && is_file( $temp ) ) { unlink( $temp ); }
        if ( is_wp_error( $attachment ) ) { return self::forbidden( 'The public preview could not be stored.' ); }
        if ( ! set_post_thumbnail( $id, $attachment ) ) { wp_delete_attachment( $attachment, true ); return self::forbidden( 'The preview could not be attached.' ); }
        return true;
    }
    private static function audit( int $id, string $action ): void { add_post_meta( $id, '_tc_owner_audit', array( 'at' => gmdate( 'c' ), 'actor' => get_current_user_id(), 'action' => $action ) ); }
    public static function handle(): void {
        self::headers();
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'POST required.', '', array( 'response' => 405 ) ); }
        $result = self::perform( wp_unslash( $_POST ), $_FILES );
        $uid = get_current_user_id();
        if ( is_wp_error( $result ) ) {
            if ( ! $uid ) { wp_die( 'Please sign in.', '', array( 'response' => 403 ) ); }
            set_transient( 'tc_owner_feedback_' . $uid, $result->get_error_message(), 120 );
            wp_safe_redirect( self::url( absint( self::scalar( $_POST, 'config_id' ) ) ), 303 ); exit;
        }
        set_transient( 'tc_owner_feedback_' . $uid, 'Configuration saved.', 120 );
        wp_safe_redirect( self::url( $result ), 303 ); exit;
    }
    private static function form_start( int $id, string $operation ): void {
        echo '<form class="tc-owner-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '"><input type="hidden" name="config_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( self::ACTION );
    }
    private static function input( string $key, string $label, string $value, bool $area = false ): void {
        echo '<label>' . esc_html( $label );
        echo $area ? '<textarea name="' . esc_attr( $key ) . '" rows="5">' . esc_textarea( $value ) . '</textarea>' : '<input name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
        echo '</label>';
    }
    private static function select( string $key, string $label, string $current, array $choices ): void {
        echo '<label>' . esc_html( $label ) . '<select name="' . esc_attr( $key ) . '">';
        foreach ( $choices as $key_value => $text ) { echo '<option value="' . esc_attr( $key_value ) . '" ' . selected( $current, $key_value, false ) . '>' . esc_html( $text ) . '</option>'; }
        echo '</select></label>';
    }
    public static function render(): string {
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return '<p>Config management is private.</p>'; }
        self::headers();
        $allowed = is_user_logged_in() ? TC_Discord::can_manage_configs( 0, false ) : false;
        if ( true !== $allowed ) { return '<div class="tc-owner-editor"><h2>Manage Configs</h2><p>Verified, administrator-approved Owner access is required. Staff and membership roles do not grant editing access.</p><a href="' . esc_url( home_url( '/account/' ) ) . '">Back to profile</a></div>'; }
        $raw = $_GET['config_id'] ?? '0'; $id = is_string( $raw ) && ctype_digit( $raw ) ? (int) $raw : 0;
        if ( $id && ( 'tc_config' !== get_post_type( $id ) || ! in_array( get_post_status( $id ), array( 'draft', 'publish', 'pending', 'private' ), true ) ) ) { return '<p>This configuration cannot be managed.</p>'; }
        $post = $id ? get_post( $id ) : null;
        ob_start();
        echo '<section class="tc-owner-editor"><h2>Manage Configs</h2><p>Owner tools · public preview images are separate from protected script files. Role verification is refreshed for every write; allow at least 10 seconds between actions.</p><p><a href="' . esc_url( home_url( '/account/' ) ) . '">Back to profile</a> · <a href="' . esc_url( self::url() ) . '">New configuration</a></p>';
        $feedback = get_transient( 'tc_owner_feedback_' . get_current_user_id() ); if ( is_string( $feedback ) && $feedback ) { echo '<p role="status">' . esc_html( $feedback ) . '</p>'; delete_transient( 'tc_owner_feedback_' . get_current_user_id() ); }
        $page_raw = $_GET['manage_page'] ?? '1'; $page = is_string( $page_raw ) && ctype_digit( $page_raw ) ? max( 1, min( 100000, (int) $page_raw ) ) : 1;
        $configs = new WP_Query( array( 'post_type' => 'tc_config', 'post_status' => array( 'draft', 'publish', 'private', 'pending' ), 'posts_per_page' => 20, 'paged' => $page, 'orderby' => array( 'modified' => 'DESC', 'ID' => 'DESC' ) ) );
        echo '<details open><summary>Existing configurations — click to edit</summary><ul>';
        foreach ( $configs->posts as $config ) {
            $release = (string) get_post_meta( $config->ID, 'tc_release_status', true );
            $games = wp_get_object_terms( $config->ID, 'tc_game', array( 'fields' => 'names' ) );
            $devices = wp_get_object_terms( $config->ID, 'tc_device', array( 'fields' => 'names' ) );
            $labels = ! is_wp_error( $games ) ? implode( ', ', $games ) : '';
            $device_labels = ! is_wp_error( $devices ) ? implode( ', ', $devices ) : '';
            echo '<li><a href="' . esc_url( self::url( $config->ID ) ) . '">' . esc_html( $config->post_title ) . '</a> — ' . esc_html( $config->post_status ) . ' / ' . esc_html( $release ?: 'legacy release' ) . ' · ' . esc_html( $labels ?: 'No game selected' ) . ' · ' . esc_html( $device_labels ?: 'No device selected' );
            if ( 'publish' === $config->post_status && 'archived' !== $release ) {
                echo ' · <a href="' . esc_url( get_permalink( $config->ID ) ) . '">View card</a>';
            }
            echo '</li>';
        }
        echo '</ul><nav aria-label="Managed configurations">';
        if ( $page > 1 ) { echo '<a href="' . esc_url( add_query_arg( 'manage_page', $page - 1, self::url() ) ) . '">Previous configurations</a> '; }
        if ( $page < $configs->max_num_pages ) { echo '<a href="' . esc_url( add_query_arg( 'manage_page', $page + 1, self::url() ) ) . '">More configurations</a>'; }
        echo '</nav></details>';
        self::form_start( $id, 'save' );
        self::input( 'title', 'Title', $post->post_title ?? '' ); self::input( 'excerpt', 'Short description', $post->post_excerpt ?? '', true ); self::input( 'description', 'Description (safe HTML)', $post->post_content ?? '', true );
        self::select( 'status', 'Publication', $post->post_status ?? 'draft', array( 'draft' => 'Draft', 'publish' => 'Published' ) );
        foreach ( array( 'tc_game' => 'Game', 'tc_device' => 'Device', 'tc_platform' => 'Platforms' ) as $tax => $label ) {
            $assigned = $id ? wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) ) : array();
            echo '<fieldset><legend>' . esc_html( $label ) . '</legend>';
            if ( in_array( $tax, array( 'tc_game', 'tc_device' ), true ) ) {
                echo '<select name="' . esc_attr( $tax ) . '[]" ' . ( 'publish' === ( $post->post_status ?? '' ) ? 'required' : '' ) . '><option value="">Choose ' . esc_html( $label ) . '…</option>';
                foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $term ) {
                    echo '<option value="' . esc_attr( (string) $term->term_id ) . '" ' . selected( in_array( $term->term_id, $assigned, true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
                }
                echo '</select>';
            } else {
                foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $term ) {
                    echo '<label class="tc-owner-check"><input type="checkbox" name="' . esc_attr( $tax ) . '[]" value="' . esc_attr( (string) $term->term_id ) . '" ' . checked( in_array( $term->term_id, $assigned, true ), true, false ) . '>' . esc_html( $term->name ) . '</label>';
                }
            }
            echo '</fieldset>';
        }
        self::select( 'tc_tier', 'Access tier', (string) get_post_meta( $id, 'tc_tier', true ), array( 'free' => 'Free Member', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime' ) );
        echo '<p class="tc-owner-help">Published + Coming soon = visible preview card with no download. Published + Available + uploaded private file = downloadable card for entitled members. Archived = hidden from the public catalogue.</p>';
        self::select( 'card_art', 'Card cover artwork', (string) ( get_post_meta( $id, '_tc_card_art', true ) ?: 'game' ), array( 'game' => 'Use shared game cover (recommended)', 'custom' => 'Use uploaded custom thumbnail' ) );
        self::select( 'tc_release_status', 'Release', (string) get_post_meta( $id, 'tc_release_status', true ), array( 'coming-soon' => 'Coming soon', 'available' => 'Available', 'archived' => 'Archived' ) );
        self::select( 'tc_input_method', 'Input', (string) get_post_meta( $id, 'tc_input_method', true ), array( '' => 'Not yet verified', 'controller' => 'Controller', 'mnk' => 'Mouse & keyboard' ) );
        foreach ( array( 'tc_config_version' => 'Version', 'tc_game_version' => 'Game version', 'tc_creator' => 'Creator', 'tc_publisher' => 'Publisher', 'tc_release_date' => 'Release date (YYYY-MM-DD)', 'tc_tutorial_url' => 'Public preview / tutorial URL (HTTPS)' ) as $key => $label ) { self::input( $key, $label, (string) get_post_meta( $id, $key, true ) ); }
        foreach ( array( 'tc_compatibility' => 'Verified compatibility', 'tc_changelog' => 'Changelog' ) as $key => $label ) { self::input( $key, $label, (string) get_post_meta( $id, $key, true ), true ); }
        if ( class_exists( 'TC_Config_Content' ) ) { echo TC_Config_Content::editor_fields( $id ); }
        if ( ! $id ) {
            echo '<div class="tc-owner-first-upload"><h3>Optional: attach your first script files</h3><p>Choose up to 10 private config files now, or add them after saving. The script must pass format and malware/inspector checks and is never uploaded to Media Library. If a file fails, the new configuration stays unpublished.</p>';
            echo '<label>Config files <input type="file" name="tc_private_files[]" accept="' . esc_attr( TC_Private_Files::accept() ) . '" multiple></label>';
            self::input( 'file_version', 'File version (optional)', '' );
            self::input( 'file_notes', 'File notes (optional)', '', true );
            echo '</div>';
        }
        echo '<button class="tc-button" type="submit">' . ( $id ? 'Save changes' : 'Create configuration' ) . '</button></form>';
        if ( $id ) {
            self::form_start( $id, 'image' ); echo '<h3>Public preview image</h3><input type="file" name="preview_image" accept="image/jpeg,image/png,image/webp,image/avif" required><button class="tc-button" type="submit">Upload public image</button></form>';
            $storage = TC_Private_Files::root();
            echo '<h3>Protected script files</h3><p>Upload up to 10 separate files, 20 MB each. ZNBK and XIMMR require an approved format inspector, including text-encoded backups. Scripts stay outside the public media library.</p>';
            if ( is_wp_error( $storage ) ) { echo '<p role="alert">' . esc_html( $storage->get_error_message() ) . '</p>'; }
            else {
                self::form_start( $id, 'upload' ); echo '<input type="file" name="tc_private_files[]" accept="' . esc_attr( TC_Private_Files::accept() ) . '" multiple required>'; self::input( 'version', 'File version', '' ); self::input( 'notes', 'Release notes', '', true ); echo '<button class="tc-button" type="submit">Add protected files</button></form>';
                foreach ( TC_Private_Files::files( $id ) as $file ) {
                    echo '<details><summary>' . esc_html( $file['name'] . ' · ' . $file['version'] ) . '</summary>';
                    self::form_start( $id, 'update' ); echo '<input type="hidden" name="file_id" value="' . esc_attr( $file['id'] ) . '">'; self::input( 'version', 'Version', $file['version'] ); self::input( 'notes', 'Notes', $file['notes'], true ); self::input( 'order', 'Order', (string) $file['order'] ); echo '<button class="tc-button" type="submit">Update file details</button></form>';
                    self::form_start( $id, 'replace' ); echo '<input type="hidden" name="file_id" value="' . esc_attr( $file['id'] ) . '"><input type="file" name="tc_private_files[]" accept="' . esc_attr( TC_Private_Files::accept() ) . '" required>'; self::input( 'version', 'New version', $file['version'] ); self::input( 'notes', 'Notes', $file['notes'], true ); echo '<label><input type="checkbox" name="confirm" value="1" required>Confirm replacement; old download ID will stop working.</label><button type="submit">Replace file</button></form>';
                    self::form_start( $id, 'delete' ); echo '<input type="hidden" name="file_id" value="' . esc_attr( $file['id'] ) . '"><label><input type="checkbox" name="confirm" value="1" required>Confirm deleting this file.</label><button type="submit">Delete file</button></form></details>';
                }
            }
            self::form_start( $id, 'trash' ); echo '<label><input type="checkbox" name="confirm" value="1" required>Move configuration to Trash. Protected files stay backed up; downloads stop.</label><button type="submit">Move to Trash</button></form>';
        }
        echo '</section>'; return (string) ob_get_clean();
    }
}
