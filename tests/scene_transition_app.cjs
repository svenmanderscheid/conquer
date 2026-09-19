const { chromium } = require('playwright');

(async () => {
  const base = process.env.BASE_URL || 'http://127.0.0.1:18988';
  const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
  const errors = [];

  async function openGame(width, height, reducedMotion = 'no-preference') {
    const page = await browser.newPage({ viewport: { width, height }, reducedMotion });
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.locator('[data-mode="login"]').click();
    await page.locator('[name="username"]').fill('PreviewPlayer');
    await page.locator('[name="password"]').fill('PreviewFixture!2026');
    await Promise.all([page.waitForURL('**/city'), page.locator('#auth-submit').click()]);
    await page.locator('#navigation [data-id="world"]').waitFor();
    return page;
  }

  for (const [width, height] of [[1280, 800], [390, 844], [844, 390]]) {
    const page = await openGame(width, height);
    await page.evaluate(() => { window.__qaCityFrame = document.querySelector('#city-frame'); });
    await page.locator('#navigation [data-id="world"]').click();
    await page.waitForTimeout(50);
    if (!await page.locator('#scene-transition').evaluate(node => node.classList.contains('is-active'))) {
      const debug = await page.evaluate(() => ({ body: document.body.className, veil: document.querySelector('#scene-transition')?.className, reduced: matchMedia('(prefers-reduced-motion: reduce)').matches, hash: location.hash }));
      throw new Error(`${width}x${height}: transition was not visible: ${JSON.stringify(debug)}`);
    }
    if (process.env.SHOT_DIR) await page.screenshot({ path: `${process.env.SHOT_DIR}\\scene-transition-${width}x${height}.png` });
    await page.waitForTimeout(300);
    if (!await page.locator('body').evaluate(node => node.classList.contains('world-mode'))) {
      throw new Error(`${width}x${height}: world map was not mounted at the transition midpoint`);
    }
    await page.waitForTimeout(800);
    if (await page.locator('#scene-transition').evaluate(node => node.classList.contains('is-active'))) {
      throw new Error(`${width}x${height}: transition remained active`);
    }
    await page.locator('#navigation [data-id="city"]').click();
    await page.waitForTimeout(400);
    if (!await page.locator('body').evaluate(node => node.classList.contains('city-mode'))) {
      throw new Error(`${width}x${height}: village did not return`);
    }
    await page.locator('#city-frame').waitFor();
    if (!await page.evaluate(() => window.__qaCityFrame === document.querySelector('#city-frame'))) {
      throw new Error(`${width}x${height}: city iframe was reloaded instead of reused`);
    }
    await page.evaluate(() => { window.__qaWorldScene = document.querySelector('.atlas-shell'); });
    await page.waitForTimeout(750);
    await page.locator('#navigation [data-id="world"]').click();
    await page.waitForTimeout(1150);
    if (!await page.evaluate(() => window.__qaWorldScene === document.querySelector('.atlas-shell'))) {
      throw new Error(`${width}x${height}: world map was rebuilt instead of reused`);
    }
    await page.close();
  }

  const reduced = await openGame(390, 844, 'reduce');
  await reduced.locator('#navigation [data-id="world"]').click();
  if (await reduced.locator('#scene-transition').evaluate(node => node.classList.contains('is-active'))) {
    throw new Error('reduced motion still animated the scene change');
  }
  if (!await reduced.locator('body').evaluate(node => node.classList.contains('world-mode'))) {
    throw new Error('reduced motion did not switch scenes immediately');
  }
  await reduced.close();

  await browser.close();
  if (errors.length) throw new Error(`Browser errors:\n${errors.join('\n')}`);
  console.log('Scene transition passed for desktop, portrait, landscape, and reduced motion.');
})().catch(error => { console.error(error); process.exit(1); });
