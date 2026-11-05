<?php
/** Read-only Discord vouches mirrored as genuine, attributable community feedback. */
defined( 'ABSPATH' ) || exit;

final class TC_Discord_Vouches {
	private const OPTION = 'timonconfigs_vouches';
	private const STATE = 'timonconfigs_vouches_state';
	private const LOCK = '_tc_vouches_lock';
	private const HOOK = 'tc_discord_vouches_sync';
	private const INTERVAL = 'tc_vouches_fifteen_minutes';
	private const GUILD = '1557854161805115402';
	private const CHANNEL = '1560781856931315832';
	private static string $active_owner = '';
	private static float $deadline = 0;

	public static function init(): void {
		add_filter( 'cron_schedules', static function ( array $schedules ): array {
			$schedules[ self::INTERVAL ] = array( 'interval' => 900, 'display' => 'Every fifteen minutes' );
			return $schedules;
		} );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 40 );
		add_action( self::HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'admin_menu', static function (): void {
			add_submenu_page( 'edit.php?post_type=tc_review', 'Discord vouches', 'Discord vouches', 'manage_options', 'tc-discord-vouches', array( __CLASS__, 'settings_page' ) );
		} );
		add_action( 'admin_post_tc_save_vouches', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_tc_sync_vouches', array( __CLASS__, 'manual_sync' ) );
	}

	public static function deactivate(): void { wp_clear_scheduled_hook( self::HOOK ); }

