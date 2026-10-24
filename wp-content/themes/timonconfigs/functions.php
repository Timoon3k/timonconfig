<?php
/**
 * TimonConfigs theme setup.
 *
 * @package TimonConfigs
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMONCONFIGS_THEME_VERSION', '0.10.3' );

require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/setup-notice.php';
if ( file_exists( get_template_directory() . '/inc/blocks.php' ) ) {
	require_once get_template_directory() . '/inc/blocks.php';
}

add_filter( 'wp_preload_resources', static function ( array $resources ): array {
	$resources[] = array( 'href' => get_template_directory_uri() . '/assets/fonts/inter-latin-wght-normal.woff2', 'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous' );
	return $resources;
} );

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'timonconfigs', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'custom-logo', array( 'height' => 64, 'width' => 260, 'flex-height' => true, 'flex-width' => true ) );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/editor.css', 'assets/css/main.css', 'assets/css/premium.css', 'assets/css/catalog.css', 'assets/css/media.css' ) );
		register_nav_menus( array( 'primary' => __( 'Primary navigation', 'timonconfigs' ), 'footer' => __( 'Footer navigation', 'timonconfigs' ) ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'timonconfigs', get_template_directory_uri() . '/assets/css/main.css', array(), TIMONCONFIGS_THEME_VERSION );
		wp_enqueue_style( 'timonconfigs-premium', get_template_directory_uri() . '/assets/css/premium.css', array( 'timonconfigs' ), TIMONCONFIGS_THEME_VERSION );
		foreach ( array( 'catalog', 'media', 'member', 'config-content', 'owner-editor' ) as $stylesheet ) {
			if ( file_exists( get_template_directory() . '/assets/css/' . $stylesheet . '.css' ) ) {
				wp_enqueue_style( 'timonconfigs-' . $stylesheet, get_template_directory_uri() . '/assets/css/' . $stylesheet . '.css', array( 'timonconfigs-premium' ), TIMONCONFIGS_THEME_VERSION );
			}
		}
		wp_enqueue_script( 'timonconfigs-config-content', get_template_directory_uri() . '/assets/js/config-content.js', array(), TIMONCONFIGS_THEME_VERSION, true );
		wp_enqueue_script( 'timonconfigs', get_template_directory_uri() . '/assets/js/main.js', array(), TIMONCONFIGS_THEME_VERSION, true );
	}
);

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'tc-site';
		if ( class_exists( 'TC_Member_Area' ) && TC_Member_Area::is_member_request() ) {
			$classes[] = 'tc-member-page';
			$classes[] = is_user_logged_in() ? 'tc-member-page--owner' : 'tc-member-page--guest';
		}
		return $classes;
	}
);

/**
 * Build the canonical URL for the current public view.
 */
function timonconfigs_canonical_url(): string {
	if ( is_404() ) {
		return '';
	}
	if ( is_search() ) {
		return (string) get_search_link();
	}
	if ( is_singular() ) {
		$url = (string) get_permalink( get_queried_object_id() );
		$page = max( 1, (int) get_query_var( 'page' ) );
		return $page > 1 ? trailingslashit( $url ) . $page . '/' : $url;
	}

	if ( is_post_type_archive( 'tc_config' ) ) {
		$base = (string) get_post_type_archive_link( 'tc_config' );
	} elseif ( is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) {
		$link = get_term_link( get_queried_object() );
		$base = is_wp_error( $link ) ? home_url( '/' ) : $link;
	} else {
		$base = home_url( '/' );
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	return $paged > 1 ? trailingslashit( $base ) . 'page/' . $paged . '/' : $base;
}

/**
 * Return a concise page-specific description for the built-in SEO fallback.
 */
function timonconfigs_meta_description(): string {
	return timonconfigs_seo_description();
}

/**
 * Breadcrumb items shared by visible navigation and structured data.
 *
 * @return array<int, array{name:string,url:string}>
 */
function timonconfigs_breadcrumb_items(): array {
	$items = array( array( 'name' => 'Home', 'url' => home_url( '/' ) ) );

	if ( is_post_type_archive( 'tc_config' ) ) {
		$items[] = array( 'name' => 'Configs', 'url' => (string) get_post_type_archive_link( 'tc_config' ) );
	} elseif ( is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) {
		$items[] = array( 'name' => 'Configs', 'url' => (string) get_post_type_archive_link( 'tc_config' ) );
		$items[] = array( 'name' => single_term_title( '', false ), 'url' => timonconfigs_canonical_url() );
	} elseif ( is_singular( 'tc_config' ) ) {
		$items[] = array( 'name' => 'Configs', 'url' => (string) get_post_type_archive_link( 'tc_config' ) );
		$items[] = array( 'name' => get_the_title(), 'url' => (string) get_permalink() );
	} elseif ( is_page() && ! is_front_page() ) {
		$items[] = array( 'name' => get_the_title(), 'url' => (string) get_permalink() );
	}

	return $items;
}

function timonconfigs_breadcrumbs(): void {
	$items = timonconfigs_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return;
	}
	?>
	<nav class="tc-breadcrumbs" aria-label="Breadcrumb"><ol>
		<?php foreach ( $items as $index => $item ) : ?>
			<li><?php if ( $index < count( $items ) - 1 ) : ?><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a><?php else : ?><span aria-current="page"><?php echo esc_html( $item['name'] ); ?></span><?php endif; ?></li>
		<?php endforeach; ?>
	</ol></nav>
	<?php
}

