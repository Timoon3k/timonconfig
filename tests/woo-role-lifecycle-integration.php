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
$GLOBALS['options'] = array();
$GLOBALS['pending_cancellation'] = false;
$GLOBALS['cancellation_date'] = '';
$GLOBALS['free_put_failure'] = false;
$GLOBALS['subscription_failure_after_cancel'] = false;
$GLOBALS['reenter_on_vip_put'] = false;
$GLOBALS['nested_requests'] = null;
$GLOBALS['replace_lease_on_vip_put'] = false;
$GLOBALS['cancel_during_vip_put'] = false;
$GLOBALS['replace_lease_on_compensating_delete'] = false;
$GLOBALS['queue'] = array();
$GLOBALS['as_queue'] = array();

/** Only the atomic option-lease SQL used by the bridge; never opens a database. */
final class TestWpdb {
    public string $options = 'wp_options';
    public function prepare( $sql, ...$args ): string { return json_encode( array( $sql, $args ), JSON_THROW_ON_ERROR ); }
    public function get_var( $prepared ) {
        list( $sql, $args ) = json_decode( $prepared, true, 512, JSON_THROW_ON_ERROR );
        if ( ! str_starts_with( $sql, 'SELECT option_value FROM wp_options WHERE option_name = %s' ) ) { throw new RuntimeException( 'Unexpected fixture read SQL.' ); }
        return $GLOBALS['options'][ $args[0] ] ?? null;
    }
    public function query( $prepared ): int {
        list( $sql, $args ) = json_decode( $prepared, true, 512, JSON_THROW_ON_ERROR );
        if ( ! str_starts_with( $sql, 'DELETE FROM wp_options WHERE option_name = %s AND option_value = %s' ) ) { throw new RuntimeException( 'Unexpected fixture write SQL.' ); }
        if ( ( $GLOBALS['options'][ $args[0] ] ?? null ) !== $args[1] ) { return 0; }
        unset( $GLOBALS['options'][ $args[0] ] );
        return 1;
    }
}
$GLOBALS['wpdb'] = new TestWpdb();

