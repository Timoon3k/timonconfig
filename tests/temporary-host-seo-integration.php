<?php
/** Temporary hostname SEO policy; no production URL mutation. Disposable localhost only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost WordPress required.' ); }
$checks = 0;
$assert = static function ( bool $ok, string $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$original_host = $_SERVER['HTTP_HOST'] ?? null;
$home_override = null;
$home = static function ( $value ) use ( &$home_override ) { return $home_override ?? $value; };
add_filter( 'option_home', $home );
try {
	$_SERVER['HTTP_HOST'] = 'timonconfigs.com';
	$home_override = 'https://darkorchid-dragonfly-367115.hostingersite.com';
	$assert( TC_SEO::temporary_host(), 'Configured Hostinger preview URL is detected.' );
	$assert( TC_SEO::should_noindex(), 'Temporary Home is not indexable regardless of current query.' );
	$robots = apply_filters( 'wp_robots', array( 'index' => true, 'follow' => true ) );
	$assert( ! isset( $robots['index'] ) && true === $robots['noindex'], 'Core robots override index on temporary host.' );
	foreach ( array( 'wpseo_robots_array', 'rank_math/frontend/robots' ) as $provider ) {
		$robots = apply_filters( $provider, array( 'index' => 'index' ) );
		$assert( 'noindex' === $robots['index'], 'Provider robots preserve noindex: ' . $provider );
	}
	$robots = apply_filters( 'aioseo_robots_meta', array() );
	$assert( 'noindex' === $robots['noindex'], 'AIOSEO robots preserve noindex.' );
	$assert( false === apply_filters( 'wp_sitemaps_enabled', true ), 'Core sitemap is disabled on temporary host.' );
	$assert( false === TC_SEO::sitemap_entry( array( 'loc' => home_url( '/configs/' ) ), 'url', null ), 'External sitemap entries are suppressed on temporary host.' );
	$text = "User-agent: *\nDisallow: /wp-admin/\nSitemap: https://preview.hostingersite.com/wp-sitemap.xml\n";
	$clean = apply_filters( 'robots_txt', $text, true );
	$assert( ! str_contains( $clean, 'Sitemap:' ) && str_contains( $clean, 'Disallow: /wp-admin/' ), 'robots.txt removes sitemap advertising and retains existing policy.' );
	$home_override = 'https://timonconfigs.com';
	$_SERVER['HTTP_HOST'] = 'preview.hostingersite.com:443';
	$assert( TC_SEO::temporary_host(), 'A temporary alias request is detected even with canonical Home configured.' );
	foreach ( array( 'timonconfigs.com', 'preview.hostingersite.com.evil.example', 'hostingersite.com.evil.example', 'evilhhostingersite.com', 'preview.hostingersite.com@evil.example' ) as $host ) {
		$_SERVER['HTTP_HOST'] = $host;
		$assert( ! TC_SEO::temporary_host(), 'Unrelated hostname/suffix does not activate preview policy: ' . $host );
	}
	$_SERVER['HTTP_HOST'] = 'timonconfigs.com';
	$assert( true === apply_filters( 'wp_sitemaps_enabled', true ), 'Canonical installation retains its sitemap policy.' );
	$assert( $text === TC_SEO::robots_text( $text ), 'Canonical installation robots content is unchanged.' );
	$assert( array( 'loc' => 'canonical' ) === TC_SEO::sitemap_entry( array( 'loc' => 'canonical' ), 'url', null ), 'Canonical provider sitemap ownership remains intact.' );
	$assert( 'https://timonconfigs.com/' === home_url( '/' ), 'Safeguard never rewrites the configured home URL.' );
	$assert( get_option( 'home' ) === 'https://timonconfigs.com', 'URL check uses a read filter rather than persisting a site URL change.' );
	WP_CLI::success( "Temporary-host SEO integration: $checks assertions passed." );
} finally {
	remove_filter( 'option_home', $home );
	if ( null === $original_host ) { unset( $_SERVER['HTTP_HOST'] ); } else { $_SERVER['HTTP_HOST'] = $original_host; }
}
