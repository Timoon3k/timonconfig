<?php
/** Config library and game/device collection template. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();

$access_labels = is_callable( array( 'TC_Catalog', 'tier_labels' ) ) ? TC_Catalog::tier_labels() : array( 'free' => 'Free Member', 'monthly' => 'VIP — Monthly & Lifetime', 'lifetime' => 'VIP Lifetime only' );
$is_term = is_tax( array( 'tc_game', 'tc_device', 'tc_platform' ) );
$term = $is_term ? get_queried_object() : null;
$is_game = $term instanceof WP_Term && 'tc_game' === $term->taxonomy;
$is_device = $term instanceof WP_Term && 'tc_device' === $term->taxonomy;
$is_platform = $term instanceof WP_Term && 'tc_platform' === $term->taxonomy;
$headline = $term instanceof WP_Term ? $term->name . ' Configs' : 'Find the right config for your setup';
$intro = $term instanceof WP_Term && $term->description ? $term->description : 'Explore TimonConfigs releases by game, device, input method, platform, and access tier. Review the details of each release before choosing a setup.';
$available = $term instanceof WP_Term && method_exists( 'TC_Catalog', 'collection_has_configs' ) ? TC_Catalog::collection_has_configs( $term ) : false;
$image = $term instanceof WP_Term && method_exists( 'TC_Catalog', 'collection_image' ) ? TC_Catalog::collection_image( $term ) : '';
$get_filter = static function ( string $key ): string {
	return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : '';
};
if ( ! $is_term && 'free' === $get_filter( 'tier' ) ) {
	$headline = 'Free configs for your setup';
	$intro = 'Explore selected Free configurations and their confirmed device, input, platform, and game-version details. Sign in with Discord and verify your current membership to request protected release files.';
}
$games = get_terms( array( 'taxonomy' => 'tc_game', 'hide_empty' => false ) );
$devices = get_terms( array( 'taxonomy' => 'tc_device', 'hide_empty' => false ) );
$platforms = taxonomy_exists( 'tc_platform' ) ? get_terms( array( 'taxonomy' => 'tc_platform', 'hide_empty' => false ) ) : array();
$games = is_wp_error( $games ) ? array() : $games;
$devices = is_wp_error( $devices ) ? array() : $devices;
$platforms = is_wp_error( $platforms ) ? array() : $platforms;
$selected_game = $is_game ? $term->slug : $get_filter( 'game' );
$selected_device = $is_device ? $term->slug : $get_filter( 'device' );
$selected_platform = $is_platform ? $term->slug : $get_filter( 'platform' );
$archive_url = (string) get_post_type_archive_link( 'tc_config' );
$form_url = $term instanceof WP_Term ? get_term_link( $term ) : $archive_url;
$form_url = is_wp_error( $form_url ) ? $archive_url : $form_url;
$sort_options = is_callable( array( 'TC_Catalog', 'sort_options' ) ) ? TC_Catalog::sort_options() : array( 'updated' => 'Recently updated', 'newest' => 'Newest uploads', 'title' => 'Title: A–Z' );
$selected_sort = is_callable( array( 'TC_Catalog', 'selected_sort' ) ) ? TC_Catalog::selected_sort() : 'updated';
?>
<main id="main">
	<section class="tc-page-hero tc-page-hero--catalog tc-collection-hero">
		<?php if ( $image ) : ?><img class="tc-collection-hero__art" src="<?php echo esc_url( $image ); ?>" alt="" width="1200" height="600" fetchpriority="high" decoding="async"><?php endif; ?>
		<div class="tc-shell">
			<?php timonconfigs_breadcrumbs(); ?>
			<p class="tc-eyebrow"><?php echo $is_game ? 'GAME COLLECTION' : ( $is_device ? 'DEVICE COLLECTION' : ( $is_platform ? 'PLATFORM COLLECTION' : 'CONFIG LIBRARY' ) ); ?></p>
			<h1><?php echo esc_html( $headline ); ?></h1>
			<div class="tc-lead"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
			<?php if ( $term instanceof WP_Term ) : ?><span class="tc-collection-status<?php echo $available ? ' is-available' : ''; ?>"><?php echo esc_html( $available ? 'Published releases available' : 'Coming soon · release details pending' ); ?></span><?php endif; ?>
			<div class="tc-collection-links">
				<?php if ( ! $is_device || 'cronus-zen' !== $term->slug ) : ?><a href="<?php echo esc_url( $is_game ? timonconfigs_collection_url( $term->slug, 'cronus-zen' ) : home_url( '/cronus-zen-configs/' ) ); ?>">Cronus Zen configs</a><?php endif; ?>
				<?php if ( ! $is_device || 'xim-matrix' !== $term->slug ) : ?><a href="<?php echo esc_url( $is_game ? timonconfigs_collection_url( $term->slug, 'xim-matrix' ) : home_url( '/xim-matrix-configs/' ) ); ?>">XIM Matrix configs</a><?php endif; ?>
				<a href="<?php echo esc_url( $archive_url ); ?>">All configurations</a>
			</div>
		</div>
	</section>
	<?php if ( $is_game ) { get_template_part( 'template-parts/collection-intent' ); } ?>
	<section class="tc-section tc-section--topless">
		<div class="tc-shell">
			<form class="tc-catalog-filter" action="<?php echo esc_url( $form_url ); ?>" method="get" role="search">
				<label><span>Search</span><input type="search" name="config_search" value="<?php echo esc_attr( $get_filter( 'config_search' ) ); ?>" placeholder="Search configs…"></label>
				<label><span>Game</span><select name="game" <?php disabled( $is_game ); ?>><option value="">All games</option><?php foreach ( $games as $game ) : ?><option value="<?php echo esc_attr( $game->slug ); ?>" <?php selected( $selected_game, $game->slug ); ?>><?php echo esc_html( $game->name ); ?></option><?php endforeach; ?></select></label>
				<?php if ( $is_game ) : ?><input type="hidden" name="game" value="<?php echo esc_attr( $term->slug ); ?>"><?php endif; ?>
				<label><span>Device</span><select name="device" <?php disabled( $is_device ); ?>><option value="">All devices</option><?php foreach ( $devices as $device ) : ?><option value="<?php echo esc_attr( $device->slug ); ?>" <?php selected( $selected_device, $device->slug ); ?>><?php echo esc_html( $device->name ); ?></option><?php endforeach; ?></select></label>
				<?php if ( $is_device ) : ?><input type="hidden" name="device" value="<?php echo esc_attr( $term->slug ); ?>"><?php endif; ?>
				<label><span>Input</span><select name="input"><option value="">All inputs</option><option value="controller" <?php selected( $get_filter( 'input' ), 'controller' ); ?>>Controller</option><option value="mnk" <?php selected( $get_filter( 'input' ), 'mnk' ); ?>>Mouse &amp; keyboard</option></select></label>
				<label><span>Platform</span><select name="platform" <?php disabled( $is_platform ); ?>><option value="">All platforms</option><?php foreach ( $platforms as $platform ) : ?><option value="<?php echo esc_attr( $platform->slug ); ?>" <?php selected( $selected_platform, $platform->slug ); ?>><?php echo esc_html( $platform->name ); ?></option><?php endforeach; ?></select></label>
				<?php if ( $is_platform ) : ?><input type="hidden" name="platform" value="<?php echo esc_attr( $term->slug ); ?>"><?php endif; ?>
				<label><span>Access</span><select name="tier"><option value="">All tiers</option><?php foreach ( $access_labels as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $get_filter( 'tier' ), $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><span>Sort</span><select name="sort"><?php foreach ( $sort_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_sort, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<button class="tc-button" type="submit">Apply filters</button>
			</form>
			<?php if ( have_posts() ) : ?>
				<div class="tc-config-grid"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/config-card' ); endwhile; ?></div>
				<div class="tc-pagination"><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => 'Previous', 'next_text' => 'Next' ) ); ?></div>
			<?php else : ?>
				<div class="tc-empty-state"><p class="tc-eyebrow"><?php echo $term instanceof WP_Term && ! $available ? 'COLLECTION IN PREPARATION' : 'NO MATCHES'; ?></p><h2><?php echo $term instanceof WP_Term && ! $available ? 'Release information is on the way.' : 'No configs match these filters yet.'; ?></h2><p><?php echo $term instanceof WP_Term && ! $available ? 'This collection is being prepared. Configurations will appear when their device, platform, access, and version details are ready to publish.' : 'Try a broader search, change the filters, or return after the next published release.'; ?></p><div class="tc-actions"><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $form_url ); ?>">Reset filters</a><a class="tc-button" href="<?php echo esc_url( $archive_url ); ?>">Explore the library</a></div></div>
			<?php endif; ?>
		</div>
	</section>
	<section class="tc-section"><div class="tc-shell tc-collection-guidance"><article><p class="tc-eyebrow">BEFORE YOU CHOOSE</p><h2>Match the config to your setup.</h2><p>Check the exact device, input method, supported game version, and confirmed platforms on the config page. An unlisted platform or version is not a compatibility promise.</p><a class="tc-text-link" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">Read the setup FAQ <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article><article><p class="tc-eyebrow">FREE &amp; VIP</p><h2>Choose the access that fits.</h2><p>Browse selected Free configs or compare VIP Monthly and VIP Lifetime. Premium access follows the purchased membership or license terms.</p><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Compare VIP plans</a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( timonconfigs_community_url() ); ?>">Join Discord</a></div></article></div></section>
</main>
<?php get_footer(); ?>
