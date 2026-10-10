'use strict';
// Real commands and settlement in a disposable database; gates exercise delayed/stale reads.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn,spawnSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'output/playwright/core-flow');
fs.mkdirSync(out,{recursive:true});
const deferred=()=>{let resolve;const promise=new Promise(r=>resolve=r);return {promise,resolve};};
(async()=>{
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--appearance','--core-flow'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 const base='http://127.0.0.1:'+port;let log='',browser,page,fixtureRoot,loginState;const errors=[],badAssets=[],checks=[];
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Fixture timeout')),60000);child.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',d=>log+=d);child.once('error',reject);child.once('exit',code=>{clearTimeout(timer);reject(Error('Fixture '+code+': '+log));});});
  fixtureRoot=log.match(/Core flow fixture root: ([^\r\n]+)/)?.[1];assert(fixtureRoot);
  const advance=(mode,id)=>{const result=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tests/Support/advance_core_flow.php',fixtureRoot,mode,...(id?[String(id)]:[])],{cwd:root,encoding:'utf8',windowsHide:true});assert.equal(result.status,0,result.stdout+result.stderr);return result.stdout?JSON.parse(result.stdout):null;};
  browser=await chromium.launch({headless:true,channel:'chrome'});
  const bounds=async(selector)=>{const box=await page.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return {fits:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:el.scrollWidth-el.clientWidth};});assert(box.fits&&box.overflow<=1,selector+' fits viewport');};
  const close=async()=>{if(await page.locator('#game-dialog').evaluate(el=>el.open))await page.keyboard.press('Escape');};
  const shot=async(name)=>{await page.screenshot({path:path.join(out,name+'.png')});};
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   const monster=advance('prepare');
   const context=await browser.newContext({viewport:{width,height},hasTouch:width<1000,...(loginState?{storageState:loginState}:{})});page=await context.newPage();page.setDefaultTimeout(20000);
   page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.url().includes('/assets/')&&r.status()>=400)badAssets.push(r.url());});
   await require('./fixtures/inventory_access.cjs')(page);
   if(!loginState){await page.goto(base+'/?zugang=login');await page.locator('[name=identifier],[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
   await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
   loginState=await context.storageState();}else await page.goto(base+'/city');
   await page.waitForFunction(()=>document.querySelector('#save-state')?.dataset.connection==='saved');await close();
   const getState=async()=>{const response=await context.request.get(base+'/api/game/state?map_x='+monster.x+'&map_y='+monster.y+'&map_radius=30');assert(response.ok());return (await response.json()).data;};
   const before=await getState();
   // Pan the real scrollable city so the farm is clear of the fixed HUD/chat.
   await page.locator('.painted-village-building[data-id=farm]').evaluate(building=>{const scroll=building.closest('.painted-village-scroll'),r=building.getBoundingClientRect();scroll.scrollLeft+=r.left+r.width/2-innerWidth/2;scroll.scrollTop+=r.top+r.height/2-innerHeight*.53;});
   const ready=page.locator('.painted-building-ready[data-id=farm]');if(await ready.isVisible())await ready.click();
   await page.locator('.painted-village-building[data-id=farm]').click();await shot(width+'x'+height+'-city');
   await page.locator('.painted-building-actions [data-action=building]').click();await bounds('#game-dialog');await shot(width+'x'+height+'-upgrade');
   const gate=deferred(),oldReady=deferred();let hold=true,firstPrimary=true,markKingdom=true;
   await page.route('**/api/game/state*',async route=>{const response=await route.fetch();if(hold&&firstPrimary){firstPrimary=false;const json=await response.json();json.data.city.food=1;oldReady.resolve();await gate.promise;await route.fulfill({response,json});}else await route.fulfill({response});});
   await page.route('**/api/kingdom/state',async route=>{const response=await route.fetch();if(hold){const json=await response.json();if(markKingdom){markKingdom=false;json.data.profile.display_name='Stale snapshot';}await gate.promise;await route.fulfill({response,json});}else await route.fulfill({response});});
   await page.evaluate(()=>{window.__stableResource=document.querySelector('#resources .resource');window.__stableWorldButton=document.querySelector('#navigation [data-id=world]');dispatchEvent(new Event('online'));});
   await oldReady.promise;
   const upgradeResponse=page.waitForResponse(r=>r.url().endsWith('/api/city/upgrade-building'));
   await page.locator('[data-action=upgrade][data-id=farm]').click();
   const response=await upgradeResponse,json=await response.json();assert(response.ok(),JSON.stringify(json));assert(json.ok);
   // An older, unresolved poll must not keep the command pending.
   await page.waitForFunction(()=>!document.querySelector('#game-dialog').open,null,{timeout:2500});
   await page.waitForFunction(()=>document.querySelector('#hud-build')?.dataset.jobState==='active',null,{timeout:2500});
   assert(hold,'old reads remain gated when upgrade is visible');hold=false;gate.resolve();
   await page.waitForFunction(()=>document.querySelector('#save-state').dataset.connection==='saved');
   assert(await page.evaluate(()=>window.__stableResource===document.querySelector('#resources .resource')),'resource control survives polling');
   assert(await page.evaluate(()=>window.__stableWorldButton===document.querySelector('#navigation [data-id=world]')),'unchanged navigation survives polling');
   assert.notEqual(await page.locator('#resources [data-id=food] .hud-value-full').innerText(),'1','old city snapshot never overwrites the confirmed upgrade');
   assert.notEqual(await page.locator('#player-hud-name').innerText(),'Stale snapshot','old kingdom snapshot never overwrites the confirmed command');
   await page.unrouteAll({behavior:'wait'});
   const queueId=(await getState()).build_queue.find(q=>q.building_code==='farm')?.id;assert(queueId);
   advance('building',queueId);const built=await getState();assert.equal(Number(built.buildings.farm.level),Number(before.buildings.farm.level)+1);
   await page.evaluate(()=>{window.__sceneClick=0;window.__sceneFinished=0;window.__worldCosts=[];for(const [module,key]of [[ConquerWorld,'render'],[ConquerLandscape,'ground']]){const original=module[key];module[key]=(...args)=>{const started=performance.now();const result=original(...args);window.__worldCosts.push({key,ms:performance.now()-started});return result;};}document.querySelector('#navigation').addEventListener('click',()=>window.__sceneClick=performance.now(),{once:true});const veil=document.querySelector('#scene-transition');new MutationObserver(()=>{if(window.__sceneClick&&!veil.classList.contains('is-active'))window.__sceneFinished=performance.now();}).observe(veil,{attributes:true,attributeFilter:['class']});});
   await page.locator('#navigation [data-id=world]').click();await page.locator('.atlas-shell').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
   const sceneMs=await page.evaluate(()=>window.__sceneFinished-window.__sceneClick),worldCosts=await page.evaluate(()=>window.__worldCosts);console.log('First map transition '+width+'x'+height+': '+sceneMs.toFixed(1)+' ms; '+JSON.stringify(worldCosts));assert(sceneMs>0&&sceneMs<2000,'first map mount has no deliberate one-second wait: '+sceneMs+' ms');
   await page.evaluate(({x,y})=>ConquerWorld.focus(Number(x),Number(y)),monster);
   await page.locator(`[data-atlas-target="monsters:${monster.id}"]`).waitFor();await shot(width+'x'+height+'-world');
   await page.locator(`[data-atlas-target="monsters:${monster.id}"]`).click({force:true});await page.locator('#march-confirm').waitFor();await page.locator('[data-action=march-max]').click();
   await bounds('#game-dialog');await shot(width+'x'+height+'-attack');
   const dispatchResponse=page.waitForResponse(r=>r.url().endsWith('/api/march/dispatch'));await page.locator('#march-confirm').click();const sentResponse=await dispatchResponse,sent=await sentResponse.json();assert(sentResponse.ok(),JSON.stringify(sent));
   await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);await page.waitForFunction(()=>localStorage.getItem('conquer:march-choice:v1::1:1:monsters'));
   advance('march',sent.data.march_id);const fought=await getState(),report=fought.reports.find(r=>Number(r.id)>Math.max(0,...before.reports.map(r=>Number(r.id)))&&Number(r.target_type)===3);
   assert(report,'actual dispatched monster battle produces a confirmed report');assert.equal(report.outcome,'attacker_wins');
   const detail=(await(await context.request.get(base+'/api/battle/report/'+report.id)).json()).data.report;assert(Object.values(detail.details.loot||{}).some(n=>Number(n)>0)||detail.details.item_rewards?.some(r=>r.count>0)||detail.details.fragment_rewards?.some(r=>r.count>0),'real server battle has confirmed rewards');
   await page.locator('#hud-mail:visible,#navigation [data-id=reports]:visible').first().click();await page.locator('[data-action=mailbox-tab][data-id=reports]').click();await page.locator('[data-action=mailbox-open]').first().click();await page.locator(`.monster-report[data-monster-report="${report.id}"]`).waitFor();
   assert(await page.locator('.mr-reward').count()>0);await bounds('#game-dialog');await shot(width+'x'+height+'-reward');await close();
   checks.push({width,height,sceneMs,worldCosts,queueId,marchId:sent.data.march_id,reportId:report.id,oldReadsIgnored:true});
   await context.close();page=null;
   console.log('PASS core flow '+width+'x'+height);
  }
  assert.deepEqual(errors,[]);assert.deepEqual(badAssets,[]);fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({checks,errors,badAssets},null,2));
 }catch(error){console.error('Core flow failure:',error);if(page&&!page.isClosed()){await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});console.error(await page.locator('dialog[open]').allTextContents());}throw error;}
 finally{if(page&&!page.isClosed())await page.unrouteAll({behavior:'ignoreErrors'});if(browser)await browser.close();if(child.exitCode===null){const stopped=new Promise(r=>child.once('exit',r));child.stdin.end('\n');await stopped;}}
})().catch(error=>{console.error(error);process.exitCode=1;});
