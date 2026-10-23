/** Real Owner forms over isolated local TLS; mock only Discord HTTP, never production. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const https = require('node:https');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = new URL(process.env.TC_TEST_URL || 'http://127.0.0.1:8080/');
if (base.href !== 'http://127.0.0.1:8080/') throw new Error('Owner HTTP mutations require disposable 127.0.0.1:8080.');
const tag = randomBytes(12).toString('hex');
const cookieName = `tc_owner_test_${tag}`;
const ownerDiscord = `8${BigInt('0x' + tag.slice(0, 12)).toString().padStart(17, '0')}`;
const staffDiscord = String(BigInt(ownerDiscord) + 1n);
const muPath = `/var/www/html/wp-content/mu-plugins/tc-owner-browser-${tag}.php`;
const report = { passed: false, assertions: 0, restored: false, errors: [] };
function expect(value, message) { assert.ok(value, message); report.assertions++; }
function wp(code) {
  const output = execFileSync('docker', ['compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', 'cli', 'wp', 'eval-file', '-'], { cwd: root, input: `<?php\n${code}`, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  const line = output.split('\n').find(line => line.startsWith('TC_OWNER_BROWSER:'));
  if (!line) throw new Error('Fixture command returned no structured result.');
  return JSON.parse(line.slice('TC_OWNER_BROWSER:'.length));
}
function dockerFile(content) {
  execFileSync('docker', ['exec', '-i', '--user', 'root', 'timonconfigs-wordpress', 'sh', '-c', `mkdir -p /var/www/html/wp-content/mu-plugins && cat > ${muPath} && chmod 644 ${muPath}`], { input: content, stdio: ['pipe', 'pipe', 'pipe'] });
}
(async () => {
  let directory, proxy, browser, fixture, origin, id;
  try {
    fixture = wp(`if(home_url('/')!=='http://127.0.0.1:8080/'){throw new RuntimeException('Disposable origin mismatch.');}
$root=TC_Private_Files::root();if(is_wp_error($root)){throw new RuntimeException('Private test storage unavailable.');}
$users=array();foreach(array('owner'=>'${ownerDiscord}','staff'=>'${staffDiscord}') as $kind=>$discord){$uid=wp_insert_user(array('user_login'=>'tc_browser_${tag}_'.$kind,'user_pass'=>wp_generate_password(64,true,true),'display_name'=>'Browser '.ucfirst($kind),'role'=>'subscriber'));if(is_wp_error($uid)){throw new RuntimeException('Fixture user creation failed.');}update_user_meta($uid,'_tc_owner_browser_fixture','${tag}');update_user_meta($uid,'_tc_discord_id',$discord);update_user_meta($uid,'_tc_discord_name','Browser '.ucfirst($kind));update_option('_tc_discord_binding_'.$discord,$uid,false);$encrypt=new ReflectionMethod(TC_Discord::class,'encrypt');update_user_meta($uid,'_tc_discord_token',$encrypt->invoke(null,wp_json_encode(array('access_token'=>'fixture-${tag}-'.$kind,'refresh_token'=>'fixture-refresh','expires'=>time()+3600))));$users[$kind]=$uid;}
$settings=array('enabled'=>1,'pilot_enabled'=>1,'pilot_ids'=>"${ownerDiscord}\n${staffDiscord}",'owner_ids'=>'${ownerDiscord}','pkce'=>0,'client_id'=>'899999999999999991','guild_id'=>'899999999999999992','free_role'=>'899999999999999993','monthly_role'=>'899999999999999994','lifetime_role'=>'899999999999999995','owner_role'=>'899999999999999996','staff_role'=>'899999999999999997','secret_encrypted'=>$encrypt->invoke(null,'fixture-client-secret'));
echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('users'=>$users,'settings'=>$settings));`);
    directory = await fs.mkdtemp(path.join(os.tmpdir(), 'tc-owner-browser-'));
    execFileSync('openssl', ['req', '-x509', '-nodes', '-newkey', 'rsa:2048', '-days', '1', '-subj', '/CN=127.0.0.1', '-addext', 'subjectAltName=IP:127.0.0.1', '-keyout', path.join(directory, 'key.pem'), '-out', path.join(directory, 'cert.pem')], { stdio: 'ignore' });
    proxy = https.createServer({ key: await fs.readFile(path.join(directory, 'key.pem')), cert: await fs.readFile(path.join(directory, 'cert.pem')) }, (request, response) => {
      if (!(request.headers.cookie || '').split(';').some(item => item.trim() === `${cookieName}=${tag}`)) { response.writeHead(403); response.end(); return; }
      const upstream = http.request({ hostname: '127.0.0.1', port: 8080, path: request.url, method: request.method, headers: { ...request.headers, 'accept-encoding': 'identity', 'x-tc-owner-fixture': tag, 'x-forwarded-proto': 'https' } }, incoming => {
        // WordPress content constants are initialized before MU filters. Rewrite only this
        // disposable proxy's HTML asset origin so browsers do not block mixed-content CSS.
        if ((incoming.headers['content-type'] || '').includes('text/html')) {
          const chunks = []; incoming.on('data', chunk => chunks.push(chunk));
          incoming.on('end', () => {
            const body = Buffer.from(Buffer.concat(chunks).toString('utf8').replaceAll('http://127.0.0.1:8080', origin).replaceAll('https://127.0.0.1:8080', origin));
            const headers = { ...incoming.headers, 'content-length': body.length }; delete headers['transfer-encoding'];
            response.writeHead(incoming.statusCode, headers); response.end(body);
          });
        } else { response.writeHead(incoming.statusCode, incoming.headers); incoming.pipe(response); }
      });
      upstream.on('error', () => { response.writeHead(502); response.end(); }); request.pipe(upstream);
    });
    await new Promise(resolve => proxy.listen(0, '127.0.0.1', resolve));
    origin = `https://127.0.0.1:${proxy.address().port}`;
    const settingsJSON = JSON.stringify(fixture.settings);
    dockerFile(`<?php
/** Disposable test transport: active only behind the random loopback TLS proxy. */
if (($_SERVER['HTTP_X_TC_OWNER_FIXTURE']??'')!=='${tag}' || ($_COOKIE['${cookieName}']??'')!=='${tag}' || ($_SERVER['HTTP_HOST']??'')!=='127.0.0.1:${proxy.address().port}') { return; }
if (get_option('home')!=='http://127.0.0.1:8080') { return; }
$_SERVER['HTTPS']='on';
add_filter('option_home',static fn()=>'${origin}');add_filter('option_siteurl',static fn()=>'${origin}');
add_filter('pre_option_timonconfigs_discord',static fn()=>json_decode('${settingsJSON}',true));
add_filter('pre_http_request',static function($pre,$args,$url){
 if(!str_starts_with($url,'https://discord.com/api/')){return new WP_Error('fixture_outbound','External requests disabled for browser fixture.');}
 $uid=get_current_user_id();if(get_user_meta($uid,'_tc_owner_browser_fixture',true)!=='${tag}'){return new WP_Error('fixture_actor','Unknown fixture actor.');}
 $is_owner=$uid===${fixture.users.owner};
 if(str_ends_with($url,'/member')){$body=array('user'=>array('id'=>get_user_meta($uid,'_tc_discord_id',true)),'roles'=>array($is_owner?'899999999999999996':'899999999999999997','899999999999999993'));}
 else{return new WP_Error('fixture_endpoint','Unexpected Discord endpoint.');}
 return array('response'=>array('code'=>200),'body'=>wp_json_encode($body),'headers'=>array());
},1,3);
add_action('template_redirect',static function(){
 if(isset($_GET['tc_owner_fixture_nonce'])){nocache_headers();wp_send_json_success(array('nonce'=>wp_create_nonce('tc_owner_config')));}
 if(!isset($_GET['tc_owner_fixture_login'])){return;}
 $kind=$_GET['tc_owner_fixture_login'];$uid='owner'===$kind?${fixture.users.owner}:('staff'===$kind?${fixture.users.staff}:0);
 if(!$uid||get_user_meta($uid,'_tc_owner_browser_fixture',true)!=='${tag}'){wp_die('Fixture actor rejected.');}
 wp_set_current_user($uid);wp_set_auth_cookie($uid,false,true);nocache_headers();wp_safe_redirect(home_url('/account/?tc_manage_configs=1'));exit;
},-20);
`);
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
    await context.addCookies([{ name: cookieName, value: tag, url: origin, secure: true, httpOnly: true }]);
    const page = await context.newPage(); page.on('pageerror', error => report.errors.push(error.message));
    await page.goto(`${origin}/?tc_owner_fixture_login=owner`, { waitUntil: 'networkidle' });
    expect(await page.locator('.tc-owner-form').first().evaluate(el => getComputedStyle(el).display) === 'grid', 'Actual editor stylesheet loads over test HTTPS.');
    expect(await page.locator('.tc-brand__logo').first().evaluate(el => el.complete && el.naturalWidth > 0), 'Brand image loads over test HTTPS.');
    expect(await page.locator('.tc-owner-editor').count() === 1, 'Verified Subscriber Owner sees real frontend editor.');
    expect(await page.getByRole('button', { name: 'Save configuration', exact: true }).count() === 1, 'Owner has a working create form.');
    const title = `LOCAL OWNER HTTP ${tag}`;
    const save = page.locator('form:has(input[name="operation"][value="save"])');
    await save.locator('[name="title"]').fill(title);
    await save.locator('[name="description"]').fill('<p>Owner browser test description.</p>');
    await save.locator('[name="status"]').selectOption('publish');
    await save.locator('[name="tc_tier"]').selectOption('free');
    await save.locator('[name="tc_release_status"]').selectOption('available');
    await save.locator('[name="tc_guide_visibility"]').selectOption('free');
    await save.locator('[name="tc_values_visibility"]').selectOption('free');
    await save.locator('[name="tc_guide_content"]').fill('<p>Test guide only.</p>');
    await save.locator('[name="tc_values_content"]').fill('test = 1\n');
    async function resetAttempt(uid = fixture.users.owner) { wp(`if(get_user_meta(${uid},'_tc_owner_browser_fixture',true)!=='${tag}'){throw new RuntimeException('Wrong test identity.');}delete_user_meta(${uid},'_tc_discord_attempt');echo 'TC_OWNER_BROWSER:'.wp_json_encode(true);`); }
    async function submit(form, button) { await resetAttempt(); await Promise.all([page.waitForURL(url => url.pathname === '/account/' && url.searchParams.has('tc_manage_configs')), form.getByRole('button', { name: button, exact: true }).click()]); await page.waitForLoadState('networkidle'); }
    await submit(save, 'Save configuration');
    id = Number(new URL(page.url()).searchParams.get('config_id'));
    expect(id > 0, 'Actual form creates a published config.');
    let state = wp(`$p=get_post(${id});echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('title'=>$p->post_title,'status'=>$p->post_status,'ownerRole'=>get_userdata(${fixture.users.owner})->roles,'files'=>TC_Private_Files::files(${id}),'thumb'=>get_post_thumbnail_id(${id})));`);
    expect(state.status === 'publish' && state.title === title, 'Published title and status persist.');
    expect(state.ownerRole.length === 1 && state.ownerRole[0] === 'subscriber', 'Frontend write does not promote WordPress role.');
    const upload = page.locator('form:has(input[name="operation"][value="upload"])');
    await upload.locator('input[type=file]').setInputFiles({ name: 'owner-test.gpc', mimeType: 'text/plain', buffer: Buffer.from('// Fixture script\nmain {}\n') });
    await upload.locator('[name="version"]').fill('test-1');
    await submit(upload, 'Add protected files');
    state = wp(`echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('files'=>TC_Private_Files::files(${id})));`);
    expect(state.files.length === 1 && state.files[0].name === 'owner-test.gpc', 'Actual private multipart upload persists via existing validator.');
    expect(!JSON.stringify(state).includes('stored_name'), 'Owner file display does not expose storage names.');
    // Generate an ordinary PNG using Chromium, then submit the real image form.
    const png = await page.screenshot({ clip: { x: 0, y: 0, width: 64, height: 64 } });
    const image = page.locator('form:has(input[name="operation"][value="image"])');
    await image.locator('input[type=file]').setInputFiles({ name: 'fixture.png', mimeType: 'image/png', buffer: png });
    await submit(image, 'Upload public image');
    state = wp(`$a=get_post_thumbnail_id(${id});echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('id'=>$a,'mime'=>get_post_mime_type($a)));`);
    expect(state.id > 0 && state.mime === 'image/jpeg', 'Public image is decoded/reencoded to a real JPEG attachment: ' + await page.locator('[role="status"]').allTextContents().then(x => x.join(' ').slice(0,200)));
    await page.locator('form:has(input[name="operation"][value="save"]) [name="title"]').fill(`${title} edited`);
    await submit(page.locator('form:has(input[name="operation"][value="save"])'), 'Save configuration');
    state = wp(`echo 'TC_OWNER_BROWSER:'.wp_json_encode(get_the_title(${id}));`);
    expect(state === `${title} edited`, 'Existing config edits persist through real HTTP.');
    await fs.mkdir(path.join(root, 'artifacts'), { recursive: true });
    await page.screenshot({ path: path.join(root, 'artifacts/owner-editor-1440.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Owner forms fit mobile without horizontal overflow.');
    await page.screenshot({ path: path.join(root, 'artifacts/owner-editor-390.png'), fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });
    const staff = await browser.newContext({ ignoreHTTPSErrors: true });
    await staff.addCookies([{ name: cookieName, value: tag, url: origin, secure: true, httpOnly: true }]);
    const staffPage = await staff.newPage();
    await staffPage.goto(`${origin}/?tc_owner_fixture_login=staff`, { waitUntil: 'networkidle' });
    expect(await staffPage.getByRole('button', { name: 'Save configuration', exact: true }).count() === 0, 'Staff sees no Owner form.');
    // A real session-bound Staff nonce still cannot authorize a crafted write.
    const staffNonce = wp(`wp_set_current_user(${fixture.users.staff});$manager=WP_Session_Tokens::get_instance(${fixture.users.staff});$tokens=$manager->get_all();echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('sessions'=>count($tokens)));`);
    expect(staffNonce.sessions > 0, 'Staff has a genuine WordPress session.');
    const nonceResponse = await staffPage.request.get(`${origin}/?tc_owner_fixture_nonce=1`);
    const staffOwnNonce = (await nonceResponse.json()).data.nonce;
    const denied = await staffPage.request.post(`${origin}/wp-admin/admin-post.php`, { form: { action: 'tc_owner_config', operation: 'trash', config_id: String(id), confirm: '1', _wpnonce: staffOwnNonce }, maxRedirects: 0 });
    expect([302, 303, 403].includes(denied.status()), 'Crafted Staff request is denied or redirected with an error.');
    state = wp(`echo 'TC_OWNER_BROWSER:'.wp_json_encode(get_post_status(${id}));`);
    expect(state === 'publish', 'Crafted Staff request cannot trash a config.');
    await staff.close();
    const trash = page.locator('form:has(input[name="operation"][value="trash"])');
    await trash.locator('[name="confirm"]').check(); await submit(trash, 'Move to Trash');
    state = wp(`echo 'TC_OWNER_BROWSER:'.wp_json_encode(get_post_status(${id}));`);
    expect(state === 'trash', 'Owner can explicitly move config to Trash.');
    expect(report.errors.length === 0, 'No browser application errors.');
    report.passed = true;
  } finally {
    if (browser) await browser.close();
    if (proxy) await new Promise(resolve => proxy.close(resolve));
    try { execFileSync('docker', ['exec', '--user', 'root', 'timonconfigs-wordpress', 'rm', '-f', muPath], { stdio: 'ignore' }); } catch {}
    if (fixture) {
      wp(`require_once ABSPATH.'wp-admin/includes/user.php';$users=array(${Object.values(fixture.users).join(',')});$posts=get_posts(array('post_type'=>'tc_config','post_status'=>array('publish','draft','trash'),'posts_per_page'=>-1,'s'=>'LOCAL OWNER HTTP ${tag}'));foreach($posts as $p){$root=TC_Private_Files::root();$dir=!is_wp_error($root)?$root.'/'.$p->ID:'';if($dir&&is_dir($dir)&&!is_link($dir)&&realpath($dir)===$dir){foreach(scandir($dir) as $entry){if($entry==='.'||$entry==='..'){continue;}$file=$dir.'/'.$entry;if(is_file($file)&&!is_link($file)){unlink($file);}}rmdir($dir);}foreach(get_children(array('post_parent'=>$p->ID,'post_type'=>'attachment','post_status'=>'any')) as $a){wp_delete_attachment($a->ID,true);}wp_delete_post($p->ID,true);}foreach($users as $uid){if(get_user_meta($uid,'_tc_owner_browser_fixture',true)!=='${tag}'){throw new RuntimeException('Fixture cleanup identity mismatch.');}delete_option('_tc_discord_binding_'.get_user_meta($uid,'_tc_discord_id',true));delete_option('_tc_discord_lock_'.$uid);delete_transient('tc_owner_feedback_'.$uid);wp_delete_user($uid);}echo 'TC_OWNER_BROWSER:'.wp_json_encode(true);`);
      report.restored = true;
    }
    if (directory) await fs.rm(directory, { recursive: true, force: true });
    await fs.mkdir(path.join(root, 'artifacts'), { recursive: true });
    await fs.writeFile(path.join(root, 'artifacts/owner-editor-browser.json'), JSON.stringify(report, null, 2));
  }
  console.log(JSON.stringify(report, null, 2));
})().catch(error => { console.error(error.message); process.exitCode = 1; });
