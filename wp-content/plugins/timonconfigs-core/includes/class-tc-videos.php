<?php
/**
 * First-party Apex showcase content managed in Gutenberg.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Videos {
	private const POST_TYPE = 'tc_video';
	private const NONCE = 'tc_video_details_nonce';
	private const META = array(
		'tc_video_url'         => 'sanitize_video_url',
		'tc_video_device'      => 'sanitize_label',
		'tc_video_poster'      => 'sanitize_media_url',
		'tc_video_upload_date' => 'sanitize_upload_date',
		'tc_video_duration'    => 'sanitize_duration',
		'tc_video_captions'    => 'sanitize_captions_url',
		'tc_video_mode'        => 'sanitize_mode',
	);

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_content_type' ) );
		add_action( 'add_meta_boxes_tc_video', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_tc_video', array( __CLASS__, 'save_meta' ) );
	}

	public static function register_content_type(): void {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Apex videos', 'timonconfigs-core' ),
				'singular_name' => __( 'Apex video', 'timonconfigs-core' ),
				'add_new_item' => __( 'Add first-party Apex video', 'timonconfigs-core' ),
				'edit_item' => __( 'Edit Apex video', 'timonconfigs-core' ),
			),
			'public' => false,
			'show_ui' => true,
			'show_in_rest' => true,
			'publicly_queryable' => false,
			'menu_icon' => 'dashicons-video-alt3',
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions' ),
		) );
		foreach ( self::META as $key => $method ) {
			register_post_meta( self::POST_TYPE, $key, array(
				'type' => 'string', 'single' => true, 'show_in_rest' => true,
				'sanitize_callback' => array( __CLASS__, $method ),
				'auth_callback' => static fn( mixed $allowed, string $meta_key, int $object_id ): bool => current_user_can( 'edit_post', $object_id ),
			) );
		}
		register_post_meta( self::POST_TYPE, 'tc_video_verified', array(
			'type' => 'boolean', 'single' => true, 'show_in_rest' => true,
			'sanitize_callback' => static fn( mixed $value ): bool => is_scalar( $value ) && rest_sanitize_boolean( $value ),
			'auth_callback' => static fn( mixed $allowed, string $meta_key, int $object_id ): bool => current_user_can( 'edit_post', $object_id ),
		) );
	}

	public static function sanitize_label( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	private static function safe_public_url( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$url = esc_url_raw( (string) $value, array( 'https', 'http' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}
		$home = wp_parse_url( home_url( '/' ) );
		$same_origin = is_array( $home ) && strtolower( $parts['host'] ) === strtolower( $home['host'] ?? '' ) && ( $parts['scheme'] ?? '' ) === ( $home['scheme'] ?? '' ) && ( $parts['port'] ?? null ) === ( $home['port'] ?? null );
		return 'https' === ( $parts['scheme'] ?? '' ) || $same_origin ? $url : '';
	}

	public static function youtube_id( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$parts = wp_parse_url( (string) $value );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return '';
		}
		$host = strtolower( $parts['host'] ?? '' );
		$path = $parts['path'] ?? '';
		$id = '';
		if ( in_array( $host, array( 'youtu.be', 'www.youtu.be' ), true ) ) {
			$id = ltrim( $path, '/' );
		} elseif ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com' ), true ) ) {
			if ( '/watch' === $path ) {
				parse_str( $parts['query'] ?? '', $query );
				$id = isset( $query['v'] ) && is_string( $query['v'] ) ? $query['v'] : '';
			} elseif ( preg_match( '#^/(?:embed|shorts)/([A-Za-z0-9_-]{11})/?$#', $path, $matches ) ) {
				$id = $matches[1];
			}
		}
		return preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? $id : '';
	}

	public static function sanitize_video_url( mixed $value ): string {
		$url = self::safe_public_url( $value );
		if ( '' === $url ) {
			return '';
		}
		$youtube_id = self::youtube_id( $url );
		if ( $youtube_id ) {
			return 'https://www.youtube.com/watch?v=' . $youtube_id;
		}
		return preg_match( '/\.mp4$/i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ? $url : '';
	}

	public static function sanitize_media_url( mixed $value ): string {
		$url = self::safe_public_url( $value );
		return preg_match( '/\.(?:avif|webp|png|jpe?g)$/i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ? $url : '';
	}

	public static function sanitize_captions_url( mixed $value ): string {
		$url = self::safe_public_url( $value );
		return preg_match( '/\.vtt$/i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ? $url : '';
	}

	public static function sanitize_upload_date( mixed $value ): string {
		$value = self::sanitize_label( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value ) ) {
			return '';
		}
		try {
			$date = new DateTimeImmutable( $value );
			$errors = DateTimeImmutable::getLastErrors();
			return $errors && ( $errors['warning_count'] || $errors['error_count'] ) ? '' : $date->format( DATE_ATOM );
		} catch ( Exception $error ) {
			return '';
		}
	}

	public static function sanitize_duration( mixed $value ): string {
		$value = self::sanitize_label( $value );
		return preg_match( '/^PT(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d+)?S)?$/', $value ) && ! preg_match( '/^PT(?:0H)?(?:0M)?(?:0(?:\.0+)?S)?$/', $value ) ? $value : '';
	}

	public static function sanitize_mode( mixed $value ): string {
		return 'modal' === $value ? 'modal' : 'inline';
	}

	public static function add_meta_box(): void {
		add_meta_box( 'tc_video_details', __( 'Apex video details', 'timonconfigs-core' ), array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'tc_save_video_details', self::NONCE );
		$fields = array(
			'tc_video_url' => array( 'Video URL — first-party MP4 or YouTube', 'url', 'https://…/apex-demo.mp4' ),
			'tc_video_device' => array( 'Device / input label — publish only confirmed mapping', 'text', 'Cronus Zen S30 / XIM Matrix Controller / XIM Matrix MnK' ),
			'tc_video_poster' => array( 'Poster image URL (or use the featured image)', 'url', 'https://…/poster.webp' ),
			'tc_video_upload_date' => array( 'Actual publication date (ISO 8601, with time zone)', 'text', '2026-10-21T12:00:00+00:00' ),
			'tc_video_duration' => array( 'Actual duration (ISO 8601)', 'text', 'PT37S' ),
			'tc_video_captions' => array( 'English captions URL (.vtt), where available', 'url', 'https://…/captions.vtt' ),
		);
		echo '<p>' . esc_html__( 'Publish only your own Apex footage. Source, device mapping and recording metadata must be checked before enabling video structured data. The first three published videos appear in order.', 'timonconfigs-core' ) . '</p>';
		foreach ( $fields as $key => $field ) {
			printf( '<p><label for="%1$s"><strong>%2$s</strong></label><br><input class="widefat" id="%1$s" name="%1$s" type="%3$s" value="%4$s" placeholder="%5$s"></p>', esc_attr( $key ), esc_html( $field[0] ), esc_attr( $field[1] ), esc_attr( get_post_meta( $post->ID, $key, true ) ), esc_attr( $field[2] ) );
		}
		$mode = self::sanitize_mode( get_post_meta( $post->ID, 'tc_video_mode', true ) );
		?>
		<p><label for="tc_video_mode"><strong><?php esc_html_e( 'Playback mode', 'timonconfigs-core' ); ?></strong></label><br><select id="tc_video_mode" name="tc_video_mode"><option value="inline" <?php selected( $mode, 'inline' ); ?>>Inline card (default)</option><option value="modal" <?php selected( $mode, 'modal' ); ?>>Optional modal</option></select></p>
		<p><label><input type="checkbox" name="tc_video_verified" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'tc_video_verified', true ) ); ?>> <?php esc_html_e( 'First-party source and recording metadata have been verified', 'timonconfigs-core' ); ?></label></p>
		<?php
	}

	public static function save_meta( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$nonce = isset( $_POST[ self::NONCE ] ) ? self::sanitize_label( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'tc_save_video_details' ) ) {
			return;
		}
		foreach ( self::META as $key => $method ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, call_user_func( array( __CLASS__, $method ), wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		update_post_meta( $post_id, 'tc_video_verified', isset( $_POST['tc_video_verified'] ) && '1' === $_POST['tc_video_verified'] );
	}

	/** @return array<int, array<string,mixed>> */
	public static function get_public_videos(): array {
		$posts = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 3, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
		$videos = array();
		foreach ( $posts as $post ) {
			$url = self::sanitize_video_url( get_post_meta( $post->ID, 'tc_video_url', true ) );
			if ( ! $url ) {
				continue;
			}
			$youtube_id = self::youtube_id( $url );
			$poster = self::sanitize_media_url( get_post_meta( $post->ID, 'tc_video_poster', true ) );
			$videos[] = array(
				'id' => $post->ID, 'title' => get_the_title( $post ),
				'description' => wp_strip_all_tags( get_the_excerpt( $post ) ?: $post->post_content ),
				'url' => $url, 'poster' => $poster ?: (string) get_the_post_thumbnail_url( $post, 'large' ),
				'device' => self::sanitize_label( get_post_meta( $post->ID, 'tc_video_device', true ) ),
				'upload_date' => self::sanitize_upload_date( get_post_meta( $post->ID, 'tc_video_upload_date', true ) ),
				'duration' => self::sanitize_duration( get_post_meta( $post->ID, 'tc_video_duration', true ) ),
				'verified' => (bool) get_post_meta( $post->ID, 'tc_video_verified', true ),
				'captions' => self::sanitize_captions_url( get_post_meta( $post->ID, 'tc_video_captions', true ) ),
				'mode' => self::sanitize_mode( get_post_meta( $post->ID, 'tc_video_mode', true ) ),
				'type' => $youtube_id ? 'youtube' : 'mp4', 'youtube_id' => $youtube_id,
			);
		}
		if ( $posts ) {
			return $videos;
		}
		for ( $index = 1; $index <= 3; $index++ ) {
			$url = self::sanitize_video_url( get_theme_mod( 'timonconfigs_video_' . $index . '_url', get_template_directory_uri() . '/assets/video/apex-demo-0' . $index . '.mp4' ) );
			if ( ! $url ) {
				continue;
			}
			$youtube_id = self::youtube_id( $url );
			$videos[] = array(
				'id' => 'fallback-' . $index,
				'title' => get_theme_mod( 'timonconfigs_video_' . $index . '_title', sprintf( 'Apex Legends Gameplay %02d', $index ) ),
				'description' => 'First-party TimonConfigs Apex Legends gameplay demonstration.',
				'url' => $url,
				'poster' => self::sanitize_media_url( get_theme_mod( 'timonconfigs_video_' . $index . '_poster', get_template_directory_uri() . '/assets/images/apex-demo-0' . $index . '.jpg' ) ),
				'device' => get_theme_mod( 'timonconfigs_video_' . $index . '_device', 'Apex Legends · Gameplay showcase' ),
				'upload_date' => '', 'duration' => '', 'verified' => false, 'captions' => '', 'mode' => 'inline',
				'type' => $youtube_id ? 'youtube' : 'mp4', 'youtube_id' => $youtube_id,
			);
		}
		return $videos;
	}
}
