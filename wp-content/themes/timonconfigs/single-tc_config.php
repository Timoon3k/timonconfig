<?php
/** Detailed config information and access guidance. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) : the_post();
	$config_id = get_the_ID();
	$tier = get_post_meta( $config_id, 'tc_tier', true ) ?: 'free';
	$input = (string) get_post_meta( $config_id, 'tc_input_method', true );
	$version = (string) get_post_meta( $config_id, 'tc_config_version', true );
	$updated = (string) get_post_meta( $config_id, 'tc_last_updated', true );
	$compatibility = (string) get_post_meta( $config_id, 'tc_compatibility', true );
	$game_version = (string) get_post_meta( $config_id, 'tc_game_version', true );
	$tutorial = (string) get_post_meta( $config_id, 'tc_tutorial_url', true );
	$changelog = (string) get_post_meta( $config_id, 'tc_changelog', true );
	$status = (string) get_post_meta( $config_id, 'tc_release_status', true );
	$ready = in_array( $status, array( '', 'available' ), true ) && 'publish' === get_post_status();
	$devices = get_the_terms( $config_id, 'tc_device' );
	$games = get_the_terms( $config_id, 'tc_game' );
	$platforms = get_the_terms( $config_id, 'tc_platform' );
	$platform = $platforms && ! is_wp_error( $platforms ) ? implode( ', ', wp_list_pluck( $platforms, 'name' ) ) : (string) get_post_meta( $config_id, 'tc_platform', true );
	$premium = 'free' !== $tier;
	$game = $games && ! is_wp_error( $games ) ? $games[0] : null;
	$cover = $game && method_exists( 'TC_Catalog', 'collection_image' ) ? TC_Catalog::collection_image( $game ) : '';
	$access_url = method_exists( 'TC_Catalog', 'public_access_url' ) ? TC_Catalog::public_access_url( $config_id ) : '';
	$status_labels = array( 'coming-soon' => 'Coming soon', 'archived' => 'Archived' );
	?>
	<main id="main">
		<article <?php post_class( 'tc-config-single' ); ?>>
			<header class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><div class="tc-config-single__hero"><div><p class="tc-eyebrow"><?php echo esc_html( $game ? $game->name : 'CONFIGURATION' ); ?></p><h1><?php the_title(); ?></h1><p class="tc-lead"><?php echo esc_html( get_the_excerpt() ); ?></p><div class="tc-config-single__tags"><span><?php echo esc_html( $premium ? ( 'lifetime' === $tier ? 'VIP Lifetime' : 'VIP Monthly' ) : 'Free' ); ?></span><?php if ( $devices && ! is_wp_error( $devices ) ) : ?><span><?php echo esc_html( $devices[0]->name ); ?></span><?php endif; ?><?php if ( $input ) : ?><span><?php echo esc_html( 'mnk' === $input ? 'Mouse & keyboard' : 'Controller' ); ?></span><?php endif; ?><span><?php echo esc_html( $ready ? 'Published release' : ( $status_labels[ $status ] ?? 'Release details pending' ) ); ?></span></div></div><?php if ( has_post_thumbnail() ) : ?><div class="tc-config-single__image"><?php the_post_thumbnail( 'large' ); ?></div><?php elseif ( $cover ) : ?><div class="tc-config-single__image"><img src="<?php echo esc_url( $cover ); ?>" alt="" width="600" height="360" decoding="async"></div><?php endif; ?></div></div></header>
			<div class="tc-shell tc-config-single__layout">
				<div class="tc-entry__content">
					<section aria-labelledby="config-about"><h2 id="config-about">About this config</h2><?php the_content(); ?></section>
					<section class="tc-config-detail-section" aria-labelledby="config-compatibility"><h2 id="config-compatibility">Compatibility &amp; setup</h2><?php if ( $compatibility ) : ?><?php echo wp_kses_post( wpautop( $compatibility ) ); ?><?php else : ?><p>Additional compatibility information has not been published. Check the confirmed device, input, and platform details or contact TimonConfigs before choosing this release.</p><?php endif; ?><?php if ( $tutorial ) : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $tutorial ); ?>" rel="noopener">Watch the setup tutorial <?php echo timonconfigs_icon( 'play' ); // phpcs:ignore ?></a><?php endif; ?></section>
					<?php if ( $changelog ) : ?><section class="tc-config-detail-section" aria-labelledby="config-changelog"><h2 id="config-changelog">Changelog</h2><?php echo wp_kses_post( wpautop( $changelog ) ); ?></section><?php endif; ?>
					<section class="tc-config-detail-section tc-faq" aria-labelledby="config-faq"><h2 id="config-faq">Before you use this config</h2><details><summary>Will this work with my device and platform?</summary><p>Use only the device and platforms explicitly listed for this release. A matching game title alone does not confirm compatibility with another device, input method, or game version.</p></details><details><summary>How do I get access?</summary><p><?php echo $premium ? 'Premium access follows your current membership or Lifetime license terms. Upgrade.chat manages purchases and VIP Discord roles. Returning from checkout alone does not grant access.' : 'Use the published Free access link when one is available. If a download has not been published, contact the TimonConfigs community for the current release information.'; ?></p></details><details><summary>What should I include when asking for help?</summary><p>Include this configuration name, device, platform, input method, game version, and the config version you are using. Do not share payment credentials or private account tokens.</p></details><a class="tc-text-link" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">Read all frequently asked questions <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></section>
				</div>
				<aside class="tc-config-specs"><p class="tc-price-card__name">CONFIG DETAILS</p><dl><div><dt>Device</dt><dd><?php echo esc_html( $devices && ! is_wp_error( $devices ) ? implode( ', ', wp_list_pluck( $devices, 'name' ) ) : 'Not yet confirmed' ); ?></dd></div><div><dt>Input</dt><dd><?php echo esc_html( $input ? ( 'mnk' === $input ? 'Mouse & keyboard' : 'Controller' ) : 'Not yet confirmed' ); ?></dd></div><div><dt>Platforms</dt><dd><?php echo esc_html( $platform ?: 'Not yet confirmed' ); ?></dd></div><div><dt>Config version</dt><dd><?php echo esc_html( $version ?: 'Not yet published' ); ?></dd></div><div><dt>Game version</dt><dd><?php echo esc_html( $game_version ?: 'Not yet confirmed' ); ?></dd></div><?php if ( $updated ) : ?><div><dt>Last update</dt><dd><time datetime="<?php echo esc_attr( $updated ); ?>"><?php echo esc_html( $updated ); ?></time></dd></div><?php endif; ?><div><dt>Access</dt><dd><?php echo esc_html( $premium ? ( 'lifetime' === $tier ? 'VIP Lifetime license' : 'VIP Monthly membership' ) : 'Free member' ); ?></dd></div><div><dt>Status</dt><dd><?php echo esc_html( $ready ? 'Published' : ( $status_labels[ $status ] ?? 'Pending publication' ) ); ?></dd></div></dl>
					<?php if ( ! $ready ) : ?><p class="tc-config-access-note">This release is not currently offered as an available download. Contact TimonConfigs for its publication status.</p><a class="tc-button tc-button--ghost" href="<?php echo esc_url( timonconfigs_community_url() ); ?>">Ask the community</a>
					<?php elseif ( $premium ) : ?><a class="tc-button" href="<?php echo esc_url( home_url( '/vip-' . ( 'lifetime' === $tier ? 'lifetime' : 'monthly' ) . '/' ) ); ?>">Upgrade to VIP <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><p>Premium files are not linked publicly. Use the official membership channels for access covered by your current plan.</p>
					<?php elseif ( $access_url ) : ?><a class="tc-button" href="<?php echo esc_url( $access_url ); ?>" rel="noopener">Open Free access <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><p>This is a public Free resource. Check the release instructions before use.</p>
					<?php else : ?><p class="tc-config-access-note">A public download link has not been published for this release.</p><a class="tc-button tc-button--ghost" href="<?php echo esc_url( timonconfigs_community_url() ); ?>">Ask about Free access</a><?php endif; ?>
					<?php if ( $game ) : $collection_url = get_term_link( $game ); if ( ! is_wp_error( $collection_url ) ) : ?><a class="tc-text-link tc-config-back-link" href="<?php echo esc_url( $collection_url ); ?>">More <?php echo esc_html( $game->name ); ?> configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php endif; endif; ?>
				</aside>
			</div>
		</article>
	</main>
	<?php
endwhile;
get_footer();
