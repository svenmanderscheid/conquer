'use strict';
// php tools/preview-feature-fixture.php --port=18978 --combat-reports
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.COMBAT_FIXTURE_URL||'http://127.0.0.1:18978';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.join(__dirname,'../artifacts/combat-reports-compact');fs.mkdirSync(output,{recursive:true});
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
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL(url=>url.pathname==='/city'),page.locator('#auth-submit').click()]);
  await page.locator('#navigation [data-id="reports"]').click();
  const mail=page.locator('.mail-card .mail-open').first();
  await mail.first().waitFor();
  assert.equal(await page.locator('.mail-card').count(),2,'Current and archive reports appear without duplicate enemy reports');
  async function bounds(selector){
   return page.locator(selector).evaluate(e=>{const r=e.getBoundingClientRect();return {inside:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:e.scrollWidth>e.clientWidth+2};});
  }
  for(const [width,height] of [[1280,900],[390,844],[320,740],[844,390]]){
   await page.setViewportSize({width,height});await mail.click();
   try{await page.locator('.combat-report').waitFor();}catch(e){console.error({errors,failed,dialog:await page.locator('#game-dialog').innerText()});await page.screenshot({path:path.join(output,'failure.png')});throw e;}
   assert.equal(await page.locator('.cr-identity').count(),2);
   assert.match(await page.locator('.cr-versus').innerText(),/Elara/);
   assert.match(await page.locator('.cr-compare').first().innerText(),/21\.000/);
   const root=await bounds('#game-dialog'),scroll=await bounds('.cr-scroll');
   assert(root.inside&&!root.overflow&&!scroll.overflow,JSON.stringify({width,height,root,scroll}));
   assert.equal(await page.locator('.cr-fold:not([open])').count(),3,'Secondary comparisons start collapsed');
   if(height>=700)assert(await page.locator('.cr-resources').evaluate(e=>e.getBoundingClientRect().bottom<=document.querySelector('.cr-scroll').getBoundingClientRect().bottom),'Loot is visible immediately with the battle totals');
   assert.equal(await page.locator('[data-combat="details"]').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(42, 114, 201)','Primary action uses the readable blue action surface');
   assert.equal(await page.locator('.cr-footer .cr-report-arrow').count(),2,'Previous and next report stay in the fixed footer');
   await page.screenshot({path:path.join(output,`${width}x${height}-overview.png`)});
   await page.locator('[data-combat="metric"]').click();
   assert.match(await page.locator('[data-cr-power]').innerText(),/Anzahl entsandter Truppen/);
   await page.screenshot({path:path.join(output,`${width}x${height}-comparison.png`)});
   for(const key of ['equipment','talents','bonuses']){
    const fold=page.locator(`[data-cr-fold="${key}"]`);await fold.locator('summary').click();
    assert(await fold.getAttribute('open')!==null);const box=await bounds(`[data-cr-fold="${key}"]`);assert(!box.overflow);
    if(width===390){await fold.locator('.cr-columns,table').first().scrollIntoViewIfNeeded();await page.screenshot({path:path.join(output,`390x844-${key}.png`)});}
    await fold.locator('summary').click();
   }
   await page.locator('[data-combat="details"]').click();
   await page.locator('#combat-details[open]').waitFor();
   assert.equal(await page.locator('.cr-detail-side').count(),2);
   assert.equal(await page.locator('.cr-army').count(),3,'Defender reinforcements are separate');
   const d=await bounds('#combat-details'),ds=await bounds('.cr-detail-scroll');
   assert(d.inside&&!d.overflow&&!ds.overflow,JSON.stringify({width,height,d,ds}));
   await page.locator('.cr-rules summary').click();
   assert.match(await page.locator('.cr-rules').innerText(),/nicht separat erfasst/);
   await page.locator('.cr-rules summary').click();
   await page.screenshot({path:path.join(output,`${width}x${height}-details.png`)});
   const army=page.locator('.cr-army').last();await army.locator('summary').click();assert(await army.getAttribute('open')!==null);
   await page.screenshot({path:path.join(output,`${width}x${height}-reinforcement.png`)});
   await page.goBack();await page.waitForFunction(()=>!document.querySelector('#combat-details').open);
   assert(await page.locator('#game-dialog').evaluate(e=>e.open),'Back from details returns to overview');
   await page.locator('[data-combat="copy"]').click();
   assert.match(await page.evaluate(()=>navigator.clipboard.readText()),/Conquer · Kampfbericht/);
   await page.goBack();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
   assert(new URL(page.url()).pathname==='/city');
  }
  await page.setViewportSize({width:390,height:844});await mail.click();await page.locator('[data-combat="details"]').click();
  await page.keyboard.press('Escape');await page.waitForFunction(()=>history.state?.combatDepth===1);
  await page.locator('[data-combat="share"]').click();await page.locator('#report-share-dialog[open]').waitFor();await page.waitForFunction(()=>!document.querySelector('[data-share-channel="world"]')?.disabled);await page.locator('[data-share-channel="world"]').click();await page.locator('.report-share-form button[type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#report-share-dialog').open);await page.waitForFunction(()=>!history.state?.conquerReportShare);
  const shared=[];await page.route('**/api/community/action',async route=>{const payload=route.request().postDataJSON();if(payload.action==='chat.send'){shared.push(payload);await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,data:{message:'Bericht geteilt.'}})});return;}await route.continue();});
  await page.locator('[data-combat="share"]').click();await page.locator('#report-share-dialog[open]').waitFor();await page.waitForFunction(()=>!document.querySelector('[data-share-channel="private"]')?.disabled);await page.locator('[data-share-channel="private"]').click();await page.locator('.cr-share-player select').selectOption('2');await page.screenshot({path:path.join(output,'390x844-share-private.png')});await page.locator('.report-share-form button[type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#report-share-dialog').open);await page.waitForFunction(()=>!history.state?.conquerReportShare);assert.equal(shared[0].channel,'private');assert.equal(shared[0].player_id,2);assert(Number(shared[0].report_id)>0);assert.match(shared[0].message,/Spieler-Kampfbericht/);assert(Array.from(shared[0].message).length<=200);await page.unroute('**/api/community/action');
  await page.locator('.dialog-close').click();await page.waitForFunction(()=>!history.state?.conquerCombat);
  await page.locator('.panel-close').click();await page.locator('#world-chat:not([hidden])').waitFor();await page.locator('.world-chat-report').waitFor();await page.locator('.world-chat-report').last().click();await page.locator('.combat-report').waitFor();assert.equal(await page.locator('[data-combat="share"]').count(),0,'A received shared report cannot be shared onward');await page.locator('.dialog-close').click();await page.locator('#navigation [data-id="reports"]').click();await page.locator('.mail-card .mail-open').first().waitFor();
  await page.locator('.mail-card .mail-open').last().click();
  assert.match(await page.locator('.cr-notice').innerText(),/Älterer Bericht/);
  assert.equal(await page.locator('.cr-compare').first().locator('td').filter({hasText:'—'}).count(),6);
  await page.screenshot({path:path.join(output,'legacy-report.png')});
  await page.locator('.dialog-close').click();await page.waitForFunction(()=>!history.state?.conquerCombat);
  assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);
  const other=await browser.newContext({viewport:{width:390,height:844}});await other.addInitScript(worldEntry);const def=await other.newPage();
  await def.goto(base);await def.locator('[data-mode="login"]').click();await def.locator('[name="username"]').fill('Elara');await def.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([def.waitForURL(url=>url.pathname==='/city'),def.locator('#auth-submit').click()]);await def.locator('#navigation [data-id="reports"]').click();
  const defenseMail=def.locator('.mail-card').filter({hasText:'Verteidigung: Niederlage'});await defenseMail.waitFor();assert.equal(await defenseMail.count(),1);
  await defenseMail.locator('.mail-open').click();assert.match(await def.locator('.cr-result').innerText(),/Niederlage[\s\S]*Verteidigung/);
  assert.match(await def.locator('.combat-report').innerText(),/Verlorene Ressourcen/);
  await def.screenshot({path:path.join(output,'defender-report.png')});
  await page.goto(base+'/reports/1');await page.locator('.combat-report').waitFor();
  assert.equal(new URL(page.url()).pathname,'/city','Direct player report links use the same main app renderer');
  await page.goto(base+'/reports/2');await page.locator('.mailbox-shell').waitFor();
  assert.equal(await page.locator('.combat-report').count(),0,'Direct links cannot open an opponent personal report');
  console.log('PASS real city app: four viewports, both perspectives, reinforcement, historical fallback, clipboard, nested dialog, touch, Back and Escape. '+output);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
