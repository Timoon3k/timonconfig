<?php
/** Catalogue config card. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

$config_id = get_the_ID();
$tier = get_post_meta( $config_id, 'tc_tier', true ) ?: 'free';
$input = get_post_meta( $config_id, 'tc_input_method', true );
$version = get_post_meta( $config_id, 'tc_config_version', true );
$status = get_post_meta( $config_id, 'tc_release_status', true );
$status_labels = array( 'coming-soon' => 'Coming soon', 'archived' => 'Archived' );
$release_ready = ( '' === $status || 'available' === $status ) && 'publish' === get_post_status( $config_id );
$games = get_the_terms( $config_id, 'tc_game' );
$devices = get_the_terms( $config_id, 'tc_device' );
$platform_terms = get_the_terms( $config_id, 'tc_platform' );
$platform = $platform_terms && ! is_wp_error( $platform_terms ) ? implode( ', ', wp_list_pluck( $platform_terms, 'name' ) ) : (string) get_post_meta( $config_id, 'tc_platform', true );
$labels = array( 'free' => 'Free', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime' );
$game = $games && ! is_wp_error( $games ) ? $games[0] : null;
$cover = ! has_post_thumbnail() && $game && method_exists( 'TC_Catalog', 'collection_image' ) ? TC_Catalog::collection_image( $game ) : '';
?>
<article <?php post_class( 'tc-config-card' ); ?>>
	<a class="tc-config-card__visual" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
		<?php if ( has_post_thumbnail() ) : ?><?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?><?php elseif ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt="" width="600" height="360" loading="lazy" decoding="async"><?php else : ?><span class="tc-config-card__device"><?php echo esc_html( $devices && ! is_wp_error( $devices ) ? $devices[0]->name : 'TimonConfigs' ); ?></span><?php endif; ?>
		<span class="tc-config-card__tier tc-config-card__tier--<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( $labels[ $tier ] ?? 'Free' ); ?></span>
		<?php if ( ! $release_ready ) : ?><span class="tc-config-card__status"><?php echo esc_html( $status_labels[ $status ] ?? 'Release details pending' ); ?></span><?php endif; ?>
	</a>
	<div class="tc-config-card__body">
		<p class="tc-config-card__meta"><?php echo esc_html( $game ? $game->name : 'Gaming config' ); ?><?php if ( $input ) : ?> · <?php echo esc_html( 'mnk' === $input ? 'Mouse & keyboard' : 'Controller' ); ?><?php endif; ?></p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( get_the_excerpt() ); ?></p>
		<div class="tc-config-card__specs"><?php if ( $devices && ! is_wp_error( $devices ) ) : ?><span><?php echo esc_html( $devices[0]->name ); ?></span><?php endif; ?><?php if ( $platform ) : ?><span><?php echo esc_html( $platform ); ?></span><?php endif; ?><?php if ( $version ) : ?><span>v<?php echo esc_html( $version ); ?></span><?php endif; ?></div>
		<a class="tc-text-link" href="<?php the_permalink(); ?>">View config details <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a>
	</div>
</article>
