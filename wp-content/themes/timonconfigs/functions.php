<?php
/**
 * TimonConfigs theme setup.
 *
 * @package TimonConfigs
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMONCONFIGS_THEME_VERSION', '0.1.0' );

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

add_action(
	'wp_head',
	static function (): void {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}

		$description = is_front_page()
			? 'Premium Cronus Zen and XIM Matrix configurations for Apex Legends, with Free, VIP Monthly, and VIP Lifetime access.'
			: wp_strip_all_tags( get_the_excerpt() );
		if ( '' === $description ) {
			$description = get_bloginfo( 'description' );
		}

		$canonical = is_singular() ? get_permalink() : home_url( '/' );
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
		if ( ! is_front_page() ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => 'TimonConfigs',
			'url'      => home_url( '/' ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>';
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

