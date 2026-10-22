<?php
/** Cookie Policy. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
get_template_part( 'template-parts/legal-page', null, array( 'policy' => 'cookies' ) );
get_footer();
