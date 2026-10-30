/** Actual local HTTP output with Rank Math active; no purchases or external requests. */
const assert = require('node:assert/strict');
const { readFile, writeFile, mkdir } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const report = { passed: false, assertions: 0, views: [], sitemap: [], errors: [], outboundBlocked: 0 };
function check(condition, message) { report.assertions++; assert.ok(condition, message); }
function local(url) {
  const parsed = new URL(url);
  return parsed.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(parsed.hostname) && parsed.port === '8080';
}

(async () => {
  await mkdir(path.join(root, 'artifacts'), { recursive: true });
  const importLog = await readFile(path.join(root, 'artifacts/seo-20261030-native-import.log'), 'utf8');
  const fixture = importLog.split('\n').filter(line => line.startsWith('{')).map(line => JSON.parse(line)).find(value => value.http_fixture_active);
  check(fixture && local(fixture.home), 'Run the native importer in localhost setup mode first.');
  const settings = JSON.parse(await readFile(path.join(root, 'docs/seo/timonconfigs-rank-math-seo-only-2026-10-30.json'), 'utf8'));
  const home = new URL(fixture.home);
  const expected = {
    '/': { title: settings.titles.homepage_title, description: settings.titles.homepage_description },
    '/contact/': {
      title: 'Cronus Zen Discord & XIM Matrix Support | TimonConfigs',
      description: 'Join the TimonConfigs Cronus Zen Discord community for XIM Matrix and Apex Legends setup help, release updates and Free or VIP membership support.',
    },
  };
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    for (const width of [1440, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      await page.route('**/*', route => {
        if (local(route.request().url())) return route.continue();
        report.outboundBlocked++;
        return route.abort();
      });
      for (const route of ['/', '/contact/', '/account/']) {
        const response = await page.goto(new URL(route, home).href, { waitUntil: 'networkidle' });
        const body = await page.locator('body').innerText();
        const label = `${route}/${width}`;
        check(response.status() === 200, `${label}: HTTP 200`);
        check(!/Fatal error|There has been a critical error|Uncaught Error|Undefined constant|Warning:|Deprecated:/.test(body), `${label}: no PHP error output`);
        check(await page.locator('main h1').count() === 1, `${label}: one main H1`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${label}: no horizontal overflow`);
        check(await page.locator('head meta[name="robots"]').count() === 1, `${label}: one robots tag`);
        const robots = await page.locator('head meta[name="robots"]').getAttribute('content');
        const canonicalCount = await page.locator('head link[rel="canonical"]').count();
        const schema = page.locator('script[type="application/ld+json"]');
        check(await schema.count() <= 1, `${label}: no duplicated metadata-provider JSON-LD scripts`);
        if (await schema.count()) {
          check((await schema.getAttribute('class') || '').includes('rank-math-schema'), `${label}: Rank Math owns the schema output`);
          const data = JSON.parse(await schema.textContent());
          check(data['@context'] === 'https://schema.org', `${label}: valid Schema.org JSON-LD`);
        }
        if (route === '/account/') {
          check(/\bnoindex\b/.test(robots), `${label}: member page stays noindex`);
          check(/\bnoindex\b/.test(response.headers()['x-robots-tag'] || ''), `${label}: member response retains its noindex header`);
          check(canonicalCount <= 1, `${label}: no duplicated private-page canonical`);
        } else {
          check(!/\bnoindex\b/.test(robots), `${label}: public page is indexable`);
          check(!/\bnoindex\b/.test(response.headers()['x-robots-tag'] || ''), `${label}: no conflicting noindex response header`);
          check(canonicalCount === 1, `${label}: one canonical`);
          check(await page.locator('head link[rel="canonical"]').getAttribute('href') === new URL(route, home).href, `${label}: canonical points to the actual local URL`);
          const actualTitle = await page.title();
          report.observed = report.observed || [];
          report.observed.push({ route, width, title: actualTitle, expectedTitle: expected[route].title, description: await page.evaluate(() => document.querySelector('head meta[name=\"description\"]')?.getAttribute('content') ?? null) });
          check(actualTitle === expected[route].title, `${label}: page-specific title uses the requested search intent (actual: ${actualTitle})`);
          check(await page.locator('head meta[name="description"]').count() === 1, `${label}: one description`);
          check(await page.locator('head meta[name="description"]').getAttribute('content') === expected[route].description, `${label}: page-specific description`);
          check(await page.locator('head meta[property="og:title"]').count() === 1, `${label}: one Open Graph title`);
          check(await page.locator('head meta[property="og:title"]').getAttribute('content') === expected[route].title, `${label}: Open Graph title stays consistent`);
          if (route === '/') {
            check(/XIM Matrix Apex Legends/.test(body), `${label}: visible Home copy supports the target search intent`);
            const communityLinks = await page.locator('main a[href]').evaluateAll(links => links.filter(link => {
              const url = new URL(link.href);
              return url.pathname === '/contact/' && url.hash === '#community' && /Cronus Zen/i.test(link.textContent);
            }).length);
            check(communityLinks > 0, `${label}: Home links internally to the Cronus Zen Discord section`);
          } else {
            check(/Cronus Zen Discord community/.test(body) && /independent community, not an official Cronus, XIM or Apex Legends support service/.test(body), `${label}: useful independent-community copy is visible`);
            const emails = await page.locator('main a[href^="mailto:"]').evaluateAll(links => links.map(link => link.getAttribute('href')));
            check(emails.length === 1 && emails[0] === 'mailto:contact@timonconfigs.com', `${label}: public seller email is used instead of the WordPress administrator email`);
          }
        }
        if (width !== 320 && route !== '/account/') {
          await page.screenshot({ path: path.join(root, `artifacts/seo-20261030-${route === '/' ? 'home' : 'contact'}-${width}.png`), fullPage: true });
        }
        report.views.push({ route, width, status: response.status(), robots, canonicalCount });
      }
      await page.close();
    }
    const context = await browser.newContext();
    async function getLocal(url) {
      for (let redirects = 0; redirects < 4; redirects++) {
        check(local(url), 'Sitemap request remains on localhost.');
        const response = await context.request.get(url, { maxRedirects: 0 });
        if (![301, 302, 303, 307, 308].includes(response.status())) return response;
        url = new URL(response.headers().location, url).href;
      }
      throw new Error('Unexpected sitemap redirect loop.');
    }
    const indexResponse = await getLocal(new URL('/sitemap_index.xml', home).href);
    const indexXml = await indexResponse.text();
    check(indexResponse.status() === 200 && /<sitemapindex\b/.test(indexXml), 'Active Rank Math XML sitemap index returns HTTP 200.');
    const childUrls = Array.from(indexXml.matchAll(/<loc>(.*?)<\/loc>/g), match => match[1].replaceAll('&amp;', '&'));
    check(childUrls.length > 0, 'The XML index contains child sitemaps.');
    report.sitemap.push({ url: new URL('/sitemap_index.xml', home).href, status: indexResponse.status(), children: childUrls.length });
    for (const url of childUrls.slice(0, 8)) {
      const response = await getLocal(url);
      const xml = await response.text();
      check(response.status() === 200 && /<urlset\b/.test(xml), 'A listed child sitemap returns a valid XML URL set.');
      const urls = Array.from(xml.matchAll(/<loc>(.*?)<\/loc>/g), match => match[1].replaceAll('&amp;', '&'));
      check(!urls.some(value => /\/(?:account|my-downloads|my-account|cart|checkout|zamowienie)(?:\/|\?|$)/.test(new URL(value).pathname)), 'Private member and payment URLs are absent from public sitemaps.');
      report.sitemap.push({ url, status: response.status(), urls: urls.length });
    }
    await context.close();
    check(report.errors.length === 0, 'No JavaScript runtime errors.');
    report.passed = true;
  } catch (error) {
    report.failure = error.message;
    process.exitCode = 1;
  } finally {
    await browser.close();
    await writeFile(path.join(root, 'artifacts/seo-20261030-browser.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, assertions: report.assertions, views: report.views.length, sitemapDocuments: report.sitemap.length, errors: report.errors, failure: report.failure }));
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
