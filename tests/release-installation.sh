#!/usr/bin/env bash
# Install the actual ZIPs into a disposable, offline WordPress database/filesystem.
# Existing Compose containers, networks, volumes, and databases are never used.
set -euo pipefail

test_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
theme_zip="$(realpath "${1:-$test_root/artifacts/timonconfigs-theme-0.11.3.zip}")"
core_zip="$(realpath "${2:-$test_root/artifacts/timonconfigs-core-0.9.8.zip}")"
wp_image='wordpress:6.8.3-php8.2-apache'
cli_image='wordpress:cli-php8.2'
db_image='mariadb:11.4'

for image_name in "$wp_image" "$cli_image" "$db_image"; do
  docker image inspect "$image_name" >/dev/null
done
unzip -tqq "$theme_zip"
unzip -tqq "$core_zip"
expected_theme_version="$(unzip -p "$theme_zip" timonconfigs/style.css | awk '$1 == "Version:" { print $2; exit }')"
expected_core_version="$(unzip -p "$core_zip" timonconfigs-core/timonconfigs-core.php | awk '$2 == "Version:" { print $3; exit }')"
test -n "$expected_theme_version"
test -n "$expected_core_version"

test_directory="$(mktemp -d "${TMPDIR:-/tmp}/tc-release-install.XXXXXXXX")"
test_prefix="tc-release-${test_directory##*.}"
test_prefix="${test_prefix,,}"
test_network="$test_prefix-network"
test_wp_volume="$test_prefix-wordpress"
test_db_volume="$test_prefix-database"
test_db_container="$test_prefix-db"
network_created=0
wp_volume_created=0
db_volume_created=0
db_container_created=0

