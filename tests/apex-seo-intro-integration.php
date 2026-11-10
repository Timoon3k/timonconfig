<?php
/** Owned Apex introduction upgrade; marked, reversible localhost fixtures only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Collections' ) ) { throw new RuntimeException( 'Load the collection module first.' ); }

$checks = 0; $created = array(); $purges = array(); $http_calls = 0;
$old_user = get_current_user_id(); $old_post = $_POST; $old_method = $_SERVER['REQUEST_METHOD'] ?? null;
$options = array();
foreach ( array( 'timonconfigs_page_seo_queue', 'timonconfigs_page_seo_state', 'timonconfigs_collections_version' ) as $key ) { $options[ $key ] = array( 'exists' => false !== get_option( $key, false ), 'value' => get_option( $key, false ) ); }
$marker = '_tc_apex_intro_fixture'; $token = wp_generate_uuid4();
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Apex intro: ' . $message ); } ++$checks; };
$no_http = static function () use ( &$http_calls ): WP_Error { ++$http_calls; return new WP_Error( 'tc_intro_local_only', 'External requests are disabled in this fixture.' ); };
$purge = static function ( string $url ) use ( &$purges ): void { $purges[] = $url; };
$create = static function ( string $key, string $content, array $extra = array() ) use ( &$created, $marker, $token ): int {
	$id = wp_insert_post( wp_slash( array_replace( array(
		'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'LOCAL QA owned intro fixture', 'post_name' => 'tc-apex-intro-' . wp_generate_uuid4(),
		'post_content' => $content, 'post_excerpt' => 'LOCAL QA excerpt must remain untouched.',
		'meta_input' => array( $marker => $token, '_tc_collection' => $key, 'tc_noindex' => true, 'tc_seo_title' => 'LOCAL QA untouched fallback title', 'rank_math_title' => 'LOCAL QA untouched native title', 'rank_math_description' => 'LOCAL QA untouched native description', 'rank_math_focus_keyword' => 'LOCAL QA untouched phrase', 'rank_math_canonical_url' => 'https://example.invalid/untouched-canonical/', '_tc_values_content' => 'PRIVATE_VALUES_MUST_NOT_APPEAR' ),
	), $extra ) ), true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$created[] = (int) $id; return (int) $id;
};
$stable = static function ( int $id ): array {
	$post = get_post( $id );
	return array(
		'post' => array_intersect_key( $post->to_array(), array_flip( array( 'post_title', 'post_name', 'post_status', 'post_password', 'post_excerpt', 'post_parent', 'comment_status', 'ping_status' ) ) ),
		'meta' => array_map( static fn( string $key ): mixed => get_post_meta( $id, $key, true ), array( '_tc_collection', 'tc_noindex', 'tc_seo_title', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_canonical_url', '_tc_values_content' ) ),
		'url' => get_permalink( $id ),
	);
};
$defs = TC_Collections::definitions();
add_filter( 'pre_http_request', $no_http, 1 ); add_action( 'litespeed_purge_url', $purge );
$backup_failure = null; $write_failure = null; $marker_failure = null; $concurrent_edit = null; $collection_scope = null; $insert_spy = null;

try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $admins ) { throw new RuntimeException( 'An existing local administrator is required.' ); }
	wp_set_current_user( (int) $admins[0] );
	foreach ( array( 'xim-matrix-apex-legends', 'cronus-zen-apex-legends' ) as $key ) {
		$definition = $defs[ $key ]; $legacy = TC_Collections::default_content( $definition, true ); $desired = TC_Collections::default_content( $definition );
		$device = 'xim-matrix' === $definition['device'] ? 'XIM Matrix' : 'Cronus Zen';
		$assert( $desired !== $legacy && str_contains( $desired, '<strong>' . $device . ' Apex Legends configs' ), 'The two Apex pages have a specific natural focus phrase and upgraded copy: ' . $key );
		$rendered = do_blocks( $desired );
		$words = str_word_count( html_entity_decode( wp_strip_all_tags( $rendered ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$assert( $words >= 120 && $words <= 165, 'The introduction is useful concise public copy: ' . $key . ' (' . $words . ' words).' );
		$assert( 1 === substr_count( $rendered, '<h2' ) && 3 === substr_count( $rendered, '<p>' ) && ! str_contains( $rendered, '<h1' ), 'The copy renders three paragraphs and one subheading without duplicating H1.' );
		$assert( str_contains( $rendered, 'Release details' ) && str_contains( $rendered, esc_url( home_url( '/faq/' ) ) ) && str_contains( $rendered, esc_url( home_url( '/pricing/' ) ) ), 'The intro directs visitors to actual release, FAQ and access information.' );
		$assert( ! preg_match( '/\b(?:best|Season\s*3[01]|S3[01]|Predator|LethalPanda|flagship|most popular|all scripts receive|regular updates)\b/i', $rendered ), 'No unverified release names, seasons, rankings or blanket update promise are invented.' );
		$id = $create( $key, $legacy ); $before = $stable( $id ); $before_purges = count( $purges );
		$result = TC_Collections::upgrade_intro( $id );
		$assert( ! is_wp_error( $result ) && true === $result['changed'] && 'updated' === $result['status'], 'A recognized stock introduction is upgraded: ' . $key );
		$assert( $desired === get_post_field( 'post_content', $id, 'raw' ) && $before === $stable( $id ), 'Only public intro content changes; title, slug, status, meta and permalink remain authoritative.' );
		$backup = get_post_meta( $id, '_tc_apex_intro_backup', true ); $managed = get_post_meta( $id, '_tc_apex_intro_managed', true );
		$assert( $legacy === $backup['content'] && hash( 'sha256', $legacy ) === $backup['hash'], 'The original content is backed up verbatim before replacement.' );
		$assert( '2026-11-10-v2' === $managed['version'] && hash( 'sha256', $desired ) === $managed['hash'], 'The managed marker records the current content and release.' );
		$assert( count( $purges ) === $before_purges + 1 && end( $purges ) === get_permalink( $id ), 'An actual content change purges the precise public page URL.' );
		$assert( ! str_contains( $desired, 'PRIVATE_VALUES_MUST_NOT_APPEAR' ), 'Private values are never copied into public introductions.' );
		$result = TC_Collections::upgrade_intro( $id );
		$assert( ! is_wp_error( $result ) && false === $result['changed'] && 'current' === $result['status'] && $backup === get_post_meta( $id, '_tc_apex_intro_backup', true ) && count( $purges ) === $before_purges + 1, 'A repeated migration does not duplicate content, rewrite the backup or purge unchanged pages.' );
		$authored = $desired . "\n<p>LOCAL QA author's later correction.</p>"; wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $authored ) ) );
		$result = TC_Collections::upgrade_intro( $id );
		$assert( ! is_wp_error( $result ) && 'authored' === $result['status'] && $authored === get_post_field( 'post_content', $id, 'raw' ) && $backup === get_post_meta( $id, '_tc_apex_intro_backup', true ), 'A later manual correction is preserved instead of reset to managed copy.' );
	}
	foreach ( array_diff_key( $defs, array_flip( array( 'xim-matrix-apex-legends', 'cronus-zen-apex-legends' ) ) ) as $key => $definition ) {
		$assert( TC_Collections::default_content( $definition ) === TC_Collections::default_content( $definition, true ), 'Other collections retain their exact legacy default blocks: ' . $key );
	}
	$legacy_xim = '<!-- wp:paragraph --><p>Browse XIM Matrix Apex Legends configs. Compare supported inputs, platforms, release versions and Free or VIP access before choosing your setup.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:heading --><h2 class="wp-block-heading">Choose the release that matches your setup</h2><!-- /wp:heading -->' . "\n" . '<!-- wp:paragraph --><p>Read the release guide for the supported input and platform before importing XIM Matrix text code. Controller and mouse &amp; keyboard setups can require different configurations.</p><!-- /wp:paragraph -->';
	$assert( $legacy_xim === TC_Collections::default_content( $defs['xim-matrix-apex-legends'], true ), 'Legacy mode remains byte-identical to the previously shipped XIM block document.' );

	$authored = $create( 'cronus-zen-apex-legends', '<p>LOCAL QA existing authored collection text.</p>' );
	$authored_before = get_post( $authored )->to_array(); $before_purges = count( $purges );
	$result = TC_Collections::upgrade_intro( $authored );
	$assert( ! is_wp_error( $result ) && 'authored' === $result['status'] && $authored_before === get_post( $authored )->to_array() && ! metadata_exists( 'post', $authored, '_tc_apex_intro_backup' ) && count( $purges ) === $before_purges, 'An existing authored page is untouched, without backup or cache side effects.' );
	$empty = $create( 'xim-matrix-apex-legends', '' ); $result = TC_Collections::upgrade_intro( $empty );
	$assert( ! is_wp_error( $result ) && $result['changed'] && '' === get_post_meta( $empty, '_tc_apex_intro_backup', true )['content'], 'An empty published introduction can be populated with a recoverable empty original.' );
	$current = $create( 'xim-matrix-apex-legends', TC_Collections::default_content( $defs['xim-matrix-apex-legends'] ) ); $result = TC_Collections::upgrade_intro( $current );
	$assert( ! is_wp_error( $result ) && ! $result['changed'] && 'current' === $result['status'] && ! metadata_exists( 'post', $current, '_tc_apex_intro_backup' ), 'Fresh current copy is not replaced or given a fabricated previous version.' );
	$previous = $create( 'cronus-zen-apex-legends', "<p>LOCAL QA previous managed owner's introduction.</p>" );
	$previous_content = get_post_field( 'post_content', $previous, 'raw' );
	$original = array( 'content' => '<p>LOCAL QA first historical source.</p>', 'hash' => hash( 'sha256', '<p>LOCAL QA first historical source.</p>' ) );
	update_post_meta( $previous, '_tc_apex_intro_backup', $original ); update_post_meta( $previous, '_tc_apex_intro_managed', array( 'version' => 'earlier-managed-release', 'hash' => hash( 'sha256', $previous_content ) ) );
	$result = TC_Collections::upgrade_intro( $previous );
	$assert( ! is_wp_error( $result ) && $result['changed'] && $original === get_post_meta( $previous, '_tc_apex_intro_backup', true ), 'Unchanged older managed copy can upgrade without overwriting its first historical backup.' );

	foreach ( array( 'draft', 'pending', 'private', 'trash' ) as $status ) {
		$id = $create( 'xim-matrix-apex-legends', $legacy_xim, array( 'post_status' => $status ) ); $before = get_post( $id )->to_array();
		$result = TC_Collections::upgrade_intro( $id );
		$assert( ! is_wp_error( $result ) && 'ineligible' === $result['status'] && $before === get_post( $id )->to_array() && ! metadata_exists( 'post', $id, '_tc_apex_intro_backup' ), 'Unpublished or trashed pages keep their content and publication state: ' . $status );
	}
	$protected = $create( 'xim-matrix-apex-legends', $legacy_xim, array( 'post_password' => 'local-fixture-password' ) );
	$before = get_post( $protected )->to_array(); $result = TC_Collections::upgrade_intro( $protected );
	$assert( ! is_wp_error( $result ) && 'ineligible' === $result['status'] && $before === get_post( $protected )->to_array(), 'A password-protected page is not changed.' );
	$other = $create( 'xim-matrix-call-of-duty', '' ); $result = TC_Collections::upgrade_intro( $other );
	$assert( ! is_wp_error( $result ) && 'ineligible' === $result['status'] && '' === get_post_field( 'post_content', $other, 'raw' ), 'The rollout does not update other game collections.' );
	$unmarked = $create( '', $legacy_xim ); $result = TC_Collections::upgrade_intro( $unmarked );
	$assert( ! is_wp_error( $result ) && 'ineligible' === $result['status'], 'An authored page without a collection marker cannot be mistaken for the target collection.' );
	$assert( 'ineligible' === TC_Collections::upgrade_intro( 0 )['status'], 'An invalid zero ID cannot fall back to the global post.' );
	wp_set_current_user( 0 ); $before = get_post_field( 'post_content', $empty, 'raw' );
	$assert( is_wp_error( TC_Collections::upgrade_intro( $empty ) ) && $before === get_post_field( 'post_content', $empty, 'raw' ), 'Guest callers cannot update an introduction.' );
	wp_set_current_user( (int) $admins[0] );

	$failure = $create( 'xim-matrix-apex-legends', $legacy_xim );
	$backup_failure = static function ( mixed $check, int $id, string $key ) use ( $failure ): mixed { return $failure === $id && '_tc_apex_intro_backup' === $key ? false : $check; };
	add_filter( 'add_post_metadata', $backup_failure, 10, 3 ); $result = TC_Collections::upgrade_intro( $failure ); remove_filter( 'add_post_metadata', $backup_failure, 10 );
	$assert( is_wp_error( $result ) && 'tc_apex_intro_backup' === $result->get_error_code() && $legacy_xim === get_post_field( 'post_content', $failure, 'raw' ), 'A failed backup prevents the content write.' );
	$write_failure = static function ( array $data, array $postarr ) use ( $failure, $legacy_xim ): array { if ( $failure === (int) ( $postarr['ID'] ?? 0 ) ) { $data['post_content'] = wp_slash( $legacy_xim ); } return $data; };
	add_filter( 'wp_insert_post_data', $write_failure, 99, 2 ); $result = TC_Collections::upgrade_intro( $failure ); remove_filter( 'wp_insert_post_data', $write_failure, 99 );
	$assert( is_wp_error( $result ) && 'tc_apex_intro_write' === $result->get_error_code() && $legacy_xim === get_post_field( 'post_content', $failure, 'raw' ) && ! metadata_exists( 'post', $failure, '_tc_apex_intro_managed' ), 'An unsuccessful content write retains its backup and does not claim current managed ownership.' );
	$result = TC_Collections::upgrade_intro( $failure );
	$assert( ! is_wp_error( $result ) && $result['changed'] && $legacy_xim === get_post_meta( $failure, '_tc_apex_intro_backup', true )['content'], 'Retry succeeds after the write failure is removed, preserving the original backup.' );
	$marker_case = $create( 'xim-matrix-apex-legends', $legacy_xim ); $before_purges = count( $purges );
	$marker_failure = static function ( mixed $check, int $id, string $key ) use ( $marker_case ): mixed { return $marker_case === $id && '_tc_apex_intro_managed' === $key ? false : $check; };
	add_filter( 'update_post_metadata', $marker_failure, 10, 3 ); $result = TC_Collections::upgrade_intro( $marker_case ); remove_filter( 'update_post_metadata', $marker_failure, 10 );
	$assert( is_wp_error( $result ) && 'tc_apex_intro_marker' === $result->get_error_code() && count( $purges ) === $before_purges + 1, 'A changed page is purged even if managed marker persistence needs a retry.' );
	$result = TC_Collections::upgrade_intro( $marker_case );
	$assert( ! is_wp_error( $result ) && 'current' === $result['status'] && count( $purges ) === $before_purges + 1, 'Retry repairs the marker without replacing current copy or purging it twice.' );
	$race = $create( 'xim-matrix-apex-legends', $legacy_xim ); $race_content = '<p>LOCAL QA concurrent author edit must win.</p>';
	$concurrent_edit = static function ( int $meta_id, int $id, string $key ) use ( $race, $race_content ): void { if ( $race === $id && '_tc_apex_intro_backup' === $key ) { global $wpdb; $wpdb->update( $wpdb->posts, array( 'post_content' => $race_content ), array( 'ID' => $id ) ); } };
	add_action( 'added_post_meta', $concurrent_edit, 10, 3 ); $result = TC_Collections::upgrade_intro( $race ); remove_action( 'added_post_meta', $concurrent_edit, 10 ); clean_post_cache( $race );
	$assert( ! is_wp_error( $result ) && 'authored' === $result['status'] && $race_content === get_post_field( 'post_content', $race, 'raw' ), 'A concurrent edit observed before the write is preserved, including with a stale WordPress object cache.' );

	// Runtime rollout must not recreate collections deliberately deleted since the previous release.
	$rollout_xim = $create( 'xim-matrix-apex-legends', $legacy_xim );
	$rollout_cronus = $create( 'cronus-zen-apex-legends', '<p>LOCAL QA authored rollout copy must remain.</p>' );
	$rollout_other = $create( 'cronus-zen-call-of-duty', '<p>LOCAL QA unrelated collection copy.</p>' );
	$scope_ids = array( $rollout_xim, $rollout_cronus, $rollout_other ); $page_inserts = 0;
	$collection_scope = static function ( WP_Query $query ) use ( &$scope_ids ): void {
		if ( in_array( 'page', (array) $query->get( 'post_type' ), true ) && '_tc_collection' === $query->get( 'meta_key' ) ) { $query->set( 'post__in', $scope_ids ); }
	};
	$insert_spy = static function ( int $id, WP_Post $post, bool $update ) use ( &$page_inserts ): void { if ( ! $update && 'page' === $post->post_type ) { ++$page_inserts; } };
	add_action( 'pre_get_posts', $collection_scope, 99 ); add_action( 'wp_insert_post', $insert_spy, 99, 3 );
	update_option( 'timonconfigs_collections_version', '2026-10-30-v1', false ); $_SERVER['REQUEST_METHOD'] = 'POST';
	TC_Collections::maybe_install();
	$assert( '2026-10-30-v1' === get_option( 'timonconfigs_collections_version' ) && $legacy_xim === get_post_field( 'post_content', $rollout_xim, 'raw' ) && 0 === $page_inserts, 'An administrator POST does not migrate content or provision any collection.' );
	$_SERVER['REQUEST_METHOD'] = 'GET'; add_filter( 'wp_doing_ajax', '__return_true' ); TC_Collections::maybe_install(); remove_filter( 'wp_doing_ajax', '__return_true' );
	$assert( '2026-10-30-v1' === get_option( 'timonconfigs_collections_version' ) && 0 === $page_inserts, 'AJAX does not run the runtime collection rollout.' );
	add_filter( 'wp_doing_cron', '__return_true' ); TC_Collections::maybe_install(); remove_filter( 'wp_doing_cron', '__return_true' );
	$assert( '2026-10-30-v1' === get_option( 'timonconfigs_collections_version' ) && 0 === $page_inserts, 'Cron does not run the administrator-only collection rollout.' );
	$other_before = get_post( $rollout_other )->to_array(); $cronus_before = get_post( $rollout_cronus )->to_array();
	TC_Collections::maybe_install();
	$assert( '2026-11-10-v2' === get_option( 'timonconfigs_collections_version' ) && TC_Collections::default_content( $defs['xim-matrix-apex-legends'] ) === get_post_field( 'post_content', $rollout_xim, 'raw' ), 'The v1 runtime upgrade updates existing stock Apex copy and records v2.' );
	$assert( $other_before === get_post( $rollout_other )->to_array() && $cronus_before === get_post( $rollout_cronus )->to_array(), 'The runtime upgrade preserves unrelated and authored collection content.' );
	$assert( 0 === $page_inserts, 'The v1 runtime upgrade never provisions missing collection pages.' );
	$purges_before_repeat = count( $purges ); TC_Collections::maybe_install();
	$assert( 0 === $page_inserts && count( $purges ) === $purges_before_repeat, 'A completed runtime rollout does not run again.' );
	$scope_ids = array( $rollout_other ); update_option( 'timonconfigs_collections_version', '2026-10-30-v1', false );
	TC_Collections::maybe_install();
	$assert( '2026-11-10-v2' === get_option( 'timonconfigs_collections_version' ) && 0 === $page_inserts, 'A v1 site with no remaining Apex pages does not recreate deleted Apex or unrelated collections.' );
	remove_action( 'pre_get_posts', $collection_scope, 99 ); remove_action( 'wp_insert_post', $insert_spy, 99 );
	$assert( 0 === $http_calls, 'The migration does not perform external requests.' );
	WP_CLI::success( "Apex SEO intro integration: $checks assertions passed; marked localhost fixtures only, no existing pages changed." );
} finally {
	remove_filter( 'pre_http_request', $no_http, 1 ); remove_action( 'litespeed_purge_url', $purge );
	if ( $backup_failure ) { remove_filter( 'add_post_metadata', $backup_failure, 10 ); }
	if ( $write_failure ) { remove_filter( 'wp_insert_post_data', $write_failure, 99 ); }
	if ( $marker_failure ) { remove_filter( 'update_post_metadata', $marker_failure, 10 ); }
	if ( $concurrent_edit ) { remove_action( 'added_post_meta', $concurrent_edit, 10 ); }
	if ( $collection_scope ) { remove_action( 'pre_get_posts', $collection_scope, 99 ); }
	if ( $insert_spy ) { remove_action( 'wp_insert_post', $insert_spy, 99 ); }
	remove_filter( 'wp_doing_ajax', '__return_true' ); remove_filter( 'wp_doing_cron', '__return_true' );
	wp_set_current_user( 0 );
	foreach ( array_reverse( $created ) as $id ) { if ( ! get_post( $id ) ) { continue; } if ( $token !== get_post_meta( $id, $marker, true ) ) { throw new RuntimeException( 'Refusing to delete an unmarked fixture record.' ); } wp_delete_post( $id, true ); }
	foreach ( $options as $key => $backup ) { $backup['exists'] ? update_option( $key, $backup['value'], false ) : delete_option( $key ); }
	wp_set_current_user( $old_user ); $_POST = $old_post;
	if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
}
