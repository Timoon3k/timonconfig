/** Layout checks of real PHP-rendered fixture states; not live provider verification. */
const assert = require('node:assert/strict');
const { readFile, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
(async () => {
  const data = JSON.parse((await readFile(path.join(root, 'artifacts/member-ui-render.json'), 'utf8')).trim());
  assert.equal(data.passed, true);
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const report = { passed: false, phpAssertions: data.assertions, checks: [], errors: [] };
  try {
    const styles = (await Promise.all(['main', 'premium', 'member'].map(name => readFile(path.join(root, `wp-content/themes/timonconfigs/assets/css/${name}.css`), 'utf8')))).join('\n');
    for (const width of [1440, 768, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      page.on('pageerror', error => report.errors.push(error.message));
      // Serve fonts only from the local WordPress; fixture links are not followed.
      await page.route('**/*', route => new URL(route.request().url()).origin === 'http://127.0.0.1:8080' ? route.continue() : route.abort());
      for (const [state, html] of Object.entries(data.fixtures)) {
        await page.setContent(`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><base href="http://127.0.0.1:8080/wp-content/themes/timonconfigs/assets/css/"><style>${styles}</style></head><body class="tc-site tc-member-page tc-member-page--owner"><main id="main"><div class="tc-shell tc-entry"><article><h1 class="screen-reader-text">Member Profile</h1><div class="tc-entry__content">${html}</div></article></div></main></body></html>`, { waitUntil: 'networkidle' });
        if (state === 'guest-ready') {
          const signIn = page.getByRole('link', { name: 'Continue with Discord', exact: true });
          assert.equal(await signIn.count(), 1);
          assert.equal(await signIn.evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)', 'Enabled sign-in has readable white text');
          assert.equal(await page.locator('.tc-member-identity').count(), 0);
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
          report.checks.push({ state, width });
          continue;
        }
        assert.equal(await page.locator('.tc-member-state').getAttribute('data-state'), state);
        assert.equal(await page.locator('.tc-member-quick__card').count(), 3);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `No overflow: ${state}/${width}`);
        const columns = await page.locator('.tc-member-quick__grid').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        assert.equal(columns, width <= 600 ? 1 : 2);
        await page.locator('.tc-member-quick__card').first().focus();
        assert.equal(await page.locator('.tc-member-quick__card').first().evaluate(el => el === document.activeElement), true);
        const focus = await page.locator('.tc-member-quick__card').first().evaluate(el => getComputedStyle(el).outlineStyle);
        assert.notEqual(focus, 'none', 'Keyboard focus remains visible');
        if (state === 'monthly') await page.screenshot({ path: path.join(root, `artifacts/member-dashboard-fixture-${width}.png`), fullPage: true });
        report.checks.push({ state, width, longName: true, missingAvatar: true });
      }
      await page.close();
    }
    assert.deepEqual(report.errors, []);
    report.passed = true;
    console.log(JSON.stringify({ passed: true, phpAssertions: data.assertions, layoutChecks: report.checks.length }));
  } finally {
    await browser.close();
    await writeFile(path.join(root, 'artifacts/member-ui-states-checks.json'), JSON.stringify(report, null, 2));
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
