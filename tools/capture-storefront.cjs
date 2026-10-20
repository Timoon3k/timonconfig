/** Reproducible visual evidence: node tools/capture-storefront.cjs */
const { chromium } = require('playwright');
const { mkdir, writeFile } = require('node:fs/promises');
const path = require('node:path');

(async () => {
  const output = path.resolve(__dirname, '../artifacts');
  const baseURL = process.env.TC_TEST_URL || 'http://127.0.0.1:8080';
  await mkdir(output, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const reports = [];
  try {
    for (const width of [1440, 1024, 768, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, deviceScaleFactor: 1 });
      await page.goto(baseURL, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts.ready);
      await page.locator('.tc-hero__art').evaluate(img => img.decode());
      await page.screenshot({ path: path.join(output, `redesign-${width}.png`), fullPage: true });
      if (width === 390 || width === 1440) {
        await page.screenshot({ path: path.join(output, `hero-${width}.png`), fullPage: false });
      }
      reports.push(await page.evaluate(() => ({
        width: innerWidth,
        pageHeight: document.documentElement.scrollHeight,
        horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
        interLoaded: document.fonts.check('900 16px Inter'),
        sections: [...document.querySelectorAll('header.tc-header,main>section,.tc-assurance-strip,footer.tc-footer')].map(el => ({
          section: el.className,
          y: Math.round(el.getBoundingClientRect().top + scrollY),
          height: Math.round(el.getBoundingClientRect().height),
          width: Math.round(el.getBoundingClientRect().width),
        })),
      })));
      await page.close();
    }
    await writeFile(path.join(output, 'storefront-geometry.json'), JSON.stringify(reports, null, 2));
    console.log(JSON.stringify(reports.map(({ width, pageHeight, horizontalOverflow, interLoaded }) => ({ width, pageHeight, horizontalOverflow, interLoaded }))));
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
