'use strict';

// The bundled connection screen needs no account, PHP server, or live game requests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { buildWeb } = require('../mobile/scripts/build-web.cjs');

const projectRoot = path.resolve(__dirname, '..');
const artifactDir = path.join(projectRoot, 'artifacts/mobile-shell');
const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'uok-mobile-shell-'));
const outputDir = path.join(temporaryRoot, 'www');
const configPath = path.join(temporaryRoot, 'capacitor.config.json');
const target = 'https://game.example.test/play/?from=mobile&test=1#city';

(async () => {
  let browser, server;
  try {
    fs.mkdirSync(artifactDir, { recursive:true });
    fs.writeFileSync(configPath, JSON.stringify({ server:{ url:target, errorPath:'index.html' } }));
    const bundle = buildWeb({ configPath, outputDir });
    assert.deepEqual(bundle.files, ['index.html', 'assets/fonts/OFL-Bree-Serif.txt', 'assets/fonts/OFL-Nunito.txt']);
    assert(bundle.files.every(file => !/\.(php|sql|env|pem|key)$/i.test(file)), 'Only public fallback assets are bundled');
    const bytes = bundle.files.reduce((sum, file) => sum + fs.statSync(path.join(outputDir, file)).size, 0);
    assert(bytes < 2 * 1024 * 1024, 'The connection screen stays below 2 MiB');
    // Never silently retain unknown content when preparing an installable bundle.
    fs.writeFileSync(path.join(outputDir, 'unexpected.php'), '<?php');
    assert.throws(() => buildWeb({ configPath, outputDir }), /Unexpected files/);
    fs.unlinkSync(path.join(outputDir, 'unexpected.php'));

    const missing = [];
    server = http.createServer((request, response) => {
      // Match Capacitor server.url + errorPath: only the exact error page is local.
      if (request.url !== '/index.html') {
        missing.push(request.url);
        response.writeHead(404).end();
        return;
      }
      response.writeHead(200, { 'Content-Type':'text/html; charset=utf-8' });
      fs.createReadStream(path.join(outputDir, 'index.html')).pipe(response);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const base = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({ headless:true, channel:process.env.BROWSER_CHANNEL || 'msedge' });
    const context = await browser.newContext({ locale:'de-DE', viewport:{ width:390, height:844 }, hasTouch:true, reducedMotion:'reduce' });
    const page = await context.newPage();
    const errors = [], subresources = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('request', request => { if (/^https?:/.test(request.url()) && request.url() !== base + '/index.html') subresources.push(request.url()); });
    await page.goto(base + '/index.html');
    await page.locator('[data-locale-select]').waitFor();
    await page.evaluate(() => document.fonts.ready);
    assert.equal(await page.locator('html').getAttribute('lang'), 'en', 'German browser does not override the English default');
    assert.equal(await page.locator('.mobile-shell-retry').textContent(), 'Try again');
    assert.equal(await page.locator('.mobile-shell-retry').getAttribute('href'), target);
    assert.equal(await page.title(), 'Union of Kingdoms');
    assert.equal(await page.locator('meta[name=theme-color]').getAttribute('content'), '#fff7e7');
    assert.deepEqual(await page.evaluate(() => {
      const style = selector => getComputedStyle(document.querySelector(selector));
      return {
        page:style('body').backgroundColor,
        headerText:style('.mobile-shell-header').color,
        retryText:style('.mobile-shell-retry').color,
        card:style('.mobile-shell-window').backgroundColor
      };
    }), { page:'rgb(255, 247, 231)', headerText:'rgb(58, 53, 41)', retryText:'rgb(32, 59, 24)', card:'rgb(255, 252, 243)' }, 'Cream surfaces preserve dark header and green-action text');
    assert.equal(await page.locator('link[rel=stylesheet],script[src]').count(), 0, 'No external CSS or JavaScript dependencies');
    const styles = await page.locator('style').evaluateAll(nodes => nodes.map(node => node.dataset.source));
    assert.equal(styles.at(-1), 'assets/css/village-theme.css', 'Embedded shared theme is loaded last');
    assert(await page.evaluate(() => document.fonts.check('18px "Conquer UI"')), 'Shared local typeface is available');
    assert.deepEqual(await page.locator('img').evaluateAll(nodes => nodes.filter(node => !node.complete || !node.naturalWidth).map(node => node.src)), []);
    assert.equal(await page.evaluate(() => ConquerPWA.register()), null, 'The connection screen never registers a service worker');
    assert.equal(await page.evaluate(async () => (await navigator.serviceWorker.getRegistrations()).length), 0);

    const fontSession = await context.newCDPSession(page);
    await fontSession.send('DOM.enable');
    await fontSession.send('CSS.enable');
    const fontChecks = [];
    for (const language of ['en', 'de', 'fr']) {
      await page.locator('[data-locale-select]').selectOption(language);
      assert.equal(await page.locator('html').getAttribute('lang'), language);
      const catalog = JSON.parse(fs.readFileSync(path.join(projectRoot, 'data/i18n', language + '.json'), 'utf8'));
      assert.equal(await page.locator('h2').textContent(), catalog['mobile.connection.title']);
      await page.evaluate(() => document.fonts.ready);
      const fontDocument = await fontSession.send('DOM.getDocument');
      for (const [selector, family] of [['h1','Bree Serif'],['h2','Bree Serif'],['.mobile-shell-retry','Bree Serif'],['.mobile-shell-content>p','Nunito']]) {
        const { nodeId } = await fontSession.send('DOM.querySelector', { nodeId:fontDocument.root.nodeId, selector });
        const { fonts } = await fontSession.send('CSS.getPlatformFontsForNode', { nodeId });
        assert(fonts.some(font => font.isCustomFont && font.familyName.includes(family)), language + ' ' + selector + ' renders ' + family);
        assert(fonts.every(font => font.isCustomFont), language + ' ' + selector + ' has no system-font fallback');
        fontChecks.push({ language, selector, fonts });
      }

      for (const [width, height] of [[1280,800], [390,844], [320,568], [844,390], [568,320]]) {
        await page.setViewportSize({ width, height });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, language + ' has no horizontal overflow at ' + width);
        for (const selector of ['.mobile-shell-retry', '[data-locale-select]']) {
          const control = page.locator(selector);
          await control.scrollIntoViewIfNeeded();
          const bounds = await control.boundingBox();
          assert(bounds && bounds.height >= 44 && bounds.width >= 44, selector + ' has a touch target');
          assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1 && bounds.y >= 0 && bounds.y + bounds.height <= height + 1, selector + ' is reachable');
          assert(await control.evaluate(node => {
            const rect = node.getBoundingClientRect();
            const hit = document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2);
            return hit === node || node.contains(hit);
          }), selector + ' is not covered');
        }
        await page.screenshot({ path:path.join(artifactDir, language + '-' + width + 'x' + height + '.png'), fullPage:true });
      }
    }
    await page.reload();
    await page.locator('[data-locale-select]').waitFor();
    assert.equal(await page.locator('html').getAttribute('lang'), 'fr', 'Explicit language survives reopening the fallback');
    await context.setOffline(true);
    await page.locator('[data-locale-select]').selectOption('de');
    assert.equal(await page.locator('.mobile-shell-retry').textContent(), 'Erneut versuchen', 'Language choice works without the network');
    await context.setOffline(false);
    assert.deepEqual(subresources, [], 'Fallback has zero network subresource requests');
    assert.deepEqual(missing, [], 'Only the exact native error path is requested');
    assert.deepEqual(errors, [], 'No browser errors');

    // An explicit retry performs exactly one navigation; it does not replay gameplay writes.
    let retries = 0;
    await page.route('https://game.example.test/**', route => {
      retries++;
      return route.fulfill({ status:200, contentType:'text/html', body:'<!doctype html><title>Retry target</title>' });
    });
    await page.locator('.mobile-shell-retry').click();
    await page.waitForURL(target);
    assert.equal(retries, 1);
    fs.writeFileSync(path.join(artifactDir, 'font-checks.json'), JSON.stringify(fontChecks, null, 2));
    console.log('Mobile shell: 15 locale/viewport checks passed; ' + bundle.files.length + ' public files, ' + bytes + ' bytes.');
  } finally {
    if (browser) await browser.close();
    if (server) await new Promise(resolve => server.close(resolve));
    const resolvedTemporaryRoot = path.resolve(temporaryRoot);
    assert.equal(path.dirname(resolvedTemporaryRoot), path.resolve(os.tmpdir()));
    assert(path.basename(resolvedTemporaryRoot).startsWith('uok-mobile-shell-'));
    fs.rmSync(resolvedTemporaryRoot, { recursive:true, force:true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
