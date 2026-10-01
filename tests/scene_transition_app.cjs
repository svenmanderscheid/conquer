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
    await page.goto(new URL('?zugang=login', page.url()).href);
    await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');
    await page.locator('[name="password"]').fill('PreviewFixture!2026');
    await Promise.all([page.waitForURL('**/city'), page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
    await page.locator('#navigation [data-id="world"]').waitFor();
    await page.evaluate(() => {
      window.__sceneTrace = [];
      const veil = document.querySelector('#scene-transition');
      new MutationObserver(() => window.__sceneTrace.push({at:performance.now(),veil:veil.className,body:document.body.className})).observe(veil,{attributes:true,attributeFilter:['class']});
    });
    return page;
  }

  async function waitForTransition(page, label) {
    // First-mount rendering and two animation frames run between the timers.
    // Inspect completion with a bounded wait instead of assuming a 1150 ms wall clock.
    await page.waitForFunction(() => !document.querySelector('#scene-transition').classList.contains('is-active'), null, {timeout:3000});
    const trace = await page.evaluate(() => window.__sceneTrace);
    if (!trace.some(row => row.veil.includes('is-revealing'))) throw new Error(`${label}: reveal phase missing`);
    console.log(`${label}: ${JSON.stringify(trace)}`);
  }

  for (const [width, height] of [[1280, 800], [390, 844], [320, 568], [844, 390], [568, 320]]) {
    const page = await openGame(width, height);
    await page.locator('.painted-village').waitFor();
    await page.evaluate(() => { window.__qaPaintedCity = document.querySelector('.painted-village'); });
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
    await waitForTransition(page, `${width}x${height} first world mount`);
    await page.evaluate(() => { window.__sceneTrace = []; });
    await page.locator('#navigation [data-id="city"]').click();
    await page.waitForTimeout(400);
    if (!await page.locator('body').evaluate(node => node.classList.contains('city-mode'))) {
      throw new Error(`${width}x${height}: village did not return`);
    }
    await page.locator('.playfield-scene-city.is-active .painted-village').waitFor();
    if (!await page.evaluate(() => window.__qaPaintedCity === document.querySelector('.painted-village'))) {
      throw new Error(`${width}x${height}: painted city was rebuilt instead of reused`);
    }
    await page.evaluate(() => { window.__qaWorldScene = document.querySelector('.atlas-shell'); });
    await waitForTransition(page, `${width}x${height} return city`);
    await page.evaluate(() => { window.__sceneTrace = []; });
    await page.locator('#navigation [data-id="world"]').click();
    await waitForTransition(page, `${width}x${height} cached world`);
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
