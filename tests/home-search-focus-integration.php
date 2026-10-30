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
	$assert( 'XIM Matrix Apex Legends Configs | TimonConfigs' === $defaults['home']['title'], 'Home metadata targets the actual Apex/device offering.' );
	$hero = timonconfigs_render_section( array( 'section' => 'hero' ) );
	$assert( str_contains( $hero, 'XIM Matrix &amp; Cronus Zen Configs for Apex Legends' ), 'Default H1 targets device and game intent.' );
	$assert( str_contains( $hero, '/xim-matrix-apex-legends/' ) && str_contains( $hero, 'Explore XIM Matrix Apex Configs' ), 'Hero links to the dedicated game and device collection.' );
	$assert( ! str_contains( $hero, 'verified compatibility guides' ), 'Default copy does not claim unprovided verified compatibility.' );
	$authored = timonconfigs_render_section( array( 'section' => 'hero', 'title' => 'Owner authored hero', 'primaryLabel' => 'Owner CTA', 'primaryUrl' => home_url( '/contact/' ) ) );
	$assert( str_contains( $authored, 'Owner authored hero' ) && str_contains( $authored, 'Owner CTA' ) && str_contains( $authored, '/contact/' ), 'Authored Gutenberg content and destinations remain authoritative.' );
	$legacy = timonconfigs_render_section( array( 'section' => 'hero', 'primaryUrl' => home_url( '/apex-legends-configs/#xim-matrix' ) ) );
	$assert( str_contains( $legacy, esc_url( home_url( '/xim-matrix-apex-legends/' ) ) ) && ! str_contains( $legacy, '/apex-legends-configs/#xim-matrix' ), 'A saved same-site Gutenberg hero destination upgrades from the former device anchor.' );
	$legacy_external_url = 'https://example.invalid/apex-legends-configs/#xim-matrix';
	$legacy_external = timonconfigs_render_section( array( 'section' => 'hero', 'primaryUrl' => $legacy_external_url ) );
	$assert( str_contains( $legacy_external, esc_url( $legacy_external_url ) ), 'An external authored URL that resembles the old collection path is preserved.' );
	$filtered_legacy = home_url( '/apex-legends-configs/?input=mnk#xim-matrix' );
	$filtered_target = timonconfigs_collection_target( $filtered_legacy );
	$filtered_query = array(); wp_parse_str( (string) wp_parse_url( $filtered_target, PHP_URL_QUERY ), $filtered_query );
	$assert( wp_parse_url( $filtered_target, PHP_URL_PATH ) === wp_parse_url( home_url( '/xim-matrix-apex-legends/' ), PHP_URL_PATH ) && ! wp_parse_url( $filtered_target, PHP_URL_FRAGMENT ) && array( 'input' => 'mnk' ) === $filtered_query, 'Legacy destination conversion preserves the input filter and removes the obsolete device fragment.' );
	$saved_block = '<!-- wp:paragraph --><p><a href="' . esc_url( $filtered_legacy ) . '">Saved setup link</a> <a href="' . esc_url( $legacy_external_url ) . '">Independent destination</a></p><!-- /wp:paragraph -->';
	$rendered_block = do_blocks( $saved_block );
	$anchors = new WP_HTML_Tag_Processor( $rendered_block );
	$assert( $anchors->next_tag( array( 'tag_name' => 'A' ) ) && $filtered_target === $anchors->get_attribute( 'href' ), 'A saved Gutenberg paragraph link receives the same safe destination conversion at render time.' );
	$assert( $anchors->next_tag( array( 'tag_name' => 'A' ) ) && $legacy_external_url === $anchors->get_attribute( 'href' ), 'Rendering an old local Gutenberg link does not rewrite the neighbouring external link.' );

	set_theme_mod( 'timonconfigs_apex_cta_url', home_url( '/call-of-duty-configs/#cronus-zen' ) );
	$legacy_customizer = timonconfigs_render_section( array( 'section' => 'hero' ) );
	$assert( str_contains( $legacy_customizer, esc_url( home_url( '/cronus-zen-call-of-duty/' ) ) ), 'Saved Customizer device anchors receive the same safe destination upgrade.' );
	remove_theme_mod( 'timonconfigs_apex_cta_url' );
	set_theme_mod( 'timonconfigs_cta_image', 'https://example.invalid/previous-customizer-image.webp' );
	$cta = timonconfigs_render_section( array( 'section' => 'cta', 'image' => 'https://example.invalid/block-background.webp', 'title' => 'Owner community heading' ) );
	$assert( ! str_contains( $cta, '<img' ) && ! str_contains( $cta, 'background.webp' ) && ! str_contains( $cta, 'previous-customizer-image' ), 'Final CTA excludes image attributes and old customizer artwork.' );
	$assert( str_contains( $cta, 'Owner community heading' ) && str_contains( $cta, 'Join Discord' ), 'Final CTA retains authored heading and actions.' );
	$search = timonconfigs_render_section( array( 'section' => 'search' ) );
	$popular_destinations = array( 'XIM Matrix Apex Legends' => 'xim-matrix-apex-legends', 'XIM Matrix Call of Duty' => 'xim-matrix-call-of-duty', 'Cronus Zen Apex Legends' => 'cronus-zen-apex-legends', 'Cronus Zen Call of Duty' => 'cronus-zen-call-of-duty', 'Free Configs' => 'free-configs' );
	foreach ( $popular_destinations as $label => $slug ) {
		$assert( str_contains( $search, 'href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">' . $label . '</a>' ), 'Popular search reaches its dedicated clean URL: ' . $label );
	}
	$assert( ! str_contains( $search, '-configs/#xim-matrix' ) && ! str_contains( $search, '-configs/#cronus-zen' ), 'Popular searches no longer link to device fragments.' );
	$games = timonconfigs_render_section( array( 'section' => 'games' ) );
	$assert( str_contains( $games, 'XIM Matrix Apex Legends Configs' ) && str_contains( $games, 'Call of Duty Configs for XIM Matrix &amp; Cronus Zen' ), 'Home contains both topical H2 sections.' );
	foreach ( array( 'apex-legends', 'call-of-duty' ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'tc_game' );
		$assert( $term instanceof WP_Term, 'Existing canonical game collection exists: ' . $slug );
		$GLOBALS['wp_query'] = new WP_Query( array( 'tc_game' => $slug ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		ob_start(); get_template_part( 'template-parts/collection-intent' ); $collection = ob_get_clean();
		$assert( str_contains( $collection, 'id="xim-matrix"' ) && str_contains( $collection, 'id="cronus-zen"' ), 'Canonical collection provides both real device anchors: ' . $slug );
		$assert( str_contains( $collection, esc_url( home_url( '/xim-matrix-' . $slug . '/' ) ) ) && str_contains( $collection, esc_url( home_url( '/cronus-zen-' . $slug . '/' ) ) ), 'Device actions reach dedicated release collections: ' . $slug );
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
