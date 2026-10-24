<?php
/** Compact, one-click config card. Downloads still use server-verified Discord access. */
defined( 'ABSPATH' ) || exit;

$config_id = get_the_ID();
$tier = (string) get_post_meta( $config_id, 'tc_tier', true ) ?: 'free';
$status = (string) get_post_meta( $config_id, 'tc_release_status', true );
$access = TC_Catalog::download_cta( $config_id );
$games = get_the_terms( $config_id, 'tc_game' );
$devices = get_the_terms( $config_id, 'tc_device' );
$game = $games && ! is_wp_error( $games ) ? $games[0] : null;
$device = $devices && ! is_wp_error( $devices ) ? $devices[0] : null;
$is_matrix = $device && 'xim-matrix' === $device->slug;
$cover = $game ? TC_Catalog::collection_image( $game ) : '';
$custom_cover = 'custom' === get_post_meta( $config_id, '_tc_card_art', true );
$show_thumbnail = has_post_thumbnail() && ( $custom_cover || ! $cover );
$ready = 'available' === $status;
$can_download = 'download' === ( $access['action'] ?? '' ) && ! empty( $access['files'][0]['id'] );
$format = $is_matrix ? 'TXT' : strtoupper( pathinfo( (string) ( $access['files'][0]['name'] ?? '' ), PATHINFO_EXTENSION ) );
$format_label = $format ? $format . ' FILE' : 'CONFIG';
$label = $can_download ? ( 'Download ' . $format_label ) : ( 'coming-soon' === $status ? 'Coming soon' : ( 'pending' === ( $access['action'] ?? '' ) ? 'No file available yet' : ( $access['label'] ?? 'Unavailable' ) ) );
$target = 'pending' === ( $access['action'] ?? '' ) ? get_permalink( $config_id ) : (string) ( $access['url'] ?? get_permalink( $config_id ) );
?>
<article <?php post_class( 'tc-config-card tc-config-card--compact tc-config-card--' . ( 'free' === $tier ? 'free' : 'vip' ) . ( 'pending' === ( $access['action'] ?? '' ) ? ' tc-config-card--pending' : '' ) ); ?>>
    <div class="tc-config-card__visual">
        <?php if ( $show_thumbnail ) : ?>
            <?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
        <?php elseif ( $cover ) : ?>
            <img src="<?php echo esc_url( $cover ); ?>" alt="" width="600" height="360" loading="lazy" decoding="async">
        <?php else : ?>
            <span class="tc-config-card__device"><?php echo esc_html( $device ? $device->name : 'TimonConfigs' ); ?></span>
        <?php endif; ?>
        <span class="tc-config-card__tier tc-config-card__tier--<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( 'free' === $tier ? 'FREE' : 'VIP' ); ?></span>
        <?php if ( ! $ready ) : ?><span class="tc-config-card__status"><?php echo esc_html( 'coming-soon' === $status ? 'Coming soon' : 'Not available' ); ?></span><?php endif; ?>
    </div>
    <div class="tc-config-card__body">
        <p class="tc-config-card__meta"><?php echo esc_html( implode( ' · ', array_filter( array( $game?->name, $device?->name ) ) ) ); ?></p>
        <h2><?php the_title(); ?></h2>
        <?php if ( is_callable( array( 'TC_Config_Content', 'buttons' ) ) ) { echo TC_Config_Content::buttons( $config_id ); /* Buttons open server-authorized content dialogs. */ } ?>
        <span class="tc-config-card__primary" aria-hidden="true"><?php echo esc_html( $label ); ?> <span aria-hidden="true">↓</span></span>
    </div>
    <?php if ( $can_download ) : ?>
        <?php TC_Downloads::render_button( $config_id, $access['files'][0]['id'], 'Download ' . get_the_title() . ( $is_matrix ? ' as TXT' : '' ) ); ?>
    <?php elseif ( 'pending' !== ( $access['action'] ?? '' ) ) : ?>
        <a class="tc-config-card__overlay-link" href="<?php echo esc_url( $target ); ?>" aria-label="<?php echo esc_attr( $label . ': ' . get_the_title() ); ?>"><span class="screen-reader-text"><?php echo esc_html( $label ); ?></span></a>
    <?php endif; ?>
</article>
