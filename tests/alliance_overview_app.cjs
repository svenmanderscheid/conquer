'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const root = path.resolve(__dirname, '..');
const view = fs.readFileSync(path.join(root, 'views/game.php'), 'utf8');
const stylesheets = [...view.matchAll(/assets\/css\/([^?"']+)\?/g)].map(match => match[1]);
const styles = stylesheets.map(name => fs.readFileSync(path.join(root, 'assets/css', name), 'utf8')).join('\n');
const source = fs.readFileSync(path.join(root, 'assets/js/mvp-panels.js'), 'utf8');
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
    const browser = await chromium.launch({headless:true});
    try {
        const page = await browser.newPage();
        await page.setContent(`<!doctype html><html lang="de"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="mobile-game playfield-mode city-mode"><dialog id="panel-dialog" data-panel="alliance"><header class="page-heading"><h1>Allianz</h1><button class="panel-close" aria-label="Schließen">×</button></header><section id="content"></section></dialog><dialog id="game-dialog"><header class="popup-heading"><h2>Details</h2></header><div id="dialog-content"></div></dialog></body></html>`);
        await page.addStyleTag({content: styles});
        await page.addScriptTag({content: source});
        await page.evaluate(() => {
            const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
            const members = Array.from({length:12}, (_, index) => ({player_id:index+1,display_name:index?'Gefährte '+index:'Sven von Grünland',avatar:'knight',role:index?'member':'leader',power:158495}));
            const kingdom = {profile:{id:1},alliance:{id:1,name:'Wächter des Grünlands',tag:'WGR',leader_id:1,role:'leader',member_count:12,max_members:50,description:'Gemeinsam schützen wir das Grünland. Sprecht eure Feldzüge ab und unterstützt neue Gefährten.',members,treasury:{food:0,lumber:0,stone:0,gold:0}},alliances:[]};
            const panels = ConquerPanels({base:'',esc,fmt:value=>Number(value).toLocaleString('de-DE'),date:value=>new Date(value),duration:()=>'',countdown:()=>'',openDialog:html=>document.querySelector('#dialog-content').innerHTML=html,action:()=>{},api:()=>{},navigate:()=>{},refresh:()=>{},toast:()=>{},costHtml:()=>'',getState:()=>({city:{}}),getKingdom:()=>kingdom,getExpeditions:()=>({}),getMarket:()=>({}),getErrors:()=>({})});
            panels.render('alliance');
            document.querySelector('#panel-dialog').showModal();
        });

        for (const [width,height] of [[320,568],[390,844],[568,320],[1280,800]]) {
            await page.setViewportSize({width,height});
            const metrics = await page.evaluate(() => {
                const frame = document.querySelector('#panel-dialog').getBoundingClientRect();
                const overview = document.querySelector('.alliance-overview');
                const actions = [...document.querySelectorAll('.alliance-action')];
                overview.scrollTop = overview.scrollHeight;
                const last = actions.at(-1).getBoundingClientRect();
                const area = overview.getBoundingClientRect();
                return {
                    actions:actions.length,
                    text:overview.innerText,
                    frame:{left:frame.left,top:frame.top,right:frame.right,bottom:frame.bottom},
                    horizontalOverflow:overview.scrollWidth>overview.clientWidth+2,
                    smallTargets:actions.filter(button=>button.getBoundingClientRect().height<44).length,
                    lastReachable:last.top>=area.top-2&&last.bottom<=area.bottom+2,
                };
            });
            assert.equal(metrics.actions,8,'The overview exposes eight alliance destinations.');
            assert.match(metrics.text,/Sven von Grünland/,'The real alliance leader is shown.');
            assert.match(metrics.text,/1\.901\.940/,'Member power is aggregated for the alliance.');
            assert.equal(metrics.horizontalOverflow,false,'The overview must not scroll sideways.');
            assert.equal(metrics.smallTargets,0,'Every alliance destination stays touch sized.');
            assert.equal(metrics.lastReachable,true,'The final destination remains reachable.');
            assert(metrics.frame.left>=-2&&metrics.frame.top>=-2&&metrics.frame.right<=width+2&&metrics.frame.bottom<=height+2,'The alliance window stays inside the viewport.');
            await page.locator('.alliance-overview').evaluate(overview => { overview.scrollTop = 0; });
            await page.screenshot({path:path.join(output,`alliance-${width}x${height}.png`)});
        }
        console.log(`Alliance overview checks passed. Screenshots: ${output}`);
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
