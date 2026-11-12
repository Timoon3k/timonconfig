/** Native download POST/303/attachment regression on disposable localhost WordPress. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes, createHash } = require('node:crypto');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const https = require('node:https');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
if ((process.env.TC_TEST_URL || 'http://127.0.0.1:8080/') !== 'http://127.0.0.1:8080/') throw new Error('Only disposable localhost WordPress may be mutated.');
const tag = randomBytes(12).toString('hex');
const cookieName = `tc_download_test_${tag}`;
const muPath = `/var/www/html/wp-content/mu-plugins/tc-download-browser-${tag}.php`;
const env = { ...process.env };
for (const name of ['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH']) delete env[name];
const report = { passed: false, restored: false, assertions: 0, downloads: [], http: [], screenshots: [], errors: [] };
const expect = (value, message) => { assert.ok(value, message); report.assertions++; };
const docker = (args, options = {}) => execFileSync('docker', ['--host=unix:///var/run/docker.sock', ...args], { cwd: root, env, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'], ...options });
function fixtureCommand(mode) {
  const output = docker(['compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', '-e', `TC_DOWNLOAD_DELIVERY_MODE=${mode}`, '-e', `TC_DOWNLOAD_DELIVERY_TAG=${tag}`, '-v', `${path.join(root, 'tests')}:/tests:ro`, 'cli', 'wp', 'eval-file', '/tests/download-delivery-browser-fixture.php']);
  const line = output.split('\n').find(line => line.startsWith('TC_DOWNLOAD_BROWSER:'));
  if (!line) throw new Error('Fixture returned no structured result.');
  return JSON.parse(line.slice('TC_DOWNLOAD_BROWSER:'.length));
}
const writeMu = content => docker(['exec', '-i', '--user', 'root', 'timonconfigs-wordpress', 'sh', '-c', `mkdir -p /var/www/html/wp-content/mu-plugins && cat > ${muPath} && chmod 644 ${muPath}`], { input: content });
(async () => {
  let fixture, directory, browser, proxy, origin, failure;
  const contexts = [];
  const upstreams = new Set();
  try {
    fixture = fixtureCommand('setup');
    await fs.mkdir(path.join(root, 'artifacts'), { recursive: true });
    directory = await fs.mkdtemp(path.join(os.tmpdir(), 'tc-download-browser-'));
    execFileSync('openssl', ['req', '-x509', '-nodes', '-newkey', 'rsa:2048', '-days', '1', '-subj', '/CN=127.0.0.1', '-addext', 'subjectAltName=IP:127.0.0.1', '-keyout', path.join(directory, 'key.pem'), '-out', path.join(directory, 'cert.pem')], { stdio: 'ignore' });
    proxy = https.createServer({ key: await fs.readFile(path.join(directory, 'key.pem')), cert: await fs.readFile(path.join(directory, 'cert.pem')) }, (request, response) => {
      if (!(request.headers.cookie || '').split(';').some(item => item.trim() === `${cookieName}=${tag}`)) { response.writeHead(403); response.end(); return; }
      const upstream = http.request({ hostname: '127.0.0.1', port: 8080, path: request.url, method: request.method, headers: { ...request.headers, 'accept-encoding': 'identity', 'x-tc-download-fixture': tag, 'x-forwarded-proto': 'https' } }, incoming => {
        // Only HTML asset origins are rewritten. Attachment bytes and headers pass unchanged.
        if ((incoming.headers['content-type'] || '').includes('text/html')) {
          const chunks = []; incoming.on('data', chunk => chunks.push(chunk));
          incoming.on('end', () => {
            const body = Buffer.from(Buffer.concat(chunks).toString('utf8').replaceAll('http://127.0.0.1:8080', origin).replaceAll('https://127.0.0.1:8080', origin));
            const headers = { ...incoming.headers, 'content-length': body.length }; delete headers['transfer-encoding'];
            response.writeHead(incoming.statusCode, headers); response.end(body);
          });
        } else { response.writeHead(incoming.statusCode, incoming.headers); incoming.pipe(response); }
      });
      upstreams.add(upstream); upstream.on('close', () => upstreams.delete(upstream));
      upstream.on('error', () => { if (!response.headersSent) response.writeHead(502); response.end(); }); request.pipe(upstream);
    });
    await new Promise(resolve => proxy.listen(0, '127.0.0.1', resolve));
    origin = `https://127.0.0.1:${proxy.address().port}`;
    writeMu(`<?php
/** LOCAL QA only: random TLS proxy, cookie, header and original database origin must all match. */
if (($_SERVER['HTTP_X_TC_DOWNLOAD_FIXTURE']??'')!=='${tag}' || ($_COOKIE['${cookieName}']??'')!=='${tag}' || ($_SERVER['HTTP_HOST']??'')!=='127.0.0.1:${proxy.address().port}' || get_option('home')!=='http://127.0.0.1:8080') { return; }
$_SERVER['HTTPS']='on';
add_filter('option_home',static fn()=>'${origin}');add_filter('option_siteurl',static fn()=>'${origin}');
add_filter('pre_option_timonconfigs_discord',static fn()=>json_decode('${JSON.stringify(fixture.settings)}',true));
add_filter('pre_http_request',static function($pre,$args,$url){
 $uid=get_current_user_id();
 if(get_user_meta($uid,'_tc_download_delivery_browser_tag',true)!=='${tag}' || $url!=='https://discord.com/api/v10/users/@me/guilds/${fixture.settings.guild_id}/member'){return new WP_Error('fixture_outbound','External requests disabled for this fixture.');}
 return array('response'=>array('code'=>200),'body'=>wp_json_encode(array('user'=>array('id'=>get_user_meta($uid,'_tc_discord_id',true)),'roles'=>get_user_meta($uid,'_tc_download_delivery_roles',true))),'headers'=>array());
},1,3);
add_action('pre_get_posts',static function($query){if('tc_config'===$query->get('post_type')){$query->set('post__in',array(${Object.values(fixture.cases).map(item => item.id).join(',')}));}});
// Match hosts which restrict wp-admin for Subscriber members and send downloads to member login.
add_action('admin_init',static function(){if(($_POST['action']??'')==='tc_request_download' && ($_SERVER['HTTP_X_TC_DOWNLOAD_ALLOW_LEGACY']??'')!=='1'){wp_safe_redirect(home_url('/account/?download-fixture-admin-blocked=1'),302);exit;}},-999);
// A delivered attachment must precede normal page middleware and template redirects.
add_action('template_redirect',static function(){if(isset($_GET['tc_download'])){wp_safe_redirect(home_url('/account/?download-fixture-late-middleware=1'),302);exit;}},-999);
add_action('parse_request',static function(){
 $backup=get_option('_tc_download_delivery_browser_fixture');$token=$_GET['tc_download']??'';
 if(is_string($token)&&preg_match('/^([A-Za-z0-9_-]+)\\.([a-f0-9]{64})$/D',$token,$parts)&&hash_equals(hash_hmac('sha256',$parts[1],wp_salt('auth')),$parts[2])){
  $payload=json_decode(base64_decode(strtr($parts[1],'-_','+/')),true);$actors=array_column($backup['users'],'id');
  if(is_array($payload)&&in_array($payload['u']??0,$actors,true)&&get_post_meta($payload['c']??0,'_tc_download_delivery_browser_tag',true)==='${tag}'&&preg_match('/^[a-f0-9]{48}$/D',$payload['n']??'')){
   $claim='_tc_download_claim_'.hash('sha256',$payload['n']);if(!in_array($claim,$backup['claims'],true)){$backup['claims'][]=$claim;update_option('_tc_download_delivery_browser_fixture',$backup,false);}
  }
 }
 if(isset($_GET['tc_download_fixture_login'])){
  $kind=$_GET['tc_download_fixture_login'];$uid=$backup['users'][$kind]['id']??0;
  if(!$uid||get_user_meta($uid,'_tc_download_delivery_browser_tag',true)!=='${tag}'){wp_die('Unknown fixture actor.');}
  wp_set_current_user($uid);wp_set_auth_cookie($uid,false,true);nocache_headers();wp_safe_redirect(home_url('/account/'),303);exit;
 }
 if(isset($_GET['tc_download_fixture_nonce'])){
  $id=absint($_GET['tc_download_fixture_nonce']);if(get_post_meta($id,'_tc_download_delivery_browser_tag',true)!=='${tag}'){wp_die('Unknown fixture release.');}
  nocache_headers();wp_send_json_success(array('nonce'=>wp_create_nonce('tc_download_'.$id.'_'.sanitize_text_field($_GET['file_id']??''))));
 }
 if(isset($_GET['tc_download_fixture_status'])){nocache_headers();wp_send_json_success(array('state'=>TC_Discord::status(),'manage'=>TC_Discord::can_manage_configs_local()));}
},-100);
if(isset($_GET['tc_download'])&&($_GET['tc_download_fixture_buffer']??'')==='locked'){ob_start(null,0,PHP_OUTPUT_HANDLER_CLEANABLE|PHP_OUTPUT_HANDLER_FLUSHABLE);echo 'LOCAL QA non-removable buffer';}
`);
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    async function actor(kind, width = 1440) {
      const context = await browser.newContext({ ignoreHTTPSErrors: true, acceptDownloads: true, viewport: { width, height: width === 390 ? 844 : 1000 } }); contexts.push(context);
      await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
      await context.addCookies([{ name: cookieName, value: tag, url: origin, secure: true, httpOnly: true }]);
      const page = await context.newPage();
      if (kind !== 'guest') await page.goto(`${origin}/?tc_download_fixture_login=${kind}`, { waitUntil: 'networkidle' });
      return { context, page, kind };
    }
    const owner = await actor('owner');
    const free = await actor('free');
    const monthly = await actor('monthly', 390);
    const lifetime = await actor('lifetime', 390);
    const ownerOnly = await actor('owner-only');
    const unverified = await actor('unverified');
    const guest = await actor('guest');
    const itemURL = item => new URL(item.url).pathname;
    async function openForm(user, key, route, width) {
      const item = fixture.cases[key];
      if (width) await user.page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
      const url = route === 'catalogue' ? `/configs/?config_search=${encodeURIComponent(item.title)}` : route === 'library' ? '/my-downloads/' : itemURL(item);
      await user.page.goto(origin + url, { waitUntil: 'networkidle' });
      const html = await user.page.content();
      expect(!html.includes('/var/lib/timonconfigs-private') && !html.includes('stored_name'), 'Public markup contains no private storage paths.');
      expect(!html.includes('LOCAL_QA_XIM_MATRIX') && !html.includes('LOCAL_QA_STORED_MATRIX') && !html.includes('LOCAL_QA_UNUSED_VALUES_MUST_NOT_EXPORT'), 'Protected Matrix values and stored TXT contents are not embedded in page markup.');
      const form = user.page.locator(`form.tc-download-form:has(input[name="config_id"][value="${item.id}"]):has(input[name="file_id"][value="${item.file_id}"])`).first();
      const count = await form.count();
      const diagnostic = count === 1 ? '' : JSON.stringify({ url: user.page.url(), headings: await user.page.locator('h1,h2').allTextContents(), forms: await user.page.locator('form.tc-download-form input[name="config_id"]').evaluateAll(inputs => inputs.map(input => input.value)), status: await user.context.request.get(`${origin}/?tc_download_fixture_status=1`).then(response => response.json()) });
      expect(count === 1, `${user.kind} sees ${key} native download on ${route}. ${diagnostic}`);
      const action = new URL(await form.getAttribute('action'), origin);
      expect(action.origin === origin && action.pathname === '/' && action.searchParams.get('tc_download_request') === '1' && (await form.getAttribute('method')).toLowerCase() === 'post', 'Download form posts to the public same-origin endpoint.');
      return form;
    }
    async function saveNative(user, key, route, width) {
      const form = await openForm(user, key, route, width);
      const responses = [];
      const observe = response => { const request = response.request(); if (request.method() === 'POST' && new URL(request.url()).searchParams.get('tc_download_request') === '1') responses.push(response.status()); };
      user.page.on('response', observe);
      const [download] = await Promise.all([user.page.waitForEvent('download', { timeout: 20000 }), form.locator('button').click()]);
      const file = path.join(directory, `${report.downloads.length}-${key}`); await download.saveAs(file);
      user.page.off('response', observe);
      const bytes = await fs.readFile(file); const item = fixture.cases[key];
      expect(bytes.equals(Buffer.from(item.bytes, 'base64')), `${key} saveAs preserves exact original bytes.`);
      expect(download.suggestedFilename() === item.name, `${key} saves using the original safe filename.`);
      expect(responses.includes(303), 'The real browser native POST receives HTTP 303.');
      report.downloads.push({ actor: user.kind, case: key, route, width: user.page.viewportSize().width, filename: download.suggestedFilename(), bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex') });
      if (user.kind === 'owner' && ((key === 'gpc' && route === 'catalogue') || (key === 'txt' && route === 'library'))) {
        const screenshot = `artifacts/download-${route}-${user.page.viewportSize().width}.png`;
        await user.page.screenshot({ path: path.join(root, screenshot), fullPage: true }); report.screenshots.push(screenshot);
      }
    }
    // Actual customer scenario: Subscriber Owner with verified membership downloads without wp-admin.
    for (const [key, route, width] of [['gpc', 'catalogue', 1440], ['znbk', 'single', 1440], ['txt', 'library', 390], ['matrix', 'library', 390], ['matrix-txt', 'catalogue', 390]]) await saveNative(owner, key, route, width);
    await saveNative(free, 'gpc', 'single', 1440);
    await saveNative(monthly, 'matrix', 'library', 390);
    await saveNative(lifetime, 'matrix-restricted', 'library', 390);
    await monthly.page.goto(`${origin}/my-downloads/`, { waitUntil: 'networkidle' });
    for (const key of ['txt', 'matrix-binary', 'matrix-restricted']) expect(await monthly.page.locator(`.tc-member-release:has(input[name="config_id"][value="${fixture.cases[key].id}"])`).count() === 0, `My Downloads does not advertise inaccessible/non-exportable ${key}.`);
    async function dataFor(user, key, fileID = fixture.cases[key].file_id) {
      const item = fixture.cases[key];
      const response = await user.context.request.get(`${origin}/?tc_download_fixture_nonce=${item.id}&file_id=${encodeURIComponent(fileID)}`);
      expect(response.status() === 200, 'The fixture creates a nonce in the real HTTP login session.');
      return { action: 'tc_request_download', config_id: String(item.id), file_id: fileID, _wpnonce: (await response.json()).data.nonce };
    }
    async function mint(user, key, endpoint = '/?tc_download_request=1', headers) {
      const response = await user.context.request.post(origin + endpoint, { form: await dataFor(user, key), maxRedirects: 0, headers });
      expect(response.status() === 303, `${key} authenticated POST mints a 303 redirect.`);
      expect(!response.headers()['content-disposition'], 'Ticket redirect is not itself the attachment.');
      const location = new URL(response.headers().location, origin);
      expect(location.origin === origin && location.searchParams.has('tc_download') && !location.href.includes('/var/'), 'Ticket carries no storage path and stays on the public origin.');
      report.http.push({ method: 'POST', path: endpoint.split('?')[0], status: response.status() });
      return location.href;
    }
    async function delivery(user, key, ticket, status = 200) {
      const response = await user.context.request.get(ticket, { maxRedirects: 0, timeout: 15000 });
      const headers = response.headers(); const bytes = await response.body();
      expect(response.status() === status, `${key} delivery returns HTTP ${status}.`);
      expect((headers['cache-control'] || '').includes('no-store') && headers['x-content-type-options'] === 'nosniff' && (headers['x-robots-tag'] || '').includes('noindex'), 'Private responses disable caching, sniffing and indexing.');
      if (status === 200) {
        expect((headers['content-disposition'] || '').startsWith('attachment;') && (headers['content-disposition'] || '').includes(fixture.cases[key].name), 'HTTP response explicitly delivers a named attachment.');
        expect(bytes.equals(Buffer.from(fixture.cases[key].bytes, 'base64')), 'HTTP attachment bytes match the protected original exactly.');
      } else {
        expect(!headers['content-disposition'], 'Denied delivery never presents an attachment.');
        expect(!bytes.equals(Buffer.from(fixture.cases[key].bytes, 'base64')) && !bytes.toString().includes('/var/lib/timonconfigs-private'), 'Denied response contains no protected file or storage path.');
      }
      report.http.push({ method: 'GET', path: '/', status: response.status(), attachment: !!headers['content-disposition'] });
      return response;
    }
    const blocked = await owner.context.request.post(`${origin}/wp-admin/admin-post.php`, { form: await dataFor(owner, 'znbk'), maxRedirects: 0 });
    expect(blocked.status() === 302 && new URL(blocked.headers().location).pathname === '/account/' && !blocked.headers()['content-disposition'], 'Restricted admin_init reproduces the reported download-to-member-profile redirect.');
    const ticket = await mint(owner, 'znbk'); await delivery(owner, 'znbk', ticket); await delivery(owner, 'znbk', ticket, 403);
    await delivery(owner, 'gpc', await mint(owner, 'gpc', '/wp-admin/admin-post.php', { 'X-TC-Download-Allow-Legacy': '1' }));
    const freeSecondSession = await actor('free');
    const boundTicket = await mint(free, 'gpc');
    await delivery(freeSecondSession, 'gpc', boundTicket, 403); await delivery(free, 'gpc', boundTicket);
    for (const [user, key] of [[free, 'znbk'], [monthly, 'txt'], [monthly, 'matrix-restricted'], [ownerOnly, 'znbk'], [unverified, 'gpc'], [guest, 'gpc']]) {
      await user.page.goto(origin + itemURL(fixture.cases[key]), { waitUntil: 'networkidle' });
      expect(await user.page.locator(`form.tc-download-form input[name="config_id"][value="${fixture.cases[key].id}"]`).count() === 0, `${user.kind} cannot see an unauthorized ${key} download form.`);
      const denied = await user.context.request.post(`${origin}/?tc_download_request=1`, { form: await dataFor(user, key), maxRedirects: 0 });
      expect(denied.status() === 403 && !denied.headers()['content-disposition'], `${user.kind} forged valid-nonce request cannot bypass native membership authorization.`);
    }
    const ownerStatus = await ownerOnly.context.request.get(`${origin}/?tc_download_fixture_status=1`);
    expect((await ownerStatus.json()).data.manage === true, 'Owner-only negative actor has genuine local Owner management authorization.');
    await unverified.page.goto(origin + itemURL(fixture.cases.gpc), { waitUntil: 'networkidle' });
    expect(await unverified.page.locator('a[href*="/account/"]').count() > 0, 'Unverified members retain a genuine account/verification CTA.');
    const badNonce = await free.context.request.post(`${origin}/?tc_download_request=1`, { form: { ...(await dataFor(free, 'gpc')), _wpnonce: 'forged' }, maxRedirects: 0 });
    expect(badNonce.status() === 403 && !badNonce.headers()['content-disposition'], 'Forged nonce cannot mint a file ticket.');
    const mismatchedNonce = await owner.context.request.post(`${origin}/?tc_download_request=1`, { form: await dataFor(free, 'gpc'), maxRedirects: 0 });
    expect(mismatchedNonce.status() === 403 && !mismatchedNonce.headers()['content-disposition'], 'A valid nonce from another actor/session cannot authorize the Owner request.');
    const forgedFile = await owner.context.request.post(`${origin}/?tc_download_request=1`, { form: await dataFor(owner, 'gpc', '11111111-1111-4111-8111-111111111111'), maxRedirects: 0 });
    expect(forgedFile.status() === 403 && !forgedFile.headers()['content-disposition'], 'A valid nonce does not grant access to an unregistered file ID.');
    await delivery(owner, 'gpc', `${origin}/?tc_download=forged-ticket`, 403);
    const wrongMethod = await owner.context.request.get(`${origin}/?tc_download_request=1`, { maxRedirects: 0 });
    expect(wrongMethod.status() === 405, 'The native request endpoint rejects GET.');
    const locked = new URL(await mint(owner, 'matrix')); locked.searchParams.set('tc_download_fixture_buffer', 'locked');
    const started = Date.now(); await delivery(owner, 'matrix', locked.href, 500);
    expect(Date.now() - started < 10000, 'A non-removable PHP output buffer fails promptly instead of hanging or corrupting the attachment.');
    const stale = await mint(monthly, 'znbk'); expect(fixtureCommand('revoke').revoked, 'Fixture membership role was revoked.'); await delivery(monthly, 'znbk', stale, 403);
    // A change before prepare() is caught by resolve() and receives the existing generic 403.
    // Descriptor mutations after resolution are covered separately by download-stream.cjs.
    const changed = await mint(free, 'gpc'); expect(fixtureCommand('tamper').tampered, 'Fixture stored bytes changed without changing length.'); await delivery(free, 'gpc', changed, 403);
    const stats = fixtureCommand('stats');
    expect(Object.values(stats.roles).every(roles => roles.length === 1 && roles[0] === 'subscriber'), 'No fixture member or Owner was promoted to WordPress administrator.');
    expect((stats.recent.owner || []).length >= 5 && stats.claims > 0, 'Only completed downloads are recorded and claim cleanup is tracked.');
    report.passed = true;
  } catch (error) { failure = error; report.errors.push(error.stack || error.message); }
  finally {
    for (const context of contexts) await context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    for (const upstream of upstreams) upstream.destroy();
    if (proxy) { proxy.closeAllConnections(); await new Promise(resolve => proxy.close(resolve)); }
    try { docker(['exec', '--user', 'root', 'timonconfigs-wordpress', 'rm', '-f', muPath]); } catch (error) { report.errors.push('MU cleanup failed: ' + error.message); report.passed = false; }
    if (fixture) {
      try { report.cleanup = fixtureCommand('restore'); report.restored = report.cleanup.restored; }
      catch (error) { report.errors.push('Fixture restoration failed: ' + error.message); report.passed = false; }
    }
    if (directory) await fs.rm(directory, { recursive: true, force: true });
    await fs.mkdir(path.join(root, 'artifacts'), { recursive: true });
    await fs.writeFile(path.join(root, 'artifacts/download-delivery-browser.json'), JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify({ passed: report.passed, assertions: report.assertions, downloads: report.downloads.length, restored: report.restored, errors: report.errors }));
    if (failure || !report.passed || !report.restored) process.exitCode = 1;
  }
})();
