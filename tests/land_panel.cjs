'use strict';
// Static browser fixture: no game API or shared database is used.
const fs = require('fs');
const path = require('path');
const os = require('os');
const http = require('http');
const assert = require('assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '..');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'conquer-land-panel-'));
const lands = Array.from({length:1024}, (_, index) => {
  const x = index % 32;
  const y = Math.floor(index / 32);
  const edge = Math.min(x, y, 31 - x, 31 - y);
  const zone = edge < 6 ? 'outer' : edge < 12 ? 'middle' : 'center';
  const open = zone === 'outer';
  return {
    id:index + 1, parcel_x:x, parcel_y:y,
    bounds:{x_min:x * 8, y_min:y * 8, x_max:x * 8 + 7, y_max:y * 8 + 7},
    center:{x:x * 8 + 4, y:y * 8 + 4}, zone,
    initial_level:zone === 'outer' ? 1 : zone === 'middle' ? 4 : 7,
    level:index === 0 ? 3 : zone === 'outer' ? 1 : zone === 'middle' ? 4 : 7,
    points:index === 0 ? 45 : 0, next_threshold:100,
    progress_pct:index === 0 ? 45 : 0, open, developable:open, own_land:index === 0
  };
});
const zones = [
  {key:'outer',open:true,opened_at:'2026-09-12 08:00:00',eligible_count:624,required_count:null,target_level:null,reached_count:null,not_before:null,progress_pct:null},
  {key:'middle',open:false,opened_at:null,eligible_count:336,required_count:40,target_level:5,reached_count:0,not_before:'2026-09-19 08:00:00',progress_pct:0},
  {key:'center',open:false,opened_at:null,eligible_count:64,required_count:12,target_level:8,reached_count:0,not_before:null,progress_pct:0}
];
const fixtureState = {geometry:{parcel_size:8,map_size:256,columns:32,rows:32},zones,lands};
const fixtureDetail = {
  ...lands[0],
  sources:[{source:'monster_kill',points:30,event_count:3},{source:'gather',points:15,event_count:1}],
  own_daily:[{source:'monster_kill',raw_points:30,credited_points:30,date:'2026-09-12'}],
  recent_events:[{id:1,source:'monster_kill',points:10,level_before:2,level_after:3,created_at:'2026-09-12 10:00:00'}],
  can_donate:true, revision:4, donation:{resource_values:{food:1,lumber:1,stone:2,gold:4},resource_units_per_point:100,daily_limit_points:100,remaining_points:100},
  monsters:[{code:20200101,name:'Orc',level:1,type:'solo',biome:'forest',art:'orc',resource_reward:{food:100},drops:[{item_code:10103001,label:'Bau-Beschleuniger',count:1,probability:.25}],gems_drop:null,guaranteed_charms:true}]
};
delete fixtureDetail.own_land;
const fixture = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>:root{--ui-paper:#f6ecd8;--ui-card:#fff6df;--ui-card-light:#fffaf0;--ui-inset:#eeddbc;--ui-disabled:#e5dac3;--ui-ink:#493b33;--ui-muted:#786345;--ui-line:#c7ab7b;--ui-frame:#70472f;--ui-blue:#2a72c9;--ui-blue-dark:#24559c;--ui-green:#74a452;--ui-green-dark:#486c35;--ui-green-soft:#e9efd3;--ui-gold:#e4af38;--ui-gold-soft:#fff0c4;--ui-font:"Segoe UI",sans-serif}html,body,#content{height:100%;margin:0;overflow:hidden}.button{min-height:40px;border:1px solid var(--ui-line);border-radius:9px;background:var(--ui-card);color:var(--ui-ink)}.button.gold{background:var(--ui-green);color:#fff}</style>
<link rel="stylesheet" href="/assets/css/land-panel.css"></head><body><main id="content"></main>
<script>window.fixtureState=${JSON.stringify(fixtureState)};window.fixtureDetail=${JSON.stringify(fixtureDetail)};window.calls=[];window.toasts=[];window.failDonation='network';window.resolveDonation=null;</script>
<script src="/assets/js/land-panel.js"></script><script>
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=n=>Math.floor(Number(n)||0).toLocaleString('de-DE');
const api=async(path,payload)=>{calls.push({path,payload:payload?JSON.parse(JSON.stringify(payload)):null});if(path==='land/state')return fixtureState;if(/^land\\/\\d+$/.test(path)){const land={...fixtureState.lands.find(item=>item.id===Number(path.split('/')[1]))};delete land.own_land;return{...fixtureDetail,...land};}if(path.endsWith('/donate')){if(failDonation==='network'){failDonation=null;throw new Error('Verbindung unterbrochen');}if(failDonation==='stale'){failDonation=null;const error=new Error('Der Landteil wurde zwischenzeitlich verändert.');error.code='STALE_LAND';throw error;}if(failDonation==='slow')return new Promise(resolve=>{resolveDonation=()=>resolve({message:'ok'});});return{message:'ok'};}throw new Error('unknown '+path);};
window.panel=ConquerLand({api,esc,fmt,toast:m=>toasts.push(m),navigate:t=>window.navigated=t,getState:()=>({city:{world_id:1,food:1000,lumber:1000,stone:1000,gold:1000}})});
panel.render('land');
document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)panel.onClick(b.dataset.action,b);});
document.addEventListener('submit',e=>{const f=e.target.closest('form[data-form]');if(!f)return;e.preventDefault();panel.onSubmit(f);});
</script></body></html>`;

async function main() {
  const server = http.createServer((req, res) => {
    const pathname = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
    if (pathname === '/') { res.setHeader('Content-Type', 'text/html'); res.end(fixture); return; }
    const file = path.resolve(root, '.' + pathname);
    if (!file.startsWith(root + path.sep) || !fs.existsSync(file)) { res.writeHead(404); res.end(); return; }
    res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'application/octet-stream');
    res.end(fs.readFileSync(file));
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  let browser;
  try {
    browser = await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || 'chrome'});
    for (const viewport of [{width:1280,height:800},{width:390,height:844},{width:320,height:740},{width:568,height:320}]) {
      const page = await browser.newPage({viewport});
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(`http://127.0.0.1:${server.address().port}`);
      const canvas = page.locator('.land-map');
      await canvas.waitFor();
      assert.equal(await canvas.getAttribute('width'), '768');
      assert.equal(await canvas.getAttribute('height'), '768');
      assert.equal(await page.locator('.land-cell').count(), 0);
      assert((await page.locator('.land-result-row').count()) <= 24);
      assert.match(await page.locator('.land-zone').first().textContent(), /Ab Weltstart geöffnet/);
      assert.doesNotMatch(await page.locator('.land-zone').first().textContent(), /0 \/ 0|Stufe 0/);
      await page.locator('[data-action="land-filter"][data-id="own"]').click();
      assert.equal(await page.locator('.land-result-row').count(), 1);
      await page.locator('[data-action="land-filter"][data-id="all"]').click();
      await page.locator('.land-result-row').first().click();
      await page.locator('.land-loot-list').waitFor();
      assert.match(await page.locator('.land-detail').textContent(), /Orc.*Bau-Beschleuniger.*1 Karten-Charm garantiert/s);
      assert.match(await page.locator('.land-detail').textContent(), /Bei deiner Stadt/);
      assert.match(await page.locator('.land-detail').textContent(), /Heute noch 100 Entwicklungspunkte.*100 Nahrung = 1 Punkt.*50 Stein = 1 Punkt.*25 Gold = 1 Punkt/s);
      const bounds = await page.locator('.land-shell').evaluate(el => ({scrollWidth:el.scrollWidth,clientWidth:el.clientWidth}));
      assert(bounds.scrollWidth <= bounds.clientWidth + 1, JSON.stringify({viewport,bounds}));
      if (viewport.width === 390) {
        const form = page.locator('[data-form="land-donate"]');
        await form.locator('[name="food"]').fill('25');
        await form.locator('button').click();
        await page.waitForFunction(() => window.toasts.includes('Verbindung unterbrochen'));
        await form.locator('button').click();
        await page.waitForFunction(() => window.calls.filter(c => c.path.endsWith('/donate')).length === 2);
        const posts = await page.evaluate(() => calls.filter(c => c.path.endsWith('/donate')));
        assert.equal(posts[0].payload.request_id, posts[1].payload.request_id);
        assert.deepEqual(posts[0].payload.resources, {food:25,lumber:0,stone:0,gold:0});
        assert.equal(posts[0].payload.expected_world_id, 1);
        assert.equal(posts[0].payload.revision, 4);
        await page.evaluate(() => { failDonation = 'stale'; });
        await form.locator('[name="food"]').fill('5');
        await form.locator('button').click();
        await page.waitForFunction(() => window.toasts.includes('Der Landteil wurde zwischenzeitlich verändert.'));
        await page.waitForFunction(() => window.calls.filter(c => /^land\/\d+$/.test(c.path)).length >= 4);
        const rejectedId = await page.evaluate(() => calls.filter(c => c.path.endsWith('/donate')).at(-1).payload.request_id);
        await form.locator('[name="food"]').fill('5');
        await form.locator('button').click();
        await page.waitForFunction(() => window.calls.filter(c => c.path.endsWith('/donate')).length === 4);
        const acceptedId = await page.evaluate(() => calls.filter(c => c.path.endsWith('/donate')).at(-1).payload.request_id);
        assert.notEqual(rejectedId, acceptedId);
        const map = page.locator('.land-map');
        const box = await map.boundingBox();
        await page.mouse.click(box.x + box.width * 1.5 / 32, box.y + box.height * .5 / 32);
        await page.waitForFunction(() => document.querySelector('.land-detail h3')?.textContent.includes('2/1'));
        await page.evaluate(() => { failDonation = 'slow'; });
        const nextForm = page.locator('[data-form="land-donate"]');
        await nextForm.locator('[name="food"]').fill('5');
        await nextForm.locator('button').click();
        await page.waitForFunction(() => typeof window.resolveDonation === 'function');
        await page.evaluate(() => { panel.render('city'); document.querySelector('#content').innerHTML = '<div id="city-safe">3D-Stadt bleibt aktiv</div>'; resolveDonation(); });
        await page.waitForTimeout(50);
        assert.equal(await page.locator('#city-safe').textContent(), '3D-Stadt bleibt aktiv');
      }
      assert.deepEqual(errors, []);
      await page.screenshot({path:path.join(output, `${viewport.width}x${viewport.height}.png`)});
      await page.close();
    }
    console.log(`PASS 32 x 32 canvas land panel, bounded list and stable donation retry at required widths. Screenshots: ${output}`);
  } finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
