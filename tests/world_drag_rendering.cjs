'use strict';
// Production map and artwork on a static fixture: no account, PHP or database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');

function fixture() {
    const state = {
        world: { id: 1 },
        city: { id: 1, world_id: 1, coord_x: 64, coord_y: 64, castle_level: 5, city_skin: 'default' },
        players: [{ id: 2, coord_x: 67, coord_y: 64, city_skin: 'default', username: 'Initial player' }],
        monsters: [{ id: 1, coord_x: 65, coord_y: 64, hp_current: 100, hp_max: 100,
            definition: { name: 'Magdar', art: 'monsters/2.5d/bright-v2/magdar', type: 'rally', level: 3 } }],
        nodes: [], marches: []
    };
    return `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Union of Kingdoms map gesture regression</title>
    ${['fantasy-fonts', 'world-map', 'world-atlas', 'map-overlay', 'castle-skins', 'world-zones', 'world-encounters', 'village-theme'].map(name => `<link rel="stylesheet" href="/assets/css/${name}.css">`).join('')}
    <style>body{margin:0}#map{height:100dvh}</style><body class="mobile-game playfield-mode world-mode"><main id="map"></main>
    <script>window.ConquerTerrainData=${fs.readFileSync(path.join(root, 'data/world_terrain.json'), 'utf8')};window.fixtureState=${JSON.stringify(state)};</script>
    ${['castle-skins', 'world-landscape', 'world-encounters', 'world-map'].map(name => `<script src="/assets/js/${name}.js"></script>`).join('')}
    <script>
    window.options={host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:m=>m.definition.art,now:()=>Date.now(),state:fixtureState};
    window.publishSnapshot=(name,extraId=null,worldId=1)=>{
        const state=structuredClone(fixtureState);state.world.id=worldId;state.city.world_id=worldId;
        state.players[0].username=name;if(extraId)state.players.push({id:extraId,coord_x:64,coord_y:66,city_skin:'default',username:name+' extra'});
        window.options={...options,state};ConquerWorld.render(options);
    };
    ConquerWorld.render(options);ConquerWorld.focus(64,64);
    </script></body></html>`;
}

const frame = page => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
const center = page => page.evaluate(() => ConquerWorld.getCenter());
const playerName = page => page.locator('[data-atlas-target="players:2"] .name-frame-label').textContent();
const spriteStamp = page => page.locator('[data-painted="magdar"] canvas').evaluate(canvas => canvas.paintStamp);

async function beginDrag(page) {
    const point = await page.locator('.atlas-viewport').evaluate(el => {
        const r = el.getBoundingClientRect();
        for (const fy of [.72, .5, .3]) for (const fx of [.3, .7, .5]) {
            const x = r.left + r.width * fx, y = r.top + r.height * fy;
            const hit = document.elementFromPoint(x, y);
            if (hit?.closest('.atlas-viewport') && !hit.closest('.atlas-zoom,.atlas-minimap')) return { x, y };
        }
        throw new Error('No exposed map point');
    });
    await page.mouse.move(point.x, point.y);
    await page.mouse.down();
    return { ...point, pointerId: await page.evaluate(() => lastMapPointer) };
}

async function burst(page, point, dx, dy = 0, count = 24, release = null) {
    return page.evaluate(({ point, dx, dy, count, release }) => {
        const el = document.querySelector('.atlas-viewport'), before = terrainPasses;
        for (let i = 1; i <= count; i++) el.dispatchEvent(new PointerEvent('pointermove', {
            bubbles: true, cancelable: true, pointerId: point.pointerId, pointerType: 'mouse', buttons: 1,
            clientX: point.x + dx * i / count, clientY: point.y + dy * i / count
        }));
        const synchronousPasses = terrainPasses - before;
        if (release) el.dispatchEvent(new PointerEvent(release, {
            bubbles: true, pointerId: point.pointerId, pointerType: 'mouse', button: 0,
            clientX: point.x + dx, clientY: point.y + dy
        }));
        return { synchronousPasses, totalPasses: terrainPasses - before, center: ConquerWorld.getCenter() };
    }, { point, dx, dy, count, release });
}

