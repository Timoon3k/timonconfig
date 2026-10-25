<?php
/** Disposable localhost WordPress: exact stock-content migration, never provider calls. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress is required.' ); }
if ( ! class_exists( 'TC_Site' ) ) { throw new RuntimeException( 'Activate TimonConfigs Core.' ); }

global $wpdb;
$checks = 0; $posts = array(); $created = array(); $users = array();
$original_user = get_current_user_id();
$version = get_option( 'timonconfigs_checkout_copy_version', null );
$old_error = get_option( 'timonconfigs_checkout_copy_error', null );
$tag = 'tc_copy_' . strtolower( wp_generate_password( 12, false ) );
$backup_key = '_tc_checkout_copy_v1_backup';
$legal_key = '_' . $tag . '_legal_review';
$slugs = array( 'pricing', 'vip-monthly', 'terms-of-service', 'privacy-policy', 'refund-policy' );
$fields = array( 'post_content', 'post_title', 'post_status', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' );
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Checkout content migration: ' . $message ); } ++$checks; };
$must = static function ( mixed $result ): mixed { if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Unexpected fixture error: ' . $result->get_error_code() ); } return $result; };
$historical = new ReflectionMethod( TC_Site::class, 'legacy_checkout_content' );
$snapshot = static function ( int $id ) use ( $fields ): array { return array_intersect_key( get_post( $id, ARRAY_A ), array_fill_keys( $fields, true ) ); };
$write = static function ( int $id, array $data ) use ( $wpdb ): void { if ( false === $wpdb->update( $wpdb->posts, $data, array( 'ID' => $id ) ) ) { throw new RuntimeException( 'Fixture content write failed.' ); } clean_post_cache( $id ); };
$network = 0;
$block_http = static function () use ( &$network ): void { ++$network; throw new RuntimeException( 'Content migration must not contact any remote service.' ); };
add_filter( 'pre_http_request', $block_http, 1 );
try {
    foreach ( array( 'administrator', 'subscriber' ) as $role ) {
        $uid = $must( wp_insert_user( array( 'user_login' => $tag . '_' . $role, 'user_pass' => wp_generate_password( 48 ), 'role' => $role ) ) );
        $users[ $role ] = (int) $uid;
    }
    foreach ( $slugs as $index => $slug ) {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page instanceof WP_Post ) { $id = $must( wp_insert_post( array( 'post_type' => 'page', 'post_title' => $tag . ' ' . $slug, 'post_name' => $slug, 'post_status' => 'draft' ), true ) ); $created[] = (int) $id; $page = get_post( $id ); }
        $posts[ $slug ] = array( 'id' => (int) $page->ID, 'post' => $snapshot( $page->ID ), 'had_backup' => metadata_exists( 'post', $page->ID, $backup_key ), 'backup' => get_post_meta( $page->ID, $backup_key, true ) );
        delete_post_meta( $page->ID, $backup_key );
        update_post_meta( $page->ID, $legal_key, 'fixture legal review date must survive unchanged' );
        $legacy = $historical->invoke( null, $slug );
        $assert( str_contains( $legacy, 'Upgrade.chat' ) && has_blocks( $legacy ), 'Known pre-retirement stock fixture: ' . $slug );
        $assert( ! str_contains( TC_Site::default_content( $slug ), 'Upgrade.chat' ) && has_blocks( TC_Site::default_content( $slug ) ), 'New seed uses website checkout: ' . $slug );
        $write( $page->ID, array( 'post_content' => $legacy, 'post_title' => $tag . ' title ' . $index, 'post_status' => 0 === $index % 2 ? 'draft' : 'private', 'post_date' => '2002-03-04 05:06:07', 'post_date_gmt' => '2002-03-04 05:06:07', 'post_modified' => '2003-04-05 06:07:08', 'post_modified_gmt' => '2003-04-05 06:07:08' ) );
    }
    $fixture_before = array_map( static fn( array $row ): array => $snapshot( $row['id'] ), $posts );
    delete_option( 'timonconfigs_checkout_copy_version' );
    wp_set_current_user( $users['subscriber'] );
    $assert( is_wp_error( TC_Site::migrate_checkout_copy() ), 'A subscriber cannot invoke writes directly.' );
    TC_Site::maybe_migrate_checkout_copy();
    $assert( false === get_option( 'timonconfigs_checkout_copy_version' ) && $fixture_before === array_map( static fn( array $row ): array => $snapshot( $row['id'] ), $posts ), 'Non-admin admin_init cannot write stock content or mark migration done.' );
    $assert( 5 === count( array_filter( TC_Site::checkout_copy_review(), static fn( array $row ): bool => 'eligible' === $row['state'] ) ), 'Read-only review recognizes all exact stock pages.' );
    wp_set_current_user( $users['administrator'] );
    TC_Site::maybe_migrate_checkout_copy();
    $assert( '1' === get_option( 'timonconfigs_checkout_copy_version' ), 'Administrator migration records its completed version.' );
    foreach ( $posts as $slug => $row ) {
        $after = $snapshot( $row['id'] );
        $assert( TC_Site::default_content( $slug ) === $after['post_content'], 'Only the known stock body changes: ' . $slug );
        $before_dates = $fixture_before[ $slug ]; unset( $before_dates['post_content'] );
        $after_dates = $after; unset( $after_dates['post_content'] );
        $assert( $before_dates === $after_dates, 'Title, dates and private/draft status survive: ' . $slug );
        $assert( $historical->invoke( null, $slug ) === get_post_meta( $row['id'], $backup_key, true ), 'Original exact content has a private backup: ' . $slug );
        $assert( 'fixture legal review date must survive unchanged' === get_post_meta( $row['id'], $legal_key, true ), 'Legal review metadata is not approved or rewritten: ' . $slug );
    }
    $result = $must( TC_Site::migrate_checkout_copy() );
    $assert( array() === $result['updated'], 'Explicit repeat is idempotent.' );
    $after_first = array_map( static fn( array $row ): array => $snapshot( $row['id'] ), $posts );
    TC_Site::maybe_migrate_checkout_copy();
    $assert( $after_first === array_map( static fn( array $row ): array => $snapshot( $row['id'] ), $posts ), 'Versioned automatic repeat performs no content writes.' );
    $custom_id = $posts['pricing']['id'];
    $custom = $historical->invoke( null, 'pricing' ) . '<!-- wp:paragraph --><p>Owner-specific checkout and tax note.</p><!-- /wp:paragraph -->';
    $write( $custom_id, array( 'post_content' => $custom ) ); delete_post_meta( $custom_id, $backup_key );
    $custom_before = $snapshot( $custom_id );
    $result = $must( TC_Site::migrate_checkout_copy() );
    $assert( array() === $result['updated'] && $custom_before === $snapshot( $custom_id ) && ! metadata_exists( 'post', $custom_id, $backup_key ), 'Authored additions are preserved in full; no blanket replacement or misleading backup.' );
    $custom_review = array_values( array_filter( $result['review'], static fn( array $row ): bool => $custom_id === $row['id'] ) );
    $assert( 'manual_review' === ( $custom_review[0]['state'] ?? '' ), 'Administrator sees a manual-review entry for custom retired-provider wording.' );
    $write( $custom_id, array( 'post_content' => str_replace( 'Upgrade.chat', 'UPGRADE.CHAT', $custom ) ) );
    $assert( 'manual_review' === array_values( array_filter( TC_Site::checkout_copy_review(), static fn( array $row ): bool => $custom_id === $row['id'] ) )[0]['state'], 'Custom provider text is flagged case-insensitively without rewriting it.' );
    $write( $custom_id, array( 'post_content' => $historical->invoke( null, 'pricing' ) ) );
    delete_post_meta( $custom_id, $backup_key );
    $raced = false;
    $owner_edit = '<!-- wp:paragraph --><p>Concurrent owner checkout wording.</p><!-- /wp:paragraph -->';
    $race = static function ( string $sql ) use ( &$raced, $custom_id, $owner_edit, $write, $wpdb ): string {
        if ( ! $raced && str_starts_with( $sql, 'UPDATE ' . $wpdb->posts . ' SET post_content' ) && str_contains( $sql, 'AND BINARY post_content' ) ) {
            $raced = true;
            $write( $custom_id, array( 'post_content' => $owner_edit ) );
        }
        return $sql;
    };
    add_filter( 'query', $race );
    try { $result = TC_Site::migrate_checkout_copy(); }
    finally { remove_filter( 'query', $race ); }
    $assert( $raced && is_wp_error( $result ) && 'tc_checkout_copy_changed' === $result->get_error_code(), 'A concurrent editor causes a safe compare-and-swap refusal.' );
    $assert( $owner_edit === get_post_field( 'post_content', $custom_id ), 'Concurrent authored content survives unchanged.' );
    $assert( 0 === $network, 'Migration makes no remote calls and cannot change subscriptions or Discord roles.' );
    WP_CLI::success( "Website checkout copy: $checks assertions; exact stock migration, capabilities, dates, backups and authored content verified locally." );
} finally {
    remove_filter( 'pre_http_request', $block_http, 1 );
    foreach ( $posts as $row ) {
        if ( ! in_array( $row['id'], $created, true ) ) { $write( $row['id'], $row['post'] ); }
        delete_post_meta( $row['id'], $backup_key );
        if ( $row['had_backup'] ) { update_post_meta( $row['id'], $backup_key, wp_slash( $row['backup'] ) ); }
        delete_post_meta( $row['id'], $legal_key );
    }
    foreach ( $created as $id ) { wp_delete_post( $id, true ); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $users as $uid ) { wp_delete_user( $uid ); }
    null === $version ? delete_option( 'timonconfigs_checkout_copy_version' ) : update_option( 'timonconfigs_checkout_copy_version', $version, false );
    null === $old_error ? delete_option( 'timonconfigs_checkout_copy_error' ) : update_option( 'timonconfigs_checkout_copy_error', $old_error, false );
    wp_set_current_user( $original_user );
}
