<?php
/** Semantic catalogue queries on disposable localhost WordPress; fixtures are always removed. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost WordPress required.' );
}

$checks = 0; $created_posts = array(); $created_terms = array();
$original_get = $_GET; $original_query = $GLOBALS['wp_query']; $original_main = $GLOBALS['wp_the_query'];
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( 'Catalogue search: ' . $message ); } ++$checks;
};
$query_ids = static function ( array $filters, array $scope, array $context = array() ): array {
	$_GET = $filters;
	$query = new WP_Query(); $GLOBALS['wp_query'] = $query; $GLOBALS['wp_the_query'] = $query;
	$query->query( array_merge( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'fields' => 'ids', 'post__in' => $scope ), $context ) );
	$ids = array_map( 'intval', $query->posts ); sort( $ids ); return $ids;
};
$same_ids = static function ( array $ids, array $expected ): bool { sort( $expected ); return $ids === $expected; };

try {
	foreach ( array(
		'XIM Matrix Apex Legends' => array( 'apex-legends', 'xim-matrix' ),
		'xim-matrix-call-of-duty' => array( 'call-of-duty', 'xim-matrix' ),
		'Cronus Zen COD scripts' => array( 'call-of-duty', 'cronus-zen' ),
		'apex ximmatrix configs' => array( 'apex-legends', 'xim-matrix' ),
		'COD / cronuszen' => array( 'call-of-duty', 'cronus-zen' ),
		'XIM MATRIX FORTNITE' => array( 'fortnite', 'xim-matrix' ),
		'R6S Cronus Zen' => array( 'rainbow-six-siege', 'cronus-zen' ),
	) as $phrase => $expected ) {
		$intent = TC_Catalog::search_intent( $phrase );
		$assert( $intent['game'] === $expected[0] && $intent['device'] === $expected[1] && '' === $intent['text'], 'Device/game phrase resolves without requiring its words in a config title: ' . $phrase );
	}
	$intent = TC_Catalog::search_intent( 'XIM Matrix Apex Legends Predator S30 scripts' );
	$assert( 'apex-legends' === $intent['game'] && 'xim-matrix' === $intent['device'] && 'predator s30' === $intent['text'], 'Release name and season remain searchable after entity resolution.' );
	$intent = TC_Catalog::search_intent( 'Free configs' );
	$assert( 'free' === $intent['tier'] && '' === $intent['game'] && '' === $intent['device'] && '' === $intent['text'], 'Free phrase resolves to the existing access label.' );
	$intent = TC_Catalog::search_intent( 'apexian ximmatrixer notes' );
	$assert( '' === $intent['game'] && '' === $intent['device'] && 'apexian ximmatrixer notes' === $intent['text'], 'Entity aliases require whole words.' );
	$assert( 'Unknown Predator S31' === TC_Catalog::search_intent( 'Unknown Predator S31' )['text'], 'An unrecognized search remains unchanged.' );
	$assert( '' === TC_Catalog::search_intent( 'Apex Legends Call of Duty' )['game'], 'A query naming two games does not silently choose one.' );
	$assert( '' === TC_Catalog::search_intent( 'XIM Matrix Cronus Zen' )['device'], 'A query naming both devices remains ambiguous.' );
	$assert( array( 'game' => '', 'device' => '', 'tier' => '', 'text' => '' ) === TC_Catalog::search_intent( '' ), 'Empty search has no accidental taxonomy or tier filter.' );

	$term_result = wp_insert_term( 'Semantic Search Fixture Game', 'tc_game', array( 'slug' => 'tc-semantic-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	if ( is_wp_error( $term_result ) ) { throw new RuntimeException( $term_result->get_error_message() ); }
	$created_terms[] = (int) $term_result['term_id'];
	$custom_term = get_term( $term_result['term_id'], 'tc_game' );
	$assert( $custom_term->slug === TC_Catalog::search_intent( 'Semantic Search Fixture Game configs' )['game'], 'Existing owner-created game terms are searchable by their actual names.' );

	$fixtures = array(
		'apex_matrix' => array( 'apex-legends', 'xim-matrix', 'monthly', 'available', 'publish', 'Predator S30' ),
		'apex_cronus' => array( 'apex-legends', 'cronus-zen', 'free', 'available', 'publish', 'Predator S30' ),
		'cod_matrix' => array( 'call-of-duty', 'xim-matrix', 'free', 'available', 'publish', 'Predator S30' ),
		'apex_coming' => array( 'apex-legends', 'xim-matrix', 'monthly', 'coming-soon', 'publish', 'Future S31' ),
		'apex_archived' => array( 'apex-legends', 'xim-matrix', 'monthly', 'archived', 'publish', 'Predator S30' ),
		'apex_draft' => array( 'apex-legends', 'xim-matrix', 'monthly', 'available', 'draft', 'Predator S30' ),
	);
	$ids = array();
	foreach ( $fixtures as $key => $fixture ) {
		$id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => $fixture[4], 'post_title' => 'Semantic Fixture ' . $fixture[5], 'post_content' => 'Temporary release without game or device names in its searchable copy.', 'meta_input' => array( 'tc_tier' => $fixture[2], 'tc_release_status' => $fixture[3] ) ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$ids[ $key ] = $id; $created_posts[] = $id;
		foreach ( array( 'tc_game' => $fixture[0], 'tc_device' => $fixture[1] ) as $taxonomy => $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term instanceof WP_Term ) { throw new RuntimeException( 'Expected installed catalogue term: ' . $slug ); }
			wp_set_object_terms( $id, array( $term->term_id ), $taxonomy );
		}
	}
	$scope = array_values( $ids );
	$assert( $same_ids( $query_ids( array( 'config_search' => 'xim matrix apex legends' ), $scope ), array( $ids['apex_matrix'], $ids['apex_coming'] ) ), 'Actual phrase query uses game AND device taxonomy and retains visible previews, excluding archived and draft releases.' );
	$assert( $same_ids( $query_ids( array( 'config_search' => 'cronuszen apex' ), $scope ), array( $ids['apex_cronus'] ) ), 'Device alias selects actual Cronus releases whose titles omit the device name.' );
	$assert( $same_ids( $query_ids( array( 'config_search' => 'cod ximmatrix' ), $scope ), array( $ids['cod_matrix'] ) ), 'COD alias selects Call of Duty rather than the other game.' );
	$assert( $same_ids( $query_ids( array( 'config_search' => 'XIM Matrix Apex Legends Predator S30' ), $scope ), array( $ids['apex_matrix'] ) ), 'Remaining release/season text narrows the taxonomy results.' );
	$assert( $same_ids( $query_ids( array( 'config_search' => 'free configs' ), $scope ), array( $ids['apex_cronus'], $ids['cod_matrix'] ) ), 'Free search uses existing access metadata instead of requiring Free in the title.' );
	$assert( $same_ids( $query_ids( array( 'config_search' => 'xim matrix apex', 'device' => 'cronus-zen' ), $scope ), array( $ids['apex_cronus'] ) ), 'An explicit device selection has precedence over parsed device words.' );
	$assert( $same_ids( $query_ids( array( 'config_search' => 'xim matrix apex', 'game' => 'call-of-duty' ), $scope ), array( $ids['cod_matrix'] ) ), 'An explicit game selection has precedence over the parsed game.' );
	$assert( array() === $query_ids( array( 'config_search' => 'xim matrix cod' ), $scope, array( 'tc_game' => 'apex-legends' ) ), 'Semantic search never erases the current native game collection constraint.' );
	$assert( $same_ids( $query_ids( array( 'config_search' => array( 'bad' ) ), $scope ), array( $ids['apex_matrix'], $ids['apex_cronus'], $ids['cod_matrix'], $ids['apex_coming'] ) ), 'Array-shaped search input remains safely ignored without widening publication visibility.' );
	$_GET = array();
	$count_keys = array( '_tc_delivery_count_' . $ids['apex_matrix'], '_tc_delivery_count_' . $ids['apex_cronus'] );
	try {
		add_option( $count_keys[0], 12, '', false ); add_option( $count_keys[1], 2, '', false );
		$download_args = array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post__in' => array( $ids['apex_matrix'], $ids['apex_cronus'] ), 'posts_per_page' => 2, 'fields' => 'ids', '_tc_download_order' => true, '_tc_collection_query' => true );
		$secondary = new WP_Query( $download_args );
		$assert( array( $ids['apex_matrix'], $ids['apex_cronus'] ) === array_map( 'intval', $secondary->posts ), 'The explicitly marked collection query supports real download-count ordering.' );
		unset( $download_args['_tc_collection_query'] );
		$unrelated = new WP_Query( $download_args );
		$assert( ! str_contains( $unrelated->request, 'tc_delivery_counts' ), 'An unmarked secondary query does not acquire collection SQL joins.' );
		$download_args['_tc_collection_query'] = true; $download_args['post_type'] = 'page';
		$unrelated = new WP_Query( $download_args );
		$assert( ! str_contains( $unrelated->request, 'tc_delivery_counts' ), 'The internal marker does not apply config download ordering to another post type.' );
	} finally {
		foreach ( $count_keys as $key ) { delete_option( $key ); }
	}
	WP_CLI::success( sprintf( 'Catalogue semantic search: %d assertions passed.', $checks ) );
} finally {
	$_GET = $original_get; $GLOBALS['wp_query'] = $original_query; $GLOBALS['wp_the_query'] = $original_main;
	foreach ( $created_posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $created_terms as $id ) { wp_delete_term( $id, 'tc_game' ); }
}
