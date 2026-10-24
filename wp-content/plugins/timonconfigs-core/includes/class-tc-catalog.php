<?php
/**
 * Config catalogue, editable collections and compatibility fields.
 *
 * The existing tc_config identifier is deliberately retained to preserve data.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Catalog {
	private const POST_TYPE = 'tc_config';
	private const NONCE = 'tc_config_details_nonce';
	private const META = array(
		'tc_tier'           => 'sanitize_tier',
		'tc_platform'       => 'sanitize_text',
		'tc_input_method'   => 'sanitize_input',
		'tc_config_version' => 'sanitize_text',
		'tc_last_updated'   => 'sanitize_date',
		'tc_compatibility'  => 'sanitize_multiline',
		'tc_tutorial_url'   => 'sanitize_public_url',
		'tc_changelog'      => 'sanitize_multiline',
		'tc_access_url'     => 'sanitize_public_url',
		'tc_release_status' => 'sanitize_release_status',
		'tc_game_version'   => 'sanitize_text',
		'tc_publisher'      => 'sanitize_text',
		'tc_creator'        => 'sanitize_text',
		'tc_release_date'   => 'sanitize_date',
	);

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'refresh_routes' ), 99 );
		add_action( 'init', array( __CLASS__, 'register_content_types' ) );
		add_action( 'init', array( __CLASS__, 'register_landing_rewrites' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_filter( 'manage_tc_config_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_tc_config_posts_custom_column', array( __CLASS__, 'admin_column' ), 10, 2 );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_archive' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'download_order_clauses' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'private_member_cache' ), 1 );
		add_filter( 'term_link', array( __CLASS__, 'filter_core_term_links' ), 10, 3 );
		foreach ( array( 'tc_game', 'tc_device' ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'render_collection_add_field' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'render_collection_edit_field' ) );
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_collection_image' ) );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_collection_image' ) );
		}
	}

	/** Useful, distinct collection copy; availability is always derived from real releases. */
	public static function game_definitions(): array {
		return array(
			'apex-legends' => array(
				'name' => 'Apex Legends',
				'description' => 'Explore the TimonConfigs Apex Legends collection for Cronus Zen and XIM Matrix. Compare controller and mouse-and-keyboard setup details, supported game versions, platform notes, and the membership required for each published release. Always use the compatibility information on the individual configuration page.',
				'image' => 'game-apex-legends.webp',
				'seo_title' => 'Apex Legends Configs for Cronus Zen & XIM Matrix | TimonConfigs',
				'seo_description' => 'Compare Apex Legends configurations, controller and MnK input methods, supported devices, verified compatibility, and current Free or VIP release details.',
			),
			'call-of-duty' => array(
				'name' => 'Call of Duty',
				'description' => 'Follow the planned TimonConfigs Call of Duty collection. Release pages will identify the exact title, game update, supported device, platform, and input method before a configuration becomes available. A collection listing does not imply compatibility with every Call of Duty game or mode.',
				'image' => 'game-call-of-duty.webp',
				'seo_title' => 'Call of Duty Config Collection & Release Updates | TimonConfigs',
				'seo_description' => 'Check Call of Duty collection availability and learn which title, update, device, platform, and input details will accompany verified TimonConfigs releases.',
			),
			'fortnite' => array(
				'name' => 'Fortnite',
				'description' => 'The Fortnite collection is being prepared around clearly documented setups. Each future release must identify its supported game version, input method, device, and platform rather than assume that one profile works across every update. Published configuration cards will appear here when those details are ready.',
				'image' => 'game-fortnite.webp',
				'seo_title' => 'Fortnite Config Collection & Compatibility | TimonConfigs',
				'seo_description' => 'View the Fortnite collection roadmap and the version, device, controller or MnK, and platform details required before a TimonConfigs release is published.',
			),
			'rainbow-six-siege' => array(
				'name' => 'Rainbow Six Siege',
				'description' => 'Track the Rainbow Six Siege collection and its release status. Device setup, input method, game update, and platform compatibility will be documented individually for each published configuration. The collection remains a roadmap until a verified release is available to browse.',
				'image' => 'game-rainbow-six-siege.webp',
				'seo_title' => 'Rainbow Six Siege Config Collection | TimonConfigs',
				'seo_description' => 'Follow Rainbow Six Siege configuration availability with transparent device, input, supported update, and platform information for each future release.',
			),
			'rust' => array(
				'name' => 'Rust',
				'description' => 'The Rust collection provides a place for future TimonConfigs releases with explicit compatibility notes. Rust editions, platforms, and updates can differ, so each configuration must name its supported setup before publication. Check this collection for confirmed release information instead of assuming cross-platform support.',
				'image' => 'game-rust.webp',
				'seo_title' => 'Rust Config Collection & Supported Setups | TimonConfigs',
				'seo_description' => 'Check planned Rust configurations and edition-specific compatibility details. Published entries will identify their platform, device, input, and supported update.',
			),
			'ea-sports-fc' => array(
				'name' => 'EA Sports FC',
				'description' => 'Browse the EA Sports FC collection roadmap. Annual editions and game updates require their own setup information, so future configuration pages will identify the exact edition, platform, device, and supported controls. No profile is offered as available until a corresponding release has been published.',
				'image' => 'game-ea-sports-fc.webp',
				'seo_title' => 'EA Sports FC Config Collection & Editions | TimonConfigs',
				'seo_description' => 'Follow the EA Sports FC configuration roadmap with clear edition, platform, device, and controller setup information for confirmed future releases.',
			),
			'nba-2k' => array(
				'name' => 'NBA 2K',
				'description' => 'The NBA 2K collection is reserved for documented TimonConfigs releases. Each published profile will state its supported annual edition, game update, platform, and device so members can compare the correct setup. Until a release is confirmed, this page communicates the collection roadmap rather than download availability.',
				'image' => 'game-nba-2k.webp',
				'seo_title' => 'NBA 2K Config Collection & Release Status | TimonConfigs',
				'seo_description' => 'Check NBA 2K collection status and edition-specific platform, input, device, and update notes before choosing a future TimonConfigs release.',
			),
		);
	}

	public static function register_landing_rewrites(): void {
		foreach ( array_keys( self::game_definitions() ) as $slug ) {
			add_rewrite_rule( '^' . $slug . '-configs/page/([0-9]{1,})/?$', 'index.php?tc_game=' . $slug . '&paged=$matches[1]', 'top' );
			add_rewrite_rule( '^' . $slug . '-configs/?$', 'index.php?tc_game=' . $slug, 'top' );
		}
		foreach ( array( 'cronus-zen', 'xim-matrix' ) as $slug ) {
			add_rewrite_rule( '^' . $slug . '-configs/page/([0-9]{1,})/?$', 'index.php?tc_device=' . $slug . '&paged=$matches[1]', 'top' );
			add_rewrite_rule( '^' . $slug . '-configs/?$', 'index.php?tc_device=' . $slug, 'top' );
		}
	}

	public static function filter_core_term_links( string $url, WP_Term $term, string $taxonomy ): string {
		if ( 'tc_game' === $taxonomy && isset( self::game_definitions()[ $term->slug ] ) ) {
			return home_url( '/' . $term->slug . '-configs/' );
		}
		if ( 'tc_device' === $taxonomy && in_array( $term->slug, array( 'cronus-zen', 'xim-matrix' ), true ) ) {
			return home_url( '/' . $term->slug . '-configs/' );
		}
		return $url;
	}

	/** A plugin update does not rerun activation: refresh only the route-cache schema. */
	public static function refresh_routes(): void {
		if ( wp_installing() || '2026-10-24-archive-v1' === get_option( 'timonconfigs_catalog_routes_version' ) ) { return; }
		flush_rewrite_rules( false );
		update_option( 'timonconfigs_catalog_routes_version', '2026-10-24-archive-v1', false );
	}

	public static function register_content_types(): void {
		register_post_type( self::POST_TYPE, array(
			'labels' => array( 'name' => __( 'Configs', 'timonconfigs-core' ), 'singular_name' => __( 'Config', 'timonconfigs-core' ), 'add_new_item' => __( 'Add new config', 'timonconfigs-core' ), 'edit_item' => __( 'Edit config', 'timonconfigs-core' ) ),
			'public' => true, 'show_in_rest' => true, 'has_archive' => 'configs',
			'capability_type' => array( 'tc_config', 'tc_configs' ), 'map_meta_cap' => true,
			'rewrite' => array( 'slug' => 'config', 'with_front' => false ), 'menu_icon' => 'dashicons-admin-generic',
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
		) );
		foreach ( array( 'tc_game' => array( 'Games', 'Game', 'games' ), 'tc_device' => array( 'Devices', 'Device', 'devices' ), 'tc_platform' => array( 'Platforms', 'Platform', 'platforms' ) ) as $taxonomy => $labels ) {
			register_taxonomy( $taxonomy, self::POST_TYPE, array(
				'labels' => array( 'name' => $labels[0], 'singular_name' => $labels[1] ),
				'public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'show_admin_column' => true,
				'rewrite' => array( 'slug' => $labels[2], 'with_front' => false ),
			) );
		}
		$enums = array( 'tc_tier' => array( 'free', 'monthly', 'lifetime' ), 'tc_input_method' => array( 'controller', 'mnk' ), 'tc_release_status' => array( 'available', 'coming-soon', 'archived' ) );
		foreach ( self::META as $key => $method ) {
			$rest = array( 'schema' => array( 'type' => 'string' ) );
			if ( isset( $enums[ $key ] ) ) {
				$rest['schema']['enum'] = array_merge( array( '' ), $enums[ $key ] );
			}
			register_post_meta( self::POST_TYPE, $key, array(
				'type' => 'string', 'single' => true, 'show_in_rest' => 'tc_access_url' === $key ? false : $rest,
				'sanitize_callback' => array( __CLASS__, $method ),
				'auth_callback' => static fn( bool $allowed, string $meta_key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
			) );
		}
		foreach ( array( 'tc_game', 'tc_device' ) as $taxonomy ) {
			register_term_meta( $taxonomy, 'tc_collection_image', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_public_url' ), 'auth_callback' => static fn(): bool => current_user_can( 'manage_categories' ) ) );
		}
	}

	/** Add missing content types without replacing an owner's existing descriptions. */
	public static function seed_core_terms(): void {
		foreach ( self::game_definitions() as $slug => $game ) {
			$existing = get_term_by( 'slug', $slug, 'tc_game' );
			if ( ! $existing ) {
				wp_insert_term( $game['name'], 'tc_game', array( 'slug' => $slug, 'description' => $game['description'] ) );
			} elseif ( '' === trim( $existing->description ) ) {
				wp_update_term( $existing->term_id, 'tc_game', array( 'description' => $game['description'] ) );
			}
		}
		$devices = array(
			'cronus-zen' => array( 'Cronus Zen', 'Browse Cronus Zen configurations with individually documented input methods, platform support, game versions, setup instructions, and current access requirements. Compatibility is stated on each release; a device collection does not imply universal support for every game or platform.' ),
			'xim-matrix' => array( 'XIM Matrix', 'Compare XIM Matrix controller and mouse-and-keyboard configurations using the release-specific input, platform, game version, and compatibility notes. Check the individual setup guide and access requirements before choosing a configuration for your device.' ),
		);
		foreach ( $devices as $slug => $device ) {
			$existing = get_term_by( 'slug', $slug, 'tc_device' );
			if ( ! $existing ) {
				wp_insert_term( $device[0], 'tc_device', array( 'slug' => $slug, 'description' => $device[1] ) );
			} elseif ( '' === trim( $existing->description ) ) {
				wp_update_term( $existing->term_id, 'tc_device', array( 'description' => $device[1] ) );
			}
		}
		foreach ( array( 'xbox' => 'Xbox', 'playstation' => 'PlayStation', 'pc' => 'PC' ) as $slug => $name ) {
			if ( ! get_term_by( 'slug', $slug, 'tc_platform' ) ) {
				wp_insert_term( $name, 'tc_platform', array( 'slug' => $slug ) );
			}
		}
	}

	/** Explicit installation scaffolding, always drafts with no invented files or compatibility. */
	public static function seed_flagship_drafts(): void {
		$drafts = array(
			'apex-legends-cronus-zen-s30' => array( 'Apex Legends — Cronus Zen S30', 'cronus-zen', 'controller' ),
			'apex-legends-xim-matrix-controller' => array( 'Apex Legends — XIM Matrix Controller', 'xim-matrix', 'controller' ),
			'apex-legends-xim-matrix-mnk' => array( 'Apex Legends — XIM Matrix MnK', 'xim-matrix', 'mnk' ),
		);
		foreach ( $drafts as $slug => $draft ) {
			$args = array( 'post_type' => self::POST_TYPE, 'post_status' => array_values( get_post_stati() ), 'posts_per_page' => 1, 'fields' => 'ids', 'suppress_filters' => true );
			if ( get_posts( array_merge( $args, array( 'name' => $slug ) ) ) || get_posts( array_merge( $args, array( 'meta_key' => '_tc_seeded_flagship', 'meta_value' => $slug ) ) ) ) {
				continue;
			}
			$id = wp_insert_post( array(
				'post_type' => self::POST_TYPE, 'post_status' => 'draft', 'post_title' => $draft[0], 'post_name' => $slug,
				'post_excerpt' => 'Release details, compatibility, and access requirements are being verified before publication.',
				'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Release information</h2><!-- /wp:heading --><!-- wp:paragraph --><p>This configuration is being prepared for publication. Supported platforms, game version, release version, update date, tutorial, access tier, and delivery information must be confirmed before release.</p><!-- /wp:paragraph -->',
				'meta_input' => array( '_tc_seeded_flagship' => $slug, 'tc_input_method' => $draft[2], 'tc_release_status' => 'coming-soon' ),
			), true );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$game = get_term_by( 'slug', 'apex-legends', 'tc_game' );
			$device = get_term_by( 'slug', $draft[1], 'tc_device' );
			if ( $game instanceof WP_Term ) { wp_set_object_terms( $id, array( $game->term_id ), 'tc_game' ); }
			if ( $device instanceof WP_Term ) { wp_set_object_terms( $id, array( $device->term_id ), 'tc_device' ); }
		}
	}

	/** Reusable released-only condition; legacy published entries remain accessible. */
	public static function released_meta_query(): array {
		return array( 'relation' => 'OR', array( 'key' => 'tc_release_status', 'compare' => 'NOT EXISTS' ), array( 'key' => 'tc_release_status', 'value' => array( '', 'available' ), 'compare' => 'IN' ) );
	}

	public static function collection_has_configs( WP_Term $term ): bool {
		if ( ! in_array( $term->taxonomy, array( 'tc_game', 'tc_device', 'tc_platform' ), true ) ) { return false; }
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ) ), 'meta_query' => self::released_meta_query() ) );
		return (bool) $query->posts;
	}

	public static function is_collection_indexable( WP_Term $term ): bool {
		return ! (bool) get_term_meta( $term->term_id, 'tc_noindex', true ) && strlen( trim( wp_strip_all_tags( $term->description ) ) ) >= 120 && self::collection_has_configs( $term );
	}

	public static function collection_image( WP_Term $term ): string {
		$image = self::sanitize_public_url( get_term_meta( $term->term_id, 'tc_collection_image', true ) );
		if ( $image ) { return $image; }
		$games = self::game_definitions();
		if ( 'tc_game' === $term->taxonomy && isset( $games[ $term->slug ] ) ) {
			$setting = 'timonconfigs_game_' . str_replace( '-', '_', $term->slug ) . '_image';
			return (string) get_theme_mod( $setting, get_template_directory_uri() . '/assets/images/' . $games[ $term->slug ]['image'] );
		}
		return get_template_directory_uri() . '/assets/images/hero-reference-wide.webp';
	}

	/** Public download/access links are only returned for published Free releases. */
	public static function public_access_url( int $post_id ): string {
		if ( self::POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) || 'free' !== get_post_meta( $post_id, 'tc_tier', true ) ) { return ''; }
		$status = (string) get_post_meta( $post_id, 'tc_release_status', true );
		if ( ! in_array( $status, array( '', 'available' ), true ) ) { return ''; }
		return self::sanitize_public_url( get_post_meta( $post_id, 'tc_access_url', true ) );
	}

	public static function sanitize_text( mixed $value ): string { return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : ''; }
	public static function sanitize_multiline( mixed $value ): string { return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : ''; }
	public static function sanitize_public_url( mixed $value ): string {
		if ( ! is_scalar( $value ) ) { return ''; }
		$url = esc_url_raw( (string) $value, array( 'https' ) );
		return wp_parse_url( $url, PHP_URL_HOST ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ? $url : '';
	}
	/** Configuration visibility labels; membership plan names and stored tier values stay unchanged. */
	public static function tier_labels(): array { return array( 'free' => 'Free Member', 'monthly' => 'VIP — Monthly & Lifetime', 'lifetime' => 'VIP Lifetime only' ); }

	public static function sanitize_tier( mixed $value ): string { return is_string( $value ) && in_array( $value, array( 'free', 'monthly', 'lifetime' ), true ) ? $value : ''; }
	public static function sanitize_input( mixed $value ): string { return is_string( $value ) && in_array( $value, array( 'controller', 'mnk' ), true ) ? $value : ''; }
	public static function sanitize_release_status( mixed $value ): string { return is_string( $value ) && in_array( $value, array( 'available', 'coming-soon', 'archived' ), true ) ? $value : ''; }
	public static function sanitize_date( mixed $value ): string {
		$value = self::sanitize_text( $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) { return ''; }
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : '';
	}

	/** Safe scalar request helper shared by the catalogue templates. */
	public static function request_value( string $key ): string {
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/** Stable sort keys; raw request values never reach an SQL clause. */
	public static function sort_options(): array {
		return array( 'updated' => 'Recently updated', 'newest' => 'Upload Date', 'title' => 'A–Z', 'downloads' => 'Downloads' );
	}

	public static function selected_sort(): string {
		$value = self::request_value( 'sort' );
		return array_key_exists( $value, self::sort_options() ) ? $value : 'updated';
	}

	/** Logged-in catalogue CTAs contain per-session nonces and must never be cached publicly. */
	public static function private_member_cache(): void {
		if ( ! is_user_logged_in() || ( ! is_singular( self::POST_TYPE ) && ! is_post_type_archive( self::POST_TYPE ) && ! is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) ) { return; }
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
		if ( ! headers_sent() ) { header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' ); }
	}

	/**
	 * Public-safe access presentation only. The download handler independently checks access again.
	 *
	 * @return array{action:string,label:string,url:string,message:string,files:array}
	 */
	public static function download_cta( int $post_id ): array {
		$details = array( 'action' => 'pending', 'label' => 'View config details', 'url' => (string) get_permalink( $post_id ), 'message' => 'Release files are not available yet.', 'files' => array() );
		if ( self::POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) || 'available' !== get_post_meta( $post_id, 'tc_release_status', true ) ) { return $details; }
		$files = is_callable( array( 'TC_Private_Files', 'files' ) ) ? TC_Private_Files::files( $post_id ) : array();
		$files = is_array( $files ) ? array_values( array_filter( $files, static fn( mixed $file ): bool => is_array( $file ) && isset( $file['id'], $file['name'] ) && is_string( $file['id'] ) && is_string( $file['name'] ) ) ) : array();
		$files = array_map( static fn( array $file ): array => array_intersect_key( $file, array_flip( array( 'id', 'name', 'size', 'version', 'notes', 'checksum', 'uploaded_at' ) ) ), $files );
		if ( has_term( 'xim-matrix', 'tc_device', $post_id ) ) {
			// Matrix cards must return importable TXT code, never silently rename a binary XIMMR backup.
			$txt = array_values( array_filter( $files, static fn( array $file ): bool => 'txt' === strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) );
			if ( $txt ) {
				$files = $txt;
			} elseif ( defined( 'TC_Downloads::MATRIX_TEXT_ID' ) && trim( (string) get_post_meta( $post_id, '_tc_values_content', true ) ) !== '' ) {
				// The actual Values content stays server-side and is checked again for role, session and visibility.
				$files = array( array( 'id' => TC_Downloads::MATRIX_TEXT_ID, 'name' => 'XIM Matrix code.txt' ) );
			} else {
				$details['message'] = 'XIM Matrix text code has not been uploaded. Add a private TXT file or enter Config Values.';
				return $details;
			}
		}
		if ( ! $files ) { return $details; }
		$details['files'] = $files;
		$details['url'] = home_url( '/account/' );
		if ( ! is_user_logged_in() ) {
			$details['action'] = 'login';
			$details['label'] = 'Log in';
			$details['message'] = 'Sign in with Discord to verify your current access.';
			if ( is_callable( array( 'TC_Discord', 'login_url' ) ) ) { $details['url'] = TC_Discord::login_url(); }
			return $details;
		}
		$details['action'] = 'verify';
		$details['label'] = 'Review member access';
		$details['message'] = 'Member verification is not available yet.';
		if ( ! is_callable( array( 'TC_Discord', 'status' ) ) || ! is_callable( array( 'TC_Discord', 'can_access' ) ) ) { return $details; }
		$allowed = TC_Discord::can_access( $post_id );
		$status = TC_Discord::status();
		$state = is_array( $status ) && isset( $status['state'] ) && is_string( $status['state'] ) ? $status['state'] : 'unavailable';
		if ( true === $allowed && defined( 'TC_Downloads::MATRIX_TEXT_ID' ) && $files[0]['id'] === TC_Downloads::MATRIX_TEXT_ID ) {
			$values = class_exists( 'TC_Config_Content' ) ? TC_Config_Content::read( $post_id, 'values' ) : new WP_Error( 'tc_values_unavailable', 'Missing values' );
			if ( is_wp_error( $values ) ) {
				$details['message'] = 'This Matrix code is unavailable for your current membership. Ask the publisher to review Values access.';
				return $details;
			}
		}
		if ( true === $allowed && is_callable( array( 'TC_Downloads', 'render_button' ) ) ) {
			$details['action'] = 'download';
			$details['label'] = 'Download';
			$details['url'] = '';
			$details['message'] = 'Access is verified again when you request a protected file.';
		} elseif ( 'unavailable' === $state ) {
			$details['message'] = 'Your current membership could not be verified. Open your profile and try again.';
		} elseif ( 'unverified' === $state ) {
			$details['label'] = 'Verify with Discord';
			$details['message'] = 'Verify your Discord membership before downloading protected files.';
		} elseif ( in_array( $state, array( 'free', 'monthly', 'lifetime' ), true ) ) {
			$details['action'] = 'memberships';
			$details['label'] = 'Browse Memberships';
			$details['url'] = home_url( '/pricing/' );
			$details['message'] = 'Compare memberships for access covered by this release.';
		}
		return $details;
	}

	/** Explain why a published config does or does not appear in the public collections. */
	public static function admin_columns( array $columns ): array {
		$columns['tc_release'] = __( 'Release', 'timonconfigs-core' );
		$columns['tc_catalog_visibility'] = __( 'Catalog visibility', 'timonconfigs-core' );
		return $columns;
	}

	public static function admin_column( string $column, int $post_id ): void {
		if ( 'tc_release' !== $column && 'tc_catalog_visibility' !== $column ) { return; }
		$release = (string) get_post_meta( $post_id, 'tc_release_status', true );
		if ( 'tc_release' === $column ) {
			echo esc_html( array( 'available' => 'Available', 'coming-soon' => 'Coming soon', 'archived' => 'Archived' )[ $release ] ?? 'Legacy / unspecified' );
			return;
		}
		$status = get_post_status( $post_id );
		if ( 'publish' !== $status ) {
			echo esc_html__( 'Not published — hidden', 'timonconfigs-core' );
		} elseif ( 'archived' === $release ) {
			echo esc_html__( 'Archived — hidden', 'timonconfigs-core' );
		} elseif ( 'coming-soon' === $release ) {
			echo esc_html__( 'Visible preview — downloads disabled', 'timonconfigs-core' );
		} else {
			echo esc_html__( 'Visible in all configs', 'timonconfigs-core' );
		}
		if ( 'publish' === $status && 'archived' !== $release ) {
			$games = get_the_terms( $post_id, 'tc_game' );
			$devices = get_the_terms( $post_id, 'tc_device' );
			if ( ! $games || is_wp_error( $games ) || ! $devices || is_wp_error( $devices ) ) {
				echo '<br><em>' . esc_html__( 'Assign a Game and Device for filtered collections.', 'timonconfigs-core' ) . '</em>';
			}
		}
	}

	public static function add_meta_box(): void {
		add_meta_box( 'tc_config_details', esc_html__( 'Config details', 'timonconfigs-core' ), array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'tc_save_config_details', self::NONCE );
		$values = array();
		foreach ( self::META as $key => $method ) { $values[ $key ] = (string) get_post_meta( $post->ID, $key, true ); }
		$selects = array(
			'tc_tier' => array( 'Access tier', array( '' => 'Select verified access requirement' ) + self::tier_labels() ),
			'tc_input_method' => array( 'Input method', array( '' => 'Not yet verified', 'controller' => 'Controller', 'mnk' => 'Mouse & keyboard' ) ),
			'tc_release_status' => array( 'Release status', array( 'coming-soon' => 'Coming soon / details pending', 'available' => 'Available (verified release)', 'archived' => 'Archived' ) ),
		);
		foreach ( $selects as $key => $field ) {
			$value = $values[ $key ];
			if ( 'tc_release_status' === $key && '' === $value ) { $value = 'publish' === $post->post_status ? 'available' : 'coming-soon'; }
			echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $field[0] ) . '</strong></label><br><select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
			foreach ( $field[1] as $option => $label ) { echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $label ) . '</option>'; }
			echo '</select></p>';
		}
		foreach ( array( 'tc_publisher' => 'Public publisher name', 'tc_creator' => 'Public creator name (optional)', 'tc_release_date' => 'Release date', 'tc_config_version' => 'Configuration version', 'tc_game_version' => 'Supported game version', 'tc_last_updated' => 'Last verified update', 'tc_tutorial_url' => 'Tutorial URL (HTTPS)', 'tc_access_url' => 'Legacy public Free resource URL (not protected delivery)', 'tc_platform' => 'Legacy platform notes' ) as $key => $label ) {
			$type = in_array( $key, array( 'tc_last_updated', 'tc_release_date' ), true ) ? 'date' : ( str_ends_with( $key, '_url' ) ? 'url' : 'text' );
			echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><input class="widefat" type="' . esc_attr( $type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $values[ $key ] ) . '"></p>';
		}
		foreach ( array( 'tc_compatibility' => 'Confirmed compatibility and setup requirements', 'tc_changelog' => 'Changelog' ) as $key => $label ) {
			echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><textarea class="widefat" rows="4" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $values[ $key ] ) . '</textarea></p>';
		}
		echo '<p>' . esc_html__( 'Assign only confirmed Xbox, PlayStation or PC terms in Platforms. Public publisher/creator names appear on release cards. Upload protected scripts through Private script files; the legacy public Free resource URL is not a protected file and is not used for member-library downloads. It is omitted from REST output and cleared for premium tiers.', 'timonconfigs-core' ) . '</p>';
	}

	public static function save_meta( int $post_id ): void {
		if ( self::POST_TYPE !== get_post_type( $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$nonce = isset( $_POST[ self::NONCE ] ) && is_string( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'tc_save_config_details' ) ) { return; }
		foreach ( self::META as $key => $method ) {
			if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) { continue; }
			$raw = wp_unslash( $_POST[ $key ] );
			$value = self::$method( $raw );
			if ( in_array( $key, array( 'tc_tier', 'tc_input_method', 'tc_release_status' ), true ) && '' !== $raw && '' === $value ) { continue; }
			update_post_meta( $post_id, $key, $value );
		}
		if ( 'free' !== get_post_meta( $post_id, 'tc_tier', true ) ) { delete_post_meta( $post_id, 'tc_access_url' ); }
	}

	public static function render_collection_add_field(): void {
		wp_nonce_field( 'tc_collection_image', 'tc_collection_image_nonce' );
		echo '<div class="form-field"><label for="tc_collection_image">' . esc_html__( 'Collection image URL', 'timonconfigs-core' ) . '</label><input type="url" name="tc_collection_image" id="tc_collection_image" placeholder="https://…"><p>' . esc_html__( 'Choose a public cover image from Media Library and paste its HTTPS URL. Premium files must never be used here.', 'timonconfigs-core' ) . '</p></div>';
	}

	public static function render_collection_edit_field( WP_Term $term ): void {
		wp_nonce_field( 'tc_collection_image', 'tc_collection_image_nonce' );
		echo '<tr class="form-field"><th><label for="tc_collection_image">' . esc_html__( 'Collection image URL', 'timonconfigs-core' ) . '</label></th><td><input type="url" class="regular-text" name="tc_collection_image" id="tc_collection_image" value="' . esc_attr( get_term_meta( $term->term_id, 'tc_collection_image', true ) ) . '"><p class="description">' . esc_html__( 'Leave empty to use the configurable theme cover. Use public artwork only.', 'timonconfigs-core' ) . '</p></td></tr>';
	}

	public static function save_collection_image( int $term_id ): void {
		$nonce = isset( $_POST['tc_collection_image_nonce'] ) && is_string( $_POST['tc_collection_image_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tc_collection_image_nonce'] ) ) : '';
		if ( ! current_user_can( 'edit_term', $term_id ) || ! wp_verify_nonce( $nonce, 'tc_collection_image' ) || ! isset( $_POST['tc_collection_image'] ) || ! is_scalar( $_POST['tc_collection_image'] ) ) { return; }
		update_term_meta( $term_id, 'tc_collection_image', self::sanitize_public_url( wp_unslash( $_POST['tc_collection_image'] ) ) );
	}

	/**
	 * Public catalogue includes published Coming soon cards, but never archived releases.
	 * This is a visibility check, NOT a download entitlement check. The protected
	 * delivery path still requires tc_release_status=available and a verified role.
	 */
	public static function catalog_visible_meta_query(): array {
		return array(
			'relation' => 'OR',
			array( 'key' => 'tc_release_status', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'tc_release_status', 'value' => array( '', 'available', 'coming-soon' ), 'compare' => 'IN' ),
		);
	}

	/** A left join retains new releases with no recorded deliveries; option names are unique. */
	public static function download_order_clauses( array $clauses, WP_Query $query ): array {
		if ( ! $query->get( '_tc_download_order' ) || is_admin() || ! $query->is_main_query() || ( ! $query->is_post_type_archive( self::POST_TYPE ) && ! $query->is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) ) { return $clauses; }
		global $wpdb;
		$clauses['join'] .= " LEFT JOIN {$wpdb->options} AS tc_delivery_counts ON tc_delivery_counts.option_name = CONCAT('_tc_delivery_count_', {$wpdb->posts}.ID)";
		$clauses['orderby'] = "CAST(COALESCE(tc_delivery_counts.option_value, '0') AS UNSIGNED) DESC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		return $clauses;
	}

	public static function filter_archive( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ( ! $query->is_post_type_archive( self::POST_TYPE ) && ! $query->is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) ) { return; }
		$query->set( 'post_type', self::POST_TYPE );
		$query->set( 'posts_per_page', 12 );
		$sort = self::selected_sort();
		$column = array( 'newest' => 'date', 'updated' => 'modified', 'title' => 'title', 'downloads' => 'date' )[ $sort ];
		$query->set( '_tc_download_order', 'downloads' === $sort );
		$direction = 'title' === $sort ? 'ASC' : 'DESC';
		$query->set( 'orderby', array( $column => $direction, 'ID' => $direction ) );
		$query->set( 'order', $direction );
		$search = self::request_value( 'config_search' );
		if ( '' !== $search ) { $query->set( 's', $search ); }
		$tax_query = (array) $query->get( 'tax_query' );
		foreach ( array( 'tc_game' => 'game', 'tc_device' => 'device', 'tc_platform' => 'platform' ) as $taxonomy => $parameter ) {
			$slug = sanitize_title( self::request_value( $parameter ) );
			if ( '' !== $slug ) { $tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug ); }
		}
		if ( $tax_query ) { $tax_query['relation'] = 'AND'; $query->set( 'tax_query', $tax_query ); }
		$meta_query = array( 'relation' => 'AND', self::catalog_visible_meta_query() );
		$existing_meta = $query->get( 'meta_query' );
		if ( is_array( $existing_meta ) && $existing_meta ) { $meta_query[] = $existing_meta; }
		$tier = self::sanitize_tier( self::request_value( 'tier' ) );
		$input = self::sanitize_input( self::request_value( 'input' ) );
		if ( '' !== $tier ) { $meta_query[] = array( 'key' => 'tc_tier', 'value' => $tier ); }
		if ( '' !== $input ) { $meta_query[] = array( 'key' => 'tc_input_method', 'value' => $input ); }
		$query->set( 'meta_query', $meta_query );
	}
}
