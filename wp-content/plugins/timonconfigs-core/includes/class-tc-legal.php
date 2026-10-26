<?php
/** Explicit publication of identified seller policies; never changes pages on a public request. */
defined( 'ABSPATH' ) || exit;

final class TC_Legal {
	private const OPTION = 'timonconfigs_legal';
	private const MANIFEST = 'timonconfigs_legal_published';
	private const NONCE = 'tc_legal_publish';
	private const VERSION = '2026-10-26';

	public static function init(): void {
		add_action( 'admin_menu', static function (): void { add_options_page( 'Legal documents', 'Legal documents', 'manage_options', 'tc-legal', array( __CLASS__, 'admin' ) ); } );
		add_action( 'admin_post_tc_legal_publish', array( __CLASS__, 'handle' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy' ), 1 );
	}
	public static function pages(): array { return array( 'terms-of-service' => 'Terms of Service', 'privacy-policy' => 'Privacy Policy', 'refund-policy' => 'Refund & Cancellation Policy' ); }
	public static function version(): string { return self::VERSION; }
	public static function retired_slugs(): array { return array( 'cookie-policy' => 'privacy-policy/#cookies', 'lifetime-license-terms' => 'terms-of-service/#lifetime-license' ); }
	public static function profile(): array {
		$defaults = apply_filters( 'timonconfigs_legal_defaults', array() ); $value = get_option( self::OPTION, array() );
		return array_merge( is_array( $defaults ) ? $defaults : array(), is_array( $value ) ? $value : array() );
	}
	private static function error( string $code, string $message ): WP_Error { return new WP_Error( 'tc_legal_' . $code, $message ); }

	/** Seller information is supplied by the owner, never inferred from a brand or payment key. */
	public static function validate_profile( mixed $value ): array|WP_Error {
		$limits = array( 'seller_name' => 240, 'seller_address' => 1600, 'seller_email' => 254, 'seller_registration' => 1800, 'seller_phone' => 100 );
		if ( ! is_array( $value ) || array_diff( array_keys( $value ), array_merge( array_keys( $limits ), array( 'prices_confirmed' ) ) ) ) { return self::error( 'profile', 'Provide the seller’s public identification and contact information.' ); }
		$out = array();
		foreach ( $limits as $key => $limit ) {
			$raw = $value[ $key ] ?? '';
			if ( ! is_string( $raw ) || strlen( $raw ) > $limit || str_contains( $raw, '{{' ) || str_contains( $raw, '}}' ) ) { return self::error( 'profile', 'Check the seller information fields and remove template markers.' ); }
			$out[ $key ] = trim( in_array( $key, array( 'seller_address', 'seller_registration' ), true ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw ) );
			if ( in_array( $key, array( 'seller_name', 'seller_registration' ), true ) && '' === $out[ $key ] ) { return self::error( 'identity', 'The seller’s actual name and business information are required before publication.' ); }
		}
		if ( '' !== $out['seller_email'] && ! is_email( $out['seller_email'] ) ) { return self::error( 'email', 'Provide a valid seller contact email or leave it empty until supplied.' ); }
		$out['prices_confirmed'] = in_array( $value['prices_confirmed'] ?? null, array( true, 1, '1' ), true );
		if ( ! $out['prices_confirmed'] ) { return self::error( 'prices', 'Confirm that €29.99 monthly and €80 once are the final customer totals, including applicable tax.' ); }
		return $out;
	}
	public static function ready(): bool { return ! is_wp_error( self::validate_profile( self::profile() ) ); }
	private static function contact_html( array $profile ): string {
		return '' !== $profile['seller_email'] ? '<a href="' . esc_url( 'mailto:' . $profile['seller_email'] ) . '">' . esc_html( $profile['seller_email'] ) . '</a>' : '<a href="' . esc_url( home_url( '/contact/' ) ) . '">Contact TimonConfigs</a>';
	}
	private static function identity_html( array $profile ): string {
		$html = '<p>TimonConfigs is operated by <strong>' . esc_html( $profile['seller_name'] ) . '</strong>. Business information: ' . esc_html( $profile['seller_registration'] ) . '.';
		if ( '' !== $profile['seller_address'] ) { $html .= ' Correspondence and complaints address: ' . esc_html( $profile['seller_address'] ) . '.'; }
		$html .= ' Contact: ' . self::contact_html( $profile ) . '.';
		if ( '' !== $profile['seller_phone'] ) { $html .= ' Contact telephone: ' . esc_html( $profile['seller_phone'] ) . '.'; }
		return $html . '</p>';
	}

	/** Convert the controlled top-level legal HTML into individually editable native blocks. */
	private static function blocks( string $html ): string|WP_Error {
		$matched = preg_match_all( '~<(p|h2|h3|ul|ol|table|blockquote)\b[^>]*>.*?</\1>~si', $html, $matches, PREG_OFFSET_CAPTURE );
		if ( ! $matched ) { return self::error( 'template', 'The legal document template could not be read.' ); }
		$offset = 0; $blocks = array();
		foreach ( $matches[0] as $index => $match ) {
			if ( '' !== trim( substr( $html, $offset, $match[1] - $offset ) ) ) { return self::error( 'template', 'The legal document contains unsupported top-level markup.' ); }
			$part = $match[0]; $tag = strtolower( $matches[1][ $index ][0] ); $name = 'paragraph'; $attributes = '';
			if ( in_array( $tag, array( 'h2', 'h3' ), true ) ) {
				$name = 'heading'; $args = array( 'level' => (int) substr( $tag, 1 ) );
				if ( preg_match( '/\bid="([a-z0-9-]+)"/', $part, $anchor ) ) { $args['anchor'] = $anchor[1]; }
				$attributes = ' ' . wp_json_encode( $args ); $part = preg_replace( '/^<' . $tag . '\b/', '<' . $tag . ' class="wp-block-heading"', $part, 1 );
			} elseif ( in_array( $tag, array( 'ul', 'ol' ), true ) ) {
				$name = 'list'; $attributes = 'ol' === $tag ? ' {"ordered":true}' : '';
				$part = preg_replace( '/^<' . $tag . '\b/', '<' . $tag . ' class="wp-block-list"', $part, 1 );
				$part = preg_replace( '~(<li\b[^>]*>.*?</li>)~s', '<!-- wp:list-item -->$1<!-- /wp:list-item -->', $part );
			} elseif ( 'table' === $tag ) { $name = 'table'; $attributes = ' {"hasFixedLayout":false}'; $part = '<figure class="wp-block-table">' . $part . '</figure>'; }
			elseif ( 'blockquote' === $tag ) {
				$name = 'quote'; $part = preg_replace( '/^<blockquote\b/', '<blockquote class="wp-block-quote"', $part, 1 );
				$part = preg_replace( '~(<p\b[^>]*>.*?</p>)~s', '<!-- wp:paragraph -->$1<!-- /wp:paragraph -->', $part );
			}
			$blocks[] = '<!-- wp:' . $name . $attributes . ' -->' . "\n" . $part . "\n" . '<!-- /wp:' . $name . ' -->';
			$offset = $match[1] + strlen( $match[0] );
		}
		if ( '' !== trim( substr( $html, $offset ) ) ) { return self::error( 'template', 'The legal document contains unsupported trailing markup.' ); }
		return implode( "\n\n", $blocks );
	}
	public static function document( string $slug, ?array $profile = null ): string|WP_Error {
		if ( ! isset( self::pages()[ $slug ] ) ) { return self::error( 'document', 'Choose a supported legal document.' ); }
		$stored = null === $profile; $profile = self::validate_profile( $profile ?? self::profile() ); if ( is_wp_error( $profile ) ) { return $profile; }
		$path = dirname( __DIR__ ) . '/resources/legal/' . $slug . '.html';
		if ( ! is_readable( $path ) ) { return self::error( 'template', 'A required legal document template is missing.' ); }
		$html = file_get_contents( $path );
		if ( ! is_string( $html ) || '' === trim( $html ) || strlen( $html ) > 120000 ) { return self::error( 'template', 'A legal document template is empty or unreadable.' ); }
		$manifest = get_option( self::MANIFEST, array() );
		$date = $stored && is_array( $manifest ) && is_string( $manifest['date'] ?? null ) ? $manifest['date'] : ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'F j, Y' );
		$uses_identity = str_contains( $html, '{{seller_identity}}' );
		$replace = array( '{{effective_date}}' => esc_html( $date ), '{{seller_identity}}' => self::identity_html( $profile ), '{{seller_contact}}' => self::contact_html( $profile ) );
		foreach ( array( 'seller_name', 'seller_address', 'seller_email', 'seller_registration' ) as $key ) { $replace[ '{{' . $key . '}}' ] = esc_html( $profile[ $key ] ); }
		$html = strtr( $html, $replace );
		if ( str_contains( $html, '{{' ) || str_contains( $html, '}}' ) ) { return self::error( 'template', 'A required legal document value is missing.' ); }
		if ( ! $uses_identity && '' !== $profile['seller_phone'] ) { $html = preg_replace_callback( '~</p>~', static fn(): string => '</p>' . "\n" . '<p>Seller contact telephone: ' . esc_html( $profile['seller_phone'] ) . '.</p>', $html, 1 ); }
		$html = preg_replace_callback( '~href="/(terms-of-service|privacy-policy|refund-policy|contact|account)/([^"<>]*)"~', static fn( array $match ): string => 'href="' . esc_url( home_url( '/' . $match[1] . '/' ) . $match[2] ) . '"', $html );
		return self::blocks( wp_kses_post( $html ) );
	}
	private static function secure_transport(): bool { return in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) || ( is_ssl() && 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ); }

