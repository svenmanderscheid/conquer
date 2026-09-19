'use strict';
// End-to-end appearance audit against an automatically disposed synthetic account/database.
// Run: node tests/fantasy_theme_app.cjs (PLAYWRIGHT_MODULE may point to a bundled runtime).
const fs = require('fs'), path = require('path'), net = require('net'), assert = require('assert');
const { spawn } = require('child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const textContrast = require('./fixtures/menu_text_contrast.cjs');
const root = path.resolve(__dirname, '..');
const output = path.join(root, 'artifacts', 'fantasy-theme');
fs.mkdirSync(output, { recursive: true });

async function startFixture() {
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => { const port = socket.address().port; socket.close(() => resolve(port)); });
  });
  const child = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
    path.join(root, 'tools/preview-feature-fixture.php'), '--port=' + port,
    '--chat', '--hud', '--talents', '--training', '--hospital', '--inventory-overview', '--mailbox',
  ], { cwd: root, stdio: ['pipe', 'pipe', 'pipe'], windowsHide: true });
  let log = '';
  const ready = new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('Fixture startup timeout: ' + log)), 60000);
    child.stdout.on('data', chunk => { log += chunk; if (log.includes('Synthetic preview ready')) { clearTimeout(timeout); resolve(); } });
    child.stderr.on('data', chunk => { log += chunk; });
    child.once('error', error => { clearTimeout(timeout); reject(error); });
    child.once('exit', code => { clearTimeout(timeout); reject(new Error('Fixture stopped (' + code + '): ' + log)); });
  });
  return { child, ready, base: 'http://127.0.0.1:' + port };
}

async function actualFonts(page, selector) {
  const cdp = await page.context().newCDPSession(page);
  try {
    await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
    const { root: document } = await cdp.send('DOM.getDocument', { depth: 0 });
    const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: document.nodeId, selector });
    assert(nodeId, 'Font sample exists: ' + selector);
    return (await cdp.send('CSS.getPlatformFontsForNode', { nodeId })).fonts;
  } finally { await cdp.detach(); }
}

