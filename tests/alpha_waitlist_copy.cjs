'use strict';
const assert = require('node:assert/strict'), fs = require('node:fs'), path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.argv[2];
assert(/^http:\/\/127\.0\.0\.1:\d+\/conquer$/.test(base), 'Disposable local fixture required');
const out = path.resolve(__dirname, '../output/playwright/admin-waitlist-copy-20261009');
fs.mkdirSync(out, { recursive: true });
(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({ permissions: ['clipboard-read', 'clipboard-write'], reducedMotion: 'reduce' });
  const page = await context.newPage(), errors = [], checks = [];
  page.on('pageerror', error => errors.push(error.message));
  const login = async name => {
    await page.goto(base + '/admin/login');
    await page.locator('[name=username]').fill(name);
    await page.locator('[name=password]').fill('Waitlist-Fixture-2026!');
    await Promise.all([page.waitForURL(base + '/admin'), page.locator('form button[type=submit]').click()]);
  };
  try {
    await page.goto(base + '/admin/alpha-waitlist');
    assert.equal(new URL(page.url()).pathname, '/conquer/admin/login');
    assert.equal(await page.locator('#waitlist-emails').count(), 0);
    await login('CopyAdmin');
    const writes = [];
    page.on('request', request => { if (request.method() !== 'GET') writes.push(request.method() + ' ' + new URL(request.url()).pathname); });
    await page.goto(base + '/admin/alpha-waitlist?q=copy01&status=waiting&page=2');
    assert.equal(await page.locator('.alpha-waitlist tbody tr').count(), 1);
    await page.locator('[data-waitlist-copy]').click();
    await page.locator('[data-waitlist-copy-status]:not([hidden])').waitFor();
    const recipients = (await page.evaluate(() => navigator.clipboard.readText())).split(', ');
    assert.equal(recipients.length, 32);
    assert(recipients.includes('tester@tests.invalid') && recipients.includes('copy02@tests.invalid') && recipients.includes('copy31@tests.invalid'));
    assert.equal(await page.locator('[data-waitlist-copy-list]').isVisible(), false);
    await page.goto(base + '/admin/alpha-waitlist?page=2');
    assert.equal(await page.locator('.alpha-waitlist tbody tr').count(), 7);
    for (const [lang, width, height] of [['en', 1440, 1000], ['en', 390, 844], ['en', 320, 568], ['en', 844, 390], ['en', 568, 320], ['de', 390, 844], ['fr', 390, 844]]) {
      await page.setViewportSize({ width, height });
      await page.locator('[data-locale-select]').selectOption(lang);
      await page.waitForFunction(value => document.documentElement.lang === value, lang);
      await page.locator('[data-waitlist-copy]').click();
      await page.waitForFunction(() => !document.querySelector('[data-waitlist-copy]').disabled);
      const status = await page.locator('[data-waitlist-copy-status]').textContent();
      assert(status.includes('32') && !status.includes('admin.waitlist.'));
      assert.equal((await page.evaluate(() => navigator.clipboard.readText())).split(', ').length, 32);
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      const button = await page.locator('[data-waitlist-copy]').boundingBox();
      assert(button.height >= 44 && button.x >= 0 && button.x + button.width <= width + 1);
      await page.screenshot({ path: path.join(out, `copy-${lang}-${width}x${height}.png`), fullPage: true });
      checks.push({ lang, width, height, count: 32, overflow: false });
    }
    assert.deepEqual(writes, [], 'Copying does not write to the server');
    for (const missing of [false, true]) {
      const fallback = await context.newPage();
      await fallback.addInitScript(missing => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: missing ? undefined : { writeText: () => Promise.reject(Error('Fixture clipboard denied')) } }), missing);
      await fallback.goto(base + '/admin/alpha-waitlist');
      await fallback.locator('[data-waitlist-copy]').click();
      await fallback.locator('[data-waitlist-copy-list]:not([hidden])').waitFor();
      assert(await fallback.locator('#waitlist-emails').evaluate(element => document.activeElement === element && element.selectionStart === 0 && element.selectionEnd === element.value.length));
      assert.equal((await fallback.locator('#waitlist-emails').inputValue()).split(', ').length, 32);
      await fallback.close();
    }
    if (!await page.locator('.sidebar-bottom form button').isVisible()) await page.locator('.mobile-menu').click();
    await Promise.all([page.waitForURL('**/admin/login'), page.locator('.sidebar-bottom form button').click()]);
    await login('CopyModerator');
    const denied = await page.goto(base + '/admin/alpha-waitlist');
    assert.equal(denied.status(), 403);
    assert.equal(await page.locator('#waitlist-emails').count(), 0);
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(out, 'checks.json'), JSON.stringify({ checks, realClipboard: true, manualFallback: true, roleProtection: true, errors }, null, 2));
    console.log('PASS: real clipboard copies all 32 addresses across pagination/filters; 7 language/viewport checks; denied and missing clipboard fallback; anonymous/moderator protection; no copy mutations.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
