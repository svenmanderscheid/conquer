'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.MELUSINA_FIXTURE_URL||'http://127.0.0.1:18957';
const fixture=process.env.MELUSINA_FIXTURE_ROOT;
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base)&&fixture&&/^conquer_feature_test_[a-f0-9]{12}$/.test(path.basename(fixture)),'Disposable --melusina fixture required');
const output=path.resolve('output/playwright/melusina');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const errors=[],failures=[];let checks=0;
 const check=(value,message)=>{assert(value,message);checks++;};
 const context=await browser.newContext({viewport:{width:1280,height:800},locale:'fr-FR'});
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.url().startsWith(base+'/api/')&&r.status()>=400)failures.push(`${r.status()} ${r.url()}`);});
 async function login(p,name){
  await p.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});
  await p.locator('[name=identifier],[name=username]').fill(name);await p.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([p.waitForURL('**/city'),p.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  await p.locator('#hud-menu').waitFor();
 }
 async function open(p){await p.goto(base+'/city#dungeons',{waitUntil:'domcontentloaded'});await p.locator('.melusina-adventure').waitFor();}
 async function action(p,selector,kind){const waiting=p.waitForResponse(r=>r.url().endsWith('/api/dungeons/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action===kind);await p.locator(selector).click();const result=await(await waiting).json();check(result.ok,JSON.stringify(result.error));return result.data;}
 async function layout(p,name){
  for(const [width,height]of[[1280,800],[390,844],[320,568],[740,360]]){
   await p.setViewportSize({width,height});await p.locator('.melusina-adventure').scrollIntoViewIfNeeded();
   const geometry=await p.evaluate(()=>{const dialog=document.querySelector('#panel-dialog'),panel=document.querySelector('.melusina-adventure'),r=dialog.getBoundingClientRect();return{frame:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:panel.scrollWidth>panel.clientWidth+2,small:[...panel.querySelectorAll('button')].filter(b=>b.checkVisibility()&&b.getBoundingClientRect().height<44).map(b=>b.textContent),scrollbar:getComputedStyle(document.querySelector('.dungeon-body')).scrollbarWidth};});
   check(geometry.frame&&!geometry.overflow,`${name} fits ${width}x${height}: ${JSON.stringify(geometry)}`);
   check(geometry.small.length===0,`Touch targets ${JSON.stringify(geometry.small)}`);
   await p.screenshot({path:path.join(output,`${name}-${width}x${height}.png`)});
  }
  await p.setViewportSize({width:1280,height:800});
 }
 try{
  await login(page,'PreviewPlayer');await page.goto(base+'/city#city');await page.locator('#hud-menu').waitFor();
  for(const [width,height]of[[1280,800],[390,844],[740,360]]){
   await page.setViewportSize({width,height});await page.locator('.painted-village-building[data-id="castle"]').click();
   await page.locator('.painted-building-actions [data-action="building"]').click();await page.locator('#game-dialog[open]').waitFor();
   await page.screenshot({path:path.join(output,`city-building-${width}x${height}.png`)});
   check(await page.locator('#game-dialog').evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=-1&&r.right<=innerWidth+1;}),'City building dialog fits viewport');
   await page.locator('#game-dialog .dialog-close').click();await page.locator('#game-dialog').waitFor({state:'hidden'});
  }
  await page.setViewportSize({width:1280,height:800});await page.screenshot({path:path.join(output,'city.png')});await open(page);
  check((await page.locator('.melusina-adventure').innerText()).includes('Accept the quest'),'English default despite French browser');
  await layout(page,'intro');
  console.log('PASS city, building actions and introduction layouts');
  await action(page,'[data-action="dungeon-melusina-accept"]','accept_melusina');
  await page.locator('.melusina-sources summary').click();check(await page.locator('[data-action="dungeon-melusina-source"]').count()>0,'Actual regional sources exist');
  await page.locator('[data-action="dungeon-melusina-source"]').first().click();
  await page.locator('.atlas-target-actions').waitFor({state:'visible'});check((await page.locator('.atlas-target-actions').innerText()).includes('Attack'),'Source navigates to real attack target');
  await open(page);await page.locator('[data-action="dungeon-melusina-entrance"]').click();
  await page.locator('.atlas-marker--dungeons.is-selected').waitFor();check(await page.locator('.atlas-target-actions [data-id="dungeons"]').isVisible(),'Map entrance opens adventure');
  for(const [width,height]of[[1280,800],[390,844],[740,360]]){
   await page.setViewportSize({width,height});await page.screenshot({path:path.join(output,`entrance-${width}x${height}.png`)});
   check(await page.locator('.atlas-target-actions [data-id="dungeons"]').isVisible(),'Entrance action remains available on mobile');
  }
  await page.setViewportSize({width:1280,height:800});await page.locator('.atlas-target-actions [data-id="dungeons"]').click();
  await page.locator('.melusina-adventure').waitFor();await layout(page,'quest');
  console.log('PASS source navigation, entrance and quest layouts');
  const crafted=await action(page,'[data-action="dungeon-melusina-craft"]','craft_melusina_key');check(crafted.state.melusina.keys===1&&crafted.state.melusina.fragments===0,'Three fragments craft exactly one key');
  await page.reload();await page.locator('.melusina-adventure').waitFor();check(await page.locator('[data-action="dungeon-melusina-craft"]').isDisabled(),'Reload preserves used fragments');
  await page.locator('.melusina-adventure [data-action="dungeon-create-open"]').click();
  await page.locator('.dungeon-planner [name=role][value=attack]').check();await page.locator('[name=troop_50100101]').fill('20000');
  const created=await action(page,'.dungeon-planner button[type=submit]','create'),id=created.run_id;
  const guestContext=await browser.newContext({viewport:{width:390,height:844}}),guest=await guestContext.newPage();await login(guest,'Dungeon2');await open(guest);
  await guest.locator('[data-action="dungeon-tab"][data-id="parties"]').click();await guest.locator(`[data-action="dungeon-join-open"][data-id="${id}"]`).click();
  await guest.locator('[name=role][value=defense]').check();await guest.locator('[name=troop_50100101]').fill('20000');await action(guest,'.dungeon-planner button[type=submit]','join');
  await page.locator('[data-action="dungeon-reload"]').click();await page.locator(`[data-action="dungeon-start"][data-id="${id}"]`).waitFor();
  const started=await action(page,`[data-action="dungeon-start"][data-id="${id}"]`,'start');check(started.state.melusina.reserved_keys===1&&started.state.melusina.keys===0,'Only leader key reserved');
  console.log('PASS craft, reload, two-player formation and key reservation');
  await page.reload();await page.locator('.dungeon-shell').waitFor();
  const result=JSON.parse(execFileSync('C:/xampp/php/php.exe',['tests/fixtures/melusina_clock.php',fixture,String(id)],{encoding:'utf8'}));check(result.status==='completed','Real simulation wins after fixture clock advancement');
  await page.locator('[data-action="dungeon-reload"]').click();await page.locator('[data-action="dungeon-tab"][data-id="reports"]').click();await page.locator('.melusina-report-story').waitFor();
  check((await page.locator('.melusina-report-story').innerText()).includes('Melusina is free'),'First rescue story appears');
  await page.screenshot({path:path.join(output,'victory.png')});await action(page,`[data-action="dungeon-claim"][data-id="${id}"]`,'claim');
  await page.locator('[data-action="dungeon-tab"][data-id="overview"]').click();await layout(page,'echo');
  check((await page.locator('.melusina-adventure').innerText()).includes('Echoes beneath the Bock'),'First clear persists as echo');
  check((await page.locator('.dungeon-shell img').evaluateAll(images=>images.filter(i=>i.complete&&!i.naturalWidth).map(i=>i.src))).length===0,'No broken dungeon images');
  check(errors.length===0,JSON.stringify(errors));check(failures.length===0,JSON.stringify(failures));
  await guestContext.close();fs.writeFileSync(path.join(output,'verification.json'),JSON.stringify({checks,errors,failures},null,2));console.log(`PASS ${checks} real-app Melusina checks; ${output}`);
 }catch(e){await page.screenshot({path:path.join(output,'failure.png')});console.error(e);console.error({errors,failures});process.exitCode=1;}finally{await browser.close();}
})();
