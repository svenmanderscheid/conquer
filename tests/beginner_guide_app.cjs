'use strict';
// Read-only game checks on the disposable preview; no real account or city is changed.
// php tools/preview-feature-fixture.php --port=18976 --hud --chat
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.BEGINNER_GUIDE_URL||'http://127.0.0.1:18976';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const output=path.resolve(__dirname,'../artifacts/beginner-guide');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try {
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],writes=[];
  page.on('pageerror',e=>{errors.push(e.message);console.error('Browser:',e.message);});page.setDefaultTimeout(20000);
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.locator('#city-frame').waitFor();
  const snapshot=await page.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
  assert(Number(snapshot.buildings.castle.level)>1);
  page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/'))writes.push(r.url());});
  assert.equal(await page.locator('.guide-welcome').count(),0,'Established player is not interrupted');
  await page.locator('#hud-menu').click();await page.locator('[data-action="dialog-tab"][data-id="help"]').click();
  await page.locator('.beginner-guide').waitFor();
  await page.evaluate(()=>document.fonts.ready);
  console.log('Guide open; checking responsive views.');
  const tab=id=>page.locator(`.guide-tabs [data-id="${id}"]`);
  const select=async id=>{await tab(id).click();await page.waitForTimeout(80);};
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});
   for(const section of ['start','buildings','goals','knowledge']){
    await select(section);
    if(section==='knowledge')await page.locator('.guide-reference summary').first().click();
    await page.waitForFunction(()=>[...document.querySelectorAll('.guide-body img')].filter(el=>{const r=el.getBoundingClientRect();return r.top<innerHeight&&r.bottom>0;}).every(el=>el.complete&&el.naturalWidth>0));
    const issues=await page.evaluate(()=>{
     const bad=[],panel=document.querySelector('#panel-dialog'),body=document.querySelector('.guide-body');
     if(body.scrollWidth>body.clientWidth+1)bad.push('Horizontal content overflow');
     for(const el of document.querySelectorAll('.guide-tabs button,.panel-close')){
      const r=el.getBoundingClientRect();if(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1)bad.push('Unreachable navigation: '+el.textContent);
     }
     if(panel.scrollWidth>panel.clientWidth+1)bad.push('Dialog overflow');
     if(body.clientHeight<80)bad.push('Reading area too small');
     return bad;
    });
    assert.deepEqual(issues,[],`${width}x${height} ${section}`);
    await page.screenshot({path:path.join(output,`${section}-${width}x${height}.png`)});
   }
  }
  console.log('Responsive views passed; checking buildings and reading progress.');
  await page.setViewportSize({width:1280,height:800});await select('buildings');
  assert.equal(await page.locator('[data-guide-building]').count(),Object.keys(snapshot.buildings).length,'Every canonical building is explained');
  for(const [code,b] of Object.entries(snapshot.buildings))assert.equal(await page.locator(`[data-guide-level="${code}"]`).innerText(),`Stufe ${b.level}`);
  await page.locator('[data-action="guide-filter"][data-id="army"]').click();assert.equal(await page.locator('[data-guide-building]').count(),5);
  await page.locator('[data-action="guide-filter"][data-id="all"]').click();
  for(const code of Object.keys(snapshot.buildings)){
   await page.locator(`[data-guide-building="${code}"] [data-action="guide-building"]`).click();
   await page.waitForFunction(code=>document.querySelector('#game-dialog').open&&document.querySelector('#game-dialog').dataset.building===code,code);
   await page.locator('.dialog-close').click();
   assert.equal(await page.locator('.beginner-guide').count(),1,'Closing building returns to guide');
  }
  await select('knowledge');await page.locator('.guide-reference summary').first().click();
  await page.locator('.guide-reference summary').nth(4).focus();
  const before=await page.locator('.guide-body').evaluate(e=>e.scrollTop);
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await page.waitForTimeout(1200);
  assert.equal(await page.locator('.guide-reference details').first().getAttribute('open'),'');
  assert.equal(await page.locator('.guide-reference summary').nth(4).evaluate(e=>e===document.activeElement),true);
  assert.equal(await page.locator('.guide-body').evaluate(e=>e.scrollTop),before,'Polling preserves reading position');
  await select('start');await page.locator('[data-action="guide-next"]').click();
  assert.match(await page.locator('#guide-heading').innerText(),/Nachschub/);
  await page.reload();await page.locator('.beginner-guide').waitFor();
  assert.match(await page.locator('#guide-heading').innerText(),/Nachschub/,'Chapter survives reload');
  assert.match(await page.locator('.guide-reading').innerText(),/1 von 6/);
  for(let i=1;i<6;i++)await page.locator('[data-action="guide-next"]').click();
  assert.equal(await tab('goals').getAttribute('aria-pressed'),'true');
  await select('start');assert.match(await page.locator('.guide-reading').innerText(),/6 von 6/);
  await select('goals');
  assert.match(await page.locator('[data-guide-goal="castle"] .guide-goal-status').innerText(),/Erreicht/);
  assert.match(await page.locator('[data-guide-goal="training"] .guide-goal-status').innerText(),/Noch offen/);
  await page.route(base+'/api/game/state*',async route=>{
   const response=await route.fetch(),json=await response.json();json.data.trained_total=20;
   await route.fulfill({response,json});
  });
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  // Training count alone must update goals even when the general render signature is unchanged.
  await page.waitForFunction(()=>document.querySelector('[data-guide-goal="training"]')?.classList.contains('is-complete'));
  await page.unroute(base+'/api/game/state*');
  await page.locator('[data-guide-goal="training"] button').click();await page.waitForFunction(()=>document.querySelector('#panel-dialog').dataset.panel==='army');
  await page.goBack();await page.locator('.beginner-guide').waitFor();
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#panel-dialog').open);
  // Exercise fresh-player onboarding with a read-only starter snapshot.
  await page.route(base+'/api/game/state*',async route=>{
   const response=await route.fetch(),json=await response.json();
   json.data.trained_total=0;json.data.research={};json.data.buildings.castle.level=1;
   await route.fulfill({response,json});
  });
  await page.evaluate(()=>{for(const k of Object.keys(localStorage))if(k.startsWith('conquer:beginner-guide:'))localStorage.removeItem(k);});
  await page.reload();await page.locator('.guide-welcome').waitFor();
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});
   await page.locator('[data-action="guide-open"]').scrollIntoViewIfNeeded();
   const r=await page.locator('[data-action="guide-open"]').boundingBox();
   assert(r&&r.y>=0&&r.y+r.height<=height,'Newcomer action reachable');
   await page.screenshot({path:path.join(output,`welcome-${width}x${height}.png`)});
  }
  await page.locator('.guide-welcome [data-action="close-dialog"]').click();
  await page.reload();await page.locator('#city-frame').waitFor();await page.waitForTimeout(700);
  assert.equal(await page.locator('#game-dialog').evaluate(e=>e.open),false,'Dismissal survives reload');
  await page.locator('#hud-menu').click();await page.locator('[data-action="dialog-tab"][data-id="help"]').click();
  await page.locator('.beginner-guide').waitFor();
  await page.keyboard.press('Escape');await page.reload();await page.locator('#city-frame').waitFor();
  assert.equal(await page.locator('#game-dialog').evaluate(e=>e.open),false,'Reading the guide does not trigger another welcome');
  // Start from the welcome button, with storage unavailable; navigation must still work.
  await page.addInitScript(()=>{Storage.prototype.getItem=()=>{throw new Error('Storage unavailable');};Storage.prototype.setItem=()=>{throw new Error('Storage unavailable');};});
  await page.reload();await page.locator('[data-action="guide-open"]').click();await page.locator('.beginner-guide').waitFor();
  assert.deepEqual(writes,[],'Reading, goal checks and destination previews never perform game actions');
  assert.deepEqual(errors,[],'No browser errors');
  console.log('PASS: all 16 buildings, 6 chapters, real goal snapshots, local resume, newcomer dismissal, polling, destinations, Escape/Back and 20 responsive views. '+output);
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
