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
				'title'       => __( 'TimonConfigs site content', 'timonconfigs' ),
				'description' => __( 'Global links, homepage copy and replaceable media. FAQs and reviews use their own editor menus.', 'timonconfigs' ),
				'priority'    => 30,
			)
		);

		$fields = array(
			'hero_eyebrow' => array( 'label' => __( 'Hero eyebrow', 'timonconfigs' ), 'default' => 'PRECISION × CONSISTENCY × REAL SETUPS' ),
			'hero_title'   => array( 'label' => __( 'Hero title', 'timonconfigs' ), 'default' => 'Elite Configs for Serious Players' ),
			'hero_tagline' => array( 'label' => __( 'Hero tagline', 'timonconfigs' ), 'default' => 'Play Smarter. Hit Different.' ),
			'hero_copy'    => array( 'label' => __( 'Hero description', 'timonconfigs' ), 'default' => 'Professional configs for Cronus Zen and XIM Matrix. Find the right setup, explore real gameplay, and join the TimonConfigs community.' ),
			'hero_cta'     => array( 'label' => __( 'Hero primary button label', 'timonconfigs' ), 'default' => 'Get Started' ),
			'trust_access' => array( 'label' => __( 'Hero benefit 1', 'timonconfigs' ), 'default' => 'Instant Access' ),
			'trust_updates'=> array( 'label' => __( 'Hero benefit 2', 'timonconfigs' ), 'default' => 'Regular Updates' ),
			'trust_support'=> array( 'label' => __( 'Hero benefit 3', 'timonconfigs' ), 'default' => 'Active Support' ),
			'final_title'  => array( 'label' => __( 'Final CTA title', 'timonconfigs' ), 'default' => 'Join the TimonConfigs Community' ),
			'final_copy'   => array( 'label' => __( 'Final CTA description', 'timonconfigs' ), 'default' => 'Get access to premium configs, regular updates, and an active community.' ),
			'discord_url'  => array( 'label' => __( 'Discord community URL', 'timonconfigs' ), 'default' => '', 'url' => true ),
			'x_url'        => array( 'label' => __( 'X profile URL', 'timonconfigs' ), 'default' => '', 'url' => true ),
			'youtube_url'  => array( 'label' => __( 'YouTube channel URL', 'timonconfigs' ), 'default' => '', 'url' => true ),
			'instagram_url'=> array( 'label' => __( 'Instagram profile URL', 'timonconfigs' ), 'default' => '', 'url' => true ),
			'creator_url'  => array( 'label' => __( 'Website creator URL', 'timonconfigs' ), 'default' => 'https://majewskitomasz.pl/', 'url' => true ),
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
					'type'    => isset( $field['url'] ) ? 'url' : ( in_array( $key, array( 'hero_copy', 'final_copy' ), true ) ? 'textarea' : 'text' ),
				)
			);
		}


		$manager->add_setting( 'timonconfigs_hero_image', array(
			'default' => get_template_directory_uri() . '/assets/images/hero-reference-wide.webp',
			'sanitize_callback' => 'esc_url_raw',
		) );
		$manager->add_control( new WP_Customize_Image_Control( $manager, 'timonconfigs_hero_image', array(
			'label' => __( 'Hero background artwork', 'timonconfigs' ),
			'section' => 'timonconfigs_home',
		) ) );

		$game_images = array(
			'apex_legends'      => 'Apex Legends',
			'call_of_duty'      => 'Call of Duty',
			'fortnite'          => 'Fortnite',
			'rainbow_six_siege' => 'Rainbow Six Siege',
			'rust'              => 'Rust',
			'ea_sports_fc'      => 'EA Sports FC',
			'nba_2k'            => 'NBA 2K',
		);
		foreach ( $game_images as $game_slug => $game_name ) {
			$asset_slug = str_replace( '_', '-', $game_slug );
			$setting    = 'timonconfigs_game_' . $game_slug . '_image';
			$manager->add_setting( $setting, array(
				'default'           => get_template_directory_uri() . '/assets/images/game-' . $asset_slug . '.webp',
				'sanitize_callback' => 'esc_url_raw',
			) );
			$manager->add_control( new WP_Customize_Image_Control( $manager, $setting, array(
				'label'   => sprintf( __( '%s cover artwork', 'timonconfigs' ), $game_name ),
				'section' => 'timonconfigs_home',
			) ) );
		}

		$plan_fields = array(
			'free' => array( 'name' => 'Free', 'tagline' => 'Perfect to get started', 'price' => '€0', 'suffix' => 'Forever', 'description' => 'Explore selected configs and join the community.', 'features' => "Selected free configurations\nBasic config library\nCommunity Discord access", 'cta' => 'Get Free Configs' ),
			'monthly' => array( 'name' => 'VIP Monthly', 'tagline' => 'For serious players', 'price' => '€40', 'suffix' => '/ month', 'description' => 'Full premium access with ongoing updates and VIP support.', 'features' => "Premium configuration library\nRegular updates covered\nPrivate VIP Discord channels\nMonthly recurring access", 'cta' => 'Get Started' ),
			'lifetime' => array( 'name' => 'VIP Lifetime', 'tagline' => 'One payment. Lifetime license.', 'price' => '€100', 'suffix' => 'one time', 'description' => 'A one-time license for long-term access under the Lifetime terms.', 'features' => "Premium configuration library\nUpdates covered by your license\nPrivate VIP Discord channels\nOne-time payment", 'cta' => 'Get Lifetime Access' ),
		);
		foreach ( $plan_fields as $plan_slug => $values ) {
			foreach ( $values as $field_name => $default ) {
				$setting = 'timonconfigs_plan_' . $plan_slug . '_' . $field_name;
				$manager->add_setting( $setting, array( 'default' => $default, 'sanitize_callback' => 'features' === $field_name ? 'sanitize_textarea_field' : 'sanitize_text_field' ) );
				$manager->add_control( $setting, array( 'label' => sprintf( __( '%1$s — %2$s', 'timonconfigs' ), $values['name'], ucfirst( $field_name ) ), 'section' => 'timonconfigs_home', 'type' => in_array( $field_name, array( 'description', 'features' ), true ) ? 'textarea' : 'text' ) );
			}
		}

		for ( $index = 1; $index <= 3; $index++ ) {
			$manager->add_setting(
				'timonconfigs_video_' . $index . '_title',
				array(
					'default'           => sprintf( 'Apex Legends Gameplay %02d', $index ),
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
					'default'           => __( 'Apex Legends · Gameplay showcase', 'timonconfigs' ),
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


			$manager->add_setting( 'timonconfigs_video_' . $index . '_poster', array(
				'default' => get_template_directory_uri() . '/assets/images/apex-demo-0' . $index . '.jpg',
				'sanitize_callback' => 'esc_url_raw',
			) );
			$manager->add_control( new WP_Customize_Image_Control( $manager, 'timonconfigs_video_' . $index . '_poster', array(
				'label' => sprintf( __( 'Apex video %d poster', 'timonconfigs' ), $index ),
				'section' => 'timonconfigs_home',
			) ) );

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

