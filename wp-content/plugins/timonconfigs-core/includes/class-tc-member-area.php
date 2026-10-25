<?php
/** Owner-only member presentation; Discord and downloads remain separate services. @package TimonConfigs_Core */
defined( 'ABSPATH' ) || exit;

final class TC_Member_Area {
	private const SHORTCODES = array( 'timonconfigs_member_area', 'timonconfigs_member_downloads' );
	private const PAGES = array( 'account' => array( 'Member Profile', 'timonconfigs_member_area' ), 'my-downloads' => array( 'My Downloads', 'timonconfigs_member_downloads' ) );

	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_shortcode( 'timonconfigs_member_area', static fn(): string => self::render( false ) );
		add_shortcode( 'timonconfigs_member_downloads', static fn(): string => self::render( true ) );
		add_action( 'template_redirect', array( __CLASS__, 'protect_response' ), 0 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 100 );
		add_filter( 'wpseo_robots_array', array( __CLASS__, 'provider_robots' ), 100 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'provider_robots' ), 100 );
		add_filter( 'aioseo_robots_meta', array( __CLASS__, 'aioseo_robots' ), 100 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_query' ), 20, 2 );
		add_filter( 'wpseo_sitemap_entry', array( __CLASS__, 'sitemap_entry' ), 20, 3 );
		add_filter( 'rank_math/sitemap/entry', array( __CLASS__, 'sitemap_entry' ), 20, 3 );
		add_action( 'save_post_page', array( __CLASS__, 'mark_member_page' ), 40 );
	}

	/** Updating an already active plugin does not run its activation installer. */
	public static function setup_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() ) { return; }
		foreach ( array_keys( self::PAGES ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page instanceof WP_Post || 'publish' !== get_post_status( $page ) ) {
				echo '<div class="notice notice-warning"><p>Member pages need setup or publication. Run <a href="' . esc_url( admin_url( 'tools.php?page=timonconfigs-setup' ) ) . '">Tools → TimonConfigs setup</a>, then review the Member Profile and My Downloads pages. Existing content and publication states are preserved.</p></div>';
				return;
			}
		}
	}

	/** Explicit provisioning only; never overwrites authored content or publication status. */
	public static function install(): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'manage_options' ) ) { return; }
		foreach ( self::PAGES as $slug => $definition ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page instanceof WP_Post ) {
				$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $definition[0], 'post_content' => '<!-- wp:shortcode -->[' . $definition[1] . ']<!-- /wp:shortcode -->' ), true );
				if ( is_wp_error( $id ) ) { continue; }
			} else {
				$id = $page->ID;
				if ( '' === trim( $page->post_content ) ) { wp_update_post( array( 'ID' => $id, 'post_content' => '<!-- wp:shortcode -->[' . $definition[1] . ']<!-- /wp:shortcode -->' ) ); }
			}
			update_post_meta( $id, '_tc_member_page', '1' );
			update_post_meta( $id, 'tc_noindex', true );
		}
	}

	private static function is_member_post( mixed $post ): bool {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) { return false; }
		if ( isset( self::PAGES[ $post->post_name ] ) || get_post_meta( $post->ID, '_tc_member_page', true ) ) { return true; }
		foreach ( self::SHORTCODES as $shortcode ) { if ( has_shortcode( $post->post_content, $shortcode ) ) { return true; } }
		return false;
	}

	public static function is_member_request(): bool { return is_singular( 'page' ) && self::is_member_post( get_queried_object() ); }

	public static function mark_member_page( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( self::is_member_post( get_post( $post_id ) ) ) { update_post_meta( $post_id, '_tc_member_page', '1' ); update_post_meta( $post_id, 'tc_noindex', true ); }
	}

	public static function protect_response(): void {
		if ( ! self::is_member_request() ) { return; }
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
			header( 'Referrer-Policy: same-origin', true );
		}
	}

	public static function robots( array $robots ): array {
		if ( self::is_member_request() ) { unset( $robots['index'], $robots['follow'] ); $robots['noindex'] = true; $robots['nofollow'] = true; $robots['noarchive'] = true; }
		return $robots;
	}

	public static function provider_robots( array $robots ): array {
		if ( self::is_member_request() ) { $robots['index'] = 'noindex'; $robots['follow'] = 'nofollow'; $robots['archive'] = 'noarchive'; }
		return $robots;
	}

	public static function aioseo_robots( array $robots ): array {
		if ( self::is_member_request() ) { $robots['noindex'] = 'noindex'; $robots['nofollow'] = 'nofollow'; $robots['noarchive'] = 'noarchive'; }
		return $robots;
	}

	public static function sitemap_query( array $args, string $post_type ): array {
		if ( 'page' !== $post_type ) { return $args; }
		$ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_tc_member_page', 'meta_value' => '1', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true ) );
		foreach ( array_keys( self::PAGES ) as $slug ) { $page = get_page_by_path( $slug, OBJECT, 'page' ); if ( $page ) { $ids[] = $page->ID; } }
		$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), array_map( 'intval', $ids ) ) ) );
		return $args;
	}

	public static function sitemap_entry( mixed $entry, mixed $type, mixed $object ): mixed { return self::is_member_post( $object ) ? false : $entry; }

	private static function status(): array {
		if ( class_exists( 'TC_Discord' ) && is_callable( array( 'TC_Discord', 'status' ) ) ) { return TC_Discord::status( get_current_user_id() ); }
		return array( 'state' => 'unverified', 'checked_at' => 0, 'expires_at' => 0, 'display_name' => '', 'avatar' => '', 'discord_id' => '', 'error' => '' );
	}

	private static function ready(): bool { return class_exists( 'TC_Discord' ) && is_callable( array( 'TC_Discord', 'is_ready' ) ) && TC_Discord::is_ready(); }
	private static function account_url(): string { return home_url( '/account/' ); }
	private static function display_price( string $plan ): string {
		if ( 'free' === $plan ) { return '€0'; }
		if ( is_callable( array( 'TC_Billing', 'display_price' ) ) ) { return TC_Billing::display_price( $plan ); }
		return array( 'monthly' => '€29.99', 'lifetime' => '€80' )[ $plan ] ?? '';
	}

	private static function navigation( bool $downloads ): void {
		?>
		<nav class="tc-member-nav" aria-label="Member navigation">
			<a href="<?php echo esc_url( self::account_url() ); ?>" <?php if ( ! $downloads ) : ?>aria-current="page"<?php endif; ?>>Dashboard</a>
			<a href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Configs</a>
			<?php if ( class_exists( 'TC_Owner_Editor' ) && true === TC_Discord::can_manage_configs_local() ) : ?><a href="<?php echo esc_url( TC_Owner_Editor::url() ); ?>">Manage Configs</a><?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Browse Memberships</a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Support</a>
			<?php if ( is_user_logged_in() && class_exists( 'TC_Discord' ) && is_callable( array( 'TC_Discord', 'logout_url' ) ) ) : ?><a href="<?php echo esc_url( TC_Discord::logout_url() ); ?>">Logout</a><?php endif; ?>
		</nav>
		<?php
	}

	/** Decorative icons are optional when the plugin is used with another theme. */
	private static function icon( string $name ): string {
		return function_exists( 'timonconfigs_icon' ) ? timonconfigs_icon( $name ) : '';
	}

	private static function login_card(): void {
		?>
		<div class="tc-member-login">
			<section class="tc-member-login__card" aria-labelledby="tc-member-login-heading">
				<p class="tc-eyebrow">PRIVATE MEMBER AREA</p>
				<div class="tc-member-login__brand"><?php
					if ( locate_template( 'template-parts/brand.php' ) ) { get_template_part( 'template-parts/brand' ); }
					else { echo esc_html( get_bloginfo( 'name' ) ); }
				?></div>
				<h2 id="tc-member-login-heading">Your setup. Your library.</h2>
				<p>Sign in to explore your configs, access protected releases and manage your member profile.</p>
				<div class="tc-member-login__action">
					<?php if ( self::ready() ) : ?><a class="tc-button" href="<?php echo esc_url( TC_Discord::login_url() ); ?>"><?php echo self::icon( 'discord' ); ?>Continue with Discord</a>
					<?php else : ?><button class="tc-button" disabled aria-describedby="tc-discord-unavailable"><?php echo self::icon( 'discord' ); ?>Continue with Discord</button><?php endif; ?>
				</div>
				<?php if ( ! self::ready() ) : ?><p id="tc-discord-unavailable" class="tc-member-note" role="status">Discord sign-in is not available yet. You can still browse configs and membership options.</p><?php endif; ?>
				<p class="tc-member-login__verification"><?php echo self::icon( 'shield' ); ?><span>Access is verified through your TimonConfigs Discord roles.</span></p>
			</section>
			<nav class="tc-member-login__links" aria-label="Member navigation"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">← Back to homepage</a><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Browse Memberships</a></nav>
			<p class="tc-member-login__legal"><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a><span aria-hidden="true"> · </span><a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>">Terms of Service</a></p>
		</div>
		<?php
	}

	private static function quick_actions(): void {
		$actions = array(
			array( 'gamepad', 'View Configs', 'Find your next setup by game and device.', home_url( '/configs/' ) ),
			array( 'users', 'Your Profile', 'Check your Discord connection and access.', self::account_url() . '#tc-member-profile' ),
			array( 'discord', 'Support', 'Get help with your setup or membership.', home_url( '/contact/' ) ),
		);
		if ( class_exists( 'TC_Owner_Editor' ) && true === TC_Discord::can_manage_configs_local() ) { $actions[] = array( 'settings', 'Manage Configs', 'Create and manage configuration releases.', TC_Owner_Editor::url() ); }
		?>
		<section class="tc-member-quick" aria-labelledby="tc-member-quick-heading">
			<div class="tc-member-section-heading"><h2 id="tc-member-quick-heading">Quick actions</h2></div>
			<div class="tc-member-quick__grid"><?php foreach ( $actions as $action ) : ?>
				<a class="tc-member-quick__card" href="<?php echo esc_url( $action[3] ); ?>"><span class="tc-member-quick__icon"><?php echo self::icon( $action[0] ); ?></span><span><strong><?php echo esc_html( $action[1] ); ?></strong><span><?php echo esc_html( $action[2] ); ?></span></span><span class="tc-member-quick__arrow"><?php echo self::icon( 'arrow' ); ?></span></a>
			<?php endforeach; ?></div>
		</section>
		<?php
	}

	public static function render( bool $downloads = false ): string {
		if ( isset( $_GET['tc_manage_configs'] ) && class_exists( 'TC_Owner_Editor' ) ) { return TC_Owner_Editor::render(); }
		// Public/editor REST content never receives owner details from shortcode rendering.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return '<p><a href="' . esc_url( self::account_url() ) . '">Open your private member area</a> to view account information.</p>'; }
		ob_start();
		$art = (string) get_theme_mod( 'timonconfigs_member_hero_image', '' );
		?>
		<div class="tc-member-area" data-member-area>
			<?php if ( ! is_user_logged_in() ) : ?>
				<?php self::login_card(); ?>
			<?php else : $status = self::status(); $state = (string) ( $status['state'] ?? 'unverified' ); $user = wp_get_current_user(); $name = (string) ( $status['display_name'] ?? '' ) ?: $user->display_name; $labels = array( 'unverified' => 'Verification required', 'free' => 'Free Member', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime', 'unavailable' => 'Verification unavailable' ); $owner_tools = class_exists( 'TC_Owner_Editor' ) && true === TC_Discord::can_manage_configs_local(); $owner_only = $owner_tools && 'unverified' === $state; $display_state = $owner_only ? 'owner' : $state; ?>
				<?php self::navigation( $downloads ); ?>
				<section id="tc-member-profile" class="tc-member-profile" aria-labelledby="tc-profile-heading" tabindex="-1">
					<?php if ( $art ) : ?><img class="tc-member-profile__art" src="<?php echo esc_url( $art ); ?>" alt="" width="1200" height="420" decoding="async"><?php endif; ?>
					<div class="tc-member-identity"><?php if ( ! empty( $status['avatar'] ) ) : ?><img class="tc-member-avatar" src="<?php echo esc_url( $status['avatar'], array( 'https' ) ); ?>" alt="" width="80" height="80" referrerpolicy="no-referrer"><?php else : ?><span class="tc-member-avatar tc-member-avatar--placeholder" aria-hidden="true"><?php echo esc_html( strtoupper( function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 ) ) ); ?></span><?php endif; ?><div><p class="tc-eyebrow">WELCOME BACK</p><h2 id="tc-profile-heading"><?php echo esc_html( $name ); ?></h2><p class="tc-member-profile__intro">Your home for configs, updates and member access.</p><span class="tc-member-state" data-state="<?php echo esc_attr( $display_state ); ?>"><?php echo self::icon( $owner_tools || in_array( $state, array( 'free', 'monthly', 'lifetime' ), true ) ? 'shield' : 'users' ); ?><?php echo esc_html( $owner_only ? 'Owner tools enabled' : ( $labels[ $state ] ?? 'Verification required' ) ); ?></span><?php if ( ! empty( $status['badges'] ) ) : ?><div class="tc-member-badges"><?php foreach ( $status['badges'] as $key => $label ) : ?><?php if ( $key !== $display_state ) : ?><span class="tc-member-state" data-state="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></span><?php endif; ?><?php endforeach; ?></div><?php endif; ?></div></div>
					<dl class="tc-member-details"><?php if ( ! empty( $status['discord_id'] ) ) : ?><div><dt>Discord User ID</dt><dd><?php echo esc_html( (string) $status['discord_id'] ); ?></dd></div><?php endif; ?><div><dt>Last Checked</dt><dd><?php echo ! empty( $status['checked_at'] ) ? esc_html( wp_date( 'j M Y, H:i T', (int) $status['checked_at'] ) ) : 'Not checked yet'; ?></dd></div><div><dt>Access verification</dt><dd><?php echo $owner_only ? 'Owner account approved; downloads need membership' : ( in_array( $state, array( 'free', 'monthly', 'lifetime' ), true ) ? 'Verified with Discord' : 'Required before protected downloads' ); ?></dd></div></dl>
					<div class="tc-actions"><?php if ( $owner_tools ) : ?><a class="tc-button" href="<?php echo esc_url( TC_Owner_Editor::url() ); ?>">Manage Configs</a><?php endif; ?><?php if ( self::ready() ) : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( TC_Discord::refresh_url() ); ?>">Refresh Roles</a><?php if ( ! $owner_tools && ( empty( $status['discord_id'] ) || in_array( $state, array( 'unverified', 'unavailable' ), true ) ) ) : ?><a class="tc-button" href="<?php echo esc_url( TC_Discord::login_url() ); ?>"><?php echo empty( $status['discord_id'] ) ? 'Continue with Discord' : 'Reconnect Discord'; ?></a><?php endif; ?><?php endif; ?><a class="tc-text-link" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Browse Memberships</a></div>
					<?php if ( ! empty( $status['error'] ) || 'unavailable' === $state ) : ?><p class="tc-member-message" role="status"><?php echo esc_html( ! empty( $status['error'] ) ? (string) $status['error'] : 'Membership verification is temporarily unavailable. Protected files remain locked. Please try again shortly.' ); ?></p><?php elseif ( $owner_tools ) : ?><p class="tc-member-note">You can manage configs. Downloads follow your Free or VIP membership.</p><?php elseif ( 'unverified' === $state ) : ?><p class="tc-member-note"><?php if ( ! self::ready() ) { echo 'Discord sign-in is not available yet. '; } ?>Complete verification in the TimonConfigs Discord server using the existing verification flow. Access is checked again before a protected file is served.</p><?php endif; ?>
				</section>
				<?php if ( ! $downloads ) { self::quick_actions(); } ?>
				<?php if ( $downloads ) : ?><p class="tc-member-note">Downloads are available on each <a href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">configuration page</a>. Your download history remains private.</p><?php endif; ?>
				<?php
				$billing_account = is_callable( array( 'TC_Billing', 'render_account' ) ) ? TC_Billing::render_account( get_current_user_id() ) : '';
				$direct_billing = ( is_callable( array( 'TC_Billing', 'direct_enabled' ) ) && TC_Billing::direct_enabled() ) || '' !== trim( $billing_account );
				self::memberships( $state, $direct_billing );
				echo $billing_account; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Billing owns and escapes its private account markup.
				?>
				<?php if ( ! $direct_billing ) : ?><section class="tc-member-management"><div class="tc-member-section-heading"><h2>Membership support</h2></div><p>Choose your plan on this website. For an earlier purchase, cancellation request or missing access, open a support ticket in our Discord server. Existing purchases and valid membership access are retained.</p><div class="tc-actions"><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Browse Memberships</a><a class="tc-text-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Get membership support</a></div></section><?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function memberships( string $state, bool $direct_billing = false ): void {
		$plans = array(
			'free' => array( 'Free', 'Selected protected releases after Discord verification.', home_url( '/configs/?tier=free' ) ),
			'monthly' => array( 'VIP Monthly', 'Recurring access to the covered premium library.', home_url( '/vip-monthly/' ) ),
			'lifetime' => array( 'VIP Lifetime', 'One-time access under the purchased Lifetime license.', home_url( '/vip-lifetime/' ) ),
		);
		?>
		<section class="tc-member-memberships" aria-labelledby="tc-member-memberships"><div class="tc-member-section-heading"><h2 id="tc-member-memberships">Your memberships</h2><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Browse Memberships</a></div><div class="tc-member-plan-grid">
		<?php foreach ( $plans as $plan => $values ) : $included = $plan === $state || ( 'free' === $plan && in_array( $state, array( 'monthly', 'lifetime' ), true ) ) || ( 'monthly' === $plan && 'lifetime' === $state ); ?>
			<article class="tc-member-plan<?php echo $included ? ' is-included' : ''; ?>"><span class="tc-member-plan__status"><?php echo esc_html( $plan === $state ? ( 'lifetime' === $plan ? 'Current license' : 'Current membership' ) : ( $included ? 'Included access' : ( 'free' === $plan ? 'Verification required' : 'Available membership' ) ) ); ?></span><h3><?php echo esc_html( get_theme_mod( 'timonconfigs_plan_' . $plan . '_name', $values[0] ) ); ?></h3><p class="tc-member-plan__price"><?php echo esc_html( self::display_price( $plan ) ); ?><small><?php echo 'monthly' === $plan ? '/ month' : ( 'lifetime' === $plan ? 'one time' : 'Free access' ); ?></small></p><p><?php echo esc_html( $values[1] ); ?></p><a class="tc-text-link" href="<?php echo esc_url( $values[2] ); ?>"><?php echo $included ? 'View included access' : ( 'free' === $plan ? 'Explore Free configs' : 'View membership' ); ?></a></article>
		<?php endforeach; ?>
		</div><p class="tc-member-note"><?php echo $direct_billing ? 'Membership badges reflect your current Discord roles. Review payment, renewal, and access sync status below.' : 'Membership status comes from your current Discord roles. Purchases start on this website; use a Discord support ticket for billing or access questions.'; ?></p></section>
		<?php
	}

	private static function recent(): void {
		$entries = class_exists( 'TC_Downloads' ) && is_callable( array( 'TC_Downloads', 'recent' ) ) ? TC_Downloads::recent( get_current_user_id(), 10 ) : array();
		?>
		<section class="tc-member-recent"><div class="tc-member-section-heading"><h2>Recent downloads</h2><a href="<?php echo esc_url( home_url( '/my-downloads/' ) ); ?>">Open My Downloads</a></div>
		<?php if ( ! $entries ) : ?><p class="tc-member-empty">No protected downloads have been recorded for your account yet.</p><?php else : ?><ul class="tc-member-history"><?php foreach ( $entries as $entry ) : ?><li><div><strong><?php echo esc_html( $entry['title'] ?? 'Configuration' ); ?></strong><span><?php echo esc_html( $entry['file_name'] ?? 'Release file' ); ?></span></div><time><?php echo esc_html( ! empty( $entry['downloaded_at'] ) ? wp_date( 'j M Y, H:i', is_numeric( $entry['downloaded_at'] ) ? (int) $entry['downloaded_at'] : ( strtotime( $entry['downloaded_at'] ) ?: 0 ) ) : '' ); ?></time></li><?php endforeach; ?></ul><?php endif; ?></section>
		<?php
	}

	private static function library( string $state ): void {
		?>
		<section class="tc-member-library"><div class="tc-member-section-heading"><h2>Your protected library</h2><a href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Explore all configs</a></div>
		<?php
		if ( ! in_array( $state, array( 'free', 'monthly', 'lifetime' ), true ) || ! class_exists( 'TC_Discord' ) || ! class_exists( 'TC_Private_Files' ) ) { echo '<p class="tc-member-empty">Verify your Discord membership to view the protected releases available to your account.</p></section>'; return; }
		$tiers = 'lifetime' === $state ? array( 'free', 'monthly', 'lifetime' ) : ( 'monthly' === $state ? array( 'free', 'monthly' ) : array( 'free' ) );
		$raw_page = isset( $_GET['library_page'] ) && is_scalar( $_GET['library_page'] ) ? absint( $_GET['library_page'] ) : 1;
		$paged = max( 1, min( 100000, $raw_page ) );
		$meta = array( 'relation' => 'AND', array( 'relation' => 'OR', array( 'key' => 'tc_tier', 'value' => $tiers, 'compare' => 'IN' ), array( 'key' => 'tc_tier', 'compare' => 'NOT EXISTS' ), array( 'key' => 'tc_tier', 'value' => '' ) ) );
		if ( method_exists( 'TC_Catalog', 'released_meta_query' ) ) { $meta[] = TC_Catalog::released_meta_query(); }
		$query = new WP_Query( array( 'post_type' => 'tc_config', 'post_status' => 'publish', 'posts_per_page' => 9, 'paged' => $paged, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => $meta ) );
		$rendered = 0;
		echo '<div class="tc-member-file-grid">';
		foreach ( $query->posts as $config ) {
			if ( true !== TC_Discord::can_access( $config->ID, get_current_user_id() ) ) { continue; }
			$files = TC_Private_Files::files( $config->ID );
			if ( ! $files ) { continue; }
			$rendered++;
			echo '<article class="tc-member-release"><h3><a href="' . esc_url( get_permalink( $config ) ) . '">' . esc_html( get_the_title( $config ) ) . '</a></h3><p>' . esc_html( wp_trim_words( $config->post_excerpt ?: wp_strip_all_tags( $config->post_content ), 24 ) ) . '</p><ul>';
			foreach ( $files as $file ) {
				echo '<li><div><strong>' . esc_html( $file['name'] ?? 'Release file' ) . '</strong><span>' . esc_html( ! empty( $file['version'] ) ? 'v' . $file['version'] . ' · ' : '' ) . esc_html( size_format( (int) ( $file['size'] ?? 0 ) ) ) . '</span></div>';
				if ( class_exists( 'TC_Downloads' ) && is_callable( array( 'TC_Downloads', 'render_button' ) ) && ! empty( $file['id'] ) ) { TC_Downloads::render_button( $config->ID, (string) $file['id'], 'Download' ); }
				echo '</li>';
			}
			echo '</ul></article>';
		}
		echo '</div>';
		if ( ! $rendered ) { echo '<p class="tc-member-empty">No protected release files match your current membership on this page. Newly approved releases will appear here when published.</p>'; }
		if ( $query->max_num_pages > 1 ) { echo '<nav class="tc-member-pagination" aria-label="Library pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'library_page', '%#%', home_url( '/my-downloads/' ) ), 'format' => '', 'current' => $paged, 'total' => $query->max_num_pages ) ) ) . '</nav>'; }
		echo '</section>';
		self::recent();
	}
}
