'use strict';
// Read-only browser QA against preview-feature-fixture.php --inventory-overview --port=18964.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.INVENTORY_OVERVIEW_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/inventory-overview');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 let page,primaryError=null;
 try {
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});
  const errors=[],writes=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(45000);
  await page.goto(base);await page.goto(new URL('?zugang=login', page.url()).href);
  await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  page.on('request',r=>{if(r.method()==='POST')writes.push(r.url())});
  const kingdom=await page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
  assert.equal(kingdom.profile.display_name,'PreviewPlayer');
  const expectedFood=kingdom.inventory.filter(i=>i.category==='resource_pack'&&i.resource==='food').reduce((n,i)=>n+i.amount*i.quantity,0);
  assert(expectedFood>=5400000000,'Use the populated disposable overview fixture');
  // Wait for the authenticated HUD to finish its initial state load before
  // testing navigation; the fixture API request above does not await the UI.
  await page.locator('#hud-research:not([data-job-state="loading"])').waitFor({state:'attached',timeout:45000});
  assert.equal(await page.locator('html').getAttribute('lang'),'en','The five baseline sizes use the English default');
  await page.locator('#navigation [data-id="inventory"]').tap({timeout:45000});
  const trigger=page.locator('#inventory-overview-button'),dialog=page.locator('#game-dialog');
  async function selectLocale(locale){
   // The game reloads on conquer:locale. The old document's lang changes before
   // that navigation, so it is not sufficient evidence that the app is ready.
   await Promise.all([
    page.waitForEvent('load'),
    page.evaluate(value=>ConquerLocale.setLocale(value),locale)
   ]);
   await page.waitForFunction(value=>document.documentElement.lang===value&&window.ConquerLocale.locale===value,locale);
   await page.locator('#hud-research:not([data-job-state="loading"])').waitFor({state:'attached',timeout:45000});
   assert.equal(await page.locator('#player-hud-name').textContent(),'PreviewPlayer','The reloaded locale document remains authenticated');
   await page.locator('#panel-dialog[data-panel="inventory"][open]').waitFor({state:'visible'});
   await trigger.waitFor({state:'visible'});
  }
  async function close(mode){
   if(mode==='back')await page.evaluate(()=>history.back());
   else if(mode==='escape')await page.keyboard.press('Escape');
   else await page.locator("#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible").first().tap();
   await page.waitForFunction(()=>!document.querySelector('#game-dialog').open&&!history.state?.conquerInventoryOverview);
   assert.equal(await page.locator('#panel-dialog').getAttribute('data-panel'),'inventory');
  }
  async function check(width,height,label){
   await page.locator('.inventory-overview img').evaluateAll(async imgs=>Promise.all(imgs.map(i=>i.decode())));
   const issues=await page.evaluate(()=>{
    const panel=document.querySelector('#game-dialog'),r=panel.getBoundingClientRect(),bad=[];
    if(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1)bad.push('dialog outside viewport');
    if(panel.scrollWidth>panel.clientWidth+1)bad.push('horizontal overflow');
    const heading=panel.querySelector(':scope > .popup-heading'),title=heading?.querySelector('h2');
    const headerActions=[...panel.querySelectorAll(':scope > .dialog-close')];
    if(headerActions.length!==1)bad.push('missing overview close action');
    if(panel.querySelector('[data-action="bug-report-open"]'))bad.push('report button outside the main screen');
    if(!heading||!title?.textContent.trim()||!title.checkVisibility({checkOpacity:true,checkVisibilityCSS:true}))bad.push('missing visible overview title');
    else{
     const head=heading.getBoundingClientRect(),range=document.createRange();range.selectNodeContents(title);
     const lines=[...range.getClientRects()].filter(line=>line.width&&line.height);
     if(title.textContent.trim()!==ConquerLocale.text('Rohstoffe & Beschleuniger'))bad.push('overview title is not in the selected language');
     if(!lines.length||title.scrollWidth>title.clientWidth+1)bad.push('overview title is clipped');
     for(const line of lines){
      if(line.left<head.left-1||line.right>head.right+1||line.top<head.top-1||line.bottom>head.bottom+1)bad.push('overview title outside header');
      for(const action of headerActions){const box=action.getBoundingClientRect();if(line.left<box.right&&line.right>box.left&&line.top<box.bottom&&line.bottom>box.top)bad.push('overview title overlaps '+action.className);}
     }
     for(const action of headerActions){
      const box=action.getBoundingClientRect(),hit=document.elementFromPoint(box.x+box.width/2,box.y+box.height/2);
      if(box.width<44||box.height<44)bad.push('header action below 44px '+action.className);
      if(box.left<head.left-1||box.right>head.right+1||box.top<head.top-1||box.bottom>head.bottom+1)bad.push('header action outside header '+action.className);
      if(!action.checkVisibility({checkOpacity:true,checkVisibilityCSS:true})||!action.contains(hit))bad.push('header action center is blocked '+action.className);
     }
    }
    for(const b of panel.querySelectorAll('button')){const x=b.getBoundingClientRect();if(!x.width||!x.height)continue;if(x.top<r.top||x.bottom>r.bottom||x.left<r.left||x.right>r.right||b.scrollWidth>b.clientWidth+2)bad.push('clipped '+b.textContent);}
    for(const b of panel.querySelectorAll('[data-overview-unit]')){
     if(!b.getClientRects().length)continue;
     const label=b.querySelector('span:not([aria-hidden])'),name=b.dataset.overviewUnit;
     if(!label||!label.textContent.trim()||!label.checkVisibility({checkOpacity:true,checkVisibilityCSS:true})||parseFloat(getComputedStyle(label).fontSize)<12){bad.push('unit label is not readable '+name);continue;}
     const range=document.createRange();range.selectNodeContents(label);
     const text=range.getBoundingClientRect(),button=b.getBoundingClientRect();
     if(!text.width||!text.height||text.left<button.left-1||text.right>button.right+1||text.top<button.top-1||text.bottom>button.bottom+1)bad.push('unit label clips '+name);
    }
    for(const td of panel.querySelectorAll('th,td'))if(td.scrollWidth>td.clientWidth+2)bad.push('cell overflow '+td.textContent);
    return bad;
   });
   await page.screenshot({path:path.join(output,`${width}x${height}-${label}.png`)});
   assert.deepEqual(issues,[],JSON.stringify({width,height,label,issues}));
  }
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(350);
   assert.equal(await page.locator('[data-action="inventory-scope"]').count(),0);
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
  for(const locale of ['de','fr']){
   await selectLocale(locale);
   for(const [width,height] of [[320,568],[568,320]]){
    await page.setViewportSize({width,height});await page.waitForTimeout(350);
    await trigger.tap();assert.equal(await dialog.evaluate(d=>d.open),true);
    await check(width,height,locale+'-resources');
    await page.locator('[data-overview-tab="speedups"]').tap();
    for(const unit of ['days','hours','minutes']){
     await page.locator(`[data-overview-unit="${unit}"]`).tap();
     assert.equal(await page.locator('.inventory-overview-units [aria-pressed="true"]').count(),1);
     await check(width,height,locale+'-speedups-'+unit);
    }
    await close('button');
   }
  }
  await selectLocale('en');
  // Changing read responses simulates another device using an item; no account is mutated.
  await page.setViewportSize({width:390,height:844});await trigger.tap();
  await page.locator('.inventory-overview-scroll').evaluate(el=>el.scrollTop=el.scrollHeight);
  const scroll=await page.locator('.inventory-overview-scroll').evaluate(el=>el.scrollTop);
  await page.route(base+'/api/kingdom/state',async route=>{
   const response=await route.fetch(),json=await response.json();
   json.data.inventory=json.data.inventory.map(i=>i.item_code===10101001?{...i,quantity:i.quantity+1}:i);
   await route.fulfill({response,json});
  });
  // Idle polling runs every 15 seconds; allow its response and repaint after a full interval.
  await page.waitForFunction(value=>document.querySelector('[data-resource="food"] [data-column="items"]').dataset.value===String(value),expectedFood+50000,{timeout:30000});
  assert.equal(await page.locator('.inventory-overview-scroll').evaluate(el=>el.scrollTop),scroll,'Refresh preserves overview scroll');
  await page.unrouteAll({behavior:'wait'});
  await close('button');
  await page.locator('[data-action="inventory-category"][data-id="other"]').tap();await trigger.tap();await close('back');
  await page.locator("#panel-dialog .panel-close:visible, #panel-dialog .mobile-page-back:visible").first().tap();
  await page.locator('#navigation [data-id="quests"]').tap();assert.equal(await trigger.isVisible(),false,'Overview entry is exclusive to inventory');
  assert.deepEqual(writes,[],'The overview must not spend or grant items');assert.deepEqual(errors,[]);
  console.log('PASS inventory overview in the real app: sums, five English viewports, German/French portrait and landscape, complete titles, 44px reachable header actions, three units, live refresh, touch, Escape, Back and zero mutations. '+output);
 }catch(error){
  primaryError=error;
  console.error('Inventory overview failed before cleanup:',error);
  throw error;
 }finally{
  let cleanupError=null;
  // Drain in-flight route.fetch/fulfill calls before disposing their context.
  // Preserve the original assertion failure if cleanup itself also fails.
  try{if(page&&!page.isClosed())await page.unrouteAll({behavior:'wait'});}
  catch(error){cleanupError=error;console.error('Inventory overview route cleanup failed:',error);}
  try{await browser.close();}
  catch(error){cleanupError??=error;console.error('Inventory overview browser cleanup failed:',error);}
  if(!primaryError&&cleanupError)throw cleanupError;
 }
})().catch(e=>{console.error(e);process.exitCode=1});
