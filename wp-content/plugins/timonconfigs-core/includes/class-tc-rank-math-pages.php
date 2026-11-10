<?php
/** Reviewed per-page Rank Math fields, with recoverable and idempotent writes. */
defined( 'ABSPATH' ) || exit;

final class TC_Rank_Math_Pages {
	private const VERSION = '2026-11-10-v2';
	private const BACKUP = '_tc_rank_math_page_backup';
	private const MANAGED = '_tc_rank_math_page_managed';
	private const STATE = 'timonconfigs_page_seo_state';
	private const QUEUE = 'timonconfigs_page_seo_queue';
	private const LOCK = '_tc_page_seo_lock';
	private const FIELDS = array( 'title', 'description', 'focus_keyword' );
	private const TYPES = array( 'page', 'post', 'tc_config', 'product' );
	private const TAXONOMIES = array( 'tc_game', 'tc_device', 'tc_platform' );

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_apply' ), 60 );
		add_action( 'admin_menu', static function (): void { add_management_page( 'Page SEO', 'Page SEO', 'manage_options', 'tc-page-seo', array( __CLASS__, 'settings_page' ) ); } );
		add_action( 'admin_post_tc_page_seo', array( __CLASS__, 'handle_action' ) );
		add_action( 'save_post', static function ( int $id ): void {
			if ( ! wp_is_post_revision( $id ) && ! wp_is_post_autosave( $id ) && in_array( get_post_type( $id ), self::TYPES, true ) ) { self::queue( 'post', $id ); }
		}, 1000 );
		foreach ( self::TAXONOMIES as $taxonomy ) { add_action( 'created_' . $taxonomy, static function ( int $id ): void { self::queue( 'term', $id ); }, 1000 ); }
	}

	public static function catalogue(): array {
		static $data = null;
		if ( null === $data ) {
			$path = dirname( __DIR__ ) . '/data/page-seo.json';
			$data = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array();
			$data = is_array( $data ) ? $data : array();
		}
		return $data;
	}

	private static function text( string $value ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $value, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}

	/** Only public labels and explicit public excerpts; never files, Values or rendered content. */
	public static function profile( WP_Post|WP_Term $object ): array {
		$data = self::catalogue(); $profile = array();
		if ( $object instanceof WP_Term ) {
			if ( ! in_array( $object->taxonomy, self::TAXONOMIES, true ) ) { return array(); }
			$section = array( 'tc_game' => 'games', 'tc_device' => 'devices', 'tc_platform' => 'platforms' )[ $object->taxonomy ];
			$profile = $data[ $section ][ $object->slug ] ?? array();
			$name = self::text( $object->name );
		} else {
			if ( ! in_array( $object->post_type, self::TYPES, true ) || in_array( $object->post_name, array( 'sample-page', 'hello-world' ), true ) ) { return array(); }
			$name = self::text( $object->post_title );
			if ( 'page' === $object->post_type ) {
				if ( 'page' === get_option( 'show_on_front' ) && $object->ID === (int) get_option( 'page_on_front' ) ) { $profile = $data['pages']['home'] ?? array(); }
				if ( ! $profile && function_exists( 'wc_get_page_id' ) ) {
					foreach ( array( 'cart', 'checkout', 'myaccount', 'shop' ) as $role ) {
						if ( $object->ID === (int) wc_get_page_id( $role ) ) { $profile = $data['utility'][ $role ] ?? array(); break; }
					}
				}
				if ( ! $profile && has_shortcode( $object->post_content, 'timonconfigs_member_downloads' ) ) { $profile = $data['utility']['my-downloads'] ?? array(); }
				if ( ! $profile && has_shortcode( $object->post_content, 'timonconfigs_member_area' ) ) { $profile = $data['utility']['account'] ?? array(); }
				$path = get_page_uri( $object );
				if ( ! $profile ) { $profile = $data['pages'][ $path ] ?? $data['utility'][ $path ] ?? array(); }
				$collection = get_post_meta( $object->ID, '_tc_collection', true );
				if ( ! $profile && is_string( $collection ) && isset( $data['pages'][ $collection ] ) ) { $profile = $data['pages'][ $collection ]; }
			}
		}
		if ( $profile ) { return array_merge( $profile, array( 'curated' => true ) ); }
		if ( '' === $name ) { return array(); }
		$description = '';
		if ( $object instanceof WP_Post && 'publish' === $object->post_status && '' === $object->post_password && 'tc_config' !== $object->post_type ) {
			$description = self::text( (string) preg_replace( '/\[[^\]]*\]/', '', strip_shortcodes( $object->post_excerpt ) ) );
		}
		if ( $object instanceof WP_Post && 'tc_config' === $object->post_type ) {
			$labels = wp_get_object_terms( $object->ID, array( 'tc_game', 'tc_device' ), array( 'fields' => 'names' ) );
			$context = ! is_wp_error( $labels ) && $labels ? ' for ' . implode( ', ', array_map( array( __CLASS__, 'text' ), $labels ) ) : '';
			$description = $name . $context . '. Read release details, supported input, platform notes, version and setup instructions before downloading.';
		} elseif ( '' === $description ) {
			$description = $name . ( $object instanceof WP_Term ? ' configs: review the collection, release status and individual setup details from TimonConfigs.' : ': read the public page details and related information from TimonConfigs.' );
		} elseif ( false === stripos( $description, $name ) ) { $description = $name . ': ' . $description; }
		return array( 'title' => $name . ' | TimonConfigs', 'focus_keyword' => $name, 'description' => wp_html_excerpt( $description, 165, '…' ), 'curated' => false );
	}

	private static function allowed(): bool { return current_user_can( 'manage_options' ); }
	private static function forbidden(): WP_Error { return new WP_Error( 'tc_seo_forbidden', 'Administrator permission is required.' ); }
	private static function get( string $type, int $id, string $key ): mixed { return 'post' === $type ? get_post_meta( $id, $key, true ) : get_term_meta( $id, $key, true ); }
	private static function put( string $type, int $id, string $key, mixed $value ): void {
		if ( 'post' === $type ) { update_post_meta( $id, $key, wp_slash( $value ) ); } else { update_term_meta( $id, $key, wp_slash( $value ) ); }
	}

	public static function manages_title( WP_Post|WP_Term $object ): bool {
		$type = $object instanceof WP_Post ? 'post' : 'term'; $id = $object instanceof WP_Post ? $object->ID : $object->term_id;
		$managed = self::get( $type, $id, self::MANAGED );
		return is_array( $managed ) && isset( $managed['fields']['rank_math_title'] ) && $managed['fields']['rank_math_title'] === self::get( $type, $id, 'rank_math_title' );
	}

	private static function apply( string $type, WP_Post|WP_Term $object ): array|WP_Error {
		if ( ! self::allowed() || ( $object instanceof WP_Post && ! current_user_can( 'edit_post', $object->ID ) ) ) { return self::forbidden(); }
		$id = $object instanceof WP_Post ? $object->ID : $object->term_id;
		$profile = self::profile( $object ); $result = array( 'changed' => 0, 'kept' => 0 );
		$managed = self::get( $type, $id, self::MANAGED );
		if ( ! $profile || ( is_array( $managed ) && ( ! empty( $managed['restored'] ) || self::VERSION === ( $managed['version'] ?? '' ) ) ) ) { return $result; }
		$backup = self::get( $type, $id, self::BACKUP );
		if ( ! is_array( $backup ) ) {
			$backup = array();
			foreach ( self::FIELDS as $field ) { $key = 'rank_math_' . $field; $backup[ $key ] = array( 'exists' => metadata_exists( $type, $id, $key ), 'value' => self::get( $type, $id, $key ) ); }
			if ( 'post' === $type ) { add_post_meta( $id, self::BACKUP, wp_slash( $backup ), true ); } else { add_term_meta( $id, self::BACKUP, wp_slash( $backup ), true ); }
			if ( self::get( $type, $id, self::BACKUP ) !== $backup ) { return new WP_Error( 'tc_seo_backup', 'SEO fields were not changed because their backup could not be saved.' ); }
		}
		$owned = array();
		foreach ( self::FIELDS as $field ) {
			$key = 'rank_math_' . $field; $current = self::get( $type, $id, $key ); $desired = sanitize_text_field( (string) $profile[ $field ] );
			$previous = is_array( $managed ) ? ( $managed['fields'][ $key ] ?? null ) : null;
			if ( ! is_scalar( $current ) || ( is_array( $managed ) && $current !== $previous ) || ( ! $profile['curated'] && '' !== trim( (string) $current ) && $current !== $previous ) ) { ++$result['kept']; continue; }
			if ( $current !== $desired ) { self::put( $type, $id, $key, $desired ); }
			if ( self::get( $type, $id, $key ) !== $desired ) { return new WP_Error( 'tc_seo_write', 'A page SEO field could not be saved. Previous values remain backed up.' ); }
			$owned[ $key ] = $desired;
			if ( $current !== $desired ) { ++$result['changed']; }
		}
		self::put( $type, $id, self::MANAGED, array( 'version' => self::VERSION, 'fields' => $owned ) );
		if ( $result['changed'] ) { $url = $object instanceof WP_Post ? get_permalink( $object ) : get_term_link( $object ); if ( ! is_wp_error( $url ) ) { do_action( 'litespeed_purge_url', $url ); } }
		return $result;
	}

	public static function apply_post( int $id ): array|WP_Error {
		if ( ! self::allowed() ) { return self::forbidden(); }
		if ( $id <= 0 ) { return new WP_Error( 'tc_seo_post', 'Choose a valid page ID.' ); }
		$post = get_post( $id );
		return $post instanceof WP_Post && in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'future' ), true ) ? self::apply( 'post', $post ) : new WP_Error( 'tc_seo_post', 'The page is not eligible for metadata preparation.' );
	}

	public static function apply_term( int $id ): array|WP_Error {
		if ( ! self::allowed() ) { return self::forbidden(); }
		if ( $id <= 0 ) { return new WP_Error( 'tc_seo_term', 'Choose a valid collection ID.' ); }
		$term = get_term( $id );
		return $term instanceof WP_Term && in_array( $term->taxonomy, self::TAXONOMIES, true ) ? self::apply( 'term', $term ) : new WP_Error( 'tc_seo_term', 'The collection is not eligible for metadata preparation.' );
	}

	public static function apply_archive(): array|WP_Error {
		if ( ! self::allowed() ) { return self::forbidden(); }
		$options = get_option( 'rank-math-options-titles', array() );
		if ( ! is_array( $options ) ) { return new WP_Error( 'tc_seo_options', 'Review the existing Rank Math title settings before continuing.' ); }
		$managed = get_option( 'tc_rank_math_archive_managed', array() );
		if ( is_array( $managed ) && ( ! empty( $managed['restored'] ) || self::VERSION === ( $managed['version'] ?? '' ) ) ) { return array( 'changed' => 0 ); }
		$profile = self::catalogue()['archive']['tc_config'] ?? array();
		if ( ! $profile ) { return new WP_Error( 'tc_seo_profile', 'The archive metadata profile is missing.' ); }
		if ( false === get_option( 'tc_rank_math_archive_backup', false ) ) {
			$backup = array();
			foreach ( array( 'title', 'description' ) as $field ) { $key = 'pt_tc_config_archive_' . $field; $backup[ $key ] = array( 'exists' => array_key_exists( $key, $options ), 'value' => $options[ $key ] ?? '' ); }
			if ( ! add_option( 'tc_rank_math_archive_backup', $backup, '', false ) ) { return new WP_Error( 'tc_seo_backup', 'The archive backup could not be saved.' ); }
		}
		$owned = array(); $changed = 0;
		foreach ( array( 'title', 'description' ) as $field ) {
			$key = 'pt_tc_config_archive_' . $field; $current = $options[ $key ] ?? '';
			if ( $managed && $current !== ( $managed['fields'][ $key ] ?? null ) ) { continue; }
			$desired = $profile[ $field ] . ( 'title' === $field ? ' %page%' : '' );
			$owned[ $key ] = $desired;
			if ( $current !== $desired ) { $options[ $key ] = $desired; ++$changed; }
		}
		if ( $changed ) { update_option( 'rank-math-options-titles', $options ); self::reset_settings(); do_action( 'litespeed_purge_url', get_post_type_archive_link( 'tc_config' ) ); }
		$stored = get_option( 'rank-math-options-titles', array() );
		foreach ( $owned as $key => $value ) { if ( ! is_array( $stored ) || ( $stored[ $key ] ?? null ) !== $value ) { return new WP_Error( 'tc_seo_write', 'The archive metadata could not be saved. Previous values remain backed up.' ); } }
		update_option( 'tc_rank_math_archive_managed', array( 'version' => self::VERSION, 'fields' => $owned ), false );
		return array( 'changed' => $changed );
	}

	private static function reset_settings(): void {
		if ( function_exists( 'rank_math' ) && isset( rank_math()->settings ) && is_callable( array( rank_math()->settings, 'reset' ) ) ) { rank_math()->settings->reset(); }
	}

	/** Restore only our unchanged writes, preserving any later editor correction. */
	public static function restore( string $type, int $id = 0 ): array|WP_Error {
		if ( ! self::allowed() ) { return self::forbidden(); }
		$owner = self::lock(); if ( ! $owner ) { return new WP_Error( 'tc_seo_busy', 'Another page SEO update is running. Please retry shortly.' ); }
		try { return self::restore_record( $type, $id ); } finally { self::unlock( $owner ); }
	}

	private static function restore_record( string $type, int $id ): array|WP_Error {
		if ( ! in_array( $type, array( 'post', 'term', 'archive' ), true ) ) { return new WP_Error( 'tc_seo_restore', 'Choose an eligible metadata record.' ); }
		if ( 'post' === $type && ( $id <= 0 || ! get_post( $id ) || ! current_user_can( 'edit_post', $id ) ) ) { return self::forbidden(); }
		if ( 'term' === $type ) { $term = get_term( $id ); if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, self::TAXONOMIES, true ) ) { return self::forbidden(); } }
		$archive = 'archive' === $type;
		$backup = $archive ? get_option( 'tc_rank_math_archive_backup' ) : self::get( $type, $id, self::BACKUP );
		$managed = $archive ? get_option( 'tc_rank_math_archive_managed' ) : self::get( $type, $id, self::MANAGED );
		if ( ! is_array( $backup ) || ! is_array( $managed ) ) { return new WP_Error( 'tc_seo_restore', 'No saved metadata backup exists for this record.' ); }
		$options = $archive ? get_option( 'rank-math-options-titles', array() ) : array();
		if ( ! is_array( $options ) ) { return new WP_Error( 'tc_seo_options', 'Review the existing Rank Math title settings before restoring.' ); }
		$changed = 0; $kept = 0;
		foreach ( (array) ( $managed['fields'] ?? array() ) as $key => $value ) {
			$allowed = $archive ? array( 'pt_tc_config_archive_title', 'pt_tc_config_archive_description' ) : array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' );
			if ( ! in_array( $key, $allowed, true ) ) { continue; }
			$current = $archive ? ( $options[ $key ] ?? '' ) : self::get( $type, $id, $key );
			if ( $current !== $value || ! isset( $backup[ $key ] ) ) { ++$kept; continue; }
			if ( $backup[ $key ]['exists'] ) { if ( $archive ) { $options[ $key ] = $backup[ $key ]['value']; } else { self::put( $type, $id, $key, $backup[ $key ]['value'] ); } }
			elseif ( $archive ) { unset( $options[ $key ] ); }
			elseif ( 'post' === $type ) { delete_post_meta( $id, $key ); } else { delete_term_meta( $id, $key ); }
			if ( ! $archive && ( $backup[ $key ]['exists'] ? self::get( $type, $id, $key ) !== $backup[ $key ]['value'] : metadata_exists( $type, $id, $key ) ) ) { return new WP_Error( 'tc_seo_restore_write', 'A previous field could not be restored. The backup and managed marker were retained for retry.' ); }
			++$changed;
		}
		$marker = array( 'version' => self::VERSION, 'fields' => array(), 'restored' => true );
		if ( $archive ) {
			update_option( 'rank-math-options-titles', $options );
			if ( get_option( 'rank-math-options-titles' ) !== $options ) { return new WP_Error( 'tc_seo_restore_write', 'Previous archive fields could not be restored. The backup was retained for retry.' ); }
			update_option( 'tc_rank_math_archive_managed', $marker, false ); self::reset_settings();
		}
		else { self::put( $type, $id, self::MANAGED, $marker ); }
		if ( $changed ) { $url = $archive ? get_post_type_archive_link( 'tc_config' ) : ( 'post' === $type ? get_permalink( $id ) : get_term_link( $id ) ); if ( ! is_wp_error( $url ) ) { do_action( 'litespeed_purge_url', $url ); } }
		return array( 'changed' => $changed, 'kept' => $kept );
	}

	private static function queue( string $type, int $id ): void {
		if ( ! self::allowed() ) { return; }
		if ( 'post' === $type && ! in_array( get_post_status( $id ), array( 'publish', 'draft', 'pending', 'future' ), true ) ) { return; }
		$queue = get_option( self::QUEUE, array() ); $queue = is_array( $queue ) ? $queue : array();
		$queue[ $type . ':' . $id ] = array( $type, $id ); update_option( self::QUEUE, $queue, false );
	}

	public static function maybe_apply(): void {
		if ( ! is_admin() || ! self::allowed() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) || wp_doing_ajax() || wp_doing_cron() || wp_installing() || is_network_admin() || 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ) ) { return; }
		self::run_batch();
	}

	private static function lock(): string {
		global $wpdb;
		$owner = wp_generate_uuid4(); $value = array( 'owner' => $owner, 'expires' => time() + 120 );
		if ( add_option( self::LOCK, $value, '', false ) ) { return $owner; }
		$old = get_option( self::LOCK );
		if ( ! is_array( $old ) || (int) ( $old['expires'] ?? PHP_INT_MAX ) >= time() ) { return ''; }
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s", maybe_serialize( $value ), self::LOCK, maybe_serialize( $old ) ) );
		wp_cache_delete( self::LOCK, 'options' ); return 1 === $changed ? $owner : '';
	}

	private static function unlock( string $owner ): void {
		global $wpdb;
		$value = get_option( self::LOCK );
		if ( is_array( $value ) && ( $value['owner'] ?? '' ) === $owner ) { $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", self::LOCK, maybe_serialize( $value ) ) ); wp_cache_delete( self::LOCK, 'options' ); }
	}

	/** Bounded admin work; no SEO writes during visitor, checkout, upload or cron requests. */
	public static function run_batch(): array|WP_Error {
		if ( ! self::allowed() ) { return self::forbidden(); }
		$owner = self::lock(); if ( ! $owner ) { return new WP_Error( 'tc_seo_busy', 'Another page SEO update is running. Please retry shortly.' ); }
		try { return self::process_batch(); } finally { self::unlock( $owner ); }
	}

	private static function process_batch(): array|WP_Error {
		global $wpdb;
		if ( ! isset( self::catalogue()['pages']['home']['title'], self::catalogue()['archive']['tc_config']['description'] ) ) { return new WP_Error( 'tc_seo_profile', 'The prepared SEO data file is missing or invalid. Reinstall the complete Core package.' ); }
		$state = get_option( self::STATE, array() );
		if ( ! is_array( $state ) || self::VERSION !== ( $state['version'] ?? '' ) ) { $state = array( 'version' => self::VERSION, 'last_id' => 0, 'term_offset' => 0, 'done' => false ); }
		$changed = 0;
		$queue = get_option( self::QUEUE, array() ); $queue = is_array( $queue ) ? $queue : array();
		foreach ( array_slice( $queue, 0, 20, true ) as $key => $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry[0], $entry[1] ) || ! in_array( $entry[0], array( 'post', 'term' ), true ) || ! is_numeric( $entry[1] ) || (int) $entry[1] <= 0 ) { unset( $queue[ $key ] ); continue; }
			$result = 'term' === $entry[0] ? self::apply_term( (int) $entry[1] ) : self::apply_post( (int) $entry[1] );
			if ( ! is_wp_error( $result ) ) { $changed += $result['changed']; unset( $queue[ $key ] ); }
			elseif ( in_array( $result->get_error_code(), array( 'tc_seo_post', 'tc_seo_term', 'tc_seo_forbidden' ), true ) ) { unset( $queue[ $key ] ); }
			else { unset( $queue[ $key ] ); $queue[ $key ] = $entry; }
		}
		update_option( self::QUEUE, $queue, false );
		if ( empty( $state['done'] ) ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ('page','post','tc_config','product') AND post_status IN ('publish','draft','pending','future') ORDER BY ID ASC LIMIT 81", (int) $state['last_id'] ) );
			foreach ( array_slice( $ids, 0, 80 ) as $id ) { $result = self::apply_post( (int) $id ); if ( is_wp_error( $result ) ) { return $result; } $changed += $result['changed']; $state['last_id'] = (int) $id; }
			if ( count( $ids ) <= 80 ) {
				$terms = get_terms( array( 'taxonomy' => self::TAXONOMIES, 'hide_empty' => false, 'number' => 81, 'offset' => (int) $state['term_offset'], 'orderby' => 'term_id', 'order' => 'ASC' ) );
				if ( is_wp_error( $terms ) ) { return $terms; }
				foreach ( array_slice( $terms, 0, 80 ) as $term ) { $result = self::apply_term( $term->term_id ); if ( is_wp_error( $result ) ) { return $result; } $changed += $result['changed']; ++$state['term_offset']; }
				if ( count( $terms ) <= 80 ) { $result = self::apply_archive(); if ( is_wp_error( $result ) ) { return $result; } $changed += $result['changed']; $state['done'] = true; }
			}
		}
		$state['changed_fields'] = (int) ( $state['changed_fields'] ?? 0 ) + $changed;
		update_option( self::STATE, $state, false );
		return array( 'changed' => $changed, 'done' => (bool) $state['done'], 'queued' => count( $queue ) );
	}

	public static function settings_page(): void {
		if ( ! self::allowed() ) { wp_die( 'Administrator permission is required.', '', array( 'response' => 403 ) ); }
		$state = get_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		echo '<div class="wrap"><h1>Page SEO — Rank Math</h1><p>Reviewed titles, descriptions and primary focus keywords are written directly to Rank Math fields. Previous values are backed up; subsequent editor corrections are preserved. Unknown authored pages keep existing Rank Math fields. New configuration descriptions use public labels, never private Values or files.</p>';
		echo '<p>Status: <strong>' . ( ! empty( $state['done'] ) ? 'Initial metadata preparation complete' : 'Metadata preparation in progress' ) . '</strong>. Refresh this page or continue the next batch for a large catalogue.</p><p>Titles and keywords do not guarantee rankings. PHP-generated page content may not be counted by the editor analysis. Indexing, canonical URLs, schema, protected pages and existing availability rules are unchanged.</p>';
		self::form( 'batch', 'Continue metadata preparation' );
		echo '<table class="widefat striped"><thead><tr><th>Page / collection</th><th>SEO title</th><th>Primary focus keyword</th><th>Meta description</th><th>Previous values</th></tr></thead><tbody>';
		$page = max( 1, isset( $_GET['seo_page'] ) ? absint( $_GET['seo_page'] ) : 1 );
		$posts = new WP_Query( array( 'post_type' => self::TYPES, 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'posts_per_page' => 50, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC' ) );
		foreach ( $posts->posts as $post ) { if ( self::profile( $post ) ) { self::row( 'post', $post->ID, $post->post_title, get_edit_post_link( $post->ID, 'raw' ) ?: '' ); } }
		$terms = get_terms( array( 'taxonomy' => self::TAXONOMIES, 'hide_empty' => false, 'number' => 200 ) );
		if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { self::row( 'term', $term->term_id, $term->name . ' collection', get_edit_term_link( $term ) ?: '' ); } }
		$options = get_option( 'rank-math-options-titles', array() ); $options = is_array( $options ) ? $options : array(); $archive = self::catalogue()['archive']['tc_config'] ?? array();
		echo '<tr><td>/configs/ archive</td><td>' . esc_html( (string) ( $options['pt_tc_config_archive_title'] ?? '' ) ) . '</td><td>' . esc_html( $archive['focus_keyword'] ?? '' ) . '<br><small>Editorial target; Rank Math has no archive focus keyword field.</small></td><td>' . esc_html( (string) ( $options['pt_tc_config_archive_description'] ?? '' ) ) . '</td><td>'; self::form( 'restore', 'Restore archive fields', 'archive' ); echo '</td></tr></tbody></table>';
		if ( $posts->max_num_pages > 1 ) { echo '<p>' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'seo_page', '%#%' ), 'format' => '', 'total' => $posts->max_num_pages, 'current' => $page ) ) ) . '</p>'; }
		echo '<p>To edit: open the page, then Rank Math → Edit Snippet and Focus Keyword. Collection terms use the Rank Math fields in their term editor. Restoring previous values preserves any later manual field edits.</p></div>';
	}

	private static function row( string $type, int $id, string $label, string $edit ): void {
		echo '<tr><td><a href="' . esc_url( $edit ) . '">' . esc_html( $label ) . '</a></td>';
		foreach ( array( 'title', 'focus_keyword', 'description' ) as $field ) { echo '<td>' . esc_html( (string) self::get( $type, $id, 'rank_math_' . $field ) ) . '</td>'; }
		echo '<td>'; if ( is_array( self::get( $type, $id, self::BACKUP ) ) ) { self::form( 'restore', 'Restore previous fields', $type, $id ); } echo '</td></tr>';
	}

	private static function form( string $operation, string $label, string $type = '', int $id = 0 ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tc_page_seo"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '"><input type="hidden" name="object_type" value="' . esc_attr( $type ) . '"><input type="hidden" name="object_id" value="' . esc_attr( (string) $id ) . '">'; wp_nonce_field( 'tc_page_seo' ); submit_button( $label, 'secondary', 'submit', false ); echo '</form>';
	}

	public static function handle_action(): void {
		if ( ! self::allowed() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'Administrator permission is required.', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'tc_page_seo' );
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? wp_unslash( $_POST['operation'] ) : '';
		$result = 'batch' === $operation ? self::run_batch() : ( 'restore' === $operation ? self::restore( isset( $_POST['object_type'] ) && is_string( $_POST['object_type'] ) ? wp_unslash( $_POST['object_type'] ) : '', isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0 ) : new WP_Error( 'tc_seo_action', 'Choose a supported SEO action.' ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) ); }
		wp_safe_redirect( admin_url( 'tools.php?page=tc-page-seo' ), 303 ); exit;
	}
}
