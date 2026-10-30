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
	$entry = array( 'loc' => 'https://example.invalid/fixture/' );
	$assert( false === TC_SEO::sitemap_entry( $entry, 'post', (object) array( 'ID' => $page_id ) ), 'Rank Math partial post rows honor stored noindex metadata.' );
	$assert( false === TC_SEO::sitemap_entry( false, 'post', (object) array( 'ID' => $page_id ) ), 'An entry already excluded by another provider filter stays excluded.' );
	$args = TC_SEO::sitemap_posts_query( array( 'post_type' => 'page', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 ), 'page' );
	$assert( ! in_array( $page_id, get_posts( $args ), true ), 'Noindex page is excluded from core sitemap query.' );

	$public_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'SEO Public Fixture', 'post_name' => 'tc-seo-public-fixture' ), true );
	if ( is_wp_error( $public_page_id ) ) { throw new RuntimeException( $public_page_id->get_error_message() ); }
	$created_posts[] = $public_page_id;
	$assert( $entry === TC_SEO::sitemap_entry( $entry, 'post', (object) array( 'ID' => $public_page_id, 'post_status' => 'draft' ) ), 'A partial provider row is resolved from the authoritative published post instead of trusting row properties.' );
	$assert( $entry === TC_Member_Area::sitemap_entry( $entry, 'post', (object) array( 'ID' => $public_page_id, 'post_type' => 'page', 'post_name' => 'account' ) ), 'A spoofed partial member slug cannot exclude an unrelated public post.' );

	// Legacy member URLs remain private even before an administrator resaves the page.
	$member_page = get_page_by_path( 'account', OBJECT, 'page' );
	if ( ! $member_page instanceof WP_Post ) {
		$member_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'account', 'post_title' => 'Member Profile' ), true );
		if ( is_wp_error( $member_id ) ) { throw new RuntimeException( $member_id->get_error_message() ); }
		$created_posts[] = $member_id;
		$member_page = get_post( $member_id );
	}
	$legacy_meta_filter = static function ( mixed $value, int $id, string $key ) use ( $member_page ): mixed {
		return $id === $member_page->ID && in_array( $key, array( 'tc_noindex', '_tc_member_page' ), true ) ? array( '' ) : $value;
	};
	add_filter( 'get_post_metadata', $legacy_meta_filter, 99, 3 );
	try {
		$assert( '' === get_post_meta( $member_page->ID, 'tc_noindex', true ), 'Legacy member fixture has no explicit noindex flag through reversible reads.' );
		$assert( false === TC_Member_Area::sitemap_entry( $entry, 'post', (object) array( 'ID' => $member_page->ID ) ), 'Rank Math partial rows exclude known legacy member slugs without relying on stored flags.' );
	} finally {
		remove_filter( 'get_post_metadata', $legacy_meta_filter, 99 );
	}

	if ( function_exists( 'wc_get_page_id' ) ) {
		$woo_page_ids = array_fill_keys( array( 'cart', 'checkout', 'myaccount', 'shop' ), -1 );
		$woo_page_filters = array();
		foreach ( array_keys( $woo_page_ids ) as $kind ) {
			$woo_page_filters[ $kind ] = static function () use ( &$woo_page_ids, $kind ): int { return $woo_page_ids[ $kind ]; };
			add_filter( 'pre_option_woocommerce_' . $kind . '_page_id', $woo_page_filters[ $kind ] );
		}
		try {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $kind ) {
				$woo_page_ids[ $kind ] = $public_page_id;
				$assert( TC_SEO::object_noindex( get_post( $public_page_id ) ), 'Mapped WooCommerce ' . $kind . ' page is noindex without an authored metadata flag.' );
				$assert( false === TC_SEO::sitemap_entry( $entry, 'post', (object) array( 'ID' => $public_page_id ) ), 'Mapped WooCommerce ' . $kind . ' page is excluded from partial provider sitemap rows.' );
				$woo_query_args = TC_SEO::sitemap_posts_query( array( 'post_type' => 'page', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'post__not_in' => array( $page_id ) ), 'page' );
				$assert( in_array( $page_id, $woo_query_args['post__not_in'], true ) && in_array( $public_page_id, $woo_query_args['post__not_in'], true ), 'Core sitemap exclusion for WooCommerce ' . $kind . ' preserves caller exclusions.' );
				$assert( ! in_array( $public_page_id, get_posts( $woo_query_args ), true ), 'The actual core sitemap page query excludes WooCommerce ' . $kind . ' without depending on stored noindex metadata.' );
				$navigate( array( 'page_id' => $public_page_id ) );
				$assert( 'noindex' === TC_SEO::provider_robots( array( 'index' => 'index' ) )['index'], 'Mapped WooCommerce ' . $kind . ' page emits matching provider noindex robots.' );
				$woo_page_ids[ $kind ] = -1;
			}
			$woo_page_ids['shop'] = $public_page_id;
			$assert( ! TC_SEO::object_noindex( get_post( $public_page_id ) ), 'The mapped public WooCommerce shop is not treated as a private utility page.' );
			$assert( $entry === TC_SEO::sitemap_entry( $entry, 'post', (object) array( 'ID' => $public_page_id ) ), 'The mapped public shop remains eligible for provider sitemaps.' );
			$shop_args = TC_SEO::sitemap_posts_query( array( 'post_type' => 'page', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 ), 'page' );
			$assert( in_array( $public_page_id, get_posts( $shop_args ), true ), 'The actual core sitemap query keeps the mapped public shop eligible.' );
			$config_args = array( 'post_type' => 'tc_config', 'post__not_in' => array( $page_id ) );
			$assert( array( $page_id ) === TC_SEO::sitemap_posts_query( $config_args, 'tc_config' )['post__not_in'], 'WooCommerce utility mapping does not alter non-page sitemap exclusions.' );
		} finally {
			foreach ( $woo_page_filters as $kind => $filter ) { remove_filter( 'pre_option_woocommerce_' . $kind . '_page_id', $filter ); }
		}
	}

	$term_result = wp_insert_term( 'SEO integration empty collection', 'tc_game', array( 'slug' => 'tc-seo-empty-fixture', 'description' => 'A clear description does not make an empty collection a released product catalogue.' ) );
	if ( is_wp_error( $term_result ) ) { throw new RuntimeException( $term_result->get_error_message() ); }
	$term_id = (int) $term_result['term_id'];
	$created_terms[] = $term_id;
	$assert( TC_SEO::object_noindex( get_term( $term_id, 'tc_game' ) ), 'Empty collection is noindex.' );
	$term_args = TC_SEO::sitemap_terms_query( array(), 'tc_game' );
	$assert( in_array( $term_id, $term_args['exclude'], true ), 'Empty collection is excluded from the XML sitemap.' );
	$assert( false === TC_SEO::sitemap_entry( array( 'loc' => 'example' ), 'term', get_term( $term_id, 'tc_game' ) ), 'External provider sitemap entry filter excludes empty collections.' );
	$assert( false === TC_SEO::sitemap_entry( $entry, 'term', (object) array( 'term_id' => $term_id, 'taxonomy' => 'tc_game', 'count' => 100 ) ), 'Partial provider term rows use the real empty collection instead of trusting a supplied count.' );

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
	update_post_meta( $config_id, 'tc_release_status', 'available' );
	wp_set_object_terms( $config_id, array( $term_id ), 'tc_game' );
	wp_update_term( $term_id, 'tc_game', array( 'description' => 'This integration collection describes a released configuration, its supported device, setup steps and compatibility details so visitors receive useful information before downloading.' ) );
	$assert( $entry === TC_SEO::sitemap_entry( $entry, 'term', (object) array( 'term_id' => $term_id, 'taxonomy' => 'tc_game' ) ), 'A real described collection with an available published configuration remains eligible for provider sitemaps.' );
	update_term_meta( $term_id, 'tc_noindex', true );
	$assert( false === TC_SEO::sitemap_entry( $entry, 'term', (object) array( 'term_id' => $term_id ) ), 'Partial term rows without taxonomy still honor explicit stored noindex metadata.' );
	delete_term_meta( $term_id, 'tc_noindex' );
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
