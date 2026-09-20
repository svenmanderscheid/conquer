'use strict';

// Read-only UI regression fixture. No PHP server, account, or database is used.
// Run: node tests/window_panels.cjs
// Requires Playwright + Chromium. Optional fallbacks:
// PLAYWRIGHT_MODULE=/absolute/path/to/playwright
// BROWSER_EXECUTABLE_PATH=/absolute/path/to/chrome
const fs = require('fs');
const path = require('path');
const os = require('os');
const vm = require('vm');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'assets/js/mvp-panels.js'), 'utf8');
const view = fs.readFileSync(path.join(root, 'views/game.php'), 'utf8');
const stylesheets = [...view.matchAll(/assets\/css\/([^?"']+)\?/g)].map(match => match[1]);
assert(stylesheets.includes('window-layout.css'), 'The fixture must load the current shared window layout.');
const styles = stylesheets.map(name => fs.readFileSync(path.join(root, 'assets/css', name), 'utf8')).join('\n');
const viewports = [[320, 568], [390, 844], [568, 320], [820, 720], [1280, 800]];
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'conquer-window-panels-'));
const tolerance = 2; // Borders and fractional pixel rounding only.

function itemRenderingChecks() {
    const host = {innerHTML: '', dataset: {}, querySelector: () => null};
    let dialogHTML = '';
    const kingdom = {inventory: [], treasures: {items: [], slots: 2, bonuses: {}}};
    const sandbox = {window: {innerWidth: 1280, innerHeight: 800}, document: {querySelector: () => host}};
    vm.runInNewContext(source, sandbox, {filename: 'mvp-panels.js'});
    const panels = sandbox.window.ConquerPanels({
        openDialog: html => { dialogHTML = html; },
        base: '', esc: String, fmt: number => Number(number).toLocaleString('de-DE'),
        getKingdom: () => kingdom, getState: () => ({}), getExpeditions: () => null, getMarket: () => null,
    });
    const cases = [
        [300, 'silver', '5min'], [1800, 'silver', '30min'],
        [3600, 'blue', '1h'], [28800, 'blue', '8h'],
        [86400, 'purple', '1d'], [259200, 'purple', '3d'],
        [604800, 'gold', '7d'], [2592000, 'gold', '30d'],
    ].map(([duration_seconds, rarity, stamp], index) => ({
        item: {item_code: 10103001 + index, quantity: 37, category: 'speedup', subcategory: 'generic', duration_seconds},
        rarity, stamp,
    }));
    for (const [resource, amount, rarity, stamp] of [
        ['food', 1000, 'silver', '1k'], ['food', 50000, 'blue', '50k'],
        ['food', 500000, 'purple', '500k'], ['food', 10000000, 'gold', '10M'],
        ['gems', 10, 'silver', '10'], ['gems', 100, 'blue', '100'],
        ['gems', 1000, 'purple', '1k'], ['gems', 10000, 'gold', '10k'],
    ]) cases.push({item: {item_code: 10101001, quantity: 37, category: 'resource_pack', resource, amount}, rarity, stamp});
    for (const test of cases) {
        kingdom.inventory = [test.item];
        panels.onClick('inventory-category', {dataset: {id: test.item.category}});
        assert(host.innerHTML.includes('loot-tile rarity-' + test.rarity), JSON.stringify(test));
        assert(host.innerHTML.includes('loot-value">' + test.stamp + '</span>'), JSON.stringify(test));
        assert(host.innerHTML.includes('loot-count">37</span>'), 'Owned quantity must stay visible.');
        assert(host.innerHTML.includes('data-action="inventory-item" data-id="' + test.item.item_code + '"'), 'Item selection must retain the real item code.');
    }
    kingdom.inventory=[{item_code:17,quantity:0,category:'resource_pack',resource:'food',amount:1000,name_de:'Katalogpaket',description_de:'Eine echte Katalogbeschreibung.',icon:'speedup.svg',icon_framed:true,rarity:'mythic'}];
    kingdom.inventory_catalog=kingdom.inventory;
    panels.onClick('inventory-category',{dataset:{id:'resource_pack'}});
    panels.onClick('inventory-scope',{dataset:{id:'all'}});
    assert(host.innerHTML.includes('Katalogpaket'),'Metadata name must win over legacy name');
    assert(host.innerHTML.includes('is-framed is-unowned'),'Framed zero-owned cards need distinct presentation');
    assert(host.innerHTML.includes('rarity-mythic'),'Explicit rarity must win over amount thresholds');
    panels.onClick('inventory-item', {dataset:{id:'17'}});
    assert(host.innerHTML.includes('Nicht im Besitz'),'Zero-owned items must show an unavailable action');
    assert(host.innerHTML.includes('type="submit" disabled'),'Zero-owned items must not be usable');
    assert(host.innerHTML.includes('Eine echte Katalogbeschreibung.'),'Localized effect metadata must win');
    const shown=()=>[...host.innerHTML.matchAll(/data-action="inventory-item" data-id="(\d+)"/g)].map(m=>Number(m[1]));
    kingdom.inventory_catalog=[
      {item_code:901,category:'speedup',subcategory:'building',duration_seconds:60,quantity:1},
      {item_code:902,category:'speedup',subcategory:'generic',duration_seconds:3600,quantity:1},
      {item_code:903,category:'speedup',subcategory:'generic',duration_seconds:60,quantity:1},
      {item_code:904,category:'speedup',subcategory:'generic',duration_seconds:600,quantity:1},
      {item_code:905,category:'resource_pack',resource:'food',amount:1000000,quantity:1},
      {item_code:906,category:'resource_pack',resource:'lumber',amount:1000,quantity:1},
      {item_code:907,category:'resource_pack',resource:'food',amount:1000,quantity:1},
      {item_code:908,category:'resource_pack',resource:'food',amount:5000,quantity:1},
    ];kingdom.inventory=kingdom.inventory_catalog;
    panels.onClick('inventory-category',{dataset:{id:'speedup'}});assert.deepEqual(shown(),[903,904,902,901],'Speedups group by troop activity and duration rather than item code');assert(host.innerHTML.includes('<span>4 Gegenstände</span>'),'Counter must describe the current category');
    panels.onClick('inventory-category',{dataset:{id:'resource_pack'}});assert.deepEqual(shown(),[907,908,905,906],'Resources group by material and ascending quantity');
    panels.onClick('inventory-scope',{dataset:{id:'owned'}});assert.deepEqual(shown(),[907,908,905,906],'Ownership view uses the same semantic ordering');
    return cases.length+10;
}

function fixtureData() {
    const inventory = [];
    let code = 10101000;
    for (const resource of ['food', 'lumber', 'stone', 'gold', 'gems']) {
        const amounts = resource === 'gems' ? [10, 100, 1000, 10000] : [1000, 5000, 50000, 500000, 10000000];
        for (const amount of amounts) inventory.push({item_code: ++code, quantity: 99, category: 'resource_pack', resource, amount});
    }
    for (const subcategory of ['generic', 'building', 'research', 'training', 'healing']) {
        for (const duration_seconds of [300, 3600, 86400, 604800]) {
            inventory.push({item_code: ++code, quantity: 23, category: 'speedup', subcategory, duration_seconds});
        }
    }
    for (const boost_type of ['resource_production', 'gathering_speed', 'construction_speed', 'research_speed', 'training_speed']) {
        inventory.push({item_code: ++code, quantity: 2, category: 'boost', boost_type, bonus_pct: 25, duration_seconds: 28800});
    }
    for (const chest_type of ['silver', 'gold', 'platinum']) inventory.push({item_code: ++code, quantity: 5, category: 'chest', chest_type});
    inventory.push(
        {item_code: ++code, category: 'ap_refill', quantity: 3, ap_amount: 50},
        {item_code: ++code, category: 'vip_point', quantity: 3, vip_points: 100},
    );
    const relicCodes = [60100001, 60100002, 60100003, 60100004, 60100005, 60100006, 60200001, 60200002, 60200003, 60200004, 60200005, 60200006, 60300001, 60300002, 60300003, 60300004, 60300005, 60300006, 60400001, 60400002, 60400003, 60400004, 60500001, 60500002];
    const questCodes = ['login_daily', 'attack_monster_1', 'attack_monster_3', 'upgrade_building_1', 'train_troops_100', 'research_complete_1', 'collect_resources', 'open_chest_1'];
    const kingdom = {
        profile: {id: 1}, inventory, inventory_catalog:[{item_code:1,category:'resource_pack',resource:'food',amount:10000000,quantity:0,name_de:'Großes Nahrungspaket',rarity:'legendary'},...inventory], queues: [], quest_resets_at: '2030-01-01T00:00:00Z',
        treasures: {
            slots: 6,
            items: relicCodes.map((treasure_code, index) => ({
                treasure_code, name: 'Relikt ' + index, grade: ['normal', 'rare', 'epic', 'legendary', 'mythic'][index % 5],
                level: 1, fragments: 40, is_unlocked: true, is_usable: true, equipped_slot: index < 5 ? index + 1 : null,
                stats_at_level: {food_production: 5},
            })),
            bonuses: Object.fromEntries(['food_production', 'lumber_production', 'stone_production', 'gold_production', 'all_attack', 'all_defense', 'all_hp', 'research_speed', 'training_speed'].map(key => [key, 5])),
        },
        quests: questCodes.flatMap(quest_code => [0, 1, 2].map(status => ({
            quest_code: quest_code + (status ? '_' + status : ''),
            title: quest_code === 'collect_resources' ? 'Vorräte aus der Wildnis' : 'Wächter des Grünlands',
            description: 'Bringe mit Sammelzügen Ressourcen nach Hause.', progress: status ? 100 : 25, target: 100,
            completed: status > 0, claimed: status === 2, rewards: [{item_code: 10102001, quantity: 1}, {gems: 10}],
        }))),
        alliance: {
            id: 1, name: 'Wächter des Grünlands', tag: 'WGR', leader_id: 1, role: 'leader', member_count: 12, max_members: 50,
            description: 'Gemeinsam schützen wir das Grünland. Sprecht eure Feldzüge ab, unterstützt neue Gefährten mit Vorräten und steht zusammen gegen den Aschenfürsten.',
            members: Array.from({length: 12}, (_, index) => ({
                player_id: index + 1, display_name: index ? 'Gefährte des Grünlands ' + index : 'Sven von Grünland',
                avatar: ['knight', 'archer', 'rider'][index % 3], role: index ? 'member' : 'leader', power: 158495,
            })),
            treasury: {food: 1000000, lumber: 123456, stone: 12345, gold: 1234},
        },
        alliances: [],
        arena: {challenges: Array.from({length: 9}, (_, index) => ({
            id: index + 1, status: 'completed', challenger_name: 'Herrscher des Grünlands',
            opponent_name: 'Wächter der Morgenröte', winner_id: 1, created_at: '2026-09-10T11:12:13Z',
        }))},
    };
    const state = {
        player: {name: 'Fixture'}, city: {food: 500000, lumber: 500000, stone: 500000, gold: 500000}, troop_defs: [],
        reports: Array.from({length: 13}, (_, index) => ({
            id: index + 1, outcome: index % 2 ? 'defender_wins' : 'attacker_wins', target_x: 123, target_y: 456, created_at: '2026-09-10T11:12:13Z',
        })),
    };
    const expeditions = {expeditions: Array.from({length: 9}, (_, index) => ({
        id: index + 1, phase: 'victory', name: 'Die Wächter gegen den Aschenfürsten', my_contribution: 2500,
    }))};
    return {kingdom, state, expeditions};
}

function loadPlaywright() {
    try { return require('playwright'); }
    catch (error) {
        if (process.env.PLAYWRIGHT_MODULE) return require(process.env.PLAYWRIGHT_MODULE);
        throw new Error('Install playwright or set PLAYWRIGHT_MODULE to its module directory.', {cause: error});
    }
}

async function itemUseChecks() {
    const calls = [];
    const sandbox = {
        window: {}, document: {},
        FormData: class { constructor(form) { this.values = form.values; } get(key) { return this.values[key]; } },
    };
    vm.runInNewContext(source, sandbox, {filename:'mvp-panels.js'});
    const panels = sandbox.window.ConquerPanels({getKingdom:()=>({inventory:[{item_code:10101001,quantity:2},{item_code:10103001,quantity:3}]}),toast:()=>{},action: (endpoint, payload) => calls.push({endpoint, payload})});
    for (const [id, queue] of [[10101001, ''], [10103001, 'building:12']]) {
        await panels.onSubmit({dataset:{form:'item-use',id:String(id)},values:{queue}});
    }
    assert.deepEqual(JSON.parse(JSON.stringify(calls)), [
        {endpoint:'kingdom/action',payload:{action:'inventory.use',item_code:10101001}},
        {endpoint:'kingdom/action',payload:{action:'inventory.use',item_code:10103001,queue_type:'building',queue_id:12}},
    ], 'Inline use must dispatch the selected item and queue through the existing single-item API.');
    await panels.onSubmit({dataset:{form:'item-use',id:'999'},values:{queue:''}});
    assert.equal(calls.length,2,'Missing catalogue item cannot dispatch a use action');
    let finish,pendingCalls=0;
    const guarded=sandbox.window.ConquerPanels({getKingdom:()=>({inventory:[{item_code:10101001,quantity:2}]}),toast:()=>{},action:()=>{pendingCalls++;return new Promise(resolve=>{finish=resolve;});}});
    const form={dataset:{form:'item-use',id:'10101001'},values:{queue:''}},first=guarded.onSubmit(form);
    await guarded.onSubmit(form);assert.equal(pendingCalls,1,'A second click while use is pending must not dispatch');finish();await first;
    return calls.length+2;
}

async function main() {
    const itemChecks = itemRenderingChecks();
    const useChecks = await itemUseChecks();
    const {chromium} = loadPlaywright();
    const browser = await chromium.launch({
        headless: true,
        ...(process.env.BROWSER_EXECUTABLE_PATH ? {executablePath: process.env.BROWSER_EXECUTABLE_PATH} : {}),
    });
    try {
        const page = await browser.newPage();
        const browserErrors = [], requestErrors = [], report = [];
        page.on('pageerror', error => browserErrors.push(error.message));
        // Only local static art is served. Unexpected requests cannot reach a network or PHP endpoint.
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            const pathname = decodeURIComponent(url.pathname);
            // addStyleTag concatenates CSS, so its ../fonts URLs resolve from the
            // fixture document rather than from assets/css/fantasy-fonts.css.
            const file = pathname.startsWith('/fonts/')
                ? path.resolve(root, 'assets' + pathname)
                : path.resolve(root, '.' + pathname);
            const assetsRoot = path.join(root, 'assets') + path.sep;
            if (url.origin !== 'https://panels.fixture' || !file.startsWith(assetsRoot) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
                requestErrors.push(route.request().url());
                await route.abort();
                return;
            }
            const contentType = {'.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.woff2': 'font/woff2'}[path.extname(file)] || 'application/octet-stream';
            await route.fulfill({body: fs.readFileSync(file), contentType});
        });
        await page.setContent(`<!doctype html><html lang="de"><head>
            <meta name="viewport" content="width=device-width,initial-scale=1"><base href="https://panels.fixture/">
            </head><body class="mobile-game playfield-mode city-mode">
            <main id="main"><section id="playfield-content"></section></main>
            <dialog id="panel-dialog" data-panel="inventory"><div class="page-heading"><h1 id="page-title">Inventar</h1><button class="panel-close">×</button></div><section id="content"></section></dialog>
            <dialog id="game-dialog"><div class="popup-heading"><h2>Details</h2><button>×</button></div><div id="dialog-content"></div></dialog>
            </body></html>`);
        await page.addStyleTag({content: styles});
        await page.addScriptTag({content: source});
        await page.evaluate(({kingdom, state, expeditions}) => {
            window.K = kingdom; window.S = state; window.E = expeditions;
            window.current = 'inventory'; window.mutationAttempts = 0;
            const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
            window.draw = () => {
                document.querySelector('#panel-dialog').dataset.panel = current;
                document.querySelector('#page-title').textContent = {inventory: 'Inventar', quests: 'Aufgaben', alliance: 'Allianz', reports: 'Post'}[current];
                if (current === 'reports') document.querySelector('#content').innerHTML = panels.reportHeader() + panels.reportCategory();
                else panels.render(current);
            };
            window.panels = ConquerPanels({
                base: '', esc, fmt: value => Number(value).toLocaleString('de-DE'), date: value => new Date(value),
                duration: () => '1h', countdown: () => '8 Std. 12 Min.',
                openDialog: html => { document.querySelector('#dialog-content').innerHTML = html; document.querySelector('#game-dialog').showModal(); },
                action: () => { mutationAttempts++; throw Error('Mutation forbidden in fixture'); },
                api: () => { throw Error('API forbidden in fixture'); },
                navigate: () => {}, refresh: () => {}, toast: () => {}, costHtml: () => '', now: () => Date.now(),
                labels: {}, researchNames: {}, render: () => draw(), getState: () => S, getKingdom: () => K,
                getExpeditions: () => E, getMarket: () => null, getErrors: () => ({}),
            });
            document.addEventListener('click', event => {
                const button = event.target.closest('[data-action]');
                if (button) panels.onClick(button.dataset.action, button);
            });
            document.querySelector('#panel-dialog').showModal();
            draw();
        }, fixtureData());

        async function check(label, screenshot = false) {
            await page.locator('#content').evaluate(async host => {
                host.scrollTop = 0;
                await Promise.all([...host.querySelectorAll('img')].map(async image => {
                    image.loading = 'eager';
                    try { await image.decode(); } catch { /* The metric below reports failed images. */ }
                }));
            });
            const metrics = await page.evaluate(tolerance => {
                const host = document.querySelector('#content'), bounds = host.getBoundingClientRect();
                const frame = document.querySelector('#panel-dialog').getBoundingClientRect();
                const selectors = ['#panel-dialog', '#content', '.inventory-paged', '#inventory-body', '.inventory-browser', '.inventory-board', '.inventory-inspector', '.inventory-inspector-description', '.inventory-inspector-controls', '.inventory-page-grid', '.inventory-pager', '.window-list', '.treasury-view', '.quest-page', '.alliance-overview'];
                return {
                    viewport: [innerWidth, innerHeight],
                    cards: document.querySelectorAll('.inventory-page-grid>.loot-card').length,
                    status: document.querySelector('.inventory-page-status,.panel-page-status')?.textContent,
                    frameOutsideViewport: frame.top < -tolerance || frame.left < -tolerance || frame.bottom > innerHeight + tolerance || frame.right > innerWidth + tolerance,
                    overflow: selectors.flatMap(selector => {
                        const node = document.querySelector(selector);
                        const scrollList = node?.matches('.inventory-scroll-board,.inventory-scroll-list,.inventory-inspector,.quest-list,.alliance-overview') && getComputedStyle(node).overflowY === 'auto';
                        return node && (node.scrollWidth > node.clientWidth + tolerance || (!scrollList && node.scrollHeight > node.clientHeight + tolerance))
                            ? [{selector, size: [node.clientWidth, node.clientHeight], scroll: [node.scrollWidth, node.scrollHeight]}] : [];
                    }),
                    clippedControls: [...host.querySelectorAll('button,input,select')].filter(node => {
                        const rect = node.getBoundingClientRect();
                        const scrollList = node.closest('.inventory-scroll-board,.inventory-scroll-list,.inventory-inspector,.quest-list,.alliance-overview');
                        return rect.width && rect.height && ((!scrollList && (rect.top < bounds.top - tolerance || rect.bottom > bounds.bottom + tolerance)) || rect.left < bounds.left - tolerance || rect.right > bounds.right + tolerance);
                    }).map(node => ({text: node.textContent.trim(), action: node.dataset.action, bounds: node.getBoundingClientRect().toJSON()})),
                    brokenImages: [...host.querySelectorAll('img')].filter(image => !image.complete || !image.naturalWidth).map(image => image.src),
                };
            }, tolerance);
            for (const control of await page.locator('.alliance-overview button').all()) {
                await control.scrollIntoViewIfNeeded();
                assert(await control.evaluate(el=>{const r=el.getBoundingClientRect(),b=el.closest('.alliance-overview').getBoundingClientRect();return r.top>=b.top-2&&r.bottom<=b.bottom+2;}),'All alliance actions remain reachable by scrolling');
            }
            report.push({label, ...metrics});
            if (screenshot || hasFailure(metrics)) {
                await page.screenshot({path: path.join(output, `${metrics.viewport.join('x')}-${label.replace(/[^a-z0-9-]/gi, '-')}.png`)});
            }
        }
        async function setPanel(panel) { await page.evaluate(value => { current = value; draw(); }, panel); }
        async function select(group, id) { await page.locator(`[data-action="panel-tab"][data-group="${group}"][data-id="${id}"]`).click(); }
        async function pages(label, pager = '.panel-pagination') {
            const previous = page.locator(pager + ' button').first();
            let resets = 0;
            while (await previous.count() && !await previous.isDisabled()) {
                assert(resets++ < 20, 'Previous-page button does not reach the beginning: ' + label);
                await previous.click();
            }
            for (let index = 1; index <= 20; index++) {
                if (label.startsWith('inventory-') && label !== 'inventory-treasures') {
                    const item = page.locator('[data-action="inventory-item"]').last();
                    if (await item.count()) {
                        const code = await item.getAttribute('data-id');
                        await item.click();
                        assert.equal(await page.locator('.inventory-dialog form[data-form="item-use"]').getAttribute('data-id'), code, 'Details must use the same item code.');
                        assert.equal(await item.getAttribute('aria-pressed'), 'true');
                        assert.equal(await page.locator('#game-dialog').evaluate(dialog => dialog.open), false, 'Tapping a tile shows inline details without a popup.');
                        assert.equal(await page.locator('#game-dialog').evaluate(dialog => dialog.open), false);
                    }
                }
                await check(label + '-' + index, index === 1);
                const next = page.locator(pager + ' button').last();
                if (!await next.count() || await next.isDisabled()) return;
                assert(index < 20, 'Next-page button does not reach the end: ' + label);
                await next.click();
            }
        }

        for (const [width, height] of viewports) {
            await page.setViewportSize({width, height});
            await setPanel('inventory');
            for (const category of ['resource_pack', 'speedup', 'boost', 'other', 'treasures']) {
                await page.locator(`[data-action="inventory-category"][data-id="${category}"]`).click();
                await pages('inventory-' + category, '.inventory-pager');
            }
            for (const tab of ['equipment', 'bonuses']) {
                await page.locator(`[data-action="inventory-view"][data-id="${tab}"]`).click();
                await pages('relic-' + tab, '.inventory-pager');
            }
            await page.locator('[data-action="inventory-view"][data-id="collection"]').click();
            await page.evaluate(() => {
                K.queues = [{type:'building', id:11, label:'castle', finishes_at:'2030-01-01T00:00:00Z'}, {type:'building', id:12, label:'farm', finishes_at:'2030-01-01T00:00:00Z'}, {type:'research', id:13, label:'production', finishes_at:'2030-01-01T00:00:00Z'}];
                panels.onClick('inventory-category', {dataset:{id:'speedup'}});
                panels.onClick('inventory-page', {dataset:{id:'speedup', page:'0'}});
                panels.onClick('inventory-item', {dataset:{id:String(K.inventory.find(item => item.category==='speedup' && item.subcategory==='building').item_code)}});
            });
            assert.deepEqual(await page.locator('#inventory-queue option').evaluateAll(options => options.map(option => option.value)), ['building:11','building:12'], 'Building speedups must only list building queues.');
            await page.locator('#inventory-queue').selectOption('building:12');
            await page.evaluate(() => draw());
            assert.equal(await page.locator('#inventory-queue').inputValue(), 'building:12', 'Refresh must preserve the selected valid queue.');
            await check('inventory-active-queue', true);
            assert.equal(await page.locator('#game-dialog').evaluate(dialog => dialog.open), false);
            await page.evaluate(() => {
                K.queues = [];
                K.inventory.push({item_code:99900001,category:'boost',boost_type:'anti_spy',quantity:1,is_usable:false});
                panels.onClick('inventory-category', {dataset:{id:'boost'}});
                panels.onClick('inventory-item', {dataset:{id:'99900001'}});
            });
            assert.equal(await page.locator('.inventory-dialog button[type="submit"],.inventory-dialog [data-action="teleport-select"]').isDisabled(), true, 'Unsupported boost must stay disabled.');
            await check('inventory-unsupported-boost', true);
            assert.equal(await page.locator('#game-dialog').evaluate(dialog => dialog.open), false);
            await page.evaluate(() => { K.inventory = K.inventory.filter(item => item.item_code!==99900001); });
            await page.locator('[data-action="inventory-category"][data-id="resource_pack"]').click();
            await page.locator('[data-action="inventory-scope"][data-id="all"]').click();
            await page.locator('[data-action="inventory-item"][data-id="1"]').click();
            assert.equal(await page.locator('.inventory-dialog button[type="submit"],.inventory-dialog [data-action="teleport-select"]').isDisabled(),true,'Unowned catalogue item must not be usable');
            assert.equal(await page.locator('.inventory-dialog .inventory-owned').innerText(),'0 vorhanden');
            await check('inventory-full-catalogue', true);
            assert.equal(await page.locator('#game-dialog').evaluate(dialog => dialog.open), false);
            await page.locator('[data-action="inventory-scope"][data-id="owned"]').click();
            assert.equal(await page.locator('[data-action="inventory-item"][data-id="1"]').count(),0,'Ownership filter must hide zero quantities');
            await setPanel('quests');
            for (const tab of ['active', 'ready', 'claimed']) {
                await select('quests', tab);
                assert.equal(await page.locator('.quest-list .quest-row').count(),tab==='active'?16:8,'Every task in this filter must be available without pagination');
                if(tab==='active')assert.deepEqual(await page.locator('.quest-row').evaluateAll(rows=>rows.map(row=>row.classList.contains('ready'))),[...Array(8).fill(true),...Array(8).fill(false)],'Ready rewards appear above all ongoing tasks in the main task list');
                assert.equal(await page.locator('.panel-pagination').count(),0,'Tasks use one scrolling list');
                const list=page.locator('.quest-list');
                assert.equal(await list.evaluate(node=>node.scrollTop),0,'Changing task filters starts at the top');
                await pages('quests-' + tab);
                await list.focus();
                const scroll=await list.evaluate(node=>{node.scrollTop=node.scrollHeight;return node.scrollTop});
                assert(scroll>0,'The full task list scrolls inside the fixed window');
                const last=await page.locator('.quest-row').last().boundingBox(),bounds=await list.boundingBox();
                assert(last.y>=bounds.y-tolerance&&last.y+last.height<=bounds.y+bounds.height+tolerance,'The final task is reachable');
                await page.evaluate(()=>draw());
                assert.equal(await list.evaluate(node=>node.scrollTop),scroll,'Server refresh preserves task scroll position');
                assert.equal(await list.evaluate(node=>node===document.activeElement),true,'Refreshing does not lose keyboard scroll focus');
                if(tab==='ready')assert.equal(await page.locator('.quest-row [data-action="quest-claim"]').count(),8,'Each ready row keeps its claim action');
                if(tab==='claimed')assert.equal(await page.locator('.quest-row button').count(),0,'Claimed tasks cannot be claimed twice');
            }
            await setPanel('alliance');
            for (const tab of ['overview', 'members', 'manage']) { await select('alliance', tab); await pages('alliance-' + tab); }
            await select('alliance', 'treasury');
            for (const tab of ['donate', 'withdraw']) { await select('treasury', tab); await check('treasury-' + tab, true); }
            await setPanel('reports');
            for (const tab of ['battles', 'expeditions', 'arena']) { await select('reports', tab); await pages('reports-' + tab); }
        }
        const realItems=JSON.parse(fs.readFileSync(path.join(root,'data/items.json'),'utf8')).items.map(i=>({...i,item_code:i.code,quantity:0}));
        for(const [width,height] of viewports){
            await page.setViewportSize({width,height});
            await page.evaluate(catalogue=>{K.inventory=[];K.inventory_catalog=catalogue;K.queues=[];current='inventory';draw();panels.onClick('inventory-scope',{dataset:{id:'all'}});},realItems);
            const seen=new Set();
            for(const category of ['resource_pack','speedup','boost','other']){
                await page.locator(`[data-action="inventory-category"][data-id="${category}"]`).click();
                await page.evaluate(category=>panels.onClick('inventory-page',{dataset:{id:category,page:0}}),category);
                let n=0;
                while(true){
                    const ids=await page.locator('[data-action="inventory-item"]').evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.id)));
                    ids.forEach(id=>seen.add(id));
                    const longest=realItems.filter(i=>ids.includes(i.code)).sort((a,b)=>(b.description_de||'').length-(a.description_de||'').length)[0];
                    if(longest){await page.locator(`[data-action="inventory-item"][data-id="${longest.code}"]`).click();}
                    assert.equal(await page.locator('.inventory-dialog button[type="submit"],.inventory-dialog [data-action="teleport-select"]').isDisabled(),true,'Full catalogue zero-owned items must remain unusable');
                    await check('real-catalogue-'+category+'-'+(++n),n===1);let effectPage=1;while(await page.locator('[aria-label="Weitere Effektinformation"]').count()&&!await page.locator('[aria-label="Weitere Effektinformation"]').isDisabled()){await page.locator('[aria-label="Weitere Effektinformation"]').click();await check('real-effect-'+category+'-'+n+'-'+(++effectPage));}
                    assert.equal(await page.locator('#game-dialog').evaluate(dialog => dialog.open), false);
                    const next=page.locator('.inventory-pager button').last();if(!await next.count()||!await next.isVisible()||await next.isDisabled())break;await next.click();
                }
            }
            assert.equal(seen.size,realItems.length,'Every actual catalogue definition must be reachable on every viewport');
        }
        // Empty states matter for new accounts and fully claimed daily objectives.
        for (const [width, height] of viewports) {
            await page.setViewportSize({width, height});
            await page.evaluate(() => {
                K.quests = []; K.inventory = []; K.treasures.items = []; K.treasures.bonuses = {};
                S.reports = []; E.expeditions = []; K.arena.challenges = [];
            });
            await setPanel('quests');
            for (const tab of ['active', 'ready', 'claimed']) { await select('quests', tab); await check('empty-quests-' + tab); }
            await setPanel('reports');
            for (const tab of ['battles', 'expeditions', 'arena']) { await select('reports', tab); await check('empty-reports-' + tab); }
            await setPanel('inventory');
            await page.locator('[data-action="inventory-category"][data-id="resource_pack"]').click();
            await check('empty-inventory');
            await page.locator('[data-action="inventory-category"][data-id="treasures"]').click();
            await page.locator('[data-action="inventory-view"][data-id="collection"]').click();
            await check('empty-relics');
        }
        const failures = report.filter(hasFailure);
        const result = {checks: report.length, itemChecks, useChecks, failures, browserErrors, requestErrors, mutationAttempts: await page.evaluate(() => mutationAttempts), output};
        fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify({result, report}, null, 2));
        console.log(JSON.stringify({...result, failures: failures.map(failure => ({label: failure.label, viewport: failure.viewport, overflow: failure.overflow, clippedControls: failure.clippedControls.map(control => control.text), brokenImages: failure.brokenImages, frameOutsideViewport: failure.frameOutsideViewport}))}, null, 2));
        assert.equal(failures.length, 0, 'Window geometry/image regressions; see ' + output);
        assert.equal(browserErrors.length, 0, 'Unexpected browser script errors.');
        assert.equal(requestErrors.length, 0, 'Missing assets or unexpected network requests.');
        assert.equal(result.mutationAttempts, 0, 'Fixture must never submit an action.');
    } finally {
        await browser.close();
    }
}

function hasFailure(result) {
    return result.frameOutsideViewport || result.overflow.length || result.clippedControls.length || result.brokenImages.length;
}

main().catch(error => { console.error(error); process.exitCode = 1; });
