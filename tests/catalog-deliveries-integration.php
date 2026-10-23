<?php
/** Real catalogue aggregation/query tests. Disposable localhost only; no outbound HTTP. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost required.' ); }
$checks = 0; $ids = array(); $term_id = 0;
$old_user = get_current_user_id(); $old_get = $_GET; $old_query = $GLOBALS['wp_query']; $old_main = $GLOBALS['wp_the_query'];
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$call = static function ( string $method, ...$args ) { return ( new ReflectionMethod( TC_Downloads::class, $method ) )->invoke( null, ...$args ); };
$slug = 'tc-deliveries-' . strtolower( wp_generate_password( 10, false ) );
$query = static function ( mixed $sort, int $page = 1 ) use ( $slug ): WP_Query {
    $_GET = array( 'sort' => $sort );
    $q = new WP_Query(); $GLOBALS['wp_query'] = $q; $GLOBALS['wp_the_query'] = $q;
    $q->query( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'tc_game' => $slug, 'fields' => 'ids', 'paged' => $page ) );
    return $q;
};
try {
    wp_set_current_user( 0 );
    $term = wp_insert_term( 'LOCAL completed-deliveries fixture', 'tc_game', array( 'slug' => $slug ) );
    if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
    $term_id = (int) $term['term_id'];
    for ( $i = 0; $i < 14; $i++ ) {
        $id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => sprintf( 'LOCAL DELIVERY %02d', $i ), 'post_date' => sprintf( '2026-01-%02d 12:00:00', $i + 1 ), 'meta_input' => array( 'tc_tier' => 'free', 'tc_release_status' => 'available' ) ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $ids[] = (int) $id; wp_set_object_terms( $id, array( $term_id ), 'tc_game' );
    }
    $assert( 0 === TC_Downloads::count( $ids[0] ) && false === get_option( '_tc_delivery_count_' . $ids[0] ), 'New configs have zero real deliveries without invented data.' );
    $assert( is_wp_error( TC_Downloads::mint( $ids[0], wp_generate_uuid4() ) ) && 0 === TC_Downloads::count( $ids[0] ), 'An unauthenticated request does not increment downloads.' );
    $call( 'increment_count', $ids[0] ); $call( 'increment_count', $ids[0] ); $call( 'increment_count', $ids[1] );
    $assert( 2 === TC_Downloads::count( $ids[0] ) && 1 === TC_Downloads::count( $ids[1] ), 'Completed delivery aggregate increments independently per configuration.' );
    global $wpdb;
    $assert( 'no' === $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name=%s", '_tc_delivery_count_' . $ids[0] ) ), 'Download aggregates never autoload on unrelated pages.' );
    $first = $query( 'downloads' );
    $assert( array_slice( array_map( 'intval', $first->posts ), 0, 2 ) === array( $ids[0], $ids[1] ), 'Downloads sorts actual measured aggregates descending.' );
    $assert( 14 === (int) $first->found_posts && 12 === count( $first->posts ) && 2 === (int) $first->max_num_pages, 'Zero-count releases remain in count sorting and stable pagination.' );
    $assert( (int) $first->posts[2] === $ids[13], 'Zero-count ties use publication date and ID, not modification date.' );
    $second = $query( 'downloads', 2 );
    $assert( 2 === count( $second->posts ) && ! array_intersect( $first->posts, $second->posts ), 'Next page contains distinct zero-count releases.' );
    $assert( str_contains( $first->request, 'LEFT JOIN' ) && ! str_contains( $first->request, 'tc_download_count' ), 'SQL uses a unique per-config aggregate and retains unmeasured posts.' );
    $call( 'increment_count', $ids[1] ); $call( 'increment_count', $ids[1] );
    $assert( (int) $query( 'downloads' )->posts[0] === $ids[1], 'A completed delivery invalidates cached query ordering.' );
    $by_date = $query( 'newest' ); $assert( (int) $by_date->posts[0] === $ids[13], 'Upload Date uses actual post publication date.' );
    $bad = $query( array( 'downloads' ) ); $assert( ! $bad->get( '_tc_download_order' ), 'Malformed sort cannot activate count SQL.' );
    $bad = $query( 'downloads DESC; invalid' ); $assert( ! $bad->get( '_tc_download_order' ), 'Unrecognized sort never becomes a query fragment.' );
    wp_update_post( array( 'ID' => $ids[0], 'post_status' => 'draft' ) );
    $assert( 0 === TC_Downloads::count( $ids[0] ) && ! in_array( $ids[0], array_map( 'intval', $query( 'downloads' )->posts ), true ), 'Draft aggregate is neither public nor a catalogue result.' );
    $count_record = (string) get_option( '_tc_delivery_count_' . $ids[1] );
    $assert( '3' === $count_record, 'Aggregate contains only a number, no user identity or download history.' );
    wp_delete_post( $ids[1], true );
    $assert( false === get_option( '_tc_delivery_count_' . $ids[1] ), 'Permanent config deletion removes its aggregate.' );
    WP_CLI::success( "Catalogue delivery aggregation: $checks assertions passed." );
} finally {
    $_GET = $old_get; $GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main;
    foreach ( $ids as $id ) { wp_delete_post( $id, true ); delete_option( '_tc_delivery_count_' . $id ); }
    if ( $term_id ) { wp_delete_term( $term_id, 'tc_game' ); }
    wp_set_current_user( $old_user );
}
