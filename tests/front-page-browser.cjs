/** Regression coverage for WordPress Reading settings; mutates only disposable localhost:8080. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';
const origin = new URL(baseURL);
if (origin.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(origin.hostname) || origin.port !== '8080' || origin.pathname !== '/' || origin.search || origin.hash || origin.username || origin.password) {
  throw new Error('Front-page mutations are restricted to the disposable http://127.0.0.1:8080 or http://localhost:8080 WordPress.');
}
const token = randomBytes(8).toString('hex');
const postMarker = `LOCAL FRONT PAGE POST ${token}`;
const authoredMarker = `Authored storefront ${token}`;
const artifacts = path.join(root, 'artifacts');
const report = { passed: false, checks: [], restored: false, screenshots: [] };

function phpData(value) {
  return `json_decode(base64_decode('${Buffer.from(JSON.stringify(value)).toString('base64')}'), true)`;
}
function wpEval(source) {
  const output = execFileSync('docker', ['compose', '-f', 'docker-compose.local.yml', 'run', '--rm', '-T', 'cli', 'wp', 'eval-file', '-'], {
    cwd: root, input: `<?php\n${source}\n`, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  const line = output.split('\n').find(item => item.startsWith('TC_FRONT_PAGE_TEST_JSON:'));
  if (!line) throw new Error('WordPress fixture command did not return its structured result.');
  return JSON.parse(line.slice('TC_FRONT_PAGE_TEST_JSON:'.length));
}
function setReading(mode, frontID, blogID, activePlugins) {
  const reading = { show_on_front: mode, page_on_front: frontID, page_for_posts: blogID };
  if (activePlugins !== undefined) reading.active_plugins = activePlugins;
  wpEval(`foreach (${phpData(reading)} as $name => $value) { update_option($name, $value); }
echo 'TC_FRONT_PAGE_TEST_JSON:' . wp_json_encode(array('updated' => true));`);
}

(async () => {
  let state;
  let browser;
  const errors = [];
  try {
    state = wpEval(`
if ('timonconfigs' !== get_stylesheet()) { throw new RuntimeException('Activate TimonConfigs before running this local test.'); }
$snapshot = array();
foreach (array('show_on_front', 'page_on_front', 'page_for_posts', 'active_plugins') as $name) {
  $value = get_option($name, null);
  $snapshot[$name] = array('exists' => null !== $value, 'value' => $value);
}
if (!is_array($snapshot['active_plugins']['value'])) { throw new RuntimeException('Expected the standard plugin option array.'); }
$ids = array();
try {
  $definitions = ${phpData({
    authored: { post_type: 'page', post_title: 'Local authored Home fixture', post_content: `<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Authored Home</h1><!-- /wp:heading --><!-- wp:paragraph --><p>${authoredMarker}</p><!-- /wp:paragraph -->` },
    empty: { post_type: 'page', post_title: 'Local empty Home fixture', post_content: '' },
    blog: { post_type: 'page', post_title: 'Local Blog fixture', post_content: '<p>A Blog page uses the posts index.</p>' },
    post: { post_type: 'post', post_title: postMarker, post_content: `<!-- wp:paragraph --><p>${postMarker}</p><!-- /wp:paragraph -->` },
  })};
  foreach ($definitions as $key => $definition) {
    $id = wp_insert_post(array_merge($definition, array('post_status' => 'publish', 'post_name' => 'tc-front-test-${token}-' . $key, 'meta_input' => array('_tc_front_page_test' => '${token}'))), true);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    $ids[$key] = (int) $id;
  }
  echo 'TC_FRONT_PAGE_TEST_JSON:' . wp_json_encode(array('snapshot' => $snapshot, 'ids' => $ids, 'blogURL' => get_permalink($ids['blog'])));
} catch (Throwable $error) {
  foreach ($ids as $id) { wp_delete_post($id, true); }
  throw $error;
}`);
    await mkdir(artifacts, { recursive: true });
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });

    async function verify(name, route, premium, width, markerExpected = null, capture = false) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      page.on('pageerror', error => errors.push(`${name}: ${error.message}`));
      page.on('console', message => { if (message.type() === 'error') errors.push(`${name}: ${message.text()}`); });
      try {
        const response = await page.goto(new URL(route, baseURL).href, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, `${name}: HTTP 200 at ${width}px`);
        const body = await page.locator('body').innerText();
        assert.equal(/(?:Fatal error|Warning|Deprecated|Notice):[\s\S]{0,220}(?:on line|\.php)/.test(body), false, `${name}: no PHP diagnostics`);
        assert.equal(await page.locator('h1').count(), 1, `${name}: one H1 at ${width}px`);
        assert.equal(await page.locator('.tc-hero').count(), premium ? 1 : 0, `${name}: correct Hero visibility at ${width}px`);
        assert.equal(await page.locator('.tc-price-card').count(), premium ? 3 : 0, `${name}: correct plan visibility at ${width}px`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${name}: no horizontal overflow`);
        if (markerExpected) assert.ok(body.includes(markerExpected), `${name}: authored content is visible`);
        if (premium || name === 'authored-static') assert.equal(body.includes(postMarker), false, `${name}: blog content does not replace the storefront`);
        if (capture) {
          await page.evaluate(() => document.fonts.ready);
          await page.locator('.tc-hero__art').evaluate(image => image.decode());
          const filename = `front-page-posts-${width}-after.png`;
          await page.screenshot({ path: path.join(artifacts, filename), fullPage: true });
          report.screenshots.push(filename);
        }
        report.checks.push({ mode: name, width, http: response.status(), h1: 1, premium, markerExpected: !!markerExpected });
      } finally { await page.close(); }
    }

    setReading('posts', 0, 0);
    for (const width of [1440, 390]) await verify('latest-posts', '/', true, width, null, true);

    setReading('page', state.ids.authored, 0);
    for (const width of [1440, 390]) await verify('authored-static', '/', false, width, authoredMarker);

    setReading('page', state.ids.empty, 0);
    for (const width of [1440, 390]) await verify('empty-static', '/', true, width);

    setReading('page', state.ids.empty, state.ids.blog);
    const blogPath = new URL(state.blogURL).pathname;
    for (const width of [1440, 390]) await verify('separate-blog', blogPath, false, width, postMarker);

    const pluginsWithoutCore = state.snapshot.active_plugins.value.filter(plugin => plugin !== 'timonconfigs-core/timonconfigs-core.php');
    setReading('posts', 0, 0, pluginsWithoutCore);
    for (const width of [1440, 390]) await verify('latest-posts-without-core', '/', true, width);
    assert.deepEqual(errors, [], 'No frontend console or page errors');
    report.passed = true;
  } catch (error) {
    report.error = error.message;
    throw error;
  } finally {
    try {
      if (browser) await browser.close();
    } finally {
      if (state) {
        const restored = wpEval(`
$snapshot = ${phpData(state.snapshot)};
foreach ($snapshot as $name => $entry) {
  if ($entry['exists']) { update_option($name, $entry['value']); } else { delete_option($name); }
}
foreach (${phpData(Object.values(state.ids))} as $id) { wp_delete_post((int) $id, true); }
foreach ($snapshot as $name => $entry) {
  $actual = get_option($name, null);
  $expected = $entry['exists'] ? $entry['value'] : null;
  // WordPress sanitizes page IDs to integers in the update cache; SQL stores strings.
  $same = in_array($name, array('page_on_front', 'page_for_posts'), true) && $entry['exists'] ? (string) $actual === (string) $expected : $actual === $expected;
  if (!$same) { throw new RuntimeException('Failed to restore option ' . $name); }
}
$remaining = get_posts(array('post_type' => array('page', 'post'), 'post_status' => array_values(get_post_stati()), 'meta_key' => '_tc_front_page_test', 'meta_value' => '${token}', 'posts_per_page' => -1, 'fields' => 'ids'));
if ($remaining) { throw new RuntimeException('Front-page fixture cleanup failed.'); }
echo 'TC_FRONT_PAGE_TEST_JSON:' . wp_json_encode(array('restored' => true));`);
        report.restored = restored.restored === true;
      }
      await mkdir(artifacts, { recursive: true });
      await writeFile(path.join(artifacts, 'front-page-checks.json'), JSON.stringify(report, null, 2));
    }
  }
  console.log(JSON.stringify(report, null, 2));
})().catch(error => { console.error(error); process.exitCode = 1; });
