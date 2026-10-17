<?php
/** VIP Monthly landing page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
get_template_part( 'template-parts/vip-plan', null, array( 'plan' => 'monthly' ) );
get_footer();
