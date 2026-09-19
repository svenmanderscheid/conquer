const { chromium } = require('playwright');

(async () => {
  const base = process.env.BASE_URL || 'http://127.0.0.1:18988';
  const shotDir = process.env.SHOT_DIR || process.env.TEMP;
  const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
  const errors = [];
  const results = [];

  async function openGame(width, height, name) {
    const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 1 });
    page.on('pageerror', error => errors.push(`${name}: ${error.message}`));
    page.on('console', message => { if (message.type() === 'error') errors.push(`${name}: ${message.text()}`); });
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.locator('[data-mode="login"]').click();
    await page.locator('[name="username"]').fill('PreviewPlayer');
    await page.locator('[name="password"]').fill('PreviewFixture!2026');
    await Promise.all([page.waitForURL('**/city'), page.locator('#auth-submit').click()]);
    await page.locator('#navigation .game-dock-item').first().waitFor();
    await page.locator('.world-chat-preview-message').first().waitFor();
    return page;
  }

  for (const [width, height, name] of [[390, 844, 'portrait'], [844, 390, 'landscape']]) {
    const page = await openGame(width, height, name);
    const layout = await page.evaluate(() => {
      const dock = document.querySelector('#navigation');
      const buttons = [...dock.querySelectorAll('.game-dock-item')];
      const preview = document.querySelector('.world-chat-preview');
      const rects = buttons.map(button => button.getBoundingClientRect());
      return {
        count: buttons.length,
        labels: buttons.map(button => button.querySelector('.dock-label')?.textContent.trim()),
        oneRow: rects.every(rect => Math.abs(rect.top - rects[0].top) < 2),
        dockInside: dock.scrollWidth <= dock.clientWidth + 1,
        buttonsInside: rects.every(rect => rect.left >= -1 && rect.right <= innerWidth + 1),
        previewRows: document.querySelectorAll('.world-chat-preview-message').length,
        heightDelta: Math.abs(preview.getBoundingClientRect().height - dock.getBoundingClientRect().height),
        chatDockGap: Math.min(...rects.map(rect => rect.top)) - preview.getBoundingClientRect().bottom,
        overlap: preview.getBoundingClientRect().bottom > Math.min(...rects.map(rect => rect.top)) + 1
      };
    });
    if (layout.count !== 7 || !layout.oneRow || !layout.dockInside || !layout.buttonsInside || layout.previewRows !== 2 || layout.heightDelta > 2 || layout.chatDockGap < 7 || layout.chatDockGap > 9 || layout.overlap) {
      throw new Error(`${name} HUD contract failed: ${JSON.stringify(layout)}`);
    }
    await page.screenshot({ path: `${shotDir}\\conquer-hud-${name}.png`, fullPage: true });

    await page.locator('[data-chat-open]').click();
    await page.locator('.world-chat-window:not([hidden])').waitFor();
    const chat = await page.evaluate(() => ({
      tabs: [...document.querySelectorAll('[data-chat-channel]')].map(button => button.textContent.trim().replace(/\d+$/, '')),
      mute: document.querySelector('[data-chat-mute]')?.textContent.trim(),
      fits: document.querySelector('.world-chat-window').getBoundingClientRect().height <= innerHeight + 1
    }));
    if (chat.tabs.join('|') !== 'Weltchat|Allianzchat|Privatchat' || !chat.fits) throw new Error(`${name} chat contract failed: ${JSON.stringify(chat)}`);
    await page.screenshot({ path: `${shotDir}\\conquer-chat-${name}.png`, fullPage: true });
    await page.locator('[data-chat-close]').first().click();

    await page.locator('#navigation [data-id="shop"]').click();
    await page.locator('.trading-tabs.shop-tabs').waitFor();
    const shops = await page.locator('.shop-tabs [data-action="trading-tab"]').allTextContents();
    if (shops.join('|') !== 'Händler|Kristall-Shop|VIP-Shop|Karawane') throw new Error(`${name} shop contract failed: ${shops.join('|')}`);
    for (const [id, heading] of [['crystals', 'Kristall-Shop'], ['vip', 'VIP-Wochenangebote'], ['caravan', 'Die Karawane ist da'], ['merchant', 'Händler']]) {
      await page.locator(`.shop-tabs [data-id="${id}"]`).click();
      if ((await page.locator('.trading-summary h2').innerText()).trim() !== heading) throw new Error(`${name} shop tab ${id} did not open`);
    }
    await page.screenshot({ path: `${shotDir}\\conquer-shop-${name}.png`, fullPage: true });
    await page.locator('#panel-dialog .panel-close').click();

    await page.locator('.hud-right-tools [data-id="events"]').click();
    await page.locator('.event-reference-calendar').waitFor();
    const calendar = await page.evaluate(() => ({
      days: document.querySelectorAll('.event-week > article').length,
      tabs: document.querySelectorAll('.event-reference-tabs button').length
    }));
    if (calendar.days !== 7 || calendar.tabs < 2) throw new Error(`${name} event calendar failed: ${JSON.stringify(calendar)}`);
    await page.screenshot({ path: `${shotDir}\\conquer-events-${name}.png`, fullPage: true });
    results.push({ name, layout, chat, shops, calendar });
    await page.close();
  }

  await browser.close();
  if (errors.length) throw new Error(`Browser errors:\n${errors.join('\n')}`);
  console.log(JSON.stringify(results, null, 2));
})().catch(error => { console.error(error); process.exit(1); });
