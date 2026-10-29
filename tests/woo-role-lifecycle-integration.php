<?php
/**
 * Execute the real Woo bridge against isolated in-memory payment/Discord fixtures.
 * No credentials, WordPress database, HTTP requests or real subscription changes.
 */
declare( strict_types=1 );
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAYSUBS_VERSION', '2.0.9' );
define( 'TIMONCONFIGS_WOO_DISCORD_BOT_TOKEN', 'FAKE_NONPRODUCTION_TOKEN' );
const TEST_USER = 101;
const TEST_MEMBER = '222222222222222222';
const TEST_GUILD = '111111111111111111';
const TEST_MONTHLY = '333333333333333333';
const TEST_LIFETIME = '444444444444444444';
const TEST_FREE = '555555555555555555';
const TEST_STAFF = '666666666666666666';
const TEST_STAFF_ROLE = '777777777777777777';
$GLOBALS['meta'] = array();
$GLOBALS['post_meta'] = array();
$GLOBALS['orders'] = array();
$GLOBALS['subscriptions'] = array();
$GLOBALS['member_roles'] = array( TEST_FREE );
$GLOBALS['requests'] = array();
$GLOBALS['cancellations'] = array();
$GLOBALS['audit_role'] = '';
$GLOBALS['audit_at'] = time() + 1;
$GLOBALS['subscription_failure'] = false;
$GLOBALS['cancel_failure'] = false;
$GLOBALS['schedule_failure'] = false;
$GLOBALS['assertions'] = 0;

