<?php
/** Disposable localhost only; real content/Discord services, mocked upstream HTTP. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Disposable localhost required.' ); }
if ( ! class_exists( 'TC_Config_Content' ) ) { require WP_PLUGIN_DIR . '/timonconfigs-core/includes/class-tc-config-content.php'; TC_Config_Content::register_meta(); }
$checks = 0; $assert = static function( bool $ok, string $label ) use( &$checks ): void { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$call = static function( string $method, ...$args ) { return (new ReflectionMethod(TC_Discord::class,$method))->invoke(null,...$args); };
$old_settings = get_option('timonconfigs_discord', null); $old_user = get_current_user_id(); $old_post = $_POST;
$posts = array(); $uid = 0; $discord_id = '910000000000000001'; $roles = array(); $mode = 'ok';
$https = static fn() => 'https://localhost:8080';
$http = static function( $pre, $args, $url ) use( &$roles, &$mode, $discord_id ) {
    if ( ! str_starts_with($url,'https://discord.com/api/v10/users/@me/guilds/') ) { throw new RuntimeException('Unexpected upstream blocked.'); }
    return 'ok' === $mode ? array('response'=>array('code'=>200),'body'=>wp_json_encode(array('user'=>array('id'=>$discord_id),'roles'=>$roles)),'headers'=>array()) : new WP_Error('fixture','Never disclose upstream details.');
};
add_filter('option_home',$https); add_filter('pre_http_request',$http,10,3);
try {
    $settings=array('enabled'=>1,'staging_confirmed'=>1,'client_id'=>'910000000000000002','guild_id'=>'910000000000000003','free_role'=>'910000000000000004','monthly_role'=>'910000000000000005','lifetime_role'=>'910000000000000006','secret_encrypted'=>$call('encrypt','fake-secret'));
    update_option('timonconfigs_discord',$settings,false);
    $uid=wp_create_user('tc_content_'.wp_generate_password(12,false),wp_generate_password(32,true));
    if(is_wp_error($uid)){throw new RuntimeException('Fixture user creation failed.');}
    update_user_meta($uid,'_tc_discord_id',$discord_id);add_option('_tc_discord_binding_'.$discord_id,$uid,'',false);
    $seed=static function() use($uid,$call):void{update_user_meta($uid,'_tc_discord_token',$call('encrypt',wp_json_encode(array('access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','expires'=>time()+3600))));delete_user_meta($uid,'_tc_discord_status');delete_user_meta($uid,'_tc_discord_attempt');};
    $id=wp_insert_post(array('post_type'=>'tc_config','post_status'=>'publish','post_title'=>'LOCAL GUIDE FIXTURE','meta_input'=>array('tc_tier'=>'monthly','tc_release_status'=>'available')),true);if(is_wp_error($id)){throw new RuntimeException('Fixture config failed.');}$posts[]=$id;
    $assert( true === TC_Config_Content::validate_fields( array( 'tc_guide_content' => 'Content-only draft' ) ), 'Content-only create validates with conservative policy.' );
    $assert( true === TC_Config_Content::save_fields( $id, array( 'tc_guide_content' => 'Content-only draft' ) ) && 'monthly' === get_post_meta( $id, '_tc_guide_visibility', true ), 'Content-only save uses the same monthly default as prevalidation.' );
    $original_content_meta = get_post_meta( $id, '_tc_guide_content', true );
    $corrupt_meta = static function( $value, $object_id, $key, $single ) use ( $id ) { return $object_id === $id && '_tc_guide_content' === $key ? array( array( 'corrupt' ) ) : $value; };
    add_filter( 'get_post_metadata', $corrupt_meta, 10, 4 );
    try {
        $assert( is_wp_error( TC_Config_Content::validate_fields( array( 'tc_guide_visibility' => 'free' ), $id ) ), 'Pure prevalidation rejects corrupt inherited content before a post edit.' );
        $assert( is_wp_error( TC_Config_Content::save_fields( $id, array( 'tc_guide_visibility' => 'free' ) ) ), 'Persistence rejects the same corrupt inherited content.' );
    } finally { remove_filter( 'get_post_metadata', $corrupt_meta, 10 ); }
    $assert( $original_content_meta === get_post_meta( $id, '_tc_guide_content', true ) && 'monthly' === get_post_meta( $id, '_tc_guide_visibility', true ), 'Rejected inherited data leaves content and policy unchanged.' );
    $guide="<h3>SETUP-SECRET-GUIDE</h3>\n<ol><li>First</li><li>Second</li></ol><table><tr><th>Mode</th><td>Test</td></tr></table><pre>line 1\nline 2</pre><code>A &lt; 10 &amp;&amp; B</code><img src=\"https://example.org/public.png\" onerror=\"alert(1)\"><script>alert(2)</script>";
    $values="VALUES-SECRET\r\nA = <10>;\nB = \\\"quoted\\\";\n";
    $result=TC_Config_Content::save_fields($id,array('tc_guide_content'=>$guide,'tc_guide_visibility'=>'monthly','tc_values_content'=>$values,'tc_values_visibility'=>'lifetime'));
    $assert(true===$result,'Validated content saved.');
    $stored=get_post_meta($id,'_tc_guide_content',true);
    $assert(!str_contains($stored,'<script')&&!str_contains($stored,'onerror'),'Guide strips active HTML.');
    $assert(str_contains($stored,'<table>')&&str_contains($stored,'<ol>')&&str_contains($stored,'<pre>'),'Guide preserves useful formatting.');
    $assert(str_contains(get_post_meta($id,'_tc_values_content',true),'A = <10>;'),'Values preserve command syntax rather than rendering HTML.');
    $assert(!str_contains(get_post_meta($id,'_tc_values_content',true),"\r"),'Line endings normalized.');
    $result=TC_Config_Content::save_fields($id,array('tc_guide_content'=>'overwrite','tc_guide_visibility'=>'public','tc_values_content'=>array('malformed'),'tc_values_visibility'=>'public'));
    $assert(is_wp_error($result)&&get_post_meta($id,'_tc_guide_content',true)===$stored,'Malformed batch changes no item.');
    $assert(is_wp_error(TC_Config_Content::save_fields($id,array('tc_values_content'=>str_repeat('x',100001),'tc_values_visibility'=>'free'))),'Oversized content rejected.');
    $assert(is_wp_error(TC_Config_Content::save_fields($id,array('tc_values_content'=>'x','tc_values_visibility'=>'owner'))),'Unknown visibility rejected.');
    wp_set_current_user(0);
    $assert(is_wp_error(TC_Config_Content::read($id,'guide')),'Anonymous cannot read protected guide.');
    $assert(is_wp_error(TC_Config_Content::read($id,'values')),'Anonymous cannot read protected values.');
    $buttons=TC_Config_Content::buttons($id);
    $assert(str_contains($buttons,'Guide')&&str_contains($buttons,'Values')&&!str_contains($buttons,'SECRET'),'Buttons never embed protected content.');
    $meta=get_registered_meta_keys('post','tc_config');
    $assert(false===$meta['_tc_guide_content']['show_in_rest']&&false===$meta['_tc_values_content']['show_in_rest'],'Protected metadata excluded from REST.');
    $assert(!empty($meta['_tc_guide_content']['revisions_enabled'])&&!empty($meta['_tc_values_visibility']['revisions_enabled']),'Content and policy have revisions.');
    $response=rest_do_request(new WP_REST_Request('GET','/wp/v2/tc_config/'.$id));
    $assert(200===$response->get_status()&&!str_contains(wp_json_encode($response->get_data()),'SECRET'),'Public config REST response never contains protected text.');
    wp_set_current_user($uid);
    foreach(array('unverified','free','monthly','lifetime')as$tier){
        $roles='unverified'===$tier?array():array($settings[$tier.'_role']);$seed();
        foreach(array('free','monthly','lifetime')as$visibility){
            update_post_meta($id,'_tc_guide_visibility',$visibility);
            $expected='free'===$visibility?'unverified'!==$tier:('monthly'===$visibility?in_array($tier,array('monthly','lifetime'),true):'lifetime'===$tier);
            $result=TC_Config_Content::read($id,'guide');
            $assert($expected===is_array($result),'Independent Guide access matrix '.$tier.'/'.$visibility);
        }
    }
    $result=TC_Config_Content::read($id,'values');
    $assert(is_array($result)&&str_contains($result['html'],'&lt;10&gt;')&&str_contains($result['text'],'A = <10>;'),'Authorized values escape HTML but copy exact syntax.');
    $assert($result['text']===str_replace("\r\n","\n",$values),'Copy output preserves spacing and backslashes.');
    $mode='error';$seed();
    $assert(is_wp_error(TC_Config_Content::read($id,'guide')),'Provider error denies protected content.');$mode='ok';
    update_post_meta($id,'_tc_guide_visibility','public');wp_set_current_user(0);
    $result=TC_Config_Content::read($id,'guide');
    $assert(is_array($result)&&str_contains($result['html'],'SETUP-SECRET-GUIDE'),'Explicit public teaser can be read anonymously.');
    $assert(str_contains($result['text'],'A < 10 && B'),'Guide copy decodes displayed entities without injecting markup.');
    $assert(is_wp_error(TC_Config_Content::read($id,'unknown')),'Unknown content kind rejected.');
    wp_update_post(array('ID'=>$id,'post_status'=>'draft'));
    $assert(is_wp_error(TC_Config_Content::read($id,'guide'))&&''===TC_Config_Content::buttons($id),'Draft content and buttons unavailable.');
    wp_update_post(array('ID'=>$id,'post_status'=>'publish','post_password'=>'secret'));
    $assert(is_wp_error(TC_Config_Content::read($id,'guide'))&&''===TC_Config_Content::buttons($id),'Password protected config unavailable.');
    wp_update_post(array('ID'=>$id,'post_password'=>''));update_post_meta($id,'tc_release_status','archived');
    $assert(is_wp_error(TC_Config_Content::read($id,'guide')),'Archived release unavailable.');update_post_meta($id,'tc_release_status','available');
    delete_post_meta($id,'_tc_guide_visibility');
    $assert(is_wp_error(TC_Config_Content::read($id,'guide')),'Missing policy never defaults to public.');
    $_POST=array('tc_config_content_nonce'=>wp_create_nonce('tc_config_content_save_'.$id),'tc_values_content'=>'unauthorized','tc_values_visibility'=>'public');TC_Config_Content::save_admin($id);
    $assert(get_post_meta($id,'_tc_values_content',true)!=='unauthorized','Anonymous cannot save content with a nonce.');
    wp_set_current_user($uid);$_POST['tc_config_content_nonce']=wp_create_nonce('tc_config_content_save_'.$id);TC_Config_Content::save_admin($id);
    $assert(get_post_meta($id,'_tc_values_content',true)!=='unauthorized','Subscriber cannot save content with a valid nonce.');
    $admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'));wp_set_current_user((int)$admins[0]);$_POST=array('tc_values_content'=>'no nonce','tc_values_visibility'=>'public');TC_Config_Content::save_admin($id);
    $assert(get_post_meta($id,'_tc_values_content',true)!=='no nonce','Admin save requires CSRF nonce.');
    $_POST['tc_config_content_nonce']=wp_create_nonce('tc_config_content_save_'.$id);TC_Config_Content::save_admin($id);
    $assert('no nonce'===get_post_meta($id,'_tc_values_content',true),'Admin editor persists with valid permission and nonce.');
    $matching_revision = 0;
    foreach ( wp_get_post_revisions( $id ) as $revision ) { if ( 'no nonce' === get_post_meta( $revision->ID, '_tc_values_content', true ) ) { $matching_revision = $revision->ID; break; } }
    $assert( $matching_revision > 0, 'Saved revision contains private metadata.' );
    TC_Config_Content::save_fields( $id, array( 'tc_values_content' => 'later revision', 'tc_values_visibility' => 'monthly' ) );
    wp_restore_post_revision( $matching_revision );
    $assert( 'no nonce' === get_post_meta( $id, '_tc_values_content', true ) && 'public' === get_post_meta( $id, '_tc_values_visibility', true ), 'Revision restore recovers both content and access policy.' );
    WP_CLI::success("Config content: $checks assertions; real services, mocked Discord only.");
} finally {
    remove_filter('pre_http_request',$http,10);remove_filter('option_home',$https);
    foreach($posts as$id){wp_delete_post($id,true);}
    if($uid&&!is_wp_error($uid)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($uid);delete_option('_tc_discord_binding_'.$discord_id);delete_option('_tc_discord_lock_'.$uid);}
    null===$old_settings?delete_option('timonconfigs_discord'):update_option('timonconfigs_discord',$old_settings,false);
    wp_set_current_user($old_user);$_POST=$old_post;
}