cleanup() {
  local result=$?
  trap - EXIT
  if (( db_container_created )); then docker rm -f "$test_db_container" >/dev/null 2>&1 || true; fi
  if (( wp_volume_created )); then docker volume rm "$test_wp_volume" >/dev/null 2>&1 || true; fi
  if (( db_volume_created )); then docker volume rm "$test_db_volume" >/dev/null 2>&1 || true; fi
  if (( network_created )); then docker network rm "$test_network" >/dev/null 2>&1 || true; fi
  rm -rf -- "$test_directory"
  exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

docker network create --internal "$test_network" >/dev/null
network_created=1
docker volume create "$test_wp_volume" >/dev/null
wp_volume_created=1
docker volume create "$test_db_volume" >/dev/null
db_volume_created=1

# Copy the bundled WordPress source; no download or package registry is needed.
docker run --rm --pull=never --network none \
  --volume "$test_wp_volume:/var/www/html" --entrypoint sh "$wp_image" \
  -c 'cp -a /usr/src/wordpress/. /var/www/html/ && chown -R 33:33 /var/www/html'
docker run --detach --pull=never --name "$test_db_container" --network "$test_network" \
  --volume "$test_db_volume:/var/lib/mysql" \
  --env MARIADB_DATABASE=release_test --env MARIADB_USER=release_test \
  --env MARIADB_PASSWORD=release-database-only --env MARIADB_ROOT_PASSWORD=release-root-only \
  "$db_image" >/dev/null
db_container_created=1
database_ready=0
for attempt in $(seq 1 60); do
  if docker exec "$test_db_container" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
    database_ready=1
    break
  fi
  sleep 1
done
if (( ! database_ready )); then
  printf '%s\n' 'Isolated release database did not become ready.' >&2
  exit 1
fi

wp_cli() {
  docker run --rm -i --pull=never --network "$test_network" --user 33:33 \
    --volume "$test_wp_volume:/var/www/html" \
    --volume "$theme_zip:/packages/theme.zip:ro" \
    --volume "$core_zip:/packages/core.zip:ro" \
    --volume "$test_directory:/release-test:ro" \
    --env "TC_EXPECTED_THEME_VERSION=$expected_theme_version" \
    --env "TC_EXPECTED_CORE_VERSION=$expected_core_version" \
    --workdir /var/www/html --entrypoint wp "$cli_image" "$@"
}

wp_cli config create --dbname=release_test --dbuser=release_test \
  --dbpass=release-database-only --dbhost="$test_db_container:3306" --skip-check --skip-salts \
  --extra-php <<'PHP'
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
PHP
wp_cli core install --url=http://timonconfigs-release.test --title='Disposable release installation' \
  --admin_user=release_admin --admin_password=release-admin-only \
  --admin_email=release@example.test --skip-email
wp_cli plugin install /packages/core.zip --activate
wp_cli theme install /packages/theme.zip --activate
wp_cli rewrite structure '/%postname%/'

cat > "$test_directory/verify.php" <<'PHP'
<?php
defined( 'ABSPATH' ) || exit;
$checks = 0;
$expect = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( 'Fresh ZIP installation: ' . $message ); }
	++$checks;
};
$expect( current_user_can( 'manage_options' ), 'explicit provisioning runs as the disposable administrator' );
$expect( is_plugin_active( 'timonconfigs-core/timonconfigs-core.php' ), 'the packaged plugin is activated' );
$expect( 'timonconfigs' === get_stylesheet(), 'the packaged theme is activated' );
$expect( getenv( 'TC_EXPECTED_THEME_VERSION' ) === wp_get_theme()->get( 'Version' ), 'installed theme version matches its ZIP' );
$expect( getenv( 'TC_EXPECTED_CORE_VERSION' ) === TIMONCONFIGS_CORE_VERSION, 'installed plugin version matches its ZIP' );
foreach ( array( 'Secrets', 'Store', 'Stripe', 'PayPal', 'Discord', 'UI' ) as $billing_class ) {
	$expect( class_exists( 'TC_Billing_' . $billing_class ), 'packaged billing class is available: ' . $billing_class );
}
$expect( class_exists( 'TC_Billing' ), 'packaged billing controller is available' );
$billing_config = TC_Billing::config();
$expect( 'disabled' === $billing_config['routing'] && 'test' === $billing_config['mode'] && ! $billing_config['role_sync_enabled'] && ! $billing_config['paypal_enabled'], 'fresh billing disables new checkout, role writes and PayPal' );
$expect( ! TC_Billing::direct_enabled() && ! TC_Billing::mode_allowed( 'live' ), 'installing ZIPs cannot activate direct sales or Live mode' );
$expect( ! TC_Billing::provider_ready( 'stripe', 'test' ) && ! TC_Billing::provider_ready( 'paypal', 'test' ), 'fresh packages contain no payment credentials' );
$expect( '€29.99' === TC_Billing::display_price( 'monthly' ) && '€80' === TC_Billing::display_price( 'lifetime' ), 'disabled checkout still uses the central EUR catalogue' );
$disabled_checkout = TC_Billing_UI::checkout( 'monthly' );
$expect( ! str_contains( $disabled_checkout, '<form' ) && ! str_contains( strtolower( $disabled_checkout ), 'upgrade.chat' ), 'fresh checkout contains no payment form or retired provider instruction' );
global $wpdb;
foreach ( array( 'orders', 'entitlements', 'events', 'sync_jobs', 'owned_roles', 'leases' ) as $billing_table ) {
	$installed_table = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $wpdb->prefix . 'tc_billing_' . $billing_table ), ARRAY_A );
	$expect( is_array( $installed_table ) && 'innodb' === strtolower( $installed_table['Engine'] ), 'packaged billing creates a transactional InnoDB table: ' . $billing_table );
}
$expect( true === WP_HTTP_BLOCK_EXTERNAL, 'WordPress external HTTP requests are disabled' );
$expect( class_exists( 'TC_Owner_Editor' ) && class_exists( 'TC_Config_Content' ), 'Owner and protected editorial modules are packaged and loaded' );
$expect( current_user_can( 'edit_tc_configs' ) && current_user_can( 'publish_tc_configs' ), 'administrator retains explicit config editor fallback' );
$expect( 'edit_tc_configs' === get_post_type_object( 'tc_config' )->cap->edit_posts, 'config permissions use their own capability namespace' );
$expect( ! TC_Owner_Editor::in_authorized_context(), 'fresh request has no temporary Owner capabilities' );
$expect( false === TC_Discord::can_manage_configs(), 'administrator has no implicit Discord Owner access' );
$expect( in_array( 'cfg', TC_Private_Files::extensions(), true ) && in_array( 'ini', TC_Private_Files::extensions(), true ), 'safe configurable support formats are packaged' );
$expect( ! get_registered_meta_keys( 'post', 'tc_config' )['_tc_values_content']['show_in_rest'], 'protected Values do not enter public REST' );
$expect( true === TC_Config_Content::validate_fields( array( 'tc_guide_content' => 'Test only' ) ), 'content-only validation uses a safe restricted default' );

