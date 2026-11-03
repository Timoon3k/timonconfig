<?php
/** Bundled identity artwork and optional, explicit Media Library registration. */
defined( 'ABSPATH' ) || exit;

/** Only public image files from this theme may be registered as bundled assets. */
function timonconfigs_brand_asset_file( string $file ): string {
	if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]*\.(?:webp|png|jpe?g)$/iD', $file ) ) { return ''; }
	$directory = realpath( get_template_directory() . '/assets/images' );
	$path = realpath( get_template_directory() . '/assets/images/' . $file );
	return $directory && $path && dirname( $path ) === $directory && is_file( $path ) && ! is_link( get_template_directory() . '/assets/images/' . $file ) ? $path : '';
}

/** @return array<string,array<string,mixed>> */
function timonconfigs_brand_assets(): array {
	$defaults = array(
		'emblem' => array(
			'file' => 'timon-crimson-emblem.webp', 'width' => 1024, 'height' => 1024,
			'title' => 'TimonConfigs crimson community emblem',
			'alt' => 'Timon community emblem with silver lettering and red lightning',
			'caption' => 'The TimonConfigs community identity in crimson and silver.',
			'description' => 'A circular Timon community emblem with silver lettering, a red ring and lightning, adapted for the TimonConfigs website identity.',
		),
		'hero' => array(
			'file' => 'apex-legends-crimson-hero.webp', 'width' => 1920, 'height' => 1080,
			'title' => 'TimonConfigs crimson Apex-inspired arena',
			'alt' => 'An Apex Legends-inspired arena beneath a red lightning storm',
			'caption' => 'Original arena artwork inspired by Apex Legends for the TimonConfigs community.',
			'description' => 'Original artwork showing a dark science-fiction arena lit by red lightning. Created for TimonConfigs; this illustration is not a gameplay screenshot or an official Apex Legends asset.',
			'variants' => array( array( 'file' => 'apex-legends-crimson-hero-640.webp' ), array( 'file' => 'apex-legends-crimson-hero-960.webp' ) ),
		),
	);
	$manifest = get_template_directory() . '/assets/images/crimson-assets.json';
	$data = is_readable( $manifest ) ? json_decode( (string) file_get_contents( $manifest ), true ) : array();
	if ( is_array( $data['assets'] ?? null ) ) { $data = $data['assets']; }
	$assets = array();
	foreach ( $defaults as $key => $default ) {
		$entry = is_array( $data[ $key ] ?? null ) ? $data[ $key ] : array();
		$entry = array_merge( $default, array_intersect_key( $entry, $default ) );
		$entry['file'] = is_string( $entry['file'] ) ? $entry['file'] : $default['file'];
		$path = timonconfigs_brand_asset_file( $entry['file'] );
		$dimensions = $path ? @getimagesize( $path ) : false;
		$available = is_array( $dimensions ) && in_array( $dimensions['mime'] ?? '', array( 'image/webp', 'image/png', 'image/jpeg' ), true );
		if ( $available ) { $entry['width'] = (int) $dimensions[0]; $entry['height'] = (int) $dimensions[1]; }
		foreach ( array( 'title', 'alt', 'caption', 'description' ) as $field ) {
			$entry[ $field ] = is_scalar( $entry[ $field ] ) ? sanitize_text_field( (string) $entry[ $field ] ) : $default[ $field ];
		}
		$entry['width'] = max( 1, (int) $entry['width'] ); $entry['height'] = max( 1, (int) $entry['height'] );
		$entry['available'] = $available;
		$entry['path'] = $available ? $path : '';
		$entry['src'] = get_template_directory_uri() . '/assets/images/' . $entry['file'];
		$entry['url'] = $entry['src'];
		$entry['sizes'] = '100vw';
		$candidates = $available ? array( $entry['width'] => $entry['src'] ) : array();
		foreach ( is_array( $entry['variants'] ?? null ) ? $entry['variants'] : array() as $variant ) {
			$file = is_string( $variant ) ? $variant : ( $variant['file'] ?? '' );
			if ( ! is_string( $file ) ) { continue; }
			$variant_path = timonconfigs_brand_asset_file( $file );
			$variant_size = $variant_path ? @getimagesize( $variant_path ) : false;
			if ( ! $variant_size || ! $available || abs( ( $variant_size[0] / $variant_size[1] ) - ( $entry['width'] / $entry['height'] ) ) > 0.02 ) { continue; }
			$candidates[ (int) $variant_size[0] ] = get_template_directory_uri() . '/assets/images/' . $file;
		}
		ksort( $candidates );
		$entry['srcset'] = implode( ', ', array_map( static fn( int $width, string $url ): string => $url . ' ' . $width . 'w', array_keys( $candidates ), array_values( $candidates ) ) );
		$assets[ $key ] = $entry;
	}
	return $assets;
}

