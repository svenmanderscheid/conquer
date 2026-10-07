'use strict';
// Native browser activation remains the source of clicks; feedback must never
// submit a second action or leave a pressed face after an interrupted gesture.
const assert = require('node:assert/strict'), fs = require('node:fs'), path = require('node:path'), http = require('node:http');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'assets/js/ui-press.js'));
const excluded = ['painted-village-building', 'atlas-marker', 'atlas-minimap', 'rt-node', 'talent-star-select', 'portrait-button', 'member-identity'];
const fixture = mode => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:25px}button,a.button{display:inline-block;margin:8px;width:140px;height:48px;border:2px solid rgb(42,80,32)}.excluded{margin-top:20px}</style><script src="/ui-press.js" defer></script></head><body class="${mode}"><button id="action"><span>Train</span></button><button id="disabled" disabled>Unavailable</button><a href="#done" class="button" id="link">Continue</a><div class="excluded">${excluded.map(name => `<button class="${name}">${name}</button>`).join('')}<div id="uok-layout-layer"><button id="resize">Resize</button></div><button draggable="true" id="drag">Drag</button></div><script>window.clicks={};document.addEventListener('click',e=>{const b=e.target.closest('button,a.button');if(b)clicks[b.id||b.className]=(clicks[b.id||b.className]||0)+1;});window.captures=0;const capture=Element.prototype.setPointerCapture;Element.prototype.setPointerCapture=function(...a){captures++;return capture.apply(this,a);};</script></body></html>`;
(async () => {
  const server = http.createServer((req, res) => {
    if (req.url === '/ui-press.js') { res.setHeader('Content-Type', 'text/javascript'); res.end(script); return; }
    res.setHeader('Content-Type', 'text/html'); res.end(fixture(req.url === '/admin' ? 'mobile-game admin-modern' : 'mobile-game'));
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  let browser;
  try {
    browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
    const page = await browser.newPage({ viewport: { width: 390, height: 844 }, hasTouch: true });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const base = 'http://127.0.0.1:' + server.address().port;
    await page.goto(base);
    const button = page.locator('#action');
    const pressed = () => button.evaluate(node => node.classList.contains('ui-is-pressed'));
    const count = () => page.evaluate(() => clicks.action || 0);
    const center = async () => { const r = await button.boundingBox(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; };
    let p = await center();
    await page.mouse.move(p.x, p.y); await page.mouse.down();
    assert(await pressed(), 'Mouse press lowers the face'); assert.equal(await count(), 0);
    assert.equal(await button.evaluate(node => node.style.getPropertyValue('--ui-press-edge')), 'rgb(42, 80, 32)');
    await page.mouse.up(); assert(!(await pressed())); assert.equal(await count(), 1);
    await page.mouse.down(); await page.mouse.move(2, 2); assert(!(await pressed()), 'Drag outside releases visual');
    await page.mouse.up(); assert.equal(await count(), 1, 'Drag outside adds no activation');
    await page.mouse.move(p.x, p.y); await page.mouse.down();
    await button.dispatchEvent('pointermove', { pointerId: 1, pointerType: 'mouse', isPrimary: true, buttons: 0, clientX: p.x, clientY: p.y });
    assert(!(await pressed()), 'Lost pointer release is recovered from buttons=0'); await page.mouse.up();
    let before = await count();
    await button.focus(); await page.keyboard.down('Space'); assert(await pressed()); assert.equal(await count(), before);
    await page.keyboard.up('Space'); assert(!(await pressed())); assert.equal(await count(), before + 1);
    before = await count(); await page.keyboard.down('Enter'); assert(await pressed()); assert.equal(await count(), before + 1);
    await page.keyboard.up('Enter'); assert(!(await pressed())); assert.equal(await count(), before + 1);
    await page.keyboard.down('Space'); await page.locator('#link').focus(); assert(!(await pressed()), 'Focus change releases key');
    await page.keyboard.up('Space'); assert.equal(await count(), before + 1);
    await page.keyboard.down('Space'); assert.equal(await page.locator('#link').evaluate(node => node.classList.contains('ui-is-pressed')), false, 'Space does not activate a link'); await page.keyboard.up('Space');
    const cdp = await page.context().newCDPSession(page); p = await center(); before = await count();
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: p.x, y: p.y, id: 1 }] });
    assert(await pressed()); await cdp.send('Input.dispatchTouchEvent', { type: 'touchCancel', touchPoints: [] });
    assert(!(await pressed())); assert.equal(await count(), before, 'Cancelled touch does not activate');
    await page.touchscreen.tap(p.x, p.y); assert(!(await pressed())); assert.equal(await count(), before + 1, 'Touch activates exactly once');
    await button.focus(); await page.keyboard.down('Space'); await page.evaluate(() => dispatchEvent(new Event('blur')));
    assert(!(await pressed()), 'Window blur releases key'); await page.keyboard.up('Space');
    await page.mouse.move(p.x, p.y); await page.mouse.down(); await page.evaluate(() => dispatchEvent(new Event('pagehide')));
    assert(!(await pressed()), 'Page lifecycle releases pointer'); await page.mouse.up();
    for (const selector of excluded.map(name => '.' + name).concat('#resize', '#drag', '#disabled')) {
      await page.locator(selector).dispatchEvent('pointerdown', { pointerId: 2, pointerType: 'touch', isPrimary: true, button: 0, buttons: 1 });
      assert.equal(await page.locator(selector).evaluate(node => node.classList.contains('ui-press-ready')), false, selector + ' excluded');
    }
    await page.evaluate(() => { const node = document.createElement('button'); node.id = 'dynamic'; node.textContent = 'Use All'; document.body.prepend(node); });
    await page.locator('#dynamic').click(); assert.equal(await page.evaluate(() => clicks.dynamic), 1);
    assert.equal(await page.locator('#dynamic').evaluate(node => node.classList.contains('ui-press-ready')), true, 'Dynamic controls use delegation');
    assert.equal(await page.evaluate(() => captures), 0, 'Feedback never captures the pointer');
    await page.goto(base + '/admin'); await page.locator('#action').click();
    assert.equal(await page.locator('#action').evaluate(node => node.classList.contains('ui-press-ready')), false, 'Admin remains independent');
    assert.deepEqual(errors, []);
    console.log('PASS pressure feedback: native mouse/touch/Space/Enter once, cancelled gestures, lost release, lifecycle/focus cleanup, dynamic controls, map/portrait/editor exclusions and admin isolation.');
  } finally { await browser?.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
