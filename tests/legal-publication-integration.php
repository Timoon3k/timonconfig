<?php
/** Local WordPress only. All publication fixtures are rolled back, including menus and archives. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Legal' ) || ! class_exists( 'TC_Site' ) ) { throw new RuntimeException( 'Activate the current TimonConfigs Core first.' ); }
if ( wp_using_ext_object_cache() ) { throw new RuntimeException( 'Use the disposable local WordPress without a shared persistent object cache for rollback-isolated legal fixtures.' ); }

global $wpdb;
$checks = 0; $requests = 0; $tag = 'legal_' . bin2hex( random_bytes( 6 ) );
$old_user = get_current_user_id(); $old_method = $_SERVER['REQUEST_METHOD'] ?? null; $old_uri = $_SERVER['REQUEST_URI'] ?? null; $old_https = $_SERVER['HTTPS'] ?? null;
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Legal publication: ' . $message ); } ++$checks; };
$must = static function ( mixed $value ): mixed { if ( is_wp_error( $value ) ) { throw new RuntimeException( 'Unexpected legal publication error: ' . $value->get_error_code() ); } return $value; };
$block_http = static function () use ( &$requests ): WP_Error { ++$requests; return new WP_Error( 'tc_legal_fixture_network', 'External HTTP is disabled for legal fixtures.' ); };
$archive_prefix = '_tc_legal_archive_';
$state = static function () use ( $wpdb, $archive_prefix ): array {
    $slugs = array( 'terms-of-service', 'privacy-policy', 'refund-policy', 'cookie-policy', 'lifetime-license-terms' );
    $placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
    return array(
        'pages' => $wpdb->get_results( $wpdb->prepare( 'SELECT ID,post_name,post_title,post_content,post_status,post_date,post_date_gmt FROM ' . $wpdb->posts . " WHERE post_type='page' AND post_name IN ($placeholders) ORDER BY ID", ...$slugs ), ARRAY_A ),
        'menus' => $wpdb->get_results( 'SELECT ID,post_name,post_title,post_content,post_status,menu_order FROM ' . $wpdb->posts . " WHERE post_type='nav_menu_item' ORDER BY ID", ARRAY_A ),
        'options' => $wpdb->get_results( $wpdb->prepare( 'SELECT option_name,option_value,autoload FROM ' . $wpdb->options . ' WHERE option_name IN (%s,%s,%s,%s) OR option_name LIKE %s ORDER BY option_name', 'timonconfigs_legal', 'timonconfigs_legal_published', 'wp_page_for_privacy_policy', 'timonconfigs_owner_legal_release', $wpdb->esc_like( $archive_prefix ) . '%' ), ARRAY_A ),
    );
};
$before_database = $state();
foreach ( array( $wpdb->posts, $wpdb->postmeta, $wpdb->options, $wpdb->users, $wpdb->usermeta, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships ) as $table ) {
    $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
    if ( 'innodb' !== strtolower( (string) $engine ) ) { throw new RuntimeException( 'Transactional WordPress tables are required for fully reversible legal fixtures.' ); }
}
if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Could not isolate legal publication fixtures.' ); }
add_filter( 'pre_http_request', $block_http, 1, 3 );
$home_override = static fn(): string => 'http://legal-fixture.example.invalid';
$capture = array();
$redirect = static function ( string $url, int $status ) use ( &$capture ): string { $capture = array( $url, $status ); throw new RuntimeException( 'tc_legal_fixture_redirect' ); };
$downgrade = static function ( array $data ): array { if ( 'privacy-policy' === ( $data['post_name'] ?? '' ) ) { $data['post_status'] = 'draft'; } return $data; };
try {
    $admin = $must( wp_insert_user( array( 'user_login' => $tag . '_admin', 'user_pass' => wp_generate_password( 48 ), 'role' => 'administrator' ) ) );
    $member = $must( wp_insert_user( array( 'user_login' => $tag . '_member', 'user_pass' => wp_generate_password( 48 ), 'role' => 'subscriber' ) ) );
    wp_set_current_user( $admin ); delete_option( 'timonconfigs_legal' ); delete_option( 'timonconfigs_legal_published' );
    $profile = array( 'seller_name' => 'TimonConfigs local legal fixture', 'seller_address' => 'Synthetic Street 1, 00-001 Warsaw, Poland', 'seller_email' => 'legal-fixture@example.invalid', 'seller_registration' => 'Synthetic test activity and tax information; not a real seller.', 'seller_phone' => '', 'prices_confirmed' => true );
    $payload = static fn( array $value ): array => array( '_wpnonce' => wp_create_nonce( 'tc_legal_publish' ), 'profile' => $value );
    $ids = array(); $originals = array();
    foreach ( array_merge( array_keys( TC_Legal::pages() ), array_keys( TC_Legal::retired_slugs() ) ) as $index => $slug ) {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        $data = array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $tag . ' authored ' . $slug, 'post_content' => '<!-- wp:paragraph --><p>Authored original ' . esc_html( $tag . ' ' . $slug ) . '</p><!-- /wp:paragraph -->', 'post_status' => 'privacy-policy' === $slug ? 'draft' : 'publish' );
        if ( $page instanceof WP_Post ) { $data['ID'] = $page->ID; }
        $id = $must( $page instanceof WP_Post ? wp_update_post( $data, true ) : wp_insert_post( $data, true ) );
        $ids[ $slug ] = (int) $id; $originals[ $slug ] = get_post( $id )->to_array();
    }
    $menu = $must( wp_create_nav_menu( $tag . ' legal menu' ) ); $sentinel = 0;
    foreach ( $ids as $slug => $id ) {
        $item = $must( wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) ) );
        if ( 'terms-of-service' === $slug ) { $sentinel = (int) $item; }
    }
    $collision = $must( wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Independent custom contact link', 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/contact/' ), 'menu-item-status' => 'publish' ) ) );
    // Menu object IDs are meaningful only together with object/type. A matching
    // numeric ID on an unrelated item is not a reference to the retired Page.
    update_post_meta( $collision, '_menu_item_object_id', $ids['cookie-policy'] );
    $baseline = $state();
    wp_set_current_user( $member );
    $assert( is_wp_error( TC_Legal::perform( $payload( $profile ) ) ) && $baseline === $state(), 'A member cannot publish legal documents or create an archive.' );
    wp_set_current_user( $admin ); $bad_nonce = $payload( $profile ); $bad_nonce['_wpnonce'] = 'invalid';
    $assert( is_wp_error( TC_Legal::perform( $bad_nonce ) ) && $baseline === $state(), 'An invalid nonce cannot replace or retire existing pages.' );
    foreach ( array( 'seller_name' => '', 'seller_email' => 'not-an-email', 'seller_registration' => '', 'prices_confirmed' => false, 'seller_address' => array( 'invalid shape' ) ) as $field => $value ) {
        $bad = $profile; $bad[ $field ] = $value;
        $assert( is_wp_error( TC_Legal::perform( $payload( $bad ) ) ) && $baseline === $state(), 'Incomplete or malformed seller data leaves publication untouched: ' . $field );
    }
    $bad = $profile; $bad['seller_name'] = '{{unfilled_name}}';
    $assert( is_wp_error( TC_Legal::document( 'terms-of-service', $bad ) ) && is_wp_error( TC_Legal::document( '../privacy-policy', $profile ) ), 'Unfilled seller placeholders and unsupported template paths are rejected.' );
    $_SERVER['HTTPS'] = 'off'; add_filter( 'option_home', $home_override );
    $assert( is_wp_error( TC_Legal::perform( $payload( $profile ) ) ) && $baseline === $state(), 'Remote HTTP cannot publish seller details or policies.' ); remove_filter( 'option_home', $home_override );

    $safe = $profile; $safe['seller_name'] = 'Local & <img src=x onerror=alert(1)> fixture';
    $safe_document = $must( TC_Legal::document( 'terms-of-service', $safe ) );
    $assert( ! preg_match( '/<script\b|<[^>]+\sonerror\s*=/i', $safe_document ) && str_contains( $safe_document, 'Local &amp;' ), 'Seller text cannot inject executable HTML into a rendered policy.' );

    // A failed policy write must not retire the old pages or claim a complete publication.
    $wpdb->query( 'SAVEPOINT tc_legal_partial' ); add_filter( 'wp_insert_post_data', $downgrade, 99 );
    $failed = TC_Legal::perform( $payload( $profile ) ); remove_filter( 'wp_insert_post_data', $downgrade, 99 );
    $assert( is_wp_error( $failed ) && ! TC_Legal::published() && 'publish' === get_post_status( $ids['cookie-policy'] ) && 'publish' === get_post_status( $ids['lifetime-license-terms'] ), 'A failed canonical publication keeps previous duplicate pages available and does not activate redirects.' );
    $wpdb->query( 'ROLLBACK TO SAVEPOINT tc_legal_partial' ); wp_cache_flush(); wp_set_current_user( $admin );

    $billing_hash = hash( 'sha256', serialize( get_option( 'timonconfigs_billing' ) ) ); $discord_hash = hash( 'sha256', serialize( get_option( 'timonconfigs_discord' ) ) );
    $result = $must( TC_Legal::perform( $payload( $profile ) ) );
    $assert( is_array( $result ) && TC_Legal::ready() && TC_Legal::published(), 'A fully identified seller can explicitly publish all three policies.' );
    foreach ( TC_Legal::pages() as $slug => $title ) {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        $assert( $page instanceof WP_Post && $page->ID === $ids[ $slug ] && 'publish' === $page->post_status, 'The canonical policy preserves its page ID and is published: ' . $slug );
        $assert( has_block( 'core/heading', $page->post_content ) && has_block( 'core/paragraph', $page->post_content ) && ! preg_match( '/\bdraft\b|pending owner|\{\{/i', $page->post_content ), 'Final policy contains editable native blocks without public draft labels or template tokens: ' . $slug );
        $assert( str_contains( $page->post_content, $profile['seller_name'] ) && str_contains( $page->post_content, $profile['seller_email'] ), 'Final policy identifies the supplied seller and direct contact: ' . $slug );
        $assert( TC_Site::default_content( $slug ) === $page->post_content, 'The configured site fallback uses the same completed policy: ' . $slug );
    }
    $terms = get_post_field( 'post_content', $ids['terms-of-service'] ); $privacy = get_post_field( 'post_content', $ids['privacy-policy'] ); $refund = get_post_field( 'post_content', $ids['refund-policy'] );
    $assert( str_contains( $terms, '€29.99' ) && str_contains( $terms, '€80' ) && str_contains( $terms, 'id="lifetime-license"' ), 'Terms agree with the live EUR catalogue and include the Lifetime license section.' );
    $assert( str_contains( $privacy, 'id="cookies"' ) && str_contains( $privacy, '__Host-tc_discord_state' ) && has_block( 'core/table', $privacy ), 'Privacy includes the actual core cookie inventory in an editable table.' );
    $assert( str_contains( $refund, '14 days' ) && str_contains( $refund, 'id="model-withdrawal"' ) && str_contains( $refund, 'not a substitute' ), 'Withdrawal instructions preserve the 14-day right instead of inventing a checkout waiver.' );
    $assert( (int) get_option( 'wp_page_for_privacy_policy' ) === $ids['privacy-policy'], 'WordPress points at the published canonical privacy page.' );
    foreach ( TC_Legal::retired_slugs() as $slug => $target ) { $assert( 'trash' === get_post_status( $ids[ $slug ] ), 'A redundant policy is reversibly retired: ' . $slug ); }
    $items = wp_get_nav_menu_items( $menu ) ?: array();
    $page_items = array_filter( $items, static fn( WP_Post $item ): bool => 'post_type' === $item->type && 'page' === $item->object );
    $object_ids = array_map( static fn( WP_Post $item ): int => (int) $item->object_id, $page_items );
    $assert( ! in_array( $ids['cookie-policy'], $object_ids, true ) && ! in_array( $ids['lifetime-license-terms'], $object_ids, true ) && get_post( $sentinel ) instanceof WP_Post, 'Only retired menu links disappear; the unrelated canonical entry remains.' );
    $assert( get_post( $collision ) instanceof WP_Post && 'custom' === get_post_meta( $collision, '_menu_item_type', true ), 'A matching numeric object ID cannot retire an unrelated custom menu entry.' );
    $archive = $result['archive'] ?? '';
    $stored_archive = is_string( $archive ) ? get_option( $archive ) : false;
    $autoload = $wpdb->get_var( $wpdb->prepare( 'SELECT autoload FROM ' . $wpdb->options . ' WHERE option_name=%s', $archive ) );
    $assert( str_starts_with( $archive, $archive_prefix ) && is_array( $stored_archive ) && ! in_array( $autoload, array( 'yes', 'on', 'auto-on', 'auto' ), true ), 'Original legal versions are archived in a private non-autoloaded option.' );
    foreach ( $originals as $slug => $original ) { $assert( $original['post_content'] === ( $stored_archive['pages'][ $slug ]['post']['post_content'] ?? null ) && $original['post_status'] === ( $stored_archive['pages'][ $slug ]['post']['post_status'] ?? null ), 'The archive preserves authored content and original publication status: ' . $slug ); }

    $again = $must( TC_Legal::perform( $payload( $profile ) ) );
    $assert( $again['ids'] === $result['ids'], 'Repeated publication preserves the same three canonical IDs.' );
    $must( TC_Site::install() );
    foreach ( TC_Legal::retired_slugs() as $slug => $target ) {
        $active = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->posts . " WHERE post_type='page' AND post_name=%s AND post_status<>'trash'", $slug ) );
        $assert( 0 === (int) $active, 'Running normal site setup cannot recreate a retired policy: ' . $slug );
    }

    add_filter( 'wp_redirect', $redirect, -999, 2 );
    foreach ( TC_Legal::retired_slugs() as $slug => $target ) {
        foreach ( array( 'GET', 'HEAD' ) as $method ) {
            $_SERVER['REQUEST_METHOD'] = $method; $_SERVER['REQUEST_URI'] = wp_parse_url( home_url( '/' . $slug . '/' ), PHP_URL_PATH ) . '?redirect_to=https://evil.example/'; $capture = array();
            try { TC_Legal::redirect_legacy(); } catch ( RuntimeException $error ) { if ( 'tc_legal_fixture_redirect' !== $error->getMessage() ) { throw $error; } }
            $assert( array( home_url( '/' . $target ), 301 ) === $capture, 'The old policy route redirects only to its canonical same-site section: ' . $slug . '/' . $method );
        }
        $_SERVER['REQUEST_METHOD'] = 'POST'; $capture = array(); TC_Legal::redirect_legacy();
        $assert( array() === $capture, 'Policy consolidation does not redirect POST bodies: ' . $slug );
    }
    remove_filter( 'wp_redirect', $redirect, -999 );
    $partial = $profile; $partial['seller_address'] = ''; $partial['seller_email'] = '';
    $partial_result = $must( TC_Legal::perform( $payload( $partial ) ) );
    $assert( is_array( $partial_result ) && TC_Legal::ready() && TC_Legal::published(), 'The owner can explicitly publish supplied identification without fabricating omitted contact details.' );
    foreach ( TC_Legal::pages() as $slug => $title ) {
        $partial_text = get_post_field( 'post_content', $ids[ $slug ] );
        $assert( ! str_contains( $partial_text, '{{' ) && ! preg_match( '/mailto:\s*["\x27]|address is\s*\.|,\s*,/i', $partial_text ) && str_contains( $partial_text, home_url( '/contact/' ) ), 'A policy with omitted address/email uses its actual Contact route and contains no blank contact placeholders: ' . $slug );
    }
    if ( method_exists( TC_Site::class, 'maybe_publish_owner_legal_release' ) ) {
        // Simulate an already published 0.9.6 release with the former empty contact fields.
        update_option( 'timonconfigs_owner_legal_release', '2026-10-26-legal-v1', false ); wp_set_current_user( $member ); $before_auto = $state();
        TC_Site::maybe_publish_owner_legal_release();
        $assert( $before_auto === $state(), 'An ordinary member request cannot perform the owner-authorized legal publication migration.' );
        wp_set_current_user( $admin );
        $wpdb->query( 'SAVEPOINT tc_legal_contact_failure' ); add_filter( 'wp_insert_post_data', $downgrade, 99 );
        TC_Site::maybe_publish_owner_legal_release(); remove_filter( 'wp_insert_post_data', $downgrade, 99 );
        $assert( '2026-10-26-legal-v1' === get_option( 'timonconfigs_owner_legal_release' ), 'An unsuccessful contact upgrade does not record the new release as completed.' );
        $wpdb->query( 'ROLLBACK TO SAVEPOINT tc_legal_contact_failure' ); wp_cache_flush();
        TC_Site::maybe_publish_owner_legal_release();
        $assert( '2026-10-26-legal-v2' === get_option( 'timonconfigs_owner_legal_release' ) && TC_Legal::published(), 'The authorized administrator migration upgrades the old release only after the legal pages are published.' );
        $contact_defaults = apply_filters( 'timonconfigs_legal_defaults', array() ); $updated_profile = TC_Legal::profile();
        $assert( $updated_profile['seller_email'] === $contact_defaults['seller_email'] && $updated_profile['seller_address'] === $contact_defaults['seller_address'] && '' !== $updated_profile['seller_address'], 'The v2 update replaces stored empty contact fields with the owner-supplied address and email.' );
        $assert( $updated_profile['seller_name'] === $partial['seller_name'] && $updated_profile['seller_registration'] === $partial['seller_registration'], 'The contact update preserves the other administrator-saved identity and business fields.' );
        foreach ( TC_Legal::pages() as $slug => $title ) {
            $updated_page = get_page_by_path( $slug, OBJECT, 'page' );
            $assert( $updated_page->ID === $ids[ $slug ] && 'publish' === $updated_page->post_status && str_contains( $updated_page->post_content, esc_html( $contact_defaults['seller_address'] ) ) && str_contains( $updated_page->post_content, 'mailto:' . $contact_defaults['seller_email'] ), 'The existing published policy receives the new contact information without changing its ID: ' . $slug );
        }
        $after_auto = $state(); TC_Site::maybe_publish_owner_legal_release();
        $assert( $after_auto === $state(), 'A repeated administrator initialization does not rewrite documents or create more legal archives.' );
    }
    $assert( $billing_hash === hash( 'sha256', serialize( get_option( 'timonconfigs_billing' ) ) ) && $discord_hash === hash( 'sha256', serialize( get_option( 'timonconfigs_discord' ) ) ) && 0 === $requests, 'Publishing policies changes no payment or Discord settings and makes no external requests.' );
    WP_CLI::success( "Legal publication integration: $checks assertions; real local WordPress, transaction-isolated synthetic seller, no external requests or production publication." );
} finally {
    remove_filter( 'pre_http_request', $block_http, 1 ); remove_filter( 'option_home', $home_override ); remove_filter( 'wp_redirect', $redirect, -999 ); remove_filter( 'wp_insert_post_data', $downgrade, 99 );
    $wpdb->query( 'ROLLBACK' ); wp_cache_flush(); wp_set_current_user( $old_user );
    foreach ( array( 'REQUEST_METHOD' => $old_method, 'REQUEST_URI' => $old_uri, 'HTTPS' => $old_https ) as $key => $value ) { if ( null === $value ) { unset( $_SERVER[ $key ] ); } else { $_SERVER[ $key ] = $value; } }
    if ( $before_database !== $state() ) { throw new RuntimeException( 'Legal fixture rollback did not restore the original policies, menus and options.' ); }
}
