/** Run against local/staging WordPress: node tests/storefront-browser.cjs */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const errors = [];
  try {
    for (const width of [1440, 1024, 768, 390, 320]) {
      for (const path of ['/', '/games/', '/pricing/', '/reviews/', '/faq/', '/video-showcase/', '/configs/']) {
        const page = await browser.newPage({ viewport: { width, height: 1000 } });
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
        const response = await page.goto(new URL(path, baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, `${path} HTTP ${width}`);
        assert.equal(await page.locator('h1').count(), 1, `${path} one H1`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${path} overflow at ${width}`);
        if (path === '/') {
          await page.evaluate(() => document.fonts.ready);
          assert.equal(await page.evaluate(() => [...document.fonts].some(font => font.family === 'Inter' && font.status === 'loaded')), true, 'Local Inter font is loaded');
          assert.equal(await page.locator('.tc-hero__art').evaluate(img => img.complete && img.naturalWidth > 0), true, 'Hero artwork loaded');
          assert.equal(await page.locator('.tc-header-discord').count(), 1, 'Community action stays present without an invented invite');
          if (width === 1440) {
            const shell = await page.locator('.tc-supported .tc-shell').boundingBox();
            assert.ok(shell.width / width > 0.94, 'Desktop grid follows the wide reference composition');
          }
        }
        if (path === '/' || path === '/video-showcase/') {
          assert.equal(await page.locator('dialog').count(), 0, 'No video modal');
          assert.equal(await page.locator('[data-inline-video]').count(), 3, 'Three inline cards');
          const players = page.locator('[data-video-player]');
          assert.equal(await players.first().getAttribute('src'), null, 'Media deferred');
          await page.locator('[data-video-play]').first().click();
          await page.waitForFunction(() => document.querySelector('[data-video-player]').currentTime > 0.15);
          assert.equal(await players.first().evaluate(video => video.paused), false, 'Native inline playback');
          const inCard = await players.first().evaluate(video => video.closest('.tc-video-card') !== null && video.controls && video.playsInline);
          assert.equal(inCard, true, 'Player stays in its card with controls');
          await page.locator('[data-video-play]').nth(1).click();
          await page.waitForFunction(() => document.querySelectorAll('[data-video-player]')[1].currentTime > 0.15);
          assert.equal(await players.first().evaluate(video => video.paused), true, 'Second playback pauses first');
          await players.nth(1).evaluate(video => video.pause());
        }
        if (path === '/' || path === '/games/') {
          assert.equal(await page.locator('.tc-game-cover').count(), 7, 'Seven reference-led game covers');
          assert.equal(await page.locator('.tc-game-cover.is-available').count(), 1, 'One current collection');
          assert.equal(await page.locator('.tc-game-cover.is-coming').count(), 6, 'Six planned collections');
          assert.equal(await page.locator('.tc-game-cover.is-coming[href*="/games/#game-"]').count(), 6, 'Planned games link to their honest roadmap anchors');
        }
        if (path === '/' || path === '/reviews/') {
          assert.equal(await page.locator('.tc-review-card').count(), 6, 'Empty installs show six clearly labelled sample cards');
          assert.equal(await page.locator('.tc-review-demo').count(), 6, 'Every sample review is visibly identified as demo content');
          await page.locator('[data-review-next]').click();
          await page.waitForFunction(() => document.querySelector('[data-review-track]').scrollLeft > 20);
        }
        if ((width === 390 || width === 320 || width === 768) && path === '/') {
          await page.locator('[data-menu-toggle]').click();
          assert.equal(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded'), 'true');
          await page.keyboard.press('Escape');
          assert.equal(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded'), 'false');
          const faq = page.locator('.tc-faq-list summary').first();
          await faq.focus();
          await page.keyboard.press('Enter');
          assert.equal(await faq.evaluate(summary => summary.parentElement.open), true, 'Keyboard FAQ');
        }
        if (path === '/') {
          await page.locator('input[name="config_search"]').fill('Apex');
          await Promise.all([page.waitForURL('**/configs/?config_search=Apex'), page.locator('.tc-search-input button').click()]);
          assert.equal(new URL(page.url()).searchParams.get('config_search'), 'Apex');
        }
        await page.close();
      }
    }
    // Keyboard activation and a failed media request must keep controls in-card.
    const page = await browser.newPage();
    await page.route('**/apex-demo-01.mp4', route => route.abort());
    await page.goto(baseURL);
    const play = page.locator('[data-video-play]').first();
    await play.focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('[data-video-player]').error !== null);
    await page.waitForFunction(() => !document.querySelector('[data-video-play]').hidden);
    assert.match(await play.textContent(), /RETRY/);
    assert.equal(await page.locator('dialog').count(), 0);
    await page.close();
    assert.deepEqual(errors, [], 'Browser console/page errors');
    console.log('Passed: responsive storefront, real inline playback, pause coordination, deferred media, keyboard controls, search and media error fallback.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
