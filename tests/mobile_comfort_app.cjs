'use strict';
// Actual main app, disposable database, synthetic read snapshots, no gameplay writes.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'output/playwright/mobile-comfort-20261009');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const port=s.address().port;s.close(()=>resolve(port));});});
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--hud','--chat'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,page,log='';const writes=[],errors=[],checks=[];
 try{
  await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>reject(Error(log||'Preview timeout')),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timeout);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(timeout);reject(Error(log));});});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,locale:'en-US'});
  await context.addInitScript(()=>{
   localStorage.setItem('conquer.locale','en');
   window.Capacitor={isNativePlatform:()=>true,Plugins:{App:{addListener(type,listener){if(type==='appStateChange')window.testNativeAppState=listener;return Promise.resolve({remove(){}});}}}};
  });
  page=await context.newPage();page.setDefaultTimeout(25000);page.on('pageerror',e=>errors.push(e.message));
  let reads=0,forceChange=false,serverUnavailable=false;
  await page.route('**/api/game/state*',async route=>{
   reads++;
   if(serverUnavailable){await route.abort('failed');return;}
   const response=await route.fetch(),json=await response.json(),s=json.data;
   s.trained_total=0;s.research={};s.build_queue=[];s.troop_queue=[];s.research_queue=[];s.plot_queue=[];
   for(const b of Object.values(s.buildings)){b.level=1;b.cost={food:0,lumber:0,stone:0,gold:0};b.requirements={};b.item_requirements=[];}
   if(forceChange)s.buildings.farm.level=2;
   await route.fulfill({response,json});
  });
  const base='http://127.0.0.1:'+port;
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/')&&!new URL(r.url()).pathname.endsWith('/api/telemetry'))writes.push(r.url());});
  await page.locator('.painted-village').waitFor();await page.locator('.kingdom-intro').waitFor();
  const sizes=[[1280,800],[390,844],[320,568],[844,390],[568,320]];
  for(const [width,height]of sizes){
   await page.setViewportSize({width,height});
   // The shared HUD applies its responsive layout profile on the next frame.
   await page.waitForTimeout(150);
   for(let step=0;step<3;step++)await page.locator('[data-action=guide-intro-next]').click();
   await page.locator('.kingdom-intro-next').waitFor();
   assert.match(await page.locator('.kingdom-intro-next').innerText(),/Your next step/);
   for(const button of await page.locator('.kingdom-intro-actions button').all()){
    const r=await button.boundingBox();assert(r&&r.x>=0&&r.y>=0&&r.x+r.width<=width+1&&r.y+r.height<=height+1&&r.height>=44,`Reachable intro action at ${width}x${height}`);
   }
   assert.equal(await page.locator('.kingdom-intro-copy').evaluate(e=>e.scrollWidth>e.clientWidth+1),false);
   await page.locator('.kingdom-intro-next').scrollIntoViewIfNeeded();
   await page.screenshot({path:path.join(out,`intro-${width}x${height}.png`)});checks.push({screen:'intro',width,height});
   if(width!==568)for(let step=0;step<3;step++)await page.locator('[data-action=guide-intro-back]').click();
  }
  await page.locator('[data-action=guide-intro-start]').click();
  await page.waitForFunction(()=>document.querySelector('#game-dialog').open&&document.querySelector('#game-dialog').dataset.building==='castle');
  assert.equal(await page.locator('.kingdom-intro').count(),0,'handoff replaces the adviser with the existing upgrade preview');
  assert.deepEqual(writes,[],'intro handoff has no game command');
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
  await page.setViewportSize({width:390,height:844});await page.locator('#hud-menu').click();await page.locator('#game-dialog [data-action=dialog-tab][data-id=army]').click();await page.locator('#train-count').waitFor();await page.locator('#train-count').fill('23');
  await page.evaluate(()=>{window.testEditedInput=document.querySelector('#train-count');window.testNativeAppState({isActive:false});});
  await page.waitForTimeout(250);const pausedReads=reads;
  await page.evaluate(()=>{dispatchEvent(new Event('pageshow'));dispatchEvent(new Event('online'));});await page.waitForTimeout(500);
  assert.equal(reads,pausedReads,'visible WebView in native background performs no state reads');
  forceChange=true;
  await Promise.all([page.waitForResponse(r=>r.url().includes('/api/game/state')&&r.ok()),page.evaluate(()=>window.testNativeAppState({isActive:true}))]);
  await page.waitForFunction(()=>document.querySelector('#save-state').dataset.connection==='saved');
  assert.equal(await page.locator('#train-count').inputValue(),'23');
  assert.equal(await page.evaluate(()=>document.querySelector('#train-count')===window.testEditedInput&&document.activeElement===window.testEditedInput),true,'changed server snapshot preserves the actual focused field');
  await context.setOffline(true);await page.waitForFunction(()=>document.querySelector('#save-state').dataset.connection==='offline');
  const offlineReads=reads;
  await page.evaluate(()=>{window.testNativeAppState({isActive:false});window.testNativeAppState({isActive:true});});await page.waitForTimeout(500);
  assert.equal(reads,offlineReads,'native return cannot send a read while offline');
  await context.setOffline(false);await page.waitForFunction(()=>document.querySelector('#save-state').dataset.connection==='saved');
  assert.equal(await page.locator('#train-count').inputValue(),'23','reconnect retains the selection');
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#panel-dialog').open);
  serverUnavailable=true;
  await page.locator('#hud-menu').click();await page.locator('[data-action=connection-refresh]').click();await page.waitForFunction(()=>document.querySelector('#save-state').dataset.connection==='failed');
  for(const [width,height]of sizes){
   await page.setViewportSize({width,height});
   await page.waitForTimeout(150);
   const issues=await page.evaluate(()=>{
    const banner=document.querySelector('#save-state'),r=banner.getBoundingClientRect(),issues=[];
    if(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1)issues.push('Connection notice outside viewport');
    for(const button of document.querySelectorAll('#hud-menu,.hud-edge-button,#navigation button,.topbar button')){
     const b=button.getBoundingClientRect(),style=getComputedStyle(button);
     if(!b.width||!b.height||style.visibility==='hidden'||style.display==='none'||Number(style.opacity)===0)continue;
     if(Math.min(r.right,b.right)-Math.max(r.left,b.left)>1&&Math.min(r.bottom,b.bottom)-Math.max(r.top,b.top)>1)issues.push('Covered HUD action: '+(button.id||button.textContent)+' '+JSON.stringify({notice:{x:r.x,y:r.y,width:r.width,height:r.height},button:{x:b.x,y:b.y,width:b.width,height:b.height}}));
    }
    return issues;
   });
   assert.deepEqual(issues,[],`Connection notice keeps HUD actions visible at ${width}x${height}`);
   await page.screenshot({path:path.join(out,`connection-failed-${width}x${height}.png`)});checks.push({screen:'connection',width,height});
  }
  serverUnavailable=false;
  for(const [width,height]of sizes){
   await page.setViewportSize({width,height});await page.locator('#hud-menu').click();const retry=page.locator('[data-action=connection-refresh]');await retry.scrollIntoViewIfNeeded();
   const r=await retry.boundingBox();assert(r&&r.x>=0&&r.y>=0&&r.x+r.width<=width+1&&r.y+r.height<=height+1&&r.height>=44,'Manual refresh is touch sized and reachable');
   await page.screenshot({path:path.join(out,`menu-${width}x${height}.png`)});await retry.click();await page.waitForFunction(()=>document.querySelector('#save-state').dataset.connection==='saved');checks.push({screen:'menu',width,height});
  }
  assert.deepEqual(writes,[],'all connection recovery actions only read state');assert.deepEqual(errors,[]);
  fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({checks,writes,errors,reads,nativeLifecycle:'simulated bridge; no physical-device acceptance'},null,2));
  console.log('PASS mobile comfort: intro handoff, native pause/resume, focused input, offline and server recovery, read-only manual refresh; five viewports');
 }catch(error){if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
