<?php
/** Run only against disposable local/staging WordPress: wp eval-file tests/video-integration.php. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Videos' ) ) {
	require_once WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-videos.php';
}
TC_Videos::register_content_type();

$tc_video_assertions = 0;
$tc_video_ids = array();
$tc_video_attachments = array();
$tc_video_original_post = $_POST;
$tc_video_original_query = $GLOBALS['wp_query']; $tc_video_original_main = $GLOBALS['wp_the_query'];
$tc_video_mods_key = 'theme_mods_' . get_option( 'stylesheet' );
$tc_video_original_mods = get_option( $tc_video_mods_key, false );
$tc_video_force_fallback = false;
$tc_video_only_post = 0;
$tc_video_hidden_home = 0;
$tc_video_front_filter = static function ( mixed $value ) use ( &$tc_video_hidden_home ): mixed { return $tc_video_hidden_home ?: $value; };
$tc_video_show_front_filter = static fn(): string => 'page';
$tc_video_blank_preview = static fn(): string => '';
$tc_video_network_calls = 0;
$tc_video_no_network = static function () use ( &$tc_video_network_calls ): WP_Error { ++$tc_video_network_calls; return new WP_Error( 'tc_video_test_network', 'External requests are disabled during local video integration tests.' ); };
add_filter( 'pre_http_request', $tc_video_no_network, 1 );
$tc_video_empty_posts = static function ( mixed $posts, WP_Query $query ) use ( &$tc_video_force_fallback, &$tc_video_only_post ): mixed {
	if ( 'tc_video' !== $query->get( 'post_type' ) ) { return $posts; }
	if ( $tc_video_force_fallback ) { return array(); }
	return $tc_video_only_post ? array( get_post( $tc_video_only_post ) ) : $posts;
};
add_filter( 'posts_pre_query', $tc_video_empty_posts, 99, 2 );
$tc_video_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$tc_video_original_user = get_current_user_id();
$tc_video_expect = static function ( bool $condition, string $message ) use ( &$tc_video_assertions ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$tc_video_assertions;
};

try {
	$tc_video_expect( '' === TC_Videos::sanitize_video_url( 'https://youtube.com.evil.test/watch?v=abcdefghijk' ), 'Lookalike YouTube hosts are rejected.' );
	$tc_video_expect( '' === TC_Videos::sanitize_video_url( 'javascript:alert(1)' ), 'Script sources are rejected.' );
	$tc_video_expect( '' === TC_Videos::sanitize_video_url( array( 'bad' ) ), 'Array-shaped URL input is rejected safely.' );
	$tc_video_expect( '' === TC_Videos::sanitize_video_url( 'http://untrusted.example/test.mp4' ), 'Insecure remote media is rejected.' );
	$tc_video_expect( '' === TC_Videos::sanitize_video_url( 'https://name:password@example.test/test.mp4' ), 'Credentialed media URLs are rejected.' );
	$tc_video_expect( 'https://www.youtube.com/watch?v=abcdefghijk' === TC_Videos::sanitize_video_url( 'https://youtu.be/abcdefghijk?t=12' ), 'YouTube URLs normalize to the validated ID.' );
	$tc_video_expect( '' === TC_Videos::youtube_id( 'https://youtube.com/watch?v[]=abcdefghijk' ), 'Array-shaped YouTube query values are rejected.' );
	$tc_video_expect( home_url( '/apex.mp4' ) === TC_Videos::sanitize_video_url( home_url( '/apex.mp4' ) ), 'Same-origin local media works on disposable HTTP WordPress.' );
	$tc_video_expect( '' === TC_Videos::sanitize_captions_url( 'https://example.test/page.html' ), 'Captions must use VTT.' );
	$tc_video_expect( '' === TC_Videos::sanitize_upload_date( '2026-02-30T12:00:00+00:00' ), 'Invalid calendar dates cannot enter schema metadata.' );
	$tc_video_expect( '' === TC_Videos::sanitize_upload_date( '2026-10-21' ), 'Publication metadata needs a real time and time zone.' );
	$tc_video_expect( '2026-10-21T12:00:00+00:00' === TC_Videos::sanitize_upload_date( '2026-10-21T12:00:00Z' ), 'Known ISO dates normalize.' );
	$tc_video_expect( 'PT37S' === TC_Videos::sanitize_duration( 'PT37S' ), 'Real video duration is accepted.' );
	$tc_video_expect( '' === TC_Videos::sanitize_duration( 'PT0S' ) && '' === TC_Videos::sanitize_duration( '<script>' ), 'Empty or malformed durations are rejected.' );
	$tc_video_expect( 'inline' === TC_Videos::sanitize_mode( array() ), 'Malformed playback mode preserves inline default.' );
	$tc_video_expect( post_type_supports( 'tc_video', 'editor' ) && post_type_supports( 'tc_video', 'thumbnail' ) && get_post_type_object( 'tc_video' )->show_in_rest, 'Videos support Gutenberg, featured images and REST editing.' );
	$tc_video_expect( array_key_exists( 'tc_video_poster_alt', get_registered_meta_keys( 'post', 'tc_video' ) ), 'Poster alternative text is editable metadata.' );

	$tc_video_force_fallback = true;
	for ( $index = 1; $index <= 3; $index++ ) {
		foreach ( array( 'url', 'title', 'poster', 'device', 'description', 'poster_alt' ) as $field ) { remove_theme_mod( 'timonconfigs_video_' . $index . '_' . $field ); }
	}
	$stock = TC_Videos::get_public_videos();
	$tc_video_expect( 3 === count( $stock ) && 3 === count( array_unique( array_column( $stock, 'description' ) ) ), 'The actual three bundled clips have distinct descriptive copy.' );
	foreach ( $stock as $video ) {
		$tc_video_expect( '' !== $video['description'] && '' !== $video['poster_alt'], 'A bundled clip includes its own description and poster alternative text.' );
		$tc_video_expect( 576 === $video['poster_width'] && 1024 === $video['poster_height'], 'Bundled poster dimensions match the actual portrait JPEG.' );
		$tc_video_expect( false === $video['verified'] && '' === $video['upload_date'] && '' === $video['duration'], 'Bundled defaults do not invent verification, publication or duration metadata.' );
	}
	$tc_video_expect( str_contains( $stock[0]['description'], 'training dummy' ) && str_contains( $stock[1]['description'], 'montage' ) && str_contains( $stock[2]['description'], 'indoor and outdoor' ), 'Descriptions follow the inspected footage rather than device or performance claims.' );
	add_filter( 'theme_mod_timonconfigs_video_1_description', $tc_video_blank_preview );
	$tc_video_expect( $stock[0]['description'] === TC_Videos::get_public_videos()[0]['description'], 'A blank Customizer preview still uses the inspected description for its exact bundled source.' );
	remove_filter( 'theme_mod_timonconfigs_video_1_description', $tc_video_blank_preview );
	set_theme_mod( 'timonconfigs_video_1_url', home_url( '/owner-authored-video.mp4' ) );
	set_theme_mod( 'timonconfigs_video_1_description', 'Owner-authored description.' );
	set_theme_mod( 'timonconfigs_video_1_poster', 'https://assets.example.invalid/owner-poster.jpg' );
	set_theme_mod( 'timonconfigs_video_1_poster_alt', 'Owner-authored poster text.' );
	$custom = TC_Videos::get_public_videos()[0];
	$tc_video_expect( 'Owner-authored description.' === $custom['description'] && 'Owner-authored poster text.' === $custom['poster_alt'], 'Authored fallback descriptions and poster text are preserved.' );
	$tc_video_expect( 0 === $custom['poster_width'] && 0 === $custom['poster_height'], 'Unknown remote posters receive no invented dimensions.' );
	remove_theme_mod( 'timonconfigs_video_1_description' ); remove_theme_mod( 'timonconfigs_video_1_poster' ); remove_theme_mod( 'timonconfigs_video_1_poster_alt' );
	$custom = TC_Videos::get_public_videos()[0];
	$tc_video_expect( '' === $custom['description'] && '' === $custom['poster'], 'An unrelated authored video does not inherit bundled footage copy or a misleading stock poster.' );
	$tc_video_force_fallback = false;

	$draft_id = wp_insert_post( array( 'post_type' => 'tc_video', 'post_status' => 'draft', 'post_title' => 'TC integration draft video' ), true );
	$tc_video_expect( ! is_wp_error( $draft_id ), 'Draft fixture can be created.' );
	$tc_video_ids[] = $draft_id;
	update_post_meta( $draft_id, 'tc_video_url', home_url( '/tc-test-draft.mp4' ) );
	$tc_video_expect( ! in_array( $draft_id, array_column( TC_Videos::get_public_videos(), 'id' ), true ), 'Draft videos are excluded from the public showcase.' );

	$published_id = wp_insert_post( array( 'post_type' => 'tc_video', 'post_status' => 'publish', 'post_title' => 'TC integration public video', 'post_excerpt' => 'Temporary integration fixture.', 'menu_order' => -999 ), true );
	$tc_video_expect( ! is_wp_error( $published_id ), 'Published fixture can be created.' );
	$tc_video_ids[] = $published_id;
	$tc_video_only_post = $published_id;
	update_post_meta( $published_id, 'tc_video_url', home_url( '/tc-test-public.mp4' ) );
	$public = TC_Videos::get_public_videos();
	$tc_video_expect( $public && $published_id === $public[0]['id'] && count( $public ) <= 3, 'Published content replaces fallback and honors ordering/limit.' );
	$tc_video_expect( false === $public[0]['verified'] && '' === $public[0]['upload_date'], 'Publication never invents verified recording metadata.' );
	$tc_video_expect( 'Temporary integration fixture.' === $public[0]['description'], 'The owner-authored excerpt remains the public description.' );
	wp_update_post( array( 'ID' => $published_id, 'post_excerpt' => '', 'post_content' => '' ) );
	update_post_meta( $published_id, 'tc_video_url', get_template_directory_uri() . '/assets/video/apex-demo-02.mp4' );
	$tc_video_expect( $stock[1]['description'] === TC_Videos::get_public_videos()[0]['description'], 'A published entry with blank text receives the matching inspected bundled clip description.' );
	wp_update_post( array( 'ID' => $published_id, 'post_excerpt' => 'Temporary integration fixture.' ) );
	$tc_video_expect( 'Temporary integration fixture.' === TC_Videos::get_public_videos()[0]['description'], 'Authored copy still overrides the known bundled source description.' );
	update_post_meta( $published_id, 'tc_video_url', home_url( '/tc-test-public.mp4' ) );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$upload = wp_upload_bits( 'tc-video-poster-' . wp_generate_password( 10, false, false ) . '.jpg', null, file_get_contents( get_template_directory() . '/assets/images/apex-demo-01.jpg' ) );
	$tc_video_expect( ! $upload['error'], 'A disposable native Media Library poster file can be created.' );
	$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Temporary video poster', 'post_status' => 'inherit' ), $upload['file'], 0, true );
	$tc_video_expect( ! is_wp_error( $attachment ), 'The temporary poster has a native attachment entry.' );
	$tc_video_attachments[] = $attachment;
	wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $upload['file'] ) );
	update_post_meta( $attachment, '_wp_attachment_image_alt', 'Authored Media Library poster text.' );
	set_post_thumbnail( $published_id, $attachment );
	$public = TC_Videos::get_public_videos();
	$tc_video_expect( 'Authored Media Library poster text.' === $public[0]['poster_alt'], 'A featured poster uses its actual Media Library alternative text.' );
	$tc_video_expect( 576 === $public[0]['poster_width'] && 1024 === $public[0]['poster_height'], 'A local featured poster uses its own native dimensions.' );

	wp_set_current_user( (int) ( $tc_video_admin[0] ?? 0 ) );
	$_POST = array( 'tc_video_url' => home_url( '/tc-test-updated.mp4' ), 'tc_video_verified' => '1', 'tc_video_poster_alt' => 'An authored <b>poster description</b>.' );
	TC_Videos::save_meta( $published_id );
	$tc_video_expect( home_url( '/tc-test-public.mp4' ) === get_post_meta( $published_id, 'tc_video_url', true ), 'Missing nonce cannot mutate a video.' );
	$_POST['tc_video_details_nonce'] = wp_create_nonce( 'tc_save_video_details' );
	TC_Videos::save_meta( $published_id );
	$tc_video_expect( home_url( '/tc-test-updated.mp4' ) === get_post_meta( $published_id, 'tc_video_url', true ), 'An authorized nonce-backed edit persists video settings.' );
	$tc_video_expect( 'An authored poster description.' === get_post_meta( $published_id, 'tc_video_poster_alt', true ), 'Authorized poster alternative text is sanitized and saved.' );
	$public = TC_Videos::get_public_videos();
	$tc_video_expect( 'An authored poster description.' === $public[0]['poster_alt'], 'Explicit poster alternative text has precedence over attachment defaults.' );
	ob_start(); get_template_part( 'template-parts/video-cards' ); $rendered = ob_get_clean();
	$tc_video_expect( str_contains( $rendered, 'class="tc-video-description">Temporary integration fixture.' ) && str_contains( $rendered, 'aria-describedby=' ), 'Public video cards render their description and connect it to the native player.' );
	$tc_video_expect( ! str_contains( $rendered, '<video alt=' ) && str_contains( $rendered, 'Video poster: An authored poster description.' ), 'Native video posters have an accessible textual description without an invalid video alt attribute.' );
	$video_page = get_page_by_path( 'video-showcase' );
	$tc_video_expect( $video_page instanceof WP_Post, 'The existing video showcase page is available for schema checks.' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $video_page->ID ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$video_objects = static fn(): array => array_values( array_filter( timonconfigs_schema_graph(), static fn( array $schema ): bool => 'VideoObject' === ( $schema['@type'] ?? '' ) ) );
	$tc_video_expect( ! $video_objects(), 'Video schema remains absent until actual publication and duration metadata are provided.' );
	update_post_meta( $published_id, 'tc_video_upload_date', '2026-10-21T12:00:00+00:00' ); update_post_meta( $published_id, 'tc_video_duration', 'PT37S' );
	$schemas = $video_objects();
	$tc_video_expect( 1 === count( $schemas ) && 'Temporary integration fixture.' === $schemas[0]['description'], 'Explicitly verified and complete metadata emits a video object with the visible authored description.' );
	$tc_video_expect( is_callable( array( 'TC_SEO', 'rank_math_video_schema' ) ), 'The Core adapter can enrich the active SEO provider without a second script.' );
	$provider_data = array( 'WebSite' => array( '@type' => 'WebSite', 'name' => 'Owner-authored site name' ), 'WebPage' => array( '@type' => 'WebPage', 'description' => 'Owner-authored page description' ) );
	$adapted = TC_SEO::rank_math_video_schema( $provider_data );
	$tc_video_expect( $provider_data['WebSite'] === $adapted['WebSite'] && $provider_data['WebPage'] === $adapted['WebPage'], 'Existing provider nodes remain byte-for-byte authoritative.' );
	$added_videos = array_values( array_filter( $adapted, static fn( mixed $schema ): bool => is_array( $schema ) && 'VideoObject' === ( $schema['@type'] ?? '' ) ) );
	$tc_video_expect( 1 === count( $added_videos ) && $schemas[0] === $added_videos[0], 'The active provider receives the same eligible visible video object as the fallback.' );
	$tc_video_expect( $adapted === TC_SEO::rank_math_video_schema( $adapted ), 'Repeated provider filtering never duplicates video objects.' );
	$authored_video = array_merge( $provider_data, array( 'owner_video' => array( '@type' => 'VideoObject', 'name' => 'Owner video', 'contentUrl' => 'https://example.invalid/owner-video.mp4' ) ) );
	$tc_video_expect( $authored_video === TC_SEO::rank_math_video_schema( $authored_video ), 'An authored provider VideoObject retains complete manual schema ownership.' );
	$nested_video = array( '@graph' => array( array( '@type' => array( 'CreativeWork', 'VideoObject' ), 'name' => 'Nested authored video' ) ) );
	$tc_video_expect( $nested_video === TC_SEO::rank_math_video_schema( $nested_video ), 'Nested and array-shaped authored VideoObject types are preserved without duplicates.' );
	update_post_meta( $published_id, 'tc_video_verified', false );
	$tc_video_expect( $provider_data === TC_SEO::rank_math_video_schema( $provider_data ), 'Unverified video metadata adds nothing to the provider graph.' );
	update_post_meta( $published_id, 'tc_video_verified', true );
	$showcase_query = $GLOBALS['wp_query'];
	$GLOBALS['wp_query'] = new WP_Query(); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$tc_video_expect( ! timonconfigs_video_schema_items() && $provider_data === TC_SEO::rank_math_video_schema( $provider_data ), 'Pages without the visible video showcase do not receive video metadata.' );
	$tc_video_hidden_home = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Temporary Home without videos', 'post_name' => 'tc-video-hidden-home-' . wp_generate_password( 8, false, false ), 'post_content' => '<!-- wp:paragraph --><p>Temporary Home content without a videos section.</p><!-- /wp:paragraph -->' ), true );
	$tc_video_expect( ! is_wp_error( $tc_video_hidden_home ), 'The removed-section check has a disposable Home page.' );
	$tc_video_ids[] = $tc_video_hidden_home;
	add_filter( 'option_page_on_front', $tc_video_front_filter ); add_filter( 'option_show_on_front', $tc_video_show_front_filter );
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $tc_video_hidden_home ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$tc_video_expect( is_front_page() && ! timonconfigs_home_has_section( 'videos' ), 'The fixture really removes Home video visibility.' );
	$tc_video_expect( ! timonconfigs_video_schema_items() && $provider_data === TC_SEO::rank_math_video_schema( $provider_data ), 'Removing the Home videos section removes provider video metadata too.' );
	remove_filter( 'option_page_on_front', $tc_video_front_filter ); remove_filter( 'option_show_on_front', $tc_video_show_front_filter );
	$GLOBALS['wp_query'] = $showcase_query; $GLOBALS['wp_the_query'] = $showcase_query;
	wp_update_post( array( 'ID' => $published_id, 'post_excerpt' => '', 'post_content' => '' ) );
	$tc_video_expect( '' === TC_Videos::get_public_videos()[0]['description'], 'An unrelated authored video with blank content remains blank.' );
	$tc_video_expect( ! $video_objects(), 'A video without a visible description is excluded from video structured data.' );
	$provider = static fn(): bool => true; add_filter( 'timonconfigs_external_seo_active', $provider );
	ob_start(); timonconfigs_print_schema(); $provider_markup = ob_get_clean(); remove_filter( 'timonconfigs_external_seo_active', $provider );
	$tc_video_expect( '' === $provider_markup, 'An active external SEO provider retains schema ownership.' );
	$tc_video_expect( ! array_filter( timonconfigs_schema_graph(), static fn( array $schema ): bool => in_array( $schema['@type'] ?? '', array( 'Review', 'AggregateRating' ), true ) || isset( $schema['aggregateRating'] ) ), 'The theme does not add self-serving review stars to its own business schema.' );
	wp_set_current_user( 0 );
	$_POST['tc_video_url'] = home_url( '/unauthorized.mp4' );
	TC_Videos::save_meta( $published_id );
	$tc_video_expect( home_url( '/tc-test-updated.mp4' ) === get_post_meta( $published_id, 'tc_video_url', true ), 'Anonymous edits cannot mutate video data.' );
	$tc_video_expect( 0 === $tc_video_network_calls, 'Metadata, local posters and schema checks make no external HTTP requests.' );
	echo 'Video integration passed: ' . $tc_video_assertions . " assertions.\n";
} finally {
	$_POST = $tc_video_original_post;
	remove_filter( 'posts_pre_query', $tc_video_empty_posts, 99 );
	remove_filter( 'pre_http_request', $tc_video_no_network, 1 );
	remove_filter( 'option_page_on_front', $tc_video_front_filter ); remove_filter( 'option_show_on_front', $tc_video_show_front_filter );
	remove_filter( 'theme_mod_timonconfigs_video_1_description', $tc_video_blank_preview );
	$tc_video_only_post = 0;
	foreach ( $tc_video_ids as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $tc_video_attachments as $id ) { wp_delete_attachment( $id, true ); }
	false === $tc_video_original_mods ? delete_option( $tc_video_mods_key ) : update_option( $tc_video_mods_key, $tc_video_original_mods );
	$GLOBALS['wp_query'] = $tc_video_original_query; $GLOBALS['wp_the_query'] = $tc_video_original_main;
	wp_set_current_user( $tc_video_original_user );
}
