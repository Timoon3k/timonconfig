<?php
/** Disposable WordPress only. Also run with --skip-plugins=timonconfigs-core to cover inactive Core. */
defined( 'ABSPATH' ) || exit;

$tc_notice_checks = 0;
$tc_notice_original_user = get_current_user_id();
$tc_notice_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$tc_notice_ids = array();
$tc_notice_menu = 0;
$tc_notice_option_snapshot = array( 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => get_option( 'page_on_front' ), 'theme_mods' => get_theme_mods() );
$tc_notice_overrides = array();
$tc_notice_hidden_slug = '';
$tc_notice_menu_locations = array();
$tc_notice_expect = static function ( bool $condition, string $message ) use ( &$tc_notice_checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$tc_notice_checks;
};
$tc_notice_render = static function (): string {
	ob_start();
	timonconfigs_setup_notice();
	return (string) ob_get_clean();
};
$tc_notice_option_filter = static function ( mixed $value, string $option ) use ( &$tc_notice_overrides ): mixed {
	return array_key_exists( $option, $tc_notice_overrides ) ? $tc_notice_overrides[ $option ] : $value;
};
$tc_notice_menu_filter = static function () use ( &$tc_notice_menu_locations ): array { return $tc_notice_menu_locations; };
$tc_notice_pages_filter = null;

