<?php
/** Reusable brand wordmark. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
if ( has_custom_logo() ) {
    $logo = wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'full', false, array( 'class' => 'custom-logo', 'alt' => 'TimonConfigs' ) );
    echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	?><img class="tc-brand__logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/timonconfigs-logo.png' ); ?>" alt="TimonConfigs" width="620" height="125" decoding="async"><?php
}