	/** Archive originals before the first page write, including retired menu references. */
	private static function archive(): string|WP_Error {
		$snapshot = array( 'at' => gmdate( 'c' ), 'by' => get_current_user_id(), 'profile' => self::profile(), 'manifest' => get_option( self::MANIFEST, array() ), 'pages' => array(), 'menus' => array(), 'locations' => get_nav_menu_locations() ); $retired_ids = array();
		foreach ( array_merge( array_keys( self::pages() ), array_keys( self::retired_slugs() ) ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof WP_Post ) { $snapshot['pages'][ $slug ] = array( 'post' => $page->to_array(), 'meta' => get_post_meta( $page->ID ) ); if ( isset( self::retired_slugs()[ $slug ] ) ) { $retired_ids[] = $page->ID; } }
		}
		if ( $retired_ids ) {
			$items = get_posts( array( 'post_type' => 'nav_menu_item', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_menu_item_object_id', 'value' => $retired_ids, 'compare' => 'IN', 'type' => 'NUMERIC' ), array( 'key' => '_menu_item_type', 'value' => 'post_type' ), array( 'key' => '_menu_item_object', 'value' => 'page' ) ) ) );
			foreach ( $items as $item ) { $snapshot['menus'][ $item->ID ] = array( 'post' => $item->to_array(), 'meta' => get_post_meta( $item->ID ), 'menus' => wp_get_object_terms( $item->ID, 'nav_menu', array( 'fields' => 'ids' ) ) ); }
		}
		$footer = absint( $snapshot['locations']['footer'] ?? 0 );
		if ( $footer ) {
			foreach ( wp_get_nav_menu_items( $footer ) ?: array() as $item ) {
				$post = get_post( $item->ID ); if ( ! $post instanceof WP_Post ) { return self::error( 'archive', 'A footer link could not be archived. Publication was not started.' ); }
				$snapshot['menus'][ $item->ID ] = array( 'post' => $post->to_array(), 'meta' => get_post_meta( $item->ID ), 'menus' => array( $footer ) );
			}
		}
		$key = '_tc_legal_archive_' . wp_generate_uuid4();
		return add_option( $key, $snapshot, '', false ) ? $key : self::error( 'archive', 'The existing legal documents could not be archived. Publication was not started.' );
	}
	/** Preserve authored footer links and add only missing canonical policy entries. */
	private static function footer_links( array $ids ): bool|WP_Error {
		$locations = get_nav_menu_locations(); $footer = absint( $locations['footer'] ?? 0 );
		if ( ! $footer ) {
			$menu = wp_get_nav_menu_object( 'TimonConfigs Footer' ); $footer = $menu ? (int) $menu->term_id : wp_create_nav_menu( 'TimonConfigs Footer' );
			if ( is_wp_error( $footer ) || ! $footer ) { return self::error( 'menu', 'The footer menu could not be prepared. Originals are archived; retry publication.' ); }
			$locations['footer'] = (int) $footer; set_theme_mod( 'nav_menu_locations', $locations );
		}
		$present = array(); foreach ( wp_get_nav_menu_items( $footer ) ?: array() as $item ) { if ( 'post_type' === $item->type && 'page' === $item->object ) { $present[] = (int) $item->object_id; } }
		foreach ( self::pages() as $slug => $title ) {
			if ( in_array( $ids[ $slug ], $present, true ) ) { continue; }
			$item = wp_update_nav_menu_item( $footer, 0, array( 'menu-item-title' => $title, 'menu-item-object' => 'page', 'menu-item-object-id' => $ids[ $slug ], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
			if ( is_wp_error( $item ) || ! $item ) { return self::error( 'menu', 'A required policy link could not be added. Originals are archived; retry publication.' ); }
		}
		return true;
	}
	public static function publish( array $profile ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) { return self::error( 'forbidden', 'Administrator permission is required.' ); }
		$profile = self::validate_profile( $profile ); if ( is_wp_error( $profile ) ) { return $profile; }
		$documents = array(); foreach ( self::pages() as $slug => $title ) { $documents[ $slug ] = self::document( $slug, $profile ); if ( is_wp_error( $documents[ $slug ] ) ) { return $documents[ $slug ]; } }
		foreach ( self::retired_slugs() as $slug => $target ) { if ( defined( 'EMPTY_TRASH_DAYS' ) && 0 === (int) EMPTY_TRASH_DAYS && get_page_by_path( $slug, OBJECT, 'page' ) instanceof WP_Post ) { return self::error( 'trash_disabled', 'Enable WordPress Trash before retiring existing policy pages, so their original records remain recoverable.' ); } }
		$archive = self::archive(); if ( is_wp_error( $archive ) ) { return $archive; }
		$ids = array(); $hashes = array();
		foreach ( self::pages() as $slug => $title ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			$post = array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $documents[ $slug ] );
			if ( $page instanceof WP_Post ) { $post['ID'] = $page->ID; }
			$id = $page instanceof WP_Post ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
			if ( is_wp_error( $id ) || ! $id || 'publish' !== get_post_status( $id ) || get_post_field( 'post_content', $id, 'raw' ) !== $documents[ $slug ] ) { return self::error( 'publish', 'Not all legal documents could be published. Originals are archived; retry publication before retiring other pages.' ); }
			$ids[ $slug ] = (int) $id; $hashes[ $slug ] = hash( 'sha256', $documents[ $slug ] );
		}
		$menu = self::footer_links( $ids ); if ( is_wp_error( $menu ) ) { return $menu; }
		foreach ( self::retired_slugs() as $slug => $target ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page instanceof WP_Post ) { continue; }
			if ( 'trash' !== $page->post_status && ( ! wp_trash_post( $page->ID ) || 'trash' !== get_post_status( $page->ID ) ) ) { return self::error( 'retire', 'The three policies were published, but a redundant policy could not be retired. Its original remains archived; retry publication.' ); }
			$items = get_posts( array( 'post_type' => 'nav_menu_item', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_menu_item_object_id', 'value' => (string) $page->ID ), array( 'key' => '_menu_item_type', 'value' => 'post_type' ), array( 'key' => '_menu_item_object', 'value' => 'page' ) ) ) );
			foreach ( $items as $item ) { if ( ! wp_delete_post( $item->ID, true ) ) { return self::error( 'menu', 'A retired policy menu link could not be removed. Originals are archived; retry publication.' ); } }
		}
		update_option( 'wp_page_for_privacy_policy', $ids['privacy-policy'] );
		if ( ! update_option( self::OPTION, $profile, false ) && get_option( self::OPTION ) !== $profile ) { return self::error( 'profile_write', 'Published pages could not be linked to the saved seller information. Retry publication.' ); }
		$manifest = array( 'version' => self::VERSION, 'ids' => $ids, 'hashes' => $hashes, 'date' => ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'F j, Y' ), 'archive' => $archive );
		if ( ! update_option( self::MANIFEST, $manifest, false ) && get_option( self::MANIFEST ) !== $manifest ) { return self::error( 'manifest', 'Publication could not be confirmed. Originals are archived; retry publication.' ); }
		return array( 'ids' => $ids, 'archive' => $archive );
	}
	public static function perform( array $data ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) || ! is_string( $data['_wpnonce'] ?? null ) || ! wp_verify_nonce( $data['_wpnonce'], self::NONCE ) ) { return self::error( 'forbidden', 'An authorized administrator session is required.' ); }
		if ( ! self::secure_transport() ) { return self::error( 'https', 'Publish the seller’s documents through HTTPS.' ); }
		$profile = self::validate_profile( $data['profile'] ?? null ); if ( is_wp_error( $profile ) ) { return $profile; }
		return self::publish( $profile );
	}
	public static function published(): bool {
		$manifest = get_option( self::MANIFEST, array() );
		if ( ! self::ready() || ! is_array( $manifest ) || self::VERSION !== ( $manifest['version'] ?? '' ) ) { return false; }
		foreach ( self::pages() as $slug => $title ) { $id = absint( $manifest['ids'][ $slug ] ?? 0 ); if ( ! $id || 'publish' !== get_post_status( $id ) ) { return false; } }
		return true;
	}
	public static function published_version(): string {
		if ( ! self::published() ) { return ''; }
		$manifest = get_option( self::MANIFEST ); $hashes = array();
		foreach ( self::pages() as $slug => $title ) { $hashes[ $slug ] = hash( 'sha256', (string) get_post_field( 'post_content', $manifest['ids'][ $slug ], 'raw' ) ); }
		return self::VERSION . '-' . substr( hash( 'sha256', wp_json_encode( $hashes ) ), 0, 32 );
	}
	public static function redirect_legacy(): void {
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) || ! is_string( $_SERVER['REQUEST_URI'] ?? null ) ) { return; }
		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); if ( ! is_string( $path ) ) { return; }
		foreach ( self::retired_slugs() as $slug => $target ) {
			if ( untrailingslashit( $path ) === untrailingslashit( (string) wp_parse_url( home_url( '/' . $slug . '/' ), PHP_URL_PATH ) ) && self::published() ) { wp_safe_redirect( home_url( '/' . $target ), 301, 'TimonConfigs legal consolidation' ); exit; }
		}
	}
	public static function handle(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'POST required.', '', array( 'response' => 405 ) ); }
		$result = self::perform( wp_unslash( $_POST ) );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator permission required.', '', array( 'response' => 403 ) ); }
		set_transient( 'tc_legal_feedback_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : 'Three legal documents are published. Duplicate policy pages were archived and retired.', 120 );
		wp_safe_redirect( admin_url( 'options-general.php?page=tc-legal' ), 303 ); exit;
	}
	public static function admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$profile = self::profile(); $key = 'tc_legal_feedback_' . get_current_user_id(); $notice = get_transient( $key );
		echo '<div class="wrap"><h1>Legal documents</h1><p>Publish three complete English policies using the seller’s actual public information: Terms of Service (including Lifetime), Privacy Policy (including cookies), and Refund &amp; Cancellation Policy. Existing versions and retired menu links are archived privately. The separate Cookie Policy and Lifetime License Terms pages move to Trash and redirect to their new sections.</p>';
		if ( empty( $profile['seller_address'] ) || empty( $profile['seller_email'] ) ) { echo '<div class="notice notice-warning"><p>A postal address or direct email has not been supplied and will be omitted, with the website Contact page used where needed. Digital-only sales may still require these details. Publishing does not confirm that every legal disclosure obligation has been met.</p></div>'; }
		if ( is_string( $notice ) && '' !== $notice ) { delete_transient( $key ); echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tc_legal_publish">'; wp_nonce_field( self::NONCE );
		$labels = array( 'seller_name' => 'Seller / data controller legal name', 'seller_address' => 'Public correspondence and complaints address, if supplied', 'seller_email' => 'Public contact and complaints email, if supplied', 'seller_registration' => 'Actual business form and any supplied registration / tax details', 'seller_phone' => 'Public contact telephone, if available' );
		echo '<table class="form-table">';
		foreach ( $labels as $name => $label ) {
			echo '<tr><th><label for="tc-legal-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
			$value = is_string( $profile[ $name ] ?? null ) ? $profile[ $name ] : '';
			if ( in_array( $name, array( 'seller_address', 'seller_registration' ), true ) ) { echo '<textarea class="large-text" rows="3" id="tc-legal-' . esc_attr( $name ) . '" name="profile[' . esc_attr( $name ) . ']" ' . ( 'seller_registration' === $name ? 'required' : '' ) . '>' . esc_textarea( $value ) . '</textarea>'; }
			else { echo '<input class="regular-text" type="' . ( 'seller_email' === $name ? 'email' : 'text' ) . '" id="tc-legal-' . esc_attr( $name ) . '" name="profile[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" ' . ( 'seller_name' === $name ? 'required' : '' ) . '>'; }
			echo '</td></tr>';
		}
		echo '</table><p><label><input type="checkbox" name="profile[prices_confirmed]" value="1" required ' . checked( in_array( $profile['prices_confirmed'] ?? null, array( true, 1, '1' ), true ), true, false ) . '> €29.99 monthly and €80 once are the final customer totals, including applicable tax. These documents preserve statutory consumer rights and do not assert a completed withdrawal waiver.</label></p>';
		submit_button( 'Save seller information and publish policies' ); echo '</form>';
		if ( self::published() ) { echo '<h2>Published documents</h2><ul>'; foreach ( self::pages() as $slug => $title ) { echo '<li><a href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">' . esc_html( $title ) . '</a></li>'; } echo '</ul>'; }
		echo '</div>';
	}
}