(async () => {
  const fixture = await startFixture();
  let browser;
  const report = [], errors = [], badAssets = [], badApis = [], appearanceFailures = [];
  try {
    await fixture.ready;
    browser = await chromium.launch({ headless: true, ...(process.env.BROWSER_EXECUTABLE_PATH ? { executablePath: process.env.BROWSER_EXECUTABLE_PATH } : {}) });
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, hasTouch: true });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.url().includes('/assets/') && response.status() >= 400) badAssets.push(response.status() + ' ' + response.url()); });
    page.on('response', response => { if (response.url().includes('/api/') && response.status() >= 400) badApis.push(response.status() + ' ' + new URL(response.url()).pathname); });
    await page.goto(fixture.base, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(output, 'welcome-desktop.png') });
    for (const route of ['', '/auth/recover']) {
      await page.goto(fixture.base + route, { waitUntil: 'networkidle' });
      for (const [width, height] of [[390, 844], [320, 568], [844, 390]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => document.fonts.ready);
        const submit = page.locator('form button');
        await submit.scrollIntoViewIfNeeded();
        const geometry = await submit.evaluate(element => {
          const rect = element.getBoundingClientRect();
          return { overflow: document.documentElement.scrollWidth - innerWidth,
            visible: rect.left >= 0 && rect.right <= innerWidth && rect.top >= 0 && rect.bottom <= innerHeight,
            height: rect.height };
        });
        assert(geometry.overflow <= 2 && geometry.visible && geometry.height >= 44, 'Authentication remains touch-accessible: ' + route + ' ' + width);
        assert.equal(await page.locator('.locale-install .button').evaluate(element => getComputedStyle(element).backgroundColor), 'rgb(92, 66, 112)', 'Installation action shares violet accent');
        report.push({ auth: route || 'welcome', width, height, ...geometry });
        await page.screenshot({ path: path.join(output, `${route ? 'recovery' : 'welcome'}-${width}x${height}.png`), fullPage: true });
      }
    }
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(fixture.base, { waitUntil: 'networkidle' });
    await page.locator('[data-mode="login"]').click();
    await page.locator('[name="username"]').fill('PreviewPlayer');
    await page.locator('[name="password"]').fill('PreviewFixture!2026');
    await Promise.all([page.waitForURL('**/city'), page.locator('#auth-submit').click()]);
    await page.waitForFunction(() => document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
    await page.locator('#resources .resource strong').first().waitFor();
    await page.evaluate(async () => { await new Promise(requestAnimationFrame); await document.fonts.ready; });
    const glyphs = await actualFonts(page, '#resources .resource strong');
    report.push({ fonts: glyphs, resourceFont: await page.locator('#resources .resource strong').first().evaluate(element => getComputedStyle(element).font) });
    assert(glyphs.some(font => font.familyName.includes('Lora') && font.isCustomFont), 'Actual resource digits render in self-hosted Lora');

    async function closeDialogs() {
      for (let i = 0; i < 4 && await page.locator('dialog[open]').count(); i++) await page.keyboard.press('Escape');
    }
    async function openPanel(id) {
      await closeDialogs();
      if (id === 'profile') await page.locator('#account-button').click();
      else if (['inventory', 'alliance', 'reports'].includes(id)) await page.locator('#navigation [data-action="tab"][data-id="' + id + '"]').click();
      else {
        await page.locator('#hud-menu').click();
        await page.locator('#game-dialog [data-action="dialog-tab"][data-id="' + id + '"]').click();
      }
      await page.locator('#panel-dialog[open]').waitFor();
      await page.waitForLoadState('networkidle');
      const ready = {
        mastery: '.talent-node', defense: '.defense-body', land: '.land-shell',
        dungeons: '.dungeon-shell[aria-busy="false"]', community: '.community-chat-log',
        events: '.progression-content', account: '.progression-content', worlds: '.world-selector',
      }[id];
      if (ready) await page.locator('#content ' + ready).first().waitFor();
      await page.evaluate(() => document.fonts.ready);
    }
    async function inspect(tag, save = false) {
      const data = await page.evaluate(() => {
        const dialog = document.querySelector('#panel-dialog[open]');
        const title = document.querySelector('#page-title');
        const heading = dialog.querySelector('.page-heading');
        const close = dialog.querySelector('.panel-close');
        const rect = dialog.getBoundingClientRect(), button = close.getBoundingClientRect();
        return {
          primary: getComputedStyle(heading).backgroundColor,
          surface: getComputedStyle(dialog).backgroundColor,
          font: getComputedStyle(title).fontFamily,
          overflow: dialog.scrollWidth - dialog.clientWidth,
          outside: rect.left < -2 || rect.right > innerWidth + 2 || rect.top < -2 || rect.bottom > innerHeight + 2,
          closeVisible: button.top >= 0 && button.left >= 0 && button.bottom <= innerHeight + 2 && button.right <= innerWidth + 2,
          title: title.textContent,
        };
      });
      report.push({ tag, ...data });
      const contrast = await textContrast(page, '#panel-dialog[open]');
      report.push({ contrast: tag, ...contrast });
      appearanceFailures.push(...contrast.failures.map(sample => `${tag}: ${sample.selector} "${sample.text}" contrast ${sample.ratio} < ${sample.minimum}`));
      if (data.primary !== 'rgb(92, 66, 112)') appearanceFailures.push(tag + ': violet header ' + data.primary);
      if (data.surface !== 'rgb(233, 223, 207)') appearanceFailures.push(tag + ': beige surface ' + data.surface);
      if (!data.font.includes('Conquer UI')) appearanceFailures.push(tag + ': fantasy typography ' + data.font);
      if (data.outside || !data.closeVisible) appearanceFailures.push(tag + ': unreachable window/close action');
      if (data.overflow > 2) appearanceFailures.push(tag + ': horizontal window overflow ' + data.overflow);
      if (save) await page.screenshot({ path: path.join(output, tag + '.png') });
    }
    const broad = ['profile', 'quests', 'army', 'research', 'inventory', 'treasures', 'mastery', 'market', 'defense', 'land', 'dungeons', 'expeditions', 'community', 'events', 'rankings', 'arena', 'settings', 'worlds', 'account', 'help', 'alliance', 'reports'];
    const sizes = [[1280, 800], [390, 844], [320, 568], [844, 390], [568, 320]];
    for (const [width, height] of sizes) {
      await closeDialogs();
      await page.setViewportSize({ width, height });
      await page.screenshot({ path: path.join(output, `city-${width}x${height}.png`) });
      for (const id of broad) {
        await openPanel(id);
        if (id === 'treasures') {
          const tile = page.locator('.treasury-card').first();
          const rect = await tile.boundingBox();
          assert(rect.width >= 44 && rect.height >= 44, 'Relics remain individually tappable: ' + width + 'x' + height);
          await page.waitForFunction(() => {
            const image = document.querySelector('.treasury-card img');
            return image?.complete && image.naturalWidth > 0;
          });
        }
        await inspect(`${id}-${width}x${height}`, true);
      }
    }
    const titleFonts = await actualFonts(page, '#page-title');
    assert(titleFonts.some(font => font.familyName.includes('Almendra') && font.isCustomFont), 'Actual menu title renders in self-hosted Almendra');
    report.push({ titleFonts });
    await closeDialogs();
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.locator('#navigation [data-action="tab"][data-id="world"]').click();
    await page.locator('.atlas-shell').waitFor();
    await page.screenshot({ path: path.join(output, 'world-desktop.png') });

    const admin = await context.newPage();
    admin.on('pageerror', error => errors.push(error.message));
    await admin.goto(fixture.base + '/admin/login');
    await admin.locator('[name="username"]').fill('PreviewAdmin');
    await admin.locator('[name="password"]').fill('PreviewFixture!2026');
    await Promise.all([admin.waitForURL(fixture.base + '/admin'), admin.getByRole('button', { name: 'Anmelden', exact: true }).click()]);
    for (const [width, height] of [[1280, 800], [390, 844]]) {
      await admin.setViewportSize({ width, height });
      await admin.evaluate(() => document.fonts.ready);
      assert((await admin.locator('body').evaluate(element => getComputedStyle(element).fontFamily)).includes('Conquer UI'), 'Backoffice shares fantasy font');
      assert.equal(await admin.locator('.topbar').evaluate(element => getComputedStyle(element).backgroundColor), 'rgb(92, 66, 112)', 'Backoffice header shares violet accent');
      assert.equal(await admin.locator('.quick-action>span').first().evaluate(element => getComputedStyle(element).color), 'rgb(92, 66, 112)', 'Backoffice links share violet accent');
      await admin.screenshot({ path: path.join(output, `admin-${width}x${height}.png`), fullPage: true });
    }
    report.push({ errors, badAssets, badApis, appearanceFailures });
    assert.deepEqual(errors, [], 'No browser errors');
    assert.deepEqual(badAssets, [], 'No missing assets');
    assert.deepEqual(badApis, [], 'All menu reads succeed');
    assert.deepEqual(appearanceFailures, [], 'Every menu keeps the approved appearance and reachable controls');
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
    console.log('PASS fantasy theme: ' + (broad.length * sizes.length) + ' menu/viewport combinations, actual Almendra/Lora glyphs, tappable relics, world, welcome and backoffice. ' + output);
  } catch (error) {
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify({ report, errors, badAssets, badApis, failure: String(error) }, null, 2));
    if (browser) for (const context of browser.contexts()) for (const [index, page] of context.pages().entries()) await page.screenshot({ path: path.join(output, `failure-${index}.png`) }).catch(() => {});
    throw error;
  } finally {
    if (browser) await browser.close();
    if (fixture.child.exitCode === null) {
      fixture.child.stdin.end('\n');
      await new Promise(resolve => fixture.child.once('exit', resolve));
    }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
