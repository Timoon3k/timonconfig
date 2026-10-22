<?php
/**
 * Editorial content managed in the block editor.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Editorial {
	private const REVIEW_NONCE = 'tc_review_details_nonce';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_content_types' ) );
		add_action( 'add_meta_boxes_tc_review', array( __CLASS__, 'add_review_meta_box' ) );
		add_action( 'save_post_tc_review', array( __CLASS__, 'save_review_meta' ) );
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
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions' ),
			)
		);

		register_post_meta( 'tc_review', 'tc_review_rating', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_rating' ), 'auth_callback' => static fn() => current_user_can( 'edit_posts' ) ) );
		register_post_meta( 'tc_review', 'tc_review_config', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn() => current_user_can( 'edit_posts' ) ) );
		register_post_meta( 'tc_review', 'tc_review_demo', array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'rest_sanitize_boolean', 'auth_callback' => static fn() => current_user_can( 'edit_posts' ) ) );

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

	public static function sanitize_rating( mixed $rating ): int {
		return min( 5, max( 1, absint( $rating ) ) );
	}

	public static function add_review_meta_box(): void {
		add_meta_box( 'tc_review_details', __( 'Review details', 'timonconfigs-core' ), array( __CLASS__, 'render_review_meta_box' ), 'tc_review', 'side', 'high' );
	}

	public static function render_review_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'tc_save_review_details', self::REVIEW_NONCE );
		$rating = self::sanitize_rating( get_post_meta( $post->ID, 'tc_review_rating', true ) ?: 5 );
		$config = (string) get_post_meta( $post->ID, 'tc_review_config', true );
		$demo   = (bool) get_post_meta( $post->ID, 'tc_review_demo', true );
		?>
		<p><label for="tc_review_rating"><strong><?php esc_html_e( 'Rating', 'timonconfigs-core' ); ?></strong></label><br><select id="tc_review_rating" name="tc_review_rating"><?php for ( $value = 5; $value >= 1; $value-- ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rating, $value ); ?>><?php echo esc_html( $value ); ?> / 5</option><?php endfor; ?></select></p>
		<p><label for="tc_review_config"><strong><?php esc_html_e( 'Config or device', 'timonconfigs-core' ); ?></strong></label><input class="widefat" id="tc_review_config" name="tc_review_config" value="<?php echo esc_attr( $config ); ?>" placeholder="Apex Legends · XIM Matrix"></p>
		<p><label><input type="checkbox" name="tc_review_demo" value="1" <?php checked( $demo ); ?>> <?php esc_html_e( 'Mark as Sample Review / Demo Content', 'timonconfigs-core' ); ?></label></p>
		<?php
	}

	public static function save_review_meta( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$nonce = isset( $_POST[ self::REVIEW_NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::REVIEW_NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'tc_save_review_details' ) ) {
			return;
		}
		$rating = isset( $_POST['tc_review_rating'] ) ? self::sanitize_rating( wp_unslash( $_POST['tc_review_rating'] ) ) : 5;
		$config = isset( $_POST['tc_review_config'] ) ? sanitize_text_field( wp_unslash( $_POST['tc_review_config'] ) ) : '';
		update_post_meta( $post_id, 'tc_review_rating', $rating );
		update_post_meta( $post_id, 'tc_review_config', $config );
		update_post_meta( $post_id, 'tc_review_demo', isset( $_POST['tc_review_demo'] ) );
	}
}
