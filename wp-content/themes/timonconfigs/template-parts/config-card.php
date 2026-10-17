<?php
/** Catalogue config card. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;

$config_id = get_the_ID();
$tier      = get_post_meta( $config_id, 'tc_tier', true ) ?: 'free';
$platform  = get_post_meta( $config_id, 'tc_platform', true );
$input     = get_post_meta( $config_id, 'tc_input_method', true );
$version   = get_post_meta( $config_id, 'tc_config_version', true );
$games     = get_the_terms( $config_id, 'tc_game' );
$devices   = get_the_terms( $config_id, 'tc_device' );
$labels    = array( 'free' => 'Free', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime' );
?>
<article <?php post_class( 'tc-config-card' ); ?>>
	<a class="tc-config-card__visual" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?><?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?><?php else : ?><span class="tc-config-card__device"><?php echo esc_html( $devices && ! is_wp_error( $devices ) ? $devices[0]->name : 'TimonConfigs' ); ?></span><?php endif; ?>
		<span class="tc-config-card__tier tc-config-card__tier--<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( $labels[ $tier ] ?? 'Free' ); ?></span>
	</a>
	<div class="tc-config-card__body">
		<p class="tc-config-card__meta"><?php echo esc_html( $games && ! is_wp_error( $games ) ? $games[0]->name : 'Gaming config' ); ?><?php if ( $input ) : ?> · <?php echo esc_html( 'mnk' === $input ? 'MnK' : ucfirst( $input ) ); ?><?php endif; ?></p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( get_the_excerpt() ); ?></p>
		<div class="tc-config-card__specs"><?php if ( $platform ) : ?><span><?php echo esc_html( $platform ); ?></span><?php endif; ?><?php if ( $version ) : ?><span>v<?php echo esc_html( $version ); ?></span><?php endif; ?></div>
		<a class="tc-text-link" href="<?php the_permalink(); ?>">View config details <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</div>
</article>

