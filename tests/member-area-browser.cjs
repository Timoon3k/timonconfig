/** Read-only public/member UI checks; automatic login is limited to disposable local WordPress. */
const assert = require('node:assert/strict');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';
const origin = new URL(baseURL);
if (origin.username || origin.password) throw new Error('Never include credentials in the test URL.');
const directory = path.resolve(__dirname, '../artifacts');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const report = { passed: false, publicChecks: [], ownerChecks: [], headerChecks: [], errors: [] };
  try {
    await mkdir(directory, { recursive: true });
    for (const width of [1024, 1100, 1280, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.goto(new URL('/', baseURL).href, { waitUntil: 'domcontentloaded' });
      const fits = await page.evaluate(() => {
        const brand = document.querySelector('.tc-brand').getBoundingClientRect();
        const nav = document.querySelector('.tc-nav').getBoundingClientRect();
        return brand.right <= nav.left && nav.right <= innerWidth && document.documentElement.scrollWidth <= innerWidth;
      });
      assert.equal(fits, true, `Member login fits the public header at ${width}`);
      report.headerChecks.push({ width, fits });
      await page.close();
    }
    for (const width of [1440, 768, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => report.errors.push(error.message));
      for (const route of ['/account/', '/my-downloads/']) {
        const response = await page.goto(new URL(route, baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, `${route} responds at ${width}`);
        const headers = response.headers();
        assert.match(headers['cache-control'] || '', /private/);
        assert.match(headers['cache-control'] || '', /no-store/);
        assert.match(headers['x-robots-tag'] || '', /noindex/);
        assert.match(await page.locator('meta[name="robots"]').getAttribute('content'), /noindex/);
        assert.equal(await page.locator('h1').count(), 1);
        assert.equal(await page.locator('[data-member-area]').count(), 1);
        assert.equal(await page.getByRole('navigation', { name: 'Member navigation' }).count(), 1);
        assert.equal(await page.locator('.tc-member-login__card').count(), 1);
        assert.equal(await page.locator('.tc-member-login__brand img').evaluate(img => img.complete && img.naturalWidth > 0), true, 'The actual brand image loads');
        assert.equal(await page.locator('.tc-header-account').innerText(), 'Member login');
        assert.equal(new URL(await page.getByRole('link', { name: '← Back to homepage', exact: true }).getAttribute('href')).pathname, '/');
        const browse = page.getByRole('link', { name: 'Browse Memberships', exact: true }).first();
        assert.equal(new URL(await browse.getAttribute('href'), baseURL).pathname, '/pricing/');
        assert.equal(await page.getByRole('link', { name: 'Logout', exact: true }).count(), 0, 'Guests never receive member logout actions');
        assert.equal(await page.locator('.tc-member-details').count(), 0, 'Guests never receive profile identifiers');
        assert.equal(await page.locator('.tc-member-file-grid').count(), 0, 'Guests never receive private file listings');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const html = await page.content();
        assert.equal(/(?:Fatal error|Warning|Deprecated|Notice):[\s\S]{0,220}(?:on line|\.php)/.test(await page.locator('body').innerText()), false);
        assert.equal(/_tc_private_files|stored_name|TIMONCONFIGS_DISCORD_CLIENT_SECRET|access_token|refresh_token/.test(html), false, 'No private implementation metadata in guest HTML');
        const api = await page.request.get(new URL(`/wp-json/wp/v2/pages?slug=${route.split('/')[1]}&_fields=content`, baseURL).href);
        assert.equal(api.status(), 200);
        const entries = await api.json();
        assert.match(entries[0]?.content?.rendered || '', /Open your private member area/);
        assert.doesNotMatch(entries[0]?.content?.rendered || '', /Discord User ID|tc-member-details|tc-member-plan__status/);
        await page.screenshot({ path: path.join(directory, `member-${route.includes('my-downloads') ? 'downloads' : 'profile'}-guest-${width}.png`), fullPage: true });
        report.publicChecks.push({ route, width, status: 200, privateCache: true, noindex: true, noOwnerData: true });
      }
      await page.close();
    }

    if (origin.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(origin.hostname) && origin.port === '8080') {
      const owner = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
      owner.on('pageerror', error => report.errors.push(error.message));
      await owner.goto(new URL('/wp-login.php', baseURL).href);
      await owner.locator('#user_login').fill(process.env.TC_TEST_ADMIN_USER || 'timonadmin');
      await owner.locator('#user_pass').fill(process.env.TC_TEST_ADMIN_PASSWORD || 'local-preview-only');
      await Promise.all([owner.waitForURL(url => url.pathname.startsWith('/wp-admin/')), owner.locator('#wp-submit').click()]);
      for (const width of [1440, 768, 390]) {
        await owner.setViewportSize({ width, height: 1000 });
        for (const route of ['/account/?member_id=999999999', '/my-downloads/?member_id=999999999']) {
          const response = await owner.goto(new URL(route, baseURL).href, { waitUntil: 'networkidle' });
          assert.equal(response.status(), 200);
          assert.match(response.headers()['cache-control'] || '', /no-store/);
          assert.equal(await owner.locator('.tc-member-identity').count(), 1);
          assert.equal(await owner.locator('.tc-member-login__card').count(), 0);
          const isDownloads = route.startsWith('/my-downloads/');
          assert.equal(await owner.locator('.tc-member-quick__card').count(), isDownloads ? 0 : 4);
          if (!isDownloads) {
            const destinations = await owner.locator('.tc-member-quick__card').evaluateAll(links => links.map(link => new URL(link.href).pathname + new URL(link.href).hash));
            assert.deepEqual(destinations, ['/configs/', '/my-downloads/', '/account/#tc-member-profile', '/contact/']);
          }
          assert.equal(await owner.locator('.tc-member-plan').count(), 3);
          assert.equal(await owner.getByRole('link', { name: 'Logout', exact: true }).count(), 1);
          assert.equal(await owner.locator('.tc-member-state').innerText(), 'Verification required', 'The disposable admin is not granted a simulated VIP membership');
          assert.doesNotMatch(await owner.locator('[data-member-area]').innerText(), /999999999/);
          assert.equal(await owner.locator('.tc-member-file-grid').count(), 0, 'Unverified owners cannot list protected releases');
          assert.equal(await owner.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
          const schemas = await owner.locator('script[type="application/ld+json"]').allTextContents();
          assert.equal(/discord_id|Last Checked|tc-member-state/.test(schemas.join('\n')), false, 'Owner-specific membership details are absent from schema');
          if (!isDownloads) {
            await owner.locator('.tc-member-quick__card').nth(2).click();
            assert.equal(new URL(owner.url()).hash, '#tc-member-profile');
          }
          report.ownerChecks.push({ route, width, status: 200, state: 'unverified', ownerOnly: true });
          await owner.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
          await owner.screenshot({ path: path.join(directory, `member-${isDownloads ? 'downloads' : 'profile'}-owner-${width}.png`), fullPage: true });
        }
      }
      // Exercise the real logout route and prove server-side session invalidation.
      const oldCookies = await owner.context().cookies();
      assert.ok(oldCookies.some(cookie => cookie.name.startsWith('wordpress_logged_in_')));
      await Promise.all([
        owner.waitForURL(url => url.pathname === '/account/' && !url.search),
        owner.getByRole('link', { name: 'Logout', exact: true }).click(),
      ]);
      await owner.waitForLoadState('networkidle');
      assert.equal(await owner.locator('.tc-member-login__card').count(), 1, 'Logout returns the guest sign-in card');
      assert.equal(await owner.locator('.tc-member-identity').count(), 0, 'Logout removes the member profile');
      assert.equal(await owner.context().cookies().then(cookies => cookies.some(cookie => cookie.name.startsWith('wordpress_logged_in_'))), false, 'Login cookie is cleared');
      const afterLogout = await owner.reload({ waitUntil: 'networkidle' });
      assert.match(afterLogout.headers()['cache-control'] || '', /no-store/);
      assert.equal(await owner.locator('.tc-member-login__card').count(), 1, 'Reload stays signed out');
      const replayContext = await browser.newContext();
      try {
        await replayContext.addCookies(oldCookies);
        const replay = await replayContext.newPage();
        await replay.goto(new URL('/account/', baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(await replay.locator('.tc-member-login__card').count(), 1, 'Previously valid session cookie is rejected after logout');
        assert.equal(await replay.locator('.tc-member-identity').count(), 0, 'Replayed cookie cannot restore the profile');
      } finally { await replayContext.close(); }
      report.logoutChecks = { guestAfterLogout: true, guestAfterReload: true, cookieCleared: true, replayRejected: true };
      await owner.close();
    }
    assert.deepEqual(report.errors, []);
    report.passed = true;
    console.log(JSON.stringify({ passed: true, publicChecks: report.publicChecks.length, ownerChecks: report.ownerChecks.length, headerChecks: report.headerChecks.length }, null, 2));
  } finally {
    await browser.close();
    await mkdir(directory, { recursive: true });
    await writeFile(path.join(directory, 'member-area-checks.json'), JSON.stringify(report, null, 2));
  }
})().catch(error => { console.error(error.message.replaceAll(process.env.TC_TEST_ADMIN_PASSWORD || 'local-preview-only', '[redacted]')); process.exitCode = 1; });
