<?php
/** Detailed config information and access guidance. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) : the_post();
	$config_id = get_the_ID();
	$publisher = (string) get_post_meta( $config_id, 'tc_publisher', true );
	$creator = (string) get_post_meta( $config_id, 'tc_creator', true );
	$release_date = TC_Catalog::sanitize_date( get_post_meta( $config_id, 'tc_release_date', true ) );
	$published_date = $release_date ?: get_the_date( 'Y-m-d', $config_id );
	$access = is_callable( array( 'TC_Catalog', 'download_cta' ) ) ? TC_Catalog::download_cta( $config_id ) : array( 'action' => 'pending', 'url' => get_permalink( $config_id ), 'label' => 'View config details', 'message' => 'Member release files are not available yet.', 'files' => array() );
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
	$status_labels = array( 'coming-soon' => 'Coming soon', 'archived' => 'Archived' );
	?>
	<main id="main">
		<article <?php post_class( 'tc-config-single' ); ?>>
			<header class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><div class="tc-config-single__hero"><div><p class="tc-eyebrow"><?php echo esc_html( $game ? $game->name : 'CONFIGURATION' ); ?></p><h1><?php the_title(); ?></h1><p class="tc-lead"><?php echo esc_html( get_the_excerpt() ); ?></p><div class="tc-config-single__tags"><span><?php echo esc_html( $premium ? ( 'lifetime' === $tier ? 'VIP Lifetime' : 'VIP Monthly' ) : 'Free' ); ?></span><?php if ( $devices && ! is_wp_error( $devices ) ) : ?><span><?php echo esc_html( $devices[0]->name ); ?></span><?php endif; ?><?php if ( $input ) : ?><span><?php echo esc_html( 'mnk' === $input ? 'Mouse & keyboard' : 'Controller' ); ?></span><?php endif; ?><span><?php echo esc_html( $ready ? 'Published release' : ( $status_labels[ $status ] ?? 'Release details pending' ) ); ?></span></div></div><?php if ( has_post_thumbnail() ) : ?><div class="tc-config-single__image"><?php the_post_thumbnail( 'large' ); ?></div><?php elseif ( $cover ) : ?><div class="tc-config-single__image"><img src="<?php echo esc_url( $cover ); ?>" alt="" width="600" height="360" decoding="async"></div><?php endif; ?></div></div></header>
			<div class="tc-shell tc-config-single__layout">
				<div class="tc-entry__content">
					<section aria-labelledby="config-about"><h2 id="config-about">About this config</h2><?php the_content(); ?></section>
					<?php if ( is_callable( array( 'TC_Config_Content', 'buttons' ) ) ) { echo TC_Config_Content::buttons( $config_id ); /* No protected content is rendered in these buttons. */ } ?>
					<section class="tc-config-detail-section" aria-labelledby="config-compatibility"><h2 id="config-compatibility">Compatibility &amp; setup</h2><?php if ( $compatibility ) : ?><?php echo wp_kses_post( wpautop( $compatibility ) ); ?><?php else : ?><p>Additional compatibility information has not been published. Check the confirmed device, input, and platform details or contact TimonConfigs before choosing this release.</p><?php endif; ?><?php if ( $tutorial ) : ?><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $tutorial ); ?>" rel="noopener">Watch the setup tutorial <?php echo timonconfigs_icon( 'play' ); // phpcs:ignore ?></a><?php endif; ?></section>
					<?php if ( $changelog ) : ?><section class="tc-config-detail-section" aria-labelledby="config-changelog"><h2 id="config-changelog">Changelog</h2><?php echo wp_kses_post( wpautop( $changelog ) ); ?></section><?php endif; ?>
					<section class="tc-config-detail-section tc-faq" aria-labelledby="config-faq"><h2 id="config-faq">Before you use this config</h2><details><summary>Will this work with my device and platform?</summary><p>Use only the device and platforms explicitly listed for this release. A matching game title alone does not confirm compatibility with another device, input method, or game version.</p></details><details><summary>How do I get access?</summary><p><?php echo $premium ? 'Premium access follows your current membership or Lifetime license terms. Upgrade.chat manages purchases and VIP Discord roles. Returning from checkout alone does not grant access.' : 'Sign in with Discord and verify your current Free Member or VIP access. Free script files use the same protected download checks as premium files; a release without uploaded files is not offered for download.'; ?></p></details><details><summary>What should I include when asking for help?</summary><p>Include this configuration name, device, platform, input method, game version, and the config version you are using. Do not share payment credentials or private account tokens.</p></details><a class="tc-text-link" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">Read all frequently asked questions <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></section>
				</div>
				<aside class="tc-config-specs"><p class="tc-price-card__name">CONFIG DETAILS</p><dl><div><dt>Publisher</dt><dd><?php echo esc_html( $publisher ?: 'TimonConfigs' ); ?></dd></div><?php if ( $creator ) : ?><div><dt>Creator</dt><dd><?php echo esc_html( $creator ); ?></dd></div><?php endif; ?><div><dt><?php echo $release_date ? 'Released' : 'Published'; ?></dt><dd><time datetime="<?php echo esc_attr( $published_date ); ?>"><?php echo esc_html( $published_date ); ?></time></dd></div><div><dt>Device</dt><dd><?php echo esc_html( $devices && ! is_wp_error( $devices ) ? implode( ', ', wp_list_pluck( $devices, 'name' ) ) : 'Not yet confirmed' ); ?></dd></div><div><dt>Input</dt><dd><?php echo esc_html( $input ? ( 'mnk' === $input ? 'Mouse & keyboard' : 'Controller' ) : 'Not yet confirmed' ); ?></dd></div><div><dt>Platforms</dt><dd><?php echo esc_html( $platform ?: 'Not yet confirmed' ); ?></dd></div><div><dt>Config version</dt><dd><?php echo esc_html( $version ?: 'Not yet published' ); ?></dd></div><div><dt>Game version</dt><dd><?php echo esc_html( $game_version ?: 'Not yet confirmed' ); ?></dd></div><?php if ( $updated ) : ?><div><dt>Last update</dt><dd><time datetime="<?php echo esc_attr( $updated ); ?>"><?php echo esc_html( $updated ); ?></time></dd></div><?php endif; ?><div><dt>Access</dt><dd><?php echo esc_html( $premium ? ( 'lifetime' === $tier ? 'VIP Lifetime license' : 'VIP Monthly membership' ) : 'Free member' ); ?></dd></div><div><dt>Status</dt><dd><?php echo esc_html( $ready ? 'Published' : ( $status_labels[ $status ] ?? 'Pending publication' ) ); ?></dd></div></dl>
					<section class="tc-protected-files" aria-labelledby="protected-files-title">
						<h2 id="protected-files-title">Release files</h2>
						<?php if ( ! $ready || 'pending' === $access['action'] ) : ?>
							<p class="tc-config-access-note">Release files are not available yet. Check the published release details or ask the community for an update.</p>
						<?php elseif ( 'download' === $access['action'] ) : ?>
							<ul class="tc-protected-file-list">
								<?php foreach ( $access['files'] as $file ) : ?>
									<li><h3><?php echo esc_html( $file['name'] ); ?></h3><p class="tc-protected-file-list__meta"><?php if ( ! empty( $file['version'] ) && is_scalar( $file['version'] ) ) : ?>Version <?php echo esc_html( (string) $file['version'] ); ?> · <?php endif; ?><?php echo esc_html( size_format( max( 0, (int) ( $file['size'] ?? 0 ) ) ) ); ?></p>
									<?php if ( ! empty( $file['notes'] ) && is_string( $file['notes'] ) ) : ?><p><?php echo esc_html( $file['notes'] ); ?></p><?php endif; ?>
									<?php if ( ! empty( $file['checksum'] ) && is_string( $file['checksum'] ) ) : ?><details><summary>File checksum</summary><code><?php echo esc_html( $file['checksum'] ); ?></code></details><?php endif; ?>
									<?php TC_Downloads::render_button( $config_id, $file['id'], 'Download' ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<a class="tc-button" href="<?php echo esc_url( $access['url'] ); ?>"><?php echo esc_html( $access['label'] ); ?></a>
						<?php endif; ?>
						<p class="tc-config-access-note"><?php echo esc_html( $access['message'] ); ?></p>
						<?php if ( 'memberships' === $access['action'] ) : ?><div class="tc-config-upsell"><p class="tc-eyebrow">EXPLORE VIP ACCESS</p><h3>A membership for your next setup.</h3><p>VIP Monthly is €40/month. VIP Lifetime is €100 one time under the purchased license. Check this release’s access requirement before choosing.</p><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( 'monthly' ) ); ?>">VIP Monthly · €40/month</a><a class="tc-text-link" href="<?php echo esc_url( timonconfigs_plan_url( 'lifetime' ) ); ?>">VIP Lifetime · €100 one time</a></div><?php endif; ?>
						<a class="tc-text-link" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Browse Memberships <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a>
					</section>
					<?php if ( $game ) : $collection_url = get_term_link( $game ); if ( ! is_wp_error( $collection_url ) ) : ?><a class="tc-text-link tc-config-back-link" href="<?php echo esc_url( $collection_url ); ?>">More <?php echo esc_html( $game->name ); ?> configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php endif; endif; ?>
				</aside>
			</div>
		</article>
	</main>
	<?php
endwhile;
get_footer();
