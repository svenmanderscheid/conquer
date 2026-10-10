'use strict';
// Real shared app with a disposable database; dense village coordinates are read-only response fixtures.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const output = path.join(root, 'output/playwright/map-nameplates');
const longName = 'A deliberately long village owner name';
const players = [
    [2, 'Moon', -4, 4, 'default', null],
    [3, 'Explorer123', 0, 4, 'forest', null],
    [4, 'Ekki', 4, 4, 'water', 'SOL'],
    [5, 'UntisMan', 8, 4, 'fire', 'SOL'],
    [6, 'Keeeesuuuu', -4, 8, 'water', null],
    [7, 'Coolman', 0, 8, 'default', null],
    [8, 'Luxifer', 4, 8, 'wind', 'SOD'],
    [9, 'Michelkov98', 8, 8, 'default', null],
    [10, longName, 0, 12, 'forest', 'LONG']
];

async function exposedPoint(locator) {
    return locator.evaluate(node => {
        const r = node.getBoundingClientRect();
        for (const fy of [.5, .3, .7, .15, .85]) for (const fx of [.5, .3, .7, .15, .85]) {
            const x = r.width * fx, y = r.height * fy;
            if (node.contains(document.elementFromPoint(r.left + x, r.top + y))) return { x, y };
        }
        return null;
    });
}

async function settle(page) {
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
}

