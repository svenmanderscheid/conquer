'use strict';
// Real game.php shell and account module; no database, live accounts or external requests.
const fs = require('fs');
const path = require('path');
const os = require('os');
const http = require('http');
const assert = require('assert');
const {execFileSync} = require('child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '..');
const out = fs.mkdtempSync(path.join(os.tmpdir(), 'conquer-discord-account-'));
const catalogs = Object.fromEntries(['en', 'de', 'fr'].map(locale => [locale,
    JSON.parse(fs.readFileSync(path.join(root, 'data/i18n', locale + '.json'), 'utf8'))]));
const php = `define('ROOT_DIR',${JSON.stringify(root.replaceAll('\\', '/'))});define('APP_BASE','');require ROOT_DIR.'/src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();$session=['username'=>'Fixture ruler'];$uiLayoutProfiles=\\Conquer\\Game\\Ui\\LayoutSettings::defaults();if(\\Conquer\\Db\\Connection::isInitialized())throw new RuntimeException('Unexpected database initialization');require ROOT_DIR.'/views/game.php';if(\\Conquer\\Db\\Connection::isInitialized())throw new RuntimeException('Unexpected database initialization');`;
let template = execFileSync(process.env.PHP_BINARY || 'php', ['-r', php], {
    encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
}).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
const fixtureCode = '0123456789ABCDEF0123456789ABCDEF';
const unsafeWorld = 'Second realm <img src=x onerror=window.xss=1> & "Friends"';
const inline = value => JSON.stringify(value).replaceAll('<', '\\u003c');
const script = `<script>window.CONQUER_BASE='';window.CONQUER_I18N=${inline({locale: 'en', catalogs})};</script>
<script src="/assets/js/localization.js"></script>
<script src="/assets/js/lord-talents.js"></script>
<script src="/assets/js/progression-panel.js"></script>
<script>
window.calls=[];window.messages=[];window.xss=0;window.fixtureCode=${inline(fixtureCode)};window.unsafeWorld=${inline(unsafeWorld)};
window.fixture={account:{email:'fixture@example.invalid',email_verified:true,has_recovery_code:true,sessions:[],discord:{enabled:false,linked:false}}};
document.querySelector('#content').id='playfield-content';document.querySelector('#panel-content').id='content';
window.context={base:'',esc:value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),
fmt:value=>Number(value).toLocaleString('en'),getState:()=>({city:{world_id:2,castle_level:10},world:{id:1},player:{name:'Fixture ruler'}}),
openDialog:()=>{throw new Error('Unexpected dialog');},toast:message=>messages.push(message),refresh:async()=>{},
api:async(route,payload)=>{
    calls.push({route,payload});
    if(route==='progression/state'&&!payload)return structuredClone(fixture);
    if(route!=='discord/account-action')throw new Error('Unexpected route '+route);
    if(payload.action==='code')return {discord:{enabled:true,linked:false},code:fixtureCode};
    if(payload.action==='unlink')return {discord:{enabled:true,linked:false},message:'Discord minigames disconnected.'};
    throw new Error('Unexpected Discord action');
}};
window.showAccount=discord=>{
    fixture.account.discord=discord;window.progression=ConquerProgression(context);
    const dialog=document.querySelector('#panel-dialog');dialog.dataset.panel='account';
    document.querySelector('#page-title').textContent='Account';document.querySelector('#content').innerHTML='';
    if(!dialog.open)dialog.showModal();progression.render('account');
};
document.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(button)progression.onClick(button.dataset.action,button);});
showAccount({enabled:false,linked:false});
</script>`;
template = template.replace('</body>', script + '</body>');

const server = http.createServer((request, response) => {
    const pathname = new URL(request.url, 'http://fixture').pathname;
    if (pathname.startsWith('/assets/') && !pathname.includes('..')) {
        const file = root + pathname;
        if (fs.existsSync(file) && fs.statSync(file).isFile()) {
            response.setHeader('Content-Type', ({'.js': 'application/javascript', '.css': 'text/css',
                '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2'})[path.extname(file)] || 'application/octet-stream');
            response.end(fs.readFileSync(file));
            return;
        }
    }
    response.setHeader('Content-Type', 'text/html; charset=utf-8');
    response.end(template);
});

async function assertLayout(page, size, stage) {
    const metrics = await page.evaluate(() => {
        const dialog = document.querySelector('#panel-dialog');
        const bounds = dialog.getBoundingClientRect();
        const host = document.querySelector('#content');
        const content = document.querySelector('.progression-content');
        const panel = document.querySelector('.discord-account');
        return {
            outside: bounds.left < -1 || bounds.top < -1 || bounds.right > innerWidth + 1 || bounds.bottom > innerHeight + 1,
            horizontal: [host, content, panel].filter(Boolean).some(element => element.scrollWidth > element.clientWidth + 2),
            bodyOverflow: document.documentElement.scrollWidth > innerWidth + 1,
        };
    });
    assert.deepEqual(metrics, {outside: false, horizontal: false, bodyOverflow: false}, `${size} ${stage}: ${JSON.stringify(metrics)}`);
}

async function assertTouchTarget(button, size) {
    await button.scrollIntoViewIfNeeded();
    const metrics = await button.evaluate(element => {
        const bounds = element.getBoundingClientRect();
        return {width: bounds.width, height: bounds.height,
            inside: bounds.left >= 0 && bounds.right <= innerWidth && bounds.top >= 0 && bounds.bottom <= innerHeight,
            reachable: element.contains(document.elementFromPoint(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2))};
    });
    const valid = metrics.width >= 44 && metrics.height >= 44 && metrics.inside && metrics.reachable;
    if (!valid) await button.page().screenshot({path: path.join(out, size + '-touch-failure.png')});
    assert.ok(valid,
        `${size} Discord action is touch-sized and reachable: ${JSON.stringify(metrics)}`);
}

(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    const browser = await chromium.launch({headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome'});
    const errors = [];
    try {
        for (const [width, height] of [[1280, 800], [390, 844], [320, 568], [568, 320]]) {
            const size = `${width}x${height}`;
            const context = await browser.newContext({viewport: {width, height}, hasTouch: true});
            await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
            const page = await context.newPage();
            page.on('pageerror', error => errors.push(`${size}: ${error.message}`));
            await page.goto(origin);
            await page.waitForSelector('.progression-content');
            assert.equal(await page.locator('.discord-account').count(), 0, `${size}: disabled feature has no account section`);

            await page.evaluate(() => showAccount({enabled: true, linked: false}));
            const generate = page.locator('[data-action="progress-discord-code"]');
            await generate.waitFor();
            assert.equal(await generate.textContent(), catalogs.en['discord.generate'], 'English is the default');
            await assertLayout(page, size, 'unlinked');
            await assertTouchTarget(generate, size);
            await generate.tap();
            const code = page.locator('.discord-account input');
            await code.waitFor();
            assert.equal(await code.inputValue(), fixtureCode);
            assert.equal(await code.evaluate(element => element.readOnly), true, 'The private linking code is readonly');
            assert.equal(await code.getAttribute('autocomplete'), 'off');
            assert.ok((await page.locator('.discord-account label').textContent()).includes(catalogs.en['discord.code_label']));
            assert.deepEqual(await page.evaluate(() => calls.filter(call => call.payload)), [{
                route: 'discord/account-action', payload: {action: 'code', expected_world_id: 2},
            }], 'Create code uses the selected city world, not the fallback world');
            await assertLayout(page, size, 'private code');
            await code.scrollIntoViewIfNeeded();
            await page.screenshot({path: path.join(out, size + '-private-code.png')});

            await page.evaluate(() => showAccount({enabled: true, linked: true, link: {
                world_id: 2, world_name: unsafeWorld, discord_id: '123456789012345678',
            }}));
            const unlink = page.locator('[data-action="progress-discord-unlink"]');
            await unlink.waitFor();
            assert.ok((await page.locator('.discord-account').textContent()).includes(unsafeWorld), 'World names render verbatim as text');
            assert.equal(await page.locator('.discord-account img').count(), 0, 'Untrusted world HTML is escaped');
            assert.equal(await page.evaluate(() => window.xss), 0);
            assert.equal(await page.locator('.discord-account input').count(), 0, 'Linked status does not retain a private code');
            await assertLayout(page, size, 'linked');
            await assertTouchTarget(unlink, size);
            await page.screenshot({path: path.join(out, size + '-linked.png')});
            await unlink.tap();
            await generate.waitFor();
            assert.equal(await page.locator('[data-action="progress-discord-unlink"]').count(), 0);
            assert.deepEqual(await page.evaluate(() => calls.filter(call => call.payload).at(-1)), {
                route: 'discord/account-action', payload: {action: 'unlink', expected_world_id: 2},
            });
            assert.equal(await code.count(), 0, 'Unlink restores generation without a stale code');
            await assertTouchTarget(generate, size);
            await assertLayout(page, size, 'after unlink');
            await context.close();
        }
        assert.deepEqual(errors, [], 'No browser errors');
        console.log('PASS Discord account: disabled visibility, English copy, create/unlink world 2 API contracts, readonly private code, escaped world name; reachable touch actions and no overflow at 1280x800, 390x844, 320x568, 568x320. Screenshots: ' + out);
    } finally {
        await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
