<?php
/**
 * Explicit, idempotent site provisioning. Never runs writes on public requests.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Site {
	public const DISCORD_INVITE = 'https://discord.gg/RyvzSqrF6';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_setup_page' ) );
		add_action( 'admin_post_tc_install_site', array( __CLASS__, 'handle_install' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate_checkout_copy' ) );
		add_action( 'admin_post_tc_update_checkout_copy', array( __CLASS__, 'handle_checkout_copy' ) );
	}

	/**
	 * Required editorial pages. Archive/taxonomy routes belong to TC_Catalog.
	 *
	 * @return array<string,string>
	 */
	public static function pages(): array {
		return array(
			'home'                   => 'Home',
			'pricing'                => 'Pricing',
			'games'                  => 'Games',
			'reviews'                => 'Reviews',
			'faq'                    => 'FAQ',
			'contact'                => 'Contact',
			'video-showcase'         => 'Video Showcase',
			'vip-monthly'            => 'VIP Monthly',
			'vip-lifetime'           => 'VIP Lifetime',
			'terms-of-service'       => 'Terms of Service',
			'privacy-policy'         => 'Privacy Policy',
			'refund-policy'          => 'Refund Policy',
			'cookie-policy'          => 'Cookie Policy',
			'lifetime-license-terms' => 'Lifetime License Terms',
		);
	}

	/** @return array<string,array<string,string>> */
	public static function legal_sections(): array {
		return array(
			'terms-of-service' => array(
				'Agreement and eligibility' => 'These draft terms describe TimonConfigs digital configuration access. The operating entity and final terms must be reviewed by the owner before production use. Users must be legally able to contract and comply with applicable law, game rules, platform rules, and community rules.',
				'Digital materials and personal licenses' => 'Configurations, setup guides, and related materials are licensed for personal use under the scope disclosed at purchase. Redistribution, resale, public file sharing, and account sharing require permission and may lead to access restrictions.',
				'Free and VIP access' => 'Free includes selected materials. VIP Monthly is a recurring subscription with covered access while active. VIP Lifetime is a one-time license whose included materials and updates follow the terms supplied before purchase. A Lifetime license does not promise every future product or uninterrupted operation.',
				'Checkout and Discord roles' => 'Start purchases on TimonConfigs and complete payment with an available Stripe or PayPal hosted checkout. A Discord support ticket may link to this website; a ticket or browser return is not evidence of payment. Use the same Discord account throughout checkout and access verification.',
				'Compatibility and changes' => 'Only compatibility expressly listed for a configuration is confirmed. Game updates, firmware, platform restrictions, and personal settings may affect compatibility. No particular competitive outcome is guaranteed.',
				'Cancellation, access restrictions, and rights' => 'Manage supported recurring subscriptions from your TimonConfigs account. If an earlier purchase is not listed, contact support and keep the original purchase records. Payment failures, chargebacks, fraud, redistribution, or material misuse may affect access under the purchase terms and applicable law. Nothing here excludes non-waivable consumer rights.',
				'Questions and disputes' => 'Contact TimonConfigs through the Contact page for account, policy, or purchase questions. Final production terms must identify the seller, jurisdiction, and mandatory consumer information.',
			),
			'privacy-policy' => array(
				'Policy status and controller' => 'This draft must be reviewed for the actual operating entity, processors, retention periods, and markets before production. The final policy must identify the data controller and a working contact channel.',
				'Information used by the service' => 'Website operation may involve account identifiers, support correspondence, security events, cookies, and technical request information. If Discord sign-in is enabled, its stated permissions may provide Discord user ID, public profile details, and current membership information.',
				'Purpose and legal basis' => 'Personal information is used only for documented purposes such as website operation, support, account access, fraud prevention, legal obligations, and service security. The legal basis for each purpose depends on the final operating model and applicable law.',
				'Payments and third parties' => 'Stripe and PayPal handle payment details for supported website checkout. TimonConfigs does not request full card numbers or PayPal passwords. Hosting, Discord, support services, and other processors may receive information necessary for their roles. Payment availability and the applicable processor are disclosed before purchase.',
				'Cookies and embedded content' => 'Essential WordPress or security cookies may be used where required. Optional analytics and marketing require the applicable consent controls. YouTube content is loaded only after the visitor chooses to play an embedded video; its provider may then process request information.',
				'Retention, security, and your choices' => 'Retain personal information only as long as necessary for its documented purpose, legal duties, and dispute handling. Depending on location, individuals may have rights to access, correction, deletion, restriction, objection, portability, and complaint to a supervisory authority.',
				'Privacy requests' => 'Use the Contact page to request help with personal information. Identity may need to be verified before responding. Never send passwords or full payment credentials in support correspondence.',
			),
			'refund-policy' => array(
				'Scope' => 'This draft policy covers TimonConfigs digital licenses and VIP memberships purchased through this website. It requires owner review before production and does not override statutory consumer rights or the terms of an existing purchase.',
				'Digital delivery and withdrawal rights' => 'Refund or withdrawal eligibility depends on applicable consumer law, the checkout terms accepted, whether digital access has been supplied, and any valid consent regarding immediate performance. Do not assume that all digital purchases are automatically non-refundable.',
				'Problems with an order' => 'Contact support promptly for duplicate or incorrect charges, access delivered to the wrong account, a missing role, or a material mismatch between the description and supplied content. Provide a transaction reference without exposing full payment credentials.',
				'Monthly cancellation' => 'Use Cancel Auto-Renewal in your TimonConfigs account for supported VIP Monthly subscriptions. Cancellation stops future renewals while verified prepaid access remains until its end date. It does not by itself establish refund eligibility for an elapsed billing period. Contact support if an earlier subscription is not listed.',
				'Review and outcome' => 'Requests are assessed against the purchase terms, access records, the issue reported, and applicable law. Approved refunds use the original payment route where possible; payment-provider processing times vary.',
			),
			'cookie-policy' => array(
				'Policy status' => 'This draft must be reviewed against the cookies and storage actually deployed on the production site. It does not claim that a completed consent or analytics system is present.',
				'Essential storage' => 'WordPress may use essential authentication, security, and administration cookies. Public visitors can browse the catalogue and pricing without signing in. Essential storage is used only where needed for the requested service.',
				'Video providers' => 'MP4 showcase files are requested when a visitor starts playback. A YouTube player is created only after a visitor chooses to load that provider. YouTube may then receive technical information and use its own storage under its policies.',
				'Optional analytics and marketing' => 'Optional tracking should remain disabled until the owner selects the tools, records their purposes, and implements any required consent controls. This theme does not automatically install analytics or advertising tags.',
				'Your choices' => 'Browser settings can block or remove cookies and other site data. Removing essential cookies may sign you out or affect session features. Contact TimonConfigs for information about storage used by the live service.',
			),
			'lifetime-license-terms' => array(
				'License status and purchase terms' => 'These draft terms require owner review before production. The final Lifetime license must be presented before purchase and identify the covered offering. The terms accepted at checkout govern a specific purchase, subject to applicable law.',
				'One-time purchase' => 'VIP Lifetime is a one-time purchase rather than a recurring monthly subscription. The displayed checkout price and the scope disclosed before purchase apply to the license.',
				'Included access' => 'Access covers the premium materials and private Discord channels expressly included with the purchased offering. Lifetime does not automatically include every future product, new brand, separate service, or individually sold release.',
				'Updates and service lifetime' => 'Only updates expressly covered by the license are included. Lifetime describes the licensed offering subject to its disclosed service lifetime and continued operation; it is not a promise that the product, third-party platforms, or the website will operate forever.',
				'Personal use and account responsibility' => 'The license is for personal use on the Discord account associated with the purchase unless otherwise agreed. Sharing protected files, reselling materials, or giving account access to another person is not permitted without authorization.',
				'Changes, restrictions, and consumer rights' => 'Compatibility may change after game, device, firmware, or platform updates. Access restrictions and discontinuation must follow the purchase terms and applicable law. Nothing in this draft removes mandatory consumer rights or makes every purchase non-refundable.',
			),
		);
	}

	/** Frozen pre-retirement stock text; used only to recognize an unchanged installed seed. */
	private static function legacy_checkout_legal_sections(): array {
		return array(
			'terms-of-service' => array(
				'Agreement and eligibility' => 'These draft terms describe TimonConfigs digital configuration access. The operating entity and final terms must be reviewed by the owner before production use. Users must be legally able to contract and comply with applicable law, game rules, platform rules, and community rules.',
				'Digital materials and personal licenses' => 'Configurations, setup guides, and related materials are licensed for personal use under the scope disclosed at purchase. Redistribution, resale, public file sharing, and account sharing require permission and may lead to access restrictions.',
				'Free and VIP access' => 'Free includes selected materials. VIP Monthly is a recurring subscription with covered access while active. VIP Lifetime is a one-time license whose included materials and updates follow the terms supplied before purchase. A Lifetime license does not promise every future product or uninterrupted operation.',
				'Checkout and Discord roles' => 'Upgrade.chat and its connected payment providers process purchases and manage VIP role delivery. A return to this website is not evidence of payment. Use the same Discord account throughout checkout and access verification.',
				'Compatibility and changes' => 'Only compatibility expressly listed for a configuration is confirmed. Game updates, firmware, platform restrictions, and personal settings may affect compatibility. No particular competitive outcome is guaranteed.',
				'Cancellation, access restrictions, and rights' => 'Manage recurring subscription cancellation through Upgrade.chat. Payment failures, chargebacks, fraud, redistribution, or material misuse may affect access under the purchase terms and applicable law. Nothing here excludes non-waivable consumer rights.',
				'Questions and disputes' => 'Contact TimonConfigs through the Contact page for account, policy, or purchase questions. Final production terms must identify the seller, jurisdiction, and mandatory consumer information.',
			),
			'privacy-policy' => array(
				'Policy status and controller' => 'This draft must be reviewed for the actual operating entity, processors, retention periods, and markets before production. The final policy must identify the data controller and a working contact channel.',
				'Information used by the service' => 'Website operation may involve account identifiers, support correspondence, security events, cookies, and technical request information. If Discord sign-in is enabled, its stated permissions may provide Discord user ID, public profile details, and current membership information.',
				'Purpose and legal basis' => 'Personal information is used only for documented purposes such as website operation, support, account access, fraud prevention, legal obligations, and service security. The legal basis for each purpose depends on the final operating model and applicable law.',
				'Payments and third parties' => 'Payment details are handled by Upgrade.chat and its connected payment providers. TimonConfigs does not request full card numbers or PayPal passwords. Hosting, Discord, support services, and other processors may receive information necessary for their roles.',
				'Cookies and embedded content' => 'Essential WordPress or security cookies may be used where required. Optional analytics and marketing require the applicable consent controls. YouTube content is loaded only after the visitor chooses to play an embedded video; its provider may then process request information.',
				'Retention, security, and your choices' => 'Retain personal information only as long as necessary for its documented purpose, legal duties, and dispute handling. Depending on location, individuals may have rights to access, correction, deletion, restriction, objection, portability, and complaint to a supervisory authority.',
				'Privacy requests' => 'Use the Contact page to request help with personal information. Identity may need to be verified before responding. Never send passwords or full payment credentials in support correspondence.',
			),
			'refund-policy' => array(
				'Scope' => 'This draft policy covers TimonConfigs digital licenses and VIP memberships purchased through Upgrade.chat. It requires owner review before production and does not override statutory consumer rights.',
				'Digital delivery and withdrawal rights' => 'Refund or withdrawal eligibility depends on applicable consumer law, the checkout terms accepted, whether digital access has been supplied, and any valid consent regarding immediate performance. Do not assume that all digital purchases are automatically non-refundable.',
				'Problems with an order' => 'Contact support promptly for duplicate or incorrect charges, access delivered to the wrong account, a missing role, or a material mismatch between the description and supplied content. Provide a transaction reference without exposing full payment credentials.',
				'Monthly cancellation' => 'Manage VIP Monthly cancellation through Upgrade.chat. Cancellation normally affects future renewal according to the subscription terms. It does not by itself establish refund eligibility for an elapsed billing period.',
				'Review and outcome' => 'Requests are assessed against the purchase terms, access records, the issue reported, and applicable law. Approved refunds use the original payment route where possible; payment-provider processing times vary.',
			),
		);
	}

	/** Native blocks, used only for new pages or genuinely empty existing content. */
	public static function default_content( string $slug ): string {
		if ( 'home' === $slug ) {
			return function_exists( 'timonconfigs_default_home_content' ) ? timonconfigs_default_home_content() : '';
		}
		$legal = self::legal_sections();
		if ( isset( $legal[ $slug ] ) ) {
			$content = self::paragraph( 'Draft policy — pending owner and legal review before production use.' );
			foreach ( $legal[ $slug ] as $heading => $body ) {
				$content .= self::heading( $heading ) . self::paragraph( $body );
			}
			return $content . self::paragraph( '<a href="' . esc_url( home_url( '/contact/' ) ) . '">Contact TimonConfigs</a> for questions.' );
		}
		$copy = array(
			'pricing'        => 'Choose selected Free materials, a recurring VIP Monthly membership, or a one-time VIP Lifetime license. Compare the current website offer and review the price, renewal terms, and payment consent before secure hosted checkout.',
			'games'          => 'Apex Legends is our flagship collection. Explore each game to check published releases, supported devices, and availability. Collections without published configurations are clearly marked Coming soon.',
			'reviews'        => 'Explore community feedback. Cards marked “Sample Review / Demo Content” are examples of the layout rather than real customer testimonials.',
			'faq'            => 'Find answers about supported devices, compatibility, membership access, checkout, and support. Check each configuration’s listed requirements before choosing a plan.',
			'contact'        => 'For a faster answer, include the configuration name, device, input method, platform, and version. Use private support for account or purchase information; never post passwords or full payment details.',
			'video-showcase' => 'Watch first-party TimonConfigs Apex Legends footage. Device and configuration labels are confirmed only when the recording metadata has been verified.',
			'vip-monthly'    => 'VIP Monthly is for players who prefer recurring access to premium configurations, covered updates while subscribed, private Discord channels, and VIP support. Review the renewal terms before website checkout and manage supported subscriptions from your TimonConfigs account.',
			'vip-lifetime'   => 'VIP Lifetime is a one-time purchase for access under the Lifetime license accepted at checkout. It has no recurring monthly payment and includes only the materials and updates expressly covered by the purchased license.',
		);
		return isset( $copy[ $slug ] ) ? self::paragraph( $copy[ $slug ] ) : '';
	}

	private static function paragraph( string $text ): string {
		return '<!-- wp:paragraph --><p>' . wp_kses_post( $text ) . '</p><!-- /wp:paragraph -->' . "\n";
	}

	private static function heading( string $text ): string {
		return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $text ) . '</h2><!-- /wp:heading -->' . "\n";
	}

	/** Exact historical stock pages, never public checkout instructions. */
	private static function legacy_checkout_content( string $slug ): string {
		$copy = array(
			'pricing' => 'Choose selected Free materials, a recurring VIP Monthly membership, or a one-time VIP Lifetime license. Compare the current offer and review the terms displayed by Upgrade.chat before purchasing.',
			'vip-monthly' => 'VIP Monthly is for players who prefer recurring access to premium configurations, covered updates while subscribed, private Discord channels, and VIP support. Manage cancellation through Upgrade.chat under the terms shown with your subscription.',
		);
		if ( isset( $copy[ $slug ] ) ) { return self::paragraph( $copy[ $slug ] ); }
		if ( ! in_array( $slug, array( 'terms-of-service', 'privacy-policy', 'refund-policy' ), true ) ) { return ''; }
		$sections = self::legacy_checkout_legal_sections()[ $slug ];
		$content = self::paragraph( 'Draft policy — pending owner and legal review before production use.' );
		foreach ( $sections as $heading => $body ) { $content .= self::heading( $heading ) . self::paragraph( $body ); }
		return $content . self::paragraph( '<a href="' . esc_url( home_url( '/contact/' ) ) . '">Contact TimonConfigs</a> for questions.' );
	}

	/** Read-only inventory; custom content is never inferred to be a plugin default. */
	public static function checkout_copy_review(): array {
		$review = array();
		foreach ( array( 'pricing', 'vip-monthly', 'terms-of-service', 'privacy-policy', 'refund-policy' ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page instanceof WP_Post ) { continue; }
			$state = $page->post_content === self::legacy_checkout_content( $slug ) ? 'eligible' : ( false !== stripos( $page->post_content, 'upgrade.chat' ) ? 'manual_review' : 'current' );
			$review[] = array( 'id' => (int) $page->ID, 'slug' => $slug, 'state' => $state );
		}
		return $review;
	}

	/** Runs once in an administrator session; public requests never write page content. */
	public static function maybe_migrate_checkout_copy(): void {
		if ( ! current_user_can( 'manage_options' ) || '1' === get_option( 'timonconfigs_checkout_copy_version' ) ) { return; }
		$result = self::migrate_checkout_copy();
		if ( is_wp_error( $result ) ) {
			update_option( 'timonconfigs_checkout_copy_error', sanitize_key( $result->get_error_code() ), false );
			return;
		}
		delete_option( 'timonconfigs_checkout_copy_error' );
		update_option( 'timonconfigs_checkout_copy_version', '1', false );
	}

	/** Compare-and-swap changes only untouched whole stock pages, preserving all page dates and metadata. */
	public static function migrate_checkout_copy(): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'tc_checkout_copy_forbidden', 'Administrator permission is required.' ); }
		global $wpdb;
		$updated = array();
		foreach ( self::checkout_copy_review() as $row ) {
			if ( 'eligible' !== $row['state'] ) { continue; }
			$before = self::legacy_checkout_content( $row['slug'] );
			$after = self::default_content( $row['slug'] );
			$backup_key = '_tc_checkout_copy_v1_backup';
			if ( metadata_exists( 'post', $row['id'], $backup_key ) ) {
				if ( $before !== get_post_meta( $row['id'], $backup_key, true ) ) { return new WP_Error( 'tc_checkout_copy_backup_conflict', 'A previous content backup needs administrator review.' ); }
			} elseif ( false === add_post_meta( $row['id'], $backup_key, wp_slash( $before ), true ) ) {
				return new WP_Error( 'tc_checkout_copy_backup_failed', 'The previous stock content could not be safely backed up.' );
			}
			// Do not fake a publication/review date or overwrite an editor racing this admin request.
			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d AND BINARY post_content = BINARY %s", $after, $row['id'], $before ) );
			if ( false === $result ) { return new WP_Error( 'tc_checkout_copy_write_failed', 'The stock-content update could not be completed.' ); }
			if ( 1 !== $result ) { return new WP_Error( 'tc_checkout_copy_changed', 'The page changed during review and was preserved. Retry after reviewing current content.' ); }
			clean_post_cache( $row['id'] );
			$updated[] = $row['id'];
		}
		return array( 'updated' => $updated, 'review' => self::checkout_copy_review() );
	}

	public static function handle_checkout_copy(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Administrator permission is required.', 'timonconfigs-core' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'tc_update_checkout_copy' );
		$result = self::migrate_checkout_copy();
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		delete_option( 'timonconfigs_checkout_copy_error' );
		update_option( 'timonconfigs_checkout_copy_version', '1', false );
		wp_safe_redirect( admin_url( 'tools.php?page=timonconfigs-setup&checkout-copy=updated' ) );
		exit;
	}

	/**
	 * Must be called by activation, a capability-checked admin action, or WP-CLI.
	 *
	 * @return array<string,int>|WP_Error
	 */
	public static function install(): array|WP_Error {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'tc_setup_forbidden', __( 'Administrator permission is required.', 'timonconfigs-core' ) );
		}
		TC_Catalog::register_content_types();
		TC_Catalog::register_landing_rewrites();
		TC_Catalog::seed_core_terms();
		TC_Catalog::seed_flagship_drafts();
		$ids = array();
		foreach ( self::pages() as $slug => $title ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof WP_Post ) {
				$ids[ $slug ] = $page->ID;
				if ( '' === trim( $page->post_content ) ) {
					$starter = self::default_content( $slug );
					if ( '' !== $starter ) {
						$updated = wp_update_post( array( 'ID' => $page->ID, 'post_content' => $starter ), true );
						if ( is_wp_error( $updated ) ) {
							return $updated;
						}
					}
				}
				continue;
			}
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_content' => self::default_content( $slug ) ), true );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$ids[ $slug ] = $id;
		}
		if ( ! get_option( 'page_on_front' ) && ! get_option( 'page_for_posts' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids['home'] );
		}
		if ( '' === (string) get_theme_mod( 'timonconfigs_discord_url', '' ) ) {
			set_theme_mod( 'timonconfigs_discord_url', self::DISCORD_INVITE );
		}
		self::install_unassigned_menus( $ids );
		if ( class_exists( 'TC_Member_Area' ) ) { TC_Member_Area::install(); }
		return $ids;
	}

	/** Create starter menus only where no menu location is assigned. */
	private static function install_unassigned_menus( array $ids ): void {
		$locations = get_nav_menu_locations();
		$definitions = array(
			'primary' => array( 'home', 'configs', 'games', 'pricing', 'reviews', 'faq', 'contact' ),
			'footer'  => array( 'terms-of-service', 'privacy-policy', 'cookie-policy', 'refund-policy', 'lifetime-license-terms', 'contact' ),
		);
		$titles = self::pages();
		foreach ( $definitions as $location => $slugs ) {
			if ( ! empty( $locations[ $location ] ) ) {
				continue;
			}
			$name = 'primary' === $location ? 'TimonConfigs Primary' : 'TimonConfigs Legal';
			$existing = wp_get_nav_menu_object( $name );
			$menu_id = $existing ? $existing->term_id : wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				continue;
			}
			$items = wp_get_nav_menu_items( $menu_id );
			if ( ! $items ) {
				foreach ( $slugs as $position => $slug ) {
					$args = array( 'menu-item-title' => $titles[ $slug ] ?? 'Configs', 'menu-item-status' => 'publish', 'menu-item-position' => $position + 1 );
					if ( 'configs' === $slug ) {
						$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/configs/' ) );
					} else {
						$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $ids[ $slug ] );
					}
					wp_update_nav_menu_item( $menu_id, 0, $args );
				}
			}
			$locations[ $location ] = (int) $menu_id;
		}
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	public static function add_setup_page(): void {
		add_management_page( __( 'TimonConfigs setup', 'timonconfigs-core' ), __( 'TimonConfigs setup', 'timonconfigs-core' ), 'manage_options', 'timonconfigs-setup', array( __CLASS__, 'render_setup_page' ) );
	}

	public static function render_setup_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap"><h1><?php esc_html_e( 'TimonConfigs site setup', 'timonconfigs-core' ); ?></h1><p><?php esc_html_e( 'Create missing pages and fill empty starter content. Existing content, publication status, and assigned menus are preserved. Review legal drafts before production.', 'timonconfigs-core' ); ?></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Page', 'timonconfigs-core' ); ?></th><th><?php esc_html_e( 'Status', 'timonconfigs-core' ); ?></th></tr></thead><tbody><?php foreach ( self::pages() as $slug => $title ) : $page = get_page_by_path( $slug, OBJECT, 'page' ); ?><tr><td><?php echo esc_html( $title ); ?></td><td><?php echo esc_html( $page ? $page->post_status : 'Missing' ); ?></td></tr><?php endforeach; ?></tbody></table>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="tc_install_site"><?php wp_nonce_field( 'tc_install_site' ); ?><?php submit_button( __( 'Create missing site structure', 'timonconfigs-core' ) ); ?></form>
		<h2><?php esc_html_e( 'Website checkout content', 'timonconfigs-core' ); ?></h2><p><?php esc_html_e( 'Unchanged stock pages are updated automatically in an administrator session. Authored content is preserved and must be reviewed manually. This update never approves legal policies or changes publication dates.', 'timonconfigs-core' ); ?></p>
		<?php if ( get_option( 'timonconfigs_checkout_copy_error', '' ) ) : ?><p role="status"><?php esc_html_e( 'A stock-content update needs review. Check the pages below before retrying.', 'timonconfigs-core' ); ?></p><?php endif; ?>
		<ul><?php foreach ( self::checkout_copy_review() as $copy_row ) : ?><li><a href="<?php echo esc_url( get_edit_post_link( $copy_row['id'], 'raw' ) ); ?>"><?php echo esc_html( self::pages()[ $copy_row['slug'] ] ); ?></a>: <?php echo esc_html( array( 'eligible' => 'Unchanged stock content — safe to update', 'manual_review' => 'Authored content — review checkout wording manually', 'current' => 'No retired checkout reference detected' )[ $copy_row['state'] ] ); ?></li><?php endforeach; ?></ul>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="tc_update_checkout_copy"><?php wp_nonce_field( 'tc_update_checkout_copy' ); ?><?php submit_button( __( 'Retry safe stock-content update', 'timonconfigs-core' ), 'secondary' ); ?></form></div>
		<?php
	}

	public static function handle_install(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator permission is required.', 'timonconfigs-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'tc_install_site' );
		$result = self::install();
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		flush_rewrite_rules();
		wp_safe_redirect( admin_url( 'tools.php?page=timonconfigs-setup&installed=1' ) );
		exit;
	}
}
