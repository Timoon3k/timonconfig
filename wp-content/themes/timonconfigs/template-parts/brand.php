<?php
/** Reusable brand wordmark. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
if ( has_custom_logo() ) {
    $logo = wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'full', false, array( 'class' => 'custom-logo', 'alt' => 'TimonConfigs' ) );
    echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
    ?><span class="tc-wordmark"><span>Timon</span>Configs</span><?php
}
