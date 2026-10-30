/** Local rendered Home/collection visual checks; no remote mutations. */
const assert = require('node:assert/strict');
const path = require('node:path');
const { mkdir, writeFile } = require('node:fs/promises');
const { chromium } = require('playwright');
(async () => {
  const output = path.resolve(__dirname, '../artifacts'); await mkdir(output, { recursive: true });
  const browser = await chromium.launch({ executablePath: '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const checks = [];
  const errors = [];
  const collectionRoutes = {
    '/xim-matrix-apex-legends/': { game: 'apex-legends', device: 'xim-matrix' },
    '/xim-matrix-call-of-duty/': { game: 'call-of-duty', device: 'xim-matrix' },
    '/cronus-zen-apex-legends/': { game: 'apex-legends', device: 'cronus-zen' },
    '/cronus-zen-call-of-duty/': { game: 'call-of-duty', device: 'cronus-zen' },
    '/free-configs/': { tier: 'free' },
  };
  try {
    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', request => {
        const url = new URL(request.request().url());
        return url.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(url.hostname) && url.port === '8080' ? request.continue() : request.abort();
      });
      for (const route of ['/', '/apex-legends-configs/', '/call-of-duty-configs/', ...Object.keys(collectionRoutes)]) {
        const response = await page.goto(`http://127.0.0.1:8080${route}`, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200);
        assert.equal(await page.locator('main h1').count(), 1);
        assert.doesNotMatch(await page.locator('body').innerText(), /Fatal error|There has been a critical error|Uncaught Error|Warning:|Deprecated:/, 'No PHP error output');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'No horizontal overflow');
        if (route === '/') {
          for (const destination of Object.keys(collectionRoutes)) {
            const matches = await page.locator('.tc-popular-searches a[href]').evaluateAll((links, wanted) => links.filter(link => {
              const url = new URL(link.href);
              return url.pathname === wanted && !url.hash && !url.search;
            }).length, destination);
            assert.equal(matches, 1, `One direct popular-search destination: ${destination}`);
          }
          const heroLink = await page.locator('.tc-hero .tc-actions a').first().getAttribute('href');
          assert.equal(new URL(heroLink, 'http://127.0.0.1:8080').pathname, '/xim-matrix-apex-legends/');
          const cta = page.locator('.tc-home-final');
          assert.equal(await cta.locator('img').count(), 0);
          assert.equal(await cta.evaluate(el => getComputedStyle(el).backgroundImage), 'none');
          for (const pseudo of ['::before', '::after']) assert.equal(await cta.evaluate((el, selector) => getComputedStyle(el, selector).content, pseudo), 'none');
          assert.match(await page.locator('.tc-home-faq').evaluate(el => getComputedStyle(el).backgroundImage), /cta-landscape/);
          await cta.screenshot({ path: path.join(output, `home-solid-cta-${width}.png`) });
        } else if (collectionRoutes[route]) {
          const filters = page.locator('form.tc-catalog-filter');
          assert.equal(await filters.count(), 1, 'Dedicated collection exposes its filters');
          assert.equal(new URL(await filters.getAttribute('action'), 'http://127.0.0.1:8080').pathname, '/configs/', 'Changing a collection filter goes through the catalogue resolver');
          for (const [field, value] of Object.entries(collectionRoutes[route])) {
            assert.equal(await filters.locator(`[name="${field}"]`).first().inputValue(), value, 'Dedicated collection retains its game/device/access scope');
          }
          if (route === '/xim-matrix-apex-legends/' && width !== 768) await page.screenshot({ path: path.join(output, `device-game-collection-${width}.png`), fullPage: true });
        } else {
          assert.equal(await page.locator('#xim-matrix').count(), 1);
          assert.equal(await page.locator('#cronus-zen').count(), 1);
          const game = route === '/apex-legends-configs/' ? 'apex-legends' : 'call-of-duty';
          for (const device of ['xim-matrix', 'cronus-zen']) {
            const target = await page.locator(`#${device} .tc-actions a`).first().getAttribute('href');
            assert.equal(new URL(target, 'http://127.0.0.1:8080').pathname, `/${device}-${game}/`, 'Existing device anchor points onward to the dedicated collection');
          }
        }
        checks.push({ width, route, passed: true });
      }
      await page.close();
    }
    assert.deepEqual(errors, [], 'No JavaScript runtime errors');
    console.log(JSON.stringify({ passed: true, cases: checks.length, javascriptErrors: errors.length }));
  } finally { await browser.close(); await writeFile(path.join(output, 'home-search-focus-browser.json'), JSON.stringify(checks, null, 2)); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
