/** End-to-end website, catalogue, CMS, and technical SEO verification. */
const assert = require('node:assert/strict');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const { ROUTES, artifactDirectory, writeStatusReport } = require('../tools/audit-public-urls.cjs');
const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';
if (new URL(baseURL).username || new URL(baseURL).password) throw new Error('Do not put credentials in TC_TEST_URL.');

const report = { startedAt: new Date().toISOString(), baseURL, passed: false, routes: [], viewportChecks: [], performance: [], checks: {}, failures: [] };
const errors = [];
function check(condition, message) { if (!condition) report.failures.push(message); }
function schemaTypes(value) {
  if (!value || typeof value !== 'object') return [];
  if (Array.isArray(value)) return value.flatMap(schemaTypes);
  return [...(value['@type'] ? [].concat(value['@type']) : []), ...Object.values(value).flatMap(item => typeof item === 'object' ? schemaTypes(item) : [])];
}

async function collectDocument(page, route, http) {
  return page.evaluate(({ route, http }) => {
    const content = selector => document.querySelector(selector)?.content || '';
    const jsonld = [...document.querySelectorAll('script[type="application/ld+json"]')].map(script => {
      try { return { valid: true, data: JSON.parse(script.textContent) }; }
      catch { return { valid: false, data: null }; }
    });
    return {
      path: route, http, title: document.title,
      h1Count: document.querySelectorAll('h1').length,
      h1: document.querySelector('h1')?.textContent.trim() || '',
      description: content('meta[name="description"]'),
      descriptionCount: document.querySelectorAll('meta[name="description"]').length,
      canonicals: [...document.querySelectorAll('link[rel="canonical"]')].map(link => link.href),
      robots: [...document.querySelectorAll('meta[name="robots"]')].map(meta => meta.content).join(', '),
      ogTitle: content('meta[property="og:title"]'),
      ogImage: content('meta[property="og:image"]'),
      twitterCard: content('meta[name="twitter:card"]'),
      jsonld,
      comingSoon: !!document.querySelector('.tc-collection-hero .tc-collection-status:not(.is-available)'),
      errorsInHTML: /(?:Fatal error|Warning|Deprecated|Notice):[\s\S]{0,220}(?:on line|\.php)/.test(document.body.innerText),
    };
  }, { route, http });
}

