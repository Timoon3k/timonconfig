<?php
/** Device/game landing pages backed by the existing, protected config catalogue. */
defined( 'ABSPATH' ) || exit;

final class TC_Collections {
	private const MARKER = '_tc_collection';
	private const VERSION = '2026-10-30-v1';

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'prepare_request' ), 2 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'provider_canonical' ), 30 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'provider_canonical' ), 30 );
	}

	public static function definitions(): array {
		$definitions = array();
		foreach ( TC_Catalog::game_definitions() as $game => $details ) {
			foreach ( array( 'xim-matrix' => 'XIM Matrix', 'cronus-zen' => 'Cronus Zen' ) as $device => $name ) {
				$slug = $device . '-' . $game;
				$definitions[ $slug ] = array(
					'slug' => $slug, 'game' => $game, 'device' => $device, 'tier' => '',
					'title' => $name . ' ' . $details['name'] . ' Configs',
					'description' => sprintf( 'Browse %s %s configs. Compare supported inputs, platforms, release versions and Free or VIP access before choosing your setup.', $name, $details['name'] ),
				);
			}
		}
		$definitions['free-configs'] = array( 'slug' => 'free-configs', 'game' => '', 'device' => '', 'tier' => 'free', 'title' => 'Free Cronus Zen & XIM Matrix Configs', 'description' => 'Browse Free TimonConfigs releases for Cronus Zen and XIM Matrix. Check the game, input, platform and setup guide before requesting protected downloads.' );
		return $definitions;
	}

	public static function page_ids(): array {
		return get_posts( array( 'post_type' => 'page', 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => self::MARKER, 'no_found_rows' => true ) );
	}

	public static function definition( WP_Post|int $post ): ?array {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) { return null; }
		$key = get_post_meta( $post->ID, self::MARKER, true );
		$definitions = self::definitions();
		if ( ! is_string( $key ) || ! isset( $definitions[ $key ] ) ) { return null; }
		$definition = $definitions[ $key ];
		$definition['id'] = $post->ID;
		$definition['title'] = get_the_title( $post );
		$definition['game_term'] = $definition['game'] ? get_term_by( 'slug', $definition['game'], 'tc_game' ) : null;
		$definition['device_term'] = $definition['device'] ? get_term_by( 'slug', $definition['device'], 'tc_device' ) : null;
		return $definition;
	}

	public static function is_collection( WP_Post|int $post ): bool { return null !== self::definition( $post ); }
	public static function current(): ?array { return is_page() ? self::definition( (int) get_queried_object_id() ) : null; }

	private static function page_url( string $key ): string {
		foreach ( self::page_ids() as $id ) {
			if ( 'publish' === get_post_status( $id ) && $key === get_post_meta( $id, self::MARKER, true ) ) { return (string) get_permalink( $id ); }
		}
		return '';
	}
	public static function url( string $game, string $device ): string {
		$key = $device . '-' . $game;
		return isset( self::definitions()[ $key ] ) ? self::page_url( $key ) : '';
	}
	public static function free_url(): string { return self::page_url( 'free-configs' ); }
	public static function page_number(): int { return max( 1, absint( TC_Catalog::request_value( 'collection_page' ) ) ); }
	public static function canonical_url(): string {
		$current = self::current();
		if ( ! $current || is_404() ) { return ''; }
		$url = (string) get_permalink( $current['id'] );
		return self::page_number() > 1 ? add_query_arg( 'collection_page', self::page_number(), $url ) : $url;
	}
	public static function provider_canonical( mixed $url ): mixed {
		$current = self::current();
		if ( ! $current || is_404() ) { return $url; }
		$key = 'wpseo_canonical' === current_filter() ? '_yoast_wpseo_canonical' : 'rank_math_canonical_url';
		$authored = get_post_meta( $current['id'], $key, true );
		if ( is_scalar( $authored ) && '' !== trim( (string) $authored ) ) { return $url; }
		return self::canonical_url();
	}

	/** Creates only missing pages. Existing authored pages, drafts and deletions are preserved. */
	public static function install(): array|WP_Error {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'tc_collection_forbidden', 'Administrator permission is required.' ); }
		$existing = array();
		foreach ( self::page_ids() as $id ) { $existing[ get_post_meta( $id, self::MARKER, true ) ] = $id; }
		$ids = array();
		foreach ( self::definitions() as $key => $definition ) {
			if ( isset( $existing[ $key ] ) ) { $ids[ $key ] = $existing[ $key ]; continue; }
			if ( get_page_by_path( $key, OBJECT, 'page' ) ) { continue; }
			$game = $definition['game'] ? get_term_by( 'slug', $definition['game'], 'tc_game' ) : null;
			$device = $definition['device'] ? get_term_by( 'slug', $definition['device'], 'tc_device' ) : null;
			if ( $definition['game'] && ( ! $game instanceof WP_Term || ! $device instanceof WP_Term ) ) { continue; }
			$intro = $definition['description'];
			$guide = 'xim-matrix' === $definition['device'] ? 'Read the release guide for the supported input and platform before importing XIM Matrix text code. Controller and mouse & keyboard setups can require different configurations.' : ( 'cronus-zen' === $definition['device'] ? 'Check the individual release for its Cronus Zen script or backup format, supported input and installation steps. A device label does not imply compatibility with every game update.' : 'Free access follows the verification requirements shown on each configuration. Sign in with the same Discord account to request a protected file; files remain outside the public Media Library.' );
			$content = '<!-- wp:paragraph --><p>' . esc_html( $intro ) . '</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:heading --><h2 class="wp-block-heading">Choose the release that matches your setup</h2><!-- /wp:heading -->' . "\n" . '<!-- wp:paragraph --><p>' . esc_html( $guide ) . '</p><!-- /wp:paragraph -->';
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $definition['title'], 'post_name' => $key, 'post_content' => $content, 'meta_input' => array( self::MARKER => $key, 'tc_seo_title' => $definition['title'] . ' | TimonConfigs', 'tc_seo_description' => $definition['description'], 'rank_math_focus_keyword' => trim( ( $device instanceof WP_Term ? $device->name . ' ' : '' ) . ( $game instanceof WP_Term ? $game->name : 'Free configs' ) ) ) ), true );
			if ( ! is_wp_error( $id ) ) { $ids[ $key ] = $id; }
		}
		return $ids;
	}

	public static function maybe_install(): void {
		if ( wp_installing() || ! current_user_can( 'manage_options' ) || self::VERSION === get_option( 'timonconfigs_collections_version' ) ) { return; }
		$result = self::install();
		if ( is_wp_error( $result ) ) { return; }
		foreach ( array_diff_key( self::definitions(), $result ) as $key => $definition ) {
			// An authored page occupying a slug is an intentional skip, not a retry loop.
			if ( ! get_page_by_path( $key, OBJECT, 'page' ) ) { return; }
		}
		update_option( 'timonconfigs_collections_version', self::VERSION, false );
	}

	private static function constraints( array $definition ): array {
		$tax = array( 'relation' => 'AND' );
		foreach ( array( 'game' => 'tc_game', 'device' => 'tc_device' ) as $key => $taxonomy ) {
			if ( $definition[ $key ] ) { $tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $definition[ $key ] ); }
		}
		return $tax;
	}
	public static function has_configs( array $definition ): bool {
		$meta = array( 'relation' => 'AND', TC_Catalog::released_meta_query() );
		if ( $definition['tier'] ) { $meta[] = array( 'key' => 'tc_tier', 'value' => $definition['tier'] ); }
		$query = new WP_Query( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => self::constraints( $definition ), 'meta_query' => $meta ) );
		return (bool) $query->posts;
	}
	public static function is_indexable( array $definition ): bool {
		if ( empty( $definition['id'] ) || 'publish' !== get_post_status( $definition['id'] ) || get_post_field( 'post_password', $definition['id'] ) || get_post_meta( $definition['id'], 'tc_noindex', true ) ) { return false; }
		foreach ( array( 'game', 'device' ) as $key ) {
			if ( ! $definition[ $key ] ) { continue; }
			$term = $definition[ $key . '_term'] ?? get_term_by( 'slug', $definition[ $key ], 'tc_' . $key );
			if ( ! $term instanceof WP_Term || get_term_meta( $term->term_id, 'tc_noindex', true ) || strlen( trim( wp_strip_all_tags( $term->description ) ) ) < 120 ) { return false; }
		}
		return self::has_configs( $definition );
	}

	/** Path constraints are authoritative; extra filters can narrow but never broaden them. */
	public static function query( array $definition ): WP_Query {
		$intent = TC_Catalog::search_intent( TC_Catalog::request_value( 'config_search' ) );
		$tax = self::constraints( $definition );
		foreach ( array( 'game' => 'tc_game', 'device' => 'tc_device', 'platform' => 'tc_platform' ) as $key => $taxonomy ) {
			$value = sanitize_title( TC_Catalog::request_value( $key ) ?: ( $intent[ $key ] ?? '' ) );
			if ( $value && $value !== ( $definition[ $key ] ?? '' ) ) { $tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $value ); }
		}
		$meta = array( 'relation' => 'AND', TC_Catalog::catalog_visible_meta_query() );
		foreach ( array_unique( array_filter( array( $definition['tier'], TC_Catalog::sanitize_tier( TC_Catalog::request_value( 'tier' ) ?: $intent['tier'] ) ) ) ) as $tier ) { $meta[] = array( 'key' => 'tc_tier', 'value' => $tier ); }
		$input = TC_Catalog::sanitize_input( TC_Catalog::request_value( 'input' ) );
		if ( $input ) { $meta[] = array( 'key' => 'tc_input_method', 'value' => $input ); }
		$sort = TC_Catalog::selected_sort();
		$column = array( 'newest' => 'date', 'updated' => 'modified', 'title' => 'title', 'downloads' => 'date' )[ $sort ];
		$direction = 'title' === $sort ? 'ASC' : 'DESC';
		return new WP_Query( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'posts_per_page' => 12, 'paged' => self::page_number(), 'tax_query' => $tax, 'meta_query' => $meta, 's' => $intent['text'], 'orderby' => array( $column => $direction, 'ID' => $direction ), 'order' => $direction, '_tc_collection_query' => true, '_tc_download_order' => 'downloads' === $sort ) );
	}

	/** A submitted topical search is routed to its real collection, preserving narrower filters. */
	public static function search_destination(): string {
		$current = self::current();
		if ( ! $current && ! is_post_type_archive( 'tc_config' ) && ! is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) { return ''; }
		$intent = TC_Catalog::search_intent( TC_Catalog::request_value( 'config_search' ) );
		$game = sanitize_title( TC_Catalog::request_value( 'game' ) ?: $intent['game'] );
		$device = sanitize_title( TC_Catalog::request_value( 'device' ) ?: $intent['device'] );
		$tier = TC_Catalog::sanitize_tier( TC_Catalog::request_value( 'tier' ) ?: $intent['tier'] );
		$platform = TC_Catalog::request_value( 'platform' );
		$term = get_queried_object();
		if ( ! $game && $term instanceof WP_Term && 'tc_game' === $term->taxonomy ) { $game = $term->slug; }
		if ( ! $device && $term instanceof WP_Term && 'tc_device' === $term->taxonomy ) { $device = $term->slug; }
		if ( ! $platform && $term instanceof WP_Term && 'tc_platform' === $term->taxonomy ) { $platform = $term->slug; }
		if ( $current ) {
			$game = $current['game'] ?: $game; $device = $current['device'] ?: $device; $tier = $current['tier'] ?: $tier;
		}
		$target = $game && $device ? self::url( $game, $device ) : '';
		if ( ! $target && 'free' === $tier && ! $game && ! $device ) { $target = self::free_url(); }
		if ( ! $target && ( ( $game && ! $device ) || ( $device && ! $game ) ) ) {
			$term = get_term_by( 'slug', $game ?: $device, $game ? 'tc_game' : 'tc_device' );
			$target = $term instanceof WP_Term ? get_term_link( $term ) : '';
			if ( is_wp_error( $target ) ) { $target = ''; }
		}
		if ( ! $target ) { return ''; }
		$parameters = array();
		if ( $intent['text'] ) { $parameters['config_search'] = $intent['text']; }
		$input = TC_Catalog::request_value( 'input' ); if ( $input ) { $parameters['input'] = $input; }
		if ( $platform ) { $parameters['platform'] = $platform; }
		if ( $tier && untrailingslashit( $target ) !== untrailingslashit( self::free_url() ) ) { $parameters['tier'] = $tier; }
		$sort = TC_Catalog::selected_sort(); if ( 'updated' !== $sort ) { $parameters['sort'] = $sort; }
		if ( $current && self::page_number() > 1 ) { $parameters['collection_page'] = self::page_number(); }
		return add_query_arg( $parameters, $target );
	}

	public static function prepare_request(): void {
		$current = self::current();
		if ( $current && is_user_logged_in() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
			nocache_headers();
			if ( ! headers_sent() ) { header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' ); }
		}
		if ( $current && self::page_number() > 1 && ! self::query( $current )->have_posts() ) {
			global $wp_query; $wp_query->set_404(); status_header( 404 ); nocache_headers(); return;
		}
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) || is_admin() || is_preview() || is_404() ) { return; }
		$target = self::search_destination();
		if ( ! $target ) { return; }
		$request = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
		$target_parts = wp_parse_url( $target );
		$path = ( $target_parts['path'] ?? '/' ) . ( isset( $target_parts['query'] ) ? '?' . $target_parts['query'] : '' );
		if ( $request && $path !== $request ) { wp_safe_redirect( $target, 302, 'TimonConfigs collection search' ); exit; }
	}

	public static function template( string $template ): string {
		if ( ! self::current() || is_404() ) { return $template; }
		$file = locate_template( 'page-config-collection.php' );
		return $file ?: $template;
	}
}
