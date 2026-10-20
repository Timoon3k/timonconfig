<?php
/**
 * Editorial content managed in the block editor.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Editorial {
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_content_types' ) );
	}

	public static function register_content_types(): void {
		register_post_type(
			'tc_review',
			array(
				'labels' => array(
					'name'          => __( 'Reviews', 'timonconfigs-core' ),
					'singular_name' => __( 'Review', 'timonconfigs-core' ),
					'add_new_item'  => __( 'Add verified review', 'timonconfigs-core' ),
					'edit_item'     => __( 'Edit review', 'timonconfigs-core' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'publicly_queryable' => false,
				'menu_icon'          => 'dashicons-star-filled',
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions' ),
			)
		);

		register_post_type(
			'tc_faq',
			array(
				'labels' => array(
					'name'          => __( 'FAQs', 'timonconfigs-core' ),
					'singular_name' => __( 'FAQ', 'timonconfigs-core' ),
					'add_new_item'  => __( 'Add FAQ', 'timonconfigs-core' ),
					'edit_item'     => __( 'Edit FAQ', 'timonconfigs-core' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'publicly_queryable' => false,
				'menu_icon'          => 'dashicons-editor-help',
				'supports'           => array( 'title', 'editor', 'page-attributes', 'revisions' ),
			)
		);
	}
}
