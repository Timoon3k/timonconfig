<?php
/**
 * Run on local/staging WordPress: wp eval-file - < tests/site-integration.php
 * Tests the explicit installer, preserving existing content and cleaning owned fixtures.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TC_Site' ) || ! class_exists( 'TC_Catalog' ) || ! function_exists( 'timonconfigs_default_home_content' ) ) {
	throw new RuntimeException( 'Activate TimonConfigs Core and the TimonConfigs theme before this test.' );
}

$tc_site_checks = 0;
$tc_site_expect = static function ( bool $condition, string $message ) use ( &$tc_site_checks ): void {
	if ( ! $condition ) { throw new RuntimeException( 'Site installation test failed: ' . $message ); }
	++$tc_site_checks;
};
$tc_site_post_ids = array();
$tc_site_menu_id = 0;
$tc_site_original_user = get_current_user_id();
$tc_site_original_request = $_REQUEST;
$tc_site_original_post = $_POST;
$tc_site_snapshot = static function ( WP_Post $post ): array {
	return array( 'ID' => $post->ID, 'title' => $post->post_title, 'content' => $post->post_content, 'status' => $post->post_status );
};
$tc_site_die_filter = static function (): Closure {
	return static function ( $message, $title = '', $args = array() ): void {
		throw new RuntimeException( 'tc-site-test-wp-die:' . (int) ( $args['response'] ?? 500 ) );
	};
};

try {
	$tc_site_expected_slugs = array( 'home', 'pricing', 'games', 'reviews', 'faq', 'contact', 'video-showcase', 'vip-monthly', 'vip-lifetime', 'terms-of-service', 'privacy-policy', 'refund-policy', 'cookie-policy', 'lifetime-license-terms' );
	$tc_site_expect( array_keys( TC_Site::pages() ) === $tc_site_expected_slugs, 'all fourteen editorial page definitions are present' );
	$tc_site_expect( ! array_key_exists( 'configs', TC_Site::pages() ), 'Configs remains a CPT archive rather than a duplicate Page' );
	$tc_site_type = get_post_type_object( 'tc_config' );
	$tc_site_expect( $tc_site_type && 'configs' === $tc_site_type->has_archive, 'the original configuration archive is preserved' );

	$tc_site_token = strtolower( wp_generate_password( 10, false, false ) );
	foreach ( array( 'publish', 'private' ) as $status ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => $status, 'post_title' => 'LOCAL SITE TEST ' . $status, 'post_name' => 'tc-site-test-' . $tc_site_token . '-' . $status, 'post_content' => '<!-- wp:paragraph --><p>Owned installer fixture ' . esc_html( $tc_site_token ) . '.</p><!-- /wp:paragraph -->' ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$tc_site_post_ids[] = (int) $id;
	}
	$tc_site_menu_id = wp_create_nav_menu( 'LOCAL Site installer test ' . $tc_site_token );
	if ( is_wp_error( $tc_site_menu_id ) ) { throw new RuntimeException( $tc_site_menu_id->get_error_message() ); }
	$tc_site_menu_id = (int) $tc_site_menu_id;
	$tc_site_menu_item_id = wp_update_nav_menu_item( $tc_site_menu_id, 0, array( 'menu-item-title' => 'Owned fixture link', 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/contact/' ), 'menu-item-status' => 'publish' ) );
	if ( is_wp_error( $tc_site_menu_item_id ) ) { throw new RuntimeException( $tc_site_menu_item_id->get_error_message() ); }
	$tc_site_post_ids[] = (int) $tc_site_menu_item_id;
	$tc_site_menu_items = array_map( static fn( WP_Post $item ): array => array( $item->ID, $item->title, $item->url, $item->menu_order ), wp_get_nav_menu_items( $tc_site_menu_id ) ?: array() );
	$tc_site_menu_locations = get_nav_menu_locations();
	$tc_site_original_front = array( get_option( 'show_on_front' ), get_option( 'page_on_front' ), get_option( 'page_for_posts' ) );
	$tc_site_existing = array();
	foreach ( TC_Site::pages() as $slug => $title ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page ) { $tc_site_existing[ $slug ] = $tc_site_snapshot( $page ); }
	}
	$tc_site_existing_fixtures = array_map( static fn( int $id ): array => $tc_site_snapshot( get_post( $id ) ), array_slice( $tc_site_post_ids, 0, 2 ) );
	$tc_site_configs_pages_before = get_posts( array( 'post_type' => 'page', 'name' => 'configs', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
	$tc_site_first = TC_Site::install();
	$tc_site_expect( ! is_wp_error( $tc_site_first ) && count( $tc_site_first ) === 14, 'explicit installation returns fourteen page IDs' );
	$tc_site_second = TC_Site::install();
	$tc_site_expect( $tc_site_first === $tc_site_second, 'repeat installation returns the same IDs without duplicates' );
	foreach ( $tc_site_first as $slug => $id ) {
		$page = get_post( $id );
		$tc_site_expect( $page instanceof WP_Post && 'page' === $page->post_type && $slug === $page->post_name, 'required route resolves to the correct Page: ' . $slug );
		if ( isset( $tc_site_existing[ $slug ] ) ) {
			$before = $tc_site_existing[ $slug ];
			$tc_site_expect( $page->ID === $before['ID'] && $page->post_title === $before['title'] && $page->post_status === $before['status'], 'existing title/ID/status survives installation: ' . $slug );
			if ( '' !== trim( $before['content'] ) ) {
				$tc_site_expect( $page->post_content === $before['content'], 'authored content survives installation: ' . $slug );
			}
		} else {
			$tc_site_expect( 'publish' === $page->post_status, 'new required editorial Page is public: ' . $slug );
		}
		$tc_site_expect( has_blocks( $page->post_content ), 'starter content uses native Gutenberg blocks: ' . $slug );
	}
	foreach ( $tc_site_existing_fixtures as $fixture ) {
		$tc_site_expect( $fixture === $tc_site_snapshot( get_post( $fixture['ID'] ) ), 'unrelated authored fixture and its public/private status are unchanged' );
	}
	$tc_site_expect( $tc_site_menu_locations === get_nav_menu_locations(), 'assigned menu locations are preserved' );
	$tc_site_expect( $tc_site_menu_items === array_map( static fn( WP_Post $item ): array => array( $item->ID, $item->title, $item->url, $item->menu_order ), wp_get_nav_menu_items( $tc_site_menu_id ) ?: array() ), 'existing unassigned manual menu is not repurposed or overwritten' );
	$tc_site_expect( $tc_site_original_front === array( get_option( 'show_on_front' ), get_option( 'page_on_front' ), get_option( 'page_for_posts' ) ), 'an assigned static front page remains unchanged' );
	$tc_site_configs_pages_after = get_posts( array( 'post_type' => 'page', 'name' => 'configs', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
	$tc_site_expect( $tc_site_configs_pages_before === $tc_site_configs_pages_after, 'installation adds no shadow Configs Page' );
	$tc_site_blocks = array_values( array_filter( parse_blocks( timonconfigs_default_home_content() ), static fn( array $block ): bool => 'timonconfigs/section' === $block['blockName'] ) );
	$tc_site_expect( count( $tc_site_blocks ) === 10, 'Home starter has ten native server-rendered sections' );
	$tc_site_expect( array_column( array_column( $tc_site_blocks, 'attrs' ), 'section' ) === array_keys( timonconfigs_home_sections() ), 'Home starter sections preserve the existing premium composition' );
	$tc_site_home = get_post( $tc_site_first['home'] );
	if ( $tc_site_home->post_content === timonconfigs_default_home_content() ) {
		$tc_site_expect( count( array_filter( parse_blocks( $tc_site_home->post_content ), static fn( array $block ): bool => 'timonconfigs/section' === $block['blockName'] ) ) === 10, 'installed Home retains all ten starter sections' );
	}
	$tc_site_legal_slugs = array_keys( TC_Site::legal_sections() );
	foreach ( $tc_site_legal_slugs as $slug ) {
		$starter = TC_Site::default_content( $slug );
		$tc_site_expect( str_contains( $starter, 'pending owner and legal review' ) && has_blocks( $starter ), 'legal starter is editable and clearly marked as a draft: ' . $slug );
	}

	$tc_site_flagship_args = array( 'post_type' => 'tc_config', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'meta_key' => '_tc_seeded_flagship', 'orderby' => 'ID', 'order' => 'ASC' );
	$tc_site_flagships_before = get_posts( $tc_site_flagship_args );
	TC_Catalog::seed_flagship_drafts();
	$tc_site_flagships_first = get_posts( $tc_site_flagship_args );
	TC_Catalog::seed_flagship_drafts();
	$tc_site_flagships_second = get_posts( $tc_site_flagship_args );
	$tc_site_expect( count( $tc_site_flagships_first ) === 3 && wp_list_pluck( $tc_site_flagships_first, 'ID' ) === wp_list_pluck( $tc_site_flagships_second, 'ID' ), 'three flagship structures seed once without duplicate posts' );
	$tc_site_flagship_ids_before = wp_list_pluck( $tc_site_flagships_before, 'ID' );
	foreach ( $tc_site_flagships_first as $flagship ) {
		if ( ! in_array( $flagship->ID, $tc_site_flagship_ids_before, true ) ) { $tc_site_post_ids[] = $flagship->ID; }
		$tc_site_expect( 'draft' === $flagship->post_status && 'coming-soon' === get_post_meta( $flagship->ID, 'tc_release_status', true ), 'unverified flagship scaffold is a draft, never an available release' );
		$tc_site_expect( '' === get_post_meta( $flagship->ID, 'tc_access_url', true ) && ! wp_get_object_terms( $flagship->ID, 'tc_platform' ), 'flagship scaffold has no invented file or platform compatibility' );
	}

	$tc_site_expect( false !== has_action( 'admin_post_tc_install_site', array( 'TC_Site', 'handle_install' ) ), 'setup POST handler is explicitly registered' );
	add_filter( 'wp_die_handler', $tc_site_die_filter, PHP_INT_MAX );
	wp_set_current_user( 0 );
	$tc_site_denied = false;
	try { TC_Site::handle_install(); } catch ( RuntimeException $error ) { $tc_site_denied = 'tc-site-test-wp-die:403' === $error->getMessage(); }
	$tc_site_expect( $tc_site_denied, 'anonymous setup handler is denied before any installation' );
	$tc_site_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$tc_site_expect( ! empty( $tc_site_admins ), 'an administrator exists for setup form tests' );
	wp_set_current_user( (int) $tc_site_admins[0] );
	$_REQUEST['_wpnonce'] = 'intentionally-invalid-site-test-nonce';
	$tc_site_nonce_denied = false;
	try { TC_Site::handle_install(); } catch ( RuntimeException $error ) { $tc_site_nonce_denied = 'tc-site-test-wp-die:403' === $error->getMessage(); }
	$tc_site_expect( $tc_site_nonce_denied, 'administrator setup still requires a valid nonce' );
	ob_start();
	TC_Site::render_setup_page();
	$tc_site_form = (string) ob_get_clean();
	preg_match( '/name="_wpnonce" value="([^"]+)"/', $tc_site_form, $tc_site_nonce_matches );
	$tc_site_expect( str_contains( $tc_site_form, 'value="tc_install_site"' ) && isset( $tc_site_nonce_matches[1] ) && (bool) wp_verify_nonce( $tc_site_nonce_matches[1], 'tc_install_site' ), 'setup form includes the correct action and valid anti-CSRF nonce' );

	echo 'Passed ' . $tc_site_checks . ' site installation assertions: page IDs, content/status/menu preservation, native blocks, draft scaffolds, and secured setup.' . PHP_EOL;
} finally {
	remove_filter( 'wp_die_handler', $tc_site_die_filter, PHP_INT_MAX );
	$_REQUEST = $tc_site_original_request;
	$_POST = $tc_site_original_post;
	wp_set_current_user( $tc_site_original_user );
	foreach ( $tc_site_post_ids as $id ) { wp_delete_post( $id, true ); }
	if ( $tc_site_menu_id ) { wp_delete_nav_menu( $tc_site_menu_id ); }
}
