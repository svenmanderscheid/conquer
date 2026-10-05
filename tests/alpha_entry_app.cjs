'use strict';
// Read-only new-player UI in disposable --alpha-entry --appearance fixture.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.ALPHA_ENTRY_UI_URL;
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/alpha-2026-10-04/new-player');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},locale:'de-DE',hasTouch:true}),errors=[];
  page.setDefaultTimeout(30000);page.setDefaultNavigationTimeout(45000);page.on('pageerror',e=>errors.push(e.message));
  page.on('console',m=>{if(m.text().startsWith('Game refresh failed:'))errors.push(m.text());});
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  await page.locator('#app-start').waitFor({state:'detached'});await page.locator('.painted-village-scene').waitFor();
  const state=await (await page.request.get(base+'/api/game/state')).json(),kingdom=await (await page.request.get(base+'/api/kingdom/state')).json();
  assert.equal(state.ok,true);assert.equal(kingdom.ok,true);assert.equal(state.data.world.map_profile.key,'luxembourg');assert.equal(Number(state.data.world.speed_factor),2);
  assert.equal(Number(kingdom.data.alpha_entry.starting_resources),500000);assert.equal(kingdom.data.quests.filter(q=>q.permanent).length,7);
  for(const key of ['food','lumber','stone','gold'])assert(Number(state.data.city[key])>=500000);
  assert.equal(await page.locator('html').getAttribute('lang'),'en','English is default despite German browser locale');
  await page.locator('.guide-welcome [data-action=guide-open]').click();await page.locator('.guide-alpha-entry').waitFor();
  assert.match(await page.locator('.guide-alpha-entry').innerText(),/500,000|500.000|500K/);assert.equal(await page.locator('.guide-menu-link').count(),5);
  for(const [width,height] of [[1280,800],[390,844],[844,390]]){
   await page.setViewportSize({width,height});await page.locator('.guide-body').evaluate(e=>e.scrollTop=0);
   const last=page.locator('.guide-menu-link').last();await last.scrollIntoViewIfNeeded();assert(await last.evaluate(e=>{const r=e.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return hit===e||e.contains(hit);}));
   assert.equal(await page.locator('.guide-body').evaluate(e=>e.scrollWidth>e.clientWidth+2),false);
   await page.locator('.guide-body').evaluate(e=>e.scrollTop=0);await page.screenshot({path:path.join(output,`guide-${width}x${height}.png`)});
  }
  await page.setViewportSize({width:390,height:844});await page.locator('.guide-menu-link[data-action=shop-section][data-id=crystals]').click();await page.locator('.trading-scroll').waitFor();assert.equal(await page.locator('[data-action=trading-tab][data-id=crystals]').getAttribute('aria-pressed'),'true','Guide opens the Crystal shop directly');
  assert.equal(await page.locator('[data-action=premium-checkout]').count(),0);await page.screenshot({path:path.join(output,'crystals-390x844.png')});
  assert.deepEqual(errors,[]);console.log('PASS Alpha new-player main app: Luxembourg 2x, 500k resources, English default, seven missions, illustrated guide touch in three sizes and no-payment Crystal shop.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
