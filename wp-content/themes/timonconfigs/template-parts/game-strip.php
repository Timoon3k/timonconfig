<?php
/**
 * Game artwork strip shared by Home and Games.
 *
 * @package TimonConfigs
 */

defined( 'ABSPATH' ) || exit;

$games = array(
	array( 'slug' => 'apex-legends', 'name' => 'Apex Legends', 'status' => 'Available now', 'url' => home_url( '/apex-legends-configs/' ) ),
	array( 'slug' => 'call-of-duty', 'name' => 'Call of Duty', 'status' => 'Coming soon' ),
	array( 'slug' => 'fortnite', 'name' => 'Fortnite', 'status' => 'Coming soon' ),
	array( 'slug' => 'rainbow-six-siege', 'name' => 'Rainbow Six Siege', 'status' => 'Coming soon' ),
	array( 'slug' => 'rust', 'name' => 'Rust', 'status' => 'Coming soon' ),
	array( 'slug' => 'ea-sports-fc', 'name' => 'EA Sports FC', 'status' => 'Coming soon' ),
	array( 'slug' => 'nba-2k', 'name' => 'NBA 2K', 'status' => 'Coming soon' ),
);
?>
<div class="tc-game-strip" role="list" aria-label="Game collections">
	<?php foreach ( $games as $game ) :
		$image = get_theme_mod(
			'timonconfigs_game_' . str_replace( '-', '_', $game['slug'] ) . '_image',
			get_template_directory_uri() . '/assets/images/game-' . $game['slug'] . '.webp'
		);
		$available = isset( $game['url'] );
		$tag       = $available ? 'a' : 'article';
		?>
		<<?php echo esc_html( $tag ); ?> class="tc-game-cover<?php echo $available ? ' is-available' : ' is-coming'; ?>" role="listitem"<?php echo $available ? ' href="' . esc_url( $game['url'] ) . '"' : ''; ?>>
			<img src="<?php echo esc_url( $image ); ?>" alt="" width="600" height="360" loading="lazy" decoding="async">
			<span class="tc-game-cover__shade"></span>
			<span class="tc-game-cover__copy"><strong><?php echo esc_html( $game['name'] ); ?></strong><small><?php echo esc_html( $game['status'] ); ?></small></span>
		</<?php echo esc_html( $tag ); ?>>
	<?php endforeach; ?>
</div>
