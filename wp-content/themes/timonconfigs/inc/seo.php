<?php
/** Provider-aware search and social metadata. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

/** One metadata provider owns the document; never duplicate an active SEO plugin. */
function timonconfigs_external_seo_active(): bool {
	$active = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' ) || defined( 'SLIM_SEO_VER' ) || function_exists( 'aioseo' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
	return (bool) apply_filters( 'timonconfigs_external_seo_active', $active );
}

/** @return array<string,array{title:string,description:string}> */
function timonconfigs_seo_page_defaults(): array {
	$monthly = timonconfigs_display_price( 'monthly' );
	$lifetime = timonconfigs_display_price( 'lifetime' );
	$checkout = 'secure website checkout';
	return array(
		'home' => array( 'title' => 'XIM Matrix Apex Legends Configs | TimonConfigs', 'description' => 'Explore XIM Matrix Apex Legends setup guides and Cronus Zen configs. Compare devices, view release details and join the TimonConfigs Discord community.' ),
		'games' => array( 'title' => 'Game Config Collections & Roadmap | TimonConfigs', 'description' => 'Explore Apex Legends and the TimonConfigs game roadmap. Check each collection for released configurations, supported devices, input methods, and availability.' ),
		'pricing' => array( 'title' => 'Free, VIP Monthly & Lifetime Pricing | TimonConfigs', 'description' => sprintf( 'Compare Free access, VIP Monthly at %1$s per month, and VIP Lifetime at %2$s one time. Review renewal, license terms, and %3$s.', $monthly, $lifetime, $checkout ) ),
		'vip-monthly' => array( 'title' => sprintf( 'TimonConfigs VIP Monthly — %s per Month', $monthly ), 'description' => 'VIP Monthly offers premium config access, covered updates while subscribed, private Discord channels, and VIP support. Review recurring billing and cancellation.' ),
		'vip-lifetime' => array( 'title' => sprintf( 'TimonConfigs VIP Lifetime — %s One Time', $lifetime ), 'description' => sprintf( 'Choose VIP Lifetime for a %s one-time purchase. Explore premium configuration access, included license terms, and covered updates without monthly billing.', $lifetime ) ),
		'reviews' => array( 'title' => 'Community Reviews & Customer Vouches | TimonConfigs', 'description' => 'Read TimonConfigs community feedback and configuration experiences. Demonstration cards are explicitly marked Sample Review and are not customer endorsements.' ),
		'faq' => array( 'title' => 'Configuration, Membership & Checkout FAQ | TimonConfigs', 'description' => sprintf( 'Find answers about Cronus Zen and XIM Matrix compatibility, Apex Legends releases, Free and VIP plans, %s, Discord access, and support.', 'website checkout' ) ),
		'contact' => array( 'title' => 'Cronus Zen Discord & XIM Matrix Support | TimonConfigs', 'description' => 'Join the TimonConfigs Cronus Zen Discord community for XIM Matrix and Apex Legends setup help, release updates and Free or VIP membership support.' ),
		'video-showcase' => array( 'title' => 'Apex Legends Gameplay Video Showcase | TimonConfigs', 'description' => 'Watch first-party TimonConfigs Apex Legends demonstrations. Check each video for confirmed device, configuration, recording, and setup information.' ),
		'terms-of-service' => array( 'title' => 'Terms of Service | TimonConfigs', 'description' => 'Review TimonConfigs terms for digital configuration licenses, memberships, acceptable use, payment handling, account responsibilities, and consumer rights.' ),
		'privacy-policy' => array( 'title' => 'Privacy Policy | TimonConfigs', 'description' => 'Learn how TimonConfigs handles website, account, Discord, support, and technical information, including data access and privacy contact options.' ),
		'refund-policy' => array( 'title' => 'Refund & Cancellation Policy | TimonConfigs', 'description' => 'Review TimonConfigs digital-access refund requests, recurring subscription cancellation, support options, and applicable statutory consumer rights.' ),
	);
}

function timonconfigs_seo_meta( string $key ): string {
	if ( is_singular() ) { return (string) get_post_meta( get_queried_object_id(), $key, true ); }
	if ( is_tax( array( 'tc_game', 'tc_device' ) ) ) { return (string) get_term_meta( get_queried_object_id(), $key, true ); }
	return '';
}

