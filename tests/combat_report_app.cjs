'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// php tools/preview-feature-fixture.php --port=18978 --combat-reports
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.COMBAT_FIXTURE_URL||'http://127.0.0.1:18978';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(process.env.COMBAT_REPORT_OUTPUT||path.join(__dirname,'../artifacts/combat-reports-compact'));fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{
  const context=await browser.newContext({viewport:{width:1280,height:900},hasTouch:true,permissions:['clipboard-read','clipboard-write']});
  // Exercise the real main app from its world view; this test does not cover the 3D city renderer.
  const worldEntry=()=>{if(location.pathname.endsWith('/city')&&!location.hash)history.replaceState(null,'','#world');};
  await context.addInitScript(worldEntry);
  const page=await context.newPage(),errors=[],failed=[];
  page.on('pageerror',e=>{if(!errors.includes(e.stack))console.error(e.stack);errors.push(e.stack);});
  page.on('response',r=>{if(r.status()>=400&&new URL(r.url()).origin===base)failed.push(r.status()+' '+r.url());});
  page.setDefaultTimeout(20000);
  await page.goto(base+'/?zugang=login');
  await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL(url=>url.pathname==='/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  await page.goto(base+'/city#reports');await page.locator('[data-action="mailbox-tab"][data-id="war"]').click();
  const mail=page.locator('.mail-card .mail-open').first();
  await mail.first().waitFor();
  assert.equal(await page.locator('.mail-card').count(),2,'Current and archive reports appear without duplicate enemy reports');
  async function bounds(selector){
   return page.locator(selector).evaluate(e=>{const r=e.getBoundingClientRect();return {inside:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:e.scrollWidth>e.clientWidth+2};});
  }
  for(const [width,height] of [[1280,900],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await mail.click();
   try{await page.locator('.combat-report').waitFor();}catch(e){console.error({errors,failed,dialog:await page.locator('#game-dialog').innerText()});await page.screenshot({path:path.join(output,'failure.png')});throw e;}
   assert.equal(await page.locator('.cr-identity').count(),2);
   assert(await page.locator('.cr-identity .cr-avatar').first().evaluate(el=>el.getBoundingClientRect().width>=72),'Saved participant art stays recognizable');
   assert.equal(await page.locator('.cr-identity.defender').evaluate(el=>getComputedStyle(el).borderBottomColor),'rgb(42, 114, 201)','Defender retains its blue role accent');
   assert(await page.locator('.cr-banner').evaluate(el=>{const rgb=value=>value.startsWith('#')?value.slice(1).match(/../g).map(c=>parseInt(c,16)):value.match(/[\d.]+/g).slice(0,3).map(Number);const lum=value=>rgb(value).map(n=>n/255).map(n=>n<=.04045?n/12.92:((n+.055)/1.055)**2.4).reduce((s,n,i)=>s+n*[.2126,.7152,.0722][i],0);const paper=lum(getComputedStyle(el).getPropertyValue('--ui-paper').trim());return [...el.querySelectorAll('small,strong,time')].every(node=>{const ink=lum(getComputedStyle(node).color);return(Math.max(ink,paper)+.05)/(Math.min(ink,paper)+.05)>=4.5;});}),'Report metadata has readable contrast on its paper surface');
   assert.match(await page.locator('.cr-versus').innerText(),/Elara/);
   assert.match(await page.locator('.cr-compare').first().innerText(),/21\.000/);
   const root=await bounds('#game-dialog'),scroll=await bounds('.cr-scroll');
   assert(root.inside&&!root.overflow&&!scroll.overflow,JSON.stringify({width,height,root,scroll}));
   assert.equal(await page.locator('.cr-fold:not([open])').count(),0,'All report comparisons start expanded');
   assert(await page.locator('.cr-compare').first().locator('td.cr-positive').count()>0,'Better overview values are green');
   assert(await page.locator('.cr-compare').first().locator('td.cr-negative').count()>0,'Worse overview values are red');
   if(height>=700)assert(await page.locator('.cr-resources').evaluate(e=>e.getBoundingClientRect().bottom<=document.querySelector('.cr-scroll').getBoundingClientRect().bottom),'Loot is visible immediately with the battle totals');
   assert.equal(await page.locator('[data-combat="details"]').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(42, 114, 201)','Primary action uses the readable blue action surface');
   assert.equal(await page.locator('.cr-footer .cr-report-arrow').count(),2,'Previous and next report stay in the fixed footer');
   await page.screenshot({path:path.join(output,`${width}x${height}-overview.png`)});
   await page.locator('[data-combat="metric"]').click();
   assert.match(await page.locator('[data-cr-power]').innerText(),/Anzahl entsandter Truppen/);
   await page.screenshot({path:path.join(output,`${width}x${height}-comparison.png`)});
   for(const key of ['equipment','talents','bonuses']){
    const fold=page.locator(`[data-cr-fold="${key}"]`);assert(await fold.getAttribute('open')!==null);const box=await bounds(`[data-cr-fold="${key}"]`);assert(!box.overflow);
    if(width===390){await fold.locator('.cr-columns,table').first().scrollIntoViewIfNeeded();await page.screenshot({path:path.join(output,`390x844-${key}.png`)});}
   }
   await page.locator('[data-combat="details"]').click();
   await page.locator('#combat-details[open]').waitFor();
   assert.equal(await page.locator('.cr-detail-side').count(),2);
   assert.equal(await page.locator('.cr-army').count(),3,'Defender reinforcements are separate');
   const d=await bounds('#combat-details'),ds=await bounds('.cr-detail-scroll');
   assert(d.inside&&!d.overflow&&!ds.overflow,JSON.stringify({width,height,d,ds}));
   assert(await page.locator('.cr-rules').getAttribute('open')!==null,'Combat rules start expanded');
   assert.match(await page.locator('.cr-rules').innerText(),/nicht separat erfasst/);
   await page.locator('.cr-rules summary').click();
   await page.screenshot({path:path.join(output,`${width}x${height}-details.png`)});
   const army=page.locator('.cr-army').last();assert(await army.getAttribute('open')!==null,'Every participating army starts expanded');
   await page.screenshot({path:path.join(output,`${width}x${height}-reinforcement.png`)});
   await page.goBack();await page.waitForFunction(()=>!document.querySelector('#combat-details').open);
   assert(await page.locator('#game-dialog').evaluate(e=>e.open),'Back from details returns to overview');
   await page.locator('[data-combat="copy"]').click();
   assert.match(await page.evaluate(()=>navigator.clipboard.readText()),/Union of Kingdoms · Kampfbericht/);
   await page.goBack();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
   assert(new URL(page.url()).pathname==='/city');
  }
  await page.setViewportSize({width:390,height:844});await mail.click();await page.locator('[data-combat="details"]').click();
  await page.keyboard.press('Escape');await page.waitForFunction(()=>history.state?.combatDepth===1);
  const shared=[];await page.route('**/api/community/action',async route=>{const payload=route.request().postDataJSON();if(payload.action==='chat.send'){shared.push(payload);await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,data:{message:'Bericht geteilt.'}})});return;}await route.continue();});
  await page.locator('[data-combat="share"]').click();await page.locator('#report-share-dialog[open]').waitFor();await page.waitForFunction(()=>!document.querySelector('[data-share-channel="world"]')?.disabled);await page.locator('[data-share-channel="world"]').click();await page.locator('.report-share-form button[type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#report-share-dialog').open);await page.waitForFunction(()=>!history.state?.conquerReportShare);assert.equal(shared.shift().channel,'world','World sharing is checked without sending a chat message');
  await page.locator('[data-combat="share"]').click();await page.locator('#report-share-dialog[open]').waitFor();await page.waitForFunction(()=>!document.querySelector('[data-share-channel="private"]')?.disabled);await page.locator('[data-share-channel="private"]').click();await page.locator('.cr-share-player select').selectOption('2');await page.screenshot({path:path.join(output,'390x844-share-private.png')});await page.locator('.report-share-form button[type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#report-share-dialog').open);await page.waitForFunction(()=>!history.state?.conquerReportShare);assert.equal(shared[0].channel,'private');assert.equal(shared[0].player_id,2);assert(Number(shared[0].report_id)>0);assert.match(shared[0].message,/Spieler-Kampfbericht/);assert(Array.from(shared[0].message).length<=200);await page.unroute('**/api/community/action');
  await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.waitForFunction(()=>!history.state?.conquerCombat);
  await page.route('**/api/battle/report/1',async route=>{const response=await route.fetch(),body=await response.json();body.data.report.can_share=false;await route.fulfill({response,json:body});});
  await page.goto(base+'/reports/1');await page.locator('.combat-report').waitFor();assert.equal(await page.locator('[data-combat="share"]').count(),0,'A received shared report cannot be shared onward');await page.unroute('**/api/battle/report/1');await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.waitForFunction(()=>!history.state?.conquerCombat);await page.goto(base+'/city#reports');await page.locator('[data-action="mailbox-tab"][data-id="war"]').click();await page.locator('.mail-card .mail-open').first().waitFor();
  await page.locator('.mail-card .mail-open').last().click();
  assert.match(await page.locator('.cr-notice').innerText(),/Älterer Bericht/);
  assert.equal(await page.locator('.cr-compare').first().locator('td').filter({hasText:'—'}).count(),6);
  await page.screenshot({path:path.join(output,'legacy-report.png')});
  await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.waitForFunction(()=>!history.state?.conquerCombat);
  assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);
  const other=await browser.newContext({viewport:{width:390,height:844}});await other.addInitScript(worldEntry);const def=await other.newPage();
  await def.goto(base+'/?zugang=login');await def.locator("[name=identifier], [name=username]").fill('Elara');await def.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([def.waitForURL(url=>url.pathname==='/city'),def.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);await def.goto(base+'/city#reports');await def.locator('[data-action="mailbox-tab"][data-id="war"]').click();
  const defenseMail=def.locator('.mail-card').filter({hasText:'Verteidigung: Niederlage'});await defenseMail.waitFor();assert.equal(await defenseMail.count(),1);
  await defenseMail.locator('.mail-open').click();assert.match(await def.locator('.cr-result').innerText(),/Niederlage[\s\S]*Verteidigung/);
  assert.match(await def.locator('.combat-report').innerText(),/Verlorene Ressourcen/);
  await def.screenshot({path:path.join(output,'defender-report.png')});
  await page.goto(base+'/reports/1');await page.locator('.combat-report').waitFor();
  assert.equal(new URL(page.url()).pathname,'/city','Direct player report links use the same main app renderer');
  await page.goto(base+'/reports/2');await page.locator('.mailbox-shell').waitFor();
  assert.equal(await page.locator('.combat-report').count(),0,'Direct links cannot open an opponent personal report');
  console.log('PASS real city app: five viewports, both perspectives, reinforcement, historical fallback, clipboard, nested dialog, touch, Back and Escape. '+output);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
