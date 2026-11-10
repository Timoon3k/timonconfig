/** Real local Rank Math output for four Apex SEO targets; every fixture is reversible LOCAL QA. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const output = path.join(root, 'artifacts');
const report = { passed: false, restored: false, assertions: 0, views: [], screenshots: [], errors: [], outboundBlocked: 0 };
function check(value, message) { report.assertions++; assert.ok(value, message); }
function local(url) { const parsed = new URL(url); return parsed.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(parsed.hostname) && parsed.port === '8080'; }
function fixture(mode) {
  const env = { ...process.env }; for (const key of ['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH']) delete env[key];
  const result = execFileSync('docker', ['--host=unix:///var/run/docker.sock', 'compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', '-e', `TC_APEX_SEO_BROWSER_MODE=${mode}`, '-v', `${root}/tests:/tests:ro`, '-w', '/var/www/html', 'cli', 'eval-file', '/tests/apex-seo-browser-fixture.php'], { cwd: root, env, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  const line = result.split('\n').find(value => value.startsWith('TC_APEX_SEO_BROWSER:'));
  if (!line) throw new Error(`Apex SEO fixture ${mode} returned no structured result.`);
  return JSON.parse(line.slice('TC_APEX_SEO_BROWSER:'.length));
}
function forbiddenSchema(node) {
  if (!node || typeof node !== 'object') return false;
  if ([node['@type']].flat().some(type => ['Review', 'AggregateRating'].includes(type))) return true;
  return Object.values(node).some(forbiddenSchema);
}

(async () => {
  await mkdir(output, { recursive: true });
  let data, browser;
  try {
    data = fixture('setup'); check(data.fixture_active && local(data.home.url), 'Only a disposable localhost fixture is tested.');
    for (const entry of [data.home, data.game, ...data.collections]) {
      for (const field of ['title', 'description', 'focus_keyword']) check(entry.native[field] === entry.profile[field], `${entry.name}: native Rank Math ${field} is stored.`);
    }
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const routes = [data.home, data.game, ...data.collections].map(entry => ({ ...entry, page: 1 }));
    for (const entry of data.collections) { const url = new URL(entry.url); url.searchParams.set('collection_page', '2'); routes.push({ ...entry, url: url.href, name: `${entry.name}-page-2`, page: 2 }); }
    if (data.empty) routes.push({ ...data.empty, page: 1, empty: true });
    for (const width of [390, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      await page.route('**/*', route => { if (local(route.request().url())) return route.continue(); report.outboundBlocked++; return route.abort(); });
      for (const entry of routes) {
        const label = `${entry.name}/${width}`;
        const response = await page.goto(entry.url, { waitUntil: 'networkidle' });
        const html = await page.content(); const body = await page.locator('body').innerText();
        check(response.status() === 200, `${label}: HTTP200`);
        check(!/Fatal error|Warning:|Deprecated:|critical error|Uncaught Error/.test(body), `${label}: no PHP errors`);
        check(!html.includes('LOCAL_QA_APEX_SEO_PRIVATE_VALUES_MUST_NOT_RENDER'), `${label}: private Values stay absent`);
        check(await page.locator('main h1').count() === 1 && await page.locator('head title').count() === 1, `${label}: one H1 and title`);
        if (entry.h1) check(await page.locator('main h1').innerText() === entry.h1, `${label}: actual collection H1 remains correct`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${label}: no horizontal overflow`);
        const title = await page.title();
        check(entry.page === 1 ? title === entry.profile.title : title.startsWith(entry.profile.title) && /\bPage\s+2\b/.test(title), `${label}: native title and pagination match reviewed copy`);
        check(!/%(?:page|title|sitename|sep)%/.test(title), `${label}: no unresolved provider variables`);
        const description = page.locator('head meta[name="description"]');
        check(await description.count() === 1 && await description.getAttribute('content') === entry.profile.description, `${label}: one correct Rank Math description`);
        const canonical = page.locator('head link[rel="canonical"]');
        const canonicalCount = await canonical.count(); const canonicalHref = canonicalCount ? await canonical.first().getAttribute('href') : null;
        check(entry.empty ? canonicalCount <= 1 && (canonicalCount === 0 || canonicalHref === entry.url) : canonicalCount === 1 && canonicalHref === entry.url, `${label}: native canonical is correct (the provider may omit it on a noindex empty page)`);
        const robots = page.locator('head meta[name="robots"]');
        check(await robots.count() === 1, `${label}: one robots declaration`);
        if (entry.empty) check(/\bnoindex\b/.test(await robots.getAttribute('content')), `${label}: empty collection remains noindex`);
        const schemas = page.locator('script[type="application/ld+json"]');
        check(await schemas.count() <= 1, `${label}: no duplicate schema providers`);
        if (await schemas.count()) { check((await schemas.getAttribute('class') || '').includes('rank-math-schema'), `${label}: Rank Math owns JSON-LD`); check(!forbiddenSchema(JSON.parse(await schemas.textContent())), `${label}: no invented ratings or reviews`); }
        if (entry.device) {
          const lead = page.locator('.tc-collection-hero .tc-lead');
          const focus = entry.device === 'xim-matrix' ? 'XIM Matrix Apex Legends configs and settings' : 'Cronus Zen Apex Legends configs and scripts';
          const leadText = await lead.innerText();
          check(await lead.count() === 1 && leadText.replace(/\s+/g, ' ').includes(focus), `${label}: focused public introduction renders once; actual lead: ${JSON.stringify(leadText.slice(0, 220))}`);
          check(await lead.locator('strong').filter({ hasText: focus }).count() === 1 && await lead.locator('p').count() === 3 && await lead.locator('h2').count() === 1, `${label}: three paragraphs and a single subheading without repeated intro`);
          check(/Guide and Values/.test(leadText) && /Release details/.test(leadText), `${label}: useful release and setup guidance remains public`);
          check(entry.device === 'xim-matrix' ? /firmware/.test(leadText) && /mouse.*keyboard/i.test(leadText) : /script or backup/.test(leadText) && /existing setup/.test(leadText), `${label}: guidance matches the actual device`);
          check(!/Predator S30|LethalPanda|Custom S30|Season 30\/31|current season|Most Popular|All scripts receive regular updates/i.test(leadText), `${label}: unverified PDF release/season/popularity claims were not inserted`);
          check(await lead.evaluate(element => { const grid = document.querySelector('.tc-config-grid'); return Boolean(grid) && Boolean(element.compareDocumentPosition(grid) & Node.DOCUMENT_POSITION_FOLLOWING); }), `${label}: introduction is before the catalogue grid`);
          check(await page.locator('.tc-config-card').count() > 0 && /LOCAL QA Apex SEO public release/.test(await page.locator('.tc-config-grid').innerText()), `${label}: actual filtered fixture cards remain visible`);
          if (entry.page === 1) { const name = `apex-seo-${entry.device}-${width}.png`; await page.locator('.tc-collection-hero').screenshot({ path: path.join(output, name), animations: 'disabled' }); report.screenshots.push(name); }
        }
        if (entry.empty) check(await page.locator('.tc-config-card').count() === 0, `${label}: empty collection invents no release cards`);
        report.views.push({ name: entry.name, width, title, canonical: canonicalHref });
      }
      await page.close();
    }
    check(report.errors.length === 0, 'No browser runtime errors.'); report.passed = true;
  } catch (error) { report.failure = error.stack || error.message; process.exitCode = 1; }
  finally {
    if (browser) await browser.close();
    if (data) { try { report.restored = Boolean(fixture('restore').restored); } catch (error) { report.restoreFailure = error.stack || error.message; report.passed = false; process.exitCode = 1; } }
    await writeFile(path.join(output, 'apex-seo-browser.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, restored: report.restored, assertions: report.assertions, views: report.views.length, screenshots: report.screenshots, errors: report.errors, failure: report.failure, restoreFailure: report.restoreFailure }));
  }
})();
