<?php
/** Read-only setup guidance for administrators. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

function timonconfigs_setup_notice(): void {
	if ( ! current_user_can( 'manage_options' ) || is_network_admin() ) {
		return;
	}
	$core_ready = class_exists( 'TC_Site' ) && is_callable( array( 'TC_Site', 'pages' ) ) && is_callable( array( 'TC_Site', 'install' ) );
	if ( ! $core_ready ) {
		?>
		<div class="notice notice-warning"><p><strong><?php esc_html_e( 'TimonConfigs setup', 'timonconfigs' ); ?></strong> — <?php esc_html_e( 'Install or activate the current TimonConfigs Core plugin, then open Tools → TimonConfigs setup. Existing content and settings will be preserved.', 'timonconfigs' ); ?></p><p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Review installed plugins', 'timonconfigs' ); ?></a></p></div>
		<?php
		return;
	}

	$front_id = (int) get_option( 'page_on_front' );
	$front = $front_id ? get_post( $front_id ) : null;
	$front_ready = 'page' === get_option( 'show_on_front' ) && $front instanceof WP_Post && 'page' === $front->post_type && 'publish' === $front->post_status;
	$required_slugs = array_keys( TC_Site::pages() );
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'post_parent' => 0, 'post_name__in' => $required_slugs, 'posts_per_page' => count( $required_slugs ), 'no_found_rows' => true ) );
	$pages_missing = (bool) array_diff( $required_slugs, wp_list_pluck( $pages, 'post_name' ) );
	$locations = get_nav_menu_locations();
	$menus_missing = false;
	foreach ( array( 'primary', 'footer' ) as $location ) {
		if ( empty( $locations[ $location ] ) || ! wp_get_nav_menu_object( $locations[ $location ] ) ) {
			$menus_missing = true;
			break;
		}
	}
	if ( $front_ready && ! $pages_missing && ! $menus_missing ) {
		return;
	}
	?>
	<div class="notice notice-warning"><p><strong><?php esc_html_e( 'TimonConfigs setup is incomplete.', 'timonconfigs' ); ?></strong>
		<?php if ( $pages_missing ) : ?><?php esc_html_e( 'Some required pages are missing.', 'timonconfigs' ); ?> <?php endif; ?>
		<?php if ( ! $front_ready ) : ?><?php esc_html_e( 'Choose a published static homepage in Settings → Reading.', 'timonconfigs' ); ?> <?php endif; ?>
		<?php if ( $menus_missing ) : ?><?php esc_html_e( 'Assign the Primary and Footer navigation menus.', 'timonconfigs' ); ?> <?php endif; ?>
		<?php esc_html_e( 'The setup action preserves existing authored content and assigned settings.', 'timonconfigs' ); ?></p>
		<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'tools.php?page=timonconfigs-setup' ) ); ?>"><?php esc_html_e( 'Open TimonConfigs setup', 'timonconfigs' ); ?></a>
		<?php if ( ! $front_ready ) : ?><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php esc_html_e( 'Review homepage settings', 'timonconfigs' ); ?></a><?php endif; ?>
		<?php if ( $menus_missing ) : ?><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Review navigation menus', 'timonconfigs' ); ?></a><?php endif; ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'timonconfigs_setup_notice' );
