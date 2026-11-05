<?php
/** Genuine-source vouches synchronization. Disposable localhost; every Discord response is mocked. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
if ( ! class_exists( 'TC_Discord_Vouches' ) ) { throw new RuntimeException( 'Load the vouches module first.' ); }

$checks = 0; $calls = array(); $created = array(); $manual = 0; $shortcode_runs = 0; $purges = array();
$old_user = get_current_user_id(); $old_post = $_POST; $old_request = $_REQUEST; $old_method = $_SERVER['REQUEST_METHOD'] ?? null;
$saved = array();
foreach ( array( 'timonconfigs_vouches', 'timonconfigs_vouches_state', '_tc_vouches_lock' ) as $key ) { $saved[ $key ] = array( 'exists' => false !== get_option( $key, false ), 'value' => get_option( $key, false ) ); }
$scheduled = array();
foreach ( _get_cron_array() as $timestamp => $hooks ) { foreach ( $hooks['tc_discord_vouches_sync'] ?? array() as $event ) { $scheduled[] = array( 'timestamp' => $timestamp, 'event' => $event ); } }
$suffix = (string) random_int( 10000000000000000, 99999999999999999 );
$guild = '17' . $suffix; $channel = '18' . $suffix; $author = '19' . $suffix;
$config = array( 'enabled' => true, 'guild_id' => $guild, 'channel_id' => $channel );
$token = 'local-vouches-fixture-' . wp_generate_password( 24, false, false );
if ( defined( 'TIMONCONFIGS_VOUCHES_DISCORD_BOT_TOKEN' ) ) { throw new RuntimeException( 'Run without an existing dedicated vouches token constant.' ); }
$mode = 'normal'; $dataset = array();
$published_safely = true;
$assert = static function ( bool $ok, string $message ) use ( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( 'Vouches: ' . $message ); } ++$checks; };
$reply = static fn( int $status, mixed $body, array $headers = array() ): array => array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'response' => array( 'code' => $status, 'message' => 'Fixture' ), 'cookies' => array() );
$message = static function ( int $number, string $content = '' ) use ( $channel, $author ): array {
	return array( 'id' => (string) ( 2000000000000000000 + $number ), 'channel_id' => $channel, 'type' => 0, 'content' => $content ?: 'Original source feedback ' . $number, 'timestamp' => '2026-10-15T12:30:00+00:00', 'edited_timestamp' => null, 'author' => array( 'id' => $author, 'username' => 'source_user', 'global_name' => 'Actual Discord Display Name', 'bot' => false ) );
};
for ( $i = 1; $i <= 150; ++$i ) { $value = $message( $i ); $dataset[ $value['id'] ] = $value; }
$ids = array_keys( $dataset );
$dataset[ $ids[149] ]['content'] = 'Original <script>alert("literal")</script> [tc_vouch_fixture] & feedback from <@123456789012345678>.';
$dataset[ $ids[148] ]['author']['bot'] = true;
$dataset[ $ids[147] ]['webhook_id'] = '1999999999999999999';
$dataset[ $ids[146] ]['type'] = 7;
$dataset[ $ids[145] ]['content'] = ''; $dataset[ $ids[145] ]['attachments'] = array( array( 'id' => '1999999999999999998' ) );
$intercept = static function ( mixed $preempt, array $args, string $url ) use ( &$calls, &$dataset, &$mode, $guild, $channel, $token, $reply, $assert ): array|WP_Error {
	$calls[] = $url;
	$assert( str_starts_with( $url, 'https://discord.com/api/v10/channels/' . $channel ), 'Only the fixed Discord origin and selected channel are contacted.' );
	$assert( 'Bot ' . $token === ( $args['headers']['Authorization'] ?? '' ) && 0 === $args['redirection'], 'The private token is used only in the request header with redirects disabled.' );
	if ( 'unauthorized' === $mode ) { return $reply( 401, array( 'message' => $token ) ); }
	if ( 'forbidden' === $mode ) { return $reply( 403, array( 'message' => $token ) ); }
	if ( 'rate' === $mode ) { return $reply( 429, array( 'retry_after' => 60, 'message' => $token ) ); }
	if ( 'transport' === $mode ) { return new WP_Error( 'unsafe_provider_text', $token ); }
	$parts = wp_parse_url( $url ); $path = $parts['path'];
	if ( 'lease_lost' === $mode ) { update_option( '_tc_vouches_lock', array( 'owner' => 'replacement-worker', 'expires' => time() + 300 ), false ); update_option( 'timonconfigs_vouches_state', array( 'source' => $guild . '/' . $channel, 'other_worker' => 'preserved' ), false ); }
	if ( '/api/v10/channels/' . $channel === $path ) { return $reply( 200, array( 'id' => $channel, 'guild_id' => 'wrong_channel' === $mode ? '1999999999999999999' : $guild, 'type' => 0 ) ); }
	if ( preg_match( '#/messages/([0-9]+)$#', $path, $matches ) ) {
		if ( 'channel_disappeared' === $mode ) { return $reply( 404, array( 'message' => 'Unknown Channel', 'code' => 10003 ) ); }
		return isset( $dataset[ $matches[1] ] ) ? $reply( 200, $dataset[ $matches[1] ] ) : $reply( 404, array( 'message' => 'Unknown Message', 'code' => 10008 ) );
	}
	if ( '/api/v10/channels/' . $channel . '/messages' !== $path ) { throw new RuntimeException( 'Unexpected fixture endpoint.' ); }
	if ( 'malformed' === $mode ) { return $reply( 200, array( array( 'id' => 'invalid', 'channel_id' => $channel ) ) ); }
	parse_str( $parts['query'] ?? '', $query );
	$messages = array_values( $dataset ); usort( $messages, static fn( array $a, array $b ): int => strcmp( $b['id'], $a['id'] ) );
	if ( ! empty( $query['before'] ) ) { $messages = array_values( array_filter( $messages, static fn( array $item ): bool => strcmp( $item['id'], $query['before'] ) < 0 ) ); }
	$messages = array_slice( $messages, 0, 100 );
	if ( 'empty_text' === $mode ) { foreach ( $messages as &$entry ) { $entry['content'] = ''; } unset( $entry ); }
	return $reply( 200, $messages );
};
$source_posts = static function () use ( $channel, $guild ): array { return get_posts( array( 'post_type' => 'tc_review', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'trash' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_tc_vouch_channel_id', 'value' => $channel ), array( 'key' => '_tc_vouch_guild_id', 'value' => $guild ) ) ) ); };
$clear_retry = static function (): void { $state = get_option( 'timonconfigs_vouches_state', array() ); $state['retry_at'] = 0; update_option( 'timonconfigs_vouches_state', $state, false ); };
$die_filter = static fn(): callable => static function ( mixed $message ): void { throw new RuntimeException( 'Fixture rejected request: ' . wp_strip_all_tags( (string) $message ) ); };
$publication_check = static function ( string $new, string $old, WP_Post $post ) use ( &$published_safely, $channel ): void {
	if ( 'tc_review' === $post->post_type && 'publish' === $new && 'Actual Discord Display Name' === $post->post_title ) {
		$published_safely = $published_safely && $channel === get_post_meta( $post->ID, '_tc_vouch_channel_id', true ) && (bool) get_post_meta( $post->ID, '_tc_vouch_source_url', true ) && (bool) get_post_meta( $post->ID, '_tc_vouch_managed_hash', true );
	}
};
$purge_check = static function ( string $url ) use ( &$purges ): void { $purges[] = $url; };
$legacy_schedule = static function ( array $schedules ): array { $schedules['tc_vouches_fifteen_minutes'] = array( 'interval' => 900, 'display' => 'Legacy test schedule' ); return $schedules; };
add_filter( 'pre_http_request', $intercept, 1, 3 );
add_action( 'transition_post_status', $publication_check, 10, 3 );
add_action( 'litespeed_purge_url', $purge_check );
add_shortcode( 'tc_vouch_fixture', static function () use ( &$shortcode_runs ): string { ++$shortcode_runs; return 'SHOULD NOT RUN'; } );
try {
	update_option( 'timonconfigs_vouches', $config, false ); delete_option( 'timonconfigs_vouches_state' ); delete_option( '_tc_vouches_lock' );
	TC_Discord_Vouches::deactivate();
	$private = new ReflectionMethod( TC_Discord_Vouches::class, 'bot_token' );
	if ( '' === $private->invoke( null ) ) {
		TC_Discord_Vouches::maybe_schedule();
		$assert( is_wp_error( TC_Discord_Vouches::sync() ) && ! $calls && ! wp_next_scheduled( 'tc_discord_vouches_sync' ), 'Missing credentials do not schedule HTTP or invent feedback.' );
	}
	define( 'TIMONCONFIGS_VOUCHES_DISCORD_BOT_TOKEN', $token );
	add_filter( 'cron_schedules', $legacy_schedule );
	wp_schedule_event( time() + 900, 'tc_vouches_fifteen_minutes', 'tc_discord_vouches_sync' );
	TC_Discord_Vouches::maybe_schedule(); $event = wp_get_scheduled_event( 'tc_discord_vouches_sync' );
	$assert( $event && 'tc_vouches_one_minute' === $event->schedule && 60 === $event->interval && $event->timestamp <= time() + 10, 'Updating an old fifteen-minute install starts the one-minute recurring job promptly.' );
	$assert( ! $calls, 'Scheduling never performs a blocking Discord request on a visitor page.' );
	TC_Discord_Vouches::maybe_schedule(); $assert( $event == wp_get_scheduled_event( 'tc_discord_vouches_sync' ), 'Repeated page loads keep the existing next check instead of postponing it.' );
	$events = 0; foreach ( _get_cron_array() as $hooks ) { $events += count( $hooks['tc_discord_vouches_sync'] ?? array() ); }
	$assert( 1 === $events, 'The upgrade leaves one job rather than duplicate old and new recurrences.' );
	remove_filter( 'cron_schedules', $legacy_schedule );
	$first = TC_Discord_Vouches::sync();
	$assert( ! is_wp_error( $first ) && 146 === $first['changed'], 'Pagination imports only 146 genuine text messages from 150 source entries.' );
	$created = $source_posts(); $assert( 146 === count( $created ), 'One attributed review is stored per eligible source message.' );
	$assert( $published_safely, 'New entries receive their source and integrity metadata before publication.' );
	$assert( count( $calls ) <= 7, 'A synchronization run has a bounded REST request budget.' );
	$post = get_post( $created[0] );
	$assert( 'Actual Discord Display Name' === $post->post_title && '2026-10-15 12:30:00' === $post->post_date_gmt, 'Real source display name and timestamp are retained.' );
	$assert( 0 === (int) get_post_meta( $post->ID, 'tc_review_rating', true ) && ! get_post_meta( $post->ID, 'tc_review_demo', true ), 'No star rating, purchase verification or demo quote is fabricated.' );
	$assert( str_contains( $post->post_content, '&lt;script&gt;' ) && str_contains( $post->post_content, '&#91;tc_vouch_fixture&#93;' ) && str_contains( $post->post_content, '@Discord member' ), 'Literal markup and shortcode delimiters are safely encoded; private Discord mention IDs are not resolved.' );
	$render = wp_kses_post( wpautop( $post->post_content ) );
	$assert( ! str_contains( $render, '<script>' ) && str_contains( html_entity_decode( $render, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), '[tc_vouch_fixture]' ) && 0 === $shortcode_runs, 'Rendering imported content preserves the visible quote without executing scripts or shortcodes.' );
	$filtered = apply_filters( 'the_content', $post->post_content );
	$assert( 0 === $shortcode_runs && ! str_contains( $filtered, 'SHOULD NOT RUN' ), 'Other WordPress content-rendering contexts also cannot execute a source shortcode.' );
	$message_id = get_post_meta( $post->ID, '_tc_vouch_message_id', true );
	$assert( 'https://discord.com/channels/' . $guild . '/' . $channel . '/' . $message_id === get_post_meta( $post->ID, '_tc_vouch_source_url', true ), 'Each review has a deterministic real-message source URL.' );
	$assert( TC_Discord_Vouches::is_source_review( $post->ID ), 'Source attribution is checked against the selected guild and channel.' );
	$again = TC_Discord_Vouches::sync(); $assert( ! is_wp_error( $again ) && 0 === $again['changed'] && $created === $source_posts(), 'Repeated polling is idempotent.' );
	// A new channel post reaches the actual public template through the cron hook, without an admin request.
	$latest = $message( 500, 'LOCAL QA newly shared text appears directly on the website.' );
	$latest['timestamp'] = gmdate( DATE_ATOM ); $dataset[ $latest['id'] ] = $latest; $purges = array();
	do_action( 'tc_discord_vouches_sync' );
	$new_ids = array_values( array_diff( $source_posts(), $created ) );
	$assert( 1 === count( $new_ids ) && 'publish' === get_post_status( $new_ids[0] ), 'The scheduled hook automatically publishes a new member post without approval.' );
	wp_set_current_user( 0 ); ob_start(); get_template_part( 'template-parts/review-cards' ); $cards = ob_get_clean();
	$assert( str_contains( $cards, $latest['content'] ) && str_contains( $cards, 'Actual Discord Display Name' ) && str_contains( $cards, 'tc-review-quote' ) && ! str_contains( $cards, 'tc-reviews-empty' ), 'An anonymous visitor reads the complete new message inline rather than a redirect-only placeholder.' );
	$assert( str_contains( $cards, TC_Discord_Vouches::source_url( $latest['id'], $config ) ) && str_contains( $cards, 'View original on Discord' ), 'Discord remains an optional source link below the public quote.' );
	$reviews_page = get_page_by_path( 'reviews' );
	$assert( in_array( home_url( '/' ), $purges, true ) && in_array( $reviews_page ? get_permalink( $reviews_page ) : home_url( '/reviews/' ), $purges, true ), 'Automatic publication invalidates Home and Reviews caches.' );
	wp_delete_post( $new_ids[0], true ); unset( $dataset[ $latest['id'] ] );
	$dataset[ $message_id ]['content'] = 'The source author edited this feedback.'; $dataset[ $message_id ]['edited_timestamp'] = '2026-10-16T13:00:00Z';
	$edited = TC_Discord_Vouches::sync(); $assert( ! is_wp_error( $edited ) && 'The source author edited this feedback.' === get_post_field( 'post_content', $post->ID ), 'Source edits update the existing review rather than create another.' );
	$assert( '2026-10-16T13:00:00Z' === get_post_meta( $post->ID, '_tc_vouch_edited_timestamp', true ), 'The source edit timestamp is retained.' );
	$hidden = $created[1]; wp_update_post( array( 'ID' => $hidden, 'post_status' => 'draft' ) ); TC_Discord_Vouches::sync();
	$assert( 'draft' === get_post_status( $hidden ), 'A manually hidden review is not republished.' );
	$local = $created[2]; wp_update_post( array( 'ID' => $local, 'post_content' => 'Owner annotation preserved.' ) ); TC_Discord_Vouches::sync();
	$assert( 'Owner annotation preserved.' === get_post_field( 'post_content', $local ) && get_post_meta( $local, '_tc_vouch_local_override', true ), 'Manual edits are preserved instead of overwritten by a poll.' );
	$manual = wp_insert_post( array( 'post_type' => 'tc_review', 'post_status' => 'publish', 'post_title' => 'Existing owner-authored review', 'post_content' => 'Existing feedback untouched.' ) );
	TC_Discord_Vouches::sync(); $assert( 'Existing feedback untouched.' === get_post_field( 'post_content', $manual ), 'Owner-authored reviews without sync markers are untouched.' );
	$oldest = end( $created ); $assert( 'publish' === get_post_status( $oldest ), 'A source review outside the latest message window is not removed.' );
	unset( $dataset[ $message_id ] ); $state = get_option( 'timonconfigs_vouches_state' ); $state['recheck_offset'] = 0; update_option( 'timonconfigs_vouches_state', $state, false );
	TC_Discord_Vouches::sync(); $assert( 'draft' === get_post_status( $post->ID ) && get_post_meta( $post->ID, '_tc_vouch_deleted', true ), 'Only a confirmed individual-message 404 hides a deleted source review.' );
	$assert( ! TC_Discord_Vouches::is_source_review( $post->ID ), 'Deleted source entries do not qualify for source display.' );
	for ( $i = 1001; $i <= 1205; ++$i ) { $value = $message( $i ); $dataset[ $value['id'] ] = $value; }
	$a = TC_Discord_Vouches::sync(); $state = get_option( 'timonconfigs_vouches_state' );
	$assert( ! is_wp_error( $a ) && ! empty( $state['scan_before'] ), 'A new-message gap exceeding two pages keeps a resumable cursor.' );
	$b = TC_Discord_Vouches::sync(); $state = get_option( 'timonconfigs_vouches_state' );
	$assert( ! is_wp_error( $b ) && empty( $state['scan_before'] ) && 351 === count( $source_posts() ), 'A following bounded run resumes the gap without losing or duplicating reviews.' );
	$created = $source_posts();
	foreach ( array( 'unauthorized', 'forbidden', 'transport', 'wrong_channel', 'malformed', 'rate', 'channel_disappeared' ) as $error_mode ) {
		$clear_retry(); $mode = $error_mode; $before = count( $calls ); $result = TC_Discord_Vouches::sync(); $state = get_option( 'timonconfigs_vouches_state' );
		$assert( is_wp_error( $result ) && $created === $source_posts(), 'API failure preserves all source records: ' . $error_mode );
		$assert( ! str_contains( wp_json_encode( $state ), $token ) && ! str_contains( $result->get_error_message(), $token ), 'Provider data and bot credentials cannot leak into status errors: ' . $error_mode );
		$assert( (int) $state['retry_at'] > time(), 'Failed polls set a bounded retry time: ' . $error_mode );
		$count = count( $calls ); $paused = TC_Discord_Vouches::sync(); $assert( is_wp_error( $paused ) && count( $calls ) === $count, 'Backoff does not make another REST call: ' . $error_mode );
	}
	$mode = 'empty_text'; $clear_retry(); $result = TC_Discord_Vouches::sync(); $state = get_option( 'timonconfigs_vouches_state' );
	$assert( ! is_wp_error( $result ) && str_contains( $state['content_notice'], 'Message Content Intent' ), 'Messages with no available text produce an actionable intent diagnostic.' );
	$mode = 'normal'; $clear_retry(); update_option( '_tc_vouches_lock', array( 'owner' => 'another-worker', 'expires' => time() + 300 ), false ); $before = count( $calls );
	$locked = TC_Discord_Vouches::sync(); $assert( is_wp_error( $locked ) && 'locked' === $locked->get_error_code() && count( $calls ) === $before && 'another-worker' === get_option( '_tc_vouches_lock' )['owner'], 'A concurrent worker lock is respected and not removed.' );
	update_option( '_tc_vouches_lock', array( 'owner' => 'expired-worker', 'expires' => time() - 1 ), false );
	$assert( ! is_wp_error( TC_Discord_Vouches::sync() ) && false === get_option( '_tc_vouches_lock', false ), 'An expired lock can be acquired atomically and released by its owner.' );
	$fresh_state = static function ( string $option ) use ( $guild, $channel ): void {
		if ( '_tc_vouches_lock' !== $option ) { return; }
		$state = get_option( 'timonconfigs_vouches_state', array() ); $state['race_marker'] = 'fresh-after-lock'; update_option( 'timonconfigs_vouches_state', $state, false );
	};
	add_action( 'added_option', $fresh_state ); $result = TC_Discord_Vouches::sync(); remove_action( 'added_option', $fresh_state );
	$assert( ! is_wp_error( $result ) && 'fresh-after-lock' === ( get_option( 'timonconfigs_vouches_state' )['race_marker'] ?? '' ), 'Cursor state is read after lock acquisition rather than overwriting a newer worker state.' );
	$before_state = get_option( 'timonconfigs_vouches_state' ); $mode = 'lease_lost'; $result = TC_Discord_Vouches::sync();
	$assert( is_wp_error( $result ) && 'lease_lost' === $result->get_error_code() && 'replacement-worker' === ( get_option( '_tc_vouches_lock' )['owner'] ?? '' ) && 'preserved' === ( get_option( 'timonconfigs_vouches_state' )['other_worker'] ?? '' ), 'A worker that loses its lease performs no cursor write or foreign lock release.' );
	delete_option( '_tc_vouches_lock' ); update_option( 'timonconfigs_vouches_state', $before_state, false ); $mode = 'normal';
	$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array(); $_REQUEST = array(); wp_set_current_user( 0 ); add_filter( 'wp_die_handler', $die_filter );
	$denied = false; try { TC_Discord_Vouches::manual_sync(); } catch ( RuntimeException $e ) { $denied = true; }
	$assert( $denied, 'The manual synchronization endpoint requires administrator permission.' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) ); wp_set_current_user( (int) $admins[0] );
	$denied = false; try { TC_Discord_Vouches::manual_sync(); } catch ( RuntimeException $e ) { $denied = true; }
	$assert( $denied, 'An administrator request without a valid nonce is rejected.' );
	remove_filter( 'wp_die_handler', $die_filter );
	$before = count( $calls ); ob_start(); TC_Discord_Vouches::settings_page(); $html = ob_get_clean();
	$assert( ! str_contains( $html, $token ) && str_contains( $html, 'View Channel' ) && str_contains( $html, 'Read Message History' ) && count( $calls ) === $before, 'The settings page explains read permissions without making HTTP or exposing credentials.' );
	$assert( str_contains( $html, 'Automatic publishing is enabled' ) && str_contains( $html, 'every minute through WP-Cron' ) && str_contains( $html, 'Next scheduled check' ), 'The administrator sees automatic publication status, its cadence and the next check.' );
	$config['enabled'] = false; update_option( 'timonconfigs_vouches', $config, false ); TC_Discord_Vouches::maybe_schedule();
	$assert( ! wp_next_scheduled( 'tc_discord_vouches_sync' ), 'Disabling sync removes its cron event.' );
	ob_start(); TC_Discord_Vouches::settings_page(); $paused_html = ob_get_clean();
	$assert( str_contains( $paused_html, 'Automatic publishing is paused' ), 'An explicit owner pause is preserved and clearly reported.' );
	$assert( '' === TC_Discord_Vouches::source_url( '../unsafe' ), 'Invalid source IDs cannot create arbitrary source links.' );
	WP_CLI::success( "Discord vouches integration: $checks assertions passed; mocked Discord only, no genuine reviews fabricated or published outside the disposable fixture." );
} finally {
	remove_filter( 'pre_http_request', $intercept, 1 ); remove_filter( 'wp_die_handler', $die_filter ); remove_shortcode( 'tc_vouch_fixture' );
	remove_action( 'transition_post_status', $publication_check, 10 );
	remove_action( 'litespeed_purge_url', $purge_check ); remove_filter( 'cron_schedules', $legacy_schedule );
	if ( isset( $fresh_state ) ) { remove_action( 'added_option', $fresh_state ); }
	foreach ( $source_posts() as $id ) { wp_delete_post( $id, true ); }
	if ( $manual ) { wp_delete_post( $manual, true ); }
	TC_Discord_Vouches::deactivate();
	foreach ( $saved as $key => $backup ) { $backup['exists'] ? update_option( $key, $backup['value'], false ) : delete_option( $key ); }
	foreach ( $scheduled as $entry ) { $event = $entry['event']; if ( $event['schedule'] ) { wp_schedule_event( (int) $entry['timestamp'], $event['schedule'], 'tc_discord_vouches_sync', $event['args'] ); } else { wp_schedule_single_event( (int) $entry['timestamp'], 'tc_discord_vouches_sync', $event['args'] ); } }
	wp_set_current_user( $old_user ); $_POST = $old_post; $_REQUEST = $old_request;
	if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
}
