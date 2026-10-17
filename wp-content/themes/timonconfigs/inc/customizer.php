<?php
/**
 * Theme Customizer fields.
 *
 * @package TimonConfigs
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $manager ): void {
		$manager->add_section(
			'timonconfigs_home',
			array(
				'title'       => __( 'TimonConfigs homepage', 'timonconfigs' ),
				'description' => __( 'Core homepage copy and media. Pages remain editable in the block editor.', 'timonconfigs' ),
				'priority'    => 30,
			)
		);

		$fields = array(
			'hero_eyebrow' => array( 'label' => __( 'Hero eyebrow', 'timonconfigs' ), 'default' => 'BUILT FOR PLAYERS WHO WANT MORE' ),
			'hero_title'   => array( 'label' => __( 'Hero title', 'timonconfigs' ), 'default' => 'Precision configs. Competitive confidence.' ),
			'hero_copy'    => array( 'label' => __( 'Hero description', 'timonconfigs' ), 'default' => 'Premium Cronus Zen and XIM Matrix configurations, refined for responsive control and supported by a focused Discord community.' ),
			'discord_url'  => array( 'label' => __( 'Discord community URL', 'timonconfigs' ), 'default' => '', 'url' => true ),
		);

		foreach ( $fields as $key => $field ) {
			$manager->add_setting(
				'timonconfigs_' . $key,
				array(
					'default'           => $field['default'],
					'sanitize_callback' => isset( $field['url'] ) ? 'esc_url_raw' : 'sanitize_text_field',
				)
			);
			$manager->add_control(
				'timonconfigs_' . $key,
				array(
					'label'   => $field['label'],
					'section' => 'timonconfigs_home',
					'type'    => isset( $field['url'] ) ? 'url' : ( 'hero_copy' === $key ? 'textarea' : 'text' ),
				)
			);
		}

		for ( $index = 1; $index <= 3; $index++ ) {
			$manager->add_setting(
				'timonconfigs_video_' . $index . '_title',
				array(
					'default'           => sprintf( 'Apex Legends Gameplay Demo %02d', $index ),
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			$manager->add_control(
				'timonconfigs_video_' . $index . '_title',
				array(
					'label'   => sprintf( __( 'Apex video %d title', 'timonconfigs' ), $index ),
					'section' => 'timonconfigs_home',
				)
			);

			$manager->add_setting(
				'timonconfigs_video_' . $index . '_device',
				array(
					'default'           => __( 'Device details pending verification', 'timonconfigs' ),
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			$manager->add_control(
				'timonconfigs_video_' . $index . '_device',
				array(
					'label'   => sprintf( __( 'Apex video %d device label', 'timonconfigs' ), $index ),
					'section' => 'timonconfigs_home',
				)
			);

			$manager->add_setting(
				'timonconfigs_video_' . $index . '_url',
				array(
					'default'           => get_template_directory_uri() . '/assets/video/apex-demo-0' . $index . '.mp4',
					'sanitize_callback' => 'esc_url_raw',
				)
			);
			$manager->add_control(
				new WP_Customize_Upload_Control(
					$manager,
					'timonconfigs_video_' . $index . '_url',
					array(
						'label'     => sprintf( __( 'Apex video %d MP4', 'timonconfigs' ), $index ),
						'section'   => 'timonconfigs_home',
					)
				)
			);
		}
	}
);

