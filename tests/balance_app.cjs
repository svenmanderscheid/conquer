'use strict';
// Full PHP app, disposable preview database, painted city and touch layouts.
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
  await page.context().addCookies([{name:'conquer_locale',value:'de',url:base}]);
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  await page.goto(base+'/city#city');
  await page.locator('.painted-village-building[data-id=watch_tower] .painted-building-label').filter({hasText:'29'}).waitFor({state:'attached'});
  const snapshot=await page.evaluate(async()=> (await(await fetch('/api/game/state')).json()).data);
  assert.deepEqual(snapshot.buildings.academy.cost,JSON.parse(fs.readFileSync(path.resolve(__dirname,'../data/buildings.json'),'utf8')).buildings.academy['30'].resources);
  assert.deepEqual(snapshot.buildings.academy.requirements,{castle:30,gold_mine:30});
  assert(snapshot.buildings.academy.item_requirements.every(i=>i.item_code!==119000001));
  assert.equal(snapshot.buildings.hall_of_alliance.item_requirements.find(i=>i.item_code===119000002).met,false);
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]) {
   await page.setViewportSize({width,height});
   for(const code of ['academy','hall_of_alliance','watch_tower']) {
    const building=page.locator(`.painted-village-building[data-id="${code}"]`);
    await building.evaluate(el=>el.scrollIntoView({block:'center',inline:'center'}));
    const position=await building.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.7,.3,.9,.1])for(const fx of [.5,.7,.3,.9,.1]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
    assert(position,'Building reachable by touch: '+code);await building.click({position});
    await page.locator(`.painted-building-menu [data-action="building"][data-id="${code}"]`).click();
    const button=page.locator(`#dialog-content [data-action="upgrade"][data-id="${code}"]`);
    assert.equal(await button.isDisabled(),code==='hall_of_alliance');
    assert.doesNotMatch(await page.locator('#dialog-content').innerText(),/Goldene Säule/);
    const shownTime=await page.locator('#game-dialog .levelup-time strong').innerText();
    assert.equal(shownTime,await page.evaluate(seconds=>window.ConquerLocale.formatDuration(seconds),snapshot.buildings[code].seconds),`Displayed bonus-adjusted duration: ${code}`);
    const displayedCosts=await page.locator('#game-dialog .levelup-resource-row').evaluateAll(rows=>Object.fromEntries(rows.map(row=>[row.querySelector('img').getAttribute('src').split('/').pop().replace('.png',''),Number(row.querySelector('.levelup-resource-values').textContent.split('/')[1].replace(/\D/g,''))])));
    assert.deepEqual(displayedCosts,Object.fromEntries(Object.entries(snapshot.buildings[code].cost).filter(([,amount])=>amount>0)),`Displayed resource prices: ${code}`);
    if(code==='hall_of_alliance')assert.match(await page.locator('#dialog-content').innerText(),/4\.999 \/ 5\.000/);
    const layout=await page.locator('#game-dialog').evaluate(d=>{const r=d.getBoundingClientRect();return {inside:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:d.scrollWidth>d.clientWidth+2};});
    assert.deepEqual(layout,{inside:true,overflow:false},`${code} ${width}x${height}`);
    await button.scrollIntoViewIfNeeded();const rect=await button.boundingBox();assert(rect.y>=0&&rect.y+rect.height<=height+1);
    await page.screenshot({path:path.join(out,`${code}-${width}x${height}.png`)});
    await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();
   }
  }
  await page.setViewportSize({width:1280,height:800});await page.locator('.painted-village').waitFor();assert.equal(await page.locator('#city-frame').count(),0);await page.screenshot({path:path.join(out,'city-overview.png')});
  assert.deepEqual(errors,[]);
  console.log('PASS reduced catalogue costs, item ownership, missing-material gating and watchtower in real painted app at five touch viewports; no JS errors.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
