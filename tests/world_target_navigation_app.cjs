'use strict';
// Actual app in an isolated preview. All feature interactions are read-only.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),net=require('net');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/world-target-navigation');fs.mkdirSync(out,{recursive:true});
(async()=>{
 let child,browser,log='',base=process.env.WORLD_TARGET_URL;
 try{
  if(!base){
   const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
   base='http://127.0.0.1:'+port;
   child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--map-search','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
   await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Fixture timeout')),60000);child.stdout.on('data',c=>{log+=c;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',c=>log+=c);child.on('exit',code=>{clearTimeout(timer);reject(Error('Fixture '+code+': '+log));});});
  }
  assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required');
  browser=await chromium.launch({headless:true,channel:'chrome'});
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,serviceWorkers:'block'}),errors=[],writes=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
  await require('./fixtures/inventory_access.cjs')(page);
  await page.goto(base);const csrf=await page.locator('[name=csrf]').first().inputValue();
  await page.request.post(base+'/auth/local',{form:{csrf,mode:'login',identifier:'PreviewPlayer',password:'PreviewFixture!2026'}});

  // Hold the scene commit explicitly; elapsed browser waits are not a reliable race gate.
  await page.route('**/assets/js/game.js*',async route=>{const response=await route.fetch();const body=(await response.text()).replace('sceneTransitionFrame=requestAnimationFrame(commitScene);','if(window.__holdSceneCommit)window.__heldSceneCommit=commitScene;else sceneTransitionFrame=requestAnimationFrame(commitScene);');assert.notEqual(body,await response.text(),'held scene hook matched');await route.fulfill({response,body});});
  const mapCalls=async()=>page.evaluate(()=>window.__mapCalls);
  async function instrument(){await page.evaluate(()=>{window.__mapCalls=[];for(const key of ['focus','locate']){const original=window.ConquerWorld[key];window.ConquerWorld[key]=(...args)=>{const result=original(...args);window.__mapCalls.push({key,args,result,mounted:!!document.querySelector('.atlas-viewport'),at:performance.now()});return result;};}});}
  if(!process.env.DUNGEON_ONLY){
  await page.goto(base+'/city#inventory');await page.reload();await page.locator('.inventory-shell').waitFor();await instrument();
  page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/')&&!new URL(r.url()).pathname.endsWith('/api/telemetry'))writes.push(r.url());});
  async function openChestSource(){await page.locator('[data-action=inventory-category][data-id=other]').click();await require('./fixtures/inventory_access.cjs').showItem(page,10105001);await page.locator('.inventory-inspector [data-action=item-sources]').click();await page.locator('.item-source-card').first().waitFor();}
  await openChestSource();await page.evaluate(()=>window.__holdSceneCommit=true);
  await page.locator('.item-source-card').filter({has:page.getByRole('button',{name:'Show on map'})}).first().getByRole('button').evaluate(button=>button.click());
  await page.waitForFunction(()=>location.hash==='#world'&&!!window.__heldSceneCommit);
  assert.equal((await mapCalls()).length,0,'no focus while first world scene is still unmounted');
  await page.evaluate(()=>{window.__holdSceneCommit=false;window.__heldSceneCommit();});
  await page.waitForFunction(()=>window.__mapCalls.some(c=>c.key==='locate'));
  let calls=await mapCalls();assert(calls.every(c=>c.mounted),'all target operations use a mounted map');assert.equal(calls.find(c=>c.key==='locate').result,true,'actual source target is selected after viewport refresh');
  assert.equal(await page.locator('.march-command').count(),0,'selection does not dispatch or open an attack form');
  console.log('First map source survives a delayed scene and fast response.');
  // A second route before the scene commits must cancel target selection.
  await page.goto(base+'/city#inventory');await page.reload();await page.locator('.inventory-shell').waitFor();await instrument();await openChestSource();await page.evaluate(()=>window.__holdSceneCommit=true);
  await page.locator('.item-source-card').filter({has:page.getByRole('button',{name:'Show on map'})}).first().getByRole('button').evaluate(button=>button.click());await page.waitForFunction(()=>location.hash==='#world');
  await page.waitForFunction(()=>!!window.__heldSceneCommit);await page.evaluate(()=>location.hash='help');await page.locator('.beginner-guide').waitFor();await page.evaluate(()=>{window.__holdSceneCommit=false;window.__heldSceneCommit();});await page.waitForTimeout(200);
  assert.equal((await mapCalls()).length,0,'new route cancels a pending source map target');
  // The beginner guide uses the same committed map target flow on the first map mount.
  await page.goto(base+'/city#help');await page.reload();await page.locator('.beginner-guide').waitFor();await instrument();
  await page.locator('.guide-tabs [data-id=goals]').click();await page.locator('[data-guide-goal=monster] button').click();
  await page.waitForFunction(()=>window.__mapCalls.some(c=>c.key==='locate'));calls=await mapCalls();assert(calls.every(c=>c.mounted));assert.equal(calls.find(c=>c.key==='locate').result,true,'beginner goal selects a real loaded target');
  console.log('Guide first-world navigation and route cancellation passed.');
  // A dungeon source must leave the remembered reports tab and reveal the exact adventure.
  }else{await page.goto(base+'/city#dungeons');await page.locator('.dungeon-shell').waitFor();page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/')&&!new URL(r.url()).pathname.endsWith('/api/telemetry'))writes.push(r.url());});}
  const dungeonData=(await (await page.request.get(base+'/api/dungeons/state')).json()).data;
  const upcoming=dungeonData.next_rotation[0],available=dungeonData.rotation[0]||dungeonData.permanent_dungeons.find(d=>d.dungeon_code==='melusina_well');
  if(!available){
   assert.deepEqual(dungeonData.rotation,[],'legacy world has no enabled weekly dungeons');
   assert.deepEqual(dungeonData.next_rotation,[],'disabled dungeons stay absent from next week');
   assert.deepEqual(dungeonData.permanent_dungeons,[],'legacy world has no permanent regional adventure');
   await page.evaluate(()=>location.hash='dungeons');await page.locator('.dungeon-shell').waitFor();
   await page.locator('[data-action=dungeon-tab][data-id=overview]').click();
   assert.equal(await page.locator('.dungeon-card,.dungeon-preview,.melusina-adventure').count(),0,'disabled world offers no dungeon cards or preview');
  }
  for(const [width,height,dungeon]of(available?[[1280,800,upcoming||available],[390,844,upcoming||available],[568,320,available]]:[])){
   await page.setViewportSize({width,height});await page.evaluate(()=>location.hash='dungeons');await page.locator('.dungeon-shell').waitFor();await page.locator('[data-action=dungeon-tab][data-id=reports]').click();
   await page.evaluate(()=>location.hash='treasures');await page.locator('.treasury-shell').waitFor();
   const detailClose=page.locator('[data-action=treasury-detail-close]');if(await detailClose.isVisible())await detailClose.click();
   await page.locator(`[data-action=treasury-select][data-id="${dungeon.treasure_code}"]`).click();await page.locator('.treasury-inspector [data-action=item-sources]').click();await page.locator('.item-source-card').first().waitFor();
   const lookup=(await (await page.request.get(base+'/api/item-sources?treasure_code='+dungeon.treasure_code)).json()).data;
   const index=lookup.sources.findIndex(s=>s.destination?.dungeon_code===dungeon.dungeon_code);assert(index>=0,'chosen dungeon has a real fragment source');
   await page.locator(`[data-action=item-sources-go][data-id="${index}"]`).click();await page.locator(`[data-dungeon-code="${dungeon.dungeon_code}"]`).waitFor();
   await page.waitForFunction(code=>document.activeElement?.dataset.dungeonCode===code,dungeon.dungeon_code);
   assert.equal(await page.locator('[data-action=dungeon-tab][data-id=overview]').getAttribute('aria-pressed'),'true','source replaces a remembered reports tab');
   const card=page.locator(`[data-dungeon-code="${dungeon.dungeon_code}"]`);assert(await card.isVisible(),'exact source is visible');
   const titleRect=await card.locator('h3').boundingBox();assert(titleRect&&titleRect.y>=50&&titleRect.y+titleRect.height<=height,'source name is visible even in short landscape');
   if(upcoming&&dungeon===upcoming)assert.equal(await card.evaluate(e=>e.closest('details').open),true,'next-week preview is expanded');
   assert.equal(await page.locator('.dungeon-planner').count(),0,'source only previews; starting a group remains deliberate');
   await page.screenshot({path:path.join(out,'dungeon-'+width+'x'+height+'.png')});
  }
  assert.deepEqual(writes,[],'all target handoffs are read-only');assert.deepEqual(errors,[],'no browser errors');
  console.log(process.env.DUNGEON_ONLY?'PASS DUNGEON SOURCES: current dungeon availability and read-only navigation.':'PASS WORLD TARGET APP: first scene, cancellation, guide, current dungeon availability and read-only navigation.');
 }finally{if(browser)await browser.close();if(child&&child.exitCode===null){const stopped=new Promise(resolve=>child.once('exit',resolve));child.stdin.end('\n');await stopped;}}
})().catch(e=>{console.error(e);process.exitCode=1;});
