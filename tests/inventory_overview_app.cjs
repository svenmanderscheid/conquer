'use strict';
// Read-only browser QA against preview-feature-fixture.php --inventory-overview --port=18964.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.INVENTORY_OVERVIEW_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/inventory-overview');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try {
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],writes=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(15000);
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  page.on('request',r=>{if(r.method()==='POST')writes.push(r.url())});
  const kingdom=await page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
  assert.equal(kingdom.profile.display_name,'PreviewPlayer');
  const expectedFood=kingdom.inventory.filter(i=>i.category==='resource_pack'&&i.resource==='food').reduce((n,i)=>n+i.amount*i.quantity,0);
  assert(expectedFood>=5400000000,'Use the populated disposable overview fixture');
  await page.locator('#navigation [data-id="inventory"]').tap();
  const trigger=page.locator('#inventory-overview-button'),dialog=page.locator('#game-dialog');
  async function close(mode){
   if(mode==='back')await page.evaluate(()=>history.back());
   else if(mode==='escape')await page.keyboard.press('Escape');
   else await page.locator('#game-dialog>.dialog-close').tap();
   await page.waitForFunction(()=>!document.querySelector('#game-dialog').open&&!history.state?.conquerInventoryOverview);
   assert.equal(await page.locator('#panel-dialog').getAttribute('data-panel'),'inventory');
  }
  async function check(width,height,label){
   await page.locator('.inventory-overview img').evaluateAll(async imgs=>Promise.all(imgs.map(i=>i.decode())));
   const issues=await page.evaluate(()=>{
    const panel=document.querySelector('#game-dialog'),r=panel.getBoundingClientRect(),bad=[];
    if(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1)bad.push('dialog outside viewport');
    if(panel.scrollWidth>panel.clientWidth+1)bad.push('horizontal overflow');
    for(const b of panel.querySelectorAll('button')){const x=b.getBoundingClientRect();if(!x.width||!x.height)continue;if(x.top<r.top||x.bottom>r.bottom||x.left<r.left||x.right>r.right||b.scrollWidth>b.clientWidth+2)bad.push('clipped '+b.textContent);}
    for(const td of panel.querySelectorAll('th,td'))if(td.scrollWidth>td.clientWidth+2)bad.push('cell overflow '+td.textContent);
    return bad;
   });
   await page.screenshot({path:path.join(output,`${width}x${height}-${label}.png`)});
   assert.deepEqual(issues,[],JSON.stringify({width,height,label,issues}));
  }
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(350);
   await page.locator('[data-action="inventory-scope"][data-id="all"]').tap();
   const board=page.locator('.inventory-scroll-board');await board.evaluate(b=>b.scrollTop=b.scrollHeight);
   const original=await board.evaluate(b=>b.scrollTop);
   await trigger.tap();assert.equal(await dialog.evaluate(d=>d.open),true);
   assert.equal(await page.locator('[data-resource="food"] [data-column="items"]').getAttribute('data-value'),String(expectedFood));
   assert.equal(await page.locator('[data-resource="gems"] [data-column="stock"]').getAttribute('data-value'),String(kingdom.profile.gems));
   await check(width,height,'resources');
   await page.locator('[data-overview-tab="speedups"]').tap();
   assert.equal(await page.locator('[data-speedup]').count(),5);
   for(const unit of ['days','hours','minutes']){
    await page.locator(`[data-overview-unit="${unit}"]`).tap();
    assert.equal(await page.locator('.inventory-overview-units [aria-pressed="true"]').count(),1);
    await check(width,height,'speedups-'+unit);
   }
   await close(width===320?'back':width===390?'escape':'button');
   assert.equal(await board.evaluate(b=>b.scrollTop),original,'Closing the overview preserves the inventory position');
  }
  // Changing read responses simulates another device using an item; no account is mutated.
  await page.setViewportSize({width:390,height:844});await trigger.tap();
  await page.locator('.inventory-overview-scroll').evaluate(el=>el.scrollTop=el.scrollHeight);
  const scroll=await page.locator('.inventory-overview-scroll').evaluate(el=>el.scrollTop);
  await page.route(base+'/api/kingdom/state',async route=>{
   const response=await route.fetch(),json=await response.json();
   json.data.inventory=json.data.inventory.map(i=>i.item_code===10101001?{...i,quantity:i.quantity+1}:i);
   await route.fulfill({response,json});
  });
  await page.waitForFunction(value=>document.querySelector('[data-resource="food"] [data-column="items"]').dataset.value===String(value),expectedFood+50000);
  assert.equal(await page.locator('.inventory-overview-scroll').evaluate(el=>el.scrollTop),scroll,'Refresh preserves overview scroll');
  await page.unroute(base+'/api/kingdom/state');
  await close('button');
  await page.locator('[data-action="inventory-category"][data-id="other"]').tap();await trigger.tap();await close('back');
  await page.locator('#panel-dialog .panel-close').tap();
  await page.locator('#navigation [data-id="quests"]').tap();assert.equal(await trigger.isVisible(),false,'Overview entry is exclusive to inventory');
  assert.deepEqual(writes,[],'The overview must not spend or grant items');assert.deepEqual(errors,[]);
  console.log('PASS inventory overview in the real app: sums, five viewports, three units, live refresh, touch, Escape, Back and zero mutations. '+output);
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
