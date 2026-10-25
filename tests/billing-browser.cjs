/** Real WordPress-rendered billing fixtures, mocked providers; never follows checkout links. */
const assert = require('node:assert/strict');
const { readFile, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
(async () => {
  const log = await readFile(path.join(root, 'artifacts/billing-render.log'), 'utf8');
  const line = log.split('\n').find(value => value.startsWith('TC_BILLING_FIXTURES:'));
  assert.ok(line, 'Run billing-integration.php with TC_BILLING_RENDER=1 first.');
  const fixtures = JSON.parse(line.slice('TC_BILLING_FIXTURES:'.length));
  assert.equal(Object.keys(fixtures).length, 3);
  const styles = (await Promise.all(['main', 'premium', 'member', 'billing'].map(name => readFile(path.join(root, `wp-content/themes/timonconfigs/assets/css/${name}.css`), 'utf8')))).join('\n');
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const report = { passed: false, views: [], errors: [] };
  try {
    for (const width of [1440, 768, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      await page.route('**/*', route => new URL(route.request().url()).origin === 'http://127.0.0.1:8080' ? route.continue() : route.abort());
      for (const [state, html] of Object.entries(fixtures)) {
        await page.setContent(`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><base href="http://127.0.0.1:8080/wp-content/themes/timonconfigs/assets/css/"><style>${styles}</style></head><body class="tc-site"><main id="main"><div class="tc-shell tc-entry"><div class="tc-entry__content">${html}</div></div></main></body></html>`, { waitUntil: 'networkidle' });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `No document overflow at ${state}/${width}`);
        assert.equal(await page.locator('.tc-billing').count(), 1);
        assert.match(await page.locator('.tc-billing-notice').first().innerText(), /Test/i);
        assert.doesNotMatch(await page.locator('body').innerText(), /Fatal error|Warning:|sk_test_|whsec_/);
        if (state.startsWith('checkout-')) {
          assert.equal(await page.locator('h1').count(), 1);
          assert.match(await page.locator('.tc-billing').innerText(), state.endsWith('monthly') ? /€29\.99/ : /€80/);
          const form = page.locator('form').first();
          assert.equal(await form.getAttribute('method'), 'post');
          assert.equal(await form.evaluate(el => el.checkValidity()), false, 'Consent starts unchecked and required');
          const checkbox = form.locator('input[name="consent"]');
          await checkbox.focus();
          assert.notEqual(await checkbox.evaluate(el => getComputedStyle(el).outlineStyle), 'none', 'Keyboard focus is visible');
          await checkbox.check();
          assert.equal(await form.evaluate(el => el.checkValidity()), true);
          assert.equal(await form.locator('input[name="_wpnonce"]').count(), 1);
        } else {
          assert.match(await page.locator('#tc-billing-heading').innerText(), /Payments/);
          assert.equal(await page.locator('dl > div').count(), 4);
          assert.match(await page.locator('.tc-billing-table').innerText(), /VIP Lifetime/);
          assert.equal(await page.locator('.tc-billing-table-wrap').evaluate(el => el.getBoundingClientRect().right <= innerWidth), true, 'Order history scroll stays within its container');
          assert.equal(await page.locator('input[name="user_id"]').count(), 0, 'Account forms do not accept a target user ID');
        }
        if (width === 1440 || width === 390) await page.screenshot({ path: path.join(root, `artifacts/billing-${state}-${width}.png`), fullPage: true });
        report.views.push({ state, width });
      }
      await page.close();
    }
    assert.deepEqual(report.errors, []);
    report.passed = true;
    console.log(`Billing browser: ${report.views.length} responsive views passed; no provider navigation or purchases.`);
  } finally {
    await browser.close();
    await writeFile(path.join(root, 'artifacts/billing-browser-report.json'), JSON.stringify(report, null, 2));
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
