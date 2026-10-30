<?php
/** Reversible browser data on disposable localhost only; no private files or purchases. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new RuntimeException( 'Disposable localhost WordPress required.' );
}
$mode = getenv( 'TC_COLLECTION_FIXTURE' ) ?: 'setup';
$option = '_tc_collection_browser_fixture';
$marker = '_tc_collection_browser_fixture_token';
if ( ! in_array( $mode, array( 'setup', 'restore' ), true ) ) { throw new RuntimeException( 'Unknown collection fixture mode.' ); }
if ( 'restore' === $mode ) {
	$backup = get_option( $option, false );
	if ( ! is_array( $backup ) || empty( $backup['token'] ) || ! is_array( $backup['ids'] ?? null ) ) { throw new RuntimeException( 'No valid collection browser fixture backup exists.' ); }
	$removed = 0;
	foreach ( $backup['ids'] as $id ) {
		$post = get_post( (int) $id );
		if ( ! $post ) { continue; }
		if ( 'tc_config' !== $post->post_type || ! hash_equals( (string) $backup['token'], (string) get_post_meta( $post->ID, $marker, true ) ) ) {
			throw new RuntimeException( 'Refusing to delete an unmarked or changed browser fixture post: ' . (int) $id );
		}
		if ( ! wp_delete_post( $post->ID, true ) ) { throw new RuntimeException( 'Could not remove browser fixture post: ' . $post->ID ); }
		++$removed;
	}
	delete_option( $option );
	WP_CLI::success( sprintf( 'Restored collection browser fixture: %d marked configurations removed.', $removed ) );
	return;
}
if ( false !== get_option( $option, false ) ) { throw new RuntimeException( 'Restore the pending collection browser fixture before another setup.' ); }
if ( ! class_exists( 'TC_Collections' ) ) { throw new RuntimeException( 'Load the updated TimonConfigs Core first.' ); }

$routes = array( 'xim-matrix-apex-legends', 'xim-matrix-call-of-duty', 'cronus-zen-apex-legends', 'cronus-zen-call-of-duty', 'free-configs', 'xim-matrix-rust', 'xim-matrix-fortnite' );
$pages = array();
foreach ( $routes as $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status || ! TC_Collections::is_collection( $page ) ) { throw new RuntimeException( 'Provision the real collection page first: ' . $slug ); }
	$pages[ '/' . $slug . '/' ] = array( 'id' => $page->ID, 'h1' => wp_specialchars_decode( get_the_title( $page ), ENT_QUOTES ), 'title' => wp_specialchars_decode( (string) get_post_meta( $page->ID, 'tc_seo_title', true ), ENT_QUOTES ), 'description' => (string) get_post_meta( $page->ID, 'tc_seo_description', true ) );
}
$token = wp_generate_password( 32, false, false );
$ids = array(); $fixtures = array(); $counter = 0; $persisted = false;
try {
	$create = static function ( string $game, string $device, string $tier, string $input, string $release = 'available', string $suffix = '' ) use ( &$counter, &$ids, &$fixtures, $token, $marker ): void {
		++$counter;
		$title = sprintf( 'Collection Browser Fixture %02d', $counter ) . ( $suffix ? ' ' . $suffix : '' );
		$stamp = time() - 2 * $counter;
		$id = wp_insert_post( array(
			'post_type' => 'tc_config', 'post_status' => 'publish', 'post_title' => $title,
			'post_content' => 'Temporary local browser fixture. This is not a customer configuration and has no private file attached.',
			'post_date' => wp_date( 'Y-m-d H:i:s', $stamp ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $stamp ),
			'meta_input' => array( $marker => $token, 'tc_tier' => $tier, 'tc_release_status' => $release, 'tc_input_method' => $input, 'tc_config_version' => 'local-browser-fixture' ),
		), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$ids[] = $id;
		foreach ( array( 'tc_game' => $game, 'tc_device' => $device ) as $taxonomy => $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term instanceof WP_Term ) { throw new RuntimeException( 'Missing fixture taxonomy term: ' . $slug ); }
			$result = wp_set_object_terms( $id, array( $term->term_id ), $taxonomy );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
		}
		$fixtures[] = array( 'id' => $id, 'title' => $title, 'game' => $game, 'device' => $device, 'tier' => $tier, 'input' => $input, 'release' => $release );
	};
	for ( $number = 1; $number <= 14; ++$number ) { $create( 'apex-legends', 'xim-matrix', 0 === $number % 3 ? 'free' : 'monthly', 0 === $number % 2 ? 'mnk' : 'controller', 'available', 1 === $number ? 'Predator S30' : '' ); }
	$create( 'apex-legends', 'cronus-zen', 'free', 'controller' );
	$create( 'call-of-duty', 'xim-matrix', 'monthly', 'mnk' );
	$create( 'call-of-duty', 'cronus-zen', 'free', 'controller' );
	$create( 'rust', 'xim-matrix', 'monthly', 'controller', 'coming-soon' );
	foreach ( $pages as $route => &$details ) {
		$definition = TC_Collections::definition( $details['id'] );
		$details['indexable'] = TC_Collections::is_indexable( $definition );
		$details['game'] = $definition['game']; $details['device'] = $definition['device']; $details['tier'] = $definition['tier'];
	}
	unset( $details );
	$backup = array( 'token' => $token, 'ids' => $ids, 'home' => home_url( '/' ) );
	if ( ! add_option( $option, $backup, '', false ) ) { throw new RuntimeException( 'Could not save the reversible collection fixture backup.' ); }
	$persisted = true;
	WP_CLI::line( wp_json_encode( array( 'fixture_active' => true, 'home' => home_url( '/' ), 'pages' => $pages, 'configurations' => $fixtures ) ) );
	WP_CLI::success( sprintf( 'Collection browser fixture ready: %d marked configurations; no private files or purchases.', count( $ids ) ) );
} finally {
	if ( ! $persisted ) {
		foreach ( $ids as $id ) { if ( hash_equals( $token, (string) get_post_meta( $id, $marker, true ) ) ) { wp_delete_post( $id, true ); } }
	}
}
