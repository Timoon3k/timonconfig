<?php
/**
 * Website membership checkout routes.
 *
 * @package TimonConfigs_Core
 */

defined( 'ABSPATH' ) || exit;

final class TC_Checkout {
	private const QUERY_VAR   = 'tc_checkout_plan';
	private const PLANS       = array( 'monthly', 'lifetime' );

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ) );
	}

	public static function register_rewrite_rules(): void {
		add_rewrite_rule(
			'^checkout/(monthly|lifetime)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param string[] $query_vars Public query variables.
	 * @return string[]
	 */
	public static function register_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;
		return $query_vars;
	}

	public static function maybe_redirect(): void {
		$raw_plan = get_query_var( self::QUERY_VAR );
		if ( ! is_scalar( $raw_plan ) ) {
			self::render_unavailable();
		}
		$plan = sanitize_key( (string) $raw_plan );

		if ( '' === $plan ) {
			return;
		}

		if ( ! in_array( $plan, self::PLANS, true ) ) {
			self::render_unavailable();
		}

		if ( class_exists( 'TC_Billing' ) ) { TC_Billing::render_checkout( $plan ); }
		self::render_unavailable();
	}

	public static function get_checkout_url( string $plan ): string {
		$plan = sanitize_key( $plan );
		if ( ! in_array( $plan, self::PLANS, true ) ) {
			return '';
		}

		return home_url( '/checkout/' . $plan . '/' );
	}

	public static function get_destination( string $plan ): string {
		// Retired external checkout API. Stored historical options are never used.
		return '';
	}

	public static function sanitize_checkout_url( $url ): string {
		// Compatibility only: new purchases cannot configure external destinations.
		return '';
	}

	private static function render_unavailable(): void {
		$status = 503;
		wp_die(
			esc_html__( 'Checkout is temporarily unavailable. Please contact us on Discord for help.', 'timonconfigs-core' ),
			esc_html__( 'Checkout unavailable', 'timonconfigs-core' ),
			array(
				'response' => $status,
				'link_url' => home_url( '/pricing/' ),
				'link_text' => esc_html__( 'Return to pricing', 'timonconfigs-core' ),
			)
		);
		exit;
	}
}

/**
 * Public theme helper for a controlled checkout URL.
 */
function timonconfigs_checkout_url( string $plan ): string {
	return TC_Checkout::get_checkout_url( $plan );
}

