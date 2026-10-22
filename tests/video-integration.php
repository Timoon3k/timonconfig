<?php
/** Run only against disposable local/staging WordPress: wp eval-file tests/video-integration.php. */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'TC_Videos' ) ) {
	require_once WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-videos.php';
}
TC_Videos::register_content_type();

$tc_video_assertions = 0;
$tc_video_ids = array();
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

	$draft_id = wp_insert_post( array( 'post_type' => 'tc_video', 'post_status' => 'draft', 'post_title' => 'TC integration draft video' ), true );
	$tc_video_expect( ! is_wp_error( $draft_id ), 'Draft fixture can be created.' );
	$tc_video_ids[] = $draft_id;
	update_post_meta( $draft_id, 'tc_video_url', home_url( '/tc-test-draft.mp4' ) );
	$tc_video_expect( ! in_array( $draft_id, array_column( TC_Videos::get_public_videos(), 'id' ), true ), 'Draft videos are excluded from the public showcase.' );

	$published_id = wp_insert_post( array( 'post_type' => 'tc_video', 'post_status' => 'publish', 'post_title' => 'TC integration public video', 'post_excerpt' => 'Temporary integration fixture.', 'menu_order' => -999 ), true );
	$tc_video_expect( ! is_wp_error( $published_id ), 'Published fixture can be created.' );
	$tc_video_ids[] = $published_id;
	update_post_meta( $published_id, 'tc_video_url', home_url( '/tc-test-public.mp4' ) );
	$public = TC_Videos::get_public_videos();
	$tc_video_expect( $public && $published_id === $public[0]['id'] && count( $public ) <= 3, 'Published content replaces fallback and honors ordering/limit.' );
	$tc_video_expect( false === $public[0]['verified'] && '' === $public[0]['upload_date'], 'Publication never invents verified recording metadata.' );

	wp_set_current_user( (int) ( $tc_video_admin[0] ?? 0 ) );
	$_POST = array( 'tc_video_url' => home_url( '/tc-test-updated.mp4' ), 'tc_video_verified' => '1' );
	TC_Videos::save_meta( $published_id );
	$tc_video_expect( home_url( '/tc-test-public.mp4' ) === get_post_meta( $published_id, 'tc_video_url', true ), 'Missing nonce cannot mutate a video.' );
	$_POST['tc_video_details_nonce'] = wp_create_nonce( 'tc_save_video_details' );
	TC_Videos::save_meta( $published_id );
	$tc_video_expect( home_url( '/tc-test-updated.mp4' ) === get_post_meta( $published_id, 'tc_video_url', true ), 'An authorized nonce-backed edit persists video settings.' );
	wp_set_current_user( 0 );
	$_POST['tc_video_url'] = home_url( '/unauthorized.mp4' );
	TC_Videos::save_meta( $published_id );
	$tc_video_expect( home_url( '/tc-test-updated.mp4' ) === get_post_meta( $published_id, 'tc_video_url', true ), 'Anonymous edits cannot mutate video data.' );
	echo 'Video integration passed: ' . $tc_video_assertions . " assertions.\n";
} finally {
	$_POST = array();
	foreach ( $tc_video_ids as $id ) {
		wp_delete_post( $id, true );
	}
	wp_set_current_user( $tc_video_original_user );
}
