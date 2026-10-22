/** Focused playback modes; YouTube is fulfilled locally and no external provider is contacted. */
const assert = require('node:assert/strict');
const { readFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    const source = await readFile(path.join(__dirname, '../wp-content/themes/timonconfigs/assets/js/main.js'), 'utf8');
    const page = await browser.newPage();
    let youtubeLoads = 0;
    await page.route('https://www.youtube-nocookie.com/**', async route => {
      youtubeLoads++;
      await route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body>Local YouTube test response</body></html>' });
    });
    await page.goto(baseURL, { waitUntil: 'networkidle' });
    const mp4 = new URL('/wp-content/themes/timonconfigs/assets/video/apex-demo-01.mp4', baseURL).href;
    await page.setContent(`<main>
      <article class="tc-video-card"><div data-inline-video data-video-kind="mp4" data-video-mode="inline"><video data-video-player data-video-src="${mp4}"></video><button data-video-play><span class="tc-inline-video__label">WATCH</span></button></div></article>
      <article class="tc-video-card"><div data-inline-video data-video-kind="mp4" data-video-mode="modal" data-video-title="Apex modal test"><video data-video-player data-video-src="${mp4}"></video><button data-video-play><span class="tc-inline-video__label">WATCH</span></button></div></article>
      <article class="tc-video-card"><div data-inline-video data-video-kind="youtube" data-video-mode="inline" data-youtube-id="abcdefghijk" data-video-title="Apex YouTube test"><button data-video-play><span class="tc-inline-video__label">LOAD YOUTUBE</span></button></div></article>
    </main>`);
    await page.addScriptTag({ content: source });
    assert.equal(await page.locator('iframe').count(), 0, 'No third-party player exists before interaction');
    assert.equal(youtubeLoads, 0, 'No YouTube request before interaction');
    await page.locator('[data-video-play]').nth(0).click();
    await page.waitForFunction(() => document.querySelector('[data-video-player]').currentTime > 0.1);
    assert.equal(await page.locator('dialog').count(), 0, 'Default mode stays inline');
    await page.locator('[data-video-play]').nth(1).click();
    await page.waitForFunction(() => document.querySelector('dialog video')?.currentTime > 0.1);
    assert.equal(await page.locator('dialog[open]').count(), 1, 'Configured modal opens accessibly');
    assert.equal(await page.locator('[data-video-player]').first().evaluate(video => video.paused), true, 'Opening the second player pauses the first');
    await page.keyboard.press('Escape');
    await page.waitForSelector('dialog', { state: 'detached' });
    assert.equal(await page.locator('dialog').count(), 0, 'Escape removes optional modal');
    assert.equal(await page.locator('[data-inline-video]').nth(1).locator('video').count(), 1, 'Video returns to the original card');
    assert.equal(await page.locator('[data-video-play]').nth(1).evaluate(button => document.activeElement === button), true, 'Closing restores keyboard focus');
    await page.locator('[data-video-play]').nth(2).click();
    await page.waitForFunction(() => document.querySelector('[data-youtube-player]') !== null);
    assert.match(await page.locator('iframe').getAttribute('src'), /^https:\/\/www\.youtube-nocookie\.com\/embed\/abcdefghijk\?/);
    assert.equal(await page.locator('iframe').getAttribute('title'), 'Apex YouTube test');
    await page.waitForFunction(() => document.querySelector('iframe').contentWindow !== null);
    assert.equal(youtubeLoads, 1, 'One explicit action loads one privacy-enhanced YouTube player');
    console.log('Video browser passed: inline mode, optional modal, pause coordination, Escape/focus restoration and deferred YouTube.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
