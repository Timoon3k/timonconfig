/** Real localhost collection pages and search redirects; run with the reversible PHP fixture. */
const assert = require('node:assert/strict');
const path = require('node:path');
const { readFile, writeFile, mkdir } = require('node:fs/promises');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const report = { passed: false, assertions: 0, views: [], redirects: [], sitemaps: [], errors: [], blockedOutbound: 0 };
function check(condition, message) { report.assertions++; assert.ok(condition, message); }
function local(url) {
  const target = new URL(url);
  return target.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(target.hostname) && target.port === '8080' && !target.username && !target.password;
}
const decodeXml = value => value.replaceAll('&amp;', '&').replaceAll('&quot;', '"').replaceAll('&apos;', "'").replaceAll('&lt;', '<').replaceAll('&gt;', '>');
const pathOnly = value => new URL(value).pathname;

(async () => {
  await mkdir(path.join(root, 'artifacts'), { recursive: true });
  const fixturePath = process.env.TC_COLLECTION_FIXTURE_LOG || path.join(root, 'artifacts/collections-browser-fixture-20261030.log');
  const log = await readFile(fixturePath, 'utf8');
  const fixture = log.split('\n').filter(line => line.startsWith('{')).map(line => JSON.parse(line)).find(value => value.fixture_active);
  check(fixture && local(fixture.home), 'Set up the reversible localhost collection browser fixture first.');
  const home = new URL(fixture.home);
  const baseRoutes = ['/xim-matrix-apex-legends/', '/xim-matrix-call-of-duty/', '/cronus-zen-apex-legends/', '/cronus-zen-call-of-duty/', '/free-configs/'];
  check(baseRoutes.every(route => fixture.pages[route]?.indexable), 'All four real device/game intersections and Free have released fixture content.');
  check(!fixture.pages['/xim-matrix-rust/'].indexable && !fixture.pages['/xim-matrix-fortnite/'].indexable, 'Preview-only Rust and empty Fortnite stay outside the index.');
  const known = new Map(fixture.configurations.map(item => [item.title, item]));
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    for (const width of [1440, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      await page.route('**/*', route => {
        if (local(route.request().url())) return route.continue();
        report.blockedOutbound++;
        return route.abort();
      });
      async function common(route, expectedStatus = 200) {
        const response = await page.goto(new URL(route, home).href, { waitUntil: 'networkidle' });
        const text = await page.locator('body').innerText();
        check(response.status() === expectedStatus, `${route}/${width}: HTTP ${expectedStatus}`);
        check(!/Fatal error|There has been a critical error|Uncaught Error|Undefined constant|Warning:|Deprecated:/.test(text), `${route}/${width}: no PHP error output`);
        check(await page.locator('main h1').count() === 1, `${route}/${width}: one main H1`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route}/${width}: no horizontal overflow`);
        report.views.push({ route, width, status: response.status(), finalUrl: page.url() });
        return response;
      }
      async function metadata(route, canonical, noindex = false, pagination = 1) {
        const expected = fixture.pages[route];
        check(await page.locator('head meta[name="description"]').count() === 1, `${route}/${width}: one description`);
        check(await page.locator('head meta[name="description"]').getAttribute('content') === expected.description, `${route}/${width}: specific collection description`);
        check(await page.locator('head link[rel="canonical"]').count() === 1, `${route}/${width}: one canonical`);
        check(await page.locator('head link[rel="canonical"]').getAttribute('href') === canonical, `${route}/${width}: correct self-canonical`);
        check(await page.locator('head meta[name="robots"]').count() === 1, `${route}/${width}: one robots directive`);
        const robots = await page.locator('head meta[name="robots"]').getAttribute('content');
        check(/\bnoindex\b/.test(robots) === noindex, `${route}/${width}: correct index eligibility`);
        const title = await page.title();
        check(pagination > 1 ? title.includes(expected.title) && title.includes(`Page ${pagination}`) : title === expected.title, `${route}/${width}: specific title and page counter`);
        check((await page.locator('main h1').innerText()).trim() === expected.h1, `${route}/${width}: collection H1`);
      }
      async function cardData() {
        return page.locator('.tc-config-grid .tc-config-card').evaluateAll(cards => cards.map(card => ({ title: card.querySelector('h2')?.textContent?.trim(), meta: card.querySelector('.tc-config-card__meta')?.textContent?.trim(), free: card.classList.contains('tc-config-card--free') })));
      }
      await common('/');
      for (const route of baseRoutes) {
        const links = await page.locator('.tc-popular-searches a[href]').evaluateAll((nodes, wanted) => nodes.filter(node => {
          const url = new URL(node.href); return url.pathname === wanted && !url.hash && !url.search;
        }).length, route);
        check(links === 1, `Home/${width}: direct popular-search link ${route}`);
      }
      check(pathOnly(await page.locator('.tc-hero .tc-actions a').first().getAttribute('href')) === '/xim-matrix-apex-legends/', `Home/${width}: hero reaches the dedicated XIM/Apex collection`);
      await page.locator('.tc-search-section form input[name="config_search"]').fill('XIM Matrix Apex Legends');
      await Promise.all([page.waitForURL(url => url.pathname === '/xim-matrix-apex-legends/' && !url.search), page.locator('.tc-search-section form input[name="config_search"]').press('Enter')]);
      await page.waitForLoadState('networkidle');
      check(pathOnly(page.url()) === '/xim-matrix-apex-legends/', `Home/${width}: submitting a device/game phrase reaches its real page`);
      for (const route of baseRoutes) {
        await common(route); await metadata(route, new URL(route, home).href);
        const expected = fixture.pages[route];
        for (const kind of ['game', 'device', 'tier']) {
          if (expected[kind]) check(await page.locator(`form.tc-catalog-filter [name="${kind}"]`).inputValue() === expected[kind], `${route}/${width}: selected ${kind} matches the collection`);
        }
        const cards = await cardData();
        check(cards.length > 0 && cards.length <= 12, `${route}/${width}: real matching cards are rendered with bounded pagination`);
        if (route === '/free-configs/') {
          check(cards.every(card => card.free), `${route}/${width}: only Free cards are shown`);
        } else {
          const deviceName = expected.device === 'xim-matrix' ? 'XIM Matrix' : 'Cronus Zen';
          const gameName = expected.game === 'apex-legends' ? 'Apex Legends' : 'Call of Duty';
          check(cards.every(card => card.meta === `${gameName} · ${deviceName}`), `${route}/${width}: every card matches both game and device`);
        }
        const own = cards.filter(card => known.has(card.title));
        check(own.length > 0, `${route}/${width}: actual fixture releases are visible`);
        check(own.every(card => {
          const item = known.get(card.title); return (!expected.game || item.game === expected.game) && (!expected.device || item.device === expected.device) && (!expected.tier || item.tier === expected.tier);
        }), `${route}/${width}: fixture identity agrees with the configured collection`);
        if (route === '/xim-matrix-apex-legends/' && width !== 320) await page.screenshot({ path: path.join(root, `artifacts/collections-apex-${width}-20261030.png`), fullPage: true });
      }
      await common('/xim-matrix-apex-legends/');
      const firstTitles = (await cardData()).map(card => card.title);
      check(await page.locator('.tc-pagination a[href*="collection_page=2"]').count() > 0, `Apex/${width}: a second result page is linked`);
      await common('/xim-matrix-apex-legends/?collection_page=2');
      await metadata('/xim-matrix-apex-legends/', new URL('/xim-matrix-apex-legends/?collection_page=2', home).href, false, 2);
      const secondCards = await cardData();
      check(secondCards.length > 0 && secondCards.every(card => !firstTitles.includes(card.title)), `Apex/${width}: page two contains different matching cards`);
      await common('/xim-matrix-apex-legends/');
      await page.locator('form.tc-catalog-filter select[name="input"]').selectOption('controller');
      await Promise.all([page.waitForURL(url => url.pathname === '/xim-matrix-apex-legends/' && url.searchParams.get('input') === 'controller'), page.locator('form.tc-catalog-filter button[type="submit"]').click()]);
      await page.waitForLoadState('networkidle');
      await metadata('/xim-matrix-apex-legends/', new URL('/xim-matrix-apex-legends/', home).href, true);
      check(await page.locator('form.tc-catalog-filter select[name="input"]').inputValue() === 'controller', `Apex/${width}: the submitted input selection survives routing`);
      const filtered = (await cardData()).filter(card => known.has(card.title));
      check(filtered.length > 0 && filtered.every(card => known.get(card.title).input === 'controller'), `Apex/${width}: submitted input filter narrows the actual cards`);
      await common('/xim-matrix-apex-legends/?collection_page=999', 404);
      if (width === 1440) {
        for (const route of ['/xim-matrix-rust/', '/xim-matrix-fortnite/']) {
          await common(route); await metadata(route, new URL(route, home).href, true);
          check(/Coming soon/.test(await page.locator('.tc-collection-status').innerText()), `${route}: availability does not claim a released fixture`);
        }
      }
      await page.close();
    }
    const context = await browser.newContext();
    async function getLocal(url, follow = true) {
      for (let count = 0; count < 5; count++) {
        check(local(url), 'HTTP QA stays on the localhost preview.');
        const response = await context.request.get(url, { maxRedirects: 0 });
        if (!follow || ![301, 302, 303, 307, 308].includes(response.status())) return response;
        url = new URL(response.headers().location, url).href;
      }
      throw new Error('Unexpected local redirect loop.');
    }
    for (const [source, wantedPath, wantedText] of [
      ['/configs/?game=apex-legends&device=xim-matrix', '/xim-matrix-apex-legends/'],
      ['/apex-legends-configs/?device=xim-matrix', '/xim-matrix-apex-legends/'],
      ['/xim-matrix-configs/?game=call-of-duty', '/xim-matrix-call-of-duty/'],
      ['/configs/?config_search=Cronus%20Zen%20COD', '/cronus-zen-call-of-duty/'],
      ['/configs/?config_search=free%20configs', '/free-configs/'],
      ['/configs/?config_search=XIM%20Matrix%20Apex%20Legends%20Predator%20S30', '/xim-matrix-apex-legends/', 'predator s30'],
    ]) {
      const response = await getLocal(new URL(source, home).href, false);
      const target = new URL(response.headers().location || '', home);
      check([301, 302, 303, 307, 308].includes(response.status()) && local(target.href), `${source}: safe collection redirect`);
      check(target.pathname === wantedPath, `${source}: correct new phrase URL`);
      check((target.searchParams.get('config_search') || '') === (wantedText || ''), `${source}: only residual release terms remain in search`);
      report.redirects.push({ source, status: response.status(), target: target.href });
      if (wantedText) {
        const response = await getLocal(target.href);
        const html = await response.text();
        check(response.status() === 200 && html.includes('Collection Browser Fixture 01 Predator S30') && !html.includes('<h2>Collection Browser Fixture 02</h2>'), 'Residual release phrase selects its actual matching card.');
      }
    }
    let indexResponse = await getLocal(new URL('/sitemap_index.xml', home).href);
    let xml = await indexResponse.text();
    let indexPath = '/sitemap_index.xml';
    if (indexResponse.status() !== 200 || !/<sitemapindex\b/.test(xml)) {
      indexPath = '/wp-sitemap.xml'; indexResponse = await getLocal(new URL(indexPath, home).href); xml = await indexResponse.text();
    }
    check(indexResponse.status() === 200 && /<sitemapindex\b/.test(xml), 'The active metadata provider serves an XML sitemap index.');
    const children = Array.from(xml.matchAll(/<loc>(.*?)<\/loc>/g), match => decodeXml(match[1]));
    check(children.length > 0 && children.length <= 20, 'The local sitemap index contains a bounded list of child maps.');
    const indexedPaths = new Set();
    report.sitemaps.push({ path: indexPath, status: indexResponse.status(), children: children.length });
    for (const child of children) {
      const response = await getLocal(child); const body = await response.text();
      check(response.status() === 200 && /<urlset\b/.test(body), 'Each listed local child sitemap returns an XML URL set.');
      const values = Array.from(body.matchAll(/<loc>(.*?)<\/loc>/g), match => decodeXml(match[1]));
      for (const value of values) { check(local(value), 'Sitemap entries use the actual local origin.'); indexedPaths.add(pathOnly(value)); }
      report.sitemaps.push({ path: pathOnly(child), status: response.status(), urls: values.length });
    }
    for (const route of baseRoutes) check(indexedPaths.has(route), `The XML sitemap contains the eligible page ${route}`);
    for (const route of ['/xim-matrix-rust/', '/xim-matrix-fortnite/', '/account/', '/my-downloads/', '/checkout/', '/cart/']) check(!indexedPaths.has(route), `The XML sitemap excludes noindex or private URL ${route}`);
    await context.close();
    check(report.errors.length === 0, 'No JavaScript runtime errors.');
    report.passed = true;
  } catch (error) {
    report.failure = error.message; process.exitCode = 1;
  } finally {
    await browser.close();
    await writeFile(path.join(root, 'artifacts/collections-browser-20261030.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, assertions: report.assertions, views: report.views.length, redirects: report.redirects.length, sitemapDocuments: report.sitemaps.length, javascriptErrors: report.errors.length, failure: report.failure }));
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
