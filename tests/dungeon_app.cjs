'use strict';
// Run only against tools/preview-feature-fixture.php --dungeons --port=18946.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.DUNGEON_FIXTURE_URL||'http://127.0.0.1:18946';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview URL required');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-dungeon-app-'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const errors=[],failures=[];
 let checks=0;
 try {
  const page=await browser.newPage({viewport:{width:1280,height:800}});
  page.on('pageerror',e=>errors.push(e.message));
  page.on('response',r=>{if(r.url().startsWith(base+'/api/')&&r.status()>=400)failures.push(r.status()+' '+r.url());});
  await page.goto(base,{waitUntil:'domcontentloaded'});
  await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');
  await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.locator('#hud-menu').waitFor();
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  await page.locator('#navigation [data-action="tab"]').first().waitFor();
  await page.locator('#hud-menu').click();
  await page.locator('[data-action="dialog-tab"][data-id="dungeons"]').click();
  await page.locator('#panel-dialog[data-panel="dungeons"] .dungeon-shell').waitFor();
  await page.waitForFunction(()=>document.querySelector('.dungeon-shell')?.textContent.includes('Gruppe'));
  assert((await page.locator('#page-title').textContent()).includes('Dungeons'));checks++;
  await page.waitForFunction(()=>document.querySelectorAll('.dungeon-card').length===6);
  assert((await page.locator('.dungeon-intro').innerText()).includes('Klassen frei kombinierbar'),'Overview explains that classes are freely combinable');checks++;
  assert.equal((await page.locator('.dungeon-shell').innerText()).includes('Pflicht'),false,'Dungeon UI contains no mandatory-role copy');checks++;
  assert.deepEqual(await page.locator('.dungeon-card').first().locator('.dungeon-role-icon').evaluateAll(es=>es.map(e=>e.title)),['Angreifer · Mehr Schaden','Verteidiger · Mehr Schutz','Sammler · Mehr Beute','Jäger · Kürzere Reise'],'Role icon titles explain every class bonus');checks++;
  assert.equal(await page.locator('.dungeon-grid').first().locator('.dungeon-guidance').count(),3,'Every current dungeon shows troop guidance');checks++;
  assert.equal(await page.locator('.dungeon-grid').first().locator('.dungeon-formation').first().locator('img').count(),3,'Guidance shows the three troop types');checks++;
  await page.locator('.dungeon-preview summary').click();
  const art=page.locator('.dungeon-card img');
  await art.evaluateAll(async images=>{await Promise.all(images.map(image=>{image.loading='eager';return image.decode();}));});
  assert.equal(await page.locator('.dungeon-card-art > img').count(),6,'All six weekly illustrations are present');checks++;
  await page.locator('.dungeon-preview summary').click();
  // Real authenticated create/join/start with two disposable accounts.
  assert.equal(await page.locator('[data-form="dungeon-create"]').count(),0,'Overview does not embed create forms');checks++;
  await page.locator('[data-action="dungeon-create-open"]').first().click();
  assert.equal(await page.locator('.dungeon-planner').count(),1,'Create opens one planner');checks++;
  const create=page.locator('.dungeon-planner [data-form="dungeon-create"]');
  assert(await create.locator('button[type="submit"]').isDisabled(),'Create initially disabled');checks++;
  assert.equal((await create.innerText()).includes('Gesucht'),false,'Create planner does not imply required classes');checks++;
  assert.deepEqual(await create.locator('.dungeon-role-option:not(.is-locked) small').allInnerTexts(),['Mehr Schaden'],'The eligible create role tile shows its class bonus');assert((await create.locator('.dungeon-role-option.is-locked small').allInnerTexts()).every(text=>text==='Talent fehlt'),'Locked create roles explain their talent requirement');checks+=2;
  await create.locator('[name="role"][value="attack"]').check();
  await create.locator('[name="difficulty"][value="normal"]').check();
  const createPreview=page.waitForResponse(r=>r.url().endsWith('/api/dungeons/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action==='preview');
  await create.locator('[name="troop_50100101"]').fill('200');
  await createPreview;
  assert.equal(await create.locator('[data-dungeon-guidance]').count(),1,'Create planner shows guidance');assert.equal(await create.locator('.dungeon-formation img').count(),3,'Create planner shows its troop mix');assert(await create.locator('[data-dungeon-forecast]').innerText(),'Create planner shows the authoritative forecast');checks+=3;
  for(const [width,height] of [[1280,800],[390,844],[320,568],[740,360]]) {
   await page.setViewportSize({width,height});await create.locator('button[type="submit"]').scrollIntoViewIfNeeded();
   const layout=await page.evaluate(()=>{const dialog=document.querySelector('#panel-dialog'),planner=dialog.querySelector('.dungeon-planner'),submit=planner.querySelector('button[type="submit"]'),r=dialog.getBoundingClientRect(),p=planner.getBoundingClientRect(),s=submit.getBoundingClientRect();return {frame:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,planner:p.left>=r.left-2&&p.right<=r.right+2,submit:s.left>=r.left-2&&s.right<=r.right+2&&s.top>=r.top-2&&s.bottom<=r.bottom+2,overflow:planner.scrollWidth>planner.clientWidth+2};});
   await page.screenshot({path:path.join(output,`dungeons-create-${width}x${height}.png`)});assert(layout.frame&&layout.planner&&!layout.overflow,`Create planner fits ${width}x${height}: ${JSON.stringify(layout)}; screenshots ${output}`);assert(layout.submit,`Create submit visible inside frame ${width}x${height}`);checks+=2;
  }
  await page.setViewportSize({width:1280,height:800});await create.locator('button[type="submit"]').scrollIntoViewIfNeeded();
  const createdResponse=page.waitForResponse(r=>r.url().endsWith('/api/dungeons/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action!=='preview');
  await create.locator('button[type="submit"]').click();
  const created=await(await createdResponse).json();
  assert(created.ok,JSON.stringify(created.error));const runId=created.data.run_id;assert(runId>0);checks++;
  const guestContext=await browser.newContext(),guest=await guestContext.newPage();
  guest.on('pageerror',e=>errors.push(e.message));
  await guest.goto(base,{waitUntil:'domcontentloaded'});
  await guest.locator('[data-mode="login"]').click();
  await guest.locator('[name="username"]').fill('Dungeon3');
  await guest.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([guest.waitForURL('**/city'),guest.locator('#auth-submit').click()]);
  await guest.goto(base+'/city#dungeons',{waitUntil:'domcontentloaded'});
  await guest.locator('[data-action="dungeon-tab"][data-id="parties"]').click();
  assert.equal(await guest.locator('[data-form="dungeon-join"]').count(),0,'Party browser does not embed join forms');checks++;
  await guest.locator(`[data-action="dungeon-join-open"][data-id="${runId}"]`).click();
  assert.equal(await guest.locator('.dungeon-planner').count(),1,'Join opens one planner');checks++;
  const join=guest.locator('.dungeon-planner [data-form="dungeon-join"]');
  assert.equal((await join.innerText()).includes('Gesucht'),false,'Join planner does not imply required classes');checks++;
  assert.deepEqual(await join.locator('.dungeon-role-option:not(.is-locked) small').allInnerTexts(),['Mehr Beute'],'The eligible join role tile shows its class bonus');assert((await join.locator('.dungeon-role-option.is-locked small').allInnerTexts()).every(text=>text==='Talent fehlt'),'Locked join roles explain their talent requirement');checks+=2;
  await join.locator('[name="role"][value="gather"]').check();
  const joinPreview=guest.waitForResponse(r=>r.url().endsWith('/api/dungeons/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action==='preview');
  await join.locator('[name="troop_50100101"]').fill('200');
  const joinForecast=await(await joinPreview).json();
  assert(['ready','risky'].includes(joinForecast.data.forecast.status),`Two-member forecast must not require roles: ${JSON.stringify(joinForecast.data.forecast)}`);checks++;
  assert.equal(await join.locator('[data-dungeon-guidance]').count(),1,'Join planner shows guidance');assert.equal(await join.locator('.dungeon-formation img').count(),3,'Join planner shows its troop mix');assert(await join.locator('[data-dungeon-forecast]').innerText(),'Join planner shows the authoritative forecast');checks+=3;
  for(const [width,height] of [[1280,800],[390,844],[320,568],[740,360]]) {
   await guest.setViewportSize({width,height});await join.locator('button[type="submit"]').scrollIntoViewIfNeeded();
   const layout=await guest.evaluate(()=>{const dialog=document.querySelector('#panel-dialog'),planner=dialog.querySelector('.dungeon-planner'),submit=planner.querySelector('button[type="submit"]'),r=dialog.getBoundingClientRect(),p=planner.getBoundingClientRect(),s=submit.getBoundingClientRect();return {frame:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,planner:p.left>=r.left-2&&p.right<=r.right+2,submit:s.left>=r.left-2&&s.right<=r.right+2&&s.top>=r.top-2&&s.bottom<=r.bottom+2,overflow:planner.scrollWidth>planner.clientWidth+2};});
   await guest.screenshot({path:path.join(output,`dungeons-join-${width}x${height}.png`)});assert(layout.frame&&layout.planner&&!layout.overflow,`Join planner fits ${width}x${height}: ${JSON.stringify(layout)}; screenshots ${output}`);assert(layout.submit,`Join submit visible inside frame ${width}x${height}`);checks+=2;
  }
  await guest.setViewportSize({width:1280,height:800});await join.locator('button[type="submit"]').scrollIntoViewIfNeeded();
  const joinedResponse=guest.waitForResponse(r=>r.url().endsWith('/api/dungeons/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action!=='preview');
  await join.locator('button[type="submit"]').click();
  const joined=await(await joinedResponse).json();assert(joined.ok,JSON.stringify(joined.error));checks++;
  await page.locator('[data-action="dungeon-tab"][data-id="parties"]').click();
  await page.locator('[data-action="dungeon-reload"]').click();
  const start=page.locator(`[data-action="dungeon-start"][data-id="${runId}"]`);
  await start.waitFor();
  const party=page.locator(`[data-party="${runId}"]`);await party.waitFor();
  assert((await party.locator('.dungeon-group-status').innerText()).includes('2/4 Klassenboni'),'Two members can start with any class combination');checks++;
  assert.equal(await party.locator('.dungeon-member.is-empty small').first().innerText(),'Jede Klasse','Open slots accept every class');checks++;
  const startedResponse=page.waitForResponse(r=>r.url().endsWith('/api/dungeons/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action!=='preview');
  await start.click();const started=await(await startedResponse).json();assert(started.ok,JSON.stringify(started.error));checks++;
  await page.locator('#toast.visible').waitFor({state:'hidden'});
  await guestContext.close();
  await page.locator('[data-action="dungeon-tab"][data-id="overview"]').click();
  for(const [width,height] of [[1280,800],[390,844],[320,568],[740,360]]) {
   await page.setViewportSize({width,height});
   await page.locator('#panel-dialog .panel-close').waitFor({state:'visible'});
   for(const tab of ['overview','parties','reports']) {
   await page.locator(`[data-action="dungeon-tab"][data-id="${tab}"]`).click();
   await page.waitForFunction(()=>{const el=document.querySelector('.dungeon-tabs .active');return el&&getComputedStyle(el).backgroundColor==='rgb(42, 114, 201)';});checks++;
   await page.locator('.dungeon-shell img').evaluateAll(async images=>{await Promise.all(images.map(image=>{image.loading='eager';return image.decode();}));});
   const layout=await page.evaluate(()=>{
    const dialog=document.querySelector('#panel-dialog'),r=dialog.getBoundingClientRect();
    return {visible:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,
     overflow:[...dialog.querySelectorAll('.dungeon-shell,input,select,button')].filter(e=>{const b=e.getBoundingClientRect();return b.width&&b.height&&(b.left<r.left-2||b.right>r.right+2);}).map(e=>e.className),
     backgrounds:getComputedStyle(document.querySelector('.dungeon-shell')).color};
   });
   await page.screenshot({path:path.join(output,`dungeons-${tab}-${width}x${height}.png`)});
   assert(layout.visible,`${width}: frame inside viewport`);assert.deepEqual(layout.overflow,[],`${width}: controls stay in frame: ${JSON.stringify(layout.overflow)}; screenshots ${output}`);checks+=2;
   }
  }
  await page.locator('#panel-dialog .panel-close').click();
  assert(await page.locator('#panel-dialog').evaluate(el=>!el.open));checks++;
  const frame=page.frameLocator('#city-frame');
  await frame.locator('canvas').waitFor();
  assert.equal(await frame.locator('body').evaluate(el=>el.classList.contains('embedded')),true,'Embedded city retains its app integration');checks++;
  for(const tab of ['profile','quests','army','research','inventory','treasures','mastery','market','community','defense','events','expeditions','rankings','arena','worlds','settings','account','help']) {
   await page.evaluate(tab=>{location.hash=tab;},tab);
   await page.locator(`#panel-dialog[data-panel="${tab}"]`).waitFor({state:'visible'});
   assert(await page.locator('#content').evaluate(el=>el.textContent.trim().length>0),`${tab} remains available`);checks++;
   await page.locator('#panel-dialog .panel-close').click();
  }
  await page.locator('#hud-menu').click();
  assert(await page.locator('[data-action="dialog-tab"][data-id="dungeons"]').isVisible(),'Main menu still exposes Dungeons');checks++;
  await page.locator('#game-dialog .dialog-close').click();
  assert(await frame.locator('.realm-hud').evaluate(el=>getComputedStyle(el).display==='none'),'Embedded 3D HUD is hidden');checks++;
  assert.deepEqual(errors,[],'No browser exceptions');assert.deepEqual(failures,[],'No failed game APIs');checks+=2;
  console.log(`${checks} actual-app checks passed; screenshots ${output}`);
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
