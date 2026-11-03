/** Read-only browser coverage for the crimson theme; never writes WordPress data. */
const assert = require('node:assert/strict');
const path = require('node:path');
const { mkdir, writeFile } = require('node:fs/promises');
const { chromium } = require('playwright');
const manifest = require('../wp-content/themes/timonconfigs/assets/images/crimson-assets.json');

const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';
const origin = new URL(baseURL);
assert.ok(['localhost', '127.0.0.1'].includes(origin.hostname) && origin.port === '8080', 'Use the local WordPress test server');
const artifacts = path.resolve(__dirname, '../artifacts');
const report = { passed: false, cases: [], motion: [], screenshots: [], errors: [] };

(async () => {
  await mkdir(artifacts, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    for (const width of [320, 390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'no-preference' });
      let routeName = '';
      page.on('pageerror', error => report.errors.push(`${width} ${routeName}: ${error.message}`));
      page.on('console', message => {
        if (message.type() === 'error' && !message.text().includes('net::ERR_FAILED')) report.errors.push(`${width} ${routeName}: ${message.text()}`);
      });
      // No external integrations or API requests are necessary to exercise layout.
      await page.route('**/*', request => {
        const url = new URL(request.request().url());
        return url.origin === origin.origin || url.protocol === 'data:' ? request.continue() : request.abort();
      });
      for (const route of ['/', '/configs/', '/pricing/', '/contact/', '/account/']) {
        routeName = route;
        const response = await page.goto(new URL(route, baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, `${width} ${route}: HTTP 200`);
        await page.evaluate(() => document.fonts.ready);
        await page.locator('img').evaluateAll(images => images.forEach(image => { image.loading = 'eager'; }));
        await page.waitForFunction(() => Array.from(document.images).every(image => image.complete));
        const layout = await page.evaluate(() => ({
          width: innerWidth,
          scrollWidth: document.documentElement.scrollWidth,
          brokenImages: Array.from(document.images).filter(image => !image.naturalWidth).map(image => image.currentSrc || image.src),
          imageCount: document.images.length,
        }));
        assert.equal(layout.scrollWidth <= layout.width, true, `${width} ${route}: no horizontal overflow (${layout.scrollWidth})`);
        assert.deepEqual(layout.brokenImages, [], `${width} ${route}: no broken images`);
        assert.equal(await page.locator('main h1').count(), 1, `${width} ${route}: one primary heading`);
        assert.doesNotMatch(await page.locator('body').innerText(), /(?:Fatal error|Deprecated|Warning|Notice):[\s\S]{0,250}(?:on line|\.php)|There has been a critical error/, `${width} ${route}: no PHP diagnostics`);
        const mainStyle = await page.locator('body').evaluate(element => getComputedStyle(element).backgroundColor);
        assert.equal(mainStyle, 'rgb(8, 7, 9)', `${width} ${route}: final crimson stylesheet is active`);
        const brand = page.locator('.tc-header .tc-brand');
        assert.ok(await brand.isVisible(), `${width} ${route}: visible brand`);
        const brandImage = brand.locator('img');
        assert.equal(await brandImage.count(), 1, `${width} ${route}: one identity image`);
        if (route === '/') {
          const image = page.locator('.tc-hero__art');
          const media = await image.evaluate(element => ({ src: element.currentSrc, width: element.width, height: element.height, naturalWidth: element.naturalWidth, naturalHeight: element.naturalHeight, declaredWidth: element.getAttribute('width'), declaredHeight: element.getAttribute('height'), srcset: element.srcset, sizes: element.sizes, alt: element.alt, priority: element.fetchPriority }));
          assert.match(media.src, /apex-legends-crimson-hero(?:-\d+)?\.webp/, 'Bundled responsive crimson illustration');
          assert.equal(media.declaredWidth, String(manifest.hero.width));
          assert.equal(media.declaredHeight, String(manifest.hero.height));
          assert.match(media.srcset, /-640\.webp 640w/);
          assert.match(media.srcset, /-960\.webp 960w/);
          assert.ok(media.sizes, 'Responsive image sizes are supplied');
          assert.equal(media.alt, manifest.hero.alt, 'Artwork has its descriptive alt text');
          assert.equal(media.priority, 'high');
          const visualBox = await page.locator('.tc-hero__visual').boundingBox();
          const textBox = await page.locator('.tc-hero__content').boundingBox();
          assert.ok(visualBox.width <= width && visualBox.height >= 250, 'Hero has a visible image frame');
          if (width <= 600) assert.ok(visualBox.y >= textBox.y + textBox.height - 15, 'Mobile text is separate from the illustration');
          const primary = page.locator('.tc-hero .tc-actions a').first();
          assert.equal(new URL(await primary.getAttribute('href'), baseURL).pathname, '/xim-matrix-apex-legends/', 'Hero keeps the device/game collection destination');
          await primary.focus();
          assert.equal(await primary.evaluate(element => element === document.activeElement && getComputedStyle(element).outlineStyle !== 'none'), true, 'Hero CTA has visible keyboard focus');
          const toggle = page.locator('[data-hero-motion-toggle]');
          await page.evaluate(() => window.scrollTo(0, 0));
          await page.waitForFunction(() => document.querySelector('[data-hero-atmosphere]').dataset.motionState === 'running');
          await toggle.focus();
          await page.keyboard.press('Space');
          assert.equal(await toggle.getAttribute('aria-pressed'), 'true', 'Keyboard can pause atmosphere');
          assert.equal(await page.locator('[data-hero-atmosphere]').getAttribute('data-motion-state'), 'paused');
          await page.keyboard.press('Space');
          assert.equal(await toggle.getAttribute('aria-pressed'), 'false', 'Keyboard can resume atmosphere');
          await page.emulateMedia({ reducedMotion: 'reduce' });
          await page.waitForFunction(() => document.querySelector('[data-hero-atmosphere]').dataset.motionState === 'paused');
          assert.equal(await toggle.isVisible(), false, 'Reduced motion hides the unnecessary motion control');
          assert.equal(await image.evaluate(element => getComputedStyle(element).animationName), 'none', 'Reduced motion renders static artwork');
          await page.emulateMedia({ reducedMotion: 'no-preference' });
          await page.locator('.tc-footer').scrollIntoViewIfNeeded();
          await page.waitForFunction(() => document.querySelector('[data-hero-atmosphere]').dataset.motionState === 'paused');
          await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
          await page.waitForFunction(() => document.querySelector('[data-hero-atmosphere]').dataset.motionState === 'running');
          report.motion.push({ width, keyboardPause: true, reducedMotion: true, offscreenPause: true, media });
          await page.locator('.tc-skip-link').focus();
          assert.equal(await page.locator('.tc-skip-link').evaluate(element => element === document.activeElement && element.getBoundingClientRect().width > 1), true, 'Skip-to-content link is visible on keyboard focus');
          await page.keyboard.press('Enter');
          assert.equal(new URL(page.url()).hash, '#main', 'Skip link targets the main landmark');
          await page.keyboard.press('Tab');
          assert.equal(await page.locator('main').evaluate(element => element.contains(document.activeElement)), true, 'Keyboard navigation continues within main content');
          await page.evaluate(() => { document.activeElement.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
          if (width === 390 || width === 1440) {
            const size = width === 1440 ? 'desktop' : 'mobile';
            const filename = `crimson-home-${size}.png`;
            await page.screenshot({ path: path.join(artifacts, filename), animations: 'disabled' });
            await page.screenshot({ path: path.join(artifacts, `crimson-home-full-${size}.png`), fullPage: true, animations: 'disabled' });
            report.screenshots.push(filename, `crimson-home-full-${size}.png`);
          }
        } else if ((width === 390 || width === 1440) && route !== '/contact/') {
          const filename = `crimson-${route.replaceAll('/', '')}-${width}.png`;
          await page.screenshot({ path: path.join(artifacts, filename), animations: 'disabled' });
          report.screenshots.push(filename);
        }
        if (width <= 768) {
          const menu = page.locator('[data-menu-toggle]');
          await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
          await menu.focus();
          await page.keyboard.press('Enter');
          assert.equal(await menu.getAttribute('aria-expanded'), 'true', 'Navigation opens with keyboard');
          assert.equal(await page.locator('[data-menu]').isVisible(), true, 'Mobile menu becomes visible');
          await page.keyboard.press('Enter');
          assert.equal(await menu.getAttribute('aria-expanded'), 'false', 'Navigation closes with keyboard');
        }
        report.cases.push({ width, route, images: layout.imageCount, overflow: false, passed: true });
      }
      await page.close();
    }
    assert.deepEqual(report.errors, [], 'No frontend runtime or console errors');
    report.passed = true;
  } catch (error) {
    report.error = error.message;
    throw error;
  } finally {
    await browser.close();
    await writeFile(path.join(artifacts, 'crimson-browser.json'), JSON.stringify(report, null, 2));
  }
  console.log(JSON.stringify({ passed: report.passed, cases: report.cases.length, motionWidths: report.motion.map(item => item.width), screenshots: report.screenshots, errors: report.errors }));
})().catch(error => { console.error(error); process.exitCode = 1; });