$first = TC_Site::install();
$expect( ! is_wp_error( $first ) && 12 === count( $first ), 'twelve canonical editorial page IDs are provisioned' );
$expect( class_exists( 'TC_Legal' ) && TC_Legal::ready(), 'the explicitly supplied seller profile and bundled legal documents are available' );
TC_Site::maybe_publish_owner_legal_release();
foreach ( TC_Legal::pages() as $legal_slug => $legal_title ) {
	$legal_page = get_page_by_path( $legal_slug, OBJECT, 'page' );
	$expect( $legal_page instanceof WP_Post && 'publish' === $legal_page->post_status && ! str_contains( $legal_page->post_content, 'Draft policy' ) && ! str_contains( $legal_page->post_content, '{{' ), 'owner-authorized legal release is published without placeholders: ' . $legal_slug );
	$expect( str_contains( $legal_page->post_content, 'TimonConfigs, ul. Dąbrowica 21, 05-326 Poświetne, Poland' ) && str_contains( $legal_page->post_content, 'mailto:contact@timonconfigs.com' ), 'the supplied postal address and direct email are published: ' . $legal_slug );
}
foreach ( array( 'account', 'my-downloads' ) as $member_slug ) {
	$member_page = get_page_by_path( $member_slug, OBJECT, 'page' );
	$expect( $member_page instanceof WP_Post && has_shortcode( $member_page->post_content, 'account' === $member_slug ? 'timonconfigs_member_area' : 'timonconfigs_member_downloads' ) && (bool) get_post_meta( $member_page->ID, 'tc_noindex', true ), 'private member page is provisioned and noindex: ' . $member_slug );
}
$expect( ! TC_Discord::is_ready(), 'OAuth remains unavailable without explicit credentials and staging verification' );
$installer_id = get_current_user_id();
wp_set_current_user( 0 );
$login_markup = TC_Member_Area::render();
wp_set_current_user( $installer_id );
$expect( str_contains( $login_markup, 'tc-member-login__card' ) && str_contains( $login_markup, 'Continue with Discord' ), 'packaged member login card renders' );
$expect( str_contains( $login_markup, 'disabled aria-describedby="tc-discord-unavailable"' ), 'fresh installation keeps login unavailable until configured' );
$expect( is_wp_error( TC_Private_Files::root() ), 'fresh host cannot silently default private uploads to a public directory' );
foreach ( $first as $slug => $id ) {
	$page = get_post( $id );
	$expect( $page instanceof WP_Post && 'page' === $page->post_type && $slug === $page->post_name, 'required page exists: ' . $slug );
	$expect( '' !== trim( $page->post_content ), 'required page has editable content: ' . $slug );
}
$expect( 7 === count( get_terms( array( 'taxonomy' => 'tc_game', 'hide_empty' => false ) ) ), 'seven games are seeded' );
$expect( 2 === count( get_terms( array( 'taxonomy' => 'tc_device', 'hide_empty' => false ) ) ), 'two device families are seeded' );
$expect( 3 === count( get_terms( array( 'taxonomy' => 'tc_platform', 'hide_empty' => false ) ) ), 'three platforms are seeded' );
foreach ( array( 'tc_config', 'tc_review', 'tc_faq', 'tc_video' ) as $post_type ) {
	$type = get_post_type_object( $post_type );
	$expect( $type && $type->show_in_rest && post_type_supports( $post_type, 'editor' ), 'editorial post type works: ' . $post_type );
}
$expect( WP_Block_Type_Registry::get_instance()->is_registered( 'timonconfigs/section' ), 'native TimonConfigs block is registered' );
$home_content = get_post_field( 'post_content', $first['home'] );
$home_blocks = array_values( array_filter( parse_blocks( $home_content ), static fn( array $block ): bool => 'timonconfigs/section' === $block['blockName'] ) );
$expect( 10 === count( $home_blocks ), 'the real installed Home contains ten native sections' );
$home_render = do_blocks( $home_content );
$expect( str_contains( $home_render, 'tc-hero' ) && str_contains( $home_render, 'tc-pricing' ), 'installed Home blocks render the storefront' );
$flagships = get_posts( array( 'post_type' => 'tc_config', 'post_status' => array_values( get_post_stati() ), 'meta_key' => '_tc_seeded_flagship', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
$expect( 3 === count( $flagships ), 'three flagship draft structures are installed' );
foreach ( $flagships as $draft ) {
	$expect( 'draft' === $draft->post_status && 'coming-soon' === get_post_meta( $draft->ID, 'tc_release_status', true ), 'flagship is not a public release' );
	$expect( '' === get_post_meta( $draft->ID, 'tc_access_url', true ), 'flagship has no invented download URL' );
}
$expect( 0 === (int) wp_count_posts( 'tc_config' )->publish, 'fresh installation publishes no configurations' );
$expect( array() === get_option( 'timonconfigs_checkout_urls', array() ), 'no product checkout settings are invented' );
$expect( '' === TC_Checkout::get_destination( 'monthly' ) && '' === TC_Checkout::get_destination( 'lifetime' ), 'both external checkout destinations are retired' );
$expect( home_url( '/checkout/monthly/' ) === TC_Checkout::get_checkout_url( 'monthly' ) && home_url( '/checkout/lifetime/' ) === TC_Checkout::get_checkout_url( 'lifetime' ), 'both local checkout routes are available for Ticket Tool links' );
$retired_urls = array( 'monthly' => 'https://upgrade.chat/release-old-monthly', 'lifetime' => 'https://upgrade.chat/release-old-lifetime' );
update_option( 'timonconfigs_checkout_urls', $retired_urls, false );
$expect( '' === TC_Checkout::get_destination( 'monthly' ) && '' === TC_Checkout::get_destination( 'lifetime' ) && $retired_urls === get_option( 'timonconfigs_checkout_urls' ), 'old checkout URLs are preserved but cannot redirect a new purchase' );

// Keep an authored change and compare actual records across a repeated installation.
$authored = get_post_field( 'post_content', $first['pricing'] ) . "\n<!-- wp:paragraph --><p>Disposable authored preservation fixture.</p><!-- /wp:paragraph -->";
$updated = wp_update_post( array( 'ID' => $first['pricing'], 'post_content' => $authored ), true );
$expect( ! is_wp_error( $updated ), 'the preservation fixture is saved' );
$snapshot = static function ( array $ids ): array {
	return array_map( static function ( int $id ): array {
		$page = get_post( $id );
		return array( $page->ID, $page->post_title, $page->post_content, $page->post_status );
	}, $ids );
};
$before = $snapshot( $first );
$menu_locations = get_nav_menu_locations();
$second = TC_Site::install();
$expect( $first === $second, 'reinstallation preserves all page IDs' );
$expect( $before === $snapshot( $second ), 'reinstallation preserves all content, titles, and publication states' );
$expect( $authored === get_post_field( 'post_content', $first['pricing'] ), 'authored content survives reinstallation' );
$expect( $menu_locations === get_nav_menu_locations(), 'reinstallation preserves assigned menu locations' );
$expect( 3 === count( get_posts( array( 'post_type' => 'tc_config', 'post_status' => 'draft', 'meta_key' => '_tc_seeded_flagship', 'posts_per_page' => -1 ) ) ), 'reinstallation does not duplicate draft configs' );
$expect( $retired_urls === get_option( 'timonconfigs_checkout_urls', array() ), 'reinstallation preserves retired checkout history without reactivating it' );
$expect( '' === TC_Checkout::get_destination( 'monthly' ) && '' === TC_Checkout::get_destination( 'lifetime' ), 'repeat installation cannot reactivate a stored external destination' );
WP_CLI::success( sprintf( 'Fresh ZIP installation: %d assertions passed; WordPress %s; theme %s; Core %s; 12 required pages, 3 published legal documents, 7 games, 2 devices, 3 platforms, 3 unpublished draft configs, 10 Home blocks. Privacy Policy published by the authorized legal action as %s.', $checks, get_bloginfo( 'version' ), wp_get_theme()->get( 'Version' ), TIMONCONFIGS_CORE_VERSION, get_post_status( $first['privacy-policy'] ) ) );
PHP
chmod 755 "$test_directory"
chmod 644 "$test_directory/verify.php"
wp_cli eval-file /release-test/verify.php --user=release_admin
printf '%s\n' 'Isolated ZIP installation passed. Temporary containers, volumes, and internal network will now be removed.'
