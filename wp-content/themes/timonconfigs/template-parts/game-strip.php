<?php
/**
 * Game artwork strip shared by Home and Games.
 *
 * @package TimonConfigs
 */

defined( 'ABSPATH' ) || exit;

$terms = get_terms( array( 'taxonomy' => 'tc_game', 'hide_empty' => false ) );
$terms = is_wp_error( $terms ) ? array() : $terms;
$definitions = method_exists( 'TC_Catalog', 'game_definitions' ) ? TC_Catalog::game_definitions() : array();
$ordered_terms = array();
foreach ( array_keys( $definitions ) as $slug ) {
	foreach ( $terms as $game_term ) {
		if ( $slug === $game_term->slug ) {
			$ordered_terms[ $game_term->term_id ] = $game_term;
		}
	}
}
foreach ( $terms as $game_term ) {
	if ( 'the-finals' !== $game_term->slug ) {
		$ordered_terms[ $game_term->term_id ] = $game_term;
	}
}
$wordmarks = array(
	'apex-legends'      => '<strong>APEX</strong><span>LEGENDS</span>',
	'call-of-duty'      => '<strong>CALL<span>OF</span>DUTY</strong>',
	'fortnite'          => '<strong>FORTNITE</strong>',
	'rainbow-six-siege' => '<span>RAINBOW SIX</span><strong>SIEGE</strong>',
	'rust'             => '<strong>RUST</strong>',
	'ea-sports-fc'      => '<span class="tc-game-cover__ea">EA</span><strong>FC</strong>',
	'nba-2k'           => '<strong>NBA<span>2K</span></strong>',
);
?>
<div class="tc-game-strip" role="list" aria-label="Game collections">
	<?php foreach ( $ordered_terms as $game ) :
		$image = method_exists( 'TC_Catalog', 'collection_image' ) ? TC_Catalog::collection_image( $game ) : '';
		$available = method_exists( 'TC_Catalog', 'collection_has_configs' ) && TC_Catalog::collection_has_configs( $game );
		$status = $available ? 'Available now' : 'Coming soon';
		$url = get_term_link( $game );
		if ( is_wp_error( $url ) ) { continue; }
		?>
		<a id="game-<?php echo esc_attr( $game->slug ); ?>" class="tc-game-cover tc-game-cover--<?php echo esc_attr( $game->slug ); ?><?php echo $available ? ' is-available' : ' is-coming'; ?>" role="listitem" href="<?php echo esc_url( $url ); ?>">
			<?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" width="600" height="360" loading="lazy" decoding="async"><?php endif; ?>
			<span class="tc-game-cover__shade"></span>
			<span class="tc-game-cover__wordmark" aria-hidden="true"><?php echo isset( $wordmarks[ $game->slug ] ) && ( ! isset( $definitions[ $game->slug ]['name'] ) || $definitions[ $game->slug ]['name'] === $game->name ) ? wp_kses_post( $wordmarks[ $game->slug ] ) : '<strong>' . esc_html( $game->name ) . '</strong>'; ?></span>
			<span class="tc-game-cover__status" aria-hidden="true"><?php echo esc_html( $status ); ?></span>
			<span class="screen-reader-text"><?php echo esc_html( $game->name . ' — ' . $status ); ?></span>
		</a>
	<?php endforeach; ?>
</div>
