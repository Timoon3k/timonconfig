/**
 * Real WordPress DOM capture. The 1200x900 theme preview uses browser-only compact
 * Home styling so actual Hero, pricing cards and footer stay legible together.
 * The untouched full-page 1440/390 captures are saved separately as evidence.
 * No generated artwork, image compositing, database writes or production changes.
 */
const assert = require('node:assert/strict');
const { mkdir, readFile, writeFile, chmod } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';
const origin = new URL(baseURL);
if (origin.username || origin.password) throw new Error('Do not embed credentials in screenshot URLs.');
const root = path.resolve(__dirname, '..');
const directory = path.join(root, 'artifacts');
const output = path.join(root, 'wp-content/themes/timonconfigs/screenshot.png');

async function prepare(page) {
  const response = await page.goto(baseURL, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200, 'The running WordPress must respond successfully.');
  await page.evaluate(() => document.fonts.ready);
  await page.locator('.tc-hero__art').evaluate(image => image.decode());
  assert.equal(await page.locator('.tc-price-card').count(), 3);
}

(async () => {
  await mkdir(directory, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const report = { origin: origin.origin, capturedAt: new Date().toISOString(), production: false, mode: 'actual-local-WordPress-with-browser-only-compact-preview', omittedPreviewSections: 'Home sections other than Hero and Pricing are hidden only inside this screenshot browser; original full-page renders are retained.' };
  try {
    for (const width of [1440, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await prepare(page);
      await page.evaluate(async () => {
        const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
        for (let y = 0; y < document.documentElement.scrollHeight; y += 700) { scrollTo(0, y); await delay(45); }
        scrollTo(0, 0);
      });
      await page.screenshot({ path: path.join(directory, `member-release-home-${width}-full.png`), fullPage: true });
      await page.close();
    }
    const page = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1 });
    await prepare(page);
    await page.addStyleTag({ content: `
      html {scrollbar-width:none;scroll-behavior:auto!important} body {min-height:900px}
      .tc-header {position:relative!important} .tc-header__inner {min-height:58px!important}
      .tc-header .tc-brand img {max-height:42px!important} .tc-header .tc-nav {gap:12px!important}
      .tc-header .tc-nav__list {gap:16px!important} .tc-header .tc-nav__list a {font-size:13px!important}
      .tc-header .tc-button {min-height:34px!important;padding:0 12px!important;font-size:10px!important}
      #main > :not(.tc-hero):not(.tc-home-pricing) {display:none!important}
      .tc-hero {height:350px!important;min-height:350px!important;aspect-ratio:auto!important}
      .tc-hero__inner {min-height:350px!important;height:350px!important;display:flex!important;align-items:center!important}
      .tc-hero__content {padding:20px 0!important;max-width:510px!important}
      .tc-hero h1 {font-size:36px!important;max-width:510px!important;margin-bottom:10px!important}
      .tc-hero__tagline {font-size:19px!important;margin:0 0 8px!important}
      .tc-hero__description {font-size:12px!important;max-width:390px!important;line-height:1.5!important;margin-bottom:16px!important}
      .tc-hero .tc-eyebrow {font-size:8px!important;margin-bottom:12px!important}
      .tc-hero .tc-button {min-height:36px!important;padding:0 20px!important;font-size:11px!important}
      .tc-hero .tc-trust {margin-top:12px!important;gap:20px!important;font-size:9px!important}
      .tc-home-pricing {padding:26px 0 24px!important;min-height:0!important}
      .tc-pricing-layout {grid-template-columns:190px 1fr!important;gap:22px!important}
      .tc-pricing-layout>.tc-heading-marker h2 {font-size:29px!important;margin-bottom:16px!important}
      .tc-pricing-layout>.tc-heading-marker>p:not(.tc-eyebrow) {font-size:11px!important}
      .tc-pricing-layout>.tc-heading-marker .tc-text-link {font-size:10px!important}
      .tc-price-card {padding:17px 18px!important;min-height:0!important}
      .tc-price-card__heading {gap:10px!important;margin-bottom:10px!important}
      .tc-price-card__heading svg {width:28px!important;height:28px!important}
      .tc-price-card__name {font-size:11px!important;margin-bottom:3px!important}
      .tc-price-card__tagline {font-size:9px!important}
      .tc-price {margin:10px 0 14px!important} .tc-price strong {font-size:37px!important}
      .tc-price span {font-size:10px!important}
      .tc-price-card ul {margin:0 0 18px!important;padding:0!important}
      .tc-price-card li {font-size:10px!important;margin:5px 0!important;line-height:1.4!important}
      .tc-price-card .tc-button {min-height:33px!important;font-size:10px!important;padding:0 10px!important}
      .tc-price-card__flag {font-size:8px!important;top:-13px!important;padding:3px 10px!important}
      .tc-plan-details {font-size:8px!important;margin-top:8px!important}
      .tc-footer {padding:18px 0 12px!important}
      .tc-footer__grid {gap:20px!important;align-items:center!important}
      .tc-footer .tc-brand img {max-height:36px!important;max-width:160px!important}
      .tc-footer__brand {gap:20px!important} .tc-footer__brand p {font-size:10px!important;max-width:260px!important}
      .tc-footer a {font-size:9px!important} .tc-footer ul {gap:12px!important}
      .tc-footer__legal {padding-top:12px!important;margin-top:15px!important;gap:12px!important;font-size:8px!important}
      .tc-footer__legal p {font-size:8px!important} .tc-footer__disclaimer {font-size:7px!important}
    ` });
    await page.evaluate(() => document.fonts.ready);
    const geometry = await page.evaluate(() => ({ header: document.querySelector('.tc-header').getBoundingClientRect().toJSON(), hero: document.querySelector('.tc-hero').getBoundingClientRect().toJSON(), pricing: document.querySelector('.tc-home-pricing').getBoundingClientRect().toJSON(), footer: document.querySelector('.tc-footer').getBoundingClientRect().toJSON(), overflow: document.documentElement.scrollWidth > innerWidth }));
    assert.equal(geometry.overflow, false, 'Preview has no horizontal overflow.');
    assert.ok(geometry.footer.bottom <= 900, `Actual footer must be included (${geometry.footer.bottom}px).`);
    assert.ok(geometry.hero.height >= 250 && geometry.pricing.height >= 230, 'The real Hero and pricing remain visible and readable.');
    await page.screenshot({ path: output, fullPage: false });
    const png = await readFile(output);
    assert.equal(png.readUInt32BE(16), 1200);
    assert.equal(png.readUInt32BE(20), 900);
    await chmod(output, 0o644);
    report.geometry = geometry;
    report.dimensions = { width: 1200, height: 900, aspectRatio: '4:3' };
    report.output = output;
    await writeFile(path.join(directory, 'theme-screenshot-capture.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ output, width: 1200, height: 900, footerBottom: geometry.footer.bottom, mode: report.mode }, null, 2));
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
