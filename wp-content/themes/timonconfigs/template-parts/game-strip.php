<?php
/**
 * Game artwork strip shared by Home and Games.
 *
 * @package TimonConfigs
 */

defined( 'ABSPATH' ) || exit;

$games = array(
	array( 'slug' => 'apex-legends', 'name' => 'Apex Legends', 'status' => 'Available now', 'url' => home_url( '/apex-legends-configs/' ) ),
	array( 'slug' => 'call-of-duty', 'name' => 'Call of Duty', 'status' => 'Coming soon', 'url' => home_url( '/games/#game-call-of-duty' ) ),
	array( 'slug' => 'fortnite', 'name' => 'Fortnite', 'status' => 'Coming soon', 'url' => home_url( '/games/#game-fortnite' ) ),
	array( 'slug' => 'rainbow-six-siege', 'name' => 'Rainbow Six Siege', 'status' => 'Coming soon', 'url' => home_url( '/games/#game-rainbow-six-siege' ) ),
	array( 'slug' => 'rust', 'name' => 'Rust', 'status' => 'Coming soon', 'url' => home_url( '/games/#game-rust' ) ),
	array( 'slug' => 'ea-sports-fc', 'name' => 'EA Sports FC', 'status' => 'Coming soon', 'url' => home_url( '/games/#game-ea-sports-fc' ) ),
	array( 'slug' => 'nba-2k', 'name' => 'NBA 2K', 'status' => 'Coming soon', 'url' => home_url( '/games/#game-nba-2k' ) ),
);
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
	<?php foreach ( $games as $game ) :
		$image = get_theme_mod(
			'timonconfigs_game_' . str_replace( '-', '_', $game['slug'] ) . '_image',
			get_template_directory_uri() . '/assets/images/game-' . $game['slug'] . '.webp'
		);
		$available = 'Available now' === $game['status'];
		?>
		<a id="game-<?php echo esc_attr( $game['slug'] ); ?>" class="tc-game-cover tc-game-cover--<?php echo esc_attr( $game['slug'] ); ?><?php echo $available ? ' is-available' : ' is-coming'; ?>" role="listitem" href="<?php echo esc_url( $game['url'] ); ?>">
			<img src="<?php echo esc_url( $image ); ?>" alt="" width="600" height="360" loading="lazy" decoding="async">
			<span class="tc-game-cover__shade"></span>
			<span class="tc-game-cover__wordmark" aria-hidden="true"><?php echo wp_kses_post( $wordmarks[ $game['slug'] ] ); ?></span>
			<span class="tc-game-cover__status" aria-hidden="true"><?php echo esc_html( $game['status'] ); ?></span>
			<span class="screen-reader-text"><?php echo esc_html( $game['name'] . ' — ' . $game['status'] ); ?></span>
		</a>
	<?php endforeach; ?>
</div>
