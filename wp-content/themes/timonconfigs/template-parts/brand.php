<?php
/** Reusable brand wordmark. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
if ( has_custom_logo() ) {
    $logo = wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'full', false, array( 'class' => 'custom-logo', 'alt' => 'TimonConfigs' ) );
    echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
    $emblem = function_exists( 'timonconfigs_brand_emblem' ) ? timonconfigs_brand_emblem() : array( 'src' => get_template_directory_uri() . '/assets/images/timon-crimson-emblem.webp', 'width' => 1024, 'height' => 1024 );
	?><img class="tc-brand__emblem" src="<?php echo esc_url( $emblem['src'] ); ?>" alt="" width="<?php echo esc_attr( (string) $emblem['width'] ); ?>" height="<?php echo esc_attr( (string) $emblem['height'] ); ?>" decoding="async"><span class="tc-brand__identity"><strong class="tc-brand__name">Timon<span>Configs</span></strong><small>APEX CONFIG COMMUNITY</small></span><?php
}
