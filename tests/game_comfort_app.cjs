'use strict';
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),net=require('net');
const {spawn}=require('child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/game-feel-2026-09-20');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--comfort','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='',page;const errors=[],checks=[];
 try{
  await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>reject(Error(log||'Preview timeout')),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timeout);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(timeout);reject(Error(log));});});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,locale:'de-DE'}),base='http://127.0.0.1:'+port;
  page=await context.newPage();page.setDefaultTimeout(25000);page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/?zugang=login');await page.locator('[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);await page.locator('[data-comfort=goal]').waitFor();
  assert.equal(await page.locator('[data-comfort=return]').count(),0,'first visit does not invent an absence');
  await page.locator('[data-comfort=goal]').click();await page.locator('#panel-dialog[data-panel=research]').waitFor();
  await page.keyboard.press('Escape');await page.locator('[data-comfort=dismiss-goal]').click();assert(await page.locator('.comfort-hint').isHidden());
  await page.reload();await page.locator('#hud-menu').waitFor();await page.waitForFunction(()=>document.querySelector('#save-state').textContent.includes('gespeichert'));assert(await page.locator('.comfort-hint').isHidden(),'dismissal survives reload');
  await page.addInitScript(()=>{const key='conquer:comfort:v1::1:1',value=JSON.parse(localStorage.getItem(key)||'{}');localStorage.setItem(key,JSON.stringify({...value,lastSeen:Math.floor(Date.now()/1000)-3600}));});
  await page.reload();await page.locator('[data-comfort=return]').waitFor();
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.locator('[data-comfort=return]').click();await page.locator('.return-summary').waitFor();
   assert.match(await page.locator('.return-summary').textContent(),/40 Truppen ausgebildet/);assert.match(await page.locator('.return-summary').textContent(),/1 Rückmärsche/);
   const layout=await page.locator('#game-dialog').evaluate(el=>{const r=el.getBoundingClientRect();return {fits:r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:el.scrollWidth-el.clientWidth};});assert(layout.fits&&layout.overflow<=1);checks.push({screen:'return',width,height,...layout});
   await page.screenshot({path:path.join(out,`return-${width}x${height}.png`)});await page.keyboard.press('Escape');
  }
  await page.locator('[data-comfort=return]').click();await page.locator('[data-comfort=done]').click();assert(await page.locator('.comfort-hint').isHidden(),'return dismissal retains goal preference');
  await page.setViewportSize({width:390,height:844});await page.locator('#hud-menu').click();await page.locator('#game-dialog [data-id=help]').click();
  await page.locator('[data-action=show-goal-hint]').click();await page.keyboard.press('Escape');await page.locator('[data-comfort=goal]').waitFor();
  await page.locator('#navigation [data-id=quests]').click();
  let claims=0;await page.route('**/api/kingdom/action',async route=>{if(route.request().postDataJSON().action==='quest.claim'){claims++;await new Promise(r=>setTimeout(r,1200));}await route.continue();});
  const claim=page.locator('[data-action=quest-claim]').first();await claim.click();assert.match(await claim.textContent(),/Wird bestätigt/);assert(await claim.isDisabled());
  await page.locator('.quest-followup').waitFor();assert.equal(claims,1);assert.match(await page.locator('.quest-followup').textContent(),/Belohnung abgeholt/);
  const nextReady=(await page.locator('[data-action=quest-next]').textContent()).includes('Belohnung');
  await page.locator('[data-action=quest-next]').click();
  if(nextReady){assert.equal(await page.locator('.quest-list').getAttribute('data-filter'),'ready');assert.equal(claims,1,'continuation never claims automatically');}else await page.waitForFunction(()=>!document.querySelector('.quest-list'));
  // Internal quest filter and list position survive leaving the panel.
  await page.evaluate(()=>location.hash='quests');await page.locator('.quest-list').waitFor();await page.locator('[data-group=quests][data-id=active]').click();
  for(let attempt=0;attempt<5;attempt++){
   await page.locator('.quest-list').evaluate(el=>el.scrollTop=200);const top=await page.locator('.quest-list').evaluate(el=>el.scrollTop);
   if(attempt%2)await page.locator('.panel-close').click();else await page.keyboard.press('Escape');await page.locator('#navigation [data-id=quests]').click();assert.equal(await page.locator('.quest-list').evaluate(el=>el.scrollTop),top);
  }
  await page.keyboard.press('Escape');await page.locator('#navigation [data-id=world]').click();await page.locator('.atlas-shell').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
  const state=(await(await context.request.get(base+'/api/game/state')).json()).data,monster=state.monsters.find(m=>m.definition?.type==='solo');assert(monster);
  const openMonster=async()=>{await page.evaluate(({x,y})=>ConquerWorld.focus(x,y),{x:Number(monster.coord_x),y:Number(monster.coord_y)});await page.locator(`[data-atlas-target="monsters:${monster.id}"]`).waitFor();await page.locator(`[data-atlas-target="monsters:${monster.id}"]`).click({force:true});await page.locator('#march-confirm').waitFor();};
  await openMonster();await page.locator('[data-action=march-clear]').click();await page.locator('#march-unit-50100101').fill('100');
  await page.waitForFunction(()=>document.querySelector('#march-preflight').textContent.includes('verwundet'));
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});assert.match(await page.locator('#march-preflight').textContent(),/Freie Hospitalplätze/);
   await page.locator('#march-preflight').scrollIntoViewIfNeeded();
   const layout=await page.locator('#game-dialog').evaluate(el=>{const r=el.getBoundingClientRect();return {fits:r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:el.scrollWidth-el.clientWidth};});assert(layout.fits&&layout.overflow<=1);checks.push({screen:'march',width,height,...layout});
   await page.screenshot({path:path.join(out,`march-${width}x${height}.png`)});
  }
  await page.setViewportSize({width:390,height:844});await page.locator('#march-confirm').click();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
  await page.waitForFunction(()=>localStorage.getItem('conquer:march-choice:v1::1:1:monsters'));
  const stored=await page.evaluate(()=>JSON.parse(localStorage.getItem('conquer:march-choice:v1::1:1:monsters')));assert.deepEqual(stored,{'50100101':100});
  await openMonster();assert.equal(await page.locator('#march-unit-50100101').inputValue(),'100');assert.equal(await page.locator('[data-action=march-default]').count(),1);
  await page.locator('[data-action=march-default]').click();assert.equal(await page.locator('.march-remembered').count(),0);
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({checks,errors,claims},null,2));console.log('PASS game comfort: feedback, goal hint, return summary, march forecast and memory, quest continuation and scroll; 5 viewports');
 }catch(error){if(page){console.error(await page.locator('dialog[open]').allTextContents());await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});}console.error(errors);throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
