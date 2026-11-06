<?php
/** Native per-object Rank Math fields; disposable localhost, reversible fixtures only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Rank_Math_Pages' ) ) { throw new RuntimeException( 'Load the page metadata module first.' ); }

$checks = 0; $posts = array(); $terms = array(); $http_calls = 0; $shortcode_runs = 0;
$old_user = get_current_user_id(); $old_method = $_SERVER['REQUEST_METHOD'] ?? null; $old_post = $_POST; $old_request = $_REQUEST;
$old_get = $_GET; $old_query = $GLOBALS['wp_query']; $old_main = $GLOBALS['wp_the_query'];
$options = array();
foreach ( array( 'rank-math-options-titles', 'tc_rank_math_archive_backup', 'tc_rank_math_archive_managed', 'timonconfigs_page_seo_state', 'timonconfigs_page_seo_queue', '_tc_page_seo_lock' ) as $key ) {
	$options[ $key ] = array( 'exists' => false !== get_option( $key, false ), 'value' => get_option( $key, false ) );
}
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Rank Math pages: ' . $message ); } ++$checks; };
$create = static function ( string $type, string $title, string $content = '', array $extra = array() ) use ( &$posts ): int {
	$id = wp_insert_post( wp_slash( array_merge( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'tc-page-seo-' . wp_generate_uuid4(), 'post_content' => $content ), $extra ) ), true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$posts[] = (int) $id; return (int) $id;
};
$create_term = static function ( string $taxonomy, string $name ) use ( &$terms ): int {
	$result = wp_insert_term( $name, $taxonomy, array( 'slug' => 'tc-page-seo-' . wp_generate_uuid4() ) );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
	$terms[] = array( (int) $result['term_id'], $taxonomy ); return (int) $result['term_id'];
};
$native = static fn( int $id ): array => array_map( static fn( string $key ): mixed => get_post_meta( $id, $key, true ), array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' ) );
$no_http = static function () use ( &$http_calls ): WP_Error { ++$http_calls; return new WP_Error( 'tc_page_seo_fixture', 'No external traffic allowed in metadata fixtures.' ); };
$front_id = 0;
$front_mode = static fn(): string => 'page';
$front_page = static function () use ( &$front_id ): int { return $front_id; };
$woo_id = 0;
$woo_page = static function () use ( &$woo_id ): int { return $woo_id; };
$term_alias_id = 0;
$term_alias = static function ( mixed $term ) use ( &$term_alias_id ): mixed {
	if ( $term instanceof WP_Term && $term->term_id === $term_alias_id ) { $term = clone $term; $term->slug = 'xim-matrix'; $term->name = 'XIM Matrix'; }
	return $term;
};
$navigate = static function ( array $args ): void { $GLOBALS['wp_query'] = new WP_Query( $args ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; };
$restore_failure_id = 0;
$block_restore = static function ( mixed $check, int $id, string $key ) use ( &$restore_failure_id ): mixed { return $id === $restore_failure_id && 'rank_math_title' === $key ? false : $check; };
$die_handler = static fn(): callable => static function ( mixed $message ): void { throw new RuntimeException( 'LOCAL QA rejected request: ' . wp_strip_all_tags( (string) $message ) ); };
add_filter( 'pre_http_request', $no_http, 1 );
add_shortcode( 'tc_page_seo_fixture', static function () use ( &$shortcode_runs ): string { ++$shortcode_runs; return 'PRIVATE_RENDERED_SHORTCODE'; } );
try {
	$data = TC_Rank_Math_Pages::catalogue(); $profiles = array();
	foreach ( array( 'pages', 'games', 'devices', 'archive', 'utility', 'platforms' ) as $section ) {
		$assert( isset( $data[ $section ] ) && is_array( $data[ $section ] ), 'The reviewed catalogue includes ' . $section . '.' );
		foreach ( $data[ $section ] as $slug => $profile ) {
			$profiles[] = $profile;
			$assert( isset( $profile['title'], $profile['description'], $profile['focus_keyword'] ) && '' !== trim( $profile['focus_keyword'] ), $section . '/' . $slug . ' has all three editor fields.' );
			$assert( str_contains( strtolower( $profile['title'] ), strtolower( $profile['focus_keyword'] ) ) && str_contains( strtolower( $profile['description'] ), strtolower( $profile['focus_keyword'] ) ), $section . '/' . $slug . ' uses its own primary phrase naturally in title and description.' );
			$assert( strlen( $profile['title'] ) <= 65 && strlen( $profile['description'] ) <= 165 && $profile['title'] === wp_strip_all_tags( $profile['title'] ) && $profile['description'] === wp_strip_all_tags( $profile['description'] ), $section . '/' . $slug . ' has concise plain-text metadata.' );
		}
	}
	$assert( 46 === count( $profiles ), 'The release contains 46 reviewed profiles.' );
	$assert( count( $profiles ) === count( array_unique( array_column( $profiles, 'title' ) ) ) && count( $profiles ) === count( array_unique( array_column( $profiles, 'description' ) ) ), 'Reviewed titles and descriptions are distinct across routes.' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$assert( ! empty( $admins ), 'An existing local administrator is available.' ); wp_set_current_user( (int) $admins[0] );

	$id = $create( 'page', 'LOCAL QA metadata front page', '<p>LOCAL QA public page body remains intact.</p>' );
	$original = array( 'rank_math_title' => 'LOCAL QA previous title', 'rank_math_description' => 'LOCAL QA previous description.', 'rank_math_focus_keyword' => 'LOCAL QA previous phrase' );
	foreach ( $original as $key => $value ) { update_post_meta( $id, $key, $value ); }
	update_post_meta( $id, 'rank_math_robots', array( 'noindex', 'nofollow' ) ); update_post_meta( $id, 'rank_math_canonical_url', 'https://example.invalid/authored-canonical/' ); update_post_meta( $id, 'tc_noindex', true ); update_post_meta( $id, 'rank_math_schema_test', array( '@type' => 'WebPage', 'name' => 'LOCAL QA authored schema' ) );
	$untouched = get_post( $id )->to_array(); $front_id = $id;
	add_filter( 'pre_option_show_on_front', $front_mode ); add_filter( 'pre_option_page_on_front', $front_page );
	$profile = TC_Rank_Math_Pages::profile( get_post( $id ) );
	$assert( ! empty( $profile['curated'] ) && $data['pages']['home']['title'] === $profile['title'], 'The mapped static front page receives Home copy despite its custom slug.' );
	$result = TC_Rank_Math_Pages::apply_post( $id );
	$assert( ! is_wp_error( $result ) && array( $profile['title'], $profile['description'], $profile['focus_keyword'] ) === $native( $id ), 'Known pages receive actual native Rank Math fields, including the focus phrase.' );
	$backup = get_post_meta( $id, '_tc_rank_math_page_backup', true );
	$assert( is_array( $backup ) && str_contains( wp_json_encode( $backup ), $original['rank_math_title'] ) && str_contains( wp_json_encode( $backup ), $original['rank_math_description'] ) && str_contains( wp_json_encode( $backup ), $original['rank_math_focus_keyword'] ), 'The first change records the previous native values.' );
	$assert( $untouched === get_post( $id )->to_array() && array( 'noindex', 'nofollow' ) === get_post_meta( $id, 'rank_math_robots', true ) && true === (bool) get_post_meta( $id, 'tc_noindex', true ) && 'https://example.invalid/authored-canonical/' === get_post_meta( $id, 'rank_math_canonical_url', true ) && array( '@type' => 'WebPage', 'name' => 'LOCAL QA authored schema' ) === get_post_meta( $id, 'rank_math_schema_test', true ), 'SEO copy changes no content, status, robots, canonical or schema.' );
	TC_Rank_Math_Pages::apply_post( $id );
	$assert( $backup === get_post_meta( $id, '_tc_rank_math_page_backup', true ) && array( $profile['title'], $profile['description'], $profile['focus_keyword'] ) === $native( $id ), 'Repeated application is idempotent and preserves the original backup.' );
	update_post_meta( $id, 'rank_math_title', 'LOCAL QA later owner title' ); TC_Rank_Math_Pages::apply_post( $id );
	$assert( 'LOCAL QA later owner title' === get_post_meta( $id, 'rank_math_title', true ), 'A later manual Rank Math edit is retained.' );
	$result = TC_Rank_Math_Pages::restore( 'post', $id );
	$assert( ! is_wp_error( $result ) && 'LOCAL QA later owner title' === get_post_meta( $id, 'rank_math_title', true ) && $original['rank_math_description'] === get_post_meta( $id, 'rank_math_description', true ) && $original['rank_math_focus_keyword'] === get_post_meta( $id, 'rank_math_focus_keyword', true ), 'Restore preserves manual edits while restoring fields still managed by the release.' );
	TC_Rank_Math_Pages::apply_post( $id );
	$assert( $original['rank_math_description'] === get_post_meta( $id, 'rank_math_description', true ), 'An explicitly restored profile is not silently applied again.' );
	remove_filter( 'pre_option_show_on_front', $front_mode ); remove_filter( 'pre_option_page_on_front', $front_page );

	$collection = $create( 'page', 'LOCAL QA collection', 'LOCAL QA collection introduction.' ); update_post_meta( $collection, '_tc_collection', 'xim-matrix-apex-legends' );
	TC_Rank_Math_Pages::apply_post( $collection );
	$assert( $data['pages']['xim-matrix-apex-legends']['focus_keyword'] === get_post_meta( $collection, 'rank_math_focus_keyword', true ), 'A marked collection receives its precise game and device phrase.' );
	$managed_title = get_post_meta( $collection, 'rank_math_title', true ); $_GET['collection_page'] = '2';
	$navigate( array( 'page_id' => $collection ) );
	$assert( is_page( $collection ) && TC_Rank_Math_Pages::manages_title( get_queried_object() ) && $managed_title . ' — Page 2' === apply_filters( 'rank_math/frontend/title', $managed_title ), 'A real collection_page=2 query receives pagination through the managed native Rank Math title.' );
	$assert( $managed_title . ' — Page 2' === apply_filters( 'rank_math/frontend/title', $managed_title . ' — Page 2' ), 'A provider title already carrying Page 2 is not suffixed twice.' );
	update_post_meta( $collection, 'rank_math_title', 'LOCAL QA owner collection title' );
	$assert( ! TC_Rank_Math_Pages::manages_title( get_queried_object() ) && 'LOCAL QA owner collection title' === apply_filters( 'rank_math/frontend/title', 'LOCAL QA owner collection title' ), 'A manually corrected native collection title remains authoritative on page 2.' );
	update_post_meta( $collection, 'rank_math_title', $managed_title ); $_GET = $old_get; $GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main;
	TC_Rank_Math_Pages::restore( 'post', $collection );
	$assert( ! metadata_exists( 'post', $collection, 'rank_math_title' ) && ! metadata_exists( 'post', $collection, 'rank_math_description' ) && ! metadata_exists( 'post', $collection, 'rank_math_focus_keyword' ), 'Restore distinguishes previously absent native fields from empty authored values.' );

	$unknown = $create( 'page', 'LOCAL QA Custom Installation Guide', 'PRIVATE_FULL_POST_CONTENT [tc_page_seo_fixture]', array( 'post_excerpt' => 'LOCAL QA public installation instructions for this authored guide.' ) );
	update_post_meta( $unknown, 'rank_math_title', 'LOCAL QA existing custom search title' ); update_post_meta( $unknown, 'rank_math_focus_keyword', 'LOCAL QA custom primary phrase' );
	$profile = TC_Rank_Math_Pages::profile( get_post( $unknown ) ); TC_Rank_Math_Pages::apply_post( $unknown );
	$assert( empty( $profile['curated'] ) && str_contains( $profile['description'], 'public installation instructions' ) && ! str_contains( wp_json_encode( $profile ), 'PRIVATE_FULL_POST_CONTENT' ) && 0 === $shortcode_runs, 'An unknown page uses its public excerpt without reading full content or executing shortcodes.' );
	$assert( 'LOCAL QA existing custom search title' === get_post_meta( $unknown, 'rank_math_title', true ) && 'LOCAL QA custom primary phrase' === get_post_meta( $unknown, 'rank_math_focus_keyword', true ) && '' !== get_post_meta( $unknown, 'rank_math_description', true ), 'Unknown authored pages keep existing native fields while missing metadata is filled.' );
	$password = $create( 'page', 'LOCAL QA Protected Notes', 'PRIVATE_PROTECTED_BODY', array( 'post_password' => 'local-qa-only', 'post_excerpt' => 'PRIVATE_PROTECTED_EXCERPT' ) );
	$profile = TC_Rank_Math_Pages::profile( get_post( $password ) );
	$assert( ! str_contains( wp_json_encode( $profile ), 'PRIVATE_PROTECTED' ), 'Password-protected excerpts are not copied into public metadata.' );

	$config = $create( 'tc_config', 'LOCAL QA Lunar Release', 'PRIVATE_CONFIGURATION_BODY [tc_page_seo_fixture]', array( 'post_excerpt' => 'PRIVATE_CONFIGURATION_EXCERPT' ) );
	update_post_meta( $config, '_tc_values_content', 'PRIVATE_VALUES_DO_NOT_COPY' ); update_post_meta( $config, '_tc_private_files', 'PRIVATE_FILE_PATH_DO_NOT_COPY' );
	$game = $create_term( 'tc_game', 'LOCAL QA Lunar Game' ); $device = $create_term( 'tc_device', 'LOCAL QA Lunar Device' );
	wp_set_object_terms( $config, array( $game ), 'tc_game' ); wp_set_object_terms( $config, array( $device ), 'tc_device' );
	$profile = TC_Rank_Math_Pages::profile( get_post( $config ) ); TC_Rank_Math_Pages::apply_post( $config );
	$assert( str_contains( wp_json_encode( $profile ), 'LOCAL QA Lunar Release' ) && ! str_contains( wp_json_encode( $profile ), 'PRIVATE_' ) && 0 === $shortcode_runs, 'Individual configuration SEO uses actual release data without exposing Values, files, body, excerpts or rendered shortcodes.' );
	$assert( '' !== get_post_meta( $config, 'rank_math_title', true ) && '' !== get_post_meta( $config, 'rank_math_description', true ) && '' !== get_post_meta( $config, 'rank_math_focus_keyword', true ), 'Each actual configuration receives its own complete native metadata.' );
	$sample = clone get_post( $unknown ); $sample->post_name = 'sample-page'; $assert( array() === TC_Rank_Math_Pages::profile( $sample ), 'The default WordPress Sample Page is not promoted as authored SEO content.' );
	$sample->post_type = 'post'; $sample->post_name = 'hello-world'; $assert( array() === TC_Rank_Math_Pages::profile( $sample ), 'The default Hello World post is not promoted.' );
	$sample->post_type = 'tc_review'; $sample->post_name = 'local-qa-review'; $assert( array() === TC_Rank_Math_Pages::profile( $sample ), 'Non-public testimonial objects do not receive standalone page metadata.' );
	if ( post_type_exists( 'product' ) ) {
		$product = $create( 'product', 'LOCAL QA Custom Membership', 'PRIVATE_PRODUCT_BODY', array( 'post_excerpt' => 'LOCAL QA public membership overview.' ) );
		TC_Rank_Math_Pages::apply_post( $product );
		$assert( '' !== get_post_meta( $product, 'rank_math_title', true ) && '' !== get_post_meta( $product, 'rank_math_description', true ) && '' !== get_post_meta( $product, 'rank_math_focus_keyword', true ), 'Public product pages also receive separate native metadata without invented prices.' );
	}

	if ( function_exists( 'wc_get_page_id' ) ) {
		$woo_id = $create( 'page', 'LOCAL QA Platnosc', '[woocommerce_checkout]' ); add_filter( 'pre_option_woocommerce_checkout_page_id', $woo_page );
		$profile = TC_Rank_Math_Pages::profile( get_post( $woo_id ) ); TC_Rank_Math_Pages::apply_post( $woo_id );
		$assert( ! empty( $profile['curated'] ) && $data['utility']['checkout']['title'] === get_post_meta( $woo_id, 'rank_math_title', true ), 'The actual Woo checkout mapping works for a translated or custom slug.' );
		$assert( TC_SEO::object_noindex( get_post( $woo_id ) ), 'The payment page remains excluded from search indexing.' );
		remove_filter( 'pre_option_woocommerce_checkout_page_id', $woo_page );
	}
	$member = $create( 'page', 'LOCAL QA Profile', '[timonconfigs_member_area]' );
	$profile = TC_Rank_Math_Pages::profile( get_post( $member ) );
	$assert( ! empty( $profile['curated'] ) && $data['utility']['account']['title'] === $profile['title'], 'Member shortcodes identify account pages without relying on an English slug.' );

	$term_alias_id = $device; add_filter( 'get_term', $term_alias );
	update_term_meta( $device, 'rank_math_title', 'LOCAL QA original term title' ); update_term_meta( $device, 'rank_math_robots', array( 'noindex' ) ); update_term_meta( $device, 'tc_noindex', true );
	$result = TC_Rank_Math_Pages::apply_term( $device );
	$assert( ! is_wp_error( $result ) && $data['devices']['xim-matrix']['title'] === get_term_meta( $device, 'rank_math_title', true ) && $data['devices']['xim-matrix']['focus_keyword'] === get_term_meta( $device, 'rank_math_focus_keyword', true ), 'Game/device/platform metadata is saved in native Rank Math term fields.' );
	$assert( array( 'noindex' ) === get_term_meta( $device, 'rank_math_robots', true ) && get_term_meta( $device, 'tc_noindex', true ), 'Term indexing restrictions remain unchanged.' );
	$managed_term_title = get_term_meta( $device, 'rank_math_title', true );
	$navigate( array( 'post_type' => 'tc_config', 'tax_query' => array( array( 'taxonomy' => 'tc_device', 'field' => 'term_id', 'terms' => array( $device ) ) ), 'paged' => 2 ) );
	$assert( is_tax( 'tc_device' ) && get_queried_object_id() === $device && $managed_term_title . ' — Page 2' === apply_filters( 'rank_math/frontend/title', $managed_term_title ), 'A real device taxonomy query on page 2 receives a distinct managed native title.' );
	update_term_meta( $device, 'rank_math_title', 'LOCAL QA owner device title' );
	$assert( 'LOCAL QA owner device title' === apply_filters( 'rank_math/frontend/title', 'LOCAL QA owner device title' ), 'A manually authored taxonomy title is preserved on page 2.' );
	update_term_meta( $device, 'rank_math_title', $managed_term_title ); $GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main;
	$result = TC_Rank_Math_Pages::restore( 'term', $device );
	$assert( ! is_wp_error( $result ) && 'LOCAL QA original term title' === get_term_meta( $device, 'rank_math_title', true ) && ! metadata_exists( 'term', $device, 'rank_math_description' ), 'Term restore reinstates previous values and previous absence.' );
	remove_filter( 'get_term', $term_alias );

	delete_option( 'tc_rank_math_archive_backup' ); delete_option( 'tc_rank_math_archive_managed' );
	$titles = get_option( 'rank-math-options-titles', array() ); $titles = is_array( $titles ) ? $titles : array();
	$titles['pt_tc_config_archive_title'] = 'LOCAL QA previous archive title'; $titles['pt_tc_config_archive_description'] = 'LOCAL QA previous archive description.'; $titles['tc_qa_unrelated'] = array( 'preserve' => 'LOCAL QA unrelated settings' );
	update_option( 'rank-math-options-titles', $titles, false );
	$result = TC_Rank_Math_Pages::apply_archive(); $changed_titles = get_option( 'rank-math-options-titles' );
	$assert( ! is_wp_error( $result ) && str_contains( $changed_titles['pt_tc_config_archive_title'], $data['archive']['tc_config']['title'] ) && $data['archive']['tc_config']['description'] === $changed_titles['pt_tc_config_archive_description'], 'The config hub uses the native archive title and description options.' );
	$assert( str_contains( $changed_titles['pt_tc_config_archive_title'], '%page%' ), 'The native archive option retains Rank Math pagination expansion.' );
	$unrelated_before = $titles; $unrelated_after = $changed_titles; unset( $unrelated_before['pt_tc_config_archive_title'], $unrelated_before['pt_tc_config_archive_description'], $unrelated_after['pt_tc_config_archive_title'], $unrelated_after['pt_tc_config_archive_description'] );
	$assert( $unrelated_before === $unrelated_after, 'Archive updates merge only their two supported keys without replacing the global Rank Math section.' );
	TC_Rank_Math_Pages::apply_archive(); $assert( $changed_titles === get_option( 'rank-math-options-titles' ), 'Repeated archive migration is idempotent.' );
	$changed_titles['pt_tc_config_archive_title'] = 'LOCAL QA later authored archive title'; update_option( 'rank-math-options-titles', $changed_titles, false );
	TC_Rank_Math_Pages::restore( 'archive' ); $restored_titles = get_option( 'rank-math-options-titles' );
	$assert( 'LOCAL QA later authored archive title' === $restored_titles['pt_tc_config_archive_title'] && $titles['pt_tc_config_archive_description'] === $restored_titles['pt_tc_config_archive_description'], 'Archive restore preserves newer authored changes and restores only managed values.' );

	// Scope the batch to a completed initial scan and our own queued records only.
	$version = ( new ReflectionClass( TC_Rank_Math_Pages::class ) )->getConstant( 'VERSION' );
	update_option( 'timonconfigs_page_seo_state', array( 'version' => $version, 'done' => true, 'last_id' => 0, 'term_offset' => 0 ), false );
	$queued = $create( 'page', 'LOCAL QA queued page', 'LOCAL QA body should not become metadata.' );
	$queue = array();
	for ( $index = 0; $index < 20; ++$index ) { $invalid_id = PHP_INT_MAX - $index; $queue[ 'post:' . $invalid_id ] = array( 'post', $invalid_id ); }
	$queue[ 'post:' . $queued ] = array( 'post', $queued ); update_option( 'timonconfigs_page_seo_queue', $queue, false );
	$first_batch = TC_Rank_Math_Pages::run_batch();
	$assert( ! is_wp_error( $first_batch ) && 1 === $first_batch['queued'] && ! metadata_exists( 'post', $queued, 'rank_math_title' ), 'A bounded batch drops twenty stale queue records without scanning unrelated existing pages.' );
	$second_batch = TC_Rank_Math_Pages::run_batch();
	$assert( ! is_wp_error( $second_batch ) && 0 === $second_batch['queued'] && '' !== get_post_meta( $queued, 'rank_math_title', true ) && array() === get_option( 'timonconfigs_page_seo_queue' ), 'A valid record behind stale entries is processed by the following batch instead of remaining blocked.' );
	$locked = $create( 'page', 'LOCAL QA lock protected page' );
	update_option( 'timonconfigs_page_seo_queue', array( 'post:' . $locked => array( 'post', $locked ) ), false );
	$state_before_lock = get_option( 'timonconfigs_page_seo_state' ); $queue_before_lock = get_option( 'timonconfigs_page_seo_queue' );
	$foreign_lock = array( 'owner' => 'local-qa-foreign-worker', 'expires' => time() + 300 ); update_option( '_tc_page_seo_lock', $foreign_lock, false );
	$result = TC_Rank_Math_Pages::run_batch();
	$assert( is_wp_error( $result ) && 'tc_seo_busy' === $result->get_error_code() && ! metadata_exists( 'post', $locked, 'rank_math_title' ) && $state_before_lock === get_option( 'timonconfigs_page_seo_state' ) && $queue_before_lock === get_option( 'timonconfigs_page_seo_queue' ) && $foreign_lock === get_option( '_tc_page_seo_lock' ), 'A live foreign migration lock prevents metadata, queue and cursor writes and is not released by this request.' );
	$result = TC_Rank_Math_Pages::restore( 'post', $unknown );
	$assert( is_wp_error( $result ) && 'tc_seo_busy' === $result->get_error_code() && $foreign_lock === get_option( '_tc_page_seo_lock' ), 'Restore also respects the active migration lease.' );
	delete_option( '_tc_page_seo_lock' );

	$restore_failure_id = $create( 'page', 'LOCAL QA recoverable restore' ); update_post_meta( $restore_failure_id, '_tc_collection', 'cronus-zen-apex-legends' );
	update_post_meta( $restore_failure_id, 'rank_math_title', 'LOCAL QA original recoverable title' );
	TC_Rank_Math_Pages::apply_post( $restore_failure_id );
	$marker_before_failure = get_post_meta( $restore_failure_id, '_tc_rank_math_page_managed', true ); $backup_before_failure = get_post_meta( $restore_failure_id, '_tc_rank_math_page_backup', true ); $native_before_failure = $native( $restore_failure_id );
	add_filter( 'update_post_metadata', $block_restore, 1, 3 ); $result = TC_Rank_Math_Pages::restore( 'post', $restore_failure_id ); remove_filter( 'update_post_metadata', $block_restore, 1 );
	$assert( is_wp_error( $result ) && 'tc_seo_restore_write' === $result->get_error_code() && $marker_before_failure === get_post_meta( $restore_failure_id, '_tc_rank_math_page_managed', true ) && $backup_before_failure === get_post_meta( $restore_failure_id, '_tc_rank_math_page_backup', true ) && $native_before_failure === $native( $restore_failure_id ) && false === get_option( '_tc_page_seo_lock', false ), 'A failed restore retains its backup and managed marker for retry and releases only its own lease.' );
	$result = TC_Rank_Math_Pages::restore( 'post', $restore_failure_id );
	$assert( ! is_wp_error( $result ) && 'LOCAL QA original recoverable title' === get_post_meta( $restore_failure_id, 'rank_math_title', true ) && ! metadata_exists( 'post', $restore_failure_id, 'rank_math_description' ), 'A later restore retries successfully after the underlying write failure is removed.' );

	wp_set_current_user( 0 );
	$before_native = $native( $unknown ); $before_state = get_option( 'timonconfigs_page_seo_state', null ); $_SERVER['REQUEST_METHOD'] = 'GET';
	$assert( is_wp_error( TC_Rank_Math_Pages::apply_post( $unknown ) ) && is_wp_error( TC_Rank_Math_Pages::apply_term( $device ) ) && is_wp_error( TC_Rank_Math_Pages::apply_archive() ) && is_wp_error( TC_Rank_Math_Pages::restore( 'post', $unknown ) ), 'Guests cannot apply or restore native metadata.' );
	TC_Rank_Math_Pages::maybe_apply();
	$assert( $before_native === $native( $unknown ) && $before_state === get_option( 'timonconfigs_page_seo_state', null ), 'An unauthenticated public GET cannot run the migration or alter its cursor.' );
	$before_denial = array( 'native' => $native( $unknown ), 'backup' => get_post_meta( $unknown, '_tc_rank_math_page_backup', true ), 'managed' => get_post_meta( $unknown, '_tc_rank_math_page_managed', true ), 'state' => get_option( 'timonconfigs_page_seo_state', null ), 'queue' => get_option( 'timonconfigs_page_seo_queue', null ), 'archive' => get_option( 'rank-math-options-titles', null ), 'lock' => get_option( '_tc_page_seo_lock', null ) );
	$unchanged_after_denial = static function () use ( $unknown, $native, $before_denial ): bool {
		return $before_denial === array( 'native' => $native( $unknown ), 'backup' => get_post_meta( $unknown, '_tc_rank_math_page_backup', true ), 'managed' => get_post_meta( $unknown, '_tc_rank_math_page_managed', true ), 'state' => get_option( 'timonconfigs_page_seo_state', null ), 'queue' => get_option( 'timonconfigs_page_seo_queue', null ), 'archive' => get_option( 'rank-math-options-titles', null ), 'lock' => get_option( '_tc_page_seo_lock', null ) );
	};
	$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array( 'action' => 'tc_page_seo', 'operation' => 'batch' ); $_REQUEST = $_POST; add_filter( 'wp_die_handler', $die_handler );
	$denied = false; try { TC_Rank_Math_Pages::handle_action(); } catch ( RuntimeException $error ) { $denied = str_starts_with( $error->getMessage(), 'LOCAL QA rejected request:' ); }
	$assert( $denied && $unchanged_after_denial(), 'A guest POST to the administrator action is denied without changing metadata, backups, queue or migration state.' );
	wp_set_current_user( (int) $admins[0] );
	foreach ( array( null, 'local-qa-invalid-nonce' ) as $nonce ) {
		$_POST = array( 'action' => 'tc_page_seo', 'operation' => 'batch' ); if ( null !== $nonce ) { $_POST['_wpnonce'] = $nonce; } $_REQUEST = $_POST;
		$denied = false; try { TC_Rank_Math_Pages::handle_action(); } catch ( RuntimeException $error ) { $denied = str_starts_with( $error->getMessage(), 'LOCAL QA rejected request:' ); }
		$assert( $denied && $unchanged_after_denial(), 'An administrator POST with ' . ( null === $nonce ? 'no nonce' : 'an invalid nonce' ) . ' is denied without any SEO or backup write.' );
	}
	remove_filter( 'wp_die_handler', $die_handler ); $_POST = $old_post; $_REQUEST = $old_request;
	wp_set_current_user( (int) $admins[0] ); $_SERVER['REQUEST_METHOD'] = 'POST';
	TC_Rank_Math_Pages::maybe_apply();
	$assert( $before_state === get_option( 'timonconfigs_page_seo_state', null ), 'Automatic migration does not run during an administrator POST or editor save.' );
	$_SERVER['REQUEST_METHOD'] = 'GET'; add_filter( 'wp_doing_ajax', '__return_true' ); TC_Rank_Math_Pages::maybe_apply(); remove_filter( 'wp_doing_ajax', '__return_true' );
	$assert( $before_state === get_option( 'timonconfigs_page_seo_state', null ), 'AJAX requests cannot trigger a global metadata migration.' );
	add_filter( 'wp_doing_cron', '__return_true' ); TC_Rank_Math_Pages::maybe_apply(); remove_filter( 'wp_doing_cron', '__return_true' );
	$assert( $before_state === get_option( 'timonconfigs_page_seo_state', null ), 'Cron requests cannot trigger an administrator-only migration.' );
	$assert( 0 === $http_calls && 0 === $shortcode_runs, 'Metadata generation makes no external requests and executes no content shortcode.' );
	WP_CLI::success( "Rank Math pages integration: $checks assertions passed; actual native fields, reversible localhost fixtures, no production changes." );
} finally {
	remove_filter( 'pre_option_show_on_front', $front_mode ); remove_filter( 'pre_option_page_on_front', $front_page ); remove_filter( 'pre_option_woocommerce_checkout_page_id', $woo_page ); remove_filter( 'get_term', $term_alias );
	remove_filter( 'pre_http_request', $no_http, 1 ); remove_shortcode( 'tc_page_seo_fixture' );
	remove_filter( 'update_post_metadata', $block_restore, 1 );
	remove_filter( 'wp_die_handler', $die_handler );
	remove_filter( 'wp_doing_ajax', '__return_true' ); remove_filter( 'wp_doing_cron', '__return_true' );
	foreach ( array_reverse( $posts ) as $id ) { wp_delete_post( $id, true ); }
	foreach ( array_reverse( $terms ) as $entry ) { wp_delete_term( $entry[0], $entry[1] ); }
	foreach ( $options as $key => $backup ) { $backup['exists'] ? update_option( $key, $backup['value'], false ) : delete_option( $key ); }
	wp_set_current_user( $old_user ); $_POST = $old_post; $_REQUEST = $old_request;
	$_GET = $old_get; $GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main;
	if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
}
