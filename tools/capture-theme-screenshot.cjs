/** Capture the actual WordPress viewport without altering its layout or content. */
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
(async () => {
  await mkdir(directory, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    await page.route('**/*', request => {
      const url = new URL(request.request().url());
      return url.origin === origin.origin ? request.continue() : request.abort();
    });
    const response = await page.goto(baseURL, { waitUntil: 'networkidle' });
    assert.equal(response.status(), 200);
    await page.evaluate(() => document.fonts.ready);
    await page.locator('.tc-hero__art').evaluate(image => image.decode());
    const geometry = await page.evaluate(() => ({
      header: document.querySelector('.tc-header').getBoundingClientRect().toJSON(),
      hero: document.querySelector('.tc-hero').getBoundingClientRect().toJSON(),
      overflow: document.documentElement.scrollWidth > innerWidth
    }));
    assert.equal(geometry.overflow, false, 'Actual Home must fit its viewport.');
    await page.screenshot({ path: output, fullPage: false });
    const png = await readFile(output);
    assert.equal(png.readUInt32BE(16), 1200);
    assert.equal(png.readUInt32BE(20), 900);
    await chmod(output, 0o644);
    await writeFile(path.join(directory, 'theme-screenshot-capture.json'), JSON.stringify({
      origin: origin.origin, capturedAt: new Date().toISOString(), production: false,
      mode: 'actual-local-WordPress-viewport', reducedMotion: true, geometry,
      dimensions: { width: 1200, height: 900 }, output
    }, null, 2));
    console.log(JSON.stringify({ output, width: 1200, height: 900, mode: 'actual-local-WordPress-viewport' }));
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
