'use strict';
// Read-only public landing regression. With no URL, render the existing PHP
// fixture without app bootstrap, login or a database. Override PHP_BINARY,
// PLAYWRIGHT_MODULE / PLAYWRIGHT_CHANNEL, or UOK_ARTWORK_URL as needed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '..');
const output = path.join(root, 'artifacts/landing-artwork');
const php = process.env.PHP_BINARY || 'php';
const viewports = [[1280, 800], [390, 844], [844, 390]];
const roles = ['guardian', 'fire-archer', 'shadow-rider'];
const names = ['Infantry guardian', 'Fire archer', 'Cavalry shadow rider'];
const artDirectory = 'assets/art/characters/fantasy-troops-v3/';
const sha256 = bytes => createHash('sha256').update(bytes).digest('hex');
const report = { status: 'running', source: process.env.UOK_ARTWORK_URL || 'isolated PHP fixture', cases: [], images: {} };
fs.mkdirSync(output, { recursive: true });

function phpRun(args) {
  return execFileSync(php, args, { cwd: root, encoding: 'utf8', maxBuffer: 8e6, timeout: 15000, windowsHide: true });
}

async function startFixture() {
  const html = phpRun([path.join(__dirname, 'fixtures/localization_landing.php')]);
  const localeAssets = new Map();
  const server = http.createServer((req, res) => {
    try {
      if (!['GET', 'HEAD'].includes(req.method)) { res.writeHead(405); res.end(); return; }
      const pathname = new URL(req.url, 'http://localhost').pathname;
      if (pathname === '/') {
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-localization-test'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'");
        res.end(html); return;
      }
      if (pathname.startsWith('/locale-assets/')) {
        if (!localeAssets.has(pathname)) localeAssets.set(pathname, JSON.parse(phpRun(['-r', "define('ROOT_DIR',getcwd());require 'src/Game/Locale.php';echo json_encode(\\Conquer\\Game\\Locale::assetResponse($argv[1]));", pathname])));
        const asset = localeAssets.get(pathname);
        res.writeHead(asset.status, asset.headers); res.end(asset.body); return;
      }
      if (pathname === '/manifest.php') {
        res.setHeader('Content-Type', 'application/manifest+json');
        res.end(phpRun(['-r', "define('APP_BASE','');require 'manifest.php';"])); return;
      }
      const file = path.resolve(root, '.' + decodeURIComponent(pathname));
      const allowed = file.startsWith(path.join(root, 'assets') + path.sep) || ['/favicon.ico', '/apple-touch-icon.png'].includes(pathname);
      if (!allowed || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
      res.setHeader('Content-Type', ({ '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.webp': 'image/webp', '.woff2': 'font/woff2', '.ico': 'image/x-icon' })[path.extname(file)] || 'application/octet-stream');
      res.end(fs.readFileSync(file));
    } catch (error) { res.writeHead(500); res.end(String(error)); }
  });
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
  return { server, url: 'http://127.0.0.1:' + server.address().port + '/' };
}

async function checkTier(page, tier, responses, label, changed) {
  const images = page.locator('.lp-troop-grid img');
  assert.equal(await images.count(), 3, label + ': exactly three troop roles');
  for (let index = 0; index < roles.length; index++) {
    const image = images.nth(index);
    const relative = artDirectory + roles[index] + '-t' + tier + '-ui.webp';
    const source = new URL(await image.getAttribute('src'), page.url());
    // Allow a deployed subdirectory, but require the complete approved path.
    const prefix = source.pathname.split('/assets/')[0];
    assert.equal(source.pathname, prefix + '/' + relative, label + ': wrong artwork for ' + roles[index]);
    assert.equal(source.origin, new URL(page.url()).origin, label + ': artwork stays on the public origin');
    assert((await image.getAttribute('alt') || '').trim(), label + ': accessible role name');
    if (changed) assert.equal(await image.getAttribute('alt'), names[index] + ', tier ' + tier);
    // Scroll each lazy image into view, including the stacked mobile cards.
    await image.scrollIntoViewIfNeeded();
    const decoded = await image.evaluate(async element => {
      await element.decode();
      return { complete: element.complete, width: element.naturalWidth, height: element.naturalHeight, currentSrc: element.currentSrc };
    });
    assert(decoded.complete && decoded.width > 0 && decoded.height > 0, label + ': decoded ' + relative);
    assert.equal(decoded.currentSrc, source.href, label + ': browser displays the expected source');
    const network = responses.get(source.href);
    assert(network, label + ': loaded response captured for ' + relative);
    const actual = await network;
    assert(!actual.error, label + ': response body readable: ' + actual.error);
    const expected = sha256(fs.readFileSync(path.join(root, relative)));
    assert.equal(actual.sha256, expected, label + ': served bytes differ from approved local artwork: ' + relative);
    report.images[relative] = { sha256: expected, width: decoded.width, height: decoded.height };
  }
  assert.equal(await page.locator('[data-tier-status]').getAttribute('role'), 'status');
  assert.equal((await page.locator('[data-tier-status]').textContent()).trim(), 'Troop artwork · Tier ' + tier + ' of 5');
  assert.deepEqual(await page.locator('[data-preview-tier]').evaluateAll(buttons => buttons.map(button => ({ tier: Number(button.dataset.previewTier), pressed: button.getAttribute('aria-pressed') }))), [1, 2, 3, 4, 5].map(value => ({ tier: value, pressed: String(value === tier) })), label + ': selected tier announced correctly');
  const overflow = await page.evaluate(() => {
    const problems = [];
    if (document.documentElement.scrollWidth > innerWidth + 1) problems.push('page horizontal overflow');
    for (const element of document.querySelectorAll('#troops, .lp-troop-grid, .lp-troop-grid img, .lp-tier-picker:not([hidden])')) {
      const box = element.getBoundingClientRect();
      if (box.left < -1 || box.right > innerWidth + 1 || element.scrollWidth > element.clientWidth + 1) problems.push(element.className || element.tagName);
    }
    return problems;
  });
  assert.deepEqual(overflow, [], label + ': artwork and controls fit the viewport');
}

(async () => {
  let server, browser;
  try {
    let url = process.env.UOK_ARTWORK_URL;
    if (!url) ({ server, url } = await startFixture());
    assert(['http:', 'https:'].includes(new URL(url).protocol), 'UOK_ARTWORK_URL must be an HTTP(S) public landing page');
    browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
    for (const javaScriptEnabled of [false, true]) for (const [width, height] of viewports) {
      const label = `${javaScriptEnabled ? 'interactive' : 'no-js'}-${width}x${height}`;
      const context = await browser.newContext({ viewport: { width, height }, locale: 'en-US', javaScriptEnabled, reducedMotion: 'reduce', serviceWorkers: 'block' });
      const errors = [], responses = new Map(), page = await context.newPage();
      const result = { label, tiers: [], errors }; report.cases.push(result);
      // Prevent writes even if a future landing script unexpectedly attempts one.
      await context.route('**/*', route => {
        if (!['GET', 'HEAD'].includes(route.request().method())) {
          errors.push('Blocked write: ' + route.request().method() + ' ' + route.request().url());
          return route.abort();
        }
        return route.continue();
      });
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
      page.on('requestfailed', request => errors.push(request.url() + ': ' + request.failure()?.errorText));
      page.on('response', response => {
        if (response.status() >= 400) errors.push('HTTP ' + response.status() + ': ' + response.url());
        if (new URL(response.url()).pathname.includes('/' + artDirectory)) responses.set(response.url(), response.body().then(body => ({ sha256: sha256(body) }), error => ({ error: error.message })));
      });
      try {
        const document = await page.goto(url, { waitUntil: 'load' });
        assert(document?.ok(), label + ': public page loaded successfully');
        assert.equal(await page.locator('html').getAttribute('lang'), 'en', label + ': English landing');
        await page.evaluate(() => document.fonts.ready);
        await checkTier(page, 5, responses, label + ' initial T5', false);
        assert.equal(await page.locator('.lp-tier-picker').isVisible(), javaScriptEnabled, label + ': progressive enhancement');
        await page.locator('#troops').screenshot({ path: path.join(output, label + '-initial-t5.png') });
        result.tiers.push('initial-5');
        if (javaScriptEnabled) for (const tier of [1, 2, 3, 4, 5]) {
          await page.locator(`[data-preview-tier="${tier}"]`).click();
          await checkTier(page, tier, responses, label + ' T' + tier, true);
          await page.locator('#troops').screenshot({ path: path.join(output, label + '-t' + tier + '.png') });
          result.tiers.push(tier);
        }
        assert.deepEqual(errors, [], label + ': browser and resource errors');
        result.status = 'passed';
      } catch (error) {
        result.status = 'failed'; result.failure = error.message;
        await page.screenshot({ path: path.join(output, label + '-failure.png') }).catch(() => {});
        throw error;
      } finally { await context.close(); }
    }
    assert.equal(Object.keys(report.images).length, 15, 'all fifteen approved troop images loaded and hashed');
    report.status = 'passed';
    console.log('PASS landing artwork: initial T5 with/without JS; T1–T5 × 3 roles; exact v3 paths and SHA256; ARIA, decoding, errors and overflow at three viewports. Artifacts: ' + output);
  } catch (error) {
    report.status = 'failed'; report.failure = error.message; throw error;
  } finally {
    fs.writeFileSync(path.join(output, process.env.UOK_ARTWORK_URL ? 'live-results.json' : 'local-results.json'), JSON.stringify(report, null, 2) + '\n');
    if (browser) await browser.close();
    if (server) { server.closeAllConnections?.(); await new Promise(resolve => server.close(resolve)); }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
