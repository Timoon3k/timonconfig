/** Real delivery handler/bytes; mocked Discord transport, disposable local Docker only. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes, createHash } = require('node:crypto');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const base = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';
if (new URL(base).origin !== 'http://127.0.0.1:8080') throw new Error('Disposable localhost:8080 only.');
const marker = randomBytes(10).toString('hex');
const content = `Synthetic delivery fixture ${marker}.\n`;
const data = value => `json_decode(base64_decode('${Buffer.from(JSON.stringify(value)).toString('base64')}'),true)`;
function php(code, failure = false) {
  try { return execFileSync('docker', ['compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', 'cli', 'wp', 'eval-file', '-'], { cwd: root, input: `<?php\n${code}`, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }); }
  catch (error) { if (failure) return { failed: true, output: String(error.stdout || '') + String(error.stderr || '') }; throw error; }
}
function json(code) { const result = php(code); const line = result.split('\n').find(line => line.startsWith('TC_STREAM:')); assert.ok(line, 'Structured fixture result'); return JSON.parse(line.slice(10)); }
let snapshot, state;
const claims = [];
function remember(token) { const payload = JSON.parse(Buffer.from(token.split('.')[0], 'base64url')); claims.push('_tc_download_claim_' + createHash('sha256').update(payload.n).digest('hex')); }
const setup = `
add_filter('option_home',static fn()=> 'https://localhost:8080');
add_filter('pre_http_request',static function($pre,$args,$url){
 if(!preg_match('#^https://discord\\.com/api/v10/users/@me/guilds/[0-9]+/member$#D',$url)){throw new RuntimeException('Unexpected provider request blocked.');}
 return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode(array('user'=>array('id'=>'700000000000000001'),'roles'=>array('700000000000000006'))));
},10,3);
`;
(async () => {
  try {
    snapshot = json(`echo 'TC_STREAM:'.wp_json_encode(array('oauth'=>get_option('timonconfigs_discord',null),'cleanup'=>wp_next_scheduled('tc_download_cleanup')));`);
    state = json(`${setup}
$id='700000000000000001'; if(get_option('_tc_discord_binding_'.$id)){throw new RuntimeException('Reserved test identity already exists.');}
$reflect=new ReflectionMethod(TC_Discord::class,'encrypt');
update_option('timonconfigs_discord',array('enabled'=>1,'staging_confirmed'=>1,'client_id'=>'700000000000000002','guild_id'=>'700000000000000003','free_role'=>'700000000000000004','monthly_role'=>'700000000000000005','lifetime_role'=>'700000000000000006','secret_encrypted'=>$reflect->invoke(null,'synthetic-secret')),false);
wp_set_current_user(0);$bind=new ReflectionMethod(TC_Discord::class,'bind');$uid=$bind->invoke(null,array('id'=>$id,'username'=>'Disposable stream member'));if(is_wp_error($uid)){throw new RuntimeException('Test binding failed.');}
update_user_meta($uid,'_tc_dl_stream_fixture','${marker}');
update_user_meta($uid,'_tc_discord_token',$reflect->invoke(null,wp_json_encode(array('access_token'=>'synthetic-access','refresh_token'=>'synthetic-refresh','expires'=>time()+3600))));
$session=WP_Session_Tokens::get_instance($uid)->create(time()+3600);$cookie=wp_generate_auth_cookie($uid,time()+3600,'logged_in',$session);wp_set_current_user($uid);$_COOKIE[LOGGED_IN_COOKIE]=$cookie;
$config=wp_insert_post(array('post_type'=>'tc_config','post_status'=>'publish','post_title'=>'LOCAL stream fixture','meta_input'=>array('tc_tier'=>'monthly','tc_release_status'=>'available','_tc_dl_stream_fixture'=>'${marker}')));
$directory=TC_Private_Files::root().'/'.$config;if(!mkdir($directory,0700)){throw new RuntimeException('Test storage failed.');}$fid=wp_generate_uuid4();$filename=$directory.'/'.$fid.'.txt';$bytes=${data(content)};file_put_contents($filename,$bytes);chmod($filename,0600);
update_post_meta($config,'_tc_private_files',array(array('id'=>$fid,'stored_name'=>basename($filename),'name'=>'synthetic.txt','size'=>strlen($bytes),'checksum'=>hash('sha256',$bytes))));
$token=TC_Downloads::mint($config,$fid);if(!is_string($token)){throw new RuntimeException('Test mint failed.');}
echo 'TC_STREAM:'.wp_json_encode(array('uid'=>$uid,'cookie'=>$cookie,'config'=>$config,'file'=>$fid,'token'=>$token));`);
    remember(state.token);
    const session = `${setup}\n$s=${data(state)};wp_set_current_user($s['uid']);$_COOKIE[LOGGED_IN_COOKIE]=$s['cookie'];`;
    const deliver = `${session}\n$_GET['tc_download']=$s['token'];$_SERVER['REQUEST_METHOD']='GET';TC_Downloads::deliver();`;
    assert.equal(php(deliver), content, 'Actual delivery emits exact fixture bytes');
    const replay = php(deliver, true);
    assert.equal(replay.failed, true, 'Delivery replay rejected');
    assert.ok(replay.output.includes('already used') && !replay.output.includes(content), 'Replay returns no protected bytes');
    const history = json(`${session}\necho 'TC_STREAM:'.wp_json_encode(TC_Downloads::recent($s['uid']));`);
    assert.equal(history.length, 1, 'Only successful delivery recorded');
    assert.equal(history[0].file_name, 'synthetic.txt');
    const token = json(`${session}\necho 'TC_STREAM:'.wp_json_encode(TC_Downloads::mint($s['config'],$s['file']));`);
    remember(token);
    const changed = php(`${session}
$_GET['tc_download']=${data(token)};$_SERVER['REQUEST_METHOD']='GET';$reads=0;
add_filter('get_post_metadata',static function($value,$id,$key) use (&$reads,$s){if((int)$id===$s['config'] && '_tc_private_files'===$key && ++$reads===2){$p=TC_Private_Files::root().'/'.$id.'/'.$s['file'].'.txt';file_put_contents($p,str_repeat('X',filesize($p)));}return $value;},10,3);
TC_Downloads::deliver();`, true);
    assert.equal(changed.failed, true, 'Same-size mutation after resolve cannot be streamed');
    assert.ok(changed.output.includes('file changed') && !changed.output.includes(content) && !changed.output.includes('/var/lib/'), 'Descriptor hash failure reveals no bytes/path');
    const response = await fetch(`${base}/?tc_download=invalid`, { redirect: 'manual' });
    assert.equal(response.status, 403);
    assert.match(response.headers.get('cache-control'), /no-store/);
    assert.match(response.headers.get('x-robots-tag'), /noindex/);
    console.log('Download delivery passed: exact bytes, replay, descriptor mutation, recent history, anonymous HTTP/cache headers.');
  } finally {
    if (snapshot) php(`
$posts=get_posts(array('post_type'=>'tc_config','post_status'=>array_values(get_post_stati()),'meta_key'=>'_tc_dl_stream_fixture','meta_value'=>'${marker}','posts_per_page'=>-1));
foreach($posts as $post){$directory=TC_Private_Files::root().'/'.$post->ID;if(is_dir($directory)&&!is_link($directory)){foreach(scandir($directory) as $f){if($f!=='.'&&$f!=='..'&&is_file($directory.'/'.$f)){unlink($directory.'/'.$f);}}rmdir($directory);}wp_delete_post($post->ID,true);}
require_once ABSPATH.'wp-admin/includes/user.php';foreach(get_users(array('meta_key'=>'_tc_dl_stream_fixture','meta_value'=>'${marker}')) as $user){delete_option('_tc_discord_binding_'.get_user_meta($user->ID,'_tc_discord_id',true));delete_option('_tc_discord_lock_'.$user->ID);wp_delete_user($user->ID);}
$snapshot=${data(snapshot)};foreach(${data(claims)} as $claim){delete_option($claim);}if(!$snapshot['cleanup']){wp_clear_scheduled_hook('tc_download_cleanup');}null===$snapshot['oauth']?delete_option('timonconfigs_discord'):update_option('timonconfigs_discord',$snapshot['oauth'],false);echo 'Cleaned stream fixtures.';`);
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