async function checkViewport(browser, width, height, origin) {
    const page = await browser.newPage({ viewport: { width, height }, hasTouch: true });
    const errors = [], assetFailures = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 400 && response.url().includes('/assets/')) assetFailures.push(response.url()); });
    await page.addInitScript(() => {
        window.terrainPasses = 0;
        window.lastMapPointer = null;
        window.addedTargets = [];
        const clear = CanvasRenderingContext2D.prototype.clearRect;
        CanvasRenderingContext2D.prototype.clearRect = function (...args) {
            if (this.canvas.classList.contains('atlas-terrain')) terrainPasses++;
            return clear.apply(this, args);
        };
        document.addEventListener('pointerdown', event => { if (event.target.closest('.atlas-viewport')) lastMapPointer = event.pointerId; }, true);
        new MutationObserver(records => {
            for (const record of records) for (const node of record.addedNodes) {
                if (node.dataset?.atlasTarget) addedTargets.push(node.dataset.atlasTarget);
            }
        }).observe(document, { childList: true, subtree: true });
    });
    try {
        await page.goto(origin);
        await page.waitForFunction(() => {
            const canvas = document.querySelector('[data-painted="magdar"] canvas');
            return canvas?.paintStamp?.includes(':true:');
        });
        await frame(page);

        // A large input burst exceeds the buffer several times, but must not
        // synchronously rebuild terrain for each event. Release flushes all input.
        for (const release of ['pointerup', 'pointercancel']) {
            await page.evaluate(() => ConquerWorld.focus(64, 64));
            await frame(page);
            const point = await beginDrag(page);
            const result = await burst(page, point, 440, 88, 40, release);
            await page.mouse.up();
            console.log(`${width}x${height} ${release}: ${result.synchronousPasses} terrain passes during 40 input events, ${result.totalPasses} including release`);
            assert.equal(result.synchronousPasses, 0, release + ': input is deferred to a display frame');
            assert(result.totalPasses <= 1, release + ': one final terrain render');
            assert.equal(result.center.x, 54, release + ': retains complete horizontal delta');
            assert.equal(result.center.y, 62, release + ': retains complete vertical delta');
            assert.equal(await page.locator('.atlas-viewport.is-dragging').count(), 0, release + ': clears gesture style');
        }

        await page.evaluate(() => ConquerWorld.focus(64, 64));
        await frame(page);
        const frameStart = await page.evaluate(() => terrainPasses);
        let scheduledPoint = await beginDrag(page);
        const scheduled = await burst(page, scheduledPoint, 440);
        assert.equal(scheduled.synchronousPasses, 0, 'live pan input does not repaint synchronously');
        await frame(page);
        assert((await page.evaluate(() => terrainPasses)) - frameStart <= 1, 'a display frame coalesces the whole burst into one terrain pass');
        assert.equal((await center(page)).x, 54, 'scheduled frame retains full camera delta');
        await page.mouse.up();
        await page.evaluate(() => ConquerWorld.focus(64, 64));
        await frame(page);
        let point = await beginDrag(page);
        await burst(page, point, 18);
        await frame(page);
        const frozen = await spriteStamp(page);
        await page.waitForTimeout(180);
        assert.equal(await spriteStamp(page), frozen, 'decorative sprite drawing pauses during pan');
        await page.evaluate(() => {
            addedTargets.length = 0;
            publishSnapshot('First response', 3);
            publishSnapshot('Latest response', 4);
        });
        assert.equal(await playerName(page), 'Initial player', 'server response does not rebuild markers during drag');
        assert.equal(await page.locator('[data-atlas-target="players:4"]').count(), 0, 'latest new marker remains deferred');
        await page.mouse.up();
        await frame(page);
        assert.equal(await playerName(page), 'Latest response', 'latest queued server snapshot appears on release');
        assert.equal(await page.locator('[data-atlas-target="players:4"]').count(), 1);
        assert.equal(await page.evaluate(() => addedTargets.includes('players:3')), false, 'superseded snapshot never creates its marker');
        await page.waitForFunction(stamp => document.querySelector('[data-painted="magdar"] canvas')?.paintStamp !== stamp, frozen);

        // A cancelled gesture also commits pending state and restores animation.
        point = await beginDrag(page);
        await burst(page, point, 20);
        await page.evaluate(() => publishSnapshot('After cancellation'));
        await page.evaluate(point => document.querySelector('.atlas-viewport').dispatchEvent(new PointerEvent('pointercancel', {
            bubbles: true, pointerId: point.pointerId, pointerType: 'mouse', clientX: point.x + 20, clientY: point.y
        })), point);
        await page.mouse.up();
        await frame(page);
        assert.equal(await playerName(page), 'After cancellation', 'cancel flushes the latest snapshot');

        // Losing browser capture can happen without pointerup. The next browser
        // pointer event processes that loss while the physical button is held.
        point = await beginDrag(page);
        await page.mouse.move(point.x + 1, point.y);
        await burst(page, point, 20);
        await page.evaluate(point => {
            publishSnapshot('After capture loss');
            document.querySelector('.atlas-viewport').releasePointerCapture(point.pointerId);
        }, point);
        await page.mouse.move(point.x + 25, point.y);
        await frame(page);
        assert.equal(await playerName(page), 'After capture loss', 'lost capture flushes pending state before mouseup');
        assert.equal(await page.locator('.atlas-viewport.is-dragging').count(), 0, 'lost capture clears gesture state');
        await page.evaluate(() => publishSnapshot('Capture recovery remains live'));
        assert.equal(await playerName(page), 'Capture recovery remains live', 'lost pointer cannot indefinitely defer future snapshots');
        await page.mouse.up();

        // Device gestures use real touch pointers, including capture and lift order.
        const session = await page.context().newCDPSession(page);
        const bounds = await page.locator('.atlas-viewport').boundingBox();
        const x = bounds.x + bounds.width / 2, y = bounds.y + bounds.height * .5;
        const zoomBefore = await page.locator('.atlas-zoom-value').textContent();
        await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ id: 10, x: x - 30, y }, { id: 11, x: x + 30, y }] });
        await session.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ id: 10, x: x - 42, y }, { id: 11, x: x + 42, y }] });
        await page.evaluate(() => publishSnapshot('After pinch'));
        await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await frame(page);
        assert(parseInt(await page.locator('.atlas-zoom-value').textContent()) > parseInt(zoomBefore), 'pinch retains its final zoom');
        assert.equal(await playerName(page), 'After pinch', 'pinch release flushes pending state');
        assert.equal(await page.locator('.atlas-target-actions:visible').count(), 0, 'pinch never opens a target');
        await session.detach();

        // If the scene disappears before pointerup, it must not retain a phantom
        // gesture that indefinitely prevents new snapshots when reopened.
        point = await beginDrag(page);
        await burst(page, point, 20);
        await page.evaluate(() => {
            publishSnapshot('After reopening');
            ConquerWorld.setVisible(false);
            ConquerWorld.setVisible(true);
        });
        await frame(page);
        assert.equal(await playerName(page), 'After reopening', 'hide/reopen cannot strand queued state');
        assert.equal(await page.locator('.atlas-viewport.is-dragging').count(), 0, 'hide clears gesture style');
        await page.mouse.up();

        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.waitForTimeout(100);
        point = await beginDrag(page);
        const beforeReducedPan = await center(page);
        await burst(page, point, 88);
        await page.evaluate(() => publishSnapshot('Reduced motion response'));
        await page.mouse.up();
        await frame(page);
        assert((await center(page)).x < beforeReducedPan.x, 'reduced motion keeps manual panning responsive');
        assert.equal(await playerName(page), 'Reduced motion response');
        const reducedStamp = await spriteStamp(page);
        await page.waitForTimeout(150);
        assert.equal(await spriteStamp(page), reducedStamp, 'reduced motion stays still after releasing');
        await page.emulateMedia({ reducedMotion: 'no-preference' });

        // Moving the destination preview is a placement gesture, not a camera
        // gesture. Its exact coordinates and state updates must survive release.
        await page.evaluate(() => {
            options = { ...options, teleport: { item_code: 401001, mode: 'advanced', origin_x: 64, origin_y: 64, footprint: 4 } };
            ConquerWorld.render(options);
            ConquerWorld.focus(90, 90);
        });
        await page.locator('.atlas-viewport').press('Enter');
        const preview = page.locator('.atlas-cell-focus.is-teleport-preview');
        await preview.waitFor({ state: 'visible' });
        const placement = await preview.evaluate(el => {
            const r = el.getBoundingClientRect();
            for (const fy of [.3, .5, .7]) for (const fx of [.3, .5, .7]) {
                const x = r.left + r.width * fx, y = r.top + r.height * fy;
                if (document.elementFromPoint(x, y)?.closest('.atlas-cell-focus') === el) return { x, y, tile: r.width / 4, cellX: Number(el.dataset.x), cellY: Number(el.dataset.y) };
            }
            throw new Error('Teleport preview has no exposed touch point');
        });
        const placementCamera = await center(page);
        await page.mouse.move(placement.x, placement.y);
        await page.mouse.down();
        point = { ...placement, pointerId: await page.evaluate(() => lastMapPointer) };
        await burst(page, point, placement.tile * 2, placement.tile, 6);
        assert.equal(await page.locator('.atlas-viewport.is-teleport-dragging').count(), 1, 'preview enters placement dragging');
        assert.equal(await page.locator('.atlas-shell.is-camera-moving').count(), 0, 'placement is not treated as camera dragging');
        assert.deepEqual(await center(page), placementCamera, 'preview drag leaves camera stationary');
        assert.equal(Number(await preview.getAttribute('data-x')), placement.cellX + 2, 'preview receives full horizontal tile delta');
        assert.equal(Number(await preview.getAttribute('data-y')), placement.cellY + 1, 'preview receives full vertical tile delta');
        await page.evaluate(() => publishSnapshot('Teleport response'));
        await page.mouse.up();
        await frame(page);
        assert.equal(await playerName(page), 'Teleport response', 'placement release applies queued state');
        assert.equal(await page.locator('.atlas-viewport.is-teleport-dragging').count(), 0, 'placement release clears gesture style');
        assert.equal(Number(await preview.getAttribute('data-x')), placement.cellX + 2, 'snapshot does not reset chosen destination');
        assert.deepEqual(await center(page), placementCamera, 'snapshot does not move placement camera');
        await page.evaluate(() => { options = { ...options, teleport: null }; ConquerWorld.render(options); ConquerWorld.focus(64, 64); });

        // A new world must never wait behind stale pointers from the old world.
        point = await beginDrag(page);
        await burst(page, point, 20);
        await page.evaluate(() => { publishSnapshot('Old world pending'); publishSnapshot('New world immediately', null, 2); });
        assert.equal(await playerName(page), 'New world immediately', 'world switch bypasses old gesture deferral');
        await page.mouse.up();
        await frame(page);
        assert.equal(await playerName(page), 'New world immediately', 'release cannot restore stale world state');
        assert.deepEqual(errors, [], 'no browser errors');
        assert.deepEqual(assetFailures, [], 'all production artwork loads');
        console.log(`PASS ${width}x${height}: coalesced pan, complete up/cancel delta, latest snapshot, paused decorations, capture loss, pinch, reopen, reduced motion, teleport and world switch`);
    } finally {
        await page.close();
    }
}

(async () => {
    const server = http.createServer((request, response) => {
        const pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
        if (pathname === '/') { response.setHeader('Content-Type', 'text/html'); response.end(fixture()); return; }
        let file = path.resolve(root, '.' + pathname);
        if (!pathname.startsWith('/assets/') || !file.startsWith(root + path.sep) || !fs.existsSync(file)) { response.writeHead(404); response.end(); return; }
        if (process.env.WORLD_DRAG_BASELINE_DIR && ['/assets/js/world-map.js', '/assets/js/world-painted.js', '/assets/css/village-theme.css'].includes(pathname)) {
            file = path.resolve(process.env.WORLD_DRAG_BASELINE_DIR, path.basename(pathname));
        }
        response.setHeader('Content-Type', ({ '.js': 'text/javascript', '.mjs': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png', '.webp': 'image/webp' })[path.extname(file)] || 'application/octet-stream');
        response.end(fs.readFileSync(file));
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'msedge', headless: true });
        for (const [width, height] of [[1280, 800], [390, 844], [844, 390]]) await checkViewport(browser, width, height, `http://127.0.0.1:${server.address().port}`);
    } finally {
        await browser?.close();
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
