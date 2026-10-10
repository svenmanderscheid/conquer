'use strict';
// Actual shared app, disposable database, read-only map snapshots and profile requests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const output = path.join(root, 'output/playwright/map-popup-input');
const baseline = process.env.MAP_POPUP_BASELINE === '1';
const assetDirectory = process.env.MAP_POPUP_ASSET_DIR
    ? path.resolve(process.env.MAP_POPUP_ASSET_DIR)
    : baseline ? path.join(output, 'baseline') : null;
const checks = [];
function check(label, pass, details) { checks.push({ label, pass: !!pass, details }); }
const frame = page => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));

async function selected(page, id) {
    const target = page.locator(`[data-atlas-target="players:${id}"]`);
    const coords = await target.evaluate(node => ({ x: +node.dataset.x, y: +node.dataset.y }));
    await page.evaluate(p => ConquerWorld.focus(p.x, p.y), coords);
    await frame(page);
    // Keyboard selection uses the real marker; action activation below is native mouse/touch.
    await target.focus();
    await page.keyboard.press('Enter');
    await page.locator('.atlas-target-actions:not([hidden])').waitFor();
    await page.waitForTimeout(220);
}

async function checkViewport(browser, base, width, height) {
    const context = await browser.newContext({ viewport: { width, height }, hasTouch: true, locale: 'en-US' });
    const page = await context.newPage();
    const errors = [], writes = [], assets = [], profiles = [];
    page.setDefaultTimeout(15000);
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => {
        if (request.method() === 'POST' && request.url().includes('/api/')) writes.push(request.url());
        if (request.url().includes('/api/kingdom/state?player_id=')) profiles.push(request.url());
    });
    page.on('response', response => { if (response.status() >= 400 && response.url().includes('/assets/')) assets.push(response.url()); });
    await page.addInitScript(() => localStorage.setItem('conquer.locale', 'en'));
    if (assetDirectory) {
        for (const filename of ['world-painted.js', 'world-atlas.css', 'village-theme.css', 'map-overlay.css']) {
            await page.route(`**/assets/${filename.endsWith('.js') ? 'js' : 'css'}/${filename}*`, route => route.fulfill({ path: path.join(assetDirectory, filename) }));
        }
    }
    await page.route('**/assets/js/world-map.js*', async route => {
        const response = await route.fetch();
        const source = assetDirectory ? fs.readFileSync(path.join(assetDirectory, 'world-map.js'), 'utf8') : await response.text();
        await route.fulfill({ response, body: source + '\n;{const render=ConquerWorld.render;ConquerWorld.render=options=>{window.__mapOptions=options;return render(options);};}' });
    });
    await page.route('**/api/game/state**', async route => {
        const response = await route.fetch(), body = await response.json();
        if (body.data?.city) {
            const s = body.data, home = s.city;
            s.players = [
                { id: 2, username: 'UpperNeighbour', coord_x: +home.coord_x + 5, coord_y: +home.coord_y + 6, castle_level: 6, city_skin: 'default', name_frame: 'default' },
                { id: 3, username: 'LowerWaterVillage', coord_x: +home.coord_x + 5, coord_y: +home.coord_y + 9, castle_level: 6, city_skin: 'water', name_frame: 'water' }
            ];
            s.monsters = []; s.nodes = []; s.marches = []; s.neutral_villages = []; s.charms = []; s.alliance_structures = [];
        }
        await route.fulfill({ response, json: body });
    });
    // Use the real profile endpoint on the disposable account for synthetic map IDs.
    await page.route('**/api/kingdom/state?player_id=*', async route => {
        const response = await route.fetch({ url: base + '/api/kingdom/state?player_id=1' });
        await route.fulfill({ response });
    });
    const prefix = `${width}x${height}`;
    try {
        await page.goto(base + '/?zugang=login');
        await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');
        await page.locator('[name=password]').fill('PreviewFixture!2026');
        await Promise.all([page.waitForURL('**/city'), page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
        if (!baseline) {
            await page.goto(base + '/city#city');
            const castle = page.locator('.painted-village-building[data-id=castle]');
            await castle.waitFor();
            await page.waitForFunction(() => !document.querySelector('.scene-transition.is-active'));
            await page.screenshot({ path: path.join(output, `${prefix}-city.png`) });
            const exposed = await castle.evaluate(node => {
                const r = node.getBoundingClientRect();
                for (const fy of [.5, .3, .7]) for (const fx of [.5, .3, .7]) {
                    const x = r.width * fx, y = r.height * fy;
                    if (node.contains(document.elementFromPoint(r.left + x, r.top + y))) return { x, y };
                }
                return null;
            });
            assert(exposed, `${prefix}: main city castle is touch accessible`);
            await castle.tap({ position: exposed });
            await page.locator('.painted-building-actions').waitFor();
            await page.screenshot({ path: path.join(output, `${prefix}-city-action.png`) });
            await page.locator('.painted-selection-close').tap();
        }
        await page.goto(base + '/city#world');
        await page.locator('[data-atlas-target="players:3"]').waitFor({ state: 'attached' });
        await page.waitForFunction(() => !document.querySelector('.scene-transition.is-active'));
        await page.evaluate(() => document.fonts.ready);
        const collapse = page.locator('.world-march-heading[aria-expanded=true]');
        if (await collapse.isVisible()) await collapse.click();
        await selected(page, 3);
        const actions = page.locator('.atlas-target-actions .atlas-action');
        const count = await actions.count();
        for (let i = 0; i < count; i++) {
            const action = actions.nth(i);
            await page.mouse.move(1, 1);
            await page.waitForTimeout(200);
            const before = await action.boundingBox();
            const point = { x: before.x + before.width / 2, y: before.y + Math.min(27, before.height / 2) };
            await page.mouse.move(point.x, point.y);
            await page.mouse.down();
            await page.waitForTimeout(180);
            const pressed = await action.boundingBox();
            const delta = { x: pressed.x - before.x, y: pressed.y - before.y };
            check(`${prefix}: action ${i + 1} retains its hit area while pressed`, Math.abs(delta.x) < 1 && Math.abs(delta.y) < 1, delta);
            await page.mouse.move(1, 1);
            await page.mouse.up();
        }
        check(`${prefix}: cancelled presses do not activate profile`, profiles.length === 0, profiles.length);
        await page.screenshot({ path: path.join(output, `${baseline ? 'baseline-' : ''}${prefix}-popup.png`) });

        // Hold a real touch across a fresh read snapshot; unchanged commands must retain identity.
        await selected(page, 3);
        const profile = page.locator('.atlas-target-actions [data-action=public-profile]');
        await profile.evaluate(node => window.__heldAction = node);
        const b = await profile.boundingBox(), point = { x: b.x + b.width / 2, y: b.y + 25 };
        const cdp = await context.newCDPSession(page), previousProfiles = profiles.length;
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ ...point, id: 1 }] });
        await page.waitForTimeout(200);
        await page.evaluate(() => {
            const options = window.__mapOptions, state = structuredClone(options.state);
            state.players.find(player => player.id === 3).power = 12345;
            ConquerWorld.render({ ...options, state });
        });
        check(`${prefix}: poll retains the held native action`, await profile.evaluate(node => node === window.__heldAction && node.isConnected));
        await page.waitForTimeout(150);
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await page.waitForTimeout(450);
        check(`${prefix}: delayed touch activates profile exactly once`, profiles.length - previousProfiles === 1, profiles.length - previousProfiles);
        if (profiles.length > previousProfiles) {
            await page.locator('#game-dialog[open]').waitFor();
            await page.keyboard.press('Escape');
            await page.waitForFunction(() => !document.querySelector('#game-dialog').open);
        }
        await page.waitForTimeout(150);

        // Native mouse release must also keep its original button across a read snapshot.
        await selected(page, 3);
        const mouseProfile = page.locator('.atlas-target-actions [data-action=public-profile]');
        const mouseBox = await mouseProfile.boundingBox(), beforeMouse = profiles.length;
        await page.mouse.move(mouseBox.x + mouseBox.width / 2, mouseBox.y + 25);
        await page.mouse.down();
        await page.waitForTimeout(180);
        await page.evaluate(() => {
            const options = window.__mapOptions, state = structuredClone(options.state);
            state.players.find(player => player.id === 3).power = 54321;
            ConquerWorld.render({ ...options, state });
        });
        await page.mouse.up();
        await page.waitForTimeout(450);
        check(`${prefix}: mouse release across poll activates profile exactly once`, profiles.length - beforeMouse === 1, profiles.length - beforeMouse);
        if (profiles.length > beforeMouse) {
            await page.locator('#game-dialog[open]').waitFor();
            await page.keyboard.press('Escape');
            await page.waitForFunction(() => !document.querySelector('#game-dialog').open);
        }
        if (!baseline) {
            await selected(page, 3);
            const keyboardProfile = page.locator('.atlas-target-actions [data-action=public-profile]'), beforeKeyboard = profiles.length;
            await keyboardProfile.focus();
            await page.keyboard.press('Space');
            await page.locator('#game-dialog[open]').waitFor();
            check(`${prefix}: keyboard Space activates profile exactly once`, profiles.length - beforeKeyboard === 1, profiles.length - beforeKeyboard);
            await page.keyboard.press('Escape');
            await page.waitForFunction(() => !document.querySelector('#game-dialog').open);
        }

        // A painted roof extends above its authoritative tile footprint, over the neighbour.
        await page.evaluate(() => { const p = window.__mapOptions.state.players.find(p => p.id === 3); ConquerWorld.focus(p.coord_x, p.coord_y); });
        await frame(page);
        await page.waitForFunction(() => document.querySelector('[data-atlas-target="players:3"] img')?.complete);
        const roof = await page.evaluate(() => {
            const lower = document.querySelector('[data-atlas-target="players:3"]'), upper = document.querySelector('[data-atlas-target="players:2"]');
            const img = lower.querySelector('img'), box = img.getBoundingClientRect(), tile = lower.getBoundingClientRect(), neighbour = upper.getBoundingClientRect();
            const canvas = document.createElement('canvas'); canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
            const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
            const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            const scale = Math.min(box.width / canvas.width, box.height / canvas.height), left = box.left + (box.width - canvas.width * scale) / 2, top = box.top + (box.height - canvas.height * scale) / 2;
            for (let y = Math.ceil(Math.max(box.top, neighbour.top) + 5); y < Math.min(tile.top - 4, neighbour.bottom - 4); y += 2) {
                for (const fraction of [.5, .45, .55, .4, .6]) {
                    const x = box.left + box.width * fraction, px = Math.floor((x - left) / scale), py = Math.floor((y - top) / scale);
                    if (px >= 0 && py >= 0 && px < canvas.width && py < canvas.height && pixels[(py * canvas.width + px) * 4 + 3] > 220) return { x, y, hit: document.elementFromPoint(x, y)?.closest('[data-atlas-target]')?.dataset.atlasTarget, tile: tile.toJSON(), art: box.toJSON() };
                }
            }
            return null;
        });
        assert(roof, `${prefix}: solid roof test point exists over upper neighbour`);
        check(`${prefix}: visible roof hit belongs to the painted village`, roof.hit === 'players:3', roof);
        await page.touchscreen.tap(roof.x, roof.y);
        await page.locator('.atlas-target-actions:not([hidden])').waitFor();
        check(`${prefix}: roof tap selects the lower village`, (await page.locator('.atlas-village-banner>strong').textContent()).includes('LowerWaterVillage'));
        await page.screenshot({ path: path.join(output, `${baseline ? 'baseline-' : ''}${prefix}-roof.png`) });
        check(`${prefix}: no gameplay writes`, writes.length === 0, writes);
        check(`${prefix}: no browser errors or missing assets`, errors.length === 0 && assets.length === 0, { errors, assets });
        console.log(`${baseline ? 'BASELINE' : 'CHECK'} ${prefix}: ` + JSON.stringify(checks.filter(row => row.label.startsWith(prefix))));
    } catch (error) {
        fs.writeFileSync(path.join(output, baseline ? 'baseline-report.json' : 'report.json'), JSON.stringify({ baseline, checks, error: String(error), physicalDevice: false }, null, 2));
        await page.screenshot({ path: path.join(output, `${prefix}-failure.png`) }).catch(() => {});
        throw error;
    } finally { await page.unrouteAll({ behavior: 'ignoreErrors' }); await context.close(); }
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const port = await new Promise(resolve => { const server = net.createServer(); server.listen(0, '127.0.0.1', () => { const port = server.address().port; server.close(() => resolve(port)); }); });
    const fixture = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['tools/preview-feature-fixture.php', '--port=' + port, '--appearance'], { cwd: root, stdio: ['pipe', 'pipe', 'pipe'], windowsHide: true });
    let log = '', browser;
    fixture.stdout.on('data', data => log += data); fixture.stderr.on('data', data => log += data);
    try {
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => { clearInterval(poll); reject(Error(log || 'Fixture timeout')); }, 90000);
            const poll = setInterval(() => { if (log.includes('Synthetic preview ready')) { clearTimeout(timer); clearInterval(poll); resolve(); } else if (fixture.exitCode !== null) { clearTimeout(timer); clearInterval(poll); reject(Error(log)); } }, 100);
        });
        browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
        for (const [width, height] of [[1280, 800], [390, 844], [844, 390]]) await checkViewport(browser, 'http://127.0.0.1:' + port, width, height);
        fs.writeFileSync(path.join(output, baseline ? 'baseline-report.json' : 'report.json'), JSON.stringify({ baseline, checks, physicalDevice: false }, null, 2));
        assert.deepEqual(checks.filter(row => !row.pass), [], 'Map popup gesture regressions');
        console.log('PASS actual app map popup: stable mouse hit areas, delayed native touch across polling, adjacent village roofs, no gameplay writes; desktop, portrait and landscape.');
    } finally {
        await browser?.close();
        if (fixture.exitCode === null) { fixture.stdin.end('\n'); await new Promise(resolve => fixture.once('exit', resolve)); }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
