'use strict';
// Actual main app; disposable database and synthetic read snapshots, no gameplay writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const {spawn} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const out = path.join(root, 'output/playwright/rally-notifications');
fs.mkdirSync(out, {recursive:true});
for (const name of ['report.json', 'failure.png']) fs.rmSync(path.join(out, name), {force:true});

(async () => {
    const port = await new Promise(resolve => {
        const server = net.createServer();
        server.listen(0, '127.0.0.1', () => {
            const chosen = server.address().port;
            server.close(() => resolve(chosen));
        });
    });
    const fixture = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [
        root + '/tools/preview-feature-fixture.php', '--port=' + port, '--hud', '--chat'
    ], {cwd:root, stdio:['pipe', 'pipe', 'pipe'], windowsHide:true});
    let browser, page, log = '', rallyReads = 0, stateReads = 0, extraEvents = false;
    const writes = [], errors = [], checks = [];
    const utc = delta => new Date(Date.now() + delta).toISOString().slice(0, 19).replace('T', ' ');
    const rally = (id, leaderId = 2, values = {}) => ({
        id, world_id:1, leader_player_id:leaderId,
        leader:{name:leaderId === 1 ? 'PreviewPlayer' : 'Elara', avatar:'knight', coord_x:65, coord_y:65},
        target_kind:'monster', target_name:'Grumwald', target_x:75, target_y:75,
        status:'gathering', created_at:utc(0), launch_at:utc(300000), arrival_time:utc(600000),
        result:{alliance_id:1, monster:{name:'Grumwald', art:'monsters/storybook-v2/grumwald', level:1}},
        participants:[], capacity:1000, troop_count:100, troops:{50100101:100}, ...values
    });
    let rallies = [rally(101, 1), rally(102, 2)];
    try {
        await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(Error(log || 'Preview timeout')), 60000);
            fixture.stdout.on('data', data => {log += data;if (log.includes('Synthetic preview ready')) {clearTimeout(timeout);resolve();}});
            fixture.stderr.on('data', data => log += data);
            fixture.on('error', reject);
            fixture.on('exit', () => {clearTimeout(timeout);reject(Error(log));});
        });
        browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_EXECUTABLE_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
        const context = await browser.newContext({viewport:{width:1280, height:800}, hasTouch:true, locale:'en-US', reducedMotion:'reduce'});
        await context.addInitScript(() => {
            if (!localStorage.getItem('conquer.locale')) localStorage.setItem('conquer.locale', 'en');
        });
        page = await context.newPage();
        page.setDefaultTimeout(25000);
        page.on('pageerror', error => errors.push(error.message));
        page.on('request', request => {
            if (!['GET', 'HEAD'].includes(request.method()) && request.url().includes('/api/')) writes.push(request.method() + ' ' + request.url());
        });
        await page.route('**/api/rally/list*', route => {
            rallyReads++;
            return route.fulfill({json:{ok:true, data:{rallies}}});
        });
        await page.route('**/api/game/state*', async route => {
            stateReads++;
            const response = await route.fetch(), json = await response.json();
            json.data.extra_events = extraEvents ? [{id:1, target:'events', icon:'events', name:'Harvest Festival', description:'Synthetic event', ends_at:utc(3600000)}] : [];
            await route.fulfill({response, json});
        });
        const base = 'http://127.0.0.1:' + port;
        await page.goto(base + '/?zugang=login');
        await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');
        await page.locator('[name=password]').fill('PreviewFixture!2026');
        await Promise.all([page.waitForURL('**/city'), page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
        await page.locator('.painted-village').waitFor();
        await page.locator('#hud-alliance-rallies').waitFor();
        await page.waitForFunction(() => document.querySelector('#save-state').dataset.connection === 'saved');
        assert.equal(await page.locator('#hud-rally-count').innerText(), '2', 'The HUD includes the leader and another alliance member');
        assert.equal(await page.locator('#toast.visible[data-rally-notice=true]').count(), 0, 'An initial snapshot is silent');

        async function refreshRallies() {
            await Promise.all([
                page.waitForResponse(response => response.url().includes('/api/rally/list') && response.ok()),
                page.evaluate(() => dispatchEvent(new CustomEvent('conquer-rally-updated')))
            ]);
            await page.waitForTimeout(100);
        }
        async function refreshGame() {
            await page.locator('#hud-menu').click();
            await Promise.all([
                page.waitForResponse(response => response.url().includes('/api/game/state') && response.ok()),
                page.locator('[data-action=connection-refresh]').click()
            ]);
            await page.waitForFunction(() => document.querySelector('#save-state').dataset.connection === 'saved');
        }
        async function expectNotice(expected) {
            await page.waitForFunction(value => {
                const toast = document.querySelector('#toast');
                return toast?.dataset.rallyNotice === 'true' && toast.classList.contains('visible') && toast.textContent === value;
            }, expected);
            assert.equal(await page.locator('#toast').getAttribute('role'), 'status');
            assert.equal(await page.locator('#toast').getAttribute('aria-live'), 'polite');
        }
        async function closeDialog() {
            const back = page.locator('#game-dialog .mobile-page-back');
            if (await back.isVisible()) await back.tap();
            else await page.locator('#game-dialog .dialog-close').click();
            await page.waitForFunction(() => !document.querySelector('#game-dialog').open);
            await page.waitForTimeout(100);
        }
        await page.locator('#hud-alliance-rallies').tap();
        await page.locator('.rally-list').waitFor();
        assert.equal(await page.locator('.rally-card').count(), 2);
        assert.equal(await page.locator('[data-rally-id="101"] [data-action=rally-join]').getAttribute('aria-label'), 'Your rally');
        assert.equal(await page.locator('[data-rally-id="101"] [data-action=rally-join]').isDisabled(), true);
        assert.equal(await page.locator('[data-rally-id="102"] [data-action=rally-join]').isEnabled(), true);
        await closeDialog();
        await page.evaluate(() => {
            window.rallyTestNotices = [];
            const toast = document.querySelector('#toast');
            new MutationObserver(records => {
                if (records.some(record => record.type === 'childList') && toast.dataset.rallyNotice === 'true') window.rallyTestNotices.push(toast.textContent);
            }).observe(toast, {childList:true});
        });
        rallies.push(rally(103));
        await refreshRallies();
        await expectNotice('Elara started a rally against Grumwald.');
        assert.equal(await page.locator('#hud-rally-count').innerText(), '3');
        const firstNotices = await page.evaluate(() => window.rallyTestNotices.length);
        await refreshRallies();
        assert.equal(await page.evaluate(() => window.rallyTestNotices.length), firstNotices, 'Repeated event snapshots do not repeat the toast');
        await page.waitForFunction(() => !document.querySelector('#toast').classList.contains('visible'), null, {timeout:6500});
        rallies.find(row => row.id === 103).status = 'marching';
        await refreshRallies();
        assert.equal(await page.locator('#toast.visible[data-rally-notice=true]').count(), 0, 'A gathering-to-marching update does not replay an existing rally');
        assert.equal(await page.evaluate(() => window.rallyTestNotices.length), firstNotices);
        checks.push({behavior:'initial silence, own and member visibility, list opening, repeat and phase-change suppression'});

        rallies.push(rally(104, 1));
        await refreshGame();
        await expectNotice('PreviewPlayer started a rally against Grumwald.');
        assert.equal(await page.locator('#hud-rally-count').innerText(), '4', 'Manual state refresh includes a fresh leader-owned rally');
        checks.push({behavior:'manual read-only game refresh shows the leader notice'});

        let nextId = 200;
        for (const extra of [false, true]) {
            extraEvents = extra;
            await refreshGame();
            for (const scene of ['city', 'world']) {
                if (!await page.locator('body').evaluate((body, name) => body.classList.contains(name + '-mode'), scene)) {
                    await page.locator('#navigation [data-id=' + scene + ']').tap();
                }
                await page.locator(scene === 'city' ? '.painted-village' : '.atlas-viewport').waitFor();
                await page.waitForFunction(() => !document.querySelector('.scene-transition.is-active'));
                for (const [width, height] of [[1280,800], [390,844], [320,568], [844,390], [568,320]]) {
                    await page.setViewportSize({width, height});
                    await page.waitForTimeout(180);
                    await page.waitForFunction(() => !document.querySelector('#toast').classList.contains('visible'), null, {timeout:6500});
                    const own = nextId % 2 === 0;
                    rallies = [rally(nextId++, own ? 1 : 2, {status:own ? 'gathering' : 'marching'})];
                    await refreshRallies();
                    await expectNotice((own ? 'PreviewPlayer' : 'Elara') + ' started a rally against Grumwald.');
                    assert.equal(await page.locator('#hud-rally-count').innerText(), '1');
                    const geometry = await page.evaluate(({extra}) => {
                        const box = element => {const r = element.getBoundingClientRect();return {x:r.x, y:r.y, width:r.width, height:r.height, right:r.right, bottom:r.bottom};};
                        const button = document.querySelector('#hud-alliance-rallies');
                        const event = document.querySelector(extra ? '#hud-extra-event' : '.hud-right-tools [data-id=events]');
                        const toast = document.querySelector('#toast');
                        const icon = box(button), events = box(event), notice = box(toast), resources = box(document.querySelector('#resources'));
                        const center = document.elementFromPoint(icon.x + icon.width / 2, icon.y + icon.height / 2);
                        return {icon, events, notice, resources, reachable:button.contains(center), htmlOverflow:document.documentElement.scrollWidth > innerWidth + 1,
                            coveredControls:[...document.querySelectorAll('.hud-edge-button,#hud-menu')].filter(button => {
                                const r = button.getBoundingClientRect(), style = getComputedStyle(button);
                                return r.width && r.height && style.visibility !== 'hidden' && style.display !== 'none' && Number(style.opacity) !== 0
                                    && Math.min(r.right, notice.right) - Math.max(r.left, notice.x) > 1
                                    && Math.min(r.bottom, notice.bottom) - Math.max(r.top, notice.y) > 1;
                            }).map(button => button.id || button.dataset.id),
                            brokenImages:[...document.querySelectorAll('#hud-alliance-rallies img, .playfield-scene.is-active img')].filter(img => img.getClientRects().length && img.complete && !img.naturalWidth).map(img => img.src)};
                    }, {extra});
                    assert(geometry.icon.y >= geometry.events.bottom - 1 && Math.abs(geometry.icon.x - geometry.events.x) <= 2, 'Rally icon is directly below visible Events: ' + JSON.stringify({scene, extra, width, height, geometry}));
                    assert(geometry.icon.width >= 44 && geometry.icon.height >= 44, 'Rally icon has a touch-sized target');
                    assert(geometry.reachable, 'Rally icon is unobstructed');
                    assert(geometry.notice.y >= geometry.resources.bottom + 7 && geometry.notice.y <= geometry.resources.bottom + 10, 'Toast is immediately below the measured resource bar');
                    for (const rect of [geometry.icon, geometry.notice]) assert(rect.x >= 0 && rect.y >= 0 && rect.right <= width + 1 && rect.bottom <= height + 1, 'Rally feedback fits the viewport');
                    assert.equal(geometry.htmlOverflow, false);
                    assert.deepEqual(geometry.coveredControls, [], 'The notice keeps visible HUD controls uncovered: ' + JSON.stringify({scene, extra, width, height, geometry}));
                    assert.deepEqual(geometry.brokenImages, []);
                    await page.screenshot({path:path.join(out, `${extra ? 'extra-events' : 'events'}-${scene}-${width}x${height}.png`)});
                    await page.locator('#hud-alliance-rallies').tap();
                    await page.locator('.rally-list').waitFor();
                    assert.equal(await page.locator('.rally-card').count(), 1);
                    const dialogNotice = await page.evaluate(() => {
                        const toast = document.querySelector('#toast'), r = toast.getBoundingClientRect(), resources = document.querySelector('#resources').getBoundingClientRect();
                        return {visible:toast.classList.contains('visible'), top:r.top, right:r.right, bottom:r.bottom, left:r.left, resourceBottom:resources.bottom};
                    });
                    assert(dialogNotice.visible, 'Opening the rally list retains the current notice');
                    assert(dialogNotice.top >= dialogNotice.resourceBottom + 7 && dialogNotice.top <= dialogNotice.resourceBottom + 10, 'The notice keeps its screen position inside the responsive dialog: ' + JSON.stringify({width, height, dialogNotice}));
                    assert(dialogNotice.left >= 0 && dialogNotice.right <= width + 1 && dialogNotice.bottom <= height + 1, 'Dialog zoom keeps the notice within the viewport');
                    await closeDialog();
                    checks.push({scene, extraEvents:extra, width, height, ownRally:own, geometry, dialogNotice});
                }
            }
        }

        await Promise.all([page.waitForEvent('domcontentloaded'), page.evaluate(() => ConquerLocale.setLocale('de'))]);
        await page.waitForFunction(() => document.documentElement.lang === 'de' && ConquerLocale.t('rally.notice.started', {player:'Elara',monster:'Grumwald'}).includes('gestartet'));
        await page.waitForFunction(() => document.querySelector('#save-state').dataset.connection === 'saved' && !document.querySelector('#hud-alliance-rallies').hidden);
        assert.equal(await page.locator('#toast.visible[data-rally-notice=true]').count(), 0, 'Locale reload also starts with a silent baseline');
        await page.evaluate(() => {
            window.rallyTestNotices = [];
            const toast = document.querySelector('#toast');
            new MutationObserver(records => {
                if (records.some(record => record.type === 'childList') && toast.dataset.rallyNotice === 'true') window.rallyTestNotices.push(toast.textContent);
            }).observe(toast, {childList:true});
        });
        rallies = [rally(nextId++, 2)];
        await refreshRallies();
        await expectNotice('Elara hat eine Rally gegen Grumwald gestartet.');
        await page.screenshot({path:path.join(out, 'german-notice-568x320.png')});
        const playerName = '<img src=x onerror="window.rallyInjected=true"> & Elara';
        const monsterName = '<b>Grumwald</b> & Frostgrimm';
        rallies = [rally(nextId++, 2, {leader:{name:playerName}, result:{alliance_id:1,monster:{name:monsterName}}})];
        await refreshRallies();
        await expectNotice(playerName + ' hat eine Rally gegen ' + monsterName + ' gestartet.');
        assert.equal(await page.locator('#toast img, #toast b').count(), 0, 'Names stay plain text');
        assert.equal(await page.evaluate(() => Boolean(window.rallyInjected)), false);
        await page.waitForFunction(() => !document.querySelector('#toast').classList.contains('visible'), null, {timeout:6500});
        rallies = [rally(nextId++, 2), rally(nextId++, 1)];
        await refreshRallies();
        await expectNotice('Elara hat eine Rally gegen Grumwald gestartet.');
        assert.equal(await page.locator('#toast').innerText(), 'Elara hat eine Rally gegen Grumwald gestartet.', 'A fresh batch displays one compact notice at a time');
        await page.screenshot({path:path.join(out, 'batch-first-568x320.png')});
        await expectNotice('PreviewPlayer hat eine Rally gegen Grumwald gestartet.');
        const batchBox = await page.locator('#toast').boundingBox();
        assert(batchBox && batchBox.x >= 0 && batchBox.y >= 0 && batchBox.x + batchBox.width <= 568 && batchBox.y + batchBox.height <= 320, 'The second queued notice stays inside the short viewport');
        await page.screenshot({path:path.join(out, 'batch-second-568x320.png')});
        const noticeCount = await page.evaluate(() => window.rallyTestNotices.length);
        rallies.push(rally(nextId++, 2, {world_id:2}), rally(nextId++, 2, {result:{alliance_id:99,monster:{name:'Foreign'}}}));
        await refreshRallies();
        assert.equal(await page.locator('#hud-rally-count').innerText(), '2', 'Foreign world and alliance rows do not enter the HUD');
        assert.equal(await page.evaluate(() => window.rallyTestNotices.length), noticeCount, 'Foreign scope rows do not announce');
        rallies = rallies.map(row => ({...row, status:'complete'}));
        await refreshRallies();
        assert.equal(await page.locator('#hud-alliance-rallies').isHidden(), true, 'The icon disappears when no active rally remains');
        checks.push({behavior:'German notice, plain-text names, sequential compact batch notices, world/alliance scoping and completed-rally removal'});
        assert.deepEqual(writes, [], 'The entire regression uses only game reads');
        assert.deepEqual(errors, [], 'No JavaScript page errors');
        fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify({checks, rallyReads, stateReads, writes, errors, deviceCoverage:'Chromium touch emulation; no physical-device acceptance'}, null, 2));
        console.log('PASS rally notifications: own/member icon, silent baseline, new starts, dedupe, localization and safe names; 20 city/world layouts, no game writes or browser errors');
    } catch (error) {
        if (page) await page.screenshot({path:path.join(out, 'failure.png')}).catch(() => {});
        throw error;
    } finally {
        if (page && !page.isClosed()) await page.unrouteAll({behavior:'wait'});
        if (browser) await browser.close();
        if (fixture.exitCode === null) {fixture.stdin.end('\n');await new Promise(resolve => fixture.once('exit', resolve));}
    }
})().catch(error => {console.error(error);process.exitCode = 1;});
