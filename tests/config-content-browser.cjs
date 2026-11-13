/** Real WordPress HTTP, Discord links, protected values, keyboard and byte-exact copying. Local only. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = 'http://127.0.0.1:8080';
const token = randomBytes(8).toString('hex');
const links = {
  message: 'https://discord.com/channels/111111111111111111/222222222222222222/333333333333333333',
  channel: 'https://discord.com/channels/111111111111111111/222222222222222222',
  invite: 'https://discord.gg/LocalQaInvite',
  short: 'discord.gg/LocalQaShort',
  query: 'https://discord.com/channels/111111111111111111/222222222222222222?jump=1&source=guide',
  existing: 'https://discord.com/invite/LocalQaExisting',
  smart: 'https://discord.gg/LocalQaSmart',
  first: 'https://discord.gg/LocalQaFirst',
  second: 'https://discord.gg/LocalQaSecond',
  legacy: 'https://discordapp.com/channels/111111111111111111/222222222222222222',
  canary: 'https://canary.discord.com/channels/111111111111111111/222222222222222222',
  ptb: 'https://ptb.discord.com/channels/111111111111111111/222222222222222222',
  code: 'https://discord.gg/LocalQaCode',
};
const invalid = [
  'https://discord.com.evil.example/channels/1/2',
  'https://discord.gg.evil.example/LocalQaSpoof',
  'https://username:password@discord.com/channels/1/2',
  'https://example.invalid/setup',
];
const guideText = `<h3>Local setup guide</h3>\n<ol><li>First step</li><li>Second step</li></ol><pre>one\ntwo</pre><table><tr><th>Mode</th><td>Test</td></tr></table><p>Message ${links.message}.</p><p>Channel ${links.channel}</p><p>Invite (${links.invite}).</p><p>Short ${links.short}</p><p>Query ${links.query}.</p><p>Smart „${links.smart}”</p><p>Pair ${links.first},${links.second}</p><p>Legacy ${links.legacy}</p><p>Canary ${links.canary}</p><p>PTB ${links.ptb}</p><p><a href="${links.existing}">Existing Discord guide link</a></p><code>${links.code}</code><p>${invalid.join('\n')}</p>`;
const valueText = `PROTECTED-${token}\nSensitivity = <10>;\nKeep = "quote";\n  Indent = 2;\nMessage ${links.message}.\nChannel ${links.channel}\nInvite (${links.invite}).\nShort ${links.short}\nQuery ${links.query}.\nSmart „${links.smart}”\nPair ${links.first},${links.second}\n${invalid.join('\n')}\n<img src=x onerror="window.tcBad=1">\n`;
const report = { passed: false, checks: 0, restored: false, popups: 0 };
function check(value, label) { assert.ok(value, label); report.checks++; }
const environment = { ...process.env };
for (const key of ['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH']) delete environment[key];
function wp(code) {
  const result = execFileSync('docker', ['--host=unix:///var/run/docker.sock', 'compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', 'cli', 'wp', 'eval-file', '-'], { cwd: root, env: environment, input: `<?php\nif(home_url('/')!=='${base}/'){throw new RuntimeException('Disposable localhost WordPress required.');}\n${code}`, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  const line = result.split('\n').find(line => line.startsWith('TC_CONTENT_TEST:'));
  if (!line) throw new Error('No fixture result.');
  return JSON.parse(line.slice('TC_CONTENT_TEST:'.length));
}
const serialized = value => `json_decode(base64_decode('${Buffer.from(JSON.stringify(value)).toString('base64')}'),true)`;
(async () => {
  let fixture, browser, snapshot;
  try {
    snapshot = wp(`$options=array();foreach(array('timonconfigs_page_seo_queue','timonconfigs_page_seo_state','_tc_page_seo_lock') as $key){$options[$key]=array('exists'=>false!==get_option($key,false),'value'=>get_option($key,false));}echo 'TC_CONTENT_TEST:'.wp_json_encode($options);`);
    fixture = wp(`if(!class_exists('TC_Config_Content')){throw new RuntimeException('Content module must be initialized.');}
$id=0;try{
$id=wp_insert_post(array('post_type'=>'tc_config','post_status'=>'publish','post_title'=>'LOCAL CONTENT HTTP ${token}','post_content'=>'Public test description.','meta_input'=>array('tc_release_status'=>'available','tc_tier'=>'free','_tc_content_fixture'=>'${token}')),true);
if(is_wp_error($id)){throw new RuntimeException('Fixture creation failed.');}
$result=TC_Config_Content::save_fields($id,array('tc_guide_content'=>base64_decode('${Buffer.from(guideText).toString('base64')}'),'tc_guide_visibility'=>'public','tc_values_content'=>base64_decode('${Buffer.from(valueText).toString('base64')}'),'tc_values_visibility'=>'monthly'));
if(is_wp_error($result)){throw new RuntimeException('Fixture content was rejected.');}
echo 'TC_CONTENT_TEST:'.wp_json_encode(array('id'=>$id,'url'=>get_permalink($id),'guide'=>get_post_meta($id,'_tc_guide_content',true)));
}catch(Throwable $error){if(is_int($id)&&$id>0&&get_post_meta($id,'_tc_content_fixture',true)==='${token}'){wp_delete_post($id,true);}throw $error;}`);
    browser = await chromium.launch({ executablePath: '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.route(url => ['discord.com', 'discord.gg', 'discordapp.com', 'canary.discord.com', 'ptb.discord.com'].includes(url.hostname), route => route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>LOCAL QA Discord destination</title><p>LOCAL QA intercepted destination.</p>' }));
    const page = await context.newPage();
    await page.addInitScript(() => { window.tcCopied = null; Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async value => { window.tcCopied = value; } } }); });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    let response = await page.goto(fixture.url, { waitUntil: 'networkidle' });
    check(response.status() === 200, 'Actual config page responds.');
    check(!(await page.content()).includes(`PROTECTED-${token}`), 'Protected values absent from initial HTML.');
    const guide = page.locator(`[data-tc-content="guide"][data-config-id="${fixture.id}"]`).first();
    const values = page.locator(`[data-tc-content="values"][data-config-id="${fixture.id}"]`).first();
    check(await guide.count() === 1 && await values.count() === 1, 'Only existing content controls render.');
    const dialog = page.locator('#tc-config-content-dialog');
    const body = dialog.locator('.tc-content-body');
    async function verifyLinks(kind, width) {
      const anchors = await body.locator('a').evaluateAll(nodes => nodes.map(node => ({ href: node.href, text: node.textContent, target: node.target, rel: node.rel })));
      const expected = ['message', 'channel', 'invite', 'query', 'smart', 'first', 'second'].map(key => links[key]).concat(`https://${links.short}`);
      if (kind === 'guide') expected.push(links.legacy, links.canary, links.ptb, links.code);
      for (const href of expected) {
        const matching = anchors.filter(item => item.href === href);
        check(matching.length === 1, `${kind} links the exact Discord destination ${href} at ${width}.`);
        check(matching[0].target === '_blank' && /\bnoopener\b/.test(matching[0].rel) && /\bnoreferrer\b/.test(matching[0].rel), `${kind} generated links open separately with safe rel at ${width}.`);
      }
      check(anchors.find(item => item.href === `https://${links.short}`)?.text === links.short, `Scheme-less invite label remains unchanged at ${width}.`);
      check(!anchors.some(item => invalid.includes(item.href)), `${kind} keeps unrelated, lookalike and credential URLs plain at ${width}.`);
      check(!anchors.some(item => /[.)]$/.test(item.href)), `${kind} leaves sentence punctuation outside Discord destinations at ${width}.`);
      check(await body.locator('a a').count() === 0, `${kind} never nests links at ${width}.`);
      if (kind === 'guide') {
        check(anchors.filter(item => item.href === links.existing).length === 1 && await body.getByRole('link', { name: 'Existing Discord guide link', exact: true }).count() === 1, `Authored Guide anchor is retained exactly once at ${width}.`);
        check(await body.locator('code').textContent() === links.code, `Guide code URL text remains unchanged at ${width}.`);
      }
      const before = await body.textContent();
      const popupPromise = page.waitForEvent('popup');
      await body.locator('a').filter({ hasText: links.invite }).click();
      const popup = await popupPromise;
      await popup.waitForLoadState('domcontentloaded');
      check(popup.url() === links.invite && (await popup.title()) === 'LOCAL QA Discord destination', `${kind} opens the actual Discord destination intercepted locally at ${width}.`);
      check(await popup.evaluate(() => window.opener === null), `Discord popup has no window.opener at ${width}.`);
      await popup.close(); report.popups++;
      check(await dialog.evaluate(el => el.open) && await body.textContent() === before, `${kind} dialog remains open with unchanged content after clicking at ${width}.`);
      check(page.url() === fixture.url, `${kind} leaves the release page in place at ${width}.`);
    }
    await guide.click(); await page.waitForFunction(() => document.querySelector('.tc-content-body')?.textContent.includes('First step')).catch(async error => { console.error(JSON.stringify({ stage: 'guide-load', status: await dialog.locator('.tc-content-status').innerText(), open: await dialog.evaluate(el => el.open), errors })); throw error; });
    check(await dialog.evaluate(el => el.open), 'Guide opens a native modal.');
    check(await body.locator('ol li').count() === 2 && await body.locator('table').count() === 1, 'Lists and tables retain structure.');
    check((await body.locator('pre').innerText()).includes('one\ntwo'), 'Code retains line breaks.');
    await verifyLinks('guide', 1440);
    await dialog.locator('.tc-content-close').focus(); await page.keyboard.press('Shift+Tab');
    check(await dialog.locator('footer a').last().evaluate(el => el === document.activeElement), 'Shift-tab wraps focus inside dialog.');
    await page.keyboard.press('Tab');
    check(await dialog.locator('.tc-content-close').evaluate(el => el === document.activeElement), 'Tab wraps to close control.');
    await dialog.locator('.tc-content-copy').click();
    check((await page.evaluate(() => window.tcCopied)).includes('First step'), 'Guide copy uses loaded text.');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('#tc-config-content-dialog').open && document.querySelector('.tc-content-body').textContent === '');
    check(!await dialog.evaluate(el => el.open) && await guide.evaluate(el => el === document.activeElement), 'Escape closes and restores opener focus.');
    check((await body.innerText()) === '', 'Closing removes fetched content from DOM.');
    await page.setViewportSize({ width: 390, height: 900 });
    await guide.click(); await page.waitForFunction(() => document.querySelector('.tc-content-body')?.textContent.includes('First step'));
    await verifyLinks('guide', 390);
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('#tc-config-content-dialog').open && document.querySelector('.tc-content-body').textContent === '');
    await values.click(); await page.waitForFunction(() => document.querySelector('.tc-content-status')?.textContent.includes('Sign in'));
    check(!(await page.content()).includes(`PROTECTED-${token}`) && await body.locator('a').count() === 0, 'Denied values and their link markup never enter DOM.');
    check(await dialog.locator('.tc-content-copy').isDisabled(), 'Denied content cannot be copied.');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('#tc-config-content-dialog').open && document.querySelector('.tc-content-body').textContent === '');
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
    wp(`$id=${fixture.id};if(get_post_meta($id,'_tc_content_fixture',true)!=='${token}'){throw new RuntimeException('Fixture ownership mismatch.');}update_post_meta($id,'_tc_values_visibility','public');echo 'TC_CONTENT_TEST:'.wp_json_encode(array('ok'=>true));`);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await values.click(); await page.waitForFunction(secret => document.querySelector('.tc-content-body')?.textContent.includes(secret), `PROTECTED-${token}`);
      check(await body.locator('code').count() === 1 && !(await body.locator('code').innerHTML()).includes('<10>'), `Values are escaped at ${width}.`);
      check(await body.locator('img').count() === 0 && await page.evaluate(() => window.tcBad === undefined), `Values HTML stays literal and inert at ${width}.`);
      check(await body.locator('code').textContent() === valueText, `Values display preserves every text character at ${width}.`);
      await verifyLinks('values', width);
      await dialog.locator('.tc-content-copy').click();
      check(await page.evaluate(() => window.tcCopied) === valueText, `Values copy exact text including links, whitespace, command syntax and literal HTML at ${width}.`);
      check(await dialog.evaluate(el => el.getBoundingClientRect().width <= innerWidth && el.getBoundingClientRect().height <= innerHeight), `Modal fits ${width}.`);
      await mkdir(path.join(root, 'artifacts'), { recursive: true });
      await page.screenshot({ path: path.join(root, 'artifacts', `config-values-${width}.png`) });
      await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('#tc-config-content-dialog').open && document.querySelector('.tc-content-body').textContent === '');
    }
    const stored = wp(`$id=${fixture.id};if(get_post_meta($id,'_tc_content_fixture',true)!=='${token}'){throw new RuntimeException('Fixture ownership mismatch.');}echo 'TC_CONTENT_TEST:'.wp_json_encode(array('guide'=>get_post_meta($id,'_tc_guide_content',true),'values'=>get_post_meta($id,'_tc_values_content',true)));`);
    check(stored.values === valueText && stored.guide === fixture.guide, 'Viewing and clicking links never rewrites saved Guide or Values metadata.');
    check(errors.length === 0, 'No browser application errors.');
    report.passed = true;
  } finally {
    if (browser) await browser.close();
    let removed = !fixture;
    if (fixture) {
      const restored = wp(`$id=${fixture.id};if(get_post_meta($id,'_tc_content_fixture',true)!=='${token}'){throw new RuntimeException('Fixture ownership mismatch.');}wp_delete_post($id,true);echo 'TC_CONTENT_TEST:'.wp_json_encode(array('ok'=>null===get_post($id)));`);
      removed = restored.ok;
    }
    if (snapshot) {
      const restored = wp(`$options=${serialized(snapshot)};foreach($options as $key=>$entry){if($entry['exists']){update_option($key,$entry['value']);}else{delete_option($key);}}foreach($options as $key=>$entry){if(($entry['exists']&&get_option($key)!==$entry['value'])||(!$entry['exists']&&false!==get_option($key,false))){throw new RuntimeException('SEO fixture option restoration failed.');}}echo 'TC_CONTENT_TEST:'.wp_json_encode(array('ok'=>true));`);
      report.restored = removed && restored.ok;
    }
    await mkdir(path.join(root, 'artifacts'), { recursive: true });
    await writeFile(path.join(root, 'artifacts', 'config-content-browser.json'), JSON.stringify(report, null, 2));
  }
  console.log(JSON.stringify(report));
})().catch(error => { console.error(error.message); process.exitCode = 1; });
