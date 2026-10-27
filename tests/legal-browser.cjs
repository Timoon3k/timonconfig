/** Read-only browser checks against the actual local WordPress legal pages. */
const assert = require('node:assert/strict');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const origin = 'http://127.0.0.1:8080';
const policies = ['terms-of-service', 'privacy-policy', 'refund-policy'];
const reportPrefix = process.env.TC_LEGAL_REPORT_PREFIX || 'legal-096';
assert.match(reportPrefix, /^[a-z0-9-]+$/);

(async () => {
  const report = { passed: false, views: [], redirects: [], errors: [], assertions: 0 };
  const check = (condition, message) => { assert.ok(condition, message); report.assertions += 1; };
  await mkdir(path.join(root, 'artifacts'), { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    for (const width of [1440, 768, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      // External social/video providers are not part of this local rendering test.
      await page.route('**/*', route => ['localhost', '127.0.0.1'].includes(new URL(route.request().url()).hostname) ? route.continue() : route.abort());
      for (const slug of policies) {
        const response = await page.goto(`${origin}/${slug}/`, { waitUntil: 'networkidle' });
        check(response.status() === 200, `${slug}/${width}: HTTP 200`);
        check(await page.locator('main h1').count() === 1, `${slug}/${width}: one H1`);
        const copy = await page.locator('.tc-legal-content').innerText();
        check(copy.includes('Tomasz Majewski'), `${slug}/${width}: real seller identity`);
        check(copy.includes('TimonConfigs, ul. Dąbrowica 21, 05-326 Poświetne, Poland'), `${slug}/${width}: owner-supplied postal address`);
        check(await page.locator('.tc-legal-content a[href="mailto:contact@timonconfigs.com"]').count() > 0, `${slug}/${width}: direct seller email`);
        check(!/\bDraft\b|\{\{|\}\}|Fatal error|Warning:|being prepared for owner review/i.test(copy), `${slug}/${width}: published copy without template markers`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${slug}/${width}: no document overflow`);
        check(await page.locator('.tc-breadcrumbs').count() === 1, `${slug}/${width}: breadcrumbs`);
        for (const policy of policies) {
          check(await page.locator(`.tc-footer__policies a[href$="/${policy}/"]`).count() === 1, `${slug}/${width}: canonical ${policy} footer link`);
        }
        check(await page.locator('.tc-footer__policies a[href*="cookie-policy"], .tc-footer__policies a[href*="lifetime-license-terms"]').count() === 0, `${slug}/${width}: no retired footer links`);
        if (slug === 'terms-of-service') {
          check(copy.includes('€29.99') && copy.includes('€80') && /final customer totals/i.test(copy), `${slug}/${width}: final EUR pricing`);
          check(await page.locator('#lifetime-license').count() === 1, `${slug}/${width}: lifetime license section`);
        }
        if (slug === 'privacy-policy') {
          check(await page.locator('#cookies').count() === 1, `${slug}/${width}: cookie section`);
          if (width === 390) await page.screenshot({ path: path.join(root, `artifacts/${reportPrefix}-privacy-390.png`), fullPage: true });
        }
        report.views.push({ slug, width, status: response.status() });
      }
      await page.close();
    }
    const redirectContext = await browser.newContext();
    for (const [slug, destination] of Object.entries({ 'cookie-policy': '/privacy-policy/#cookies', 'lifetime-license-terms': '/terms-of-service/#lifetime-license' })) {
      const response = await redirectContext.request.get(`${origin}/${slug}/`, { maxRedirects: 0 });
      check(response.status() === 301, `${slug}: permanent redirect`);
      check(response.headers().location === `${origin}${destination}`, `${slug}: exact canonical section`);
      report.redirects.push({ slug, status: response.status(), destination });
    }
    await redirectContext.close();
    check(report.errors.length === 0, 'No browser JavaScript runtime errors');
    if (!process.env.TC_LEGAL_SKIP_THEME_SCREENSHOT) {
      const preview = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1 });
      await preview.route('**/*', route => ['localhost', '127.0.0.1'].includes(new URL(route.request().url()).hostname) ? route.continue() : route.abort());
      const home = await preview.goto(`${origin}/`, { waitUntil: 'networkidle' });
      check(home.status() === 200, 'Theme screenshot: real local Home route');
      check(await preview.locator('main h1').count() === 1, 'Theme screenshot: rendered Home content');
      await preview.screenshot({ path: path.join(root, 'wp-content/themes/timonconfigs/screenshot.png'), fullPage: false });
      await preview.close();
    }
    report.passed = true;
  } finally {
    await writeFile(path.join(root, `artifacts/${reportPrefix}-browser.json`), `${JSON.stringify(report, null, 2)}\n`);
    await browser.close();
  }
  console.log(`Legal browser: ${report.assertions} assertions, ${report.views.length} responsive page views, ${report.redirects.length} permanent redirects; theme screenshot ${process.env.TC_LEGAL_SKIP_THEME_SCREENSHOT ? 'preserved' : '1200×900'}.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
