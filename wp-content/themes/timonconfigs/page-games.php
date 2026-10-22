<?php
/** Editable game collections overview. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
$editor_content = (string) get_post_field( 'post_content', get_queried_object_id() );
$games = get_terms( array( 'taxonomy' => 'tc_game', 'hide_empty' => false ) );
$games = is_wp_error( $games ) ? array() : $games;
$definitions = method_exists( 'TC_Catalog', 'game_definitions' ) ? TC_Catalog::game_definitions() : array();
$ordered = array();
foreach ( array_keys( $definitions ) as $slug ) {
	foreach ( $games as $game ) {
		if ( $slug === $game->slug ) { $ordered[ $game->term_id ] = $game; }
	}
}
foreach ( $games as $game ) {
	if ( 'the-finals' !== $game->slug ) { $ordered[ $game->term_id ] = $game; }
}
?>
<main id="main">
	<section class="tc-page-hero tc-page-hero--games"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow">GAME COLLECTIONS</p><h1><?php echo esc_html( get_the_title() ); ?></h1><div class="tc-lead"><?php if ( trim( $editor_content ) ) : echo apply_filters( 'the_content', $editor_content ); else : ?><p>Find configurations for the games you play. Every collection separates published releases from upcoming work and links to the exact setup information you need.</p><?php endif; ?></div><div class="tc-actions"><a class="tc-button" href="<?php echo esc_url( home_url( '/configs/' ) ); ?>">Browse all configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><a class="tc-button tc-button--ghost" href="<?php echo esc_url( timonconfigs_community_url() ); ?>">Join Discord</a></div></div></section>
	<section class="tc-section tc-games-roadmap"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow">AVAILABLE &amp; UPCOMING</p><h2>Play your favorite games.</h2></div><p>Availability follows published configuration releases. Coming soon collections do not promise a release date.</p></div><?php get_template_part( 'template-parts/game-strip' ); ?></div></section>
	<section class="tc-section"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow">COLLECTION DIRECTORY</p><h2>Find your game. Check your setup.</h2></div></div><div class="tc-game-directory">
		<?php foreach ( $ordered as $game ) :
			$url = get_term_link( $game );
			if ( is_wp_error( $url ) ) { continue; }
			$available = method_exists( 'TC_Catalog', 'collection_has_configs' ) && TC_Catalog::collection_has_configs( $game );
			$image = method_exists( 'TC_Catalog', 'collection_image' ) ? TC_Catalog::collection_image( $game ) : '';
			?>
			<article class="tc-game-directory__card"><?php if ( $image ) : ?><a class="tc-game-directory__image" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $game->name . ' configurations' ); ?>"><img src="<?php echo esc_url( $image ); ?>" alt="" width="600" height="360" loading="lazy" decoding="async"></a><?php endif; ?><div class="tc-game-directory__body"><span class="tc-collection-status<?php echo $available ? ' is-available' : ''; ?>"><?php echo esc_html( $available ? 'Published releases' : 'Coming soon' ); ?></span><h2><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $game->name ); ?></a></h2><?php if ( $game->description ) : ?><div><?php echo wp_kses_post( wpautop( $game->description ) ); ?></div><?php else : ?><p>Release and compatibility details will appear when this collection is ready.</p><?php endif; ?><a class="tc-text-link" href="<?php echo esc_url( $url ); ?>">Explore collection <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></div></article>
		<?php endforeach; ?>
	</div></div></section>
	<section class="tc-section tc-games-devices"><div class="tc-shell"><div class="tc-section-heading"><div><p class="tc-eyebrow">CHOOSE YOUR DEVICE</p><h2>Games and devices are different.</h2></div><p>Browse the device collections, then confirm the supported input and platform on each release.</p></div><div class="tc-platforms"><article><span class="tc-number">01 / CRONUS</span><h2>Cronus Zen</h2><p>Find releases prepared for Cronus Zen and inspect their setup and compatibility details.</p><a href="<?php echo esc_url( home_url( '/cronus-zen-configs/' ) ); ?>">View Cronus Zen configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article><article><span class="tc-number">02 / XIM</span><h2>XIM Matrix</h2><p>Compare XIM Matrix configurations for confirmed controller or mouse and keyboard setups.</p><a href="<?php echo esc_url( home_url( '/xim-matrix-configs/' ) ); ?>">View XIM Matrix configs <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article><article><span class="tc-number">03 / MEMBERSHIP</span><h2>Free and VIP access</h2><p>Check the access level on each config. Premium membership follows the current plan or Lifetime license terms.</p><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">Compare access plans <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a></article></div></div></section>
</main>
<?php get_footer(); ?>