	private static function snowflake( mixed $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]{16,19}$/D', $value );
	}

	public static function config(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'enabled' => ! array_key_exists( 'enabled', $stored ) || (bool) $stored['enabled'],
			'guild_id' => self::snowflake( $stored['guild_id'] ?? null ) ? $stored['guild_id'] : self::GUILD,
			'channel_id' => self::snowflake( $stored['channel_id'] ?? null ) ? $stored['channel_id'] : self::CHANNEL,
		);
	}

	private static function bot_token(): string {
		foreach ( array( 'TIMONCONFIGS_VOUCHES_DISCORD_BOT_TOKEN', 'TIMONCONFIGS_WOO_DISCORD_BOT_TOKEN', 'TIMONCONFIGS_BILLING_LIVE_DISCORD_BOT_TOKEN' ) as $constant ) {
			if ( defined( $constant ) && is_string( constant( $constant ) ) && '' !== trim( constant( $constant ) ) ) { return trim( constant( $constant ) ); }
		}
		return '';
	}

	public static function ready(): bool { return self::config()['enabled'] && '' !== self::bot_token(); }

	public static function maybe_schedule(): void {
		if ( ! self::ready() ) { self::deactivate(); return; }
		if ( ! wp_next_scheduled( self::HOOK ) ) { wp_schedule_event( time() + 60, self::INTERVAL, self::HOOK ); }
	}

	public static function source_url( string $message_id, ?array $config = null ): string {
		$config = $config ?? self::config();
		return self::snowflake( $message_id ) && self::snowflake( $config['guild_id'] ?? null ) && self::snowflake( $config['channel_id'] ?? null ) ? 'https://discord.com/channels/' . $config['guild_id'] . '/' . $config['channel_id'] . '/' . $message_id : '';
	}

	/** True only for this configured source, never merely for an owner-created testimonial. */
	public static function is_source_review( int $post_id ): bool {
		$config = self::config(); $message_id = get_post_meta( $post_id, '_tc_vouch_message_id', true );
		return self::snowflake( $message_id )
			&& $config['guild_id'] === get_post_meta( $post_id, '_tc_vouch_guild_id', true )
			&& $config['channel_id'] === get_post_meta( $post_id, '_tc_vouch_channel_id', true )
			&& self::source_url( $message_id, $config ) === get_post_meta( $post_id, '_tc_vouch_source_url', true )
			&& ! get_post_meta( $post_id, '_tc_vouch_hidden', true )
			&& ! get_post_meta( $post_id, '_tc_vouch_deleted', true );
	}

	private static function state( array $config ): array {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) && ( $state['source'] ?? '' ) === $config['guild_id'] . '/' . $config['channel_id'] ? $state : array( 'source' => $config['guild_id'] . '/' . $config['channel_id'] );
	}

	private static function lock(): string {
		global $wpdb;
		$owner = wp_generate_uuid4(); $lock = array( 'owner' => $owner, 'expires' => time() + 180 );
		if ( add_option( self::LOCK, $lock, '', false ) ) { return $owner; }
		$old = get_option( self::LOCK );
		if ( ! is_array( $old ) || (int) ( $old['expires'] ?? PHP_INT_MAX ) >= time() ) { return ''; }
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s", maybe_serialize( $lock ), self::LOCK, maybe_serialize( $old ) ) );
		wp_cache_delete( self::LOCK, 'options' );
		return 1 === $changed ? $owner : '';
	}

	private static function unlock( string $owner ): void {
		global $wpdb;
		$lock = get_option( self::LOCK );
		if ( is_array( $lock ) && hash_equals( (string) ( $lock['owner'] ?? '' ), $owner ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", self::LOCK, maybe_serialize( $lock ) ) );
			wp_cache_delete( self::LOCK, 'options' );
		}
	}

	/** A worker may not write after its lease is taken over, even if an HTTP callback was slow. */
	private static function renew(): bool {
		global $wpdb;
		if ( '' === self::$active_owner || microtime( true ) >= self::$deadline ) { return false; }
		wp_cache_delete( self::LOCK, 'options' );
		$old = get_option( self::LOCK );
		if ( ! is_array( $old ) || ! hash_equals( (string) ( $old['owner'] ?? '' ), self::$active_owner ) ) { return false; }
		$new = array( 'owner' => self::$active_owner, 'expires' => time() + 180, 'heartbeat' => wp_generate_uuid4() );
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s", maybe_serialize( $new ), self::LOCK, maybe_serialize( $old ) ) );
		wp_cache_delete( self::LOCK, 'options' );
		return 1 === $changed;
	}

	private static function lease_error(): WP_Error { return new WP_Error( 'lease_lost', 'Synchronization paused because its worker lease or time budget ended. Existing feedback was preserved.', array( 'retry' => 300 ) ); }

	/** Only GET requests to Discord's fixed REST origin; API response bodies never become errors. */
	private static function request( string $path ): array|WP_Error {
		if ( ! self::renew() ) { return self::lease_error(); }
		$response = wp_remote_get( 'https://discord.com/api/v10' . $path, array( 'timeout' => 12, 'redirection' => 0, 'limit_response_size' => 524288, 'headers' => array( 'Authorization' => 'Bot ' . self::bot_token(), 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $response ) ) { return new WP_Error( 'transport', 'Discord could not be reached. Existing feedback was preserved.', array( 'retry' => 300 ) ); }
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$retry = $body['retry_after'] ?? wp_remote_retrieve_header( $response, 'retry-after' );
			return new WP_Error( 'rate_limit', 'Discord requested a pause. Synchronization will retry automatically.', array( 'retry' => max( 1, min( 86400, is_numeric( $retry ) ? (int) ceil( (float) $retry ) : 900 ) ) ) );
		}
		if ( 200 !== $code ) {
			$message = match ( $code ) { 401 => 'Discord rejected the bot credentials.', 403 => 'The bot needs access to the configured vouches channel.', 404 => 'The Discord channel or message was not found.', default => 'Discord returned an unsuccessful response.' };
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new WP_Error( 'http_' . $code, $message, array( 'retry' => in_array( $code, array( 401, 403 ), true ) ? 3600 : 300, 'discord_code' => is_array( $body ) && is_int( $body['code'] ?? null ) ? $body['code'] : 0 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return JSON_ERROR_NONE === json_last_error() && is_array( $data ) ? $data : new WP_Error( 'invalid_response', 'Discord returned an invalid response. Existing feedback was preserved.', array( 'retry' => 300 ) );
	}

	private static function compare_ids( string $a, string $b ): int { return strlen( $a ) <=> strlen( $b ) ?: strcmp( $a, $b ); }

	private static function messages( array $config, string $before = '' ): array|WP_Error {
		$path = '/channels/' . $config['channel_id'] . '/messages?limit=100';
		if ( $before ) { if ( ! self::snowflake( $before ) ) { return new WP_Error( 'invalid_cursor', 'The synchronization cursor is invalid.', array( 'retry' => 300 ) ); } $path .= '&before=' . $before; }
		$data = self::request( $path );
		if ( is_wp_error( $data ) ) { return $data; }
		if ( ! array_is_list( $data ) || count( $data ) > 100 ) { return new WP_Error( 'invalid_messages', 'Discord returned an invalid message list.', array( 'retry' => 300 ) ); }
		foreach ( $data as $message ) {
			if ( ! is_array( $message ) || ! self::snowflake( $message['id'] ?? null ) || ( $message['channel_id'] ?? '' ) !== $config['channel_id'] || ( $before && self::compare_ids( $message['id'], $before ) >= 0 ) ) { return new WP_Error( 'invalid_messages', 'Discord returned an invalid message list.', array( 'retry' => 300 ) ); }
		}
		usort( $data, static fn( array $a, array $b ): int => -self::compare_ids( $a['id'], $b['id'] ) );
		return $data;
	}

	/** Keep source words; display HTML literally and replace unresolved private Discord mentions. */
	private static function parsed( array $message, array $config ): ?array {
		if ( ( $message['channel_id'] ?? '' ) !== $config['channel_id'] || ! self::snowflake( $message['id'] ?? null ) || ! in_array( $message['type'] ?? null, array( 0, 19 ), true ) || ! empty( $message['webhook_id'] ) || ! is_array( $message['author'] ?? null ) || ! empty( $message['author']['bot'] ) || ! self::snowflake( $message['author']['id'] ?? null ) || ! is_string( $message['content'] ?? null ) ) { return null; }
		$content = wp_check_invalid_utf8( $message['content'] );
		$content = preg_replace( '/<@!?[0-9]+>/', '@Discord member', $content );
		$content = preg_replace( '/<@&[0-9]+>/', '@Discord role', $content );
		$content = preg_replace( '/<#[0-9]+>/', '#Discord channel', $content );
		$content = preg_replace( '/<a?:([a-zA-Z0-9_]+):[0-9]+>/', ':$1:', $content );
		$content = trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content ) );
		if ( '' === $content || strlen( $content ) > 20000 ) { return null; }
		$author = '';
		foreach ( array( $message['member']['nick'] ?? null, $message['author']['global_name'] ?? null, $message['author']['username'] ?? null ) as $name ) { if ( is_string( $name ) && '' !== trim( $name ) ) { $author = sanitize_text_field( $name ); break; } }
		if ( '' === $author || ! is_string( $message['timestamp'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $message['timestamp'] ) ) { return null; }
		try { $date = new DateTimeImmutable( $message['timestamp'] ); $errors = DateTimeImmutable::getLastErrors(); if ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) { return null; } } catch ( Exception $e ) { return null; }
		$stored_content = str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), esc_html( $content ) );
		return array( 'message_id' => $message['id'], 'author' => $author, 'content' => $stored_content, 'source_text' => $content, 'date' => $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), 'timestamp' => $date->format( DATE_ATOM ), 'edited' => is_string( $message['edited_timestamp'] ?? null ) ? sanitize_text_field( $message['edited_timestamp'] ) : '' );
	}

	private static function find_review( string $message_id, array $config ): int {
		$ids = get_posts( array( 'post_type' => 'tc_review', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'trash' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_tc_vouch_message_id', 'value' => $message_id ), array( 'key' => '_tc_vouch_channel_id', 'value' => $config['channel_id'] ), array( 'key' => '_tc_vouch_guild_id', 'value' => $config['guild_id'] ) ) ) );
		return $ids ? (int) $ids[0] : 0;
	}

	private static function upsert( array $message, array $config ): int|WP_Error {
		$value = self::parsed( $message, $config );
		if ( ! $value ) { return 0; }
		if ( ! self::renew() ) { return self::lease_error(); }
		$id = self::find_review( $value['message_id'], $config );
		$hash = hash( 'sha256', $value['author'] . "\n" . $value['content'] );
		if ( $id ) {
			$post = get_post( $id );
			// Staff can hide or edit an imported entry; a later poll must not undo moderation.
			if ( ! $post || 'publish' !== $post->post_status || get_post_meta( $id, '_tc_vouch_hidden', true ) || get_post_meta( $id, '_tc_vouch_deleted', true ) ) { return 0; }
			$old_hash = get_post_meta( $id, '_tc_vouch_managed_hash', true );
			if ( $old_hash && ! hash_equals( $old_hash, hash( 'sha256', $post->post_title . "\n" . $post->post_content ) ) ) { update_post_meta( $id, '_tc_vouch_local_override', true ); return 0; }
			if ( $old_hash === $hash ) { return 0; }
			$result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => $value['author'], 'post_content' => $value['content'] ) ), true );
		} else {
			$result = wp_insert_post( wp_slash( array( 'post_type' => 'tc_review', 'post_status' => 'draft', 'post_title' => $value['author'], 'post_content' => $value['content'], 'post_date_gmt' => $value['date'], 'post_date' => get_date_from_gmt( $value['date'] ), 'comment_status' => 'closed', 'ping_status' => 'closed' ) ), true );
		}
		if ( is_wp_error( $result ) ) { return new WP_Error( 'review_write', 'A source review could not be saved. Existing feedback was preserved.', array( 'retry' => 300 ) ); }
		if ( ! self::renew() ) { return self::lease_error(); }
		$created = ! $id;
		$id = (int) $result;
		foreach ( array( '_tc_vouch_message_id' => $value['message_id'], '_tc_vouch_channel_id' => $config['channel_id'], '_tc_vouch_guild_id' => $config['guild_id'], '_tc_vouch_source_url' => self::source_url( $value['message_id'], $config ), '_tc_vouch_author' => $value['author'], '_tc_vouch_source_text' => $value['source_text'], '_tc_vouch_timestamp' => $value['timestamp'], '_tc_vouch_edited_timestamp' => $value['edited'], '_tc_vouch_managed_hash' => $hash, 'tc_review_demo' => false, 'tc_review_rating' => 0 ) as $key => $meta ) { update_post_meta( $id, $key, wp_slash( $meta ) ); }
		if ( $created ) {
			if ( ! self::renew() ) { return self::lease_error(); }
			$published = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
			if ( is_wp_error( $published ) ) { return new WP_Error( 'review_write', 'A source review could not be published.', array( 'retry' => 300 ) ); }
		}
		return 1;
	}

	/** Bounded work: at most two new-message pages, one older page and three individual rechecks. */
	public static function sync(): array|WP_Error {
		if ( ! self::ready() ) { return new WP_Error( 'not_ready', 'Enable synchronization and configure a private Discord bot token first.' ); }
		$config = self::config();
		$owner = self::lock();
		if ( ! $owner ) { return new WP_Error( 'locked', 'Another Discord synchronization is already running.' ); }
		self::$active_owner = $owner; self::$deadline = microtime( true ) + 120;
		$state = self::state( $config );
		$changed = 0; $failure = null; $state['last_attempt'] = time(); $empty_normal = 0; $text_normal = 0;
		try {
			if ( (int) ( $state['retry_at'] ?? 0 ) > time() ) { return new WP_Error( 'backoff', 'Discord synchronization is waiting for its next retry.' ); }
			$channel = self::request( '/channels/' . $config['channel_id'] );
			if ( is_wp_error( $channel ) ) { $failure = $channel; return $failure; }
			if ( ( $channel['id'] ?? '' ) !== $config['channel_id'] || ( $channel['guild_id'] ?? '' ) !== $config['guild_id'] || ! in_array( $channel['type'] ?? null, array( 0, 5, 10, 11, 12 ), true ) ) { $failure = new WP_Error( 'wrong_channel', 'The configured channel does not belong to the selected Discord server.', array( 'retry' => 3600 ) ); return $failure; }
			for ( $page = 0; $page < 2; ++$page ) {
				$before = self::snowflake( $state['scan_before'] ?? null ) ? $state['scan_before'] : '';
				$messages = self::messages( $config, $before );
				if ( is_wp_error( $messages ) ) { $failure = $messages; return $failure; }
				$stop = self::snowflake( $state['scan_stop'] ?? null ) ? $state['scan_stop'] : ( self::snowflake( $state['latest'] ?? null ) ? $state['latest'] : '' );
				$reached = false;
				foreach ( $messages as $message ) {
					if ( in_array( $message['type'] ?? null, array( 0, 19 ), true ) && is_array( $message['author'] ?? null ) && empty( $message['author']['bot'] ) && empty( $message['webhook_id'] ) && is_string( $message['content'] ?? null ) ) {
						if ( '' === trim( $message['content'] ) ) { ++$empty_normal; } else { ++$text_normal; }
					}
					$result = self::upsert( $message, $config );
					if ( is_wp_error( $result ) ) { $failure = $result; return $failure; } $changed += $result;
					if ( $stop && self::compare_ids( $message['id'], $stop ) <= 0 ) { $reached = true; }
				}
				$head = $state['scan_head'] ?? ( $messages[0]['id'] ?? ( $state['latest'] ?? '' ) );
				$oldest = $messages ? $messages[ count( $messages ) - 1 ]['id'] : '';
				if ( ! $stop ) {
					$state['latest'] = $head; $state['older_before'] = $oldest; $state['older_done'] = count( $messages ) < 100;
					break;
				}
				if ( $reached || count( $messages ) < 100 ) {
					$state['latest'] = $head; unset( $state['scan_before'], $state['scan_stop'], $state['scan_head'] ); break;
				}
				$state['scan_before'] = $oldest; $state['scan_stop'] = $stop; $state['scan_head'] = $head;
			}
			if ( empty( $state['older_done'] ) && self::snowflake( $state['older_before'] ?? null ) ) {
				$older = self::messages( $config, $state['older_before'] );
				if ( is_wp_error( $older ) ) { $failure = $older; return $failure; }
				foreach ( $older as $message ) { $result = self::upsert( $message, $config ); if ( is_wp_error( $result ) ) { $failure = $result; return $failure; } $changed += $result; }
				if ( $older ) { $state['older_before'] = $older[ count( $older ) - 1 ]['id']; }
				$state['older_done'] = count( $older ) < 100;
			}
			$query = array( 'post_type' => 'tc_review', 'post_status' => 'publish', 'posts_per_page' => 3, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'offset' => max( 0, (int) ( $state['recheck_offset'] ?? 0 ) ), 'meta_query' => array( array( 'key' => '_tc_vouch_channel_id', 'value' => $config['channel_id'] ), array( 'key' => '_tc_vouch_guild_id', 'value' => $config['guild_id'] ) ) );
			$ids = get_posts( $query );
			if ( ! $ids && $query['offset'] ) { $query['offset'] = 0; $ids = get_posts( $query ); }
			foreach ( $ids as $id ) {
				$message_id = get_post_meta( $id, '_tc_vouch_message_id', true );
				if ( ! self::snowflake( $message_id ) ) { continue; }
				$message = self::request( '/channels/' . $config['channel_id'] . '/messages/' . $message_id );
				if ( is_wp_error( $message ) ) {
					if ( 'http_404' === $message->get_error_code() && 10008 === ( $message->get_error_data()['discord_code'] ?? 0 ) ) {
						if ( ! self::renew() ) { $failure = self::lease_error(); return $failure; }
						$deleted = wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ), true );
						if ( is_wp_error( $deleted ) ) { $failure = new WP_Error( 'review_write', 'A removed source review could not be hidden.', array( 'retry' => 300 ) ); return $failure; }
						update_post_meta( $id, '_tc_vouch_deleted', true ); ++$changed;
					}
					else { $failure = $message; return $failure; }
				} else {
					if ( ( $message['id'] ?? '' ) !== $message_id || ( $message['channel_id'] ?? '' ) !== $config['channel_id'] ) { $failure = new WP_Error( 'invalid_message', 'Discord returned an invalid source message.', array( 'retry' => 300 ) ); return $failure; }
					$result = self::upsert( $message, $config ); if ( is_wp_error( $result ) ) { $failure = $result; return $failure; } $changed += $result;
				}
				$state['recheck_id'] = (int) $id;
			}
			$state['recheck_offset'] = count( $ids ) < 3 ? 0 : $query['offset'] + 3;
			$state['content_notice'] = $empty_normal > 0 && 0 === $text_normal ? 'Discord returned user messages without text. Check Message Content Intent; attachment-only messages are not imported.' : '';
			$state['last_success'] = time(); $state['last_error'] = ''; $state['retry_at'] = 0; $state['changed'] = $changed;
			return array( 'changed' => $changed, 'older_complete' => ! empty( $state['older_done'] ) );
		} finally {
			if ( self::renew() ) {
				if ( $failure instanceof WP_Error ) { $data = $failure->get_error_data(); $state['last_error'] = $failure->get_error_message(); $state['last_error_code'] = $failure->get_error_code(); $state['retry_at'] = time() + (int) ( $data['retry'] ?? 300 ); }
				update_option( self::STATE, $state, false );
			}
			self::unlock( $owner );
			self::$active_owner = ''; self::$deadline = 0;
			if ( $changed ) {
				do_action( 'litespeed_purge_url', home_url( '/' ) );
				$page = get_page_by_path( 'reviews' ); do_action( 'litespeed_purge_url', $page ? get_permalink( $page ) : home_url( '/reviews/' ) );
				do_action( 'timonconfigs_vouches_changed', $changed );
			}
		}
	}

	public static function settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator permission is required.', '', array( 'response' => 403 ) ); }
		$config = self::config(); $state = self::state( $config );
		$count = new WP_Query( array( 'post_type' => 'tc_review', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_tc_vouch_channel_id', 'value' => $config['channel_id'] ), array( 'key' => '_tc_vouch_guild_id', 'value' => $config['guild_id'] ) ) ) );
		echo '<div class="wrap"><h1>Discord vouches</h1><p>Read genuine text feedback from the selected Discord channel. Original wording, display names, dates and source links are retained. No star rating or verified purchase is invented.</p>';
		echo '<p>Published source entries: <strong>' . esc_html( (string) $count->found_posts ) . '</strong>.</p>';
		echo '<p>Bot credentials: <strong>' . ( self::bot_token() ? 'available privately on this server' : 'not configured' ) . '</strong>. The existing TimonConfigs Woo bot token is reused. An optional private <code>TIMONCONFIGS_VOUCHES_DISCORD_BOT_TOKEN</code> constant can select a different bot. Never paste tokens into this page or GitHub.</p>';
		echo '<p>The bot needs <strong>View Channel</strong> and <strong>Read Message History</strong>. Enable <strong>Message Content Intent</strong> in the Discord Developer Portal so Discord returns the message text. It does not need permission to send messages.</p>';
		echo '<p>Schedule: every 15 minutes through WP-Cron when credentials are present. Low-traffic sites should run WordPress cron from the hosting scheduler. Each run processes bounded pages; older feedback is imported gradually.</p>';
		if ( ! empty( $state['last_success'] ) ) { echo '<p>Last successful synchronization: ' . esc_html( wp_date( 'Y-m-d H:i:s', (int) $state['last_success'] ) ) . '</p>'; }
		if ( ! empty( $state['last_error'] ) ) { echo '<div class="notice notice-warning inline"><p>' . esc_html( $state['last_error'] ) . '</p></div>'; }
		if ( ! empty( $state['content_notice'] ) ) { echo '<div class="notice notice-info inline"><p>' . esc_html( $state['content_notice'] ) . '</p></div>'; }
		echo '<p><a href="' . esc_url( 'https://discord.com/channels/' . $config['guild_id'] . '/' . $config['channel_id'] ) . '" target="_blank" rel="noopener noreferrer">Open the source channel</a>. Imported posts can be hidden by changing their status to Draft; synchronization will preserve that choice.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tc_save_vouches">'; wp_nonce_field( 'tc_save_vouches' );
		echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked( $config['enabled'], true, false ) . '> Enable automatic synchronization</label></p>';
		foreach ( array( 'guild_id' => 'Discord server ID', 'channel_id' => 'Vouches channel ID' ) as $key => $label ) { echo '<p><label>' . esc_html( $label ) . '<br><input name="' . esc_attr( $key ) . '" value="' . esc_attr( $config[ $key ] ) . '" pattern="[1-9][0-9]{16,19}" required class="regular-text"></label></p>'; }
		submit_button( 'Save vouches settings' ); echo '</form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tc_sync_vouches">'; wp_nonce_field( 'tc_sync_vouches' ); submit_button( 'Synchronize now', 'secondary' ); echo '</form></div>';
	}

	private static function require_admin( string $action ): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator permission is required.', '', array( 'response' => 403 ) ); }
		check_admin_referer( $action );
	}

	public static function save_settings(): void {
		self::require_admin( 'tc_save_vouches' );
		$guild = isset( $_POST['guild_id'] ) && is_string( $_POST['guild_id'] ) ? wp_unslash( $_POST['guild_id'] ) : '';
		$channel = isset( $_POST['channel_id'] ) && is_string( $_POST['channel_id'] ) ? wp_unslash( $_POST['channel_id'] ) : '';
		if ( ! self::snowflake( $guild ) || ! self::snowflake( $channel ) ) { wp_die( 'Enter valid Discord server and channel IDs.', '', array( 'response' => 400 ) ); }
		update_option( self::OPTION, array( 'enabled' => isset( $_POST['enabled'] ), 'guild_id' => $guild, 'channel_id' => $channel ), false );
		self::deactivate(); self::maybe_schedule();
		wp_safe_redirect( admin_url( 'edit.php?post_type=tc_review&page=tc-discord-vouches' ), 303 ); exit;
	}

	public static function manual_sync(): void {
		self::require_admin( 'tc_sync_vouches' ); self::sync();
		wp_safe_redirect( admin_url( 'edit.php?post_type=tc_review&page=tc-discord-vouches' ), 303 ); exit;
	}
}
