/** Actual admin multipart uploads. Disposable localhost only; fixtures and bytes are removed. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const path = require('node:path');
const { mkdir, writeFile } = require('node:fs/promises');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = new URL(process.env.TC_TEST_URL || 'http://127.0.0.1:8080/');
if (base.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(base.hostname) || base.port !== '8080' || base.pathname !== '/' || base.username || base.password || base.search || base.hash) throw new Error('Private-file mutations are restricted to disposable localhost:8080.');
const token = randomBytes(8).toString('hex');
const report = { passed: false, assertions: 0, restored: false, tests: [] };
function expect(ok, message) { assert.ok(ok, message); report.assertions++; }
function wp(code) {
  const output = execFileSync('docker', ['compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', 'cli', 'wp', 'eval-file', '-'], { cwd: root, input: `<?php\n${code}`, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  const line = output.split('\n').find(line => line.startsWith('TC_PRIVATE_BROWSER:'));
  if (!line) throw new Error('Fixture command returned no structured result.');
  return JSON.parse(line.slice('TC_PRIVATE_BROWSER:'.length));
}
const script = name => ({ name, mimeType: 'text/plain', buffer: Buffer.from(`// Disposable ${token}\nmain { set_val(PS4_RX, 0); }\n`) });
(async () => {
  let id, browser;
  try {
    const fixture = wp(`$root = TC_Private_Files::root(); if (is_wp_error($root)) { throw new RuntimeException($root->get_error_message()); }
$id = wp_insert_post(array('post_type'=>'tc_config','post_title'=>'LOCAL PRIVATE HTTP FIXTURE ${token}','post_status'=>'draft','meta_input'=>array('_tc_private_browser_fixture'=>'${token}')),true);
if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());} echo 'TC_PRIVATE_BROWSER:'.wp_json_encode(array('id'=>$id));`);
    id = fixture.id;
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('/wp-login.php', base).href);
    await page.locator('#user_login').fill(process.env.TC_TEST_ADMIN_USER || 'timonadmin');
    await page.locator('#user_pass').fill(process.env.TC_TEST_ADMIN_PASSWORD || 'local-preview-only');
    await Promise.all([page.waitForURL(url => url.pathname.startsWith('/wp-admin/')), page.locator('#wp-submit').click()]);
    const manage = new URL(`/wp-admin/edit.php?post_type=tc_config&page=tc-private-files&config_id=${id}`, base).href;
    async function reload() { await page.goto(manage, { waitUntil: 'domcontentloaded' }); }
    async function state() { return wp(`$audit=get_post_meta(${id},'_tc_private_file_audit',false); echo 'TC_PRIVATE_BROWSER:'.wp_json_encode(array('files'=>TC_Private_Files::files(${id}),'audits'=>count($audit),'actions'=>wp_list_pluck($audit,'action')));`); }
    async function submit(form) { await Promise.all([page.waitForURL(url => url.pathname === '/wp-admin/edit.php' && url.searchParams.get('page') === 'tc-private-files'), form.locator('input[type="submit"]').click()]); await page.waitForLoadState('domcontentloaded'); }
    await reload();
    expect((await page.locator('body').innerText()).includes('Private script files'), 'Dedicated management screen exists.');
    expect(!(await page.content()).includes('/var/lib/timonconfigs-private'), 'No private root appears in admin HTML.');
    let nonce = await page.locator('input[name="tc_private_files_nonce"]').first().inputValue();
    let response = await page.request.post(new URL('/wp-admin/admin-post.php', base).href, { form: { action: 'tc_private_files_manage', config_id: String(id), operation: 'upload', tc_private_files_nonce: nonce } });
    expect((await response.text()).includes('Choose at least one script file.'), 'Zero-file upload reports a clear error.');
    expect((await state()).files.length === 0, 'Zero-file request creates no records.');
    response = await page.request.post(new URL('/wp-admin/admin-post.php', base).href, { form: { action: 'tc_private_files_manage', config_id: String(id), operation: 'upload', 'tc_private_files_nonce[]': 'malformed' } });
    expect(response.status() === 403, 'Array-shaped nonce is rejected with HTTP403.');
    expect(!/(Warning|Fatal error):/.test(await response.text()), 'Malformed nonce exposes no PHP diagnostics.');
    await reload();
    let upload = page.locator('form:has(input[name="operation"][value="upload"])');
    await upload.locator('input[type="file"]').setInputFiles(script('apex-test.gpc'));
    await upload.locator('input[name="version"]').fill('1.0');
    await upload.locator('textarea[name="notes"]').fill('Test-only release notes');
    await submit(upload);
    let current = await state();
    expect(current.files.length === 1 && current.files[0].version === '1.0', 'One actual HTTP upload persists metadata.');
    expect(/^[a-f0-9]{64}$/.test(current.files[0].checksum), 'Stored checksum is computed SHA256.');
    const first = current.files[0];
    const fileForm = (fileID, op) => page.locator(`section:has(input[name="file_id"][value="${fileID}"]) form:has(input[name="operation"][value="${op}"])`);
    await reload(); upload = page.locator('form:has(input[name="operation"][value="upload"])');
    await upload.locator('input[type="file"]').setInputFiles(Array.from({ length: 10 }, (_, i) => script(`batch-${i}.gpc`)));
    await submit(upload); current = await state();
    expect(current.files.length === 11, 'Ten actual files can be uploaded in one operation.');
    expect(new Set(current.files.map(file => file.id)).size === 11, 'Every stored file has a separate opaque identifier.');
    await reload(); upload = page.locator('form:has(input[name="operation"][value="upload"])');
    await upload.locator('input[type="file"]').setInputFiles(script('apex-test.gpc'));
    await submit(upload); current = await state();
    expect(current.files.length === 12 && current.files.filter(file => file.name === 'apex-test.gpc').length === 2, 'Duplicate original names append instead of overwriting.');
    for (const bad of [
      { name: 'archive.zip', mimeType: 'application/zip', buffer: Buffer.from('PK\x03\x04unsafe') },
      { name: 'php.gpc', mimeType: 'text/plain', buffer: Buffer.from('<?php echo 1;') },
      { name: 'invalid.json', mimeType: 'application/json', buffer: Buffer.from('{invalid}') },
      { name: 'empty.gpc', mimeType: 'text/plain', buffer: Buffer.alloc(0) },
    ]) {
      await reload(); upload = page.locator('form:has(input[name="operation"][value="upload"])');
      await upload.locator('input[type="file"]').setInputFiles(bad); await submit(upload);
      expect((await state()).files.length === 12, `Invalid file ${bad.name} does not add a record.`);
      expect(await page.locator('.notice-error').count() > 0, `Invalid file ${bad.name} reports an error.`);
    }
    await reload(); upload = page.locator('form:has(input[name="operation"][value="upload"])');
    await upload.locator('input[type="file"]').setInputFiles(Array.from({ length: 11 }, (_, i) => script(`too-many-${i}.gpc`)));
    await submit(upload);
    expect((await state()).files.length === 12, 'Eleven simultaneous uploads are rejected atomically.');
    await reload();
    let form = fileForm(first.id, 'update');
    await form.locator('input[name="order"]').fill('9999');
    await form.locator('input[name="version"]').fill('1.1');
    await form.locator('textarea[name="notes"]').fill('Reordered notes');
    await submit(form); current = await state();
    expect(current.files.at(-1).id === first.id && current.files.at(-1).version === '1.1', 'Version/notes and display order are editable.');
    await reload(); form = fileForm(first.id, 'replace');
    await form.locator('input[type="file"]').setInputFiles(script('replacement.gpc'));
    await form.locator('input[name="version"]').fill('2.0');
    await form.locator('input[name="confirm"]').check();
    await submit(form); current = await state();
    const replacement = current.files.find(file => file.name === 'replacement.gpc');
    expect(current.files.length === 12 && replacement && replacement.id !== first.id, 'Replacement keeps count and revokes the old identifier.');
    expect(replacement.notes === 'Reordered notes' && replacement.order === 9999, 'Replacement preserves editable notes/order.');
    expect(wp(`echo 'TC_PRIVATE_BROWSER:'.wp_json_encode(array('oldRejected'=>is_wp_error(TC_Private_Files::resolve(${id},'${first.id}'))));`).oldRejected, 'Old file ID no longer resolves.');
    await reload(); nonce = await page.locator('input[name="tc_private_files_nonce"]').first().inputValue();
    response = await page.request.post(new URL('/wp-admin/admin-post.php', base).href, { form: { action: 'tc_private_files_manage', config_id: String(id), operation: 'delete', tc_private_files_nonce: nonce, file_id: replacement.id } });
    expect((await response.text()).includes('Confirm this file replacement or deletion'), 'Destructive action requires explicit confirmation.');
    expect((await state()).files.length === 12, 'Unconfirmed deletion leaves all files intact.');
    await reload(); form = fileForm(replacement.id, 'delete');
    await form.locator('input[name="confirm"]').check(); await submit(form); current = await state();
    expect(current.files.length === 11 && !current.files.some(file => file.id === replacement.id), 'Confirmed deletion removes the selected record.');
    expect(current.audits === 15 && current.actions.filter(action => action === 'uploaded').length === 12 && ['metadata-updated', 'replaced', 'deleted'].every(action => current.actions.includes(action)), 'Every successful upload/edit/replace/delete appends its audit event; rejected requests do not invent events.');
    const rest = await page.request.get(new URL(`/wp-json/wp/v2/tc_config/${id}?context=edit`, base).href);
    const restBody = await rest.text();
    expect(!restBody.includes('stored_name') && !restBody.includes('_tc_private_files'), 'Even editor REST output does not disclose private records.');
    expect(errors.length === 0, 'No application page errors during real multipart operations.');
    report.tests = ['zero/one/ten uploads', 'eleven rejected', 'duplicate filenames', 'archive/PHP/JSON/empty rejection', 'metadata/order', 'confirmed replace/delete', 'old-ID revocation', 'append-only audit', 'REST path secrecy'];
    report.passed = true;
  } catch (error) { report.error = error.message; throw error; }
  finally {
    if (browser) await browser.close();
    if (id) {
      const result = wp(`$id=${id}; if(get_post_meta($id,'_tc_private_browser_fixture',true)!=='${token}'){throw new RuntimeException('Fixture ownership mismatch.');}
$root=TC_Private_Files::root();if(is_wp_error($root)){throw new RuntimeException('Private root unavailable for fixture cleanup.');}$directory=$root.DIRECTORY_SEPARATOR.$id;
if(is_dir($directory)){if(is_link($directory)||realpath($directory)!==$directory){throw new RuntimeException('Unsafe fixture cleanup directory.');}foreach(scandir($directory) as $name){if($name==='.'||$name==='..'){continue;}$path=$directory.DIRECTORY_SEPARATOR.$name;if(is_link($path)||!is_file($path)){throw new RuntimeException('Unexpected fixture entry.');}unlink($path);}rmdir($directory);}
wp_delete_post($id,true);delete_transient('tc_private_notice_'.get_current_user_id());echo 'TC_PRIVATE_BROWSER:'.wp_json_encode(array('restored'=>null===get_post($id)));`);
      report.restored = result.restored;
    }
    await mkdir(path.join(root, 'artifacts'), { recursive: true });
    await writeFile(path.join(root, 'artifacts/private-files-browser.json'), JSON.stringify(report, null, 2));
  }
  console.log(JSON.stringify(report, null, 2));
})().catch(error => { console.error(error); process.exitCode = 1; });
