<?php
/** Editable SEO metadata and indexing safeguards. @package TimonConfigs_Core */
defined( 'ABSPATH' ) || exit;

final class TC_SEO {
	private const NONCE = 'tc_seo_nonce';
	private const FIELDS = array( 'tc_seo_title', 'tc_seo_description', 'tc_seo_image', 'tc_noindex' );

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_metadata' ), 30 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ) );
		foreach ( array( 'tc_game', 'tc_device' ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'add_term_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'edit_term_fields' ) );
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_term' ) );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_term' ) );
		}
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 99 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_posts_query' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'sitemap_terms_query' ), 10, 2 );
		add_filter( 'wpseo_sitemap_entry', array( __CLASS__, 'sitemap_entry' ), 10, 3 );
		add_filter( 'rank_math/sitemap/entry', array( __CLASS__, 'sitemap_entry' ), 10, 3 );
		add_filter( 'wpseo_robots_array', array( __CLASS__, 'provider_robots' ) );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'provider_robots' ) );
		add_filter( 'aioseo_robots_meta', array( __CLASS__, 'aioseo_robots' ) );
	}

	public static function register_metadata(): void {
		foreach ( array( 'page', 'tc_config' ) as $post_type ) {
			add_post_type_support( $post_type, 'custom-fields' );
			foreach ( self::FIELDS as $key ) {
				register_post_meta( $post_type, $key, array(
					'type' => 'tc_noindex' === $key ? 'boolean' : 'string', 'single' => true, 'show_in_rest' => true,
					'sanitize_callback' => static fn( mixed $value ) => self::sanitize( $key, $value ),
					'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
				) );
			}
		}
		foreach ( array( 'tc_game', 'tc_device' ) as $taxonomy ) {
			foreach ( array( 'tc_seo_title', 'tc_seo_description', 'tc_noindex' ) as $key ) {
				register_term_meta( $taxonomy, $key, array(
					'type' => 'tc_noindex' === $key ? 'boolean' : 'string', 'single' => true, 'show_in_rest' => true,
					'sanitize_callback' => static fn( mixed $value ) => self::sanitize( $key, $value ),
					'auth_callback' => static fn() => current_user_can( 'manage_categories' ),
				) );
			}
		}
	}

	public static function sanitize( string $key, mixed $value ): string|bool {
		if ( ! is_scalar( $value ) ) { return 'tc_noindex' === $key ? false : ''; }
		if ( 'tc_noindex' === $key ) { return rest_sanitize_boolean( $value ); }
		if ( 'tc_seo_image' === $key ) { return esc_url_raw( (string) $value, array( 'https', 'http' ) ); }
		return 'tc_seo_description' === $key ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
	}

	public static function add_meta_boxes(): void {
		foreach ( array( 'page', 'tc_config' ) as $post_type ) {
			add_meta_box( 'tc_seo', __( 'Search and social metadata', 'timonconfigs-core' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'normal', 'default' );
		}
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'tc_save_seo', self::NONCE );
		foreach ( array( 'tc_seo_title' => 'SEO title', 'tc_seo_description' => 'Meta description', 'tc_seo_image' => 'Social image URL' ) as $key => $label ) {
			$value = (string) get_post_meta( $post->ID, $key, true );
			echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><input class="widefat" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . ( 'tc_seo_image' === $key ? 'url' : 'text' ) . '" value="' . esc_attr( $value ) . '"></p>';
		}
		echo '<p><label><input type="checkbox" name="tc_noindex" value="1" ' . checked( (bool) get_post_meta( $post->ID, 'tc_noindex', true ), true, false ) . '> Exclude from search indexing and XML sitemap</label></p><p>Leave blank for automatic page-specific metadata. An active SEO plugin controls its own title, social tags and schema; configure those values in that plugin.</p>';
	}

	public static function save_post( int $post_id ): void {
		if ( ! in_array( get_post_type( $post_id ), array( 'page', 'tc_config' ), true ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$nonce = isset( $_POST[ self::NONCE ] ) && is_string( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'tc_save_seo' ) ) { return; }
		foreach ( self::FIELDS as $key ) {
			if ( 'tc_noindex' === $key ) { update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ); continue; }
			if ( isset( $_POST[ $key ] ) ) { update_post_meta( $post_id, $key, self::sanitize( $key, wp_unslash( $_POST[ $key ] ) ) ); }
		}
	}

	public static function add_term_fields(): void {
		wp_nonce_field( 'tc_save_term_seo', self::NONCE );
		foreach ( array( 'tc_seo_title' => 'SEO title', 'tc_seo_description' => 'Meta description' ) as $key => $label ) {
			echo '<div class="form-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="text"></div>';
		}
		echo '<div class="form-field"><label><input type="checkbox" name="tc_noindex" value="1"> Exclude from search indexing</label><p>Empty or unpublished collections are automatically excluded from indexing and sitemaps.</p></div>';
	}

	public static function edit_term_fields( WP_Term $term ): void {
		wp_nonce_field( 'tc_save_term_seo', self::NONCE );
		foreach ( array( 'tc_seo_title' => 'SEO title', 'tc_seo_description' => 'Meta description' ) as $key => $label ) {
			echo '<tr class="form-field"><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) get_term_meta( $term->term_id, $key, true ) ) . '"></td></tr>';
		}
		echo '<tr class="form-field"><th>Indexing</th><td><label><input type="checkbox" name="tc_noindex" value="1" ' . checked( (bool) get_term_meta( $term->term_id, 'tc_noindex', true ), true, false ) . '> Exclude from search indexing</label><p>Empty or unpublished collections are automatically excluded from indexing and XML sitemaps.</p></td></tr>';
	}

	public static function save_term( int $term_id ): void {
		$nonce = isset( $_POST[ self::NONCE ] ) && is_string( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term || ! current_user_can( get_taxonomy( $term->taxonomy )->cap->manage_terms ) || ! wp_verify_nonce( $nonce, 'tc_save_term_seo' ) ) { return; }
		foreach ( array( 'tc_seo_title', 'tc_seo_description' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) { update_term_meta( $term_id, $key, self::sanitize( $key, wp_unslash( $_POST[ $key ] ) ) ); }
		}
		update_term_meta( $term_id, 'tc_noindex', isset( $_POST['tc_noindex'] ) );
	}

	public static function object_noindex( WP_Post|WP_Term $object ): bool {
		if ( $object instanceof WP_Post ) {
			return 'publish' !== $object->post_status || (bool) get_post_meta( $object->ID, 'tc_noindex', true ) || ( 'tc_config' === $object->post_type && in_array( get_post_meta( $object->ID, 'tc_release_status', true ), array( 'coming-soon', 'archived' ), true ) );
		}
		if ( (bool) get_term_meta( $object->term_id, 'tc_noindex', true ) ) { return true; }
		if ( in_array( $object->taxonomy, array( 'tc_game', 'tc_device', 'tc_platform' ), true ) ) {
			return method_exists( 'TC_Catalog', 'is_collection_indexable' ) ? ! TC_Catalog::is_collection_indexable( $object ) : 0 === (int) $object->count;
		}
		return false;
	}

	public static function should_noindex(): bool {
		if ( is_404() || is_search() || is_preview() ) { return true; }
		if ( is_post_type_archive( 'tc_config' ) || is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) {
			foreach ( array( 'config_search', 'game', 'device', 'tier', 'input', 'platform', 'sort' ) as $key ) {
				if ( isset( $_GET[ $key ] ) && ( ! is_scalar( $_GET[ $key ] ) || '' !== (string) $_GET[ $key ] ) ) { return true; }
			}
		}
		$object = get_queried_object();
		return ( $object instanceof WP_Post || $object instanceof WP_Term ) && self::object_noindex( $object );
	}

	public static function robots( array $robots ): array {
		if ( self::should_noindex() ) { unset( $robots['index'] ); $robots['noindex'] = true; }
		return $robots;
	}

	public static function provider_robots( array $robots ): array {
		if ( self::should_noindex() ) { $robots['index'] = 'noindex'; }
		return $robots;
	}

	public static function aioseo_robots( array $robots ): array {
		if ( self::should_noindex() ) { $robots['noindex'] = 'noindex'; }
		return $robots;
	}

	public static function sitemap_posts_query( array $args, string $post_type ): array {
		if ( ! in_array( $post_type, array( 'page', 'tc_config' ), true ) ) { return $args; }
		$clauses = array( 'relation' => 'AND', array( 'relation' => 'OR', array( 'key' => 'tc_noindex', 'compare' => 'NOT EXISTS' ), array( 'key' => 'tc_noindex', 'value' => '1', 'compare' => '!=' ) ) );
		if ( 'tc_config' === $post_type ) {
			$clauses[] = array( 'relation' => 'OR', array( 'key' => 'tc_release_status', 'compare' => 'NOT EXISTS' ), array( 'key' => 'tc_release_status', 'value' => array( 'coming-soon', 'archived' ), 'compare' => 'NOT IN' ) );
		}
		if ( ! empty( $args['meta_query'] ) ) { $clauses[] = $args['meta_query']; }
		$args['meta_query'] = $clauses;
		return $args;
	}

	public static function sitemap_terms_query( array $args, string $taxonomy ): array {
		if ( ! in_array( $taxonomy, array( 'tc_game', 'tc_device', 'tc_platform' ), true ) ) { return $args; }
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		$excluded = is_wp_error( $terms ) ? array() : array_map( static fn( WP_Term $term ): int => $term->term_id, array_filter( $terms, array( __CLASS__, 'object_noindex' ) ) );
		$args['exclude'] = array_unique( array_merge( (array) ( $args['exclude'] ?? array() ), $excluded ) );
		return $args;
	}

	public static function sitemap_entry( mixed $entry, mixed $type, mixed $object ): mixed {
		return ( $object instanceof WP_Post || $object instanceof WP_Term ) && self::object_noindex( $object ) ? false : $entry;
	}
}
