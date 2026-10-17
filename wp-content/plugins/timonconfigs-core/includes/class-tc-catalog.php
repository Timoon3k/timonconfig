<?php
/**
 * Config catalogue content model and admin fields.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Catalog {
	private const POST_TYPE = 'tc_config';
	private const NONCE     = 'tc_config_details_nonce';
	private const META      = array(
		'tc_tier'           => 'sanitize_key',
		'tc_platform'       => 'sanitize_text_field',
		'tc_input_method'   => 'sanitize_key',
		'tc_config_version' => 'sanitize_text_field',
		'tc_last_updated'   => array( __CLASS__, 'sanitize_date' ),
	);

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_content_types' ) );
		add_action( 'init', array( __CLASS__, 'register_landing_rewrites' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_archive' ) );
		add_filter( 'term_link', array( __CLASS__, 'filter_core_term_links' ), 10, 3 );
	}

	public static function register_landing_rewrites(): void {
		add_rewrite_rule( '^apex-legends-configs/page/([0-9]{1,})/?$', 'index.php?tc_game=apex-legends&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^apex-legends-configs/?$', 'index.php?tc_game=apex-legends', 'top' );
		add_rewrite_rule( '^cronus-zen-configs/page/([0-9]{1,})/?$', 'index.php?tc_device=cronus-zen&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^cronus-zen-configs/?$', 'index.php?tc_device=cronus-zen', 'top' );
		add_rewrite_rule( '^xim-matrix-configs/page/([0-9]{1,})/?$', 'index.php?tc_device=xim-matrix&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^xim-matrix-configs/?$', 'index.php?tc_device=xim-matrix', 'top' );
	}

	public static function sanitize_date( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return '';
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : '';
	}

	/**
	 * Keep the three primary SEO landings short and stable.
	 *
	 * @param string  $url      Generated term URL.
	 * @param WP_Term $term     Current term.
	 * @param string  $taxonomy Taxonomy name.
	 */
	public static function filter_core_term_links( string $url, WP_Term $term, string $taxonomy ): string {
		$paths = array(
			'tc_game:apex-legends' => '/apex-legends-configs/',
			'tc_device:cronus-zen' => '/cronus-zen-configs/',
			'tc_device:xim-matrix' => '/xim-matrix-configs/',
		);
		$key   = $taxonomy . ':' . $term->slug;

		return isset( $paths[ $key ] ) ? home_url( $paths[ $key ] ) : $url;
	}

	public static function register_content_types(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Configs', 'timonconfigs-core' ),
					'singular_name' => __( 'Config', 'timonconfigs-core' ),
					'add_new_item'  => __( 'Add new config', 'timonconfigs-core' ),
					'edit_item'     => __( 'Edit config', 'timonconfigs-core' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'configs',
				'rewrite'      => array( 'slug' => 'config', 'with_front' => false ),
				'menu_icon'    => 'dashicons-admin-generic',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_taxonomy(
			'tc_game',
			self::POST_TYPE,
			array(
				'labels'            => array( 'name' => __( 'Games', 'timonconfigs-core' ), 'singular_name' => __( 'Game', 'timonconfigs-core' ) ),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'games', 'with_front' => false ),
			)
		);

		register_taxonomy(
			'tc_device',
			self::POST_TYPE,
			array(
				'labels'            => array( 'name' => __( 'Devices', 'timonconfigs-core' ), 'singular_name' => __( 'Device', 'timonconfigs-core' ) ),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'devices', 'with_front' => false ),
			)
		);

		foreach ( self::META as $key => $sanitize_callback ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}

	public static function seed_core_terms(): void {
		if ( ! term_exists( 'Apex Legends', 'tc_game' ) ) {
			wp_insert_term( 'Apex Legends', 'tc_game', array( 'slug' => 'apex-legends' ) );
		}

		foreach ( array( 'Cronus Zen' => 'cronus-zen', 'XIM Matrix' => 'xim-matrix' ) as $name => $slug ) {
			if ( ! term_exists( $name, 'tc_device' ) ) {
				wp_insert_term( $name, 'tc_device', array( 'slug' => $slug ) );
			}
		}
	}

	public static function add_meta_box(): void {
		add_meta_box(
			'tc_config_details',
			esc_html__( 'Config details', 'timonconfigs-core' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'tc_save_config_details', self::NONCE );
		$values = array();
		foreach ( array_keys( self::META ) as $key ) {
			$values[ $key ] = (string) get_post_meta( $post->ID, $key, true );
		}
		?>
		<p><label for="tc_tier"><strong><?php esc_html_e( 'Access tier', 'timonconfigs-core' ); ?></strong></label><br>
		<select id="tc_tier" name="tc_tier">
			<?php foreach ( array( 'free' => 'Free', 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime' ) as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $values['tc_tier'], $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<p><label for="tc_platform"><strong><?php esc_html_e( 'Platforms', 'timonconfigs-core' ); ?></strong></label><br><input class="widefat" id="tc_platform" name="tc_platform" value="<?php echo esc_attr( $values['tc_platform'] ); ?>" placeholder="Xbox Series, PlayStation 5, PC"></p>
		<p><label for="tc_input_method"><strong><?php esc_html_e( 'Input method', 'timonconfigs-core' ); ?></strong></label><br>
		<select id="tc_input_method" name="tc_input_method"><option value="controller" <?php selected( $values['tc_input_method'], 'controller' ); ?>>Controller</option><option value="mnk" <?php selected( $values['tc_input_method'], 'mnk' ); ?>>Mouse & keyboard</option></select></p>
		<p><label for="tc_config_version"><strong><?php esc_html_e( 'Config version', 'timonconfigs-core' ); ?></strong></label><br><input id="tc_config_version" name="tc_config_version" value="<?php echo esc_attr( $values['tc_config_version'] ); ?>"></p>
		<p><label for="tc_last_updated"><strong><?php esc_html_e( 'Last verified date', 'timonconfigs-core' ); ?></strong></label><br><input type="date" id="tc_last_updated" name="tc_last_updated" value="<?php echo esc_attr( $values['tc_last_updated'] ); ?>"></p>
		<?php
	}

	public static function save_meta( int $post_id ): void {
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'tc_save_config_details' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		foreach ( self::META as $key => $sanitize_callback ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$value = call_user_func( $sanitize_callback, wp_unslash( $_POST[ $key ] ) );
			if ( 'tc_tier' === $key && ! in_array( $value, array( 'free', 'monthly', 'lifetime' ), true ) ) {
				$value = 'free';
			}
			if ( 'tc_input_method' === $key && ! in_array( $value, array( 'controller', 'mnk' ), true ) ) {
				$value = 'controller';
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	public static function filter_archive( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( self::POST_TYPE ) ) {
			return;
		}

		$query->set( 'posts_per_page', 12 );
		$query->set( 'orderby', 'modified' );
		$query->set( 'order', 'DESC' );

		$search = isset( $_GET['config_search'] ) ? sanitize_text_field( wp_unslash( $_GET['config_search'] ) ) : '';
		if ( '' !== $search ) {
			$query->set( 's', $search );
		}

		$tax_query = array();
		foreach ( array( 'tc_game' => 'game', 'tc_device' => 'device' ) as $taxonomy => $parameter ) {
			$slug = isset( $_GET[ $parameter ] ) ? sanitize_title( wp_unslash( $_GET[ $parameter ] ) ) : '';
			if ( '' !== $slug ) {
				$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug );
			}
		}
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}

		$tier = isset( $_GET['tier'] ) ? sanitize_key( wp_unslash( $_GET['tier'] ) ) : '';
		if ( in_array( $tier, array( 'free', 'monthly', 'lifetime' ), true ) ) {
			$query->set( 'meta_query', array( array( 'key' => 'tc_tier', 'value' => $tier ) ) );
		}
	}
}

