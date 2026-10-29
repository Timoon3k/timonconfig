<?php
/** Offline reCAPTCHA verification gate regression: no WordPress or Google requests. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('TIMONCONFIGS_RECAPTCHA_V2_SITE_KEY', 'public-test-key');
define('TIMONCONFIGS_RECAPTCHA_V2_SECRET_KEY', 'private-test-secret');
$GLOBALS['tc_actions'] = [];
$GLOBALS['tc_notices'] = [];
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['tc_actions'][$name][] = $callback; }
function is_user_logged_in(): bool { return true; }
function get_current_user_id(): int { return 101; }
function absint( $v ): int { return abs((int)$v); }
function wc_add_notice($s,$kind): void { $GLOBALS['tc_notices'][] = [$s,$kind]; }
class TSession { public array $d=[]; public function get($key){return $this->d[$key]??null;} public function set($key,$v){$this->d[$key]=$v;} }
class TCart {public function get_cart(){return [['product_id'=>427]];} public function needs_shipping(){return false;} }
class TWC {public $cart;public $session;public function __construct(){$this->cart=new TCart;$this->session=new TSession;} }
$GLOBALS['tc_wc'] = new TWC;
function WC():TWC{return $GLOBALS['tc_wc'];}
class TC_Woo_Bridge {public static function config():array{return ['monthly_product'=>427,'lifetime_product'=>430];}}
class TItem {public function get_product_id(){return 427;}}
class TOrder {public function get_items($type){return [new TItem];}}
require __DIR__.'/../wp-content/plugins/timonconfigs-core/includes/class-tc-woo-captcha.php';
function verify(bool $v,string $name):void{if(!$v){fwrite(STDERR,"FAIL ".$name."\n");exit(1);}echo "PASS ".$name."\n";}
TC_Woo_Captcha::init();
verify(TC_Woo_Captcha::configured(),'Site/secret pair enables server verification');
verify(isset($GLOBALS['tc_actions']['woocommerce_checkout_process']),'Classic checkout validation hook');
verify(isset($GLOBALS['tc_actions']['woocommerce_store_api_checkout_order_processed']),'Store API/Wallet validation hook');
TC_Woo_Captcha::require_classic();
verify(count($GLOBALS['tc_notices'])===1 && $GLOBALS['tc_notices'][0][1]==='error','Unverified checkout blocked before charge');
try{TC_Woo_Captcha::require_store(new TOrder);verify(false,'Unverified wallet blocked');}catch(\RuntimeException $e){verify(true,'Unverified wallet fails closed');}
class FakeStoreApiRouteException extends \RuntimeException {
    public string $error_code;
    public function __construct($code,$message,$status){$this->error_code=$code;parent::__construct($message,$status);}
}
class_alias(FakeStoreApiRouteException::class, 'Automattic\WooCommerce\StoreApi\Exceptions\RouteException');
try {
    TC_Woo_Captcha::require_store(new TOrder);
    verify(false, 'Native Store API exception required');
} catch (FakeStoreApiRouteException $e) {
    verify($e->getCode()===403 && $e->error_code==='tc_woo_captcha_required', 'Unsolved wallet returns native Store API 403, not an unexpected 500');
}
WC()->session->set('tc_woo_recaptcha_verified',['user'=>101,'at'=>time()]);
TC_Woo_Captcha::require_classic();
verify(count($GLOBALS['tc_notices'])===1,'Verified same user can pay');
TC_Woo_Captcha::require_store(new TOrder);
verify(true,'Verified wallet allowed');
WC()->session->set('tc_woo_recaptcha_verified',['user'=>102,'at'=>time()]);
TC_Woo_Captcha::require_classic();
verify(count($GLOBALS['tc_notices'])===2,'Different user cannot reuse proof');
WC()->session->set('tc_woo_recaptcha_verified',['user'=>101,'at'=>time()-650]);
TC_Woo_Captcha::require_classic();
verify(count($GLOBALS['tc_notices'])===3,'Expired proof rejected');
echo "CAPTCHA checkout gating PASS.\n";