async function checkViewport(browser, base, width, height) {
    const page = await browser.newPage({ viewport: { width, height }, hasTouch: true });
    const selectedName = width <= 390 ? longName.replaceAll(' ', '') : longName;
    const errors = [], failures = [], metrics = [];
    page.setDefaultTimeout(15000);
    page.setDefaultNavigationTimeout(60000);
    page.on('pageerror', e => errors.push(e.message));
    page.on('response', response => {
        if (response.status() >= 400 && new URL(response.url()).pathname.startsWith('/assets/')) failures.push(`${response.status()} ${response.url()}`);
    });
    await page.route('**/api/game/state**', async route => {
        const response = await route.fetch(), body = await response.json();
        if (body.data?.city) {
            const state = body.data, home = state.city;
            state.players = players.map(([id, username, dx, dy, frame, tag]) => ({
                id, username: id === 10 ? selectedName : username, coord_x: +home.coord_x + dx, coord_y: +home.coord_y + dy,
                castle_level: id % 5 + 1, city_skin: frame, name_frame: frame,
                alliance_id: tag ? (tag === 'SOL' ? 1 : 2) : null, alliance_tag: tag
            }));
            state.monsters = []; state.nodes = []; state.marches = [];
        }
        await route.fulfill({ response, json: body });
    });
    try {
        await page.goto(base + '/?zugang=login');
        await page.locator('[name="identifier"], [name="username"]').fill('PreviewPlayer');
        await page.locator('[name="password"]').fill('PreviewFixture!2026');
        await Promise.all([page.waitForURL('**/city'), page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);
        await page.goto(base + '/city#world');
        await page.locator('[data-atlas-target="players:3"] .name-frame-label').waitFor();
        await page.waitForFunction(() => !document.querySelector('.scene-transition.is-active'));
        await page.evaluate(() => document.fonts.ready);
        const collapse = page.locator('.world-march-heading[aria-expanded="true"]');
        if (await collapse.isVisible()) await collapse.click();
        const home = await page.locator('[data-atlas-target="home"]').evaluate(node => ({ x: +node.dataset.x, y: +node.dataset.y }));
        const center = { x: home.x + 2, y: home.y + 6 };
        const viewport = page.locator('.atlas-viewport');
        const defaultZoom = await page.locator('.atlas-zoom-value').textContent();
        for (const zoom of ['default', 'minimum', 'maximum']) {
            if (zoom !== 'default') for (let i = 0; i < 24; i++) await viewport.press(zoom === 'minimum' ? '-' : '+');
            await page.evaluate(p => ConquerWorld.focus(p.x, p.y), center);
            await settle(page);
            const labels = await page.locator('.atlas-marker--home,.atlas-marker--players').evaluateAll(nodes => nodes.filter(node => !node.hidden).map(node => {
                const frame = node.querySelector('.atlas-marker-name'), label = frame.querySelector('.name-frame-label');
                const rect = frame.getBoundingClientRect(), text = label.getBoundingClientRect(), style = getComputedStyle(label);
                return {
                    key: node.dataset.atlasTarget, frame: frame.dataset.nameFrame,
                    rect: rect.toJSON(), text: text.toJSON(), fontSize: parseFloat(style.fontSize),
                    visibility: getComputedStyle(frame).visibility, opacity: getComputedStyle(frame).opacity,
                    title: label.title, name: label.textContent, aria: node.getAttribute('aria-label'),
                    clientWidth: label.clientWidth, scrollWidth: label.scrollWidth
                };
            }));
            assert(labels.length >= 2, `${width}x${height} ${zoom}: dense cluster present`);
            for (const label of labels) {
                assert(label.rect.width > 0 && label.rect.height > 0 && label.visibility !== 'hidden' && +label.opacity > 0, JSON.stringify(label));
                assert(label.fontSize >= 11, `${width} ${zoom}: name stays readable`);
                assert(label.text.left >= label.rect.left - 1 && label.text.right <= label.rect.right + 1, `${width} ${zoom}: text stays inside frame`);
                assert(label.text.top >= label.rect.top - 1 && label.text.bottom <= label.rect.bottom + 1, `${width} ${zoom}: text stays inside frame vertically`);
                assert.equal(label.title, label.name, 'Full name retained in title');
                if (label.key !== 'home') {
                    const source = players.find(player => 'players:' + player[0] === label.key);
                    assert.equal(label.frame, source[4], 'Equipped frame identity retained');
                    assert(label.aria.includes(source[0] === 10 ? selectedName : source[1]), 'Full player name retained for accessibility');
                }
            }
            for (let i = 0; i < labels.length; i++) for (let j = i + 1; j < labels.length; j++) {
                const a = labels[i], b = labels[j];
                assert(!(a.rect.left < b.rect.right - .5 && a.rect.right > b.rect.left + .5 && a.rect.top < b.rect.bottom - .5 && a.rect.bottom > b.rect.top + .5), `${width}x${height} ${zoom}: overlap ${a.name} / ${b.name}`);
            }
            if (zoom === 'default') {
                for (const label of labels.filter(item => ['Moon', 'Explorer123', 'Coolman'].includes(item.name))) {
                    assert(label.scrollWidth <= label.clientWidth + 1, `${label.name} fits at default zoom`);
                }
            }
            metrics.push({ zoom, percentage: await page.locator('.atlas-zoom-value').textContent(), labels });
            await page.screenshot({ path: path.join(output, `${width}x${height}-${zoom}.png`) });
        }
        assert.equal(metrics[0].percentage, defaultZoom);
        assert.equal(metrics[1].percentage, Math.min(width, height) <= 600 ? '25%' : '40%');
        // Reset the camera to a useful selection scale using a fresh world page.
        await page.reload();
        const target = page.locator('[data-atlas-target="players:10"]');
        await target.waitFor({ state: 'attached' });
        const coords = await target.evaluate(node => ({ x: +node.dataset.x, y: +node.dataset.y }));
        await page.evaluate(p => ConquerWorld.focus(p.x, p.y), coords);
        await settle(page);
        const touch = await exposedPoint(target);
        assert(touch, `${width}x${height}: castle remains reachable by touch`);
        await target.tap({ position: touch });
        const menu = page.locator('.atlas-target-actions');
        await menu.waitFor();
        assert.equal(await menu.locator('.atlas-village-banner>strong').textContent(), '[LONG] ' + selectedName);
        const fullName = await menu.locator('.atlas-village-banner>strong').evaluate(node => {
            const text = node.getBoundingClientRect(), banner = node.closest('.atlas-village-banner').getBoundingClientRect();
            return { text: text.toJSON(), banner: banner.toJSON(), clientWidth: node.clientWidth, scrollWidth: node.scrollWidth, clientHeight: node.clientHeight, scrollHeight: node.scrollHeight };
        });
        assert(fullName.scrollWidth <= fullName.clientWidth + 1 && fullName.scrollHeight <= fullName.clientHeight + 1, `${width}x${height}: selected full name is visibly readable, not clipped`);
        assert(fullName.text.left >= fullName.banner.left && fullName.text.right <= fullName.banner.right && fullName.text.top >= fullName.banner.top && fullName.text.bottom <= fullName.banner.bottom, `${width}x${height}: full name stays inside selected banner`);
        assert(fullName.text.left >= 0 && fullName.text.right <= width && fullName.text.top >= 0 && fullName.text.bottom <= height, `${width}x${height}: full selected name stays on screen`);
        await page.screenshot({ path: path.join(output, `${width}x${height}-selected.png`) });
        await menu.locator('[data-atlas="clear"]').tap();
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No page overflow');

        if ([[1280, 800], [390, 844], [844, 390]].some(size => size[0] === width && size[1] === height)) {
            await page.goto(base + '/city#city');
            await page.waitForSelector('.painted-village-building[data-id="castle"]');
            await page.waitForFunction(() => !document.querySelector('.scene-transition.is-active'));
            await page.screenshot({ path: path.join(output, `${width}x${height}-city.png`) });
            const castle = page.locator('.painted-village-building[data-id="castle"]');
            await castle.evaluate(node => node.scrollIntoView({ block: 'center', inline: 'center' }));
            const point = await exposedPoint(castle);
            assert(point, 'City castle action is reachable');
            await castle.tap({ position: point });
            await page.locator('.painted-building-actions').waitFor();
            await page.screenshot({ path: path.join(output, `${width}x${height}-city-action.png`) });
            await page.locator('.painted-selection-close').tap();
        }
        assert.deepEqual(errors, [], 'No browser errors');
        assert.deepEqual(failures, [], 'Artwork and styles load');
        fs.writeFileSync(path.join(output, `${width}x${height}.json`), JSON.stringify(metrics, null, 2));
        console.log(`PASS ${width}x${height}: dense nameplates at default/min/max zoom, preserved names/frames, touch selection, no browser errors`);
    } catch (error) {
        await page.screenshot({ path: path.join(output, `${width}x${height}-failure.png`) });
        throw error;
    } finally {
        await page.unrouteAll({ behavior: 'ignoreErrors' });
        await page.close();
    }
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const socket = net.createServer();
    await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const fixture = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['tools/preview-feature-fixture.php', '--port=' + port, '--appearance', '--gathering'], { cwd: root, stdio: ['pipe', 'pipe', 'pipe'], windowsHide: true });
    let log = '', browser;
    fixture.stdout.on('data', data => log += data);
    fixture.stderr.on('data', data => log += data);
    try {
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => { clearInterval(poll); reject(new Error(log || 'Fixture timeout')); }, 90000);
            const poll = setInterval(() => {
                if (log.includes('Synthetic preview ready')) { clearTimeout(timer); clearInterval(poll); resolve(); }
                else if (fixture.exitCode !== null) { clearTimeout(timer); clearInterval(poll); reject(new Error(log)); }
            }, 100);
        });
        browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'msedge' });
        for (const [width, height] of [[1280, 800], [390, 844], [320, 568], [844, 390], [568, 320]]) await checkViewport(browser, 'http://127.0.0.1:' + port, width, height);
        console.log('PASS actual app nameplates; screenshots: ' + output);
    } finally {
        await browser?.close();
        fixture.stdin.end('\n');
        await new Promise(resolve => { if (fixture.exitCode !== null) resolve(); else fixture.once('exit', resolve); });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