try {
	$tc_notice_expect( ! empty( $tc_notice_admins ), 'A disposable administrator must exist.' );
	wp_set_current_user( 0 );
	$tc_notice_expect( '' === $tc_notice_render(), 'Anonymous requests never receive administrator setup guidance.' );
	$subscriber_id = wp_insert_user( array( 'user_login' => 'tc_notice_' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 30 ), 'role' => 'subscriber' ) );
	$tc_notice_expect( ! is_wp_error( $subscriber_id ), 'A disposable subscriber can be created.' );
	$tc_notice_ids['user'] = (int) $subscriber_id;
	wp_set_current_user( $subscriber_id );
	$tc_notice_expect( '' === $tc_notice_render(), 'Subscribers cannot see administrative setup links.' );
	wp_set_current_user( (int) $tc_notice_admins[0] );

	if ( ! class_exists( 'TC_Site' ) || ! is_callable( array( 'TC_Site', 'pages' ) ) || ! is_callable( array( 'TC_Site', 'install' ) ) ) {
		$html = $tc_notice_render();
		$tc_notice_expect( str_contains( $html, 'current TimonConfigs Core plugin' ) && str_contains( $html, esc_url( admin_url( 'plugins.php' ) ) ), 'Inactive or outdated Core points administrators to the existing Plugins screen.' );
		$tc_notice_expect( ! str_contains( $html, 'tools.php?page=' ), 'Missing Core never links to an unavailable setup endpoint.' );
	} else {
		$required_slugs = array_keys( TC_Site::pages() );
		$actual_pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'post_parent' => 0, 'post_name__in' => $required_slugs, 'posts_per_page' => count( $required_slugs ) ) );
		$tc_notice_expect( ! array_diff( $required_slugs, wp_list_pluck( $actual_pages, 'post_name' ) ), 'Run the explicit site installer before this integration test.' );
		$tc_notice_pages_filter = static function ( mixed $posts, WP_Query $query ) use ( &$tc_notice_hidden_slug, $required_slugs, $actual_pages ): mixed {
			if ( $query->get( 'post_name__in' ) === $required_slugs ) {
				return array_values( array_filter( $actual_pages, static fn( WP_Post $page ): bool => $page->post_name !== $tc_notice_hidden_slug ) );
			}
			return $posts;
		};
		add_filter( 'posts_pre_query', $tc_notice_pages_filter, 10, 2 );
		$front_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'LOCAL SETUP NOTICE FIXTURE', 'post_name' => 'tc-setup-notice-fixture', 'post_status' => 'publish', 'post_content' => 'Disposable custom front page, not production content.' ), true );
		$tc_notice_expect( ! is_wp_error( $front_id ), 'A custom homepage fixture can be created.' );
		$tc_notice_ids['page'] = $front_id;
		$tc_notice_menu = wp_create_nav_menu( 'TC setup notice ' . wp_generate_password( 8, false ) );
		$tc_notice_expect( ! is_wp_error( $tc_notice_menu ), 'A disposable menu can be created.' );
		$tc_notice_menu_locations = array( 'primary' => $tc_notice_menu, 'footer' => $tc_notice_menu );
		$tc_notice_overrides = array( 'show_on_front' => 'page', 'page_on_front' => $front_id );
		add_filter( 'pre_option_show_on_front', $tc_notice_option_filter, 10, 2 );
		add_filter( 'pre_option_page_on_front', $tc_notice_option_filter, 10, 2 );
		add_filter( 'theme_mod_nav_menu_locations', $tc_notice_menu_filter );
		$tc_notice_expect( '' === $tc_notice_render(), 'Any valid custom static homepage is accepted without a setup warning.' );

		$tc_notice_overrides['show_on_front'] = 'posts';
		$html = $tc_notice_render();
		$tc_notice_expect( str_contains( $html, 'Choose a published static homepage' ) && str_contains( $html, esc_url( admin_url( 'options-reading.php' ) ) ) && str_contains( $html, esc_url( admin_url( 'tools.php?page=timonconfigs-setup' ) ) ), 'A posts front page points to Tools and Reading without changing options.' );
		$tc_notice_overrides = array( 'show_on_front' => 'page', 'page_on_front' => 0 );
		$tc_notice_expect( str_contains( $tc_notice_render(), 'Choose a published static homepage' ), 'A missing static homepage is detected.' );
		$tc_notice_overrides['page_on_front'] = $front_id;
		$tc_notice_hidden_slug = 'pricing';
		$tc_notice_expect( str_contains( $tc_notice_render(), 'Some required pages are missing.' ), 'Missing required pages produce setup guidance.' );
		$tc_notice_hidden_slug = '';
		$tc_notice_menu_locations = array();
		$html = $tc_notice_render();
		$tc_notice_expect( str_contains( $html, 'Assign the Primary and Footer' ) && str_contains( $html, esc_url( admin_url( 'nav-menus.php' ) ) ), 'Unassigned menus point to the real navigation editor.' );
		$tc_notice_menu_locations = array( 'primary' => PHP_INT_MAX, 'footer' => $tc_notice_menu );
		$tc_notice_expect( str_contains( $tc_notice_render(), 'Assign the Primary and Footer' ), 'A stale menu ID is detected instead of being treated as assigned navigation.' );
		$tc_notice_expect( 'Disposable custom front page, not production content.' === get_post_field( 'post_content', $front_id ), 'Setup guidance never overwrites authored homepage content.' );
	}
} finally {
	remove_filter( 'pre_option_show_on_front', $tc_notice_option_filter, 10 );
	remove_filter( 'pre_option_page_on_front', $tc_notice_option_filter, 10 );
	remove_filter( 'theme_mod_nav_menu_locations', $tc_notice_menu_filter );
	if ( $tc_notice_pages_filter ) { remove_filter( 'posts_pre_query', $tc_notice_pages_filter, 10 ); }
	if ( ! empty( $tc_notice_ids['page'] ) ) { wp_delete_post( $tc_notice_ids['page'], true ); }
	if ( $tc_notice_menu && ! is_wp_error( $tc_notice_menu ) ) { wp_delete_nav_menu( $tc_notice_menu ); }
	if ( ! empty( $tc_notice_ids['user'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $tc_notice_ids['user'] );
	}
	wp_set_current_user( $tc_notice_original_user );
}
$tc_notice_expect( $tc_notice_option_snapshot === array( 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => get_option( 'page_on_front' ), 'theme_mods' => get_theme_mods() ), 'All original reading options and theme settings remain unchanged after fixture cleanup.' );
WP_CLI::success( sprintf( 'Setup notice integration: %d assertions passed.', $tc_notice_checks ) );