async function recordPerformance(page, width) {
  await page.addInitScript(() => {
    window.__tcPerformance = { lcpMs: null, cls: 0, interactionMs: null };
    if (!window.PerformanceObserver) return;
    try { new PerformanceObserver(list => { for (const entry of list.getEntries()) window.__tcPerformance.lcpMs = Math.round(entry.startTime); }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch {}
    try { new PerformanceObserver(list => { for (const entry of list.getEntries()) if (!entry.hadRecentInput) window.__tcPerformance.cls += entry.value; }).observe({ type: 'layout-shift', buffered: true }); } catch {}
    try { new PerformanceObserver(list => { for (const entry of list.getEntries()) if (entry.interactionId) window.__tcPerformance.interactionMs = Math.max(window.__tcPerformance.interactionMs || 0, entry.duration); }).observe({ type: 'event', buffered: true, durationThreshold: 16 }); } catch {}
  });
  await page.goto(baseURL, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  if (await page.locator('.tc-hero__art').count()) await page.locator('.tc-hero__art').evaluate(img => img.decode());
  const summary = page.locator('.tc-faq-list summary').first();
  if (await summary.count()) { await summary.click(); await page.waitForTimeout(150); }
  const values = await page.evaluate(() => ({ ...window.__tcPerformance, cls: Number(window.__tcPerformance.cls.toFixed(4)) }));
  report.performance.push({ width, ...values });
}

async function verifySitemap(request) {
  const response = await request.get(new URL('/wp-sitemap.xml', baseURL).href);
  check(response.status() === 200, `Core XML sitemap responds ${response.status()}`);
  if (response.status() !== 200) return;
  const readLocations = body => [...body.matchAll(/<loc>([\s\S]*?)<\/loc>/g)].map(match => match[1].replaceAll('&amp;', '&'));
  const body = await response.text();
  const locations = readLocations(body);
  const entries = [...locations];
  if (body.includes('<sitemapindex')) {
    for (const url of locations.filter(url => !url.includes('-users-')).slice(0, 20)) {
      const child = await request.get(url);
      check(child.status() === 200, `Sitemap child responds ${child.status()}: ${url}`);
      if (child.status() === 200) entries.push(...readLocations(await child.text()));
    }
  }
  for (const item of report.routes.filter(row => row.comingSoon)) {
    const url = new URL(item.path, baseURL).href.replace(/\/$/, '');
    check(!entries.some(entry => entry.replace(/\/$/, '') === url), `Coming-soon collection appears in sitemap: ${item.path}`);
  }
  report.checks.sitemap = { status: response.status(), discoveredURLs: entries.length, emptyCollectionsExcluded: !report.failures.some(message => message.includes('appears in sitemap')) };
}

async function verifyFilters(page) {
  await page.goto(new URL('/configs/', baseURL).href, { waitUntil: 'networkidle' });
  for (const name of ['config_search', 'game', 'device', 'input', 'platform', 'tier']) check(await page.locator(`.tc-catalog-filter [name="${name}"]`).count() > 0, `Missing catalogue control: ${name}`);
  await page.locator('select[name="input"]').selectOption('controller');
  await page.locator('select[name="tier"]').selectOption('free');
  await Promise.all([page.waitForURL(url => url.searchParams.get('input') === 'controller' && url.searchParams.get('tier') === 'free'), page.locator('.tc-catalog-filter button[type="submit"]').click()]);
  check(new URL(page.url()).pathname === '/configs/', 'Filters leave the catalogue unexpectedly');
  for (const collection of [
    { route: '/apex-legends-configs/', key: 'game', slug: 'apex-legends' },
    { route: '/cronus-zen-configs/', key: 'device', slug: 'cronus-zen' },
    { route: '/platforms/pc/', key: 'platform', slug: 'pc' },
  ]) {
    await page.goto(new URL(collection.route, baseURL).href, { waitUntil: 'networkidle' });
    const control = page.locator(`select[name="${collection.key}"]`);
    check(await control.inputValue() === collection.slug && await control.isDisabled(), `Collection filter does not display its fixed ${collection.key}`);
    check(await page.locator(`input[type="hidden"][name="${collection.key}"]`).inputValue() === collection.slug, `Collection form loses its ${collection.key}`);
    await page.locator('select[name="input"]').selectOption('mnk');
    await Promise.all([page.waitForURL(url => url.searchParams.get('input') === 'mnk'), page.locator('.tc-catalog-filter button[type="submit"]').click()]);
    check(new URL(page.url()).pathname === collection.route && new URL(page.url()).searchParams.get(collection.key) === collection.slug, `Collection filtering loses its ${collection.key} context`);
  }
  for (const route of ['/configs/?game[]=apex-legends&device[]=cronus-zen&tier[]=free&input[]=mnk&platform[]=pc&config_search[]=Apex', '/configs/?game=not-a-game&device=not-a-device&tier=not-a-tier&input=not-an-input&platform=not-a-platform']) {
    const response = await page.goto(new URL(route, baseURL).href, { waitUntil: 'networkidle' });
    check(response.status() === 200, `Invalid scalar/array filters return ${response.status()}`);
    check(!/(?:Fatal error|Warning|Deprecated|Notice):[\s\S]{0,220}(?:on line|\.php)/.test(await page.locator('body').innerText()), 'Malformed catalogue filters emit PHP diagnostics');
  }
  report.checks.catalogueFilters = 'search/game/device/input/platform/tier controls present; submitted filters retain collection context; malformed scalar and array parameters handled';
}

async function verifyCMS(browser) {
  const origin = new URL(baseURL);
  if (!['127.0.0.1', 'localhost'].includes(origin.hostname)) {
    report.checks.gutenberg = 'not run: automatic admin login is restricted to the local disposable WordPress';
    return;
  }
  const page = await browser.newPage();
  const editorErrors = [];
  const editorEnvironmentFailures = [];
  page.on('pageerror', error => editorErrors.push(error.message));
  page.on('console', message => {
    if (message.type() !== 'error') return;
    let host = '';
    try { host = new URL(message.location().url).hostname; } catch {}
    if (message.text().includes('net::ERR_TUNNEL_CONNECTION_FAILED') && ['secure.gravatar.com', 's.w.org'].includes(host)) {
      editorEnvironmentFailures.push({ host, reason: 'blocked_by_environment_proxy' });
    } else editorErrors.push(message.text());
  });
  try {
    await page.goto(new URL('/wp-login.php', baseURL).href);
    await page.locator('#user_login').fill(process.env.TC_TEST_ADMIN_USER || 'timonadmin');
    await page.locator('#user_pass').fill(process.env.TC_TEST_ADMIN_PASSWORD || 'local-preview-only');
    await Promise.all([page.waitForURL(url => url.pathname.startsWith('/wp-admin/')), page.locator('#wp-submit').click()]);
    const response = await page.request.get(new URL('/wp-json/wp/v2/pages?slug=home&_fields=id', baseURL).href);
    const pages = await response.json();
    assert.ok(Array.isArray(pages) && pages[0]?.id, 'Home page exists for a read-only Gutenberg edit-screen check');
    await page.goto(new URL(`/wp-admin/post.php?post=${pages[0].id}&action=edit`, baseURL).href, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.wp?.blocks?.getBlockTypes().some(block => block.name.startsWith('timonconfigs/')), null, { timeout: 15000 });
    const welcome = page.getByRole('dialog').filter({ hasText: 'Welcome to the editor' });
    if (await welcome.count()) {
      await welcome.getByRole('button', { name: 'Close', exact: true }).click();
      await welcome.waitFor({ state: 'hidden' });
    }
    const blocks = await page.evaluate(() => wp.blocks.getBlockTypes().filter(block => block.name.startsWith('timonconfigs/')).map(block => block.name));
    check(blocks.length > 0, 'TimonConfigs Gutenberg blocks are registered');
    await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks().length >= 10, null, { timeout: 15000 });
    const homeBlocks = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map(block => ({ name: block.name, section: block.attributes.section, valid: block.isValid !== false })));
    const sections = homeBlocks.filter(block => block.name === 'timonconfigs/section');
    check(sections.length === 10, `Home editor has ${sections.length} storefront sections; expected 10`);
    check(sections.every(block => block.valid && typeof block.section === 'string' && block.section.length > 0), 'Home editor contains invalid storefront blocks or missing section attributes');
    const canvas = page.frames().find(frame => frame.name() === 'editor-canvas') || page;
    await canvas.waitForFunction(() => {
      const previews = [...document.querySelectorAll('.tc-block-editor-preview')];
      return previews.length === 10 && previews.every(preview => preview.querySelector('section, .tc-assurance-strip'));
    }, null, { timeout: 15000 });
    const previewCount = await canvas.locator('.tc-block-editor-preview').count();
    check(previewCount === 10, `Home editor has ${previewCount} rendered section previews; expected 10`);
    const heroBlock = canvas.locator('[data-type="timonconfigs/section"]').first();
    await heroBlock.click({ position: { x: 15, y: 15 } });
    const headingControl = page.getByLabel('Heading', { exact: true });
    if (!(await headingControl.count())) {
      const settings = page.getByRole('button', { name: 'Settings', exact: true });
      if (await settings.count()) await settings.click();
    }
    check(await page.getByLabel('Heading', { exact: true }).count() > 0, 'Selected Home section exposes the Heading editor control');
    const types = await page.evaluate(async () => {
      const response = await window.wp.apiFetch({ path: '/wp/v2/types?context=edit' });
      return Object.keys(response);
    });
    for (const type of ['tc_config', 'tc_review', 'tc_faq', 'tc_video']) check(types.includes(type), `CPT missing from editor REST types: ${type}`);
    check(editorErrors.length === 0, `Gutenberg browser errors: ${[...new Set(editorErrors)].join('; ')}`);
    report.checks.gutenberg = { registeredBlocks: blocks, editorialTypes: types.filter(type => type.startsWith('tc_')), homeBlocks: sections, renderedPreviews: previewCount, editorErrors: [...new Set(editorErrors)], editorEnvironmentFailures, savedContent: false };
  } catch (error) {
    const diagnostic = error.message.replaceAll(process.env.TC_TEST_ADMIN_PASSWORD || 'local-preview-only', '[redacted]');
    report.failures.push(`Gutenberg editor verification: ${diagnostic}`);
    if (editorErrors.length) report.failures.push(`Gutenberg browser errors: ${[...new Set(editorErrors)].join('; ')}`);
  } finally { await page.close(); }
}

(async () => {
  await mkdir(artifactDirectory, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  if (process.argv.includes('--cms-only')) {
    try { await verifyCMS(browser); }
    finally { await browser.close(); }
    report.passed = report.failures.length === 0;
    await writeFile(path.join(artifactDirectory, 'website-cms-checks.json'), JSON.stringify(report, null, 2));
    await writeStatusReport();
    console.log(JSON.stringify({ passed: report.passed, gutenberg: report.checks.gutenberg, failures: report.failures }, null, 2));
    if (!report.passed) process.exitCode = 1;
    return;
  }
  try {
    for (const width of [1440, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
      for (const route of ROUTES) {
        const response = await page.goto(new URL(route, baseURL).href, { waitUntil: 'networkidle' });
        const http = response?.status() || 0;
        const item = await collectDocument(page, route, http);
        check(http === 200, `${route} returns HTTP ${http} at ${width}px`);
        check(item.h1Count === 1, `${route} has ${item.h1Count} H1 elements at ${width}px`);
        check(item.canonicals.length === 1, `${route} has ${item.canonicals.length} canonical tags`);
        check(item.descriptionCount === 1 && !!item.description, `${route} lacks one nonempty meta description`);
        check(!!item.title && !!item.ogTitle && !!item.ogImage && !!item.twitterCard, `${route} lacks title/social metadata`);
        check(item.jsonld.every(schema => schema.valid), `${route} contains invalid JSON-LD`);
        check(!item.errorsInHTML, `${route} exposes PHP diagnostics`);
        if (item.comingSoon) check(/noindex/i.test(item.robots), `${route} is empty/coming soon without noindex`);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
        check(!overflow, `${route} has horizontal overflow at ${width}px`);
        if (width === 1440) report.routes.push({ ...item, schemaTypes: item.jsonld.flatMap(schema => schemaTypes(schema.data)) });
        report.viewportChecks.push({ path: route, width, http, horizontalOverflow: overflow });
      }
      await recordPerformance(page, width);
      if (width === 1440) {
        await verifyFilters(page);
        const names = await page.locator('#primary-menu>li>a').allTextContents();
        check(['Home', 'Configs', 'Games', 'Pricing', 'Reviews', 'FAQ', 'Contact'].every(name => names.includes(name)), 'Primary menu lacks a required public page');
        report.checks.navigation = names;
      } else {
        await page.goto(baseURL, { waitUntil: 'networkidle' });
        await page.locator('[data-menu-toggle]').click();
        check(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded') === 'true', 'Mobile menu does not open');
        await page.keyboard.press('Escape');
        check(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded') === 'false', 'Mobile menu does not close with Escape');
      }
      await page.close();
    }
    const tiny = await browser.newPage({ viewport: { width: 320, height: 900 } });
    for (const route of ['/', '/configs/', '/games/', '/pricing/', '/call-of-duty-configs/']) {
      await tiny.goto(new URL(route, baseURL).href, { waitUntil: 'networkidle' });
      const overflow = await tiny.evaluate(() => document.documentElement.scrollWidth > innerWidth);
      check(!overflow, `${route} has horizontal overflow at 320px`);
      report.viewportChecks.push({ path: route, width: 320, horizontalOverflow: overflow });
    }
    await tiny.close();
    await verifySitemap(browser.contexts()[0]?.request || (await browser.newContext()).request);
    await verifyCMS(browser);
    const titleMap = new Map();
    const descriptionMap = new Map();
    for (const item of report.routes.filter(row => row.path !== '/configs/?tier=free')) {
      if (titleMap.has(item.title)) check(false, `Duplicate title: ${item.path} and ${titleMap.get(item.title)}`);
      if (descriptionMap.has(item.description)) check(false, `Duplicate description: ${item.path} and ${descriptionMap.get(item.description)}`);
      titleMap.set(item.title, item.path); descriptionMap.set(item.description, item.path);
    }
    check(!report.routes.some(item => item.schemaTypes.includes('AggregateRating')), 'Demonstration reviews must not emit aggregate ratings');
    check(errors.length === 0, `Browser errors: ${[...new Set(errors)].join('; ')}`);
    report.checks.browserErrors = [...new Set(errors)];
  } catch (error) { report.failures.push(error.stack || error.message); }
  finally {
    report.passed = report.failures.length === 0;
    await browser.close();
    await writeFile(path.join(artifactDirectory, 'website-checks.json'), JSON.stringify(report, null, 2));
    await writeStatusReport();
  }
  console.log(JSON.stringify({ passed: report.passed, routes: report.routes.length, viewportChecks: report.viewportChecks.length, failures: report.failures, report: path.resolve(__dirname, '../docs/URL_STATUS.md') }, null, 2));
  if (!report.passed) process.exitCode = 1;
})();
