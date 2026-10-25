<?php
/** Private billing views; payment fields are hosted by the provider. */
defined( 'ABSPATH' ) || exit;

final class TC_Billing_UI {
	private static function start( string $operation, array $fields = array(), bool $admin = false ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tc-billing-actions"><input type="hidden" name="action" value="' . ( $admin ? 'tc_billing_admin' : 'tc_billing_action' ) . '"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
		wp_nonce_field( $admin ? 'tc_billing_admin' : 'tc_billing_action' );
		foreach ( $fields as $key => $value ) { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">'; }
	}
	private static function date( mixed $date ): string { return is_string( $date ) && '' !== $date ? $date . ' UTC' : '—'; }
	public static function checkout( string $plan ): string {
		$product = TC_Billing::plan( $plan ); if ( ! in_array( $plan, array( 'monthly', 'lifetime' ), true ) || ! $product ) { return '<p>Choose a membership from Pricing.</p>'; }
		$identity = TC_Billing::identity(); $config = TC_Billing::config();
		ob_start();
		echo '<section class="tc-billing tc-billing-checkout"><p class="tc-eyebrow">TIMONCONFIGS MEMBERSHIP</p><h1>' . esc_html( $product['name'] ) . '</h1><p>' . esc_html( TC_Billing::display_price( $plan ) ) . ( 'monthly' === $plan ? ' / month. Renews automatically until canceled.' : ' one time. No monthly renewal.' ) . '</p>';
		if ( ! TC_Billing::direct_enabled() ) {
			echo '<p class="tc-billing-notice" role="status">Online checkout is not available yet. No payment can be made from this page.</p><p>For help, open a support ticket in our Discord server. Payments are completed on this website after checkout is enabled.</p><a class="tc-button tc-button--ghost" href="' . esc_url( home_url( '/contact/' ) ) . '">Get membership support</a> <a href="' . esc_url( home_url( '/pricing/' ) ) . '">Back to memberships</a></section>';
			return (string) ob_get_clean();
		}
		if ( 'test' === $config['mode'] ) { echo '<p class="tc-billing-notice" role="status">Test checkout — use test payment details only. This purchase does not confirm a live membership.</p>'; }
		if ( 'pilot' === $config['routing'] ) { echo '<p class="tc-billing-notice" role="status">Restricted Live pilot. Payments are real: €29.99 per month or €80 once. Provider fees apply.</p>'; }
		if ( is_wp_error( $identity ) ) {
			echo '<p>Continue with Discord to securely link this purchase to your member account.</p><a class="tc-button" href="' . esc_url( TC_Discord::login_url() ) . '">Continue with Discord</a><p>After signing in, return to this membership checkout.</p></section>';
			return (string) ob_get_clean();
		}
		if ( ! TC_Billing::current_user_can_purchase() ) { echo '<p class="tc-billing-notice" role="status">This Live pilot is limited to its invited Discord account.</p><a href="' . esc_url( home_url( '/pricing/' ) ) . '">Back to memberships</a></section>'; return (string) ob_get_clean(); }
		echo '<p>Purchase for Discord account <strong>' . esc_html( $identity['discord_id'] ) . '</strong>.</p>';
		$intent = isset( $_GET['tc_billing_intent'] ) && is_string( $_GET['tc_billing_intent'] ) && preg_match( '/^[a-f0-9]{64}$/D', $_GET['tc_billing_intent'] ) ? $_GET['tc_billing_intent'] : '';
		foreach ( array( 'stripe' => 'Continue with Stripe', 'paypal' => 'Continue with PayPal' ) as $provider => $label ) {
			if ( ! TC_Billing::checkout_provider_ready( $provider, $config['mode'], $plan ) ) { continue; }
			self::start( 'checkout', array( 'plan' => $plan, 'provider' => $provider, 'intent' => $intent ) );
			echo '<label><input type="checkbox" name="consent" value="1" required><span>' . ( 'monthly' === $plan ? 'I authorize recurring monthly payments of €29.99 until I cancel auto-renewal.' : 'I agree to a one-time €80 purchase under the Lifetime license terms.' ) . ' I have reviewed the <a href="' . esc_url( home_url( '/terms-of-service/' ) ) . '">Terms</a>, <a href="' . esc_url( home_url( '/refund-policy/' ) ) . '">Refund Policy</a> and <a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '">Privacy Policy</a>.</span></label><button class="tc-button" type="submit">' . esc_html( $label ) . '</button></form>';
		}
		if ( ! TC_Billing::provider_ready( 'stripe', $config['mode'] ) ) { echo '<p class="tc-billing-notice">Payment setup is not complete. Contact support before purchasing.</p>'; }
		echo '<p>Card and wallet availability is determined by the payment provider. Buying Lifetime does not cancel an existing subscription; manage its renewal separately. Access opens after verified payment and Discord synchronization.</p><a href="' . esc_url( home_url( '/pricing/' ) ) . '">Back to memberships</a></section>';
		return (string) ob_get_clean();
	}
	public static function account( int $uid ): string {
		if ( ! is_user_logged_in() || $uid !== get_current_user_id() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return ''; }
		$config = TC_Billing::config(); $mode = $config['mode']; $orders = TC_Billing_Store::orders_for_user( $uid, $mode ); $entitlements = TC_Billing_Store::entitlements( $uid, $mode );
		if ( is_wp_error( $entitlements ) ) { return '<section class="tc-billing"><p class="tc-billing-notice" role="status">Billing details are temporarily unavailable. Please try again shortly.</p></section>'; }
		if ( ! TC_Billing::direct_enabled() && ! $orders && ! $entitlements ) { return ''; }
		TC_Billing::headers();
		ob_start(); echo '<section class="tc-billing" aria-labelledby="tc-billing-heading"><h2 id="tc-billing-heading">Payments &amp; membership</h2>';
		if ( 'test' === $mode ) { echo '<p class="tc-billing-notice" role="status">Test environment. Test orders are separate from live purchases.</p>'; }
		$feedback = get_transient( 'tc_billing_feedback_' . $uid );
		if ( is_string( $feedback ) && '' !== $feedback ) { delete_transient( 'tc_billing_feedback_' . $uid ); echo '<p class="tc-billing-notice" role="status">' . esc_html( $feedback ) . '</p>'; }
		if ( isset( $_GET['tc_billing_return'] ) ) { echo '<p class="tc-billing-notice" role="status">Payment return received. Your verified billing status is shown below. Returning here does not activate membership.</p>'; }
		$sync = TC_Billing_Store::sync_state( $uid, $mode );
		$sync_label = array( 'done' => 'Up to date', 'pending' => 'Pending', 'error' => 'Retry pending' );
		echo '<p>Discord sync: <strong class="tc-billing-status" data-status="' . esc_attr( $sync['status'] ?? '' ) . '">' . esc_html( $sync_label[ $sync['status'] ?? '' ] ?? 'No pending payment updates' ) . '</strong></p>';
		$has_lifetime = false;
		foreach ( $entitlements as $source ) {
			$has_lifetime = $has_lifetime || ( 'lifetime' === $source['plan'] && 'active' === $source['status'] );
			echo '<article class="tc-billing-card"><h3>' . esc_html( TC_Billing::plan( $source['plan'] )['name'] ?? 'Membership' ) . '</h3><dl><div><dt>Provider</dt><dd>' . esc_html( ucfirst( $source['provider'] ) ) . '</dd></div><div><dt>Payment access</dt><dd>' . esc_html( ( 'active' === $source['status'] && 'monthly' === $source['plan'] && ! empty( $source['valid_until'] ) && $source['valid_until'] <= gmdate( 'Y-m-d H:i:s' ) ? 'expired' : $source['status'] ) ) . '</dd></div><div><dt>Paid access until</dt><dd>' . esc_html( 'lifetime' === $source['plan'] && 'active' === $source['status'] ? 'Lifetime license — no monthly expiry' : self::date( $source['valid_until'] ?? null ) ) . '</dd></div><div><dt>Next payment</dt><dd>' . esc_html( ! empty( $source['cancel_at_period_end'] ) ? 'Auto-renewal canceled' : self::date( $source['next_payment_at'] ?? null ) ) . '</dd></div></dl></article>';
		}
		if ( ! $entitlements ) { echo '<p>No verified direct purchase yet. Your current Discord roles still determine library access.</p>'; }
		echo '<h3>Order history</h3><div class="tc-billing-table-wrap"><table class="tc-billing-table"><thead><tr><th scope="col">Date</th><th scope="col">Membership</th><th scope="col">Provider</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead><tbody>';
		foreach ( $orders as $order ) {
			echo '<tr><td>' . esc_html( self::date( $order['created_at'] ) ) . '</td><td>' . esc_html( TC_Billing::plan( $order['plan'] )['name'] ) . '</td><td>' . esc_html( ucfirst( $order['provider'] ) ) . '</td><td>€' . esc_html( number_format( (int) $order['amount'] / 100, 2, '.', '' ) ) . '</td><td>' . esc_html( $order['status'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		$managed = array();
		foreach ( $orders as $order ) {
			$contract = $order['provider'] . ':' . ( $order['subscription_id'] ?: $order['id'] );
			if ( isset( $managed[ $contract ] ) ) { continue; } $managed[ $contract ] = true;
			echo '<article class="tc-billing-card"><h3>' . esc_html( TC_Billing::plan( $order['plan'] )['name'] ) . ' · ' . esc_html( ucfirst( $order['provider'] ) ) . '</h3>';
			if ( 'monthly' === $order['plan'] && $order['subscription_id'] ) {
				self::start( 'portal', array( 'order_id' => $order['id'] ) ); echo '<button class="tc-button tc-button--ghost" type="submit">Manage Subscription</button></form>';
				self::start( 'cancel', array( 'order_id' => $order['id'] ) ); echo '<label><input type="checkbox" name="confirm_cancel" value="1" required> Stop future renewals; keep verified prepaid access until its end date.</label><button class="tc-button tc-button--ghost" type="submit">Cancel Auto-Renewal</button></form>';
			}
			if ( 'paypal' === $order['provider'] && 'lifetime' === $order['plan'] && in_array( $order['status'], array( 'pending', 'checkout' ), true ) ) {
				self::start( 'capture', array( 'order_id' => $order['id'] ) ); echo '<button class="tc-button" type="submit">Complete approved PayPal payment</button></form>';
			}
			self::start( 'refresh', array( 'order_id' => $order['id'] ) ); echo '<button class="tc-button tc-button--ghost" type="submit">Refresh payment status</button></form></article>';
		}
		echo '<div class="tc-billing-actions">' . ( $has_lifetime ? '' : '<a class="tc-button" href="' . esc_url( TC_Checkout::get_checkout_url( 'lifetime' ) ) . '">Upgrade to Lifetime</a>' ) . '<a href="' . esc_url( home_url( '/contact/' ) ) . '">Membership support</a></div><p>For an earlier purchase or missing access, open a support ticket in our Discord server. A new purchase does not cancel an existing subscription. Complete payments through this website; a ticket message does not confirm payment.</p></section>';
		return (string) ob_get_clean();
	}
	private static function credentials(): void {
		$labels = array( 'STRIPE_SECRET_KEY' => 'Stripe Secret key', 'STRIPE_WEBHOOK_SECRET' => 'Stripe webhook Signing secret', 'DISCORD_BOT_TOKEN' => 'Discord Bot token', 'PAYPAL_CLIENT_ID' => 'PayPal Client ID', 'PAYPAL_CLIENT_SECRET' => 'PayPal Client secret' );
		echo '<section id="tc-billing-credentials"><h2>1. Save server credentials</h2><p>Keys stay on the server, encrypted with this WordPress installation’s keys. Saved values are never displayed. Leave a field blank to keep its current value. Changing WordPress security salts or the site address requires entering stored keys again.</p>';
		$feedback_key = 'tc_billing_credentials_feedback_' . get_current_user_id(); $feedback = get_transient( $feedback_key );
		if ( is_string( $feedback ) && '' !== $feedback ) { delete_transient( $feedback_key ); echo '<p role="status">' . esc_html( 'saved' === $feedback ? 'Credentials saved.' : 'Credentials could not be saved. Check HTTPS, permissions and stable server security salts, then try again.' ) . '</p>'; }
		$secure = TC_Billing_Secrets::secure_transport() && TC_Billing_Secrets::storage_available();
		if ( ! $secure ) { echo '<p role="status">Credential entry requires HTTPS and valid server-side WordPress security keys (AUTH_KEY and AUTH_SALT). Ask the hosting administrator to configure them if this notice persists.</p>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tc_billing_secrets">'; wp_nonce_field( 'tc_billing_secrets' );
		foreach ( array( 'live' => 'Live — real payments', 'test' => 'Test / Sandbox' ) as $mode => $title ) {
			echo '<details ' . ( TC_Billing::config()['mode'] === $mode ? 'open' : '' ) . '><summary><strong>' . esc_html( $title ) . '</strong></summary><table class="form-table">';
			foreach ( $labels as $name => $label ) {
				$info = TC_Billing_Secrets::info( $name, $mode ); $locked = ! $secure || 'constant' === $info['source'];
				$state = $info['unreadable'] ? 'Cannot decrypt — enter again' : ( $info['configured'] ? 'Configured' : 'Missing' );
				echo '<tr><th><label for="tc-key-' . esc_attr( $mode . '-' . $name ) . '">' . esc_html( $label ) . '</label></th><td><input type="password" class="regular-text" id="tc-key-' . esc_attr( $mode . '-' . $name ) . '" name="credentials[' . esc_attr( $mode ) . '][' . esc_attr( $name ) . ']" value="" autocomplete="new-password" ' . disabled( $locked, true, false ) . '><span> ' . esc_html( $state ) . '</span>';
				if ( 'constant' === $info['source'] ) { echo '<p>Managed by the server configuration.</p>'; }
				else { echo '<p><label><input type="checkbox" name="clear[' . esc_attr( $mode ) . '][' . esc_attr( $name ) . ']" value="1" ' . disabled( ! $secure, true, false ) . '> Clear this stored credential</label></p>'; }
				echo '</td></tr>';
			}
			echo '</table></details>';
		}
		submit_button( 'Save credentials', 'secondary', 'submit', true, $secure ? array() : array( 'disabled' => 'disabled' ) ); echo '</form></section>';
	}
	private static function stripe_setup(): void {
		$config = TC_Billing::config(); echo '<h2>2. Verify two Stripe prices</h2><p>Create Monthly €29.99/month and Lifetime €80 once in Stripe, then paste only their Price IDs. This check reads Stripe’s catalogue and fills in the Product IDs. It never creates a charge.</p>';
		self::start( 'stripe_setup', array(), true );
		echo '<p><label>Stripe account <select name="mode"><option value="live" ' . selected( $config['mode'], 'live', false ) . '>Live — real account</option><option value="test" ' . selected( $config['mode'], 'test', false ) . '>Test / Sandbox</option></select></label></p>';
		foreach ( array( 'monthly' => 'Monthly Price ID', 'lifetime' => 'Lifetime Price ID' ) as $plan => $label ) { echo '<p><label>' . esc_html( $label ) . ' <input class="regular-text" name="' . esc_attr( $plan . '_price_id' ) . '" placeholder="price_…" required></label></p>'; }
		echo '<button class="button" type="submit">Verify and save Stripe prices</button></form>';
	}
	public static function admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$config = TC_Billing::config(); echo '<div class="wrap"><h1>TimonConfigs billing</h1><p>Configure payments on this page. A single-account Live pilot can run on your main HTTPS site; only its invited Discord account can start real purchases. Keep public checkout disabled until broader payment and access checks are complete.</p>';
		$notice = get_transient( 'tc_billing_admin_' . get_current_user_id() ); if ( is_string( $notice ) ) { delete_transient( 'tc_billing_admin_' . get_current_user_id() ); echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>'; }
		self::credentials(); self::stripe_setup(); echo '<h2>3. Enable a restricted Live pilot</h2><p>Choose Live and Restricted Live pilot, enter your own Discord User ID, and confirm real charges. Existing Discord membership server/role IDs are reused when the Live billing role fields are empty. Enable role synchronization only after the bot is installed above the VIP roles. To stop new purchases, choose Checkout unavailable and keep Live selected for existing payments.</p><form method="post" action="options.php">'; settings_fields( 'tc_billing' );
		foreach ( array( 'mode' => array( 'test' => 'Test / Sandbox', 'live' => 'Live' ), 'routing' => array( 'disabled' => 'Checkout unavailable', 'pilot' => 'Restricted Live pilot — one account', 'direct' => 'Public website checkout' ) ) as $key => $options ) {
			echo '<p><label>' . esc_html( ucfirst( $key ) ) . ' <select name="timonconfigs_billing[' . esc_attr( $key ) . ']">';
			foreach ( $options as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $config[ $key ], $value, false ) . '>' . esc_html( $label ) . '</option>'; } echo '</select></label></p>';
		}
		echo '<p><label>Invited Discord User ID <input class="regular-text" name="timonconfigs_billing[pilot_discord_id]" value="' . esc_attr( $config['pilot_discord_id'] ) . '" pattern="[1-9][0-9]{16,19}" autocomplete="off"></label></p>';
		foreach ( array( 'live_pilot_enabled' => 'Enable the single-account Live pilot', 'pilot_real_charges_confirmed' => 'I authorize real €29.99 recurring or €80 one-time payments for this invited account, including provider fees; this does not confirm completed acceptance tests', 'stripe_test_confirmed' => 'I verified Stripe checkout, renewal, refund and cancellation in controlled Test or Live acceptance checks', 'migration_confirmed' => 'I reviewed earlier purchases and confirmed that valid access is preserved without duplicate subscriptions', 'legal_confirmed' => 'Seller identity, EUR VAT-inclusive pricing, invoices, renewal consent and EU withdrawal/refund policies were reviewed for this actual business', 'paypal_enabled' => 'Enable PayPal after verified Stripe acceptance', 'role_sync_enabled' => 'Enable configured VIP role synchronization' ) as $key => $label ) { echo '<p><label><input type="checkbox" name="timonconfigs_billing[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $config[ $key ] ), true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
		foreach ( array( 'test', 'live' ) as $mode ) {
			echo '<h2>' . esc_html( ucfirst( $mode ) ) . ' configuration</h2><table class="form-table">';
			foreach ( TC_Billing::public_keys() as $key ) { echo '<tr><th><label for="tc-billing-' . esc_attr( $mode . '-' . $key ) . '">' . esc_html( str_replace( '_', ' ', $key ) ) . '</label></th><td><input class="regular-text" id="tc-billing-' . esc_attr( $mode . '-' . $key ) . '" name="timonconfigs_billing[' . esc_attr( $mode . '_' . $key ) . ']" value="' . esc_attr( TC_Billing::setting( $key, $mode ) ) . '"></td></tr>'; }
			echo '</table><p>Server credentials: ';
			foreach ( array( 'STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET', 'PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'DISCORD_BOT_TOKEN' ) as $name ) { echo '<code>' . esc_html( $name ) . '</code> ' . ( '' !== TC_Billing::secret( $name, $mode ) ? 'configured' : 'missing' ) . ' · '; }
			echo '</p><p>Stripe webhook: <code>' . esc_html( rest_url( 'timonconfigs/v1/billing/stripe/' . $mode ) ) . '</code><br>PayPal webhook: <code>' . esc_html( rest_url( 'timonconfigs/v1/billing/paypal/' . $mode ) ) . '</code></p>';
		}
		submit_button(); echo '</form><h2>Queue and reconciliation</h2><p>Run server cron every 5 minutes; normal page traffic is not a reliable scheduler. Last worker run: ' . esc_html( (string) get_option( 'timonconfigs_billing_last_tick', 'Not recorded yet' ) ) . '</p>';
		$worker_error = get_option( 'timonconfigs_billing_worker_error', '' ); if ( is_string( $worker_error ) && '' !== $worker_error ) { echo '<p>Worker diagnostic: <code>' . esc_html( $worker_error ) . '</code></p>'; }
		$diagnostics = TC_Billing_Store::diagnostics();
		foreach ( array( 'events' => 'Provider notifications', 'jobs' => 'Discord synchronization' ) as $key => $title ) {
			echo '<h3>' . esc_html( $title ) . '</h3><table class="widefat"><thead><tr><th>ID</th><th>Status</th><th>Attempts</th><th>Safe diagnostic</th><th>Action</th></tr></thead><tbody>';
			foreach ( $diagnostics[ $key ] ?? array() as $row ) {
				echo '<tr><td>' . esc_html( (string) $row['id'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( (string) $row['attempts'] ) . '</td><td>' . esc_html( $row['last_error'] ?? '' ) . '</td><td>';
				self::start( 'events' === $key ? 'retry_event' : 'retry_sync', array( 'id' => $row['id'], 'user_id' => $row['user_id'] ?? 0, 'mode' => $row['mode'] ), true ); echo '<button type="submit" class="button">Retry safely</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<h2>Verified legacy access</h2><p>Enter only access verified from the real legacy purchase or approved grant. Do not invent paid dates. Test and Live reviews are separate. Existing external roles are never adopted automatically.</p>';
		self::start( 'external_grant', array(), true );
		echo '<p><label>WordPress user ID <input type="number" min="1" name="user_id" required></label></p><p><label>Mode <select name="mode"><option value="test">Test</option><option value="live">Live</option></select></label></p><p><label>Source <select name="source_provider"><option value="legacy">Verified legacy purchase</option><option value="grant">Approved giveaway / grant</option></select></label></p><p><label>Plan <select name="plan"><option value="monthly">Monthly</option><option value="lifetime">Lifetime</option></select></label></p><p><label>Paid access until (UTC; required for Monthly) <input name="valid_until" placeholder="2026-12-01 12:00:00"></label></p><p><label><input type="checkbox" name="confirm" value="1" required> I verified this entitlement from its actual source.</label></p><button class="button" type="submit">Record verified access</button></form>';
		self::start( 'review_legacy', array(), true );
		echo '<p><label>WordPress user ID <input type="number" min="1" name="user_id" required></label> <label>Mode <select name="mode"><option value="test">Test</option><option value="live">Live</option></select></label></p><p><label><input type="checkbox" name="confirm" value="1" required> I reviewed every independent purchase/grant and recorded all valid access above. Owned roles may now expire according to this inventory.</label></p><button class="button" type="submit">Confirm legacy inventory review</button></form></div>';
	}
}