/** @return array<string,mixed> */
function timonconfigs_brand_emblem(): array { return timonconfigs_brand_assets()['emblem']; }

/** Resolve legacy stock artwork at render time while retaining all authored destinations. */
function timonconfigs_hero_media( array $settings = array() ): array {
	$asset = timonconfigs_brand_assets()['hero'];
	$selected = $settings['image'] ?? get_theme_mod( 'timonconfigs_hero_image', '' );
	$selected = is_scalar( $selected ) ? esc_url_raw( trim( (string) $selected ), array( 'https', 'http' ) ) : '';
	$is_stock = '' === $selected;
	foreach ( array( 'hero-reference-wide.webp', 'hero-premium.webp', 'apex-legends-crimson-hero.webp' ) as $file ) {
		if ( $selected === get_template_directory_uri() . '/assets/images/' . $file ) { $is_stock = true; }
	}
	$block_alt = array_key_exists( 'imageAlt', $settings ) || array_key_exists( 'alt', $settings );
	$explicit_alt = $settings['imageAlt'] ?? $settings['alt'] ?? get_theme_mod( 'timonconfigs_hero_alt', null );
	$has_alt = is_scalar( $explicit_alt ) && ( $block_alt || '' !== trim( (string) $explicit_alt ) );
	if ( ! $is_stock ) {
		$asset['src'] = esc_url_raw( $selected, array( 'https', 'http' ) );
		$asset['url'] = $asset['src'];
		$asset['srcset'] = '';
		$asset['alt'] = $has_alt ? sanitize_text_field( (string) $explicit_alt ) : '';
		$asset['width'] = 0; $asset['height'] = 0;
		$asset['path'] = ''; $asset['available'] = true;
		$id = attachment_url_to_postid( $asset['src'] );
		if ( $id && ! $has_alt ) { $asset['alt'] = sanitize_text_field( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ); }
		$metadata = $id ? wp_get_attachment_metadata( $id ) : false;
		if ( is_array( $metadata ) && ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			$asset['width'] = (int) $metadata['width']; $asset['height'] = (int) $metadata['height'];
		}
		return $asset;
	}
	if ( ! $asset['available'] ) {
		$asset['src'] = get_template_directory_uri() . '/assets/images/hero-reference-wide.webp';
		$asset['url'] = $asset['src']; $asset['srcset'] = '';
		$asset['width'] = 2038; $asset['height'] = 771;
		$asset['alt'] = '';
	}
	if ( $has_alt ) { $asset['alt'] = sanitize_text_field( (string) $explicit_alt ); }
	return $asset;
}

/** Image SEO fields are emitted only for the image actually selected for sharing. */
function timonconfigs_brand_image_metadata( string $url ): array {
	foreach ( timonconfigs_brand_assets() as $asset ) {
		if ( $asset['available'] && $url === $asset['src'] ) { return $asset; }
	}
	return array();
}

