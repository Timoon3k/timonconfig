<?php
/** Run via WordPress: wp eval-file - < tests/seo-integration.php */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TC_SEO' ) ) {
	require_once WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-seo.php';
	TC_SEO::init();
	TC_SEO::register_metadata();
}

$checks = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
$created_posts = array();
$created_terms = array();
$original_user = get_current_user_id();
$original_get = $_GET;
$original_post = $_POST;
$original_query = $GLOBALS['wp_query'];
$original_main_query = $GLOBALS['wp_the_query'];
$navigate = static function ( array $query ): void {
	$GLOBALS['wp_query'] = new WP_Query( $query );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
};

try {
	$assert( '' === TC_SEO::sanitize( 'tc_seo_title', array( 'bad' ) ), 'Array-shaped metadata is rejected.' );
	$assert( 'Safe title' === TC_SEO::sanitize( 'tc_seo_title', '<script>bad()</script>Safe title' ), 'Metadata title strips unsafe markup.' );
	$assert( '' === TC_SEO::sanitize( 'tc_seo_image', 'javascript:alert(1)' ), 'Unsafe social image protocol is rejected.' );
	$assert( false === TC_SEO::sanitize( 'tc_noindex', 'false' ), 'Boolean false is preserved.' );

	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'SEO Integration Fixture', 'post_name' => 'tc-seo-integration-fixture' ), true );
	if ( is_wp_error( $page_id ) ) { throw new RuntimeException( $page_id->get_error_message() ); }
	$created_posts[] = $page_id;
	update_post_meta( $page_id, 'tc_seo_title', 'Fixture Search Title' );
	update_post_meta( $page_id, 'tc_seo_description', 'A unique, honest integration fixture description.' );
	update_post_meta( $page_id, 'tc_noindex', true );
	$assert( TC_SEO::object_noindex( get_post( $page_id ) ), 'Explicit noindex is recognized.' );
	$args = TC_SEO::sitemap_posts_query( array( 'post_type' => 'page', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 ), 'page' );
	$assert( ! in_array( $page_id, get_posts( $args ), true ), 'Noindex page is excluded from core sitemap query.' );

	$term_result = wp_insert_term( 'SEO integration empty collection', 'tc_game', array( 'slug' => 'tc-seo-empty-fixture', 'description' => 'A clear description does not make an empty collection a released product catalogue.' ) );
	if ( is_wp_error( $term_result ) ) { throw new RuntimeException( $term_result->get_error_message() ); }
	$term_id = (int) $term_result['term_id'];
	$created_terms[] = $term_id;
	$assert( TC_SEO::object_noindex( get_term( $term_id, 'tc_game' ) ), 'Empty collection is noindex.' );
	$term_args = TC_SEO::sitemap_terms_query( array(), 'tc_game' );
	$assert( in_array( $term_id, $term_args['exclude'], true ), 'Empty collection is excluded from the XML sitemap.' );
	$assert( false === TC_SEO::sitemap_entry( array( 'loc' => 'example' ), 'term', get_term( $term_id, 'tc_game' ) ), 'External provider sitemap entry filter excludes empty collections.' );

	wp_set_current_user( 0 );
	$_POST = array( 'tc_seo_nonce' => wp_create_nonce( 'tc_save_seo' ), 'tc_seo_title' => 'Unauthorized overwrite' );
	TC_SEO::save_post( $page_id );
	$assert( 'Fixture Search Title' === get_post_meta( $page_id, 'tc_seo_title', true ), 'Unauthenticated metadata writes are denied.' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
	$_POST = array( 'tc_seo_nonce' => 'invalid', 'tc_seo_title' => 'CSRF overwrite' );
	TC_SEO::save_post( $page_id );
	$assert( 'Fixture Search Title' === get_post_meta( $page_id, 'tc_seo_title', true ), 'Missing or invalid nonce is denied.' );

	$navigate( array( 'page_id' => $page_id ) );
	$assert( 'Fixture Search Title' === timonconfigs_seo_title(), 'Page title overrides are rendered.' );
	$assert( 'A unique, honest integration fixture description.' === timonconfigs_seo_description(), 'Page descriptions are rendered.' );
	$assert( TC_SEO::should_noindex(), 'Noindex applies to the actual public query.' );

	$navigate( array( 'post_type' => 'tc_config' ) );
	$_GET['tier'] = 'free';
	$assert( TC_SEO::should_noindex(), 'Filtered catalogue is noindex.' );
	$_GET = array( 'sort' => 'title' );
	$assert( TC_SEO::should_noindex(), 'Sorted catalogue variants remain noindex with the canonical library URL.' );
	$_GET = array();
	$navigate( array( 'page_id' => (int) get_option( 'page_on_front' ) ) );
	$graph = timonconfigs_schema_graph();
	$types = wp_list_pluck( $graph, '@type' );
	$assert( in_array( 'Organization', $types, true ), 'Home includes Organization schema.' );
	$assert( ! in_array( 'Review', $types, true ) && ! in_array( 'AggregateRating', $types, true ), 'Demonstration reviews have no review schema.' );
	foreach ( $graph as $node ) {
		if ( 'FAQPage' === $node['@type'] ) { $assert( count( $node['mainEntity'] ) <= 5, 'Home FAQ schema contains visible questions only.' ); }
	}

	$assert( ! timonconfigs_external_seo_active(), 'Test starts with the built-in fallback provider.' );
	define( 'AIOSEO_VERSION', 'integration-test' );
	$assert( timonconfigs_external_seo_active(), 'AIOSEO is detected.' );
	ob_start();
	timonconfigs_print_metadata();
	timonconfigs_print_schema();
	$head = ob_get_clean();
	$assert( ! str_contains( $head, 'property="og:title"' ) && ! str_contains( $head, 'name="twitter:card"' ) && ! str_contains( $head, 'application/ld+json' ), 'Fallback social tags and JSON-LD are suppressed when AIOSEO owns metadata.' );
	WP_CLI::success( sprintf( 'SEO integration: %d assertions passed.', $checks ) );
} finally {
	$_GET = $original_get;
	$_POST = $original_post;
	wp_set_current_user( $original_user );
	$GLOBALS['wp_query'] = $original_query;
	$GLOBALS['wp_the_query'] = $original_main_query;
	foreach ( $created_posts as $post_id ) { wp_delete_post( $post_id, true ); }
	foreach ( $created_terms as $term_id ) { wp_delete_term( $term_id, 'tc_game' ); }
}
