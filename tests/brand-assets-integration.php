<?php
/** Bundled artwork, explicit metadata import and SEO ownership. Disposable localhost only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }

$checks = 0; $fixture = 0; $attachment_backups = array();
$original_user = get_current_user_id();
$original_query = $GLOBALS['wp_query']; $original_main = $GLOBALS['wp_the_query'];
$original_post = $_POST; $original_request = $_REQUEST; $original_method = $_SERVER['REQUEST_METHOD'] ?? null;
$original_mods = get_option( 'theme_mods_' . get_option( 'stylesheet' ) );
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Brand assets: ' . $message ); } ++$checks; };
$marked_ids = static function (): array {
	return array_map( 'intval', get_posts( array( 'post_type' => 'attachment', 'post_status' => array( 'inherit', 'publish', 'private', 'draft', 'trash' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => '_timonconfigs_brand_asset' ) ) );
};
$before_ids = $marked_ids();
$hero_override = ''; $hero_alt_override = ''; $provider = false; $http_calls = 0;
// Mutable references let this exercise Customizer defaults without changing stored settings.
$hero_filter = static function ( mixed $value ) use ( &$hero_override ): mixed { return $hero_override; };
$alt_filter = static function ( mixed $value ) use ( &$hero_alt_override ): mixed { return $hero_alt_override; };
$provider_filter = static function ( mixed $value ) use ( &$provider ): bool { return $provider; };
$no_http = static function ( mixed $preempt ) use ( &$http_calls ): WP_Error { ++$http_calls; return new WP_Error( 'tc_brand_test_network', 'No external requests in artwork integration tests.' ); };
$no_upload = static function ( array $caps ): array { $caps['upload_files'] = false; return $caps; };
$die_filter = static function (): callable { return static function (): void { throw new RuntimeException( 'Brand import nonce rejected.' ); }; };
$front_filter = static function ( mixed $value ) use ( &$fixture ): int { return $fixture; };
$show_front_filter = static fn( mixed $value ): string => 'page';
$no_logo_filter = static fn( mixed $value ): int => 0;
$navigate = static function ( int $id ): void {
	$query = new WP_Query(); $GLOBALS['wp_query'] = $query; $GLOBALS['wp_the_query'] = $query;
	$query->query( array( 'page_id' => $id ) );
};
add_filter( 'theme_mod_timonconfigs_hero_image', $hero_filter );
add_filter( 'theme_mod_timonconfigs_hero_alt', $alt_filter );
add_filter( 'timonconfigs_external_seo_active', $provider_filter, 99 );
add_filter( 'pre_http_request', $no_http, 99 );

try {
	$assert( function_exists( 'timonconfigs_brand_assets' ), 'The theme loads the artwork helper.' );
	$assets = timonconfigs_brand_assets();
	foreach ( array( 'emblem', 'hero' ) as $key ) {
		$asset = $assets[ $key ]; $dimensions = getimagesize( $asset['path'] );
		$assert( $asset['available'] && $asset['width'] === $dimensions[0] && $asset['height'] === $dimensions[1], 'Actual bundled dimensions are used: ' . $key );
		foreach ( array( 'title', 'alt', 'caption', 'description' ) as $field ) { $assert( '' !== trim( $asset[ $field ] ), 'Editable image metadata is populated: ' . $key . '/' . $field ); }
	}
	$assert( str_contains( $assets['hero']['srcset'], '640w' ) && str_contains( $assets['hero']['srcset'], '960w' ), 'Responsive variants are included with their measured widths.' );
	$assert( '' === timonconfigs_brand_asset_file( '../images/apex-legends-crimson-hero.webp' ) && '' === timonconfigs_brand_asset_file( '../../functions.php' ), 'Only basename image files in the bundle are accepted.' );
	$assert( str_contains( strtolower( $assets['hero']['description'] ), 'illustration' ), 'The artwork is accurately described as an illustration.' );
	$default = timonconfigs_hero_media();
	$assert( $default['src'] === $assets['hero']['src'] && $default['alt'] === $assets['hero']['alt'], 'Automatic hero defaults use the new image and its accurate alternative text.' );
	foreach ( array( 'hero-reference-wide.webp', 'hero-premium.webp' ) as $stock ) {
		$resolved = timonconfigs_hero_media( array( 'image' => get_template_directory_uri() . '/assets/images/' . $stock ) );
		$assert( $resolved['src'] === $default['src'], 'Known theme stock artwork is upgraded at render time: ' . $stock );
	}
	$custom = 'https://images.example.test/hero-reference-wide.webp';
	$resolved = timonconfigs_hero_media( array( 'image' => $custom ) );
	$assert( $resolved['src'] === $custom && '' === $resolved['srcset'] && '' === $resolved['alt'] && 0 === $resolved['width'] && 0 === $resolved['height'], 'An unknown authored image retains its URL and receives no invented dimensions or artwork alternative text.' );
	$resolved = timonconfigs_hero_media( array( 'image' => $custom, 'imageAlt' => '<b>Owner artwork</b>' ) );
	$assert( 'Owner artwork' === $resolved['alt'], 'Explicit custom alternative text is retained and sanitized.' );
	$assert( '' === timonconfigs_hero_media( array( 'alt' => '' ) )['alt'], 'An explicitly decorative block image keeps empty alternative text.' );
	$assert( $default['src'] === timonconfigs_hero_media( array( 'image' => 'javascript:alert(1)' ) )['src'], 'Unsupported image protocols fall back to valid bundled artwork.' );
	$hero_override = $custom; $hero_alt_override = 'Custom hero from Customizer';
	$assert( $custom === timonconfigs_hero_media()['src'] && $hero_alt_override === timonconfigs_hero_media()['alt'], 'An authored Customizer image and alternative text are preserved.' );
	$hero_override = ''; $hero_alt_override = '';
	$assert( $before_ids === $marked_ids() && $original_mods === get_option( 'theme_mods_' . get_option( 'stylesheet' ) ), 'Rendering artwork never imports media or rewrites theme settings.' );

	wp_set_current_user( 0 );
	$denied = timonconfigs_import_brand_assets();
	$assert( is_wp_error( $denied ) && 'tc_brand_forbidden' === $denied->get_error_code(), 'Unauthenticated callers cannot import images.' );
	$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$assert( ! empty( $administrators ), 'An existing local administrator is available.' );
	wp_set_current_user( (int) $administrators[0] );
	add_filter( 'user_has_cap', $no_upload, 99 );
	$denied = timonconfigs_import_brand_assets();
	remove_filter( 'user_has_cap', $no_upload, 99 );
	$assert( is_wp_error( $denied ), 'Media upload capability is required in addition to administrator permission.' );
	$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array( 'action' => 'timonconfigs_import_brand_assets', '_wpnonce' => 'invalid-brand-import-nonce' ); $_REQUEST = $_POST;
	add_filter( 'wp_die_handler', $die_filter );
	$rejected = false;
	try { timonconfigs_handle_import_brand_assets(); } catch ( RuntimeException $exception ) { $rejected = 'Brand import nonce rejected.' === $exception->getMessage(); }
	remove_filter( 'wp_die_handler', $die_filter );
	$assert( $rejected && $before_ids === $marked_ids(), 'The explicit HTTP import action rejects an invalid nonce before writing media.' );
	$ids = timonconfigs_import_brand_assets();
	$assert( ! is_wp_error( $ids ) && 2 === count( $ids ), 'The explicit administrator import registers both public images.' );
	foreach ( $ids as $key => $id ) {
		$post = get_post( $id ); $metadata = wp_get_attachment_metadata( $id );
		$attachment_backups[ $id ] = array( 'post' => array( 'ID' => $id, 'post_title' => $post->post_title, 'post_excerpt' => $post->post_excerpt, 'post_content' => $post->post_content ), 'alt_exists' => metadata_exists( 'post', $id, '_wp_attachment_image_alt' ), 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$assert( 'attachment' === $post->post_type && is_file( get_attached_file( $id ) ), 'The imported entry has a real native uploads file: ' . $key );
		$assert( is_array( $metadata ) && ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ), 'WordPress generates native image metadata: ' . $key );
		if ( ! in_array( $id, $before_ids, true ) ) {
			$assert( $assets[ $key ]['title'] === $post->post_title && $assets[ $key ]['caption'] === $post->post_excerpt && $assets[ $key ]['description'] === $post->post_content && $assets[ $key ]['alt'] === get_post_meta( $id, '_wp_attachment_image_alt', true ), 'All four image fields are imported from the manifest: ' . $key );
		}
	}
	$assert( $ids === timonconfigs_import_brand_assets(), 'Repeated import reuses marked attachment IDs.' );
	$edited_id = $ids['hero'];
	wp_update_post( array( 'ID' => $edited_id, 'post_title' => 'Authored title', 'post_excerpt' => 'Authored caption', 'post_content' => 'Authored description' ) );
	update_post_meta( $edited_id, '_wp_attachment_image_alt', 'Authored alternative text' );
	$again = timonconfigs_import_brand_assets(); $edited = get_post( $edited_id );
	$assert( $again === $ids && 'Authored title' === $edited->post_title && 'Authored caption' === $edited->post_excerpt && 'Authored description' === $edited->post_content && 'Authored alternative text' === get_post_meta( $edited_id, '_wp_attachment_image_alt', true ), 'Later imports preserve all four owner-authored image fields.' );
	$local_image = timonconfigs_hero_media( array( 'image' => wp_get_attachment_url( $edited_id ), 'alt' => 'Local authored artwork' ) );
	$local_metadata = wp_get_attachment_metadata( $edited_id );
	$assert( $local_image['width'] === (int) $local_metadata['width'] && $local_image['height'] === (int) $local_metadata['height'], 'Authored local attachment dimensions are read from its own metadata.' );
	$assert( 'Authored alternative text' === timonconfigs_hero_media( array( 'image' => wp_get_attachment_url( $edited_id ) ) )['alt'], 'A selected Media Library image uses its authored alternative text unless explicitly overridden.' );

	$fixture = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Temporary brand SEO fixture', 'post_name' => 'temporary-brand-seo-' . wp_generate_password( 8, false, false ), 'post_content' => 'Temporary public artwork metadata verification.' ), true );
	$assert( ! is_wp_error( $fixture ) && $fixture > 0, 'A disposable SEO page is available.' );
	$navigate( $fixture );
	$assert( $assets['hero']['src'] === timonconfigs_social_image(), 'The unconfigured social image falls back to the new hero.' );
	update_post_meta( $fixture, 'tc_seo_image', $custom );
	$assert( $custom === timonconfigs_social_image(), 'A custom social image has precedence over the theme default.' );
	delete_post_meta( $fixture, 'tc_seo_image' ); set_post_thumbnail( $fixture, $ids['emblem'] );
	$assert( get_the_post_thumbnail_url( $fixture, 'large' ) === timonconfigs_social_image(), 'Featured artwork has precedence over the theme default.' );
	delete_post_thumbnail( $fixture );
	ob_start(); timonconfigs_print_metadata(); $html = ob_get_clean();
	$assert( 1 === substr_count( $html, 'property="og:image"' ) && str_contains( $html, 'content="' . esc_attr( $assets['hero']['src'] ) . '"' ), 'Fallback sharing markup emits one selected hero image.' );
	$assert( str_contains( $html, 'property="og:image:width" content="' . $assets['hero']['width'] . '"' ) && str_contains( $html, 'property="og:image:alt" content="' . esc_attr( $assets['hero']['alt'] ) . '"' ), 'Fallback sharing fields include measured dimensions and accurate alternative text.' );
	$provider = true;
	ob_start(); timonconfigs_print_metadata(); timonconfigs_print_schema(); $external_html = ob_get_clean();
	$assert( '' === $external_html, 'An external SEO provider retains ownership without duplicate Open Graph or JSON-LD output.' );
	$provider = false;
	add_filter( 'option_page_on_front', $front_filter ); add_filter( 'option_show_on_front', $show_front_filter ); add_filter( 'theme_mod_custom_logo', $no_logo_filter );
	$navigate( $fixture );
	$organizations = array_values( array_filter( timonconfigs_schema_graph(), static fn( array $schema ): bool => 'Organization' === $schema['@type'] ) );
	$assert( 1 === count( $organizations ) && $assets['emblem']['src'] === $organizations[0]['logo'], 'The homepage fallback organization uses the emblem when no custom logo exists.' );
	$assert( $original_mods === get_option( 'theme_mods_' . get_option( 'stylesheet' ) ), 'The explicit import preserves stored logo and hero settings.' );
	$assert( 0 === $http_calls, 'Rendering, metadata generation and public image import make no external HTTP requests.' );
	WP_CLI::success( "Brand artwork integration: $checks assertions passed." );
} finally {
	remove_filter( 'theme_mod_timonconfigs_hero_image', $hero_filter ); remove_filter( 'theme_mod_timonconfigs_hero_alt', $alt_filter );
	remove_filter( 'timonconfigs_external_seo_active', $provider_filter, 99 ); remove_filter( 'pre_http_request', $no_http, 99 );
	remove_filter( 'user_has_cap', $no_upload, 99 ); remove_filter( 'wp_die_handler', $die_filter );
	remove_filter( 'option_page_on_front', $front_filter ); remove_filter( 'option_show_on_front', $show_front_filter ); remove_filter( 'theme_mod_custom_logo', $no_logo_filter );
	if ( is_int( $fixture ) && $fixture > 0 ) { wp_delete_post( $fixture, true ); }
	foreach ( $attachment_backups as $id => $backup ) {
		if ( ! in_array( (int) $id, $before_ids, true ) ) { continue; }
		wp_update_post( $backup['post'] );
		if ( $backup['alt_exists'] ) { update_post_meta( $id, '_wp_attachment_image_alt', $backup['alt'] ); } else { delete_post_meta( $id, '_wp_attachment_image_alt' ); }
	}
	foreach ( array_diff( $marked_ids(), $before_ids ) as $id ) { wp_delete_attachment( $id, true ); }
	wp_set_current_user( $original_user );
	$GLOBALS['wp_query'] = $original_query; $GLOBALS['wp_the_query'] = $original_main;
	$_POST = $original_post; $_REQUEST = $original_request;
	if ( null === $original_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $original_method; }
}