function timonconfigs_plan_url( string $plan ): string {
	if ( function_exists( 'timonconfigs_checkout_url' ) ) {
		$url = timonconfigs_checkout_url( $plan );
		if ( '' !== $url ) {
			return $url;
		}
	}

	return home_url( '/pricing/' );
}

/** Keep the community CTA visible while pointing to honest contact guidance until configured. */
function timonconfigs_community_url(): string {
	$url = (string) get_theme_mod( 'timonconfigs_discord_url', '' );
	return $url ?: 'https://discord.gg/RyvzSqrF6';
}

/**
 * FAQ entries from the editor, with factual defaults for a fresh install.
 *
 * @return array<int, array{question:string,answer:string}>
 */
function timonconfigs_faq_items(): array {
	$posts = get_posts( array( 'post_type' => 'tc_faq', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
	if ( $posts ) {
		return array_map(
			static fn( WP_Post $post ): array => array( 'question' => get_the_title( $post ), 'answer' => apply_filters( 'the_content', $post->post_content ) ),
			$posts
		);
	}
	return array(
		array( 'question' => 'How do I receive my configs?', 'answer' => 'Explore Free access or choose a VIP plan. Upgrade.chat manages checkout and Discord role delivery.' ),
		array( 'question' => 'Do I need a Cronus Zen or XIM Matrix?', 'answer' => 'Yes. Each configuration lists its supported device, input method, and platform. Check these details before choosing a plan.' ),
		array( 'question' => 'Which games are supported?', 'answer' => 'Apex Legends is the current flagship collection. Additional releases appear only when their compatibility details are ready.' ),
		array( 'question' => 'What does VIP Lifetime include?', 'answer' => 'A one-time €100 license with access and updates defined by the Lifetime terms presented at checkout.' ),
		array( 'question' => 'Do you offer refunds?', 'answer' => 'Review the Refund Policy for digital access, subscription cancellation, and support. Statutory consumer rights remain unaffected.' ),
	);
}

/** Render accessible FAQ disclosure rows. */
function timonconfigs_render_faqs( ?int $limit = null ): void {
	$items = timonconfigs_faq_items();
	$items = null === $limit ? $items : array_slice( $items, 0, $limit );
	foreach ( $items as $item ) {
		echo '<details><summary>' . esc_html( $item['question'] ) . '</summary><div class="tc-faq-answer">' . wp_kses_post( $item['answer'] ) . '</div></details>';
	}
}

/**
 * Published reviews or clearly labelled layout samples for an empty site.
 *
 * @return array<int, array{name:string,quote:string,config:string,rating:int,demo:bool,image:string}>
 */
function timonconfigs_review_items(): array {
	$posts = get_posts( array( 'post_type' => 'tc_review', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
	if ( $posts ) {
		return array_map(
			static fn( WP_Post $post ): array => array(
				'name'   => get_the_title( $post ),
				'quote'  => apply_filters( 'the_content', $post->post_content ),
				'config' => (string) get_post_meta( $post->ID, 'tc_review_config', true ),
				'rating' => min( 5, max( 1, (int) get_post_meta( $post->ID, 'tc_review_rating', true ) ) ),
				'demo'   => (bool) get_post_meta( $post->ID, 'tc_review_demo', true ),
				'image'  => (string) get_the_post_thumbnail_url( $post, 'thumbnail' ),
			),
			$posts
		);
	}
	$samples = array(
		array( 'Nova', '“Clear setup steps and a well-organized library. Finding the right profile felt straightforward.”', 'Apex Legends · Cronus Zen' ),
		array( 'Vector', '“I liked being able to check the device and input details before choosing a configuration.”', 'Apex Legends · XIM Matrix' ),
		array( 'Orbit', '“The walkthrough made it easy to follow each step and understand the settings for my setup.”', 'XIM Matrix · Controller' ),
		array( 'Pulse', '“A focused library with clear version information. The setup guide was a useful starting point.”', 'Cronus Zen · Controller' ),
		array( 'Cipher', '“Having the compatibility details in one place helped me compare the available options.”', 'Apex Legends · MnK' ),
		array( 'Echo', '“A welcoming community and a simple way to explore the different membership options.”', 'TimonConfigs Community' ),
	);
	return array_map(
		static fn( array $sample ): array => array( 'name' => $sample[0], 'quote' => $sample[1], 'config' => $sample[2], 'rating' => 5, 'demo' => true, 'image' => '' ),
		$samples
	);
}

function timonconfigs_icon( string $name ): string {
	$icons = array(
		'arrow' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M4 10h11M11 5l5 5-5 5"/></svg>',
		'check' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg>',
		'play'  => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m7 5 8 5-8 5Z"/></svg>',
		'discord' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M7 5 4 6 2 15l4 2 1-2m6-10 3 1 2 9-4 2-1-2M6 14c3 1 5 1 8 0"/><circle cx="7" cy="10" r="1"/><circle cx="13" cy="10" r="1"/></svg>',
		'x' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M3 3h4l10 14h-4ZM17 3 3 17"/></svg>',
		'youtube' => '<svg aria-hidden="true" viewBox="0 0 20 20"><rect x="1" y="4" width="18" height="12" rx="4"/><path d="m8 7 5 3-5 3Z"/></svg>',
		'instagram' => '<svg aria-hidden="true" viewBox="0 0 20 20"><rect x="2" y="2" width="16" height="16" rx="4"/><circle cx="10" cy="10" r="4"/><path d="M14 5h1"/></svg>',
		'bolt' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m11 2-7 9h5l-1 7 8-10h-5Z"/></svg>',
		'refresh' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M16 6a7 7 0 1 0 1 7M16 2v5h-5"/></svg>',
		'shield' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m10 2 7 3v5c0 4-4 7-7 8-3-1-7-4-7-8V5Z"/><path d="m6 10 3 3 5-6"/></svg>',
		'users' => '<svg aria-hidden="true" viewBox="0 0 20 20"><circle cx="7" cy="6" r="3"/><path d="M1 18v-3a6 6 0 0 1 12 0v3M14 3a3 3 0 0 1 0 6m1 3a5 5 0 0 1 4 5"/></svg>',
		'crosshair' => '<svg aria-hidden="true" viewBox="0 0 20 20"><circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3"/><path d="M10 0v4m0 12v4M0 10h4m12 0h4"/></svg>',
		'download' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M10 2v11m-4-4 4 4 4-4M3 13v5h14v-5"/></svg>',
		'search' => '<svg aria-hidden="true" viewBox="0 0 20 20"><circle cx="8" cy="8" r="5.5"/><path d="m12 12 6 6"/></svg>',
		'settings' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m8 2 4 0 1 3 3 1 2 4-2 3-1 3-4 2-3-2-3-1-2-4 2-3 1-3Z"/><circle cx="10" cy="10" r="3"/></svg>',
		'gamepad' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M6 5h8c3 0 5 9 3 11-2 1-4-3-5-3H8c-1 0-3 4-5 3C1 14 3 5 6 5Z"/><path d="M5 9h4M7 7v4m6-2h1m1 2h1"/></svg>',
		'crown' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m2 5 4 4 4-6 4 6 4-4-2 11H4Zm2 13h12"/></svg>',
		'gift' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M2 8h16v4H2Zm2 4v6h12v-6M10 8v10"/><path d="M10 8C2 8 3 1 7 3l3 5c8 0 7-7 3-5Z"/></svg>',
		'menu'  => '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
	);
	return $icons[ $name ] ?? '';
}

