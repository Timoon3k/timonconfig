<?php
/** Editable device/game landing page with a genuinely filtered config catalogue. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
$collection = is_callable( array( 'TC_Collections', 'current' ) ) ? TC_Collections::current() : null;
if ( ! $collection ) { require get_template_directory() . '/page.php'; return; }
$page_id = get_queried_object_id();
$page = get_post( $page_id );
if ( $page instanceof WP_Post && post_password_required( $page ) ) { require get_template_directory() . '/page.php'; return; }
$game_term = $collection['game_term'] ?? null;
$device_term = $collection['device_term'] ?? null;
$image = $game_term instanceof WP_Term ? TC_Catalog::collection_image( $game_term ) : '';
$available = TC_Collections::has_configs( $collection );
$cards = TC_Collections::query( $collection );
$archive_url = (string) get_post_type_archive_link( 'tc_config' );
$base_url = (string) get_permalink( $page_id );
$access_labels = TC_Catalog::tier_labels();
$selected = array();
foreach ( array( 'game', 'device', 'tier', 'input', 'platform', 'config_search' ) as $key ) {
	$selected[ $key ] = TC_Catalog::request_value( $key );
	if ( in_array( $key, array( 'game', 'device', 'tier' ), true ) && ! empty( $collection[ $key ] ) ) { $selected[ $key ] = (string) $collection[ $key ]; }
}
$terms = array();
foreach ( array( 'game', 'device', 'platform' ) as $kind ) {
	$values = get_terms( array( 'taxonomy' => 'tc_' . $kind, 'hide_empty' => false ) );
	$terms[ $kind ] = is_wp_error( $values ) ? array() : $values;
}
get_header();
?>
<main id="main">
	<section class="tc-page-hero tc-page-hero--catalog tc-collection-hero">
		<?php if ( $image ) : ?><img class="tc-collection-hero__art" src="<?php echo esc_url( $image ); ?>" alt="" width="1200" height="600" fetchpriority="high" decoding="async"><?php endif; ?>
		<div class="tc-shell">
			<?php timonconfigs_breadcrumbs(); ?>
			<p class="tc-eyebrow"><?php echo $game_term instanceof WP_Term ? 'GAME &amp; DEVICE COLLECTION' : 'FREE CONFIG COLLECTION'; ?></p>
			<h1><?php echo esc_html( get_the_title( $page_id ) ); ?></h1>
			<div class="tc-lead"><?php echo $page instanceof WP_Post && '' !== trim( $page->post_content ) ? apply_filters( 'the_content', get_the_content( null, false, $page ) ) : wp_kses_post( wpautop( $collection['description'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standard authored page content filters. ?></div>
			<span class="tc-collection-status<?php echo $available ? ' is-available' : ''; ?>"><?php echo esc_html( $available ? 'Published releases available' : 'Coming soon · release details pending' ); ?></span>
			<div class="tc-collection-links">
				<?php if ( $game_term instanceof WP_Term ) : $game_url = get_term_link( $game_term ); ?>
					<?php if ( ! is_wp_error( $game_url ) ) : ?><a href="<?php echo esc_url( $game_url ); ?>">All <?php echo esc_html( $game_term->name ); ?> configs</a><?php endif; ?>
					<?php foreach ( array( 'xim-matrix' => 'XIM Matrix', 'cronus-zen' => 'Cronus Zen' ) as $device_slug => $device_name ) : ?>
						<?php if ( $device_slug !== $collection['device'] ) : ?><a href="<?php echo esc_url( timonconfigs_collection_url( $game_term->slug, $device_slug ) ); ?>"><?php echo esc_html( $device_name . ' ' . $game_term->name ); ?></a><?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
				<?php if ( $device_term instanceof WP_Term ) : $device_url = get_term_link( $device_term ); ?><?php if ( ! is_wp_error( $device_url ) ) : ?><a href="<?php echo esc_url( $device_url ); ?>">All <?php echo esc_html( $device_term->name ); ?> configs</a><?php endif; ?><?php endif; ?>
				<a href="<?php echo esc_url( $archive_url ); ?>">All configurations</a>
			</div>
		</div>
	</section>
	<section class="tc-section tc-section--topless"><div class="tc-shell">
		<form class="tc-catalog-filter" action="<?php echo esc_url( $archive_url ); ?>" method="get" role="search">
			<label><span>Search</span><input type="search" name="config_search" value="<?php echo esc_attr( $selected['config_search'] ); ?>" placeholder="Search this setup…"></label>
			<?php foreach ( array( 'game' => 'Game', 'device' => 'Device' ) as $kind => $label ) : ?>
				<label><span><?php echo esc_html( $label ); ?></span><select name="<?php echo esc_attr( $kind ); ?>"><option value="">All <?php echo esc_html( strtolower( $label ) ); ?>s</option><?php foreach ( $terms[ $kind ] as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected[ $kind ], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select></label>
			<?php endforeach; ?>
			<label><span>Input</span><select name="input"><option value="">All inputs</option><option value="controller" <?php selected( $selected['input'], 'controller' ); ?>>Controller</option><option value="mnk" <?php selected( $selected['input'], 'mnk' ); ?>>Mouse &amp; keyboard</option></select></label>
			<label><span>Platform</span><select name="platform"><option value="">All platforms</option><?php foreach ( $terms['platform'] as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected['platform'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select></label>
			<label><span>Access</span><select name="tier"><option value="">All tiers</option><?php foreach ( $access_labels as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected['tier'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><span>Sort</span><select name="sort"><?php foreach ( TC_Catalog::sort_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( TC_Catalog::selected_sort(), $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<button class="tc-button" type="submit">Apply filters</button>
		</form>
		<?php if ( $cards->have_posts() ) : ?>
			<div class="tc-config-grid"><?php while ( $cards->have_posts() ) : $cards->the_post(); get_template_part( 'template-parts/config-card' ); endwhile; ?></div>
			<?php
			$pagination_args = array();
			foreach ( array( 'config_search', 'input', 'platform', 'tier', 'sort' ) as $key ) {
				$value = TC_Catalog::request_value( $key );
				if ( '' !== $value ) { $pagination_args[ $key ] = $value; }
			}
			$pagination = paginate_links( array( 'base' => str_replace( '999999999', '%#%', add_query_arg( 'collection_page', 999999999, $base_url ) ), 'format' => '', 'current' => TC_Collections::page_number(), 'total' => (int) $cards->max_num_pages, 'mid_size' => 1, 'prev_text' => 'Previous', 'next_text' => 'Next', 'add_args' => $pagination_args ) );
			if ( $pagination ) : ?><nav class="tc-pagination" aria-label="Configuration pages"><?php echo wp_kses_post( $pagination ); ?></nav><?php endif; ?>
		<?php else : ?>
			<div class="tc-empty-state"><p class="tc-eyebrow"><?php echo $available ? 'NO MATCHES' : 'COLLECTION IN PREPARATION'; ?></p><h2><?php echo $available ? 'No configs match these filters yet.' : 'Release information is on the way.'; ?></h2><p><?php echo $available ? 'Try a broader search or clear the additional filters for this collection.' : 'Published configurations will appear here when the matching device, game and release details are available.'; ?></p><div class="tc-actions"><a class="tc-button tc-button--ghost" href="<?php echo esc_url( $base_url ); ?>">Reset filters</a><a class="tc-button" href="<?php echo esc_url( $archive_url ); ?>">Explore the library</a></div></div>
		<?php endif; wp_reset_postdata(); ?>
	</div></section>
	<section class="tc-section"><div class="tc-shell tc-collection-guidance">
		<article><p class="tc-eyebrow">BEFORE YOU INSTALL</p><h2>Match the release to your setup.</h2><p>Check the named game edition, device, input method, supported platform and version on each configuration. Follow its Guide and Values, and keep a backup before changing your setup.</p><a class="tc-text-link" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">Read the setup FAQ <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article>
		<article><p class="tc-eyebrow">MEMBER ACCESS</p><h2>Check your Free or VIP access.</h2><p>Each release shows its access requirement. Downloads continue to use your verified Discord account and the membership attached to that release.</p><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Compare access plans</a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( home_url( '/contact/#community' ) ); ?>">Get setup help</a></div></article>
	</div></section>
</main>
<?php get_footer(); ?>