function absint( $value ): int { return abs( (int) $value ); }
function get_option( $key, $default = null ) {
    if ( 'timonconfigs_woo' === $key ) { return array( 'enabled' => 1, 'monthly_product' => 427, 'lifetime_product' => 430 ); }
    if ( 'timonconfigs_discord' === $key ) { return array( 'guild_id' => TEST_GUILD, 'monthly_role' => TEST_MONTHLY, 'lifetime_role' => TEST_LIFETIME, 'free_role' => TEST_FREE, 'staff_role' => TEST_STAFF_ROLE ); }
    if ( '_tc_discord_binding_' . TEST_MEMBER === $key ) { return TEST_USER; }
    return $GLOBALS['options'][ $key ] ?? $default;
}
function add_option( $key, $value, $deprecated = '', $autoload = false ): bool {
    if ( array_key_exists( $key, $GLOBALS['options'] ) ) { return false; }
    $GLOBALS['options'][ $key ] = $value; return true;
}
function wp_cache_delete( $key, $group = '' ): bool { return true; }
function maybe_unserialize( $value ) { return $value; }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['queue'][ $hook . serialize( $args ) ] ?? false; }
function wp_schedule_single_event( $at, $hook, $args = array() ): bool { $GLOBALS['queue'][ $hook . serialize( $args ) ] = $at; return true; }
function as_has_scheduled_action( $hook, $args, $group ): bool { return isset( $GLOBALS['as_queue'][ $hook . serialize( $args ) . $group ] ); }
function as_enqueue_async_action( $hook, $args, $group ): int { $GLOBALS['as_queue'][ $hook . serialize( $args ) . $group ] = true; return count( $GLOBALS['as_queue'] ); }
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
function arraysubs_is_waiting_cancellation( $id ): bool { return $GLOBALS['pending_cancellation']; }
function arraysubs_get_scheduled_cancel_date( $id ): string { return $GLOBALS['cancellation_date']; }
function arraysubs_cancel_subscription_immediately( $id, $reason, $actor ): bool {
    $GLOBALS['cancellations'][] = $id;
    if ( $GLOBALS['cancel_failure'] ) { throw new RuntimeException( 'Simulated native cancellation exception' ); }
    $GLOBALS['subscriptions'][ $id ]->post_status = 'arraysubs-cancelled';
    if ( $GLOBALS['subscription_failure_after_cancel'] ) { $GLOBALS['subscription_failure'] = true; }
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
        if ( 'PUT' === $method && TEST_FREE === $match[1] && $GLOBALS['free_put_failure'] ) {
            return array( 'code' => 403, 'body' => '{}' );
        }
        if ( 'PUT' === $method && in_array( $match[1], array( TEST_MONTHLY, TEST_LIFETIME ), true ) && $GLOBALS['reenter_on_vip_put'] ) {
            $GLOBALS['reenter_on_vip_put'] = false;
            $before = count( $GLOBALS['requests'] );
            TC_Woo_Bridge::sync_user( TEST_USER );
            $GLOBALS['nested_requests'] = count( $GLOBALS['requests'] ) - $before;
        }
        if ( 'PUT' === $method && in_array( $match[1], array( TEST_MONTHLY, TEST_LIFETIME ), true ) && $GLOBALS['replace_lease_on_vip_put'] ) {
            $GLOBALS['replace_lease_on_vip_put'] = false;
            $GLOBALS['options']['_tc_woo_sync_lock_' . TEST_USER] = ( time() + 300 ) . ':replacement-worker';
        }
        if ( 'PUT' === $method ) { $GLOBALS['member_roles'][] = $match[1]; }
        if ( 'PUT' === $method && TEST_MONTHLY === $match[1] && $GLOBALS['cancel_during_vip_put'] ) {
            $GLOBALS['subscriptions'][11]->post_status = 'arraysubs-cancelled';
        }
        if ( 'DELETE' === $method && TEST_MONTHLY === $match[1] && $GLOBALS['replace_lease_on_compensating_delete'] ) {
            $GLOBALS['replace_lease_on_compensating_delete'] = false;
            $GLOBALS['options']['_tc_woo_sync_lock_' . TEST_USER] = ( time() + 300 ) . ':compensating-replacement';
            $GLOBALS['meta'][ TEST_USER ]['_tc_woo_owned_roles'] = array( TEST_LIFETIME => 1 );
        }
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
/** Reset only synthetic state; the assertions counter intentionally survives. */
function reset_membership_fixture(): void {
    foreach ( array( 'meta', 'post_meta', 'orders', 'subscriptions', 'requests', 'cancellations', 'options', 'queue', 'as_queue' ) as $key ) { $GLOBALS[ $key ] = array(); }
    $GLOBALS['member_roles'] = array( TEST_FREE );
    foreach ( array( 'subscription_failure', 'cancel_failure', 'schedule_failure', 'pending_cancellation', 'free_put_failure', 'subscription_failure_after_cancel', 'reenter_on_vip_put', 'replace_lease_on_vip_put', 'cancel_during_vip_put', 'replace_lease_on_compensating_delete' ) as $key ) { $GLOBALS[ $key ] = false; }
    $GLOBALS['audit_role'] = ''; $GLOBALS['audit_at'] = time() + 1; $GLOBALS['cancellation_date'] = ''; $GLOBALS['nested_requests'] = null;
}

// Turning off renewal must preserve a verified, already-paid period.
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['pending_cancellation'] = true;
$GLOBALS['cancellation_date'] = gmdate( 'Y-m-d H:i:s', time() + 1800 );
TC_Woo_Bridge::sync_user( TEST_USER );
verify( TC_Woo_Bridge::paid_access( TEST_USER )['monthly'], 'Scheduled cancellation preserves the paid period' );
verify( in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && ! in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Scheduled cancellation does not prematurely remove Monthly' );
verify( ! $GLOBALS['cancellations'], 'Routine renewal cancellation never invokes immediate moderation cancellation' );

// A terminal cancellation cannot be revived by an old future next-payment date.
$GLOBALS['subscriptions'][11]->post_status = 'arraysubs-cancelled';
$before = count( mutations() );
TC_Woo_Bridge::sync_user( TEST_USER );
$expiry_writes = array_slice( mutations(), $before );
verify( ! TC_Woo_Bridge::paid_access( TEST_USER )['monthly'], 'Terminal cancelled status wins over stale future payment date' );
verify( in_array( TEST_FREE, $GLOBALS['member_roles'], true ) && ! in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ), 'Terminal cancellation completes Monthly to Free transition' );
verify( count( $expiry_writes ) === 2 && $expiry_writes[0][0] === 'PUT' && str_ends_with( $expiry_writes[0][1], '/roles/' . TEST_FREE ) && $expiry_writes[1][0] === 'DELETE' && str_ends_with( $expiry_writes[1][1], '/roles/' . TEST_MONTHLY ), 'Free is established before removing the last owned VIP role' );
verify( isset( $GLOBALS['orders'][22] ), 'Terminal cancellation keeps original paid financial history' );

// A failed Free grant must not prolong a known-ineligible paid Discord role.
reset_membership_fixture(); monthly_fixture( time() - 100 ); TC_Woo_Bridge::sync_user( TEST_USER );
$GLOBALS['subscriptions'][11]->post_status = 'arraysubs-cancelled'; $GLOBALS['free_put_failure'] = true;
$before = count( mutations() ); TC_Woo_Bridge::sync_user( TEST_USER );
$failed_writes = array_slice( mutations(), $before );
verify( ! TC_Woo_Bridge::paid_access( TEST_USER )['monthly'], 'Failed Free grant never restores a cancelled paid entitlement' );
verify( ! in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && ! in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Failed Free grant cannot preserve an ineligible paid Discord role' );
verify( count( $failed_writes ) === 2 && $failed_writes[0][0] === 'PUT' && $failed_writes[1][0] === 'DELETE', 'Failed Free attempt precedes removal of the ineligible owned VIP' );
verify( get_user_meta( TEST_USER, '_tc_woo_sync_error', true ) === 'discord_http_403', 'Free grant failure remains visible for support and retry' );

// Moderation must invalidate stale badges even when Free is already present.
reset_membership_fixture(); monthly_fixture( time() - 100 ); TC_Woo_Bridge::sync_user( TEST_USER );
$GLOBALS['member_roles'] = array( TEST_FREE ); $GLOBALS['audit_role'] = TEST_MONTHLY;
$GLOBALS['meta'][TEST_USER]['_tc_discord_status'] = array( 'state' => 'monthly' );
$before = count( mutations() ); TC_Woo_Bridge::sync_user( TEST_USER );
verify( '' === get_user_meta( TEST_USER, '_tc_discord_status', true ), 'Moderator cutoff invalidates a stale VIP badge without needing a bot role write' );
verify( count( mutations() ) === $before && ! TC_Woo_Bridge::paid_access( TEST_USER )['monthly'], 'Already-Free moderation does not restore VIP or make redundant role changes' );

// A dependency failure after recording moderation cannot grant synthetic Free.
reset_membership_fixture(); monthly_fixture( time() - 100 ); TC_Woo_Bridge::sync_user( TEST_USER );
$GLOBALS['member_roles'] = array(); $GLOBALS['audit_role'] = TEST_MONTHLY;
$GLOBALS['subscription_failure_after_cancel'] = true;
$before = count( mutations() ); TC_Woo_Bridge::sync_user( TEST_USER );
verify( count( mutations() ) === $before, 'Unknown state after moderator cancellation performs no role mutations' );
verify( ! empty( get_user_meta( TEST_USER, '_tc_woo_revoked', true )['monthly'] ), 'Unknown reread preserves the moderator entitlement cutoff' );

// Two queued jobs for the same member must never interleave Discord writes.
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['reenter_on_vip_put'] = true; TC_Woo_Bridge::sync_user( TEST_USER );
verify( $GLOBALS['nested_requests'] === 0, 'A nested concurrent worker cannot read or mutate Discord while the first lease is held' );
verify( in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && empty( $GLOBALS['options'] ), 'Successful reconciliation releases its lease after a consistent role transition' );

// A worker must not clear a lease which another worker now owns.
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['replace_lease_on_vip_put'] = true; TC_Woo_Bridge::sync_user( TEST_USER );
verify( str_ends_with( $GLOBALS['options']['_tc_woo_sync_lock_' . TEST_USER] ?? '', ':replacement-worker' ), 'Lease release uses compare-and-delete and preserves a replacement owner' );

// Expired leases are reclaimed atomically; still-valid leases are respected.
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['options']['_tc_woo_sync_lock_' . TEST_USER] = ( time() - 1 ) . ':expired-worker';
TC_Woo_Bridge::sync_user( TEST_USER );
verify( in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && empty( $GLOBALS['options'] ), 'Expired worker lease can be reclaimed and released' );
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['options']['_tc_woo_sync_lock_' . TEST_USER] = ( time() + 300 ) . ':live-worker';
TC_Woo_Bridge::sync_user( TEST_USER );
verify( ! $GLOBALS['requests'] && str_ends_with( $GLOBALS['options']['_tc_woo_sync_lock_' . TEST_USER], ':live-worker' ), 'A live worker lease blocks duplicate Discord work' );

// Committed status and date changes wake reconciliation without changing roles inline.
reset_membership_fixture(); monthly_fixture( time() - 100 );
TC_Woo_Bridge::array_status_changed( 'arraysubs-cancelled', 'arraysubs-active', (object) array( 'ID' => 11 ) );
verify( count( $GLOBALS['as_queue'] ) === 1 && count( $GLOBALS['queue'] ) === 1, 'ArraySubs status change with a post object queues Action Scheduler and WP fallback' );
verify( ! $GLOBALS['requests'] && ! $GLOBALS['cancellations'], 'Status wake-up never calls Discord or cancels payment inline' );
$GLOBALS['queue'] = array(); $GLOBALS['as_queue'] = array();
TC_Woo_Bridge::array_meta_changed( 1, 11, '_next_payment_date', gmdate( 'Y-m-d H:i:s' ) );
verify( count( $GLOBALS['as_queue'] ) === 1 && count( $GLOBALS['queue'] ) === 1 && ! $GLOBALS['requests'], 'Known subscription date update queues reconciliation without Discord writes' );
$GLOBALS['queue'] = array(); $GLOBALS['as_queue'] = array();
TC_Woo_Bridge::array_meta_changed( 2, 11, '_unrelated_note', 'Unrelated edit' );
TC_Woo_Bridge::array_status_changed( 'arraysubs-active', 'arraysubs-active', (object) array( 'ID' => 11 ) );
verify( ! $GLOBALS['as_queue'] && ! $GLOBALS['queue'], 'Unrelated metadata and unchanged subscription status do not queue duplicate work' );

// A verified profile read may request a role refresh, at most once per minute.
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['meta'][ TEST_USER ]['_tc_woo_tracked'] = '1';
TC_Woo_Bridge::observe_member( TEST_USER, array( 'member_verified' => true ) );
verify( count( $GLOBALS['as_queue'] ) === 1 && count( $GLOBALS['queue'] ) === 1 && ! $GLOBALS['requests'], 'Verified tracked member observation queues refresh without synchronous role mutation' );
verify( get_user_meta( TEST_USER, '_tc_woo_observe_after', true ) >= time() + 59, 'Verified observation sets a one-minute throttle' );
$GLOBALS['queue'] = array(); $GLOBALS['as_queue'] = array();
TC_Woo_Bridge::observe_member( TEST_USER, array( 'member_verified' => true ) );
verify( ! $GLOBALS['queue'] && ! $GLOBALS['as_queue'], 'A second verified observation inside the minute does not enqueue again' );
$GLOBALS['meta'][ TEST_USER ]['_tc_woo_observe_after'] = time() - 1;
TC_Woo_Bridge::observe_member( TEST_USER, array( 'member_verified' => true ) );
verify( count( $GLOBALS['queue'] ) === 1 && count( $GLOBALS['as_queue'] ) === 1, 'Observation can queue a refresh after the throttle expires' );
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['meta'][ TEST_USER ]['_tc_woo_tracked'] = '1';
TC_Woo_Bridge::observe_member( TEST_USER, array( 'member_verified' => false ) );
verify( ! $GLOBALS['queue'] && ! $GLOBALS['as_queue'] && '' === get_user_meta( TEST_USER, '_tc_woo_observe_after', true ), 'Unverified observation never queues work or marks a throttle' );
unset( $GLOBALS['meta'][ TEST_USER ]['_tc_woo_tracked'] );
TC_Woo_Bridge::observe_member( TEST_USER, array( 'member_verified' => true ) );
verify( ! $GLOBALS['queue'] && ! $GLOBALS['as_queue'], 'Verified but untracked Discord members are not adopted by billing reconciliation' );

// A cancellation while a remote grant is in flight must compensate only that new grant.
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['cancel_during_vip_put'] = true;
TC_Woo_Bridge::sync_user( TEST_USER );
$inflight_writes = mutations();
verify( ! in_array( TEST_MONTHLY, $GLOBALS['member_roles'], true ) && in_array( TEST_FREE, $GLOBALS['member_roles'], true ), 'Cancellation during the grant response compensates VIP while preserving existing Free' );
verify( count( $inflight_writes ) === 2 && $inflight_writes[0][0] === 'PUT' && $inflight_writes[1][0] === 'DELETE', 'An in-flight obsolete grant is followed by its compensating deletion' );
verify( empty( get_user_meta( TEST_USER, '_tc_woo_owned_roles', true )[ TEST_MONTHLY ] ) && isset( $GLOBALS['orders'][22] ), 'Compensated grant clears only its role ownership and preserves payment history' );
verify( get_user_meta( TEST_USER, '_tc_woo_sync_error', true ) === 'entitlement_changed_during_grant' && empty( $GLOBALS['options'] ), 'Compensated cancellation records its diagnostic and releases its own lease' );

// A lease lost while compensating may not overwrite the replacement worker's metadata.
reset_membership_fixture(); monthly_fixture( time() - 100 );
$GLOBALS['cancel_during_vip_put'] = true; $GLOBALS['replace_lease_on_compensating_delete'] = true;
TC_Woo_Bridge::sync_user( TEST_USER );
verify( get_user_meta( TEST_USER, '_tc_woo_owned_roles', true ) === array( TEST_LIFETIME => 1 ), 'Lease loss during compensation preserves replacement worker ownership metadata' );
verify( str_ends_with( $GLOBALS['options']['_tc_woo_sync_lock_' . TEST_USER] ?? '', ':compensating-replacement' ), 'Compensating worker cannot release the newer worker lease' );

echo 'Woo role lifecycle: ' . $GLOBALS['assertions'] . " assertions PASS (in-memory providers, no HTTP or DB).\n";
