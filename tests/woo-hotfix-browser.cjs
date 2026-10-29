/** Read-only smoke test of actual localhost WordPress pages; no checkout submission. */
const assert = require('node:assert/strict');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const origin = 'http://127.0.0.1:8080';
const report = { passed: false, assertions: 0, views: [], errors: [], outboundBlocked: 0 };
function check(value, message) { report.assertions++; assert.ok(value, message); }

(async () => {
  await mkdir(path.join(root, 'artifacts'), { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    for (const width of [1440, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      await page.route('**/*', route => {
        if (new URL(route.request().url()).origin === origin) return route.continue();
        report.outboundBlocked++;
        return route.abort();
      });
      for (const route of ['/', '/pricing/', '/account/', '/configs/']) {
        const response = await page.goto(origin + route, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        const body = await page.locator('body').innerText();
        check(response.status() === 200, `${route}/${width}: HTTP 200`);
        check(!/Fatal error|There has been a critical error|Uncaught Error|Undefined constant|Warning:|Deprecated:/.test(body), `${route}/${width}: no PHP fatal or warning`);
        check(await page.locator('main').count() === 1, `${route}/${width}: theme main content renders`);
        check(await page.locator('.tc-header').count() === 1 && await page.locator('.tc-footer').count() === 1, `${route}/${width}: theme navigation/footer render`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route}/${width}: no horizontal document overflow`);
        if (route === '/account/') {
          check(await page.locator('.tc-member-login__card').count() === 1 && /Continue with Discord/.test(body), `${route}/${width}: guest member login renders`);
        }
        const name = route === '/' ? 'home' : route.split('/')[1];
        if (width === 390 || (width === 1440 && route === '/')) {
          await page.screenshot({ path: path.join(root, `artifacts/core118-${name}-${width}.png`), fullPage: true });
        }
        report.views.push({ route, width, http: response.status() });
      }
      await page.close();
    }
    check(report.errors.length === 0, 'No browser JavaScript runtime errors');
    report.passed = true;
  } catch (error) {
    report.failure = error.message;
    process.exitCode = 1;
  } finally {
    await browser.close();
    await writeFile(path.join(root, 'artifacts/core118-browser.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, assertions: report.assertions, views: report.views.length, errors: report.errors, failure: report.failure }));
  }
})();
