'use strict';
// Real writes only against a disposable --monster-reports preview database.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base=process.env.REWARD_FIXTURE_URL||'http://127.0.0.1:18991';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const out=path.resolve('artifacts/reward-dialog-review');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
  await page.addInitScript(()=>{if(location.pathname==='/city'&&!location.hash)history.replaceState(null,'','#world');});
  await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL(u=>u.pathname==='/city'),page.locator('#auth-submit').click()]);
  await page.locator('#navigation [data-id="inventory"]').click();await page.locator('[data-action="inventory-category"][data-id="other"]').click();
  const state=await page.evaluate(async()=>(await(await fetch('/api/kingdom/state')).json()).data),chest=state.inventory.find(i=>i.category==='chest'&&i.quantity>0);assert(chest,'Fresh fixture must include a welcome chest');
  let originalBody,originalResult,blocked=0;
  await page.route('**/api/kingdom/action',async route=>{const body=route.request().postDataJSON();if(body.action==='inventory.use'&&body.item_code===chest.item_code&&!blocked++){originalBody=body;const response=await route.fetch();originalResult=(await response.json()).data;assert(originalResult?.result?.drops?.length);await route.abort('failed');}else await route.continue();});
  await page.locator(`[data-action="inventory-item"][data-id="${chest.item_code}"]`).click();await page.locator('[data-form="item-use"] button[type="submit"]').click();await page.locator('.reward-recovery').waitFor();
  assert(originalBody.operation_key,'Persistent receipt included before submission');
  await page.reload();await page.locator('.reward-recovery').waitFor();
  const responsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.operation_key===originalBody.operation_key);
  await page.locator('[data-action="reward-retry"]').click();const replay=(await(await responsePromise).json()).data;await page.locator('.reward-result').waitFor();
  assert.deepEqual(replay.result,originalResult.result,'Lost response replays the exact server receipt');
  const after=await page.evaluate(async()=>(await(await fetch('/api/kingdom/state')).json()).data);
  const chestDrop=originalResult.result.drops.filter(d=>d.item_code===chest.item_code).reduce((n,d)=>n+d.quantity,0);
  assert.equal(after.inventory.find(i=>i.item_code===chest.item_code)?.quantity||0,chest.quantity-1+chestDrop,'Only one chest consumed');
  assert.equal(await page.locator('.reward-item').count(),originalResult.result.drops.length);
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.locator('.reward-list').evaluate(async el=>{await Promise.all([...el.querySelectorAll('img')].map(i=>i.decode()));});
   const metrics=await page.locator('#game-dialog').evaluate(el=>{const r=el.getBoundingClientRect(),b=el.querySelector('.reward-result footer button'),q=b.getBoundingClientRect();return {fits:r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:el.scrollWidth>el.clientWidth+1,buttonReachable:q.y>=0&&q.bottom<=innerHeight&&b.contains(document.elementFromPoint(q.x+q.width/2,q.y+q.height/2))};});
   assert.deepEqual(metrics,{fits:true,overflow:false,buttonReachable:true},`${width}x${height}`);
   for(const selector of ['#dialog-title','.dialog-close'])assert(await page.locator('#game-dialog '+selector).evaluate(el=>{const r=el.getBoundingClientRect();return r.y>=0&&r.bottom<=innerHeight&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),`${selector} visible and uncovered at ${width}x${height}`);
   if(height>600)assert((await page.locator('#game-dialog').boundingBox()).height<height*.8,'Small reward result fits its content');
   await page.screenshot({path:path.join(out,`${width}x${height}-rewards.png`)});
  }
  await page.goBack();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);assert.equal(new URL(page.url()).hash,'#inventory','Back keeps inventory open');
  await page.setViewportSize({width:390,height:844});await page.locator('[data-action="inventory-category"][data-id="other"]').click();await page.locator('.inventory-scope-bar [data-action="inventory-scope"][data-id="all"]').click();
  const material=after.inventory_catalog.find(i=>i.usage_context==='building');assert(material);await page.locator(`[data-action="inventory-item"][data-id="${material.item_code}"]`).click();assert.match(await page.locator('.inventory-dialog').innerText(),/automatisch/i);assert(await page.locator('.inventory-dialog button[type="submit"]').isDisabled());await page.keyboard.press('Escape');
  await page.goto(base+'/city#treasures');await page.locator('[data-action="treasury-main-tab"][data-id="chests"]').click();
  const dailyResponse=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='chest.free');await page.locator('[data-action="treasury-chest-open"][data-id="gold"]').click();const daily=(await(await dailyResponse).json()).data;await page.locator('.reward-result').waitFor();assert.equal(await page.locator('.reward-item').count(),daily.result.drops.length);await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerRewards);
  await page.goto(base+'/city#reports');await page.locator('[data-action="mailbox-tab"][data-id="reports"]').click();await page.locator('[data-action="mailbox-open"]').first().click();await page.locator('.monster-report').waitFor();
  const icons=await page.locator('.mr-loot .cr-resources img').evaluateAll(async imgs=>{await Promise.all(imgs.map(i=>i.decode()));return imgs.map(i=>i.getAttribute('src'));});
  const expected=after.inventory_catalog.find(i=>i.item_code===10103001);assert(icons.some(src=>src.includes('/'+expected.icon)),'Historical monster loot uses the real catalog icon');
  const mapping=await page.evaluate(async()=>{const k=(await(await fetch('/api/kingdom/state')).json()).data;return k.inventory_catalog.filter(i=>i.category==='chest'||i.category==='speedup').map(i=>({expected:i.icon,actual:ConquerRewards.resolve({item_code:i.item_code,count:2,label:'Gold'},k,'').icon}));});
  assert(mapping.every(r=>r.actual.includes('/'+r.expected)),'Item codes beat ambiguous drop labels for chests and speedups');
  assert.deepEqual(errors,[]);console.log('PASS reward receipts: real inventory/daily chests, lost response + reload exact replay, five viewports, icons, Back/Escape and material guidance. '+out);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
