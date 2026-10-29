<?php
/** Website checkout links for Discord ticket messages. */
defined( 'ABSPATH' ) || exit;

final class TC_Settings {
	public static function init(): void {
		if ( is_admin() ) { add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) ); }
	}
	public static function add_settings_page(): void {
		add_options_page( 'TimonConfigs', 'TimonConfigs', 'manage_options', 'timonconfigs', array( __CLASS__, 'render_page' ) );
	}
	/** Retired external URL settings are not registered or changed. */
	public static function register_settings(): void {}
	public static function sanitize_urls( $value ): array { return array(); }
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap"><h1>TimonConfigs checkout links</h1><p>Purchases start on this website. Add these links to your Discord Ticket Tool messages; the ticket itself never confirms payment or grants VIP access.</p><table class="widefat"><thead><tr><th>Destination</th><th>Website link</th></tr></thead><tbody>';
		foreach ( array( 'Browse Memberships' => home_url( '/pricing/' ), 'VIP Monthly' => TC_Checkout::get_checkout_url( 'monthly' ), 'VIP Lifetime' => TC_Checkout::get_checkout_url( 'lifetime' ) ) as $label => $url ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><input class="large-text code" type="url" readonly aria-label="' . esc_attr( $label . ' website link' ) . '" value="' . esc_attr( $url ) . '"></td></tr>';
		}
		echo '</tbody></table><p>Use the same Discord account on the website. Payment and VIP access are confirmed by the server after the payment provider verifies the purchase.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'options-general.php?page=tc-woo' ) ) . '">Configure website payments</a></p><p>Online checkout remains unavailable until its environment and payment settings are ready. Existing purchase records and membership access are retained.</p></div>';
	}
}
