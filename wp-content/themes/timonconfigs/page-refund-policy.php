<?php
/** Refund page. @package TimonConfigs */
defined( 'ABSPATH' ) || exit;
get_header();
get_template_part( 'template-parts/legal-page', null, array( 'policy' => 'refund' ) );
get_footer();
