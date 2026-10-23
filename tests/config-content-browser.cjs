/** Real WordPress HTTP, public guide/denied values, keyboard and exact copying. Disposable local only. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = 'http://127.0.0.1:8080';
const token = randomBytes(8).toString('hex');
const valueText = `PROTECTED-${token}\nSensitivity = <10>;\nKeep = "quote";`;
const report = { passed: false, checks: 0, restored: false };
function check(value, label) { assert.ok(value, label); report.checks++; }
function wp(code) {
  const result = execFileSync('docker', ['compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', 'cli', 'wp', 'eval-file', '-'], { cwd: root, input: `<?php\n${code}`, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  const line = result.split('\n').find(line => line.startsWith('TC_CONTENT_TEST:'));
  if (!line) throw new Error('No fixture result.');
  return JSON.parse(line.slice('TC_CONTENT_TEST:'.length));
}
(async () => {
  let fixture, browser;
  try {
    fixture = wp(`if(!class_exists('TC_Config_Content')){throw new RuntimeException('Content module must be initialized.');}
$id=wp_insert_post(array('post_type'=>'tc_config','post_status'=>'publish','post_title'=>'LOCAL CONTENT HTTP ${token}','post_content'=>'Public test description.','meta_input'=>array('tc_release_status'=>'available','tc_tier'=>'free','_tc_content_fixture'=>'${token}')),true);
if(is_wp_error($id)){throw new RuntimeException('Fixture creation failed.');}
TC_Config_Content::save_fields($id,array('tc_guide_content'=>"<h3>Local setup guide</h3>\\n<ol><li>First step</li><li>Second step</li></ol><pre>one\\ntwo</pre><table><tr><th>Mode</th><td>Test</td></tr></table>",'tc_guide_visibility'=>'public','tc_values_content'=>base64_decode('${Buffer.from(valueText).toString('base64')}'),'tc_values_visibility'=>'monthly'));
echo 'TC_CONTENT_TEST:'.wp_json_encode(array('id'=>$id,'url'=>get_permalink($id)));`);
    browser = await chromium.launch({ executablePath: '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    await page.addInitScript(() => { window.tcCopied = null; Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async value => { window.tcCopied = value; } } }); });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    let response = await page.goto(fixture.url, { waitUntil: 'networkidle' });
    check(response.status() === 200, 'Actual config page responds.');
    check(!(await page.content()).includes(`PROTECTED-${token}`), 'Protected values absent from initial HTML.');
    const guide = page.locator(`[data-tc-content="guide"][data-config-id="${fixture.id}"]`).first();
    const values = page.locator(`[data-tc-content="values"][data-config-id="${fixture.id}"]`).first();
    check(await guide.count() === 1 && await values.count() === 1, 'Only existing content controls render.');
    const dialog = page.locator('#tc-config-content-dialog');
    await guide.click(); await page.waitForFunction(() => document.querySelector('.tc-content-body')?.textContent.includes('First step')).catch(async error => { console.error(JSON.stringify({stage:'guide-load',status:await dialog.locator('.tc-content-status').innerText(),open:await dialog.evaluate(el=>el.open),errors})); throw error; });
    check(await dialog.evaluate(el => el.open), 'Guide opens a native modal.');
    check(await dialog.locator('ol li').count() === 2 && await dialog.locator('table').count() === 1, 'Lists and tables retain structure.');
    check((await dialog.locator('pre').innerText()).includes('one\ntwo'), 'Code retains line breaks.');
    await dialog.locator('.tc-content-close').focus(); await page.keyboard.press('Shift+Tab');
    check(await dialog.locator('footer a').last().evaluate(el => el === document.activeElement), 'Shift-tab wraps focus inside dialog.');
    await page.keyboard.press('Tab');
    check(await dialog.locator('.tc-content-close').evaluate(el => el === document.activeElement), 'Tab wraps to close control.');
    await dialog.locator('.tc-content-copy').click();
    check((await page.evaluate(() => window.tcCopied)).includes('First step'), 'Guide copy uses loaded text.');
    await page.keyboard.press('Escape');
    check(!await dialog.evaluate(el => el.open) && await guide.evaluate(el => el === document.activeElement), 'Escape closes and restores opener focus.');
    check((await dialog.locator('.tc-content-body').innerText()) === '', 'Closing removes fetched content from DOM.');
    await values.click(); await page.waitForFunction(() => document.querySelector('.tc-content-status')?.textContent.includes('Sign in'));
    check(!(await page.content()).includes(`PROTECTED-${token}`), 'Denied values never enter DOM.');
    check(await dialog.locator('.tc-content-copy').isDisabled(), 'Denied content cannot be copied.');
    await page.keyboard.press('Escape');
    const endpoint = `${base}/wp-admin/admin-post.php`;
    response = await page.request.post(endpoint, { form: { action: 'tc_config_content', config_id: String(fixture.id), kind: 'values' } });
    check(response.status() === 403 && !(await response.text()).includes(`PROTECTED-${token}`), 'Forged request without nonce denied.');
    const nonceResponse = await page.request.get(`${endpoint}?action=tc_content_nonce`);
    check(/no-store/.test(nonceResponse.headers()['cache-control']) && /noindex/.test(nonceResponse.headers()['x-robots-tag']), 'Nonce route is private/no-store/noindex.');
    const nonce = (await nonceResponse.json()).data.nonce;
    response = await page.request.post(endpoint, { form: { action: 'tc_config_content', config_id: String(fixture.id), kind: 'values', _wpnonce: nonce } });
    check(response.status() === 401 && /no-store/.test(response.headers()['cache-control']), 'Valid nonce does not bypass membership.');
    const api = await page.request.get(`${base}/wp-json/wp/v2/tc_config/${fixture.id}`);
    check(api.status() === 200 && !(await api.text()).includes(`PROTECTED-${token}`), 'Public REST response cannot read protected metadata.');
    wp(`$id=${fixture.id}; if(get_post_meta($id,'_tc_content_fixture',true)!=='${token}'){throw new RuntimeException('Fixture ownership mismatch.');}update_post_meta($id,'_tc_values_visibility','public');echo 'TC_CONTENT_TEST:'.wp_json_encode(array('ok'=>true));`);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await values.click(); await page.waitForFunction(secret => document.querySelector('.tc-content-body')?.textContent.includes(secret), `PROTECTED-${token}`);
      check(await dialog.locator('code').count() === 1 && !(await dialog.locator('code').innerHTML()).includes('<10>'), `Values are escaped at ${width}.`);
      await dialog.locator('.tc-content-copy').click();
      const copied = await page.evaluate(() => window.tcCopied);
      check(copied.includes('Sensitivity = <10>;') && copied.includes('Keep = "quote";') && copied.includes('\n'), `Values copy exact syntax at ${width}.`);
      check(await dialog.evaluate(el => el.getBoundingClientRect().width <= innerWidth && el.getBoundingClientRect().height <= innerHeight), `Modal fits ${width}.`);
      await mkdir(path.join(root, 'artifacts'), { recursive: true });
      await page.screenshot({ path: path.join(root, 'artifacts', `config-values-${width}.png`) });
      await page.keyboard.press('Escape');
    }
    check(errors.length === 0, 'No browser application errors.');
    report.passed = true;
  } finally {
    if (browser) await browser.close();
    if (fixture) {
      const restored = wp(`$id=${fixture.id};if(get_post_meta($id,'_tc_content_fixture',true)!=='${token}'){throw new RuntimeException('Fixture ownership mismatch.');}wp_delete_post($id,true);echo 'TC_CONTENT_TEST:'.wp_json_encode(array('ok'=>null===get_post($id)));`);
      report.restored = restored.ok;
    }
    await mkdir(path.join(root, 'artifacts'), { recursive: true });
    await writeFile(path.join(root, 'artifacts', 'config-content-browser.json'), JSON.stringify(report, null, 2));
  }
  console.log(JSON.stringify(report));
})().catch(error => { console.error(error.message); process.exitCode = 1; });
