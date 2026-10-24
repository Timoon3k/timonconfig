<?php
/**
 * Disposable WordPress only. Run with --skip-plugins=timonconfigs-core.
 * Uses protected-service doubles for catalogue presentation; auth is tested separately.
 */
defined( 'ABSPATH' ) || exit;
if ( class_exists( 'TC_Discord' ) || class_exists( 'TC_Private_Files' ) || class_exists( 'TC_Downloads' ) ) {
	throw new RuntimeException( 'Run this presentation suite with --skip-plugins=timonconfigs-core.' );
}
require_once WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-catalog.php';

if ( ! class_exists( 'TC_Discord' ) ) {
	final class TC_Discord {
		public static string $state = 'unverified';
		public static bool $allowed = false;
		public static function status(): array { return array( 'state' => self::$state ); }
		public static function can_access( int $id ): bool { return self::$allowed; }
		public static function login_url(): string { return home_url( '/account/' ); }
	}
}
if ( ! class_exists( 'TC_Private_Files' ) ) {
	final class TC_Private_Files {
		public static array $entries = array();
		public static function files( int $id ): array { return self::$entries; }
	}
}
if ( ! class_exists( 'TC_Downloads' ) ) {
	final class TC_Downloads {
		public const MATRIX_TEXT_ID = '00000000-0000-4000-8000-000000000001';
		public static function render_button( int $id, string $file_id, string $label = 'Download' ): void {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="file" value="' . esc_attr( $file_id ) . '"><button class="tc-button" type="submit">' . esc_html( $label ) . '</button></form>';
		}
	}
}
$checks = 0;
$ids = array();
$term_id = 0;
$user_id = 0;
$previous_get = $_GET;
$previous_post = $_POST;
$previous_user = get_current_user_id();
$previous_query = $GLOBALS['wp_query'];
$previous_main = $GLOBALS['wp_the_query'];
$previous_rewrite_rules = get_option( 'rewrite_rules', null );
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( 'Member catalogue: ' . $message ); }
	++$checks;
};
$render_card = static function ( int $id ): string {
	$GLOBALS['post'] = get_post( $id );
	setup_postdata( $GLOBALS['post'] );
	ob_start();
	get_template_part( 'template-parts/config-card' );
	return (string) ob_get_clean();
};
$query_ids = static function ( string $slug, mixed $sort, int $page = 1 ): array {
	$_GET = array( 'sort' => $sort );
	$q = new WP_Query();
	$GLOBALS['wp_query'] = $q;
	$GLOBALS['wp_the_query'] = $q;
	$q->query( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'tc_game' => $slug, 'fields' => 'ids', 'paged' => $page ) );
	return array( 'ids' => array_map( 'intval', $q->posts ), 'total' => (int) $q->found_posts, 'pages' => (int) $q->max_num_pages, 'orderby' => $q->get( 'orderby' ) );
};
try {
	TC_Catalog::init();
	TC_Catalog::register_content_types();
	TC_Catalog::seed_core_terms();
	$slug = 'tc-member-catalog-' . strtolower( wp_generate_password( 8, false ) );
	$term = wp_insert_term( 'LOCAL member catalogue fixture', 'tc_game', array( 'slug' => $slug ) );
	if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
	$term_id = (int) $term['term_id'];
	update_term_meta( $term_id, 'tc_collection_image', 'https://example.test/public-cover.webp' );
	for ( $i = 0; $i < 14; $i++ ) {
		$id = wp_insert_post( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => sprintf( 'LOCAL SCRIPT %02d', 14 - $i ), 'post_date' => sprintf( '2026-01-%02d 12:00:00', $i + 1 ), 'post_content' => 'Disposable release preview.', 'post_excerpt' => 'Public script preview.', 'meta_input' => array( 'tc_tier' => 'monthly', 'tc_release_status' => 'available', 'tc_publisher' => 'Fixture Studio', 'tc_creator' => 'Fixture Creator', 'tc_release_date' => '2026-01-01', 'tc_last_updated' => '2026-02-01' ) ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$ids[] = (int) $id;
		wp_set_object_terms( $id, array( $term_id ), 'tc_game' );
	}
	$default = $query_ids( $slug, '' );
	$assert( count( $default['ids'] ) === 12 && $default['total'] === 14 && $default['pages'] === 2, 'one game supports unlimited separate script records with twelve per page' );
	$assert( count( $query_ids( $slug, 'updated', 2 )['ids'] ) === 2, 'pagination serves records beyond the first page' );
	$newest = $query_ids( $slug, 'newest' );
	$assert( $newest['ids'][0] === $ids[13], 'upload-date sort uses actual publication dates' );
	$assert( $newest['orderby'] === array( 'date' => 'DESC', 'ID' => 'DESC' ), 'upload sort has a deterministic ID tie-break' );
	$title = $query_ids( $slug, 'title' );
	$assert( $title['ids'][0] === $ids[13] && $title['orderby'] === array( 'title' => 'ASC', 'ID' => 'ASC' ), 'title sort is alphabetic and deterministic' );
	$assert( $query_ids( $slug, array( 'title' ) )['orderby'] === array( 'modified' => 'DESC', 'ID' => 'DESC' ), 'array-shaped sort falls back safely' );
	$assert( $query_ids( $slug, 'date DESC; DROP TABLE posts' )['orderby'] === array( 'modified' => 'DESC', 'ID' => 'DESC' ), 'arbitrary sort input never becomes an SQL clause' );
	$_GET = array();
	$GLOBALS['wp_query'] = $previous_query;
	$GLOBALS['wp_the_query'] = $previous_main;
	$assert( TC_Catalog::sanitize_date( '2026-02-30' ) === '' && TC_Catalog::sanitize_date( array( '2026-01-01' ) ) === '', 'release-date validation rejects invalid dates and arrays' );
	$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) $admin[0] );
	$_POST = array( 'tc_config_details_nonce' => wp_create_nonce( 'tc_save_config_details' ), 'tc_publisher' => '<b>Approved publisher</b>', 'tc_creator' => '<script>alert(1)</script>Creator', 'tc_release_date' => '2026-02-30' );
	TC_Catalog::save_meta( $ids[0] );
	$assert( get_post_meta( $ids[0], 'tc_publisher', true ) === 'Approved publisher' && ! str_contains( get_post_meta( $ids[0], 'tc_creator', true ), '<script>' ) && get_post_meta( $ids[0], 'tc_release_date', true ) === '', 'secured editor save sanitizes public names and release dates' );
	$user_id = wp_insert_user( array( 'user_login' => $slug, 'user_pass' => wp_generate_password( 30 ), 'role' => 'subscriber' ) );
	if ( is_wp_error( $user_id ) ) { throw new RuntimeException( $user_id->get_error_message() ); }
	$_POST = array( 'tc_config_details_nonce' => wp_create_nonce( 'tc_save_config_details' ), 'tc_publisher' => 'Unauthorized publisher' );
	wp_set_current_user( $user_id );
	TC_Catalog::save_meta( $ids[0] );
	$assert( get_post_meta( $ids[0], 'tc_publisher', true ) === 'Approved publisher', 'non-editors cannot overwrite public release credits' );
	$meta = get_registered_meta_keys( 'post', 'tc_config' );
	$assert( isset( $meta['tc_publisher'], $meta['tc_creator'], $meta['tc_release_date'] ) && false !== $meta['tc_publisher']['show_in_rest'], 'public release metadata is available through Gutenberg REST' );

	// A XIM Matrix binary backup must never be falsely advertised as a TXT code.
	$matrix_term = get_term_by( 'slug', 'xim-matrix', 'tc_device' );
	$assert( $matrix_term instanceof WP_Term, 'XIM Matrix device exists for text-code selection.' );
	wp_set_object_terms( $ids[1], array( $matrix_term->term_id ), 'tc_device' );
	TC_Private_Files::$entries = array( array( 'id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'name' => 'binary-backup.ximmr' ) );
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'pending', 'Matrix binary backup alone does not pretend to be a TXT code.' );
	update_post_meta( $ids[1], '_tc_values_content', "XIM_CODE_SAMPLE\\n" );
	wp_set_current_user( 0 );
	$matrix_cta = TC_Catalog::download_cta( $ids[1] );
	$assert( $matrix_cta['action'] === 'login' && $matrix_cta['files'][0]['id'] === TC_Downloads::MATRIX_TEXT_ID, 'Matrix Values allow a signed TXT export selection without exposing code to guests.' );
	delete_post_meta( $ids[1], '_tc_values_content' );
	wp_set_object_terms( $ids[1], array(), 'tc_device' );

	TC_Private_Files::$entries = array( array( 'id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'name' => 'fixture.gpc', 'size' => 80, 'version' => '1.0', 'notes' => 'Verified release notes.', 'checksum' => str_repeat( 'a', 64 ), 'uploaded_at' => '2026-02-01', 'stored_name' => 'secret-storage-name.gpc', 'path' => '/outside/private/secret-script.gpc' ) );
	wp_set_current_user( 0 );
	$guest = TC_Catalog::download_cta( $ids[1] );
	$assert( $guest['action'] === 'login' && $guest['label'] === 'Log in', 'anonymous readers must log in before a protected download' );
	$assert( ! isset( $guest['files'][0]['stored_name'], $guest['files'][0]['path'] ), 'presentation metadata strips private storage fields defensively' );
	$html = $render_card( $ids[1] );
	$assert( str_contains( $html, 'https://example.test/public-cover.webp' ) && str_contains( $html, 'LOCAL SCRIPT' ) && ! str_contains( $html, 'Fixture Studio' ) && ! str_contains( $html, 'Fixture Creator' ) && ! str_contains( $html, '2026-02-01' ), 'compact cards use shared artwork without credits, release dates or long metadata' );
	$assert( str_contains( $html, 'tc-config-card__overlay-link' ) && str_contains( $html, 'Log in' ) && ! str_contains( $html, 'secret-script' ) && ! str_contains( $html, 'secret-storage-name' ) && ! str_contains( $html, 'recorded downloads' ), 'guest cards click through to Discord login without private paths or counts' );
	wp_set_current_user( $user_id );
	TC_Discord::$state = 'unverified';
	$assert( TC_Catalog::download_cta( $ids[1] )['label'] === 'Verify with Discord', 'unverified members receive verification guidance' );
	TC_Discord::$state = 'free';
	$free = TC_Catalog::download_cta( $ids[1] );
	$assert( $free['action'] === 'memberships' && $free['url'] === home_url( '/pricing/' ), 'verified Free members see Browse Memberships for paid releases' );
	TC_Discord::$state = 'monthly';
	TC_Discord::$allowed = true;
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'download', 'entitled Monthly members receive protected download presentation' );
	update_post_meta( $ids[1], 'tc_release_status', 'coming-soon' );
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'pending', 'published Coming soon preview remains non-downloadable even for entitled VIP members' );
	update_post_meta( $ids[1], 'tc_release_status', 'available' );
	$html = $render_card( $ids[1] );
	$assert( str_contains( $html, '<form method="post"' ) && str_contains( $html, 'Download LOCAL SCRIPT' ) && ! str_contains( $html, '/outside/private' ), 'entitled full-card actions render protected POST form without a direct file URL' );
	TC_Discord::$state = 'lifetime';
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'download', 'Lifetime access can use the same protected button pipeline' );
	TC_Discord::$allowed = false;
	TC_Discord::$state = 'unavailable';
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'verify', 'provider outage does not suggest an authorized download or a duplicate purchase' );
	TC_Private_Files::$entries = array();
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'pending', 'releases without files never claim a working download' );
	TC_Private_Files::$entries = array( array( 'id' => 'file-fixture', 'name' => 'fixture.gpc' ) );
	wp_update_post( array( 'ID' => $ids[1], 'post_status' => 'draft' ) );
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'pending', 'draft releases never offer protected files' );
	wp_update_post( array( 'ID' => $ids[1], 'post_status' => 'publish' ) );
	update_post_meta( $ids[1], 'tc_release_status', 'archived' );
	$assert( TC_Catalog::download_cta( $ids[1] )['action'] === 'pending', 'archived releases never offer protected files' );
	WP_CLI::success( sprintf( 'Member catalogue presentation: %d assertions passed (protected-service doubles; no account/provider operations).', $checks ) );
} finally {
	$_GET = $previous_get;
	$_POST = $previous_post;
	wp_set_current_user( $previous_user );
	$GLOBALS['wp_query'] = $previous_query;
	$GLOBALS['wp_the_query'] = $previous_main;
	wp_reset_postdata();
	foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
	if ( $term_id ) { wp_delete_term( $term_id, 'tc_game' ); }
	if ( $user_id && ! is_wp_error( $user_id ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user_id ); }
	// This suite skips the real plugin; never persist an incomplete rewrite set.
	null === $previous_rewrite_rules ? delete_option( 'rewrite_rules' ) : update_option( 'rewrite_rules', $previous_rewrite_rules );
}
