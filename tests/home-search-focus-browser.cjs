/** Local rendered Home/collection visual checks; no remote mutations. */
const assert = require('node:assert/strict');
const path = require('node:path');
const { mkdir, writeFile } = require('node:fs/promises');
const { chromium } = require('playwright');
(async () => {
  const output = path.resolve(__dirname, '../artifacts'); await mkdir(output, { recursive: true });
  const browser = await chromium.launch({ executablePath: '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const checks = [];
  try {
    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      for (const route of ['/', '/apex-legends-configs/', '/call-of-duty-configs/']) {
        const response = await page.goto(`http://127.0.0.1:8080${route}`, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200);
        assert.equal(await page.locator('h1').count(), 1);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'No horizontal overflow');
        if (route === '/') {
          const cta = page.locator('.tc-home-final');
          assert.equal(await cta.locator('img').count(), 0);
          assert.equal(await cta.evaluate(el => getComputedStyle(el).backgroundImage), 'none');
          for (const pseudo of ['::before', '::after']) assert.equal(await cta.evaluate((el, selector) => getComputedStyle(el, selector).content, pseudo), 'none');
          assert.match(await page.locator('.tc-home-faq').evaluate(el => getComputedStyle(el).backgroundImage), /cta-landscape/);
          await cta.screenshot({ path: path.join(output, `home-solid-cta-${width}.png`) });
        } else {
          assert.equal(await page.locator('#xim-matrix').count(), 1);
          assert.equal(await page.locator('#cronus-zen').count(), 1);
        }
        checks.push({ width, route, passed: true });
      }
      await page.close();
    }
    console.log(JSON.stringify({ passed: true, cases: checks.length }));
  } finally { await browser.close(); await writeFile(path.join(output, 'home-search-focus-browser.json'), JSON.stringify(checks, null, 2)); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
