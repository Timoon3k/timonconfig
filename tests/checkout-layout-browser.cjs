/** Actual local Woo checkout + gateway hook layout; no real gateway or order submission. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const origin = 'http://127.0.0.1:8080';
const token = randomBytes(24).toString('hex');
const mu = `/var/www/html/wp-content/mu-plugins/tc-checkout-layout-${token}.php`;
const baseline = process.env.TC_LAYOUT_BASELINE === '1';
const report = { passed: false, baseline, cases: [], assertions: 0, errors: [], restored: false, externalRequestsBlocked: 0 };
function docker(args, input) {
  const env = { ...process.env }; for (const name of ['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH']) delete env[name];
  return execFileSync('docker', ['--host=unix:///var/run/docker.sock', ...args], { cwd: root, env, input, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
}
function check(value, message) { report.assertions++; assert.ok(value, message); }
async function geometry(page) {
  return page.evaluate(() => {
    const box = selector => { const e = document.querySelector(selector); return e ? e.getBoundingClientRect().toJSON() : null; };
    return { width: innerWidth, scrollWidth: document.documentElement.scrollWidth, form: box('form.checkout'), grid: box('.tc-vip-checkout__grid'), customer: box('#customer_details'), summary: box('.tc-vip-checkout__summary'), express: box('#wc-stripe-express-checkout-element'), button: box('#place_order'), payment: box('#payment'), heading: box('.tc-vip-checkout__heading'), hook: box('.tc-layout-hook-notice'), after: box('.tc-layout-after-notice'), shell: box('.tc-vip-checkout__shell') };
  });
}
(async () => {
  await fs.mkdir(path.join(root, 'artifacts'), { recursive: true });
  let browser;
  try {
    const source = await fs.readFile(path.join(root, 'tests/checkout-layout-fixture.php'), 'utf8');
    const fixture = source.replace('<?php', `<?php\ndefine('TC_CHECKOUT_LAYOUT_FIXTURE_TOKEN', '${token}');`);
    docker(['exec', '-i', '--user', 'root', 'timonconfigs-wordpress', 'sh', '-c', `mkdir -p /var/www/html/wp-content/mu-plugins && cat > ${mu} && chmod 644 ${mu}`], fixture);
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ extraHTTPHeaders: { 'X-TC-Checkout-Layout': token }, reducedMotion: 'reduce' });
    await context.route('**/*', route => {
      const request = route.request(); const url = new URL(request.url());
      if (url.origin !== origin) { report.externalRequestsBlocked++; return route.abort(); }
      if (request.method() === 'POST' && url.searchParams.get('wc-ajax') !== 'update_order_review') return route.abort();
      return route.continue();
    });
    const page = await context.newPage(); page.on('pageerror', error => report.errors.push(error.message));
    for (const width of (baseline ? [1440] : [320, 390, 768, 820, 1024, 1440, 1920, 2560, 3840])) {
      await page.setViewportSize({ width, height: 1080 });
      for (const mode of (baseline ? ['stripe'] : ['none', 'empty', 'stripe', 'all'])) {
        const response = await page.goto(`${origin}/checkout/?tc_layout_hooks=${mode}`, { waitUntil: 'networkidle' });
        const body = await page.locator('body').innerText();
        await fs.writeFile(path.join(root, 'artifacts/checkout-layout-last.html'), await page.content());
        check(response.status() === 200, `${width}/${mode}: HTTP200`);
        check(!/Fatal error|Warning:|Deprecated:|Uncaught Error|critical error/.test(body), `${width}/${mode}: no PHP diagnostics`);
        check(await page.locator('form.checkout').count() === 1, `${width}/${mode}: real Woo checkout form`);
        check(await page.locator('form.checkout form').count() === 0, `${width}/${mode}: no nested checkout forms`);
        check(await page.locator('#customer_details').count() === 1 && await page.locator('#payment').count() === 1, `${width}/${mode}: real billing/payment panels`);
        await page.evaluate(() => document.fonts.ready);
        const g = await geometry(page); report.cases.push({ width, mode, geometry: g });
        if (baseline) {
          await page.screenshot({ path: path.join(root, 'artifacts/checkout-desktop-before.png'), fullPage: true });
          report.reproduced = g.customer.x > g.summary.x + 50 && g.summary.y > g.customer.y + 100;
          check(report.reproduced, 'Baseline reproduces customer-right/payment-below-left displacement');
          continue;
        }
        check(g.scrollWidth <= width, `${width}/${mode}: no horizontal overflow`);
        if (width > 820) {
          check(Math.abs(g.customer.y - g.summary.y) <= 2, `${width}/${mode}: panel tops align`);
          check(g.customer.right < g.summary.left + 1, `${width}/${mode}: billing left, payment right`);
          check(g.customer.width > 300 && g.summary.width > 300, `${width}/${mode}: both panels remain usable`);
        } else {
          check(g.summary.y >= g.customer.bottom - 1, `${width}/${mode}: payment follows billing on mobile`);
          check(Math.abs(g.customer.left - g.summary.left) <= 2, `${width}/${mode}: mobile panels align`);
        }
        check(Math.abs(g.shell.left - (width - g.shell.width) / 2) <= 2, `${width}/${mode}: checkout shell is centered`);
        check(g.shell.width <= 1191 && g.shell.width >= Math.min(width - 120, 1000), `${width}/${mode}: sane centered content width`);
        check(g.button.left >= g.payment.left && g.button.right <= g.payment.right + 1, `${width}/${mode}: pay button stays in payment panel`);
        const amountsFit = await page.locator('.woocommerce-checkout-review-order-table .woocommerce-Price-amount').evaluateAll(amounts => amounts.length > 0 && amounts.every(amount => {
          const range = document.createRange(); range.selectNodeContents(amount);
          const rectangles = Array.from(range.getClientRects()).filter(rectangle => rectangle.width > 0 && rectangle.height > 0);
          return rectangles.length > 0 && Math.max(...rectangles.map(rectangle => rectangle.top)) - Math.min(...rectangles.map(rectangle => rectangle.top)) <= 2;
        }));
        check(amountsFit, `${width}/${mode}: each order amount stays on one visible line`);
        const fieldsFit = await page.locator('#payment input.input-text').evaluateAll(fields => fields.every(field => {
          const box = field.getBoundingClientRect(); const payment = document.querySelector('#payment').getBoundingClientRect();
          return box.left >= payment.left && box.right <= payment.right + 1;
        }));
        check(fieldsFit, `${width}/${mode}: native card fields stay inside payment panel`);
        if (mode !== 'none') check(g.express.bottom <= Math.min(g.customer.top, g.summary.top) + 1, `${width}/${mode}: express hook stays above panels`);
        if (mode === 'all') {
          check(g.hook.bottom <= Math.min(g.customer.top, g.summary.top) + 1, `${width}/${mode}: arbitrary before hook stays above panels`);
          check(g.after.left >= g.customer.left && g.after.right <= g.customer.right + 1, `${width}/${mode}: after-customer hook follows billing column`);
          check(await page.evaluate(() => {
            const nodes = ['#wc-stripe-express-checkout-element', '.tc-layout-hook-notice', '#customer_details', '.tc-layout-after-notice', '.tc-vip-checkout__summary'].map(selector => document.querySelector(selector));
            return nodes.every((node, index) => index === 0 || (nodes[index - 1].compareDocumentPosition(node) & Node.DOCUMENT_POSITION_FOLLOWING));
          }), `${width}/${mode}: checkout hook order remains unchanged`);
        }
        if (mode === 'stripe' && [390, 1440, 1920].includes(width)) {
          await page.screenshot({ path: path.join(root, `artifacts/checkout-layout-${width}.png`), fullPage: true });
        }
      }
    }
    if (!baseline) {
      await page.setViewportSize({ width: 1440, height: 1080 });
      await page.goto(`${origin}/checkout/?tc_layout_hooks=all`, { waitUntil: 'networkidle' });
      const responsePromise = page.waitForResponse(response => new URL(response.url()).searchParams.get('wc-ajax') === 'update_order_review');
      await page.locator('#payment_method_tc_layout_paypal').check();
      await page.evaluate(() => { window.tcLayoutUpdated = false; jQuery(document.body).one('updated_checkout', () => { window.tcLayoutUpdated = true; }); jQuery(document.body).trigger('update_checkout'); });
      const update = await responsePromise; const json = await update.json();
      check(update.status() === 200 && json.result === 'success', 'Native Woo update_order_review succeeds without an order');
      await page.waitForFunction(() => window.tcLayoutUpdated === true);
      await page.waitForFunction(() => !document.querySelector('.blockUI'));
      check(await page.locator('#payment_method_tc_layout_paypal').isChecked(), 'Native AJAX preserves the selected synthetic payment method');
      const g = await geometry(page); check(Math.abs(g.customer.y - g.summary.y) <= 2, 'Panels remain aligned after native order-review fragments refresh');
    }
    check(report.errors.length === 0, 'No browser runtime errors');
    report.passed = true;
  } catch (error) { report.failure = error.message; process.exitCode = 1; }
  finally {
    if (browser) await browser.close();
    docker(['exec', '--user', 'root', 'timonconfigs-wordpress', 'rm', '-f', mu]);
    report.restored = true;
    await fs.writeFile(path.join(root, `artifacts/checkout-layout-${baseline ? 'baseline' : 'browser'}.json`), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed, baseline, reproduced: report.reproduced, assertions: report.assertions, cases: report.cases.length, restored: report.restored, failure: report.failure, errors: report.errors }));
  }
})();
