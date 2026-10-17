<?php
/**
 * TimonConfigs theme setup.
 *
 * @package TimonConfigs
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMONCONFIGS_THEME_VERSION', '0.2.0' );

require_once get_template_directory() . '/inc/customizer.php';

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
		add_editor_style( 'assets/css/editor.css' );
		register_nav_menus( array( 'primary' => __( 'Primary navigation', 'timonconfigs' ), 'footer' => __( 'Footer navigation', 'timonconfigs' ) ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'timonconfigs', get_template_directory_uri() . '/assets/css/main.css', array(), TIMONCONFIGS_THEME_VERSION );
		wp_enqueue_script( 'timonconfigs', get_template_directory_uri() . '/assets/js/main.js', array(), TIMONCONFIGS_THEME_VERSION, true );
	}
);

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'tc-site';
		return $classes;
	}
);

/**
 * Build the canonical URL for the current public view.
 */
function timonconfigs_canonical_url(): string {
	if ( is_singular() ) {
		return (string) get_permalink();
	}

	if ( is_post_type_archive( 'tc_config' ) ) {
		$base = (string) get_post_type_archive_link( 'tc_config' );
	} elseif ( is_tax( array( 'tc_game', 'tc_device' ) ) ) {
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
	if ( is_front_page() ) {
		return 'Premium Cronus Zen and XIM Matrix configurations for Apex Legends, with Free, VIP Monthly, and VIP Lifetime access.';
	}

	if ( is_page() ) {
		$page_descriptions = array(
			'games'            => 'Browse supported TimonConfigs game collections and find Cronus Zen or XIM Matrix releases for your setup.',
			'vip-monthly'      => 'VIP Monthly includes premium configs, covered updates while subscribed, and private Discord channels for €40 per month.',
			'vip-lifetime'     => 'VIP Lifetime provides premium config access under the Lifetime license terms for a €100 one-time payment.',
			'faq'              => 'Answers about TimonConfigs compatibility, membership plans, Upgrade.chat checkout, Discord access, and support.',
			'contact'          => 'Contact TimonConfigs for configuration, membership, policy, privacy, or account support.',
			'video-showcase'   => 'Watch first-party TimonConfigs Apex Legends gameplay demonstrations for the flagship config collection.',
			'terms-of-service' => 'Terms governing TimonConfigs digital configuration licenses, memberships, access, and acceptable use.',
			'privacy-policy'   => 'How TimonConfigs handles account, Discord, support, security, and website information.',
			'refund-policy'    => 'Refund and cancellation information for TimonConfigs digital access and VIP memberships.',
		);
		$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		if ( isset( $page_descriptions[ $slug ] ) ) {
			return $page_descriptions[ $slug ];
		}
	}

	if ( is_post_type_archive( 'tc_config' ) ) {
		return 'Browse verified TimonConfigs releases by game, device, input method, and access tier.';
	}

	if ( is_tax( array( 'tc_game', 'tc_device' ) ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term && $term->description ) {
			return wp_strip_all_tags( $term->description );
		}
		if ( $term instanceof WP_Term ) {
			return sprintf( 'Explore verified %s configs, compatibility details, access options, and current release information.', $term->name );
		}
	}

	$description = is_singular() ? wp_strip_all_tags( get_the_excerpt() ) : '';
	return '' !== $description ? $description : (string) get_bloginfo( 'description' );
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
	} elseif ( is_tax( array( 'tc_game', 'tc_device' ) ) ) {
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

add_action(
	'wp_head',
	static function (): void {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}

		$description = timonconfigs_meta_description();
		$canonical   = timonconfigs_canonical_url();
		?>
		<meta name="description" content="<?php echo esc_attr( wp_trim_words( $description, 28, '' ) ); ?>">
		<link rel="canonical" href="<?php echo esc_url( $canonical ); ?>">
		<meta property="og:type" content="<?php echo is_singular() ? 'article' : 'website'; ?>">
		<meta property="og:title" content="<?php echo esc_attr( wp_get_document_title() ); ?>">
		<meta property="og:description" content="<?php echo esc_attr( wp_trim_words( $description, 28, '' ) ); ?>">
		<meta property="og:url" content="<?php echo esc_url( $canonical ); ?>">
		<?php
	},
	2
);

add_action(
	'wp_head',
	static function (): void {
		$schemas = array();
		if ( is_front_page() ) {
			$schemas[] = array(
				'@context' => 'https://schema.org',
				'@type'    => 'Organization',
				'name'     => 'TimonConfigs',
				'url'      => home_url( '/' ),
			);
		}

		$crumbs = timonconfigs_breadcrumb_items();
		if ( count( $crumbs ) > 1 ) {
			$schemas[] = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array_map(
					static fn( array $item, int $index ): array => array(
						'@type'    => 'ListItem',
						'position' => $index + 1,
						'name'     => $item['name'],
						'item'     => $item['url'],
					),
					$crumbs,
					array_keys( $crumbs )
				),
			);
		}

		foreach ( $schemas as $schema ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>';
		}
	},
	20
);

function timonconfigs_plan_url( string $plan ): string {
	if ( function_exists( 'timonconfigs_checkout_url' ) ) {
		$url = timonconfigs_checkout_url( $plan );
		if ( '' !== $url ) {
			return $url;
		}
	}

	return home_url( '/pricing/' );
}

function timonconfigs_icon( string $name ): string {
	$icons = array(
		'arrow' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="M4 10h11M11 5l5 5-5 5"/></svg>',
		'check' => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg>',
		'play'  => '<svg aria-hidden="true" viewBox="0 0 20 20"><path d="m7 5 8 5-8 5Z"/></svg>',
		'menu'  => '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
	);
	return $icons[ $name ] ?? '';
}

