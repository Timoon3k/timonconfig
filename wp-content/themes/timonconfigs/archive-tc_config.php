<?php
/** Config catalogue and taxonomy archive. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();

$is_term  = is_tax( array( 'tc_game', 'tc_device' ) );
$term     = $is_term ? get_queried_object() : null;
$headline = $is_term && $term instanceof WP_Term ? $term->name . ' Configs' : 'Find the right config for your setup';
$intro    = $is_term && $term instanceof WP_Term && $term->description
	? $term->description
	: 'Browse verified TimonConfigs releases by game, device, input method, and access tier. Each entry lists its real compatibility and update status.';
$games    = get_terms( array( 'taxonomy' => 'tc_game', 'hide_empty' => false ) );
$devices  = get_terms( array( 'taxonomy' => 'tc_device', 'hide_empty' => false ) );
$games    = is_wp_error( $games ) ? array() : $games;
$devices  = is_wp_error( $devices ) ? array() : $devices;
?>
<main id="main">
	<section class="tc-page-hero tc-page-hero--catalog"><div class="tc-shell"><?php timonconfigs_breadcrumbs(); ?><p class="tc-eyebrow"><span></span><?php echo $is_term ? 'CONFIG COLLECTION' : 'CONFIG LIBRARY'; ?></p><h1><?php echo esc_html( $headline ); ?></h1><p class="tc-lead"><?php echo esc_html( $intro ); ?></p></div></section>
	<section class="tc-section tc-section--topless">
		<div class="tc-shell">
			<form class="tc-catalog-filter" action="<?php echo esc_url( get_post_type_archive_link( 'tc_config' ) ); ?>" method="get" role="search">
				<label><span>Search</span><input type="search" name="config_search" value="<?php echo esc_attr( isset( $_GET['config_search'] ) ? sanitize_text_field( wp_unslash( $_GET['config_search'] ) ) : '' ); ?>" placeholder="Search configs…"></label>
				<label><span>Game</span><select name="game"><option value="">All games</option><?php foreach ( $games as $game ) : ?><option value="<?php echo esc_attr( $game->slug ); ?>" <?php selected( isset( $_GET['game'] ) ? sanitize_title( wp_unslash( $_GET['game'] ) ) : '', $game->slug ); ?>><?php echo esc_html( $game->name ); ?></option><?php endforeach; ?></select></label>
				<label><span>Device</span><select name="device"><option value="">All devices</option><?php foreach ( $devices as $device ) : ?><option value="<?php echo esc_attr( $device->slug ); ?>" <?php selected( isset( $_GET['device'] ) ? sanitize_title( wp_unslash( $_GET['device'] ) ) : '', $device->slug ); ?>><?php echo esc_html( $device->name ); ?></option><?php endforeach; ?></select></label>
				<label><span>Access</span><select name="tier"><option value="">All tiers</option><?php foreach ( array( 'free' => 'Free', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $_GET['tier'] ) ? sanitize_key( wp_unslash( $_GET['tier'] ) ) : '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<button class="tc-button" type="submit">Apply filters</button>
			</form>

			<?php if ( have_posts() ) : ?>
				<div class="tc-config-grid"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/config-card' ); endwhile; ?></div>
				<div class="tc-pagination"><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => 'Previous', 'next_text' => 'Next' ) ); ?></div>
			<?php else : ?>
				<div class="tc-empty-state"><p class="tc-eyebrow"><span></span>NO MATCHES</p><h2>No configs match these filters yet.</h2><p>Try a broader search or check back after the next verified release.</p><a class="tc-button tc-button--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'tc_config' ) ); ?>">Clear filters</a></div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>

