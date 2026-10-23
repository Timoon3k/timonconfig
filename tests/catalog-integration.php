<?php
/**
 * Run with WP-CLI eval-file on a disposable local/staging database only.
 * Creates then deletes local fixtures; no purchases or membership changes.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TC_Catalog' ) ) { throw new RuntimeException( 'TimonConfigs Core must be active.' ); }

$tc_test_ids = array();
$tc_test_terms = array();
$tc_test_previous_get = $_GET;
$tc_test_previous_post = $_POST;
$tc_test_previous_user = get_current_user_id();
$tc_test_previous_query = $GLOBALS['wp_query'];
$tc_test_previous_main_query = $GLOBALS['wp_the_query'];
$tc_test_checks = 0;
$tc_test_expect = static function ( bool $condition, string $message ) use ( &$tc_test_checks ): void {
	if ( ! $condition ) { throw new RuntimeException( 'Catalogue test failed: ' . $message ); }
	$tc_test_checks++;
};
$tc_test_query = static function ( array $args, array $filters = array() ): array {
	$_GET = $filters;
	$query = new WP_Query();
	$GLOBALS['wp_query'] = $query;
	$GLOBALS['wp_the_query'] = $query;
	$query->query( array_merge( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'fields' => 'ids' ), $args ) );
	$ids = array_map( 'intval', $query->posts );
	sort( $ids );
	return $ids;
};

try {
	TC_Catalog::register_content_types();
	TC_Catalog::seed_core_terms();
	$tc_test_expect( count( TC_Catalog::game_definitions() ) === 7, 'seven distinct game definitions' );
	$tc_test_expect( ! isset( TC_Catalog::game_definitions()['the-finals'] ), 'The Finals excluded' );
	foreach ( TC_Catalog::game_definitions() as $slug => $definition ) {
		$term = get_term_by( 'slug', $slug, 'tc_game' );
		$tc_test_expect( $term instanceof WP_Term, 'seeded ' . $slug );
		$tc_test_expect( ! is_wp_error( get_term_link( $term ) ) && str_contains( get_term_link( $term ), '/' . $slug . '-configs/' ), 'short collection link ' . $slug );
	}
	$count_before = wp_count_terms( array( 'taxonomy' => 'tc_game', 'hide_empty' => false ) );
	TC_Catalog::seed_core_terms();
	$tc_test_expect( $count_before === wp_count_terms( array( 'taxonomy' => 'tc_game', 'hide_empty' => false ) ), 'term setup is idempotent' );
	$tc_test_expect( TC_Catalog::sanitize_date( '2026-02-30' ) === '' && TC_Catalog::sanitize_date( '2028-02-29' ) === '2028-02-29', 'calendar date validation' );
	$tc_test_expect( TC_Catalog::sanitize_date( array( 'bad' ) ) === '' && TC_Catalog::sanitize_tier( array( 'monthly' ) ) === '' && TC_Catalog::sanitize_input( 'other' ) === '', 'invalid scalar and enum values fail safely' );
	$tc_test_expect( TC_Catalog::sanitize_public_url( 'javascript:alert(1)' ) === '' && TC_Catalog::sanitize_public_url( 'https://example.test/free.zip' ) === 'https://example.test/free.zip', 'public URL protocol validation' );
	$slug = 'tc-catalog-test-' . strtolower( wp_generate_password( 8, false, false ) );
	$created_term = wp_insert_term( 'Catalogue integration fixture', 'tc_game', array( 'slug' => $slug, 'description' => str_repeat( 'Confirmed setup details with independently reviewed compatibility information. ', 3 ) ) );
	if ( is_wp_error( $created_term ) ) { throw new RuntimeException( $created_term->get_error_message() ); }
	$term_id = (int) $created_term['term_id'];
	$tc_test_terms[] = $term_id;
	$term = get_term( $term_id, 'tc_game' );
	$tc_test_expect( ! TC_Catalog::collection_has_configs( $term ) && ! TC_Catalog::is_collection_indexable( $term ), 'empty collections are unavailable and non-indexable' );
	$device = get_term_by( 'slug', 'cronus-zen', 'tc_device' );
	$pc = get_term_by( 'slug', 'pc', 'tc_platform' );
	$xbox = get_term_by( 'slug', 'xbox', 'tc_platform' );
	$fixtures = array(
		'monthly' => array( 'tier' => 'monthly', 'input' => 'controller', 'platform' => $pc->term_id, 'status' => 'available' ),
		'free' => array( 'tier' => 'free', 'input' => 'controller', 'platform' => $xbox->term_id, 'status' => 'available' ),
		'archived' => array( 'tier' => 'monthly', 'input' => 'mnk', 'platform' => $pc->term_id, 'status' => 'archived' ),
		'coming' => array( 'tier' => 'monthly', 'input' => 'controller', 'platform' => $pc->term_id, 'status' => 'coming-soon' ),
		'legacy' => array( 'tier' => 'lifetime', 'input' => 'mnk', 'platform' => $pc->term_id, 'status' => '' ),
	);
	$ids = array();
	foreach ( $fixtures as $key => $fixture ) {
		$id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'LOCAL TEST FIXTURE ' . $slug . ' ' . $key, 'post_content' => 'Disposable integration-test record.', 'meta_input' => array( 'tc_tier' => $fixture['tier'], 'tc_input_method' => $fixture['input'] ) ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$id = (int) $id;
		$ids[ $key ] = $id;
		$tc_test_ids[] = $id;
		if ( $fixture['status'] ) { update_post_meta( $id, 'tc_release_status', $fixture['status'] ); }
		wp_set_object_terms( $id, array( $term_id ), 'tc_game' );
		wp_set_object_terms( $id, array( $device->term_id ), 'tc_device' );
		wp_set_object_terms( $id, array( $fixture['platform'] ), 'tc_platform' );
	}
	$tc_test_expect( TC_Catalog::collection_has_configs( $term ) && TC_Catalog::is_collection_indexable( $term ), 'released populated collection is eligible to index' );
	update_term_meta( $term_id, 'tc_noindex', '1' );
	$tc_test_expect( ! TC_Catalog::is_collection_indexable( $term ), 'explicit noindex is respected' );
	delete_term_meta( $term_id, 'tc_noindex' );
	wp_set_current_user( 0 );
	$visible = array( $ids['monthly'], $ids['free'], $ids['coming'], $ids['legacy'] );
	sort( $visible );
	$tc_test_expect( $tc_test_query( array( 'tc_game' => $slug ) ) === $visible, 'taxonomy shows published available, coming-soon and legacy cards, never archived' );
	$tc_test_expect( $tc_test_query( array(), array( 'game' => $slug, 'tier' => 'monthly', 'device' => 'cronus-zen', 'input' => 'controller', 'platform' => 'pc' ) ) === array_values( array_intersect( $visible, array( $ids['monthly'], $ids['coming'] ) ) ), 'combined catalogue filters' );
	$tc_test_expect( $tc_test_query( array( 'tc_game' => $slug ), array( 'tier' => 'free', 'platform' => 'xbox' ) ) === array( $ids['free'] ), 'filters work on game collection' );
	$tc_test_expect( $tc_test_query( array( 'tc_device' => 'cronus-zen' ), array( 'game' => $slug, 'input' => 'mnk' ) ) === array( $ids['legacy'] ), 'device collection preserves base term and applies input/game filters' );
	$tc_test_expect( $tc_test_query( array( 'tc_platform' => 'pc' ), array( 'game' => $slug, 'tier' => 'monthly' ) ) === array_values( array_intersect( $visible, array( $ids['monthly'], $ids['coming'] ) ) ), 'platform collection retains its term while filtering game and tier' );
	$tc_test_expect( $tc_test_query( array( 'tc_platform' => 'pc' ), array( 'game' => $slug, 'platform' => 'xbox' ) ) === array(), 'a platform parameter cannot replace the current platform collection' );
	$tc_test_expect( $tc_test_query( array( 'tc_game' => $slug ), array( 'game' => 'apex-legends' ) ) === array(), 'filtering cannot erase the current collection constraint' );
	$tc_test_expect( $tc_test_query( array( 'tc_game' => $slug ), array( 'config_search' => $slug . ' free' ) ) === array( $ids['free'] ), 'catalogue search' );
	$tc_test_expect( $tc_test_query( array( 'tc_game' => $slug ), array( 'tier' => array( 'free' ), 'input' => array( 'mnk' ), 'platform' => array( 'pc' ), 'config_search' => array( 'bad' ) ) ) === $visible, 'array-shaped query arguments are safely ignored' );
	update_post_meta( $ids['free'], 'tc_access_url', 'https://example.test/free.zip' );
	update_post_meta( $ids['monthly'], 'tc_access_url', 'https://example.test/premium.zip' );
	$tc_test_expect( TC_Catalog::public_access_url( $ids['free'] ) === 'https://example.test/free.zip' && TC_Catalog::public_access_url( $ids['monthly'] ) === '', 'premium links fail closed even if legacy metadata exists' );
	update_post_meta( $ids['free'], 'tc_release_status', 'coming-soon' );
	$tc_test_expect( TC_Catalog::public_access_url( $ids['free'] ) === '', 'unreleased Free files are not exposed' );
	update_post_meta( $ids['free'], 'tc_release_status', 'available' );
	$_GET = array();
	$GLOBALS['wp_query'] = $tc_test_previous_query;
	$GLOBALS['wp_the_query'] = $tc_test_previous_main_query;
	$server = rest_get_server();
	$request = new WP_REST_Request( 'GET', '/wp/v2/tc_config/' . $ids['monthly'] );
	$response = $server->dispatch( $request );
	$tc_test_expect( $response->get_status() === 200 && ! array_key_exists( 'tc_access_url', $response->get_data()['meta'] ?? array() ), 'premium access URL is absent from public REST data' );
	$request = new WP_REST_Request( 'POST', '/wp/v2/tc_config/' . $ids['monthly'] );
	$request->set_param( 'meta', array( 'tc_tier' => 'free' ) );
	$response = $server->dispatch( $request );
	$tc_test_expect( in_array( $response->get_status(), array( 401, 403 ), true ) && get_post_meta( $ids['monthly'], 'tc_tier', true ) === 'monthly', 'anonymous REST update is denied' );
	$_POST = array( 'tc_config_details_nonce' => wp_create_nonce( 'tc_save_config_details' ), 'tc_tier' => 'free' );
	TC_Catalog::save_meta( $ids['monthly'] );
	$tc_test_expect( get_post_meta( $ids['monthly'], 'tc_tier', true ) === 'monthly', 'a nonce never substitutes for edit-post capability' );
	$_POST = array( 'tc_collection_image_nonce' => wp_create_nonce( 'tc_collection_image' ), 'tc_collection_image' => 'https://example.test/forbidden.jpg' );
	TC_Catalog::save_collection_image( $term_id );
	$tc_test_expect( get_term_meta( $term_id, 'tc_collection_image', true ) === '', 'collection image save requires term-edit capability' );
	$admin_ids = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) );
	if ( ! $admin_ids ) { throw new RuntimeException( 'An administrator is needed for local integration tests.' ); }
	wp_set_current_user( (int) $admin_ids[0] );
	$request = new WP_REST_Request( 'POST', '/wp/v2/tc_config/' . $ids['monthly'] );
	$request->set_param( 'meta', array( 'tc_tier' => 'unknown-tier' ) );
	$response = $server->dispatch( $request );
	$tc_test_expect( $response->get_status() === 400 && get_post_meta( $ids['monthly'], 'tc_tier', true ) === 'monthly', 'REST rejects unsupported tier without changing existing access' );
	$_POST = array( 'tc_config_details_nonce' => array( 'bad' ), 'tc_tier' => 'free' );
	TC_Catalog::save_meta( $ids['monthly'] );
	$tc_test_expect( get_post_meta( $ids['monthly'], 'tc_tier', true ) === 'monthly', 'malformed nonce prevents mutation' );
	$_POST = array( 'tc_config_details_nonce' => wp_create_nonce( 'tc_save_config_details' ), 'tc_tier' => 'monthly', 'tc_compatibility' => "Confirmed controller setup\nReview game version", 'tc_access_url' => 'https://example.test/premium.zip' );
	TC_Catalog::save_meta( $ids['monthly'] );
	$tc_test_expect( get_post_meta( $ids['monthly'], 'tc_access_url', true ) === '' && str_contains( get_post_meta( $ids['monthly'], 'tc_compatibility', true ), "\n" ), 'admin save retains multiline setup details and clears premium public links' );
	$before_drafts = get_posts( array( 'post_type' => 'tc_config', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_tc_seeded_flagship' ) );
	TC_Catalog::seed_flagship_drafts();
	$after_drafts = get_posts( array( 'post_type' => 'tc_config', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_tc_seeded_flagship' ) );
	$new_drafts = array_diff( $after_drafts, $before_drafts );
	foreach ( $new_drafts as $id ) {
		$tc_test_ids[] = (int) $id;
		$tc_test_expect( get_post_status( $id ) === 'draft' && get_post_meta( $id, 'tc_release_status', true ) === 'coming-soon' && get_post_meta( $id, 'tc_access_url', true ) === '' && ! wp_get_object_terms( $id, 'tc_platform' ), 'seeded flagship remains a draft with unverified platform and no file' );
	}
	TC_Catalog::seed_flagship_drafts();
	$second_drafts = get_posts( array( 'post_type' => 'tc_config', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_tc_seeded_flagship' ) );
	$tc_test_expect( count( $second_drafts ) === count( $after_drafts ), 'flagship draft scaffolding is idempotent' );
	echo 'Passed ' . $tc_test_checks . ' catalogue assertions: seeding, access safety, REST validation, taxonomy filters, draft status, and malformed inputs.' . PHP_EOL;
} finally {
	$_GET = $tc_test_previous_get;
	$_POST = $tc_test_previous_post;
	wp_set_current_user( $tc_test_previous_user );
	$GLOBALS['wp_query'] = $tc_test_previous_query;
	$GLOBALS['wp_the_query'] = $tc_test_previous_main_query;
	foreach ( $tc_test_ids as $id ) { wp_delete_post( $id, true ); }
	foreach ( $tc_test_terms as $id ) { wp_delete_term( $id, 'tc_game' ); }
}
