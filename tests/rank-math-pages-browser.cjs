/** Native Rank Math HTTP rendering; reversible localhost data and no external requests. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const output = path.join(root, 'artifacts');
const report = { passed: false, restored: false, assertions: 0, views: [], errors: [], outboundBlocked: 0 };
function check(value, message) { report.assertions++; assert.ok(value, message); }
function fixture(mode) {
  const env = { ...process.env }; for (const key of ['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH']) delete env[key];
  const result = execFileSync('docker', ['--host=unix:///var/run/docker.sock', 'compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', '-e', `TC_PAGE_SEO_BROWSER_MODE=${mode}`, '-v', `${root}/tests:/tests:ro`, '-w', '/var/www/html', 'cli', 'eval-file', '/tests/rank-math-pages-browser-fixture.php'], { cwd: root, env, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  const line = result.split('\n').find(value => value.startsWith('TC_PAGE_SEO_BROWSER:'));
  if (!line) throw new Error(`Native SEO fixture ${mode} returned no structured result.`);
  return JSON.parse(line.slice('TC_PAGE_SEO_BROWSER:'.length));
}
function local(url) { const parsed = new URL(url); return parsed.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(parsed.hostname) && parsed.port === '8080'; }
function forbiddenSchema(node) {
  if (!node || typeof node !== 'object') return false;
  if ([node['@type']].flat().some(type => ['Review', 'AggregateRating'].includes(type))) return true;
  return Object.values(node).some(forbiddenSchema);
}

(async () => {
  await mkdir(output, { recursive: true });
  let data, browser;
  try {
    data = fixture('setup'); check(data.fixture_active && local(data.collection.url), 'Only a disposable localhost fixture is tested.');
    for (const entry of [data.collection, data.release]) {
      for (const field of ['title', 'description', 'focus_keyword']) check(entry.native[field] === entry.profile[field], `Fixture ${entry.id} stores the prepared native Rank Math ${field}.`);
    }
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const collectionPage2 = new URL(data.collection.url); collectionPage2.searchParams.set('collection_page', '2');
    const archivePage2 = new URL('page/2/', data.archive.url);
    const routes = [
      { name: 'collection', url: data.collection.url, profile: data.collection.profile, page: 1 },
      { name: 'collection-page-2', url: collectionPage2.href, profile: data.collection.profile, page: 2 },
      { name: 'archive', url: data.archive.url, profile: data.archive.profile, page: 1 },
      { name: 'archive-page-2', url: archivePage2.href, profile: data.archive.profile, page: 2 },
      { name: 'release', url: data.release.url, profile: data.release.profile, page: 1 },
    ];
    for (const width of [390, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      await page.route('**/*', route => {
        if (local(route.request().url())) return route.continue();
        report.outboundBlocked++; return route.abort();
      });
      for (const entry of routes) {
        const label = `${entry.name}/${width}`;
        const response = await page.goto(entry.url, { waitUntil: 'networkidle' });
        const html = await page.content(); const body = await page.locator('body').innerText();
        check(response.status() === 200, `${label}: HTTP200`);
        check(!/Fatal error|Warning:|Deprecated:|critical error|Uncaught Error/.test(body), `${label}: no PHP error output`);
        check(!html.includes('LOCAL_QA_NATIVE_SEO_PRIVATE_VALUES_MUST_NOT_RENDER'), `${label}: protected Values remain absent`);
        check(await page.locator('main h1').count() === 1 && await page.locator('head title').count() === 1, `${label}: one main heading and document title`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${label}: no horizontal overflow`);
        const title = await page.title();
        if (entry.page === 1) check(title === entry.profile.title, `${label}: actual native provider title matches prepared copy`);
        else check(title.startsWith(entry.profile.title) && /\bPage\s+2\b/i.test(title) && title !== entry.profile.title, `${label}: actual provider output has a distinct page-two title`);
        check(!/%(?:page|title|sitename|sep)%/.test(title), `${label}: no unresolved provider variables`);
        const descriptions = page.locator('head meta[name="description"]');
        check(await descriptions.count() === 1 && await descriptions.getAttribute('content') === entry.profile.description, `${label}: one native provider description with prepared copy`);
        const canonical = page.locator('head link[rel="canonical"]');
        check(await canonical.count() === 1 && await canonical.getAttribute('href') === entry.url, `${label}: one correct canonical, including pagination`);
        check(await page.locator('head meta[name="robots"]').count() === 1, `${label}: one provider robots declaration`);
        const scripts = page.locator('script[type="application/ld+json"]');
        check(await scripts.count() <= 1, `${label}: metadata providers do not duplicate schema scripts`);
        if (await scripts.count()) {
          check((await scripts.getAttribute('class') || '').includes('rank-math-schema'), `${label}: Rank Math owns schema output`);
          const schema = JSON.parse(await scripts.textContent());
          check(schema['@context'] === 'https://schema.org' && !forbiddenSchema(schema), `${label}: valid schema without invented ratings/reviews`);
        }
        if (entry.name.startsWith('collection') || entry.name.startsWith('archive')) check(await page.locator('.tc-config-card').count() > 0, `${label}: real catalogue pages render the released fixture cards`);
        report.views.push({ name: entry.name, width, title, canonical: await canonical.getAttribute('href') });
      }
      await page.close();
    }
    check(report.errors.length === 0, 'No browser runtime errors.'); report.passed = true;
  } catch (error) { report.failure = error.stack || error.message; process.exitCode = 1; }
  finally {
    if (browser) await browser.close();
    if (data) { try { report.restored = Boolean(fixture('restore').restored); } catch (error) { report.restoreFailure = error.stack || error.message; report.passed = false; process.exitCode = 1; } }
    await writeFile(path.join(output, 'rank-math-pages-browser.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, restored: report.restored, assertions: report.assertions, views: report.views.length, errors: report.errors, failure: report.failure, restoreFailure: report.restoreFailure }));
  }
})();
