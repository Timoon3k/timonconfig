<?php
/** Targeted editorial SEO/CTA regression. Disposable local WordPress only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
$checks = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$mods_option = 'theme_mods_' . get_option( 'stylesheet' );
$mods = get_option( $mods_option, null );
$original_query = $GLOBALS['wp_query']; $original_main = $GLOBALS['wp_the_query'];
try {
	foreach ( array( 'hero_title', 'hero_copy', 'apex_cta', 'apex_cta_url' ) as $key ) { remove_theme_mod( 'timonconfigs_' . $key ); }
	$defaults = timonconfigs_seo_page_defaults();
	$assert( 'XIM Matrix & Cronus Zen Configs for Apex Legends | TimonConfigs' === $defaults['home']['title'], 'Home metadata targets the actual Apex/device offering.' );
	$hero = timonconfigs_render_section( array( 'section' => 'hero' ) );
	$assert( str_contains( $hero, 'XIM Matrix &amp; Cronus Zen Configs for Apex Legends' ), 'Default H1 targets device and game intent.' );
	$assert( str_contains( $hero, '/apex-legends-configs/#xim-matrix' ) && str_contains( $hero, 'Explore XIM Matrix Apex Configs' ), 'Hero links to the existing useful canonical collection anchor.' );
	$assert( ! str_contains( $hero, 'verified compatibility guides' ), 'Default copy does not claim unprovided verified compatibility.' );
	$authored = timonconfigs_render_section( array( 'section' => 'hero', 'title' => 'Owner authored hero', 'primaryLabel' => 'Owner CTA', 'primaryUrl' => home_url( '/contact/' ) ) );
	$assert( str_contains( $authored, 'Owner authored hero' ) && str_contains( $authored, 'Owner CTA' ) && str_contains( $authored, '/contact/' ), 'Authored Gutenberg content and destinations remain authoritative.' );
	set_theme_mod( 'timonconfigs_cta_image', 'https://example.invalid/previous-customizer-image.webp' );
	$cta = timonconfigs_render_section( array( 'section' => 'cta', 'image' => 'https://example.invalid/block-background.webp', 'title' => 'Owner community heading' ) );
	$assert( ! str_contains( $cta, '<img' ) && ! str_contains( $cta, 'background.webp' ) && ! str_contains( $cta, 'previous-customizer-image' ), 'Final CTA excludes image attributes and old customizer artwork.' );
	$assert( str_contains( $cta, 'Owner community heading' ) && str_contains( $cta, 'Join Discord' ), 'Final CTA retains authored heading and actions.' );
	$search = timonconfigs_render_section( array( 'section' => 'search' ) );
	foreach ( array( 'XIM Matrix Apex Legends', 'XIM Matrix Call of Duty', 'Cronus Zen Apex Legends', 'Cronus Zen Call of Duty' ) as $label ) { $assert( str_contains( $search, $label ), 'Relevant topical search link: ' . $label ); }
	$games = timonconfigs_render_section( array( 'section' => 'games' ) );
	$assert( str_contains( $games, 'XIM Matrix Apex Legends Configs' ) && str_contains( $games, 'Call of Duty Configs for XIM Matrix &amp; Cronus Zen' ), 'Home contains both topical H2 sections.' );
	foreach ( array( 'apex-legends', 'call-of-duty' ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'tc_game' );
		$assert( $term instanceof WP_Term, 'Existing canonical game collection exists: ' . $slug );
		$GLOBALS['wp_query'] = new WP_Query( array( 'tc_game' => $slug ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		ob_start(); get_template_part( 'template-parts/collection-intent' ); $collection = ob_get_clean();
		$assert( str_contains( $collection, 'id="xim-matrix"' ) && str_contains( $collection, 'id="cronus-zen"' ), 'Canonical collection provides both real device anchors: ' . $slug );
		$assert( str_contains( $collection, 'device=xim-matrix' ) && str_contains( $collection, 'device=cronus-zen' ), 'Device actions reach existing filtered releases: ' . $slug );
		$assert( str_contains( $collection, 'No firmware compatibility is implied' ), 'Collection distinguishes setup guidance from verified version claims.' );
	}
	$external = static fn() => true; add_filter( 'timonconfigs_external_seo_active', $external );
	ob_start(); timonconfigs_print_metadata(); timonconfigs_print_schema(); $head = ob_get_clean(); remove_filter( 'timonconfigs_external_seo_active', $external );
	$assert( '' === $head, 'Existing external SEO provider ownership still suppresses fallback metadata.' );
	WP_CLI::success( "Home search-focus integration: $checks assertions passed." );
} finally {
	null === $mods ? delete_option( $mods_option ) : update_option( $mods_option, $mods );
	$GLOBALS['wp_query'] = $original_query; $GLOBALS['wp_the_query'] = $original_main;
}