function timonconfigs_seo_title(): string {
	$custom = timonconfigs_seo_meta( 'tc_seo_title' );
	if ( '' !== $custom ) { return $custom; }
	$defaults = timonconfigs_seo_page_defaults();
	if ( is_front_page() ) { return $defaults['home']['title']; }
	if ( is_page() ) {
		$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		if ( isset( $defaults[ $slug ] ) ) { return $defaults[ $slug ]['title']; }
		return get_the_title( get_queried_object_id() ) . ' | TimonConfigs';
	}
	if ( is_post_type_archive( 'tc_config' ) ) { return class_exists( 'TC_Catalog' ) && 'free' === TC_Catalog::request_value( 'tier' ) ? 'Free Cronus Zen & XIM Matrix Configs | TimonConfigs' : 'Cronus Zen & XIM Matrix Config Library | TimonConfigs'; }
	if ( is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) {
		$term = get_queried_object();
		return ( $term instanceof WP_Term ? $term->name : 'Game' ) . ' Configs | TimonConfigs';
	}
	if ( is_singular( 'tc_config' ) ) { return get_the_title( get_queried_object_id() ) . ' | TimonConfigs'; }
	if ( is_404() ) { return 'Page Not Found | TimonConfigs'; }
	if ( is_search() ) { return 'Search Results | TimonConfigs'; }
	return (string) get_bloginfo( 'name' );
}

function timonconfigs_seo_description(): string {
	$custom = timonconfigs_seo_meta( 'tc_seo_description' );
	if ( '' !== $custom ) { return $custom; }
	$defaults = timonconfigs_seo_page_defaults();
	if ( is_front_page() ) { return $defaults['home']['description']; }
	if ( is_page() ) {
		$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		if ( isset( $defaults[ $slug ] ) ) { return $defaults[ $slug ]['description']; }
	}
	if ( is_post_type_archive( 'tc_config' ) ) { return class_exists( 'TC_Catalog' ) && 'free' === TC_Catalog::request_value( 'tier' ) ? 'Explore selected Free TimonConfigs configurations. Check supported devices, controller or MnK input, confirmed platforms, game versions, and published public access links.' : 'Browse TimonConfigs configurations by game, Cronus Zen or XIM Matrix device, controller or MnK input, platform, and Free or VIP access. Check release details before choosing.'; }
	if ( is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term && $term->description ) { return wp_strip_all_tags( $term->description ); }
		if ( $term instanceof WP_Term ) { return sprintf( 'Explore %s configuration compatibility, input methods, availability, membership options, and setup guidance from TimonConfigs.', $term->name ); }
	}
	if ( is_404() ) { return 'This TimonConfigs address could not be found. Browse the configuration library, game collections, pricing, or contact the community for help.'; }
	if ( is_search() ) { return 'Search TimonConfigs pages and configuration information. Review device, input method, platform, and release details before choosing a configuration.'; }
	$post = get_queried_object();
	if ( $post instanceof WP_Post ) {
		$text = $post->post_excerpt ?: $post->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		if ( '' !== trim( $text ) ) { return wp_trim_words( $text, 30, '' ); }
	}
	return $defaults['home']['description'];
}

function timonconfigs_social_image(): string {
	$custom = timonconfigs_seo_meta( 'tc_seo_image' );
	if ( '' !== $custom ) { return $custom; }
	if ( is_tax( array( 'tc_game', 'tc_device' ) ) && method_exists( 'TC_Catalog', 'collection_image' ) ) {
		$image = TC_Catalog::collection_image( get_queried_object() );
		if ( $image ) { return $image; }
	}
	if ( is_singular() ) {
		$image = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		if ( $image ) { return $image; }
	}
	if ( class_exists( 'TC_Collections' ) && method_exists( 'TC_Catalog', 'collection_image' ) ) {
		$collection = TC_Collections::current();
		if ( isset( $collection['game_term'] ) && $collection['game_term'] instanceof WP_Term ) {
			$image = TC_Catalog::collection_image( $collection['game_term'] );
			if ( $image ) { return $image; }
		}
	}
	return (string) get_theme_mod( 'timonconfigs_hero_image', get_template_directory_uri() . '/assets/images/hero-reference-wide.webp' );
}

add_filter( 'pre_get_document_title', static function ( string $title ): string {
	if ( timonconfigs_external_seo_active() ) { return $title; }
	$title = timonconfigs_seo_title();
	$collection_page = class_exists( 'TC_Collections' ) && TC_Collections::current() ? TC_Collections::page_number() : 1;
	$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ), $collection_page );
	return $paged > 1 && ! preg_match( '/\bPage\s+' . $paged . '\b/i', $title ) ? sprintf( '%s — Page %d', $title, $paged ) : $title;
}, 20 );

add_action( 'wp', static function (): void {
	if ( ! timonconfigs_external_seo_active() ) { remove_action( 'wp_head', 'rel_canonical' ); }
} );