/** Only an explicit administrator action copies these two public files into uploads. */
function timonconfigs_import_brand_assets(): array|WP_Error {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) { return new WP_Error( 'tc_brand_forbidden', 'Administrator and Media Library upload permissions are required.' ); }
	$assets = timonconfigs_brand_assets();
	foreach ( $assets as $asset ) { if ( ! $asset['available'] ) { return new WP_Error( 'tc_brand_missing', 'The bundled identity artwork is not available in this theme package.' ); } }
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$ids = array();
	foreach ( $assets as $key => $asset ) {
		$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => array( 'inherit', 'publish', 'private', 'draft' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_timonconfigs_brand_asset', 'value' => $key ), array( 'key' => '_timonconfigs_brand_asset_file', 'value' => $asset['file'] ) ) ) );
		if ( $existing ) { $ids[ $key ] = (int) $existing[0]; continue; }
		$bytes = file_get_contents( $asset['path'] );
		if ( false === $bytes ) { return new WP_Error( 'tc_brand_read', 'The bundled artwork could not be read.' ); }
		$upload = wp_upload_bits( $asset['file'], null, $bytes );
		if ( $upload['error'] ) { return new WP_Error( 'tc_brand_copy', 'The public artwork could not be copied into the Media Library.' ); }
		$type = wp_check_filetype( $asset['file'], array( 'webp' => 'image/webp', 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg' ) );
		$id = wp_insert_attachment( array( 'post_mime_type' => $type['type'], 'post_title' => $asset['title'], 'post_excerpt' => $asset['caption'], 'post_content' => $asset['description'], 'post_status' => 'inherit' ), $upload['file'], 0, true );
		if ( is_wp_error( $id ) ) { wp_delete_file( $upload['file'] ); return new WP_Error( 'tc_brand_attachment', 'The Media Library entry could not be created.' ); }
		update_post_meta( $id, '_wp_attachment_image_alt', $asset['alt'] );
		update_post_meta( $id, '_timonconfigs_brand_asset', $key );
		update_post_meta( $id, '_timonconfigs_brand_asset_file', $asset['file'] );
		$metadata = wp_generate_attachment_metadata( $id, $upload['file'] );
		if ( is_array( $metadata ) ) { wp_update_attachment_metadata( $id, $metadata ); }
		$ids[ $key ] = (int) $id;
	}
	return $ids;
}

add_action( 'admin_menu', static function (): void {
	add_theme_page( 'TimonConfigs brand assets', 'TimonConfigs brand assets', 'manage_options', 'timonconfigs-brand-assets', 'timonconfigs_brand_assets_page' );
} );

function timonconfigs_brand_assets_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator permission is required.', '', array( 'response' => 403 ) ); }
	echo '<div class="wrap"><h1>TimonConfigs brand assets</h1><p>The theme already uses its bundled artwork. This optional action adds editable Media Library entries with their image title, alternative text, caption and description. Existing custom logos and hero settings are preserved.</p>';
	$notice = get_transient( 'tc_brand_import_notice_' . get_current_user_id() );
	if ( is_string( $notice ) && $notice ) { delete_transient( 'tc_brand_import_notice_' . get_current_user_id() ); echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>'; }
	echo '<table class="widefat"><thead><tr><th>Artwork</th><th>Title and alternative text</th></tr></thead><tbody>';
	foreach ( timonconfigs_brand_assets() as $asset ) {
		echo '<tr><td><img src="' . esc_url( $asset['src'] ) . '" width="120" alt=""><br>' . esc_html( $asset['file'] ) . '</td><td><strong>' . esc_html( $asset['title'] ) . '</strong><p>' . esc_html( $asset['alt'] ) . '</p><p>' . esc_html( $asset['caption'] ) . '</p></td></tr>';
	}
	echo '</tbody></table><form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="timonconfigs_import_brand_assets">';
	wp_nonce_field( 'timonconfigs_import_brand_assets' );
	submit_button( 'Add bundled artwork to Media Library' );
	echo '</form></div>';
}

add_action( 'admin_post_timonconfigs_import_brand_assets', 'timonconfigs_handle_import_brand_assets' );
function timonconfigs_handle_import_brand_assets(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) { wp_die( 'Administrator permission is required.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'timonconfigs_import_brand_assets' );
	$result = timonconfigs_import_brand_assets();
	set_transient( 'tc_brand_import_notice_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : 'Artwork is available in the Media Library. Existing authored metadata was preserved.', 120 );
	wp_safe_redirect( admin_url( 'themes.php?page=timonconfigs-brand-assets' ), 303 );
	exit;
}
