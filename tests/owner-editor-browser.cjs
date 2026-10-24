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
$game=get_term_by('slug','apex-legends','tc_game');$device=get_term_by('slug','cronus-zen','tc_device');if(!$game||!$device){throw new RuntimeException('Seeded game/device terms required.');}echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('users'=>$users,'settings'=>$settings,'game'=>$game->term_id,'device'=>$device->term_id));`);
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
 if(str_ends_with($url,'/member')&&get_user_meta($uid,'_tc_owner_browser_fail_member',true)==='once'){delete_user_meta($uid,'_tc_owner_browser_fail_member');return array('response'=>array('code'=>503),'body'=>'{}','headers'=>array());}
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
    expect(await page.getByRole('button', { name: 'Publish config', exact: true }).count() === 1, 'Owner has one short publish form.');
    expect(await page.locator('form:has(input[name="operation"][value="save"]) [name="description"]').isVisible() === false, 'Advanced description stays collapsed by default.');
    const title = `LOCAL OWNER HTTP ${tag}`;
    const quickForm = () => page.locator('form:has(input[name="operation"][value="save"])');
    const textBytes = Buffer.concat([Buffer.from([0xff, 0xfe]), Buffer.from('Sensitivity = 10\r\n', 'utf16le')]);
    async function fillBasic(configTitle, name = 'owner-test.txt', buffer = textBytes) {
      const form = quickForm();
      await form.locator('[name="title"]').fill(configTitle);
      await form.locator('[name="tc_game[]"]').selectOption(String(fixture.game));
      await form.locator('[name="tc_device[]"]').selectOption(String(fixture.device));
      await form.locator('[name="tc_tier"]').selectOption('free');
      await form.locator('input[name="tc_private_files[]"]').setInputFiles({ name, mimeType: 'text/plain', buffer });
    }
    let lastResponse;
    page.on('response', async response => {
      if (!response.url().includes('/wp-admin/admin-post.php') || response.request().method() !== 'POST') return;
      const data = await response.json().catch(() => ({}));
      lastResponse = {status:response.status(),success:data.success,code:data.data?.code,message:data.data?.message?.slice(0,240),redirect:data.data?.redirect ? new URL(data.data.redirect,origin).pathname + new URL(data.data.redirect,origin).search : undefined};
    });
    async function submit(form, button) {
      try { await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}), form.getByRole('button', { name: button, exact: true }).click()]); }
      catch (error) { throw new Error(`${error.message} Owner fixture response: ${JSON.stringify(lastResponse)}; feedback: ${JSON.stringify(await page.locator('[data-owner-result]').allTextContents())}`); }
    }
    async function reveal(form) { await form.evaluate(el => { for (let node = el.parentElement; node; node = node.parentElement) { if (node.tagName === 'DETAILS') node.open = true; } }); }
    // An actual rejected upload must not publish an empty card.
    await fillBasic(`${title} rejected`, 'unsafe.php', Buffer.from('<?php echo 1;'));
    await submit(quickForm(), 'Publish config');
    let rejected = wp(`$posts=get_posts(array('post_type'=>'tc_config','post_status'=>'publish','title'=>'${title} rejected','numberposts'=>-1));echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('published'=>count($posts)));`);
    expect(rejected.published === 0, 'Rejected HTTP upload never publishes an empty config card.');
    await page.goto(`${origin}/account/?tc_manage_configs=1`, { waitUntil: 'networkidle' });
    await fillBasic(title);
    // A provider outage must not navigate away, drop the file, or pretend the role was removed.
    wp(`update_user_meta(${fixture.users.owner},'_tc_owner_browser_fail_member','once');echo 'TC_OWNER_BROWSER:'.wp_json_encode(true);`);
    const failedResponse = await Promise.all([
      page.waitForResponse(response => response.url().includes('/wp-admin/admin-post.php') && response.request().method() === 'POST'),
      quickForm().getByRole('button', {name:'Publish config',exact:true}).click()
    ]).then(results => results[0]);
    expect(failedResponse.status() === 503, 'Fresh Owner verification fails closed with a temporary JSON response.');
    await quickForm().locator('[data-owner-result][role="alert"]').waitFor();
    expect(await quickForm().locator('[name="title"]').inputValue() === title, 'Temporary provider failure keeps the typed title.');
    expect(await quickForm().locator('input[name="tc_private_files[]"]').evaluate(input => input.files.length === 1 && input.files[0].name === 'owner-test.txt'), 'Temporary provider failure keeps the selected file in the browser.');
    let outage = wp(`$posts=get_posts(array('post_type'=>'tc_config','post_status'=>array('publish','draft'),'title'=>'${title}','numberposts'=>-1));echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('posts'=>count($posts),'roles'=>get_userdata(${fixture.users.owner})->roles,'discord'=>get_user_meta(${fixture.users.owner},'_tc_discord_id',true)));`);
    expect(outage.posts === 0 && outage.roles.length === 1 && outage.roles[0] === 'subscriber' && outage.discord === ownerDiscord, 'Failed verification performs no draft, role, or identity mutation.');
    // User clicks again: the existing cooldown is respected without changing _tc_discord_attempt.
    await submit(quickForm(), 'Publish config');
    id = Number(new URL(page.url()).searchParams.get('config_id'));
    expect(id > 0, 'One real submit creates the published config. ' + JSON.stringify({feedback:await page.locator('[role="status"],.tc-owner-feedback').allTextContents(),scripts:await page.locator('script[src*="owner-editor.js"]').count(),wait:await quickForm().getAttribute('data-wait-seconds')}));
    let state = wp(`$p=get_post(${id});echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('title'=>$p->post_title,'status'=>$p->post_status,'release'=>get_post_meta(${id},'tc_release_status',true),'ownerRole'=>get_userdata(${fixture.users.owner})->roles,'files'=>TC_Private_Files::files(${id}),'thumb'=>get_post_thumbnail_id(${id}),'game'=>wp_get_object_terms(${id},'tc_game',array('fields'=>'ids')),'device'=>wp_get_object_terms(${id},'tc_device',array('fields'=>'ids'))));`);
    expect(state.status === 'publish' && state.release === 'available' && state.title === title, 'Published title and available release persist.');
    expect(state.game.includes(fixture.game) && state.device.includes(fixture.device), 'Selected game/device persist without advanced options.');
    expect(state.ownerRole.length === 1 && state.ownerRole[0] === 'subscriber', 'Frontend write does not promote WordPress role.');
    expect(state.files.length === 1 && state.files[0].name === 'owner-test.txt', 'One-submit HTTP publish includes a validated private file.');
    expect(!JSON.stringify(state).includes('stored_name'), 'Owner file display does not expose storage names.');
    const catalog = await context.newPage();
    await catalog.goto(`${origin}/configs/?config_search=${encodeURIComponent(title)}`, { waitUntil: 'networkidle' });
    const card = catalog.locator(`.tc-config-card.post-${id}`);
    const cardCount = await card.count();
    let diagnostic = '';
    if (cardCount !== 1) {
      await catalog.screenshot({path:path.join(root,'artifacts/owner-card-diagnostic.png'),fullPage:true});
      const direct = wp(`$q=new WP_Query(array('post_type'=>'tc_config','post_status'=>'publish','s'=>'${title}','fields'=>'ids'));echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('found'=>$q->posts,'release'=>get_post_meta(${id},'tc_release_status',true)));`);
      diagnostic = JSON.stringify({path:new URL(catalog.url()).pathname,h1:await catalog.locator('h1').allTextContents(),cards:await catalog.locator('.tc-config-card h2').allTextContents(),direct});
    }
    expect(cardCount === 1, 'New config immediately appears as a catalog card. ' + diagnostic);
    expect(await card.getByRole('heading', { name: title, exact: true }).count() === 1, 'Card displays the genuine config title.');
    await card.locator('img').first().scrollIntoViewIfNeeded();
    await card.locator('img').first().evaluate(img => img.decode());
    expect(await card.locator('img').first().evaluate(img => img.naturalWidth > 0), 'Card inherits working game artwork without a separate image upload.');
    const downloadButton = card.getByRole('button', { name: /^Download / });
    expect(await downloadButton.count() === 1, 'Entitled member receives contextual Download on the card.');
    const [download] = await Promise.all([catalog.waitForEvent('download'), downloadButton.click()]);
    const downloadedPath = path.join(directory, 'original-windows.txt'); await download.saveAs(downloadedPath);
    expect((await fs.readFile(downloadedPath)).equals(textBytes), 'Real download preserves exact Windows UTF-16 upload bytes.');
    await catalog.screenshot({ path: path.join(root, 'artifacts/owner-published-card-1440.png'), fullPage: true });
    await catalog.close();
    // Automatic ZNBK approval: real multipart upload and authenticated byte delivery.
    const backup = wp(`$tmp=wp_tempnam('tc-zip-browser');$zip=new ZipArchive();if(true!==$zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)){throw new RuntimeException('Fixture ZIP unavailable.');}$zip->addFromString('slots/config.json','{"fixture":true}');$zip->close();$bytes=file_get_contents($tmp);unlink($tmp);echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('bytes'=>base64_encode($bytes),'scanner'=>has_filter('timonconfigs_private_file_scan')));`);
    expect(backup.scanner === false, 'ZNBK HTTP test runs without an external scanner-approval callback.');
    const backupBytes = Buffer.from(backup.bytes, 'base64');
    const upload = page.locator('form:has(input[name="operation"][value="upload"])');
    await upload.locator('input[type=file]').setInputFiles({name:'owner-backup.znbk',mimeType:'application/zip',buffer:backupBytes});
    await submit(upload, 'Upload script files');
    state = wp(`echo 'TC_OWNER_BROWSER:'.wp_json_encode(TC_Private_Files::files(${id}));`);
    expect(state.some(file => file.name === 'owner-backup.znbk') && state.length === 2, 'ZNBK is stored by actual HTTP upload without external approval.');
    const txtID = state.find(file => file.name === 'owner-test.txt').id;
    const order = page.locator('form:has(input[name="operation"][value="update"]):has(input[name="file_id"][value="'+txtID+'"])');
    await reveal(order); await order.locator('[name="order"]').fill('99'); await submit(order,'Update file details');
    const backupCatalog = await context.newPage();
    await backupCatalog.goto(`${origin}/configs/?config_search=${encodeURIComponent(title)}`, {waitUntil:'networkidle'});
    const backupCard = backupCatalog.locator(`.tc-config-card.post-${id}`);
    const [backupDownload] = await Promise.all([backupCatalog.waitForEvent('download'),backupCard.getByRole('button',{name:/^Download /}).click()]);
    const backupPath = path.join(directory,'delivered-backup.znbk'); await backupDownload.saveAs(backupPath);
    expect((await fs.readFile(backupPath)).equals(backupBytes), 'ZNBK downloads original ZIP bytes through the protected delivery route.');
    await backupCatalog.close();
    // Every supported common format can be attached during its first publish, not only later.
    const gpcBytes = Buffer.concat([Buffer.from('// Owner fixture '), Buffer.from([0xa9]), Buffer.from(' 2026\r\nmain { combo_run(Test); }\r\ncombo Test { wait(10); }\r\n')]);
    for (const [extension, bytes] of [['gpc', gpcBytes], ['znbk', backupBytes]]) {
      await page.goto(`${origin}/account/?tc_manage_configs=1`, {waitUntil:'networkidle'});
      await fillBasic(`${title} ${extension.toUpperCase()}`, `initial.${extension}`, bytes);
      await submit(quickForm(), 'Publish config');
      const initialID = Number(new URL(page.url()).searchParams.get('config_id'));
      const initial = wp(`echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('status'=>get_post_status(${initialID}),'release'=>get_post_meta(${initialID},'tc_release_status',true),'files'=>TC_Private_Files::files(${initialID})));`);
      expect(initialID > 0 && initial.status === 'publish' && initial.release === 'available' && initial.files.length === 1 && initial.files[0].name === `initial.${extension}`, `${extension.toUpperCase()} publishes with its private file in one actual HTTP submit.`);
      const initialCatalog = await context.newPage();
      await initialCatalog.goto(`${origin}/configs/?config_search=${encodeURIComponent(`${title} ${extension.toUpperCase()}`)}`, {waitUntil:'networkidle'});
      const initialCard = initialCatalog.locator(`.tc-config-card.post-${initialID}`);
      expect(await initialCard.count() === 1, `${extension.toUpperCase()} first publish immediately has a catalog card.`);
      const [initialDownload] = await Promise.all([initialCatalog.waitForEvent('download'),initialCard.getByRole('button',{name:/^Download /}).click()]);
      const initialPath = path.join(directory, `delivered-initial.${extension}`); await initialDownload.saveAs(initialPath);
      expect((await fs.readFile(initialPath)).equals(bytes), `${extension.toUpperCase()} protected download preserves the original first-upload bytes.`);
      await initialCatalog.close();
    }
    await page.goto(`${origin}/account/?tc_manage_configs=1&config_id=${id}`, {waitUntil:'networkidle'});
    // Generate an ordinary PNG using Chromium, then submit the real image form.
    const png = await page.screenshot({ clip: { x: 0, y: 0, width: 64, height: 64 } });
    const image = page.locator('form:has(input[name="operation"][value="image"])');
    await reveal(image);
    await image.locator('input[type=file]').setInputFiles({ name: 'fixture.png', mimeType: 'image/png', buffer: png });
    await submit(image, 'Upload custom picture');
    state = wp(`$a=get_post_thumbnail_id(${id});echo 'TC_OWNER_BROWSER:'.wp_json_encode(array('id'=>$a,'mime'=>get_post_mime_type($a)));`);
    expect(state.id > 0 && state.mime === 'image/jpeg', 'Public image is decoded/reencoded to a real JPEG attachment: ' + await page.locator('[role="status"]').allTextContents().then(x => x.join(' ').slice(0,200)));
    await quickForm().locator('[name="title"]').fill(`${title} edited`);
    await submit(quickForm(), 'Save changes');
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
    expect(await staffPage.getByRole('button', { name: 'Publish config', exact: true }).count() === 0, 'Staff sees no Owner form.');
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
    await reveal(trash);
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
