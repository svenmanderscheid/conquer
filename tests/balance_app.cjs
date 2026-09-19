'use strict';
// Full PHP app, disposable preview database, real 3D scene and touch layouts.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const base=process.env.BALANCE_FIXTURE_URL||'http://127.0.0.1:18974';
assert.match(base,/^http:\/\/127\.0\.0\.1:\d+$/);
const out=path.resolve(__dirname,'../artifacts/balance-import');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try {
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});page.setDefaultTimeout(20000);
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  const frame=page.frameLocator('#city-frame');
  await frame.locator('#label-watch_tower .building-level').filter({hasText:'29'}).waitFor();
  const snapshot=await page.evaluate(async()=> (await(await fetch('/api/game/state')).json()).data);
  assert.equal(snapshot.buildings.academy.cost.gold,113894170);
  assert.deepEqual(snapshot.buildings.academy.requirements,{castle:30,gold_mine:30});
  assert.equal(snapshot.buildings.hall_of_alliance.item_requirements.find(i=>i.item_code===119000002).met,false);
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]) {
   await page.setViewportSize({width,height});
   for(const code of ['academy','hall_of_alliance','watch_tower']) {
    await page.locator('#hud-build').click();await page.locator(`#dialog-content [data-action="building"][data-id="${code}"]`).click();
    const button=page.locator(`#dialog-content [data-action="upgrade"][data-id="${code}"]`);
    assert.equal(await button.isDisabled(),code==='hall_of_alliance');
    assert.match(await page.locator('#dialog-content').innerText(),/Goldene Säule/);
    if(code==='hall_of_alliance')assert.match(await page.locator('#dialog-content').innerText(),/4\.999 \/ 5\.000/);
    const layout=await page.locator('#game-dialog').evaluate(d=>{const r=d.getBoundingClientRect();return {inside:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:d.scrollWidth>d.clientWidth+2};});
    assert.deepEqual(layout,{inside:true,overflow:false},`${code} ${width}x${height}`);
    await button.scrollIntoViewIfNeeded();const rect=await button.boundingBox();assert(rect.y>=0&&rect.y+rect.height<=height+1);
    await page.screenshot({path:path.join(out,`${code}-${width}x${height}.png`)});
    await page.locator('#game-dialog .dialog-close').click();
   }
  }
  await page.setViewportSize({width:1280,height:800});
  await frame.locator('canvas').first().waitFor();
  assert.equal(await frame.locator('#label-watch_tower .building-level').innerText(),'Lv. 29');
  for(const selector of ['#city-hud','#resource-bar','#bottom-nav'])if(await frame.locator(selector).count())assert.equal(await frame.locator(selector).isVisible(),false,'embedded HUD hidden');
  await page.screenshot({path:path.join(out,'city-overview.png')});
  // Use the scene's own focus event to inspect the existing watchtower from nearby.
  await frame.locator('body').evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'watch_tower'}})));
  await page.screenshot({path:path.join(out,'watchtower-close.png')});
  await page.goto(base+'/city/3d?embed=1');await page.locator('#label-watch_tower .building-level').filter({hasText:'29'}).waitFor();
  await page.locator('#label-watch_tower').click();await page.locator('#building-upgrade').click();
  await page.locator('#upgrade-dialog').waitFor();assert.match(await page.locator('#upgrade-costs').innerText(),/Goldene Säule/);
  await page.waitForFunction(()=>!document.querySelector('#start-upgrade').disabled);
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]) {
   await page.setViewportSize({width,height});
   const button=page.locator('#start-upgrade');
   await button.scrollIntoViewIfNeeded();
   // A button inside a scrolling section must also receive a real touch/click.
   await button.click({trial:true});
   const box=await button.boundingBox();assert(box.x>=0&&box.y>=0&&box.x+box.width<=width+1&&box.y+box.height<=height+1,`standalone upgrade reachable ${width}x${height}`);
   await page.screenshot({path:path.join(out,`watchtower-standalone-${width}x${height}.png`)});
  }
  assert.deepEqual(errors,[]);
  console.log('PASS source costs, item ownership, missing-material gating and watchtower in real app/3D at five touch viewports; no JS errors.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
