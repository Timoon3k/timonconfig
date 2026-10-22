<?php
/** Native, server-rendered storefront sections. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

function timonconfigs_home_sections(): array {
	return array( 'hero' => 'Hero', 'assurance' => 'Trust bar', 'search' => 'Config search', 'games' => 'Game collections', 'pricing' => 'Pricing', 'benefits' => 'Why TimonConfigs', 'reviews' => 'Reviews', 'videos' => 'Apex videos', 'faq' => 'FAQ', 'cta' => 'Final call to action' );
}

function timonconfigs_default_home_content(): string {
	return implode( "\n", array_map( static fn( string $section ): string => '<!-- wp:timonconfigs/section ' . wp_json_encode( array( 'section' => $section ) ) . ' /-->', array_keys( timonconfigs_home_sections() ) ) );
}

/** Shared visibility check for schema: removed sections must not emit invisible content. */
function timonconfigs_home_has_section( string $section ): bool {
	$content = (string) get_post_field( 'post_content', (int) get_option( 'page_on_front' ) );
	if ( '' === trim( $content ) ) {
		return array_key_exists( $section, timonconfigs_home_sections() );
	}
	$find = static function ( array $blocks ) use ( &$find, $section ): bool {
		foreach ( $blocks as $block ) {
			if ( 'timonconfigs/section' === ( $block['blockName'] ?? '' ) && $section === ( $block['attrs']['section'] ?? 'hero' ) ) {
				return true;
			}
			if ( ! empty( $block['innerBlocks'] ) && $find( $block['innerBlocks'] ) ) {
				return true;
			}
		}
		return false;
	};
	return $find( parse_blocks( $content ) );
}

/** Render only whitelisted template parts and sanitize editor attributes at the boundary. */
function timonconfigs_render_section( array $attributes ): string {
	$section = isset( $attributes['section'] ) && is_string( $attributes['section'] ) ? $attributes['section'] : 'hero';
	if ( ! array_key_exists( $section, timonconfigs_home_sections() ) ) {
		return '';
	}
	$settings = array();
	foreach ( array( 'title', 'eyebrow', 'description', 'tagline', 'primaryLabel' ) as $key ) {
		if ( isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) && '' !== trim( $attributes[ $key ] ) ) {
			$settings[ $key ] = sanitize_text_field( $attributes[ $key ] );
		}
	}
	if ( isset( $attributes['image'] ) && is_string( $attributes['image'] ) && $attributes['image'] ) {
		$clean_image = esc_url_raw( $attributes['image'], array( 'https', 'http' ) );
		if ( $clean_image ) { $settings['image'] = $clean_image; }
	}
	if ( isset( $attributes['primaryUrl'] ) && is_string( $attributes['primaryUrl'] ) && $attributes['primaryUrl'] ) {
		$clean_url = esc_url_raw( $attributes['primaryUrl'], array( 'https', 'http' ) );
		if ( $clean_url ) { $settings['primaryUrl'] = $clean_url; }
	}
	if ( 'benefits' === $section && ! empty( $attributes['items'] ) && is_array( $attributes['items'] ) ) {
		$items = array();
		foreach ( array_slice( $attributes['items'], 0, 5 ) as $item ) {
			if ( is_array( $item ) && count( $item ) >= 3 && is_string( $item[0] ) && is_string( $item[1] ) && is_string( $item[2] ) ) {
				$items[] = array( sanitize_key( $item[0] ), sanitize_text_field( $item[1] ), sanitize_text_field( $item[2] ) );
			}
		}
		if ( $items ) {
			$settings['items'] = $items;
		}
	}
	ob_start();
	get_template_part( 'template-parts/home/' . $section, null, array( 'settings' => $settings ) );
	return (string) ob_get_clean();
}

add_action( 'init', static function (): void {
	wp_register_script( 'timonconfigs-blocks', get_template_directory_uri() . '/assets/js/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ), TIMONCONFIGS_THEME_VERSION, true );
	wp_localize_script( 'timonconfigs-blocks', 'tcBlockEditor', array( 'sections' => timonconfigs_home_sections(), 'customizeUrl' => admin_url( 'customize.php?autofocus[section]=timonconfigs_home' ), 'gamesUrl' => admin_url( 'edit-tags.php?taxonomy=tc_game&post_type=tc_config' ), 'videosUrl' => admin_url( 'edit.php?post_type=tc_video' ), 'reviewsUrl' => admin_url( 'edit.php?post_type=tc_review' ), 'faqUrl' => admin_url( 'edit.php?post_type=tc_faq' ) ) );
	register_block_type( 'timonconfigs/section', array(
		'api_version' => 3,
		'editor_script' => 'timonconfigs-blocks',
		'render_callback' => 'timonconfigs_render_section',
		'attributes' => array(
			'section' => array( 'type' => 'string', 'default' => 'hero', 'enum' => array_keys( timonconfigs_home_sections() ) ),
			'title' => array( 'type' => 'string', 'default' => '' ),
			'eyebrow' => array( 'type' => 'string', 'default' => '' ),
			'description' => array( 'type' => 'string', 'default' => '' ),
			'tagline' => array( 'type' => 'string', 'default' => '' ),
			'image' => array( 'type' => 'string', 'default' => '' ),
			'primaryLabel' => array( 'type' => 'string', 'default' => '' ),
			'primaryUrl' => array( 'type' => 'string', 'default' => '' ),
			'items' => array( 'type' => 'array', 'default' => array() ),
		),
		'supports' => array( 'html' => false, 'anchor' => false ),
	) );
	register_block_pattern( 'timonconfigs/storefront', array( 'title' => __( 'TimonConfigs complete Home', 'timonconfigs' ), 'categories' => array( 'featured' ), 'description' => __( 'The existing premium layout as reorderable sections, with shared CMS content.', 'timonconfigs' ), 'content' => timonconfigs_default_home_content() ) );
} );
