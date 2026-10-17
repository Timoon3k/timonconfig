<?php
/**
 * Admin settings for TimonConfigs business integrations.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Settings {
	private const OPTION_NAME = 'timonconfigs_checkout_urls';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_settings_page(): void {
		add_options_page(
			esc_html__( 'TimonConfigs', 'timonconfigs-core' ),
			esc_html__( 'TimonConfigs', 'timonconfigs-core' ),
			'manage_options',
			'timonconfigs',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'timonconfigs',
			self::OPTION_NAME,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( __CLASS__, 'sanitize_urls' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'timonconfigs_checkout',
			esc_html__( 'Upgrade.chat checkout', 'timonconfigs-core' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Paste the real Upgrade.chat HTTPS checkout links. Visitors are sent through a validated local redirect.', 'timonconfigs-core' ) . '</p>';
			},
			'timonconfigs'
		);

		foreach ( array( 'monthly' => 'VIP Monthly', 'lifetime' => 'VIP Lifetime' ) as $key => $label ) {
			add_settings_field(
				'timonconfigs_' . $key,
				esc_html( $label ),
				array( __CLASS__, 'render_url_field' ),
				'timonconfigs',
				'timonconfigs_checkout',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * @param mixed $value Submitted option.
	 * @return array<string,string>
	 */
	public static function sanitize_urls( $value ): array {
		$clean = array();
		if ( ! is_array( $value ) ) {
			return $clean;
		}

		foreach ( array( 'monthly', 'lifetime' ) as $plan ) {
			$url = TC_Checkout::sanitize_checkout_url( $value[ $plan ] ?? '' );
			if ( '' !== $url ) {
				$clean[ $plan ] = $url;
			}
		}

		return $clean;
	}

	/** @param array{key:string} $args Field arguments. */
	public static function render_url_field( array $args ): void {
		$options = get_option( self::OPTION_NAME, array() );
		$key     = $args['key'];
		$value   = is_array( $options ) ? (string) ( $options[ $key ] ?? '' ) : '';
		printf(
			'<input class="regular-text" type="url" name="%1$s[%2$s]" value="%3$s" placeholder="https://upgrade.chat/…" inputmode="url" autocomplete="off">',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TimonConfigs settings', 'timonconfigs-core' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'timonconfigs' );
				do_settings_sections( 'timonconfigs' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

