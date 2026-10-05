'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const root = path.resolve(__dirname, '..');
const view = fs.readFileSync(path.join(root, 'views/game.php'), 'utf8');
const stylesheets = [...view.matchAll(/assets\/css\/([^?"']+)\?/g)].map(match => match[1]);
const styles = stylesheets.map(name => fs.readFileSync(path.join(root, 'assets/css', name), 'utf8')).join('\n');
const source = fs.readFileSync(path.join(root, 'assets/js/alliance-ranks.js'), 'utf8') + '\n' + fs.readFileSync(path.join(root, 'assets/js/relic-presentation.js'), 'utf8') + '\n' + fs.readFileSync(path.join(root, 'assets/js/mvp-panels.js'), 'utf8');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'conquer-alliance-overview-'));

function playwright() {
    try { return require('playwright'); }
    catch (error) {
        if (process.env.PLAYWRIGHT_MODULE) return require(process.env.PLAYWRIGHT_MODULE);
        throw error;
    }
}

async function main() {
    const {chromium} = playwright();
    const browser = await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});
    try {
        const page = await browser.newPage();
        const errors=[];page.on('pageerror',error=>errors.push(error.message));
        await page.setContent(`<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="mobile-game playfield-mode city-mode"><dialog id="panel-dialog" data-panel="alliance"><header class="page-heading"><h1>Alliance</h1><button class="panel-close" aria-label="Close">×</button></header><section id="content"></section></dialog><dialog id="game-dialog"><header class="popup-heading"><h2>Details</h2></header><div id="dialog-content"></div></dialog></body></html>`);
        await page.addStyleTag({content: styles});
        await page.evaluate(catalog => { window.ConquerLocale={locale:'en',t:(key,params={})=>(catalog[key]||key).replace(/\{(\w+)\}/g,(_,name)=>params[name]??''),text:value=>value}; },JSON.parse(fs.readFileSync(path.join(root,'data/i18n/en.json'),'utf8')));
        await page.addScriptTag({content: source});
        await page.evaluate(() => {
            const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
            const members = Array.from({length:12}, (_, index) => ({player_id:index+1,display_name:index?'Gefährte '+index:'Sven von Grünland',avatar:'knight',role:index?'member':'leader',power:158495}));
            const kingdom = window.kingdom = {profile:{id:1},alliance:{id:1,name:'Wächter des Grünlands',tag:'WGR',leader_id:1,role:'leader',member_count:12,max_members:50,description:'Gemeinsam schützen wir das Grünland. Sprecht eure Feldzüge ab und unterstützt neue Gefährten.',members,treasury:{food:0,lumber:0,stone:0,gold:0}},alliances:[]};
            const panels = window.panels = ConquerPanels({base:'',esc,fmt:value=>Number(value).toLocaleString('de-DE'),date:value=>new Date(value),duration:()=>'',countdown:()=>'',openDialog:html=>document.querySelector('#dialog-content').innerHTML=html,action:()=>{},api:()=>Promise.resolve({world_id:1,alliance_id:1,targets:[],goal:null}),navigate:()=>{},refresh:()=>{},render:()=>panels.render('alliance'),toast:()=>{},costHtml:()=>'',getState:()=>({city:{world_id:1},world:{map_profile:{key:'luxembourg'}}}),getKingdom:()=>kingdom,getExpeditions:()=>({}),getMarket:()=>({}),getErrors:()=>({})});
            document.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(button)panels.onClick(button.dataset.action,button);});
            panels.render('alliance');
            document.querySelector('#panel-dialog').showModal();
        });

        for (const [width,height] of [[320,568],[390,844],[568,320],[1280,800]]) {
            await page.setViewportSize({width,height});
            const metrics = await page.evaluate(() => {
                const frame = document.querySelector('#panel-dialog').getBoundingClientRect();
                const overview = document.querySelector('.alliance-home-scroll');
                const actions = [...overview.querySelectorAll('.alliance-home-link')];
                actions.at(-1).scrollIntoView({block:'nearest'});
                const last = actions.at(-1).getBoundingClientRect();
                const area = overview.getBoundingClientRect();
                return {
                    actions:actions.length,
                    tabs:document.querySelectorAll('.alliance-home-tabs .subtab').length,
                    about:overview.querySelector('.alliance-home-profile').textContent,
                    description:overview.querySelector('.alliance-full-description').textContent,
                    target:overview.querySelector('[data-action="territory-open"]').dataset.id,
                    frame:{left:frame.left,top:frame.top,right:frame.right,bottom:frame.bottom},
                    horizontalOverflow:overview.scrollWidth>overview.clientWidth+2,
                    smallTargets:actions.filter(button=>button.getBoundingClientRect().height<44).length,
                    lastReachable:last.top>=area.top-2&&last.bottom<=area.bottom+2,
                };
            });
            assert.equal(metrics.actions,8,'The overview exposes all current alliance destinations.');
            assert.equal(metrics.tabs,3,'One navigation row groups overview, members and more.');
            assert.equal(metrics.target,'goal','Territories opens the shared objective first.');
            assert.match(metrics.description,/Gemeinsam schützen/,'The alliance description remains readable.');
            assert.match(metrics.about,/Sven von Grünland/,'Alliance details retain the real leader.');
            assert.match(metrics.about,/1\.901\.940/,'Member power is still aggregated in alliance details.');
            assert.equal(metrics.horizontalOverflow,false,'The overview must not scroll sideways.');
            assert.equal(metrics.smallTargets,0,'Every alliance destination stays touch sized.');
            assert.equal(metrics.lastReachable,true,'The final destination remains reachable.');
            assert(metrics.frame.left>=-2&&metrics.frame.top>=-2&&metrics.frame.right<=width+2&&metrics.frame.bottom<=height+2,'The alliance window stays inside the viewport.');
            await page.locator('.alliance-home-scroll').evaluate(overview => { overview.scrollTop = 0; });
            await page.screenshot({path:path.join(output,`alliance-${width}x${height}.png`)});
        }
        await page.evaluate(()=>panels.render('alliance'));
        assert.match(await page.locator('.alliance-home-profile').textContent(),/Sven von Grünland/,'A server refresh preserves alliance details.');
        await page.locator('.alliance-home-tabs [data-id="members"]').click();
        assert.equal(await page.locator('.alliance-home-members .member-identity').count(),12,'The member list exposes every member without pagination.');
        assert.equal(await page.locator('.panel-pagination').count(),0);
        await page.locator('.alliance-home-tabs [data-id="manage"]').click();
        await page.locator('[data-action="panel-tab"][data-id="treasury"]').click();
        assert.equal(await page.locator('[data-form="alliance-donate"]').count(),1,'Treasury contributions remain reachable from More.');
        await page.locator('.alliance-home-list-heading [data-id="manage"]').click();
        await page.locator('[data-alliance-details="settings"]>summary').click();
        assert.equal(await page.locator('[data-action="alliance-leave"]').isDisabled(),true,'The alliance leader still must transfer leadership before leaving.');
        assert.deepEqual(errors,[],'Alliance navigation must not raise browser errors.');
        console.log(`Alliance overview checks passed. Screenshots: ${output}`);
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