function absint( $value ): int { return abs( (int) $value ); }
function get_option( $key, $default = null ) {
    if ( 'timonconfigs_woo' === $key ) { return array( 'enabled' => 1, 'monthly_product' => 427, 'lifetime_product' => 430 ); }
    if ( 'timonconfigs_discord' === $key ) { return array( 'guild_id' => TEST_GUILD, 'monthly_role' => TEST_MONTHLY, 'lifetime_role' => TEST_LIFETIME, 'free_role' => TEST_FREE, 'staff_role' => TEST_STAFF_ROLE ); }
    if ( '_tc_discord_binding_' . TEST_MEMBER === $key ) { return TEST_USER; }
    return $default;
}
function get_user_meta( $user, $key, $single = true ) {
    if ( '_tc_discord_id' === $key && TEST_USER === $user ) { return TEST_MEMBER; }
    return $GLOBALS['meta'][ $user ][ $key ] ?? '';
}
function update_user_meta( $user, $key, $value ): bool {
    $changed = ( $GLOBALS['meta'][ $user ][ $key ] ?? null ) !== $value;
    $GLOBALS['meta'][ $user ][ $key ] = $value;
    return $changed;
}
function delete_user_meta( $user, $key ): bool { unset( $GLOBALS['meta'][ $user ][ $key ] ); return true; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['post_meta'][ $id ][ $key ] ?? ''; }
function did_action( $action ): int { return 'arraysubs_after_boot' === $action ? 1 : 0; }
function esc_html( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_attr( $value ): string { return esc_html( $value ); }
function esc_url( $value ): string { return (string) $value; }
function admin_url( $path ): string { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_field( $action, $name, $referer, $echo ): string { return '<input name="_wpnonce" value="fake-test-nonce">'; }
function wp_date( $format, $at ): string { return gmdate( $format, $at ); }
function is_user_logged_in(): bool { return true; }
function get_current_user_id(): int { return TEST_USER; }
function check_admin_referer( $action ): void {}
function home_url( $path ): string { return 'https://example.test' . $path; }
final class TestRedirect extends RuntimeException {
    public function __construct( public string $url ) { parent::__construct( 'Intercept test redirect' ); }
}
function wp_safe_redirect( $url, $status = 302 ): void { throw new TestRedirect( $url ); }
function arraysubs_is_subscription_product( $id ): bool { return 427 === $id; }
function arraysubs_get_product_subscription_data( $id ): array { return array(); }
function arraysubs_get_subscription( $id ) { return $GLOBALS['subscriptions'][ $id ] ?? null; }
function arraysubs_get_customer_subscriptions( $uid, $statuses ): array {
    if ( $GLOBALS['subscription_failure'] ) { throw new RuntimeException( 'Simulated dependency read failure' ); }
    return array_keys( array_filter( $GLOBALS['subscriptions'], static fn( $sub ): bool => in_array( $sub->post_status, $statuses, true ) ) );
}
function arraysubs_is_waiting_cancellation( $id ): bool { return false; }
function arraysubs_cancel_subscription_immediately( $id, $reason, $actor ): bool {
    $GLOBALS['cancellations'][] = $id;
    if ( $GLOBALS['cancel_failure'] ) { throw new RuntimeException( 'Simulated native cancellation exception' ); }
    $GLOBALS['subscriptions'][ $id ]->post_status = 'arraysubs-cancelled';
    return true;
}
function arraysubs_schedule_cancellation( $id, $reason, $actor ): bool {
    if ( $GLOBALS['schedule_failure'] ) { throw new RuntimeException( 'Simulated scheduling exception' ); }
    return true;
}
function wc_get_product( $id ) { return null; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? null; }
function wc_get_orders( $query ): array {
    return array_values( array_filter( $GLOBALS['orders'], static fn( $order ): bool => $order->get_customer_id() === $query['customer_id'] ) );
}
class WP_Error {
    public function __construct( private string $code, private string $message = '' ) {}
    public function get_error_code(): string { return $this->code; }
}
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ): int { return $response['code']; }
function wp_remote_retrieve_body( $response ): string { return $response['body']; }
function wp_remote_request( $url, $args ): array {
    $method = $args['method'];
    $GLOBALS['requests'][] = array( $method, $url );
    if ( str_contains( $url, '/audit-logs?' ) ) {
        $entries = array();
        if ( $GLOBALS['audit_role'] ) {
            $entries[] = array( 'id' => (string) ( ( $GLOBALS['audit_at'] - 1420070400 ) * 1000 * 4194304 ), 'target_id' => TEST_MEMBER, 'user_id' => TEST_STAFF, 'changes' => array( array( 'key' => '$remove', 'new_value' => array( array( 'id' => $GLOBALS['audit_role'] ) ) ) ) );
        }
        return array( 'code' => 200, 'body' => json_encode( array( 'audit_log_entries' => $entries ) ) );
    }
    if ( str_ends_with( $url, '/members/' . TEST_STAFF ) ) {
        return array( 'code' => 200, 'body' => json_encode( array( 'user' => array( 'id' => TEST_STAFF, 'bot' => false ), 'roles' => array( TEST_STAFF_ROLE ) ) ) );
    }
    if ( preg_match( '~/roles/([0-9]+)$~', $url, $match ) ) {
        if ( 'PUT' === $method ) { $GLOBALS['member_roles'][] = $match[1]; }
        if ( 'DELETE' === $method ) { $GLOBALS['member_roles'] = array_values( array_diff( $GLOBALS['member_roles'], array( $match[1] ) ) ); }
        return array( 'code' => 204, 'body' => '' );
    }
    return array( 'code' => 200, 'body' => json_encode( array( 'user' => array( 'id' => TEST_MEMBER ), 'roles' => $GLOBALS['member_roles'] ) ) );
}
final class TestItem {
    public function __construct( private int $pid ) {}
    public function get_product_id(): int { return $this->pid; }
}
final class TestOrder {
    public function __construct( private int $pid, private int $paid_at ) {}
    public function get_customer_id(): int { return TEST_USER; }
    public function get_date_paid(): DateTimeImmutable { return ( new DateTimeImmutable() )->setTimestamp( $this->paid_at ); }
    public function is_paid(): bool { return true; }
    public function get_items( $type ): array { return array( new TestItem( $this->pid ) ); }
    public function get_total(): string { return 430 === $this->pid ? '80.00' : '29.99'; }
    public function get_total_refunded(): string { return '0.00'; }
}
function verify( bool $passed, string $label ): void {
    ++$GLOBALS['assertions'];
    if ( ! $passed ) { fwrite( STDERR, 'FAIL: ' . $label . "\n" ); exit( 1 ); }
}
function mutations(): array { return array_values( array_filter( $GLOBALS['requests'], static fn( $r ): bool => 'GET' !== $r[0] ) ); }
function monthly_fixture( int $paid_at ): void {
    $GLOBALS['orders'][22] = new TestOrder( 427, $paid_at );
    $GLOBALS['subscriptions'][11] = (object) array( 'post_status' => 'arraysubs-active' );
    $GLOBALS['post_meta'][11] = array( '_customer_id' => TEST_USER, '_product_id' => 427, '_billing_period' => 'month', '_billing_interval' => 1, '_currency' => 'EUR', '_recurring_amount' => '29.99', '_completed_payments' => 1, '_trial_length' => 0, '_parent_order_id' => 22, '_next_payment_date' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) );
}
require __DIR__ . '/../wp-content/plugins/timonconfigs-core/includes/class-tc-woocommerce.php';

