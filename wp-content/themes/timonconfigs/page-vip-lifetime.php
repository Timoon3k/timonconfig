<?php
/** VIP Lifetime landing page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
get_template_part( 'template-parts/vip-plan', null, array( 'plan' => 'lifetime' ) );
get_footer();
