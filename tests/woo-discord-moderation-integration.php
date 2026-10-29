<?php
/** Offline Discord audit simulation: only real Staff/Owner role removals qualify for automated moderation. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
define('TIMONCONFIGS_WOO_DISCORD_BOT_TOKEN','FAKE_NONPRODUCTION_TOKEN');
$GLOBALS['actor_role'] = 'staff';
$GLOBALS['audit_role_removed'] = true;
const GUILD_ID='111111111111111111';
const MEMBER_ID='222222222222222222';
const ROLE_ID='333333333333333333';
const STAFF_ID='444444444444444444';
const STAFF_ROLE='555555555555555555';
function get_option($key,$def=null) {
 if($key==='timonconfigs_discord')return ['guild_id'=>GUILD_ID,'staff_role'=>STAFF_ROLE,'owner_role'=>'666666666666666666'];
 return $def;
}
function wp_remote_request($url,$args=[]) {
 if(str_contains($url,'/audit-logs')){
  $eventId=(string)((time()-1420070400)*1000*4194304);
  $data=['audit_log_entries'=>[[
   'id'=>$eventId,'target_id'=>MEMBER_ID,'user_id'=>STAFF_ID,'changes'=>[['key'=>'$remove','new_value'=>($GLOBALS['audit_role_removed']?[['id'=>ROLE_ID]]:[['id'=>'777777777777777777']])]]
  ]]];
 }else{
  $data=['roles'=>($GLOBALS['actor_role']==='staff'?[STAFF_ROLE]:[]),'user'=>['bot'=>($GLOBALS['actor_role']==='bot')]];
 }
 return ['status'=>200,'body'=>json_encode($data)];
}
function is_wp_error($o){return $o instanceof WP_Error;}
class WP_Error {}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function absint($v):int{return abs((int)$v);}
require __DIR__.'/../wp-content/plugins/timonconfigs-core/includes/class-tc-woocommerce.php';
function verify(bool $v,string $n):void{if(!$v){fwrite(STDERR,"FAIL: ".$n."\n");exit(1);}echo "PASS ".$n."\n";}
$m=new ReflectionMethod(TC_Woo_Bridge::class,'staff_removed_role');
verify($m->invoke(null,GUILD_ID,MEMBER_ID,ROLE_ID)===true,'Verified Staff removal detected');
$GLOBALS['actor_role']='visitor';
verify($m->invoke(null,GUILD_ID,MEMBER_ID,ROLE_ID)===false,'Non-Staff removal never triggers cancellation');
$GLOBALS['actor_role']='bot';
verify($m->invoke(null,GUILD_ID,MEMBER_ID,ROLE_ID)===false,'Bot removal never triggers cancellation');
$GLOBALS['actor_role']='staff';$GLOBALS['audit_role_removed']=false;
verify($m->invoke(null,GUILD_ID,MEMBER_ID,ROLE_ID)===false,'Audit record must identify correct removed VIP role');
echo "Discord role moderation audit PASS.\n";
