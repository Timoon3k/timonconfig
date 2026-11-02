/** Isolated browser transport regression; no WordPress writes, Discord calls or real uploads. */
const assert = require('node:assert/strict');
const { readFile, writeFile, mkdir } = require('node:fs/promises');
const { chromium } = require('playwright');
const http = require('node:http');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const report = { passed: false, assertions: 0, cases: [], errors: [] };
const check = (condition, message) => { report.assertions++; assert.ok(condition, message); };
const html = `<!doctype html><html lang="en"><meta charset="utf-8"><title>Local Owner transport fixture</title><body>
<form class="tc-owner-form" data-wait-seconds="0" data-upload-max-bytes="20971520" data-post-max-bytes="67108864" data-upload-max-files="10" action="/?tc_owner_submit=1" method="post" enctype="multipart/form-data">
<input name="action" value="tc_owner_config" type="hidden"><input name="title" value="Owner fixture title"><textarea name="description">Owner fixture notes</textarea><input type="file" name="tc_private_files[]" multiple>
<button type="submit" name="status" value="publish">Publish config</button><button type="submit" name="status" value="draft">Save draft</button></form>
<form class="tc-owner-form" data-wait-seconds="0" action="/wp-admin/admin-post.php" method="post"><button type="submit" disabled>Previously disabled</button></form></body></html>`;
const json = (status, data, success = false) => ({ status, contentType: 'application/json', body: JSON.stringify({ success, data }) });

