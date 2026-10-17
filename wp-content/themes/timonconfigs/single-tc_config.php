<?php
/** Single config detail. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) : the_post();
	$config_id = get_the_ID();
	$tier      = get_post_meta( $config_id, 'tc_tier', true ) ?: 'free';
	$platform  = get_post_meta( $config_id, 'tc_platform', true );
	$input     = get_post_meta( $config_id, 'tc_input_method', true );
	$version   = get_post_meta( $config_id, 'tc_config_version', true );
	$updated   = get_post_meta( $config_id, 'tc_last_updated', true );
	$devices   = get_the_terms( $config_id, 'tc_device' );
	$games     = get_the_terms( $config_id, 'tc_game' );
	$premium   = 'free' !== $tier;
	?>
	<main id="main">
		<article <?php post_class( 'tc-config-single' ); ?>>
			<header class="tc-page-hero"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><div class="tc-config-single__hero"><div><p class="tc-eyebrow"><span></span><?php echo esc_html( $games && ! is_wp_error( $games ) ? $games[0]->name : 'CONFIG' ); ?></p><h1><?php the_title(); ?></h1><p class="tc-lead"><?php echo esc_html( get_the_excerpt() ); ?></p><div class="tc-config-single__tags"><span><?php echo esc_html( $premium ? ( 'lifetime' === $tier ? 'VIP Lifetime' : 'VIP Monthly' ) : 'Free' ); ?></span><?php if ( $devices && ! is_wp_error( $devices ) ) : ?><span><?php echo esc_html( $devices[0]->name ); ?></span><?php endif; ?><?php if ( $input ) : ?><span><?php echo esc_html( 'mnk' === $input ? 'MnK' : ucfirst( $input ) ); ?></span><?php endif; ?></div></div><?php if ( has_post_thumbnail() ) : ?><div class="tc-config-single__image"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?></div></div></header>
			<div class="tc-shell tc-config-single__layout">
				<div class="tc-entry__content"><h2>About this config</h2><?php the_content(); ?></div>
				<aside class="tc-config-specs"><p class="tc-price-card__name">CONFIG DETAILS</p><dl><?php if ( $platform ) : ?><div><dt>Platforms</dt><dd><?php echo esc_html( $platform ); ?></dd></div><?php endif; ?><?php if ( $version ) : ?><div><dt>Version</dt><dd><?php echo esc_html( $version ); ?></dd></div><?php endif; ?><?php if ( $updated ) : ?><div><dt>Last verified</dt><dd><time datetime="<?php echo esc_attr( $updated ); ?>"><?php echo esc_html( $updated ); ?></time></dd></div><?php endif; ?><div><dt>Access</dt><dd><?php echo esc_html( $premium ? 'VIP membership' : 'Free member' ); ?></dd></div></dl><?php if ( $premium ) : ?><a class="tc-button" href="<?php echo esc_url( timonconfigs_plan_url( 'lifetime' === $tier ? 'lifetime' : 'monthly' ) ); ?>">View access options <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><p>Premium files are delivered only after server-side membership verification.</p><?php else : ?><a class="tc-button" href="<?php echo esc_url( get_theme_mod( 'timonconfigs_discord_url', home_url( '/contact/' ) ) ); ?>">Get free access <?php echo timonconfigs_icon( 'arrow' ); // phpcs:ignore ?></a><?php endif; ?></aside>
			</div>
		</article>
	</main>
	<?php
endwhile;
get_footer();

