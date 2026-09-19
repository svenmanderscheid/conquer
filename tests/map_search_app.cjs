'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.MAP_SEARCH_FIXTURE_URL||'http://127.0.0.1:18957';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required: --regional-bosses --chat --map-search');
const output=path.resolve(__dirname,'../artifacts/map-search');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome',args:['--use-angle=swiftshader','--enable-unsafe-swiftshader']});
 const errors=[];let checks=0;
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800}});page.on('pageerror',e=>{errors.push(e.stack);console.error(e.stack);});
  await page.addInitScript(()=>{window.mapHistoryLog=[];for(const name of ['pushState','replaceState','back']){const original=history[name].bind(history);history[name]=(...args)=>{mapHistoryLog.push([name,args[0],location.hash]);return original(...args);};}window.addEventListener('popstate',()=>mapHistoryLog.push(['popstate',history.state,location.hash]));});
  await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  await page.locator('#navigation [data-id="world"]').click();
  const panel=page.locator('#atlas-search-panel'),open=async()=>{if(!await panel.isVisible())await page.getByRole('button',{name:'Kartensuche öffnen',exact:true}).click();await panel.waitFor({state:'visible'});await page.waitForFunction(()=>document.querySelector('.atlas-search-level-value').textContent!=='–');};
  for(const [width,height] of (process.argv.includes('--flows-only')?[]:[[1280,800],[390,844],[320,568],[844,390],[568,320]])){
   await page.setViewportSize({width,height});await open();
   assert.equal(await panel.locator('.atlas-search-category').count(),7);checks++;
   assert(await page.locator('.map-overlay-backdrop').isHidden(),'search remains non-modal and leaves the map interactive');checks++;
   assert(await page.evaluate(()=>document.activeElement?.tagName!=='INPUT'),'opening search must not summon the mobile keyboard');assert.equal(await page.locator('.atlas-nearby,.atlas-nearby-row').count(),0,'search has no result list');checks+=2;
   await panel.locator('[data-search-category="food"]').click();
   await panel.locator('#atlas-object-level').fill('1');
   assert.equal(await panel.locator('.atlas-search-level-value').textContent(),'2');checks++;
   await panel.locator('[data-atlas="level-down"]').click();assert.equal(await panel.locator('.atlas-search-level-value').textContent(),'1');checks++;
   await panel.locator('[data-atlas="level-up"]').click();
   await panel.locator('.atlas-search-category img').evaluateAll(images=>Promise.all(images.map(img=>img.decode())));
   await page.screenshot({path:path.join(output,`${width}x${height}-search.png`)});
   for(const selector of ['.atlas-object-search-submit','.map-search-collapse','#atlas-object-level','[data-atlas="level-down"]','[data-atlas="level-up"]']){
    assert(await panel.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),`${width}x${height}: reachable ${selector}`);checks++;
   }
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth&&document.documentElement.scrollHeight<=innerHeight));checks++;
   const response=page.waitForResponse(r=>r.url().includes('/api/map/search?'));
   assert(await page.locator('#navigation').isVisible(),'permanent navigation stays visible beside search');checks++;
   await panel.getByRole('button',{name:/^(Nächstes Ziel|Weiter suchen)$/}).click();const data=(await (await response).json()).data;
   assert.equal(data.target.kind,'nodes');assert.equal(Number(data.target.data.level),2);assert.equal(Number(data.target.data.object_type),1);checks+=3;
   await panel.waitFor({state:'hidden'});await page.locator('.atlas-target-actions .is-gather').waitFor({state:'visible'});
   assert(await page.locator(`.atlas-marker[data-atlas-target="nodes:${data.target.data.id}"]`).isVisible());checks++;
   await page.screenshot({path:path.join(output,`${width}x${height}-result.png`)});
   let previous=data,seen=new Set([Number(data.target.data.id)]);
   for(let i=0;i<3;i++){
    const next=page.locator('.atlas-target-actions [data-atlas="search-next"]');await next.waitFor({state:'visible'});
    assert(await next.evaluate(el=>{const r=el.getBoundingClientRect();return r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),`${width}x${height}: next search button reachable`);checks++;
    const response=page.waitForResponse(r=>r.url().includes('/api/map/search?'));
    await next.click();const httpResponse=await response,body=await httpResponse.json();assert(body.ok,JSON.stringify(body));const result=body.data;
    assert(result.target&&result.cursor);const id=Number(result.target.data.id);
    const distance=row=>(Number(row.coord_x)-65)**2+(Number(row.coord_y)-65)**2;
    if(i<2){assert(!seen.has(id),'repeat click selects a different object');assert(distance(result.target.data)>=distance(previous.target.data),'results stay ordered from the city');seen.add(id);}
    else{assert.equal(id,Number(data.target.data.id));assert(result.wrapped,'after the last object return to the nearest');}
    await page.waitForFunction(key=>document.querySelector(`.atlas-marker[data-atlas-target="${key}"]`)?.getAttribute('aria-pressed')==='true',`nodes:${id}`);previous=result;checks+=3;
   }
   if(width===1280){await page.locator('.atlas-target-actions .is-gather').click();await page.locator('#game-dialog').waitFor({state:'visible'});assert((await page.locator('#game-dialog').textContent()).includes('Sammeln'));await page.locator('#game-dialog').getByRole('button',{name:'Fenster schließen',exact:true}).click();checks++;}
   await open();assert.equal(await panel.locator('.atlas-search-level-value').textContent(),'2','selection survives reopening');checks++;
   await page.goBack();await panel.waitFor({state:'hidden'});assert.equal(new URL(page.url()).hash,'#world');checks++;
   await open();await page.keyboard.press('Escape');await panel.waitFor({state:'hidden'});
   await page.waitForFunction(()=>!history.state?.conquerMapSearch).catch(async error=>{console.error(await page.evaluate(()=>({history:mapHistoryLog,state:history.state,hidden:document.querySelector('#atlas-search-panel').hidden})));throw error;});checks++;
   console.log(`PASS search, result, Back and layout at ${width}x${height}`);
  }
  await page.setViewportSize({width:390,height:844});
  for(const category of ['solo','rally']){
   await open();await panel.locator(`[data-search-category="${category}"]`).click();await panel.locator('#atlas-object-level').fill('0');
   await panel.getByRole('button',{name:/^(Nächstes Ziel|Weiter suchen)$/}).click();await panel.waitFor({state:'hidden'});
   await page.locator('.atlas-target-actions [data-action="expedition"]').click();await page.locator('#game-dialog').waitFor({state:'visible'});
   if(category==='rally')assert.equal(await page.locator('#game-dialog .is-monster-rally').count(),1);
   await page.locator('#game-dialog').getByRole('button',{name:'Fenster schließen',exact:true}).click();checks++;
  }
  for(const category of ['lumber','stone','gold','gems']){
   await open();await panel.locator(`[data-search-category="${category}"]`).click();await panel.locator('#atlas-object-level').fill('1');
   await panel.getByRole('button',{name:/^(Nächstes Ziel|Weiter suchen)$/}).click();await panel.waitFor({state:'hidden'});checks++;
  }
  await open();await panel.locator('[data-search-category="gems"]').click();await panel.locator('#atlas-object-level').fill(await panel.locator('#atlas-object-level').getAttribute('max'));
  await panel.getByRole('button',{name:/^(Nächstes Ziel|Weiter suchen)$/}).click();await page.waitForFunction(()=>document.querySelector('.atlas-search-feedback').textContent.includes('Kein freies Ziel'));checks++;
  await page.screenshot({path:path.join(output,'empty-search.png')});
  await page.route('**/api/map/search?*',route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({ok:false,error:{message:'Suche momentan nicht erreichbar.'}})}));
  await panel.getByRole('button',{name:/^(Nächstes Ziel|Weiter suchen)$/}).click();await page.waitForFunction(()=>document.querySelector('.atlas-search-feedback').textContent.includes('nicht erreichbar'));assert(await panel.getByRole('button',{name:/^(Nächstes Ziel|Weiter suchen)$/}).isEnabled());checks++;
  await page.unroute('**/api/map/search?*');
  // A response arriving after dismissal must not reopen a target or move the map.
  let release;const gate=new Promise(resolve=>release=resolve);let intercepted;const started=new Promise(resolve=>intercepted=resolve);
  await page.route('**/api/map/search?*',async route=>{const response=await route.fetch();intercepted();await gate;await route.fulfill({response});});
  await panel.locator('[data-search-category="food"]').click();await panel.locator('#atlas-object-level').fill('1');
  const before=await page.evaluate(()=>ConquerWorld.getCenter());await panel.getByRole('button',{name:/^(Nächstes Ziel|Weiter suchen)$/}).click();await started;
  await panel.locator('.map-search-collapse').click();await panel.waitFor({state:'hidden'});release();await page.waitForTimeout(600);
  assert.deepEqual(await page.evaluate(()=>ConquerWorld.getCenter()),before);assert(!await page.locator('.atlas-target-actions').isVisible());checks+=2;
  await page.unroute('**/api/map/search?*');await open();
  assert.equal(await panel.locator('.atlas-nearby-row').count(),0);checks++;
  await panel.locator('.map-search-collapse').click();await page.waitForFunction(()=>!history.state?.conquerMapSearch);
  await page.locator('#navigation [data-id="city"]').click();
  await page.frameLocator('#city-frame').locator('#world canvas').waitFor();const frame=page.frames().find(f=>f.url().includes('/city/3d'));await frame.waitForFunction(()=>window.conquer3D?.getState().ready,{},{timeout:120000});
  for(const selector of ['.realm-hud','#resource-bar','.realm-nav','.city-footer']){assert(!await frame.locator(selector).isVisible());checks++;}
  await page.screenshot({path:path.join(output,'embedded-city.png')});
  await page.setViewportSize({width:1280,height:800});await page.screenshot({path:path.join(output,'city-overview.png')});
  const angle=await frame.evaluate(()=>conquer3D.getState().wheelAngle);await frame.waitForFunction(previous=>conquer3D.getState().wheelAngle!==previous,angle);checks++;
  await frame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'lumber_camp'}})));await frame.locator('#zoomIn').click();await page.screenshot({path:path.join(output,'city-close.png')});
  assert.deepEqual(errors,[]);console.log(`PASS ${checks} map-search app checks; screenshots: ${output}`);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