$GLOBALS['orders'][21] = new TestOrder( 430, time() - 100 );
$access = TC_Woo_Bridge::paid_access( TEST_USER );
verify( $access['lifetime'] && ! $access['unknown'], 'Paid Lifetime can be read without a critical error' );
TC_Woo_Bridge::sync_user( TEST_USER );
verify( in_array( TEST_LIFETIME, $GLOBALS['member_roles'], true ), 'Paid Lifetime receives real bridge role grant' );
verify( ! in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Free role removed after successful Lifetime grant' );
$grant = get_user_meta( TEST_USER, '_tc_woo_role_granted_at', true );
verify( is_array( $grant ) && $grant['lifetime'] > 0, 'Lifetime grant timestamp persisted without undefined constants' );
verify( count( $GLOBALS['orders'] ) === 1, 'Grant preserves financial history' );

$GLOBALS['member_roles'] = array();
$GLOBALS['audit_role'] = TEST_LIFETIME;
TC_Woo_Bridge::sync_user( TEST_USER );
verify( ! TC_Woo_Bridge::paid_access( TEST_USER )['lifetime'], 'Verified moderator removal blocks old Lifetime purchase' );
verify( ! in_array( TEST_LIFETIME, $GLOBALS['member_roles'], true ), 'Old paid order cannot restore revoked Lifetime' );
verify( in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Moderated member receives Free fallback' );
verify( count( $GLOBALS['orders'] ) === 1 && ! $GLOBALS['cancellations'], 'Lifetime revocation does not delete or cancel financial records' );
$revoked = get_user_meta( TEST_USER, '_tc_woo_revoked', true );
$GLOBALS['orders'][23] = new TestOrder( 430, $revoked['lifetime'] + 1 );
$GLOBALS['audit_role'] = '';
TC_Woo_Bridge::sync_user( TEST_USER );
verify( in_array( TEST_LIFETIME, $GLOBALS['member_roles'], true ), 'A separate later Lifetime purchase restores entitlement' );

// Independent Monthly customer, with no historic Lifetime orders or revocations.
$GLOBALS['meta'] = array(); $GLOBALS['orders'] = array(); $GLOBALS['member_roles'] = array( TEST_FREE );
monthly_fixture( time() - 100 );
$access = TC_Woo_Bridge::paid_access( TEST_USER );
verify( $access['monthly'] && ! $access['unknown'], 'Verified Monthly subscription remains readable' );
TC_Woo_Bridge::sync_user( TEST_USER );
verify( in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && ! in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Monthly grant replaces Free' );
verify( ! empty( get_user_meta( TEST_USER, '_tc_woo_role_granted_at', true )['monthly'] ), 'Monthly grant timestamp persisted' );
$GLOBALS['post_meta'][11]['_next_payment_date'] = gmdate( 'Y-m-d H:i:s', time() - 10 );
TC_Woo_Bridge::sync_user( TEST_USER );
verify( ! in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Expired Monthly removes VIP and restores Free' );
verify( ! $GLOBALS['cancellations'], 'Natural expiry does not trigger moderator cancellation' );

monthly_fixture( time() - 50 );
TC_Woo_Bridge::sync_user( TEST_USER );
$GLOBALS['member_roles'] = array();
$GLOBALS['audit_role'] = TEST_MONTHLY;
$granted = get_user_meta( TEST_USER, '_tc_woo_role_granted_at', true );
$GLOBALS['audit_at'] = $granted['monthly'] - 1;
$before = count( mutations() );
TC_Woo_Bridge::sync_user( TEST_USER );
verify( count( mutations() ) === $before && ! $GLOBALS['cancellations'], 'Older audit entry cannot cancel a newly granted subscription' );
verify( get_user_meta( TEST_USER, '_tc_woo_sync_error', true ) === 'vip_role_missing_manual_review_required', 'Unproven removal requires review without restoring VIP' );
$GLOBALS['audit_at'] = time() + 1;
TC_Woo_Bridge::sync_user( TEST_USER );
verify( $GLOBALS['cancellations'] === array( 11 ), 'Verified Staff removal requests native Monthly cancellation once' );
verify( ! TC_Woo_Bridge::paid_access( TEST_USER )['monthly'], 'Moderated Monthly has no old paid entitlement' );
verify( ! in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Moderated Monthly stays Free without restoring VIP' );
verify( isset( $GLOBALS['orders'][22] ), 'Moderator cancellation preserves original payment order' );

// Lifetime does not cancel an existing recurring Monthly agreement automatically.
$GLOBALS['meta'] = array(); $GLOBALS['orders'] = array();
monthly_fixture( time() - 20 );
$GLOBALS['orders'][24] = new TestOrder( 430, time() - 10 );
$account = TC_Woo_Bridge::account( TEST_USER );
verify( str_contains( $account, 'VIP Lifetime' ), 'Lifetime membership is displayed on member profile' );
verify( str_contains( $account, 'Cancel monthly subscription' ) && str_contains( $account, 'tc_subscription_id' ), 'Lifetime buyer can still cancel their existing Monthly renewals' );

// Unexpected dependency read errors fail closed while preserving page/process availability.
$GLOBALS['subscription_failure'] = true;
$before = count( mutations() );
$access = TC_Woo_Bridge::paid_access( TEST_USER );
verify( $access['unknown'] && ! $access['monthly'], 'ArraySubs read exception returns unavailable entitlement without fatal' );
TC_Woo_Bridge::sync_user( TEST_USER );
verify( count( mutations() ) === $before, 'Unknown subscription state performs no Discord writes' );
verify( get_user_meta( TEST_USER, '_tc_woo_sync_error', true ) === 'arraysubs_read_failed', 'Unknown subscription state records safe diagnostic' );
$account = TC_Woo_Bridge::account( TEST_USER );
verify( str_contains( $account, 'Membership verification pending' ) && str_contains( $account, 'Please contact support' ), 'Dependency read exception renders profile pending/help without fatal' );
verify( str_contains( $account, 'Subscription controls are temporarily unavailable' ) && str_ends_with( $account, '</section>' ), 'Unavailable subscription controls preserve complete page markup' );
verify( ! str_contains( $account, 'No current paid membership' ) && ! str_contains( $account, 'Cancel monthly subscription' ), 'Read failure never falsely claims no subscription or exposes unverified controls' );

// Dependency cancellation failure must preserve the local cutoff and financial record,
// while explicitly leaving recurring billing for support review.
$GLOBALS['subscription_failure'] = false;
$GLOBALS['meta'] = array(); $GLOBALS['orders'] = array(); $GLOBALS['member_roles'] = array( TEST_FREE );
$GLOBALS['cancellations'] = array(); $GLOBALS['audit_role'] = '';
monthly_fixture( time() - 100 );
TC_Woo_Bridge::sync_user( TEST_USER );
$GLOBALS['member_roles'] = array(); $GLOBALS['audit_role'] = TEST_MONTHLY; $GLOBALS['audit_at'] = time() + 1;
$GLOBALS['cancel_failure'] = true;
TC_Woo_Bridge::sync_user( TEST_USER );
verify( get_user_meta( TEST_USER, '_tc_woo_sync_error', true ) === 'moderation_remote_payment_cancellation_requires_review', 'Native cancellation exception becomes safe billing review diagnostic' );
verify( $GLOBALS['subscriptions'][11]->post_status === 'arraysubs-active', 'Failed native cancellation is not marked cancelled' );
verify( ! TC_Woo_Bridge::paid_access( TEST_USER )['monthly'] && isset( $GLOBALS['orders'][22] ), 'Cancellation exception retains revocation cutoff and financial history' );
verify( in_array( TEST_FREE, $GLOBALS['member_roles'], true ) && ! in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ), 'Failed cancellation never restores moderated VIP' );

$GLOBALS['schedule_failure'] = true;
$_POST['tc_subscription_id'] = 11;
try {
    TC_Woo_Bridge::cancel_monthly();
    verify( false, 'Customer scheduling error must redirect safely' );
} catch ( TestRedirect $redirect ) {
    verify( str_ends_with( $redirect->url, '/account/?tc_cancel=failed' ), 'Customer scheduling exception returns to profile with failed status' );
}
verify( get_user_meta( TEST_USER, '_tc_woo_cancel_error', true ) === 'subscription_cancellation_requires_review', 'Customer cancellation exception records safe diagnostic' );
echo 'Woo role lifecycle: ' . $GLOBALS['assertions'] . " assertions PASS (in-memory providers, no HTTP or DB).\n";
