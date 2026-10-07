'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Real app QA on the disposable tools/preview-feature-fixture.php account only.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const net=require('node:net'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
let base=process.env.INVENTORY_FIXTURE_URL;
const out=path.resolve(__dirname,'../artifacts/inventory-reference');fs.mkdirSync(out,{recursive:true});
(async()=>{
 let fixture,browser;
 try{
  if(!base){
   const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
   fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.resolve(__dirname,'../tools/preview-feature-fixture.php'),'--port='+port,'--inventory-overview','--appearance'],{cwd:path.resolve(__dirname,'..'),stdio:['pipe','pipe','pipe'],windowsHide:true});
   await new Promise((resolve,reject)=>{let log='';fixture.stdout.on('data',data=>{log+=data;if(log.includes('Synthetic preview ready'))resolve();});fixture.stderr.on('data',data=>log+=data);fixture.on('error',reject);fixture.on('exit',()=>reject(Error(log)));});
   base='http://127.0.0.1:'+port;
  }
  assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'An isolated local fixture is required');
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(45000);
  await page.context().addCookies([{name:'conquer_locale',value:'de',url:base}]);
  await require('./fixtures/inventory_access.cjs')(page);
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});
  await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  const state=await page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
  assert.equal(state.profile.display_name,'PreviewPlayer');
  const retired=new Set([10102021,10102031,10202010,10202011]);
  assert(!state.inventory_catalog.some(item=>retired.has(Number(item.code??item.item_code))));
  assert(!state.inventory.some(item=>retired.has(Number(item.item_code))));
  await page.locator('#navigation [data-id="inventory"]').click();
  assert.equal(await page.locator('[data-action="inventory-scope"],.inventory-scope-bar').count(),0,'Inventory shows owned items without a catalogue filter');
  assert.deepEqual(await page.locator('.inventory-category-tabs button').evaluateAll(bs=>bs.map(b=>b.dataset.id)),['resource_pack','speedup','boost','other']);
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(450);
   for(const category of ['resource_pack','speedup','boost','other']){
    await page.locator(`[data-action="inventory-category"][data-id="${category}"]`).click();
    assert.equal(await page.locator('#panel-dialog').getAttribute('data-panel'),'inventory','Item categories stay in the backpack');
    const board=page.locator('.inventory-scroll-board');
    if(!await board.count()){
     assert.equal(await page.locator('.inventory-empty').count(),1,'An unowned category shows the honest empty state');
     assert.equal(await page.locator('[data-action="inventory-scope"],[data-action="inventory-item"]').count(),0,'An empty category never opens the catalogue');
     const bounds=await page.locator('#panel-dialog').boundingBox();assert(bounds.x>=0&&bounds.x+bounds.width<=width+1&&bounds.y>=0&&bounds.y+bounds.height<=height+1,'Empty inventory stays inside the viewport');
     await page.screenshot({path:path.join(out,`${width}x${height}-${category}.png`)});continue;
    }
    await board.waitFor();
    await page.locator('.inventory-page-grid').evaluate(async el=>{await Promise.all([...el.querySelectorAll('img')].map(async i=>{i.loading='eager';await i.decode()}))});
    const metrics=await page.evaluate(()=>{
     const grid=document.querySelector('.inventory-page-grid'),board=document.querySelector('.inventory-scroll-board'),panel=document.querySelector('#panel-dialog'),r=panel.getBoundingClientRect();
     const bad=[...document.querySelectorAll('.inventory-category-tabs button,.panel-close')].filter(b=>{const x=b.getBoundingClientRect(),scrollTab=b.closest('.inventory-category-tabs'),mustBeVisible=!scrollTab||b.getAttribute('aria-pressed')==='true';return (mustBeVisible&&(x.left<0||x.right>innerWidth||x.top<0||x.bottom>innerHeight))||b.scrollWidth>b.clientWidth+2}).map(b=>b.textContent);
     const clipped=[...grid.querySelectorAll('.loot-count,.loot-value')].filter(el=>el.scrollWidth>el.clientWidth+1).map(el=>el.textContent);
     const stockProblems=[...grid.querySelectorAll('.item-icon')].filter(tile=>{const stock=tile.querySelector('.loot-count'),value=tile.querySelector('.loot-value'),s=stock.getBoundingClientRect(),v=value.getBoundingClientRect();return !stock.textContent.startsWith('×')||parseFloat(getComputedStyle(stock).fontSize)<11||s.top>=tile.getBoundingClientRect().y+tile.clientHeight/2||(value.textContent&&s.bottom>=v.top);}).map(tile=>tile.textContent);
     return {shell:document.querySelector('.inventory-shell').className,grid:grid.outerHTML.slice(0,250),columns:getComputedStyle(grid).gridTemplateColumns.split(' ').length,overflow:board.scrollWidth>board.clientWidth+2||panel.scrollWidth>panel.clientWidth+2,outside:r.left<0||r.right>innerWidth+1||r.top<0||r.bottom>innerHeight+1,bad,clipped,stockProblems};
    });
    await page.screenshot({path:path.join(out,`${width}x${height}-${category}.png`)});
    assert.equal(metrics.columns,width>=1000?5:width>=700?4:width>=540?3:4,JSON.stringify(metrics));assert.equal(metrics.overflow,false,JSON.stringify({width,height,category,metrics}));assert.equal(metrics.outside,false);assert.deepEqual(metrics.bad,[]);assert.deepEqual(metrics.clipped,[],JSON.stringify({width,height,category,metrics}));assert.deepEqual(metrics.stockProblems,[],JSON.stringify({width,height,category,metrics}));
    const tiles=page.locator('[data-action="inventory-item"]');
    assert((await tiles.evaluateAll(nodes=>nodes.map(node=>Number(node.dataset.id)))).every(code=>!retired.has(code)),'Removed items never appear in the backpack');
    if(await tiles.count()){
     const tile=tiles.last(),id=await tile.getAttribute('data-id');await tile.click();
     const scroll=await board.evaluate(b=>b.scrollTop);
     assert.equal(await page.locator('#inventory-details [data-form="item-use"]').getAttribute('data-id'),id);
     assert.equal(await tile.getAttribute('aria-pressed'),'true');
     assert.equal(await page.locator('#game-dialog').evaluate(d=>d.open),false,'Selecting an item never opens a popup');
     const layout=await page.evaluate(()=>({board:document.querySelector('.inventory-scroll-board').getBoundingClientRect().toJSON(),details:document.querySelector('#inventory-details').getBoundingClientRect().toJSON()}));
     if(width<540)assert(layout.details.y>=layout.board.bottom-1,'Phone details sit below the grid');
     else assert(layout.details.x>=layout.board.right-1,'Wide details sit beside the grid');
     const submit=page.locator('#inventory-details button[type="submit"]');await submit.scrollIntoViewIfNeeded();
     const bounds=await submit.boundingBox();assert(bounds.y>=0&&bounds.y+bounds.height<=height+1,'Use button is reachable');
     const useAll=page.locator('#inventory-details [data-action="inventory-use-all"]');
     if(await useAll.count()){
      await useAll.scrollIntoViewIfNeeded();
      const box=await useAll.boundingBox();assert(box.x>=0&&box.x+box.width<=width+1&&box.y>=0&&box.y+box.height<=height+1&&box.height>=40,'Use all is reachable on every viewport');
     }
     if(width===320&&category==='speedup')await page.screenshot({path:path.join(out,'320x568-item-details.png')});
     assert.equal(await board.evaluate(b=>b.scrollTop),scroll,'Viewing details preserves the list position');
     // A real app poll must preserve the scrolled grid and the focused tile.
     await page.waitForTimeout(150);
     if(width===390&&category==='speedup'){
      await page.waitForResponse(r=>r.url().endsWith('/api/kingdom/state'));await page.waitForTimeout(250);
      assert.equal(await board.evaluate(b=>b.scrollTop),scroll,'Polling preserves grid position');
      assert.equal(await page.locator('#inventory-details [data-form="item-use"]').getAttribute('data-id'),id,'Polling preserves selected details');
     }
    }
   }
  }
  await page.setViewportSize({width:390,height:844});
  await page.locator('[data-action="inventory-category"][data-id="resource_pack"]').click();
  const zero=state.inventory_catalog.find(i=>i.category==='resource_pack'&&!Number(i.quantity));
  assert(zero);assert.equal(await page.locator(`[data-action="inventory-item"][data-id="${zero.item_code}"]`).count(),0);await require('./fixtures/inventory_access.cjs').showItem(page,zero.item_code);assert.equal(await page.locator('.loot-card.is-unowned').count(),1,'Only the linked unowned item is previewed');
  assert.equal(await page.locator('#inventory-details button[type="submit"]').isDisabled(),true);
  assert.equal(await page.locator('#inventory-details [data-action="inventory-use-all"]').isDisabled(),true);
  await page.locator('[data-action="inventory-category"][data-id="resource_pack"]').click();
  assert.equal(await page.locator(`[data-action="inventory-item"][data-id="${zero.item_code}"]`).count(),0);
  const owned=state.inventory.find(i=>i.category==='resource_pack'&&i.quantity>0);
  assert(owned,'Fixture must contain a resource pack for the real consumption check');
  if(owned){
   await page.locator(`[data-action="inventory-item"][data-id="${owned.item_code}"]`).click();
   const selectedQuantity=Math.min(3,owned.quantity);
   await page.locator('#inventory-quantity').fill(String(selectedQuantity));
   await Promise.all([page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().method()==='POST'),page.locator('#inventory-details button[type="submit"]').click()]);
   await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
   const after=await page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
   assert.equal(after.inventory.find(i=>i.item_code===owned.item_code)?.quantity||0,owned.quantity-selectedQuantity);
   assert.equal(await page.locator('#panel-dialog').evaluate(d=>d.open),true,'Consumption keeps the inventory open');
   await page.waitForFunction(({code,count})=>document.querySelector(`#inventory-details [data-id="${code}"]`)?.closest('.inventory-dialog')?.querySelector('.inventory-owned')?.textContent.includes(Number(count).toLocaleString('de-DE')), {code:owned.item_code,count:owned.quantity-selectedQuantity});
   const bulkRequests=[];const trackBulk=r=>{if(r.method()==='POST'&&r.url().endsWith('/api/kingdom/action')&&r.postDataJSON()?.use_all)bulkRequests.push(r.postDataJSON());};page.on('request',trackBulk);
   const bulkResponse=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.use_all===true);
   await page.locator('#inventory-details [data-action="inventory-use-all"]').dblclick();
   const response=await bulkResponse;assert.equal(response.status(),200);
   const result=(await response.json()).data;
   assert.equal(result.result.quantity,owned.quantity-selectedQuantity,'All consumes the full remaining stack');
   assert.equal(result.result.amount,(owned.quantity-selectedQuantity)*owned.amount,'All credits the complete package value');
   await page.waitForFunction(code=>!document.querySelector(`.inventory-page-grid [data-action="inventory-item"][data-id="${code}"]`),owned.item_code);
   assert.equal(bulkRequests.length,1,'Double click issues only one bulk request');page.off('request',trackBulk);
   assert(bulkRequests[0].operation_key,'Bulk request has a persistent receipt');
   assert.equal(result.state.inventory.find(i=>i.item_code===owned.item_code)?.quantity||0,0);
   assert.equal(await page.locator('#game-dialog').evaluate(d=>d.open),false,'Using all resources keeps inline details');
  }
  await page.locator('#inventory-overview-button').click();
  await page.locator('.inventory-overview').waitFor();
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#panel-dialog').evaluate(d=>d.open),true,'Closing overview returns to inline inventory');
  await page.keyboard.press('Escape');
  await page.waitForFunction(()=>!document.querySelector('#panel-dialog').open);
  await page.locator('#navigation [data-id="inventory"]').click();
  await page.goBack();
  await page.waitForFunction(()=>!document.querySelector('#panel-dialog').open);
  assert.deepEqual(errors,[]);console.log('PASS real inventory: four categories, five viewports, icons, details, scrolling, ownership and available item use. '+out);
 }finally{if(browser)await browser.close();if(fixture&&fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(e=>{console.error(e);process.exitCode=1});
