<?php
/** Run via WordPress: wp eval-file - < tests/seo-integration.php */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost WordPress required.' );
}

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
	$assert( false !== has_filter( 'rank_math/frontend/title', array( 'TC_SEO', 'rank_math_title' ) ) && false !== has_filter( 'rank_math/frontend/description', array( 'TC_SEO', 'rank_math_description' ) ), 'Metadata fallbacks use the active provider filters.' );
	$assert( 'Fixture Search Title' === apply_filters( 'rank_math/frontend/title', 'Provider default title' ), 'Rank Math receives the explicit TimonConfigs page title.' );
	$assert( 'A unique, honest integration fixture description.' === apply_filters( 'rank_math/frontend/description', 'Provider default description' ), 'Rank Math receives the explicit TimonConfigs page description.' );
	update_post_meta( $page_id, 'rank_math_title', 'Authored provider title with native variables' );
	$assert( 'Provider-rendered authored title' === apply_filters( 'rank_math/frontend/title', 'Provider-rendered authored title' ), 'Authored Rank Math title wins without replacing its native formatting.' );
	$assert( 'A unique, honest integration fixture description.' === apply_filters( 'rank_math/frontend/description', 'Provider default description' ), 'A Rank Math title does not disable the separate description fallback.' );
	update_post_meta( $page_id, 'rank_math_description', 'Authored provider description' );
	$assert( 'Provider-rendered authored description' === apply_filters( 'rank_math/frontend/description', 'Provider-rendered authored description' ), 'Authored Rank Math description wins independently.' );
	delete_post_meta( $page_id, 'rank_math_title' );
	delete_post_meta( $page_id, 'rank_math_description' );
	$GLOBALS['wp_query']->set( 'paged', 3 );
	$assert( 'Fixture Search Title — Page 3' === apply_filters( 'rank_math/frontend/title', 'Provider default — Page 3' ), 'Fallback titles preserve paginated context.' );
	update_post_meta( $page_id, 'tc_seo_title', 'Fixture Search Title — Page 3' );
	$assert( 'Fixture Search Title — Page 3' === apply_filters( 'rank_math/frontend/title', 'Provider default — Page 3' ), 'Already paginated source title is not suffixed twice.' );
	$assert( 'A unique, honest integration fixture description.' === apply_filters( 'rank_math/frontend/description', 'Provider default description' ), 'Description is not changed into pagination copy.' );
	update_post_meta( $page_id, 'tc_seo_title', 'Fixture Search Title' );
	$navigate( array( 'page_id' => $page_id ) );
	$assert( TC_SEO::should_noindex(), 'Metadata integration does not make a protected noindex page indexable.' );

	// Exercise a real static-front-page query with temporary read filters only.
	// Existing installation options and Contact metadata are never rewritten.
	$rank_math_options_before = get_option( 'rank-math-options-titles', null );
	$rank_math_options = array(); $front_mode = 'page';
	$metadata = array( $page_id => array( 'tc_seo_title' => '', 'tc_seo_description' => '', 'rank_math_title' => '', 'rank_math_description' => '' ) );
	$meta_filter = static function ( mixed $value, int $id, string $key ) use ( &$metadata ): mixed {
		return isset( $metadata[ $id ] ) && array_key_exists( $key, $metadata[ $id ] ) ? array( $metadata[ $id ][ $key ] ) : $value;
	};
	$front_filter = static function () use ( &$front_mode ): string { return $front_mode; };
	$page_filter = static fn(): int => $page_id;
	$options_filter = static function () use ( &$rank_math_options ): array { return $rank_math_options; };
	add_filter( 'get_post_metadata', $meta_filter, 99, 3 );
	add_filter( 'pre_option_show_on_front', $front_filter );
	add_filter( 'pre_option_page_on_front', $page_filter );
	add_filter( 'pre_option_rank-math-options-titles', $options_filter );
	try {
		$navigate( array( 'page_id' => $page_id ) );
		$assert( is_front_page() && is_page(), 'The provider contract is tested against an actual static-front-page query.' );
		$defaults = timonconfigs_seo_page_defaults();
		$assert( $defaults['home']['title'] === apply_filters( 'rank_math/frontend/title', 'Provider default title' ), 'Static Home uses the theme title when no authored metadata or global setting exists.' );
		$assert( $defaults['home']['description'] === apply_filters( 'rank_math/frontend/description', 'Provider default description' ), 'Static Home uses the theme description when nothing is authored.' );
		$rank_math_options = array( 'homepage_title' => 'Global Home Search Title', 'homepage_description' => 'Global Home search description.' );
		$assert( 'Global Home Search Title' === apply_filters( 'rank_math/frontend/title', 'Provider default title' ), 'The global Rank Math homepage title applies to static Home.' );
		$assert( 'Global Home search description.' === apply_filters( 'rank_math/frontend/description', 'Provider default description' ), 'The global homepage description applies to static Home.' );
		$metadata[ $page_id ]['tc_seo_title'] = 'Specific Home Title';
		$metadata[ $page_id ]['tc_seo_description'] = 'Specific Home description.';
		$assert( 'Specific Home Title' === apply_filters( 'rank_math/frontend/title', 'Provider default title' ), 'An explicit TimonConfigs Home title wins over global defaults.' );
		$assert( 'Specific Home description.' === apply_filters( 'rank_math/frontend/description', 'Provider default description' ), 'An explicit TimonConfigs Home description wins over global defaults.' );
		$metadata[ $page_id ]['rank_math_title'] = 'Authored Home provider title';
		$metadata[ $page_id ]['rank_math_description'] = 'Authored Home provider description';
		$assert( 'Provider authored Home title' === apply_filters( 'rank_math/frontend/title', 'Provider authored Home title' ), 'Per-page Rank Math title remains authoritative on Home.' );
		$assert( 'Provider authored Home description' === apply_filters( 'rank_math/frontend/description', 'Provider authored Home description' ), 'Per-page Rank Math description remains authoritative on Home.' );
		$metadata[ $page_id ] = array( 'tc_seo_title' => '', 'tc_seo_description' => '', 'rank_math_title' => '', 'rank_math_description' => '' );
		$rank_math_options['homepage_title'] = '%sitename% — %unknown_fixture_variable%';
		if ( ! is_callable( array( '\\RankMath\\Helper', 'replace_vars' ) ) ) {
			$assert( 'Provider-expanded Home title' === apply_filters( 'rank_math/frontend/title', 'Provider-expanded Home title' ), 'Missing provider variable engine does not leak unresolved template variables.' );
		}
		$front_mode = 'posts';
		$navigate( array( 'post_type' => 'post' ) );
		$assert( 'Provider posts Home title' === apply_filters( 'rank_math/frontend/title', 'Provider posts Home title' ), 'Posts Home keeps the provider title and its global handling.' );
		$assert( 'Provider posts Home description' === apply_filters( 'rank_math/frontend/description', 'Provider posts Home description' ), 'Posts Home keeps the provider description.' );

		$contact = get_page_by_path( 'contact', OBJECT, 'page' );
		if ( ! $contact instanceof WP_Post ) {
			$contact_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'contact', 'post_title' => 'Contact' ), true );
			if ( is_wp_error( $contact_id ) ) { throw new RuntimeException( $contact_id->get_error_message() ); }
			$created_posts[] = $contact_id; $contact = get_post( $contact_id );
		}
		$metadata[ $contact->ID ] = array( 'tc_seo_title' => '', 'tc_seo_description' => '', 'rank_math_title' => '', 'rank_math_description' => '' );
		$navigate( array( 'page_id' => $contact->ID ) );
		$assert( $defaults['contact']['title'] === apply_filters( 'rank_math/frontend/title', 'Provider Contact title' ), 'Contact uses its page-specific theme fallback title.' );
		$assert( $defaults['contact']['description'] === apply_filters( 'rank_math/frontend/description', 'Provider Contact description' ), 'Contact uses its page-specific theme fallback description.' );
		$metadata[ $contact->ID ]['rank_math_title'] = 'Authored Contact title';
		$assert( 'Provider authored Contact title' === apply_filters( 'rank_math/frontend/title', 'Provider authored Contact title' ), 'Authored Rank Math Contact title still wins.' );
	} finally {
		remove_filter( 'get_post_metadata', $meta_filter, 99 );
		remove_filter( 'pre_option_show_on_front', $front_filter );
		remove_filter( 'pre_option_page_on_front', $page_filter );
		remove_filter( 'pre_option_rank-math-options-titles', $options_filter );
	}
	$assert( $rank_math_options_before === get_option( 'rank-math-options-titles', null ), 'Tests and metadata filters leave stored Rank Math options unchanged.' );
	$assert( 'Fixture Search Title' === get_post_meta( $page_id, 'tc_seo_title', true ), 'Read filters do not overwrite existing page metadata.' );

	update_term_meta( $term_id, 'tc_seo_title', 'Fixture Game Collection Search Title' );
	update_term_meta( $term_id, 'tc_seo_description', 'Fixture game collection description.' );
	$navigate( array( 'tc_game' => 'tc-seo-empty-fixture' ) );
	$assert( 'Fixture Game Collection Search Title' === apply_filters( 'rank_math/frontend/title', 'Provider taxonomy title' ), 'Rank Math receives the explicit game taxonomy title.' );
	$assert( 'Fixture game collection description.' === apply_filters( 'rank_math/frontend/description', 'Provider taxonomy description' ), 'Rank Math receives the explicit game taxonomy description.' );
	update_term_meta( $term_id, 'rank_math_title', 'Authored taxonomy title' );
	update_term_meta( $term_id, 'rank_math_description', 'Authored taxonomy description' );
	$assert( 'Provider authored taxonomy title' === apply_filters( 'rank_math/frontend/title', 'Provider authored taxonomy title' ), 'Authored Rank Math taxonomy title wins.' );
	$assert( 'Provider authored taxonomy description' === apply_filters( 'rank_math/frontend/description', 'Provider authored taxonomy description' ), 'Authored Rank Math taxonomy description wins.' );
	$assert( TC_SEO::should_noindex(), 'An empty taxonomy stays noindex even after metadata is optimized.' );

	$config_id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => 'SEO Config Fixture', 'post_name' => 'tc-seo-config-fixture' ), true );
	if ( is_wp_error( $config_id ) ) { throw new RuntimeException( $config_id->get_error_message() ); }
	$created_posts[] = $config_id;
	update_post_meta( $config_id, 'tc_seo_title', 'Configuration Search Title' );
	update_post_meta( $config_id, 'tc_seo_description', 'Configuration search description.' );
	$navigate( array( 'post_type' => 'tc_config', 'p' => $config_id ) );
	$assert( 'Configuration Search Title' === apply_filters( 'rank_math/frontend/title', 'Provider config title' ), 'Configuration metadata is available to Rank Math.' );
	$assert( 'Configuration search description.' === apply_filters( 'rank_math/frontend/description', 'Provider config description' ), 'Configuration description is available to Rank Math.' );
	update_post_meta( $config_id, 'rank_math_title', 'Authored config title' );
	$assert( 'Provider authored config title' === apply_filters( 'rank_math/frontend/title', 'Provider authored config title' ), 'Authored Rank Math config metadata remains authoritative.' );

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
