'use strict';
// Real report UI on a disposable --monster-reports preview.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base=process.env.MONSTER_REPORT_URL||'http://127.0.0.1:18976';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required');
const output=path.resolve('artifacts/monster-report-review');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true,permissions:['clipboard-read','clipboard-write']});
  await context.addInitScript(()=>{if(location.pathname.endsWith('/city')&&!location.hash)history.replaceState(null,'','#world');});
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.stack||e.message));page.setDefaultTimeout(20000);
  await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL(url=>url.pathname==='/city'),page.locator('#auth-submit').click()]);
  await page.locator('#navigation [data-id="reports"]').click();await page.locator('[data-action="mailbox-tab"][data-id="reports"]').click();
  const open=async()=>{await page.locator('[data-action="mailbox-open"]').first().click();await page.locator('.monster-report').waitFor();};
  const bounds=async selector=>page.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&el.scrollWidth<=el.clientWidth+1;});
  let id;
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await open();id=await page.locator('.monster-report').getAttribute('data-monster-report');
   assert(await page.locator('#game-dialog').evaluate(el=>el.classList.contains('combat-report-dialog')),'Shared player report window layout');
   assert.equal(await page.locator('.cr-identity').count(),2);assert.equal(await page.locator('.cr-fold[open]').count(),3);
   assert.equal(await page.locator('.mr-troop-card').count(),4);assert.equal(await page.locator('.mr-boost-list li').count(),9);
   assert.match(await page.locator('.cr-identity.defender img').getAttribute('src'),/life-orc\.png/);
   await page.waitForFunction(()=>/Rückmarsch/.test(document.querySelector('[data-report-delivery]')?.textContent||''));assert.match(await page.locator('[data-report-delivery]').innerText(),/Rückmarsch/);assert(await bounds('#game-dialog'));assert(await bounds('.cr-scroll'));
   assert(await page.locator('.cr-footer').evaluate(el=>{const r=el.getBoundingClientRect();return [...el.querySelectorAll('button')].every(b=>{const q=b.getBoundingClientRect();return b.contains(document.elementFromPoint(q.x+q.width/2,q.y+q.height/2));})&&r.bottom<=innerHeight;}),'Fixed report actions stay reachable');
   await page.screenshot({path:path.join(output,`${width}x${height}-overview.png`)});
   for(const key of ['equipment','talents','bonuses']){
    const fold=page.locator(`[data-mr-fold="${key}"]`);await fold.locator('.cr-equipment,.mr-mastery-head,.mr-boost-list').first().scrollIntoViewIfNeeded();
    assert(await fold.evaluate(el=>el.open&&el.scrollWidth<=el.clientWidth+1),'Visible report content has no horizontal overflow');
    await page.screenshot({path:path.join(output,`${width}x${height}-${key}.png`)});
   }
   await page.locator('[data-monster="details"]').click();await page.locator('#monster-combat-details[open]').waitFor();
   assert.equal(await page.locator('#monster-combat-details .cr-troop').count(),3);assert(await bounds('#monster-combat-details'));assert(await bounds('#monster-combat-details .cr-detail-scroll'));
   assert.deepEqual(await page.locator('#monster-combat-details .cr-troop dl>div:first-child dd').allTextContents(),['0','0','0'],'Historical troop balances correctly show no deaths');
   await page.screenshot({path:path.join(output,`${width}x${height}-details.png`)});
   await page.goBack();await page.waitForFunction(()=>!document.querySelector('#monster-combat-details').open);assert(await page.locator('#game-dialog').evaluate(el=>el.open));
   await page.locator('[data-monster="copy"]').click();assert.match(await page.evaluate(()=>navigator.clipboard.readText()),/Monster-Kampfbericht[\s\S]*Monster-HP nach \/ vor Kampf/);
   await page.goBack();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
  }
  await page.setViewportSize({width:390,height:844});await open();
  assert.match(await page.locator('.cr-location-hint').innerText(),/Charm einsammeln/);await page.locator('[data-monster="location"]').click();
  await page.waitForFunction(()=>location.hash==='#world'&&ConquerWorld.getCenter()?.x===75&&ConquerWorld.getCenter()?.y===65);
  assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),false,'Monster portrait closes the report and returns to its map coordinates');
  await page.locator('#navigation [data-id="reports"]').click();await page.locator('[data-action="mailbox-tab"][data-id="reports"]').click();await open();
  const shared=[];await page.route('**/api/community/action',async route=>{const payload=route.request().postDataJSON();if(payload.action==='chat.send'){shared.push(payload);await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,data:{message:'Bericht geteilt.'}})});return;}await route.continue();});
  await page.locator('[data-monster="share"]').click();await page.locator('#report-share-dialog[open]').waitFor();await page.locator('[data-share-channel="world"]').click();await page.screenshot({path:path.join(output,'390x844-share.png')});await page.locator('.report-share-form button[type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#report-share-dialog').open);await page.waitForFunction(()=>!history.state?.conquerReportShare);assert.equal(shared[0].channel,'world');assert(Number(shared[0].report_id)>0);assert.match(shared[0].message,/Monster-Kampfbericht/);assert(Array.from(shared[0].message).length<=200);
  for(const channel of ['alliance','private']){await page.locator('[data-monster="share"]').click();await page.locator('#report-share-dialog[open]').waitFor();const target=page.locator(`[data-share-channel="${channel}"]`);if(await target.isEnabled()){await target.click();if(channel==='private')await page.locator('.cr-share-player select').selectOption({index:1});await page.locator('.report-share-form button[type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#report-share-dialog').open);}else await page.locator('[data-share-close]').first().click();await page.waitForFunction(()=>!history.state?.conquerReportShare);}
  if(shared.some(item=>item.channel==='alliance'))assert.match(shared.find(item=>item.channel==='alliance').message,/Monster-Kampfbericht/);if(shared.some(item=>item.channel==='private'))assert.ok(shared.find(item=>item.channel==='private').player_id>0);await page.unroute('**/api/community/action');
  const fold=page.locator('[data-mr-fold="bonuses"]');await fold.locator('.mr-boost-list').scrollIntoViewIfNeeded();
  const scroll=await page.locator('.cr-scroll').evaluate(el=>el.scrollTop);await page.waitForTimeout(5500);
  assert(await fold.evaluate(el=>el.open));assert(Math.abs(await page.locator('.cr-scroll').evaluate(el=>el.scrollTop)-scroll)<2,'Polling retains scroll and expanded comparisons');
  await page.locator('[data-monster="details"]').click();await page.keyboard.press('Escape');await page.waitForFunction(()=>history.state?.monsterDepth===1);
  await page.locator('[aria-label="Älterer Kampfbericht"]').click();assert.match(await page.locator('.monster-report').innerText(),/Gesamte Rally/);
  await page.locator('[aria-label="Älterer Kampfbericht"]').click();assert.match(await page.locator('.cr-result').innerText(),/Niederlage/);assert.equal(await page.locator('.cr-resources>div').count(),0);assert.match(await page.locator('.cr-location-hint').innerText(),/Erneut angreifen/);assert.match(await page.locator('[data-monster="location"]').getAttribute('aria-label'),/erneut angreifen/);
  await page.locator('[aria-label="Älterer Kampfbericht"]').click();assert.equal(await page.locator('.cr-bonuses').count(),0);assert.match(await page.locator('.cr-notice').innerText(),/damals nicht gespeichert/);
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerMonsterReport);
  await page.goto(base+'/reports/'+id);await page.locator('.monster-report').waitFor();assert(await bounds('.cr-scroll'));
  await page.locator('[data-monster="details"]').click();assert.equal(await page.locator('#monster-combat-details .cr-troop').count(),3);await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerMonsterReport);
  await page.screenshot({path:path.join(output,'direct-report.png')});
  const denied=await page.evaluate(async id=>(await fetch('/api/battle/report/'+id+'/delete',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'})).status,id);assert.equal(denied,403);
  await page.locator('.mr-delete summary').click();await page.locator('[data-action="monster-report-delete"]').click();await page.waitForURL('**/city#reports');
  await page.locator('[data-action="mailbox-tab"][data-id="reports"]').click();await page.waitForFunction(()=>document.querySelectorAll('[data-action="mailbox-open"]').length===3);
  const gone=await page.evaluate(async id=>(await fetch('/api/battle/report/'+id)).status,id);assert.equal(gone,404);
  assert.deepEqual(errors,[],'No browser errors');
  console.log('PASS shared player report layout, five responsive formats, fixed actions, details/Back/Escape, copy, live refresh, rally/defeat/legacy, direct link and protected deletion. '+output);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