function timonconfigs_print_metadata(): void {
	if ( timonconfigs_external_seo_active() ) { return; }
	$title = wp_get_document_title();
	$description = timonconfigs_seo_description();
	$url = timonconfigs_canonical_url();
	$image = timonconfigs_social_image();
	?>
	<meta name="description" content="<?php echo esc_attr( $description ); ?>">
	<?php if ( $url && ! is_404() ) : ?><link rel="canonical" href="<?php echo esc_url( $url ); ?>"><?php endif; ?>
	<meta property="og:type" content="<?php echo is_singular( 'tc_config' ) ? 'article' : 'website'; ?>">
	<meta property="og:site_name" content="TimonConfigs">
	<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
	<?php if ( $url && ! is_404() ) : ?><meta property="og:url" content="<?php echo esc_url( $url ); ?>"><?php endif; ?>
	<meta property="og:image" content="<?php echo esc_url( $image ); ?>">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>">
	<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>">
	<meta name="twitter:image" content="<?php echo esc_url( $image ); ?>">
	<?php
}
add_action( 'wp_head', 'timonconfigs_print_metadata', 2 );

/** @return array<int,array<string,mixed>> */
function timonconfigs_schema_graph(): array {
	$schemas = array();
	if ( is_front_page() ) {
		$organization = array( '@type' => 'Organization', '@id' => home_url( '/#organization' ), 'name' => 'TimonConfigs', 'url' => home_url( '/' ) );
		$logo = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
		if ( $logo ) { $organization['logo'] = $logo; }
		$socials = array_filter( array_map( static fn( string $key ): string => (string) get_theme_mod( 'timonconfigs_' . $key . '_url', '' ), array( 'discord', 'youtube', 'x', 'instagram' ) ) );
		if ( $socials ) { $organization['sameAs'] = array_values( $socials ); }
		$schemas[] = $organization;
	}
	if ( ( is_front_page() && ( ! function_exists( 'timonconfigs_home_has_section' ) || timonconfigs_home_has_section( 'faq' ) ) ) || is_page( 'faq' ) ) {
		$items = timonconfigs_faq_items();
		if ( is_front_page() ) { $items = array_slice( $items, 0, 5 ); }
		if ( $items ) {
			$schemas[] = array( '@type' => 'FAQPage', 'mainEntity' => array_map( static fn( array $item ): array => array( '@type' => 'Question', 'name' => $item['question'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $item['answer'] ) ) ), $items ) );
		}
	}
	$crumbs = timonconfigs_breadcrumb_items();
	if ( count( $crumbs ) > 1 && ! is_404() ) {
		$schemas[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => array_map( static fn( array $item, int $index ): array => array( '@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url'] ), $crumbs, array_keys( $crumbs ) ) );
	}
	if ( ( ( is_front_page() && ( ! function_exists( 'timonconfigs_home_has_section' ) || timonconfigs_home_has_section( 'videos' ) ) ) || is_page( 'video-showcase' ) ) && method_exists( 'TC_Videos', 'get_public_videos' ) ) {
		foreach ( TC_Videos::get_public_videos() as $video ) {
			if ( empty( $video['verified'] ) || empty( $video['upload_date'] ) || empty( $video['duration'] ) || empty( $video['poster'] ) || empty( $video['url'] ) || ! strtotime( $video['upload_date'] ) ) { continue; }
			$schema = array( '@type' => 'VideoObject', 'name' => $video['title'], 'description' => $video['description'], 'thumbnailUrl' => $video['poster'], 'uploadDate' => gmdate( 'c', strtotime( $video['upload_date'] ) ), 'duration' => $video['duration'] );
			if ( 'youtube' === $video['type'] && ! empty( $video['youtube_id'] ) ) { $schema['embedUrl'] = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $video['youtube_id'] ); }
			else { $schema['contentUrl'] = $video['url']; }
			$schemas[] = $schema;
		}
	}
	if ( is_page( array( 'vip-monthly', 'vip-lifetime' ) ) && timonconfigs_direct_billing() ) {
		$plan = is_page( 'vip-lifetime' ) ? 'lifetime' : 'monthly';
		$checkout = timonconfigs_plan_url( $plan );
		$price = timonconfigs_display_price( $plan );
		if ( $checkout && preg_match( '/^€\s*(\d+(?:[.,]\d{1,2})?)$/u', trim( $price ), $matches ) ) {
			$schemas[] = array(
				'@type' => 'Product', 'name' => 'TimonConfigs VIP ' . ucfirst( $plan ),
				'description' => timonconfigs_seo_description(), 'url' => timonconfigs_canonical_url(),
				'offers' => array( '@type' => 'Offer', 'url' => $checkout, 'priceCurrency' => 'EUR', 'price' => str_replace( ',', '.', $matches[1] ), 'description' => 'monthly' === $plan ? 'Recurring monthly membership. Renewal and cancellation terms are displayed at checkout.' : 'One-time purchase under the Lifetime license terms displayed at checkout.' ),
			);
		}
	}
	return $schemas;
}

function timonconfigs_print_schema(): void {
	if ( timonconfigs_external_seo_active() ) { return; }
	$schemas = timonconfigs_schema_graph();
	if ( $schemas ) { echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $schemas ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>'; }
}
add_action( 'wp_head', 'timonconfigs_print_schema', 20 );
