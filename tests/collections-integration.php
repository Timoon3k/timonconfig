<?php
/** Real collection pages, catalogue intersections and indexing on disposable localhost only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost WordPress required.' );
}

$checks = 0; $created_posts = array(); $page_backups = array();
$original_get = $_GET; $original_post = $_POST;
$original_query = $GLOBALS['wp_query']; $original_main = $GLOBALS['wp_the_query'];
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( 'Collections: ' . $message ); } ++$checks;
};
$navigate = static function ( int $id ): void {
	$query = new WP_Query(); $GLOBALS['wp_query'] = $query; $GLOBALS['wp_the_query'] = $query;
	$query->query( array( 'page_id' => $id ) );
};
$ids_of = static fn( WP_Query $query ): array => array_map( static fn( mixed $post ): int => $post instanceof WP_Post ? $post->ID : (int) $post, $query->posts );
$backup_page = static function ( int $id ) use ( &$page_backups ): void {
	if ( isset( $page_backups[ $id ] ) ) { return; }
	$post = get_post( $id );
	$page_backups[ $id ] = array(
		'post' => array( 'ID' => $id, 'post_title' => $post->post_title, 'post_content' => $post->post_content, 'post_status' => $post->post_status, 'post_name' => $post->post_name, 'post_password' => $post->post_password ),
		'meta' => array_map( static fn( string $key ): array => array( 'key' => $key, 'exists' => metadata_exists( 'post', $id, $key ), 'value' => get_post_meta( $id, $key, true ) ), array( '_tc_collection', 'tc_seo_title', 'tc_seo_description', 'tc_noindex', 'rank_math_title', 'rank_math_description', 'rank_math_canonical_url', '_yoast_wpseo_canonical' ) ),
	);
};

// Bound all configuration reads to this test's releases; existing catalogue content is untouched.
$scope = array();
$scope_filter = static function ( WP_Query $query ) use ( &$scope ): void {
	if ( in_array( 'tc_config', (array) $query->get( 'post_type' ), true ) ) { $query->set( 'post__in', $scope ?: array( 0 ) ); }
};
$term_ids = array(); $term_noindex = array(); $short_terms = array();
$term_filter = static function ( mixed $term ) use ( &$term_ids, &$short_terms ): mixed {
	if ( $term instanceof WP_Term && in_array( $term->term_id, $term_ids, true ) ) {
		$term = clone $term;
		$term->description = ! empty( $short_terms[ $term->term_id ] ) ? 'Short.' : 'This collection documents the supported game, device, input method, platform and release version. Read the setup instructions and current compatibility notes on each published configuration before installing it.';
	}
	return $term;
};
$term_meta_filter = static function ( mixed $value, int $id, string $key ) use ( &$term_ids, &$term_noindex ): mixed {
	return 'tc_noindex' === $key && in_array( $id, $term_ids, true ) ? array( ! empty( $term_noindex[ $id ] ) ) : $value;
};

try {
	$assert( class_exists( 'TC_Collections' ), 'The collection module is loaded by Core.' );
	$installed = TC_Collections::install();
	if ( is_wp_error( $installed ) ) { throw new RuntimeException( $installed->get_error_message() ); }
	$assert( is_array( $installed ), 'The explicit local installer returns collection page IDs.' );
	$routes = array( 'xim-matrix-apex-legends', 'xim-matrix-call-of-duty', 'cronus-zen-apex-legends', 'cronus-zen-call-of-duty', 'free-configs' );
	$pages = array();
	foreach ( $routes as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		$assert( $page instanceof WP_Post && 'publish' === $page->post_status, 'A real published page exists at /' . $slug . '/.' );
		$pages[ $slug ] = $page->ID;
		$definition = TC_Collections::definition( $page );
		$assert( is_array( $definition ) && $definition['slug'] === $slug, 'The exact page is linked to its managed definition: ' . $slug );
		$assert( '' !== trim( (string) get_post_meta( $page->ID, 'tc_seo_title', true ) ) && '' !== trim( (string) get_post_meta( $page->ID, 'tc_seo_description', true ) ), 'The new page starts with editable search metadata: ' . $slug );
		$assert( '' !== trim( $page->post_content ), 'Useful public introduction is stored as editable content: ' . $slug );
	}
	$installed_ids = TC_Collections::page_ids(); sort( $installed_ids );
	TC_Collections::install();
	$again = TC_Collections::page_ids(); sort( $again );
	$assert( $installed_ids === $again, 'Repeated installation does not duplicate pages.' );
	$assert( get_permalink( $pages['xim-matrix-apex-legends'] ) === TC_Collections::url( 'apex-legends', 'xim-matrix' ), 'The device/game link resolves to the real page permalink.' );
	$assert( get_permalink( $pages['free-configs'] ) === TC_Collections::free_url(), 'Free search has a real canonical page.' );
	$assert( '' === TC_Collections::url( 'invented-game', 'xim-matrix' ), 'Unknown combinations do not invent public URLs.' );

	foreach ( array( 'tc_game' => array( 'apex-legends', 'call-of-duty' ), 'tc_device' => array( 'xim-matrix', 'cronus-zen' ) ) as $taxonomy => $slugs ) {
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			$assert( $term instanceof WP_Term, 'Expected existing catalogue term: ' . $slug );
			$term_ids[] = $term->term_id;
		}
	}
	add_filter( 'get_term', $term_filter, 99 );
	add_filter( 'get_term_metadata', $term_meta_filter, 99, 3 );
	add_action( 'pre_get_posts', $scope_filter, 99 );
	$make_config = static function ( string $title, string $game, string $device, string $tier = 'monthly', string $release = 'available', string $status = 'publish' ) use ( &$created_posts, &$scope ): int {
		$id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => $status, 'post_title' => $title, 'post_content' => 'Temporary compatibility fixture with no private script content.', 'meta_input' => array( 'tc_tier' => $tier, 'tc_release_status' => $release, 'tc_input_method' => 'controller' ) ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$created_posts[] = $id; $scope[] = $id;
		foreach ( array( 'tc_game' => $game, 'tc_device' => $device ) as $taxonomy => $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy ); wp_set_object_terms( $id, array( $term->term_id ), $taxonomy );
		}
		return $id;
	};
	$matrix_ids = array();
	for ( $number = 1; $number <= 13; ++$number ) { $matrix_ids[] = $make_config( sprintf( 'Collection Fixture %02d Predator S30', $number ), 'apex-legends', 'xim-matrix', 1 === $number ? 'free' : 'monthly' ); }
	$cronus = $make_config( 'Collection Fixture Other Device', 'apex-legends', 'cronus-zen', 'free' );
	$cod = $make_config( 'Collection Fixture Other Game', 'call-of-duty', 'xim-matrix', 'free' );
	$coming = $make_config( 'ZZ Collection Fixture Future', 'apex-legends', 'xim-matrix', 'monthly', 'coming-soon' );
	$only_preview = $make_config( 'Collection Fixture Preview Only', 'call-of-duty', 'cronus-zen', 'monthly', 'coming-soon' );
	$archived = $make_config( 'Collection Fixture Archived', 'apex-legends', 'xim-matrix', 'monthly', 'archived' );
	$draft = $make_config( 'Collection Fixture Draft', 'apex-legends', 'xim-matrix', 'monthly', 'available', 'draft' );
	$matrix = TC_Collections::definition( $pages['xim-matrix-apex-legends'] );
	$empty = TC_Collections::definition( $pages['cronus-zen-call-of-duty'] );
	$free = TC_Collections::definition( $pages['free-configs'] );
	$_GET = array( 'sort' => 'title' );
	$query = TC_Collections::query( $matrix ); $first_ids = $ids_of( $query );
	$assert( 14 === (int) $query->found_posts && 12 === count( $first_ids ), 'The actual page query intersects device and game and paginates the matching available/preview cards.' );
	$assert( ! array_intersect( $first_ids, array( $cronus, $cod, $archived, $draft ) ), 'Other devices/games, archived configs and drafts cannot leak into matching cards.' );
	$_GET['collection_page'] = '2'; $second = TC_Collections::query( $matrix ); $second_ids = $ids_of( $second );
	$assert( 2 === count( $second_ids ) && ! array_intersect( $first_ids, $second_ids ), 'Second page contains the remaining matching cards without duplicates.' );
	$assert( in_array( $coming, array_merge( $first_ids, $second_ids ), true ), 'Existing coming-soon preview visibility is retained without treating it as a released download.' );
	$_GET = array( 'config_search' => 'Predator S30', 'tier' => 'free' );
	$assert( array( $matrix_ids[0] ) === $ids_of( TC_Collections::query( $matrix ) ), 'Release text and access filter narrow a fixed game/device collection.' );
	$_GET = array( 'device' => 'cronus-zen', 'game' => 'call-of-duty' );
	$assert( ! array_intersect( $ids_of( TC_Collections::query( $matrix ) ), array( $cronus, $cod ) ), 'Conflicting query parameters never widen the collection to another game/device.' );
	$_GET = array(); $free_ids = $ids_of( TC_Collections::query( $free ) ); sort( $free_ids );
	$expected_free = array( $matrix_ids[0], $cronus, $cod ); sort( $expected_free );
	$assert( $expected_free === $free_ids, 'Free page contains published Free configurations across both devices and games.' );
	$assert( TC_Collections::has_configs( $matrix ) && TC_Collections::is_indexable( $matrix ), 'The described real intersection with released configs is indexable.' );
	$assert( array( $only_preview ) === $ids_of( TC_Collections::query( $empty ) ), 'A preview-only intersection remains visible as preparation rather than inventing a released download.' );
	$assert( ! TC_Collections::has_configs( $empty ) && ! TC_Collections::is_indexable( $empty ), 'A game and device each having releases does not make their preview-only intersection indexable.' );
	$assert( TC_SEO::object_noindex( get_post( $pages['cronus-zen-call-of-duty'] ) ), 'Empty collection emits the same noindex decision as sitemap eligibility.' );
	$entry = array( 'loc' => get_permalink( $pages['xim-matrix-apex-legends'] ) );
	$assert( $entry === TC_SEO::sitemap_entry( $entry, 'post', (object) array( 'ID' => $pages['xim-matrix-apex-legends'] ) ), 'Rank Math partial sitemap rows retain an eligible collection.' );
	$assert( false === TC_SEO::sitemap_entry( $entry, 'post', (object) array( 'ID' => $pages['cronus-zen-call-of-duty'] ) ), 'Rank Math partial sitemap rows exclude the empty intersection.' );
	$args = TC_SEO::sitemap_posts_query( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ), 'page' );
	$map_ids = get_posts( $args );
	$assert( in_array( $pages['xim-matrix-apex-legends'], $map_ids, true ) && ! in_array( $pages['cronus-zen-call-of-duty'], $map_ids, true ), 'Actual native sitemap query retains the released page and excludes the empty page.' );
	$term_noindex[ $matrix['device_term']->term_id ] = true;
	$assert( ! TC_Collections::is_indexable( $matrix ), 'A device explicitly excluded by its owner also excludes its combined collection.' );
	$term_noindex = array();
	$short_terms[ $matrix['game_term']->term_id ] = true;
	$assert( ! TC_Collections::is_indexable( TC_Collections::definition( $pages['xim-matrix-apex-legends'] ) ), 'A short parent description does not bypass the existing collection quality guard.' );
	$short_terms = array();
	$navigate( $pages['xim-matrix-apex-legends'] ); $_GET = array();
	$assert( is_array( TC_Collections::current() ) && TC_Collections::current()['slug'] === $matrix['slug'], 'A real main-page request identifies the matching managed collection.' );
	$assert( ! TC_SEO::should_noindex(), 'Base released collection has indexable robots.' );
	$_GET = array( 'input' => 'controller' );
	$assert( TC_SEO::should_noindex(), 'Additional filter variants remain noindex.' );
	$_GET = array( 'collection_page' => '2' );
	$assert( ! TC_SEO::should_noindex(), 'Valid pagination is not confused with a filtered variant.' );
	$assert( 2 === TC_Collections::page_number() && add_query_arg( 'collection_page', 2, get_permalink( $pages['xim-matrix-apex-legends'] ) ) === TC_Collections::canonical_url(), 'Pagination has its own self-canonical instead of canonicalizing distinct results to page one.' );
	$assert( str_contains( apply_filters( 'rank_math/frontend/title', 'Provider default title' ), 'Page 2' ), 'Provider metadata preserves the collection page number.' );
	$matrix_id = $pages['xim-matrix-apex-legends']; $backup_page( $matrix_id );
	update_post_meta( $matrix_id, 'rank_math_canonical_url', 'https://example.invalid/owner-authored-canonical/' );
	$assert( 'Provider authored canonical' === apply_filters( 'rank_math/frontend/canonical', 'Provider authored canonical' ), 'An explicit authored Rank Math canonical remains under the provider control.' );
	update_post_meta( $matrix_id, '_yoast_wpseo_canonical', 'https://example.invalid/owner-authored-yoast-canonical/' );
	$assert( 'Yoast authored canonical' === apply_filters( 'wpseo_canonical', 'Yoast authored canonical' ), 'An explicit authored Yoast canonical is not replaced by collection pagination.' );
	foreach ( $page_backups[ $matrix_id ]['meta'] as $meta ) {
		if ( in_array( $meta['key'], array( 'rank_math_canonical_url', '_yoast_wpseo_canonical' ), true ) ) { $meta['exists'] ? update_post_meta( $matrix_id, $meta['key'], $meta['value'] ) : delete_post_meta( $matrix_id, $meta['key'] ); }
	}
	wp_update_post( array( 'ID' => $matrix_id, 'post_password' => 'localhost-fixture-password' ) );
	$assert( ! TC_Collections::is_indexable( TC_Collections::definition( $matrix_id ) ) && TC_SEO::object_noindex( get_post( $matrix_id ) ), 'Password-protected managed pages remain noindex even when their configuration intersection has releases.' );
	$assert( false === TC_SEO::sitemap_entry( $entry, 'post', (object) array( 'ID' => $matrix_id ) ), 'A password-protected collection is excluded from provider sitemap output.' );
	wp_update_post( array( 'ID' => $matrix_id, 'post_password' => $page_backups[ $matrix_id ]['post']['post_password'] ) );
	$_GET = array( 'config_search' => 'xim matrix apex legends' );
	$archive = new WP_Query(); $GLOBALS['wp_query'] = $archive; $GLOBALS['wp_the_query'] = $archive;
	$archive->query( array( 'post_type' => 'tc_config', 'post_status' => 'publish' ) );
	$assert( TC_Collections::url( 'apex-legends', 'xim-matrix' ) === TC_Collections::search_destination(), 'Submitting the complete topical search selects its real collection URL.' );
	$_GET = array( 'config_search' => 'XIM Matrix Apex Legends Predator S30', 'input' => 'mnk', 'tier' => 'monthly', 'sort' => 'title' );
	$destination = TC_Collections::search_destination(); $parameters = array(); wp_parse_str( (string) wp_parse_url( $destination, PHP_URL_QUERY ), $parameters );
	$assert( wp_parse_url( TC_Collections::url( 'apex-legends', 'xim-matrix' ), PHP_URL_PATH ) === wp_parse_url( $destination, PHP_URL_PATH ) && array( 'config_search' => 'predator s30', 'input' => 'mnk', 'tier' => 'monthly', 'sort' => 'title' ) === $parameters, 'Topical redirect preserves only the remaining release search and supported narrowing filters.' );
	$_GET = array( 'config_search' => 'free configs' );
	$assert( TC_Collections::free_url() === TC_Collections::search_destination(), 'Free phrase routes to its clean page instead of a duplicate tier query.' );
	$_GET = array( 'config_search' => 'xim matrix apex', 'game' => 'call-of-duty' );
	$assert( TC_Collections::url( 'call-of-duty', 'xim-matrix' ) === TC_Collections::search_destination(), 'The selected game determines the destination rather than conflicting words in the search phrase.' );
	$platform = get_term_by( 'slug', 'pc', 'tc_platform' );
	$assert( $platform instanceof WP_Term, 'The installed PC platform collection exists.' );
	wp_set_object_terms( $matrix_ids[0], array( $platform->term_id ), 'tc_platform' );
	$_GET = array( 'config_search' => 'xim matrix apex legends' );
	$platform_query = new WP_Query(); $GLOBALS['wp_query'] = $platform_query; $GLOBALS['wp_the_query'] = $platform_query;
	$platform_query->query( array( 'post_type' => 'tc_config', 'tc_platform' => 'pc' ) );
	$assert( add_query_arg( 'platform', 'pc', TC_Collections::url( 'apex-legends', 'xim-matrix' ) ) === TC_Collections::search_destination(), 'Topical search from a native platform collection retains that platform in its canonical destination.' );
	$contact = get_page_by_path( 'contact', OBJECT, 'page' );
	if ( $contact instanceof WP_Post ) {
		$navigate( $contact->ID );
		$assert( '' === TC_Collections::search_destination(), 'The search router cannot redirect an unrelated authored page.' );
	}

	// Existing author edits and statuses survive another explicit provisioning pass.
	$_GET = array(); $authored_id = $pages['xim-matrix-call-of-duty']; $backup_page( $authored_id );
	wp_update_post( array( 'ID' => $authored_id, 'post_title' => 'Owner collection title', 'post_content' => '<!-- wp:paragraph --><p>Owner-authored collection guidance.</p><!-- /wp:paragraph -->', 'post_status' => 'draft' ) );
	update_post_meta( $authored_id, 'tc_seo_title', 'Owner search title' );
	update_post_meta( $authored_id, 'rank_math_description', 'Owner Rank Math description.' );
	TC_Collections::install();
	$assert( 'draft' === get_post_status( $authored_id ) && 'Owner collection title' === get_the_title( $authored_id ) && str_contains( get_post_field( 'post_content', $authored_id ), 'Owner-authored' ), 'Provisioning never republishes a draft or replaces the owner title/content.' );
	$assert( 'Owner search title' === get_post_meta( $authored_id, 'tc_seo_title', true ) && 'Owner Rank Math description.' === get_post_meta( $authored_id, 'rank_math_description', true ), 'Provisioning leaves authored theme/provider metadata untouched.' );
	$assert( ! TC_Collections::is_indexable( TC_Collections::definition( $authored_id ) ), 'Unpublished managed collection cannot be indexable.' );
	$unmanaged_id = $pages['cronus-zen-call-of-duty']; $backup_page( $unmanaged_id );
	delete_post_meta( $unmanaged_id, '_tc_collection' );
	wp_update_post( array( 'ID' => $unmanaged_id, 'post_content' => '<p>Existing unrelated page at this URL.</p>' ) );
	TC_Collections::install();
	$assert( ! metadata_exists( 'post', $unmanaged_id, '_tc_collection' ) && null === TC_Collections::definition( $unmanaged_id ), 'An existing unmanaged slug is not silently adopted as a new collection.' );
	$assert( '<p>Existing unrelated page at this URL.</p>' === get_post_field( 'post_content', $unmanaged_id ), 'The owner page occupying a requested URL remains intact.' );
	WP_CLI::success( sprintf( 'Collections integration: %d assertions passed.', $checks ) );
} finally {
	remove_action( 'pre_get_posts', $scope_filter, 99 );
	remove_filter( 'get_term', $term_filter, 99 );
	remove_filter( 'get_term_metadata', $term_meta_filter, 99 );
	foreach ( $page_backups as $id => $backup ) {
		wp_update_post( $backup['post'] );
		foreach ( $backup['meta'] as $meta ) { $meta['exists'] ? update_post_meta( $id, $meta['key'], $meta['value'] ) : delete_post_meta( $id, $meta['key'] ); }
	}
	foreach ( $created_posts as $id ) { wp_delete_post( $id, true ); }
	$_GET = $original_get; $_POST = $original_post;
	$GLOBALS['wp_query'] = $original_query; $GLOBALS['wp_the_query'] = $original_main;
}
