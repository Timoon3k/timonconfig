/** LOCAL QA fixtures + real WordPress public pages; no Discord requests or purchases. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const origin = 'http://127.0.0.1:8080';
const output = path.join(root, 'artifacts');
const report = { passed: false, restored: false, assertions: 0, views: [], errors: [], screenshots: [], youtubeRequestsBeforeClick: 0 };
function check(value, message) { report.assertions++; assert.ok(value, message); }
function fixture(mode) {
  const env = { ...process.env }; for (const key of ['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH']) delete env[key];
  const result = execFileSync('docker', ['--host=unix:///var/run/docker.sock', 'compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', '-e', `TC_COMMUNITY_BROWSER_MODE=${mode}`, '-v', `${root}/tests:/tests:ro`, '-w', '/var/www/html', 'cli', 'eval-file', '/tests/community-media-browser-fixture.php'], { cwd: root, env, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  const line = result.split('\n').find(line => line.startsWith('TC_COMMUNITY_BROWSER:'));
  if (!line) throw new Error(`Fixture ${mode} returned no structured result.`);
  return JSON.parse(line.slice('TC_COMMUNITY_BROWSER:'.length));
}
async function visit(page, route, width) {
  const response = await page.goto(new URL(route, origin).href, { waitUntil: 'networkidle' });
  check(response.status() === 200, `${route}/${width}: HTTP200`);
  await page.evaluate(() => document.fonts.ready);
  const body = await page.locator('body').innerText();
  check(!/Fatal error|Warning:|Deprecated:|critical error|Uncaught Error/.test(body), `${route}/${width}: no PHP errors`);
  check(await page.locator('main h1').count() === 1, `${route}/${width}: one main heading`);
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route}/${width}: no horizontal overflow`);
  check(!body.includes('LOCAL_QA_PRIVATE_VALUES_MUST_NOT_RENDER'), `${route}/${width}: protected config content stays private`);
  report.views.push({ route, width });
  return body;
}
async function screenshot(page, name, element) {
  const file = path.join(output, name); await (element || page).screenshot({ path: file, ...(element ? {} : { fullPage: true }), animations: 'disabled' }); report.screenshots.push(name);
}
(async () => {
  await mkdir(output, { recursive: true });
  let browser, data;
  try {
    data = fixture('setup');
    check(data.fixture_active && data.config.cta.action === 'login', 'The marked local fixture retains the genuine unauthenticated download CTA');
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    const requests = [];
    await context.route('**/*', route => { const url = new URL(route.request().url()); requests.push(url.href); return url.origin === origin ? route.continue() : route.abort(); });
    const page = await context.newPage(); page.on('pageerror', error => report.errors.push(error.message));
    for (const width of [320, 390, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const route of ['/', '/reviews/', '/video-showcase/', '/configs/', new URL(data.config.url).pathname]) {
        const body = await visit(page, route, width);
        if (route === '/' || route === '/reviews/') {
          const cards = page.locator('.tc-review-card');
          check(await cards.count() === 1, `${route}/${width}: only the eligible imported QA message is visible`);
          check((await cards.innerText()).includes(data.reviews.valid.title), `${route}/${width}: source author is retained`);
          check((await cards.locator('.tc-review-quote').innerText()).includes(data.text), `${route}/${width}: HTML and shortcode-shaped source text remain literal`);
          check(await cards.locator('img, script, form, .gallery, .tc-review-stars').count() === 0, `${route}/${width}: quoted markup/shortcodes do not execute and stars are not invented`);
          check(await page.evaluate(() => typeof window.tcCommunityExecuted === 'undefined'), `${route}/${width}: injected source script never executes`);
          check(await cards.locator('.tc-review-source a').getAttribute('href') === data.reviews.valid.source, `${route}/${width}: original Discord-shaped source link retained`);
          check(await cards.locator('time').getAttribute('datetime') === '2026-10-31T12:34:56+00:00' && (await cards.locator('time').innerText()) === '2026-10-31', `${route}/${width}: original message date retained`);
          for (const text of ['LOCAL QA demo must never render', 'LOCAL QA hidden message', 'LOCAL QA edited message', 'LOCAL QA invalid message', 'Sample Review', 'Demo Content']) check(!body.includes(text), `${route}/${width}: excluded review text absent: ${text}`);
          const forbiddenSchema = await page.locator('script[type="application/ld+json"]').evaluateAll(scripts => scripts.some(script => /"@type"\s*:\s*"(?:Review|AggregateRating)"/.test(script.textContent)));
          check(!forbiddenSchema, `${route}/${width}: no fabricated review/rating schema`);
          if (route === '/reviews/' && width !== 320) await screenshot(page, `community-reviews-${width}.png`);
        }
        if (route === '/' || route === '/video-showcase/') {
          const cards = page.locator('.tc-video-card'); check(await cards.count() === 3, `${route}/${width}: three actual bundled videos`);
          for (let index = 0; index < data.videos.length; index++) {
            const card = cards.nth(index); const video = card.locator('video'); const expected = data.videos[index];
            check(await card.locator('h3').innerText() === expected.title, `${route}/${width}: inspected footage title ${index + 1}`);
            check(await card.locator('.tc-video-description').innerText() === expected.description, `${route}/${width}: public footage description ${index + 1}`);
            check(await video.getAttribute('poster') === expected.poster && await video.getAttribute('alt') === null, `${route}/${width}: native poster uses a valid video element ${index + 1}`);
            const accessibility = await video.evaluate(element => ({ refs: (element.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean).map(id => document.getElementById(id)?.textContent || ''), src: element.getAttribute('src'), preload: element.preload }));
            check(accessibility.refs.includes(expected.description) && accessibility.refs.includes(`Video poster: ${expected.poster_alt}`), `${route}/${width}: native player references its visible copy and descriptive poster alternative ${index + 1}`);
            check(accessibility.src === null && accessibility.preload === 'none', `${route}/${width}: movie bytes are not loaded before visitor intent ${index + 1}`);
            const size = await page.evaluate(url => new Promise((resolve, reject) => { const image = new Image(); image.onload = () => resolve({ width: image.naturalWidth, height: image.naturalHeight }); image.onerror = reject; image.src = url; }), expected.poster);
            check(size.width === expected.poster_width && size.height === expected.poster_height && size.width > 0, `${route}/${width}: actual poster dimensions match measured metadata ${index + 1}`);
          }
          check(await page.locator('iframe[src*="youtube"]').count() === 0, `${route}/${width}: no automatic YouTube frame`);
          if (route === '/video-showcase/' && width !== 320) await screenshot(page, `community-videos-${width}.png`);
        }
        if (route === '/configs/') {
          const card = page.locator(`article.post-${data.config.id}`); check(await card.count() === 1, `${route}/${width}: marked public release appears in the real catalogue`);
          const details = card.locator('.tc-config-card__details'); const overlay = card.locator('.tc-config-card__overlay-link');
          check(await details.getAttribute('href') === data.config.url && /^\/config\//.test(new URL(data.config.url).pathname), `${route}/${width}: details use the real public release URL`);
          check(await overlay.getAttribute('href') === data.config.cta.url, `${route}/${width}: protected CTA target remains authoritative`);
          await details.scrollIntoViewIfNeeded();
          check(await details.evaluate(element => { const box = element.getBoundingClientRect(); return document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2)?.closest('a') === element; }), `${route}/${width}: details are clickable above the protected card overlay`);
          check(await overlay.evaluate(element => { const visual = element.closest('article').querySelector('.tc-config-card__visual').getBoundingClientRect(); return document.elementFromPoint(visual.x + visual.width / 2, visual.y + visual.height / 2)?.closest('a') === element; }), `${route}/${width}: original card overlay remains clickable away from the details link`);
          await details.focus(); await page.keyboard.press('Enter'); await page.waitForURL(data.config.url);
          check((await page.locator('main h1').innerText()) === data.config.title, `${route}/${width}: keyboard details navigation reaches the actual release page`);
          if (width !== 320) await screenshot(page, `community-release-${width}.png`);
        }
      }
    }
    report.youtubeRequestsBeforeClick = requests.filter(url => /youtube|ytimg|googlevideo/.test(new URL(url).hostname)).length;
    check(report.youtubeRequestsBeforeClick === 0, 'Bundled video cards make no YouTube requests before playback');
    await page.setViewportSize({ width: 1440, height: 1000 }); await visit(page, '/video-showcase/', 1440);
    const first = page.locator('[data-inline-video]').first(); await first.locator('[data-video-play]').click();
    await page.waitForFunction(() => { const video = document.querySelector('[data-inline-video] video'); return video.readyState >= 2 && video.currentTime > 0 && !video.paused; }, null, { timeout: 15000 });
    check(await first.locator('video').evaluate(element => !element.error && element.currentSrc.includes('apex-demo-01.mp4')), 'Visitor playback starts the actual first-party MP4');
    await first.locator('video').evaluate(element => element.pause());
    fixture('empty');
    for (const width of [320, 390, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const route of ['/', '/reviews/']) {
        await visit(page, route, width);
        check(await page.locator('.tc-review-card').count() === 0, `${route}/${width}: empty state invents no fallback testimonials`);
        check(await page.locator('.tc-reviews-empty').innerText() === 'No community reviews have been published here yet.', `${route}/${width}: empty state describes missing website reviews`);
        check(await page.locator('.tc-reviews-empty a').count() === 0, `${route}/${width}: guests are not redirected to Discord instead of reading reviews`);
      }
    }
    check(report.errors.length === 0, 'No browser runtime errors'); report.passed = true;
  } catch (error) { report.failure = error.message; process.exitCode = 1; }
  finally {
    if (browser) await browser.close();
    if (data) report.restored = Boolean(fixture('restore').restored);
    await writeFile(path.join(output, 'community-media-browser.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, restored: report.restored, assertions: report.assertions, views: report.views.length, youtubeRequestsBeforeClick: report.youtubeRequestsBeforeClick, errors: report.errors, failure: report.failure }));
  }
})();