(async () => {
  const source = await readFile(path.join(root, 'wp-content/themes/timonconfigs/assets/js/owner-editor.js'), 'utf8');
  const server = http.createServer((request, response) => {
    response.setHeader('Content-Type', 'text/html; charset=utf-8');
    response.end(request.url.startsWith('/wp-login.php') ? '<!doctype html><title>Sign in</title><p>Local login fixture</p>' : html);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const origin = `http://127.0.0.1:${server.address().port}`;
  let browser;
  try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const cases = [
      { name: 'HTML request too large', response: { status: 413, contentType: 'text/html', body: '<h1>413</h1><script>window.secretWasRendered=true</script>' }, message: /request size limit \(HTTP 413\)/ },
      { name: 'HTML forbidden', response: { status: 403, contentType: 'text/html', body: '<h1>Security rule</h1>' }, message: /refused.*HTTP 403.*session may have expired/i },
      { name: 'HTML unauthenticated', response: { status: 401, contentType: 'text/html', body: '<h1>Sign in</h1>' }, message: /HTTP 401/ },
      { name: 'Login redirect', response: { status: 302, headers: { location: `${origin}/wp-login.php?reauth=1` } }, message: /redirected instead of confirmed \(HTTP 200\)/ },
      { name: 'Gateway failure is uncertain', response: { status: 502, contentType: 'text/html', body: '<h1>Bad gateway</h1>' }, message: /could not confirm.*HTTP 502.*Check Existing configurations/ },
      { name: 'Malformed JSON error', response: { status: 500, contentType: 'application/json', body: '{incomplete' }, message: /could not confirm.*HTTP 500/ },
      { name: 'Unexpected success HTML', response: { status: 200, contentType: 'text/html', body: '<h1>Unexpected HTML</h1>' }, message: /unexpected save response \(HTTP 200\)/ },
      { name: 'WordPress missing action response', response: { status: 400, contentType: 'text/html', body: '0' }, message: /incomplete or invalid.*HTTP 400/ },
      { name: 'Missing endpoint', response: { status: 404, contentType: 'text/html', body: 'Not found' }, message: /endpoint could not be found \(HTTP 404\)/ },
      { name: 'Plain text JSON remains supported', response: { ...json(403, { message: 'Approved Owner account access is required.', code: 'tc_owner_forbidden' }), contentType: 'text/plain' }, message: /^Approved Owner account access is required\.$/ },
      { name: 'Server outage message and cooldown', response: json(503, { message: 'Discord verification is temporarily unavailable.', code: 'tc_discord_unavailable', retry_after: 1 }), message: /Discord verification.*Wait 1 second, then try again/ },
      { name: 'JSON server message cannot inject HTML', response: json(403, { message: '<img src=x onerror="window.secretWasRendered=true">', code: 'tc_owner_forbidden' }), message: /^<img src=x/ },
      { name: 'HTML rate limit Retry-After', response: { status: 429, headers: { 'retry-after': '2' }, contentType: 'text/html', body: 'Rate limited' }, message: /HTTP 429.*Wait 2 seconds before trying again/ },
      { name: 'JSON rate limit Retry-After', response: { ...json(429, { message: 'Please wait before saving again.', retry_after: 1 }), headers: { 'retry-after': '2' } }, message: /Please wait.*Wait 2 seconds, then try again/ },
      { name: 'External JSON destination rejected', response: json(200, { redirect: 'https://example.invalid/steal' }, true), message: /unexpected save response \(HTTP 200\)/ },
      { name: 'Malformed JSON destination rejected', response: json(200, { redirect: 'http://[' }, true), message: /unexpected save response \(HTTP 200\)/ },
      { name: 'True network failure remains uncertain', abort: true, message: /connection was interrupted.*Check Existing configurations/ },
      { name: 'Publish confirmed', response: json(200, { redirect: '/account/?config_id=42' }, true), destination: '/account/?config_id=42' },
      { name: 'Recoverable draft confirmed', response: json(422, { code: 'tc_owner_draft_saved', redirect: '/account/?config_id=43' }), destination: '/account/?config_id=43' },
      { name: 'Legacy dispatch and draft submitter retained', response: json(200, { redirect: '/account/?config_id=44' }, true), destination: '/account/?config_id=44', draft: true, legacy: true },
      { name: 'Per-file limit rejects before network', response: json(200, {}, true), dataset: { uploadMaxBytes: '32' }, local: true, message: /per-file upload limit/ },
      { name: 'Aggregate request includes upload overhead', response: json(200, {}, true), dataset: { postMaxBytes: '16384' }, local: true, message: /estimated.*request limit, including upload overhead/ },
      { name: 'File count limit rejects before network', response: json(200, {}, true), dataset: { uploadMaxFiles: '1' }, twoFiles: true, local: true, message: /at most 1 per save/ },
      { name: 'Large text counts toward request limit', response: json(200, {}, true), dataset: { postMaxBytes: '40000' }, largeText: true, local: true, message: /estimated.*request limit/ },
    ];
    for (const width of [390, 1440]) {
      for (const test of cases) {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        page.on('pageerror', error => report.errors.push(error.message));
        let calls = 0;
        let payload;
        await page.route('**/*', async route => {
          const request = route.request();
          const url = new URL(request.url());
          if (url.origin !== origin) return route.abort();
          if (request.method() === 'POST' && (url.pathname === '/wp-admin/admin-post.php' || (url.pathname === '/' && url.searchParams.get('tc_owner_submit') === '1'))) {
            calls++; payload = request.postDataBuffer();
            check(url.searchParams.get('tc_owner_json') === '1', `${test.name}: JavaScript opts into JSON without requiring an Accept header`);
            check(test.legacy ? url.searchParams.get('action') === 'tc_owner_config' : url.searchParams.get('tc_owner_submit') === '1', `${test.name}: dispatch survives the hidden action input or an empty POST body`);
            if (test.abort) return route.abort('failed');
            return route.fulfill(test.response);
          }
          return route.continue();
        });
        await page.goto(origin, { waitUntil: 'domcontentloaded' });
        const form = page.locator('form.tc-owner-form').first();
        if (test.legacy) await form.evaluate(node => node.setAttribute('action', '/wp-admin/admin-post.php'));
        if (test.dataset) await form.evaluate((node, values) => Object.assign(node.dataset, values), test.dataset);
        const file = { name: 'owner-transport.znbk', mimeType: 'application/octet-stream', buffer: Buffer.from([0, 255, ...Array.from({ length: 100 }, (_, i) => i)]) };
        await form.locator('[type=file]').setInputFiles(test.twoFiles ? [file, { ...file, name: 'second-script.gpc' }] : file);
        const notes = test.largeText ? '🔥'.repeat(10000) : 'Owner fixture notes';
        await form.locator('textarea').fill(notes);
        await page.addScriptTag({ content: source });
        await form.getByRole('button', { name: test.draft ? 'Save draft' : 'Publish config', exact: true }).click();
        if (test.destination) {
          await page.waitForURL(`${origin}${test.destination}`);
          check(calls === 1, `${test.name}/${width}: exactly one save request`);
          check(payload.includes(Buffer.from(`name="status"\r\n\r\n${test.draft ? 'draft' : 'publish'}`)), `${test.name}/${width}: the deliberate submit action is sent`);
        } else {
          const feedback = form.locator('[data-owner-result][role=alert]');
          await feedback.waitFor();
          const message = await feedback.innerText();
          check(test.message.test(message), `${test.name}/${width}: useful classified response (${message})`);
          check(await form.locator('[name=title]').inputValue() === 'Owner fixture title', `${test.name}/${width}: title remains`);
          check(await form.locator('textarea').inputValue() === notes, `${test.name}/${width}: typed notes remain`);
          check(await form.locator('[type=file]').evaluate(input => input.files.length) === (test.twoFiles ? 2 : 1), `${test.name}/${width}: selected files remain`);
          check(await form.getByRole('button', { name: 'Publish config', exact: true }).isEnabled(), `${test.name}/${width}: publish is reenabled`);
          check(await form.getAttribute('aria-busy') === null, `${test.name}/${width}: busy state is cleared`);
          check(await page.getByRole('button', { name: 'Previously disabled', exact: true }).isDisabled(), `${test.name}/${width}: existing disabled state is preserved`);
          check(await feedback.locator('img, script').count() === 0 && !await page.evaluate(() => window.secretWasRendered), `${test.name}/${width}: untrusted HTML is never rendered`);
          check(calls === (test.local ? 0 : 1), `${test.name}/${width}: no silent retry or rejected preflight request`);
          check(page.url() === `${origin}/`, `${test.name}/${width}: failure does not navigate away`);
        }
        report.cases.push({ name: test.name, width, requests: calls });
        await page.close();
      }
    }
    // A second submission while the first transport is unresolved must not duplicate a write.
    const page = await browser.newPage();
    let finish;
    let calls = 0;
    await page.route('**/*', async route => {
      if (route.request().method() !== 'POST') return route.continue();
      calls++;
      await new Promise(resolve => { finish = resolve; });
      return route.fulfill(json(403, { message: 'Fixture complete.' }));
    });
    await page.goto(origin);
    await page.addScriptTag({ content: source });
    const form = page.locator('form.tc-owner-form').first();
    await form.getByRole('button', { name: 'Publish config', exact: true }).click();
    await page.waitForFunction(() => document.querySelector('form').getAttribute('aria-busy') === 'true');
    await page.waitForTimeout(50);
    await form.evaluate(node => node.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true })));
    check(calls === 1, 'Pending submit blocks a second request.');
    check(await form.getByRole('button', { name: 'Publish config', exact: true }).isDisabled(), 'Pending transport disables submit controls.');
    finish();
    await form.locator('[data-owner-result][role=alert]').waitFor();
    check(await form.getByRole('button', { name: 'Publish config', exact: true }).isEnabled(), 'Resolved transport restores controls.');
    await page.close();
    check(report.errors.length === 0, 'No JavaScript runtime errors.');
    report.passed = true;
  } catch (error) {
    report.failure = error.message; process.exitCode = 1;
  } finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
    await mkdir(path.join(root, 'artifacts'), { recursive: true });
    await writeFile(path.join(root, 'artifacts/owner-upload-transport-browser-20261102.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, cases: report.cases.length, assertions: report.assertions, errors: report.errors, failure: report.failure }));
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
