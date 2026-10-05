'use strict';
// Real /city#city in a disposable database. Only chest claims reach a write API;
// completion feeds and acknowledgement outages are controlled at the HTTP boundary.
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),os=require('os'),net=require('net'),{spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
let base=process.env.CITY_READINESS_FIXTURE_URL;
const output=process.env.CITY_READINESS_OUTPUT?path.resolve(__dirname,'..',process.env.CITY_READINESS_OUTPUT):fs.mkdtempSync(path.join(os.tmpdir(),'conquer-city-readiness-'));
fs.mkdirSync(output,{recursive:true});

async function startFixture(){
 const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const port=server.address().port;await new Promise(resolve=>server.close(resolve));
 base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--chat'],{cwd:path.resolve(__dirname,'..'),stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);
 await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',reject);});
 return fixture;
}

const completed=[
 {id:8101,type:'train_complete',data:{building_code:'barrack',city_id:1,world_id:1,count:25,troop_code:50100101}},
 {id:8102,type:'train_complete',data:{building_code:'barrack',city_id:1,world_id:1,count:15,troop_code:50100101}},
 {id:8103,type:'train_complete',data:{building_code:'archery_range',city_id:1,world_id:1,count:12,troop_code:50200101}},
 {id:8104,type:'train_complete',data:{building_code:'stable',city_id:1,world_id:1,count:9,troop_code:50300101}},
 {id:8105,type:'research_complete',data:{building_code:'academy',city_id:1,world_id:1,research_code:'food_production',level:1}},
 {id:8106,type:'build_complete',data:{building_code:'farm',city_id:1,world_id:1,level:7}},
 {id:8107,type:'heal_complete',data:{building_code:'hospital',city_id:1,world_id:1,count:18}},
];

(async()=>{
 const fixture=base?null:await startFixture();
 assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'A disposable local preview is required');
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 let page;
 try{
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});
  page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  const errors=[],missingAssets=[],readRequests=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.status()>=400&&new URL(response.url()).pathname.startsWith('/assets/'))missingAssets.push(response.url());});
  let records=completed.map(row=>structuredClone(row)),acknowledged=new Set(),failRead=false,hideChests=false,expiredUnprocessed=false,reducedMotion=false;
  await page.route('**/api/game/state*',async route=>{
   const response=await route.fetch(),json=await response.json();
   if(json.ok){
    json.data.building_completions=records.filter(row=>!acknowledged.has(row.id));
    if(expiredUnprocessed)for(const queue of [json.data.build_queue,json.data.troop_queue,json.data.research_queue])for(const job of queue||[]){job.is_processed=0;job.finishes_at='2020-01-01 00:00:00';}
   }
   await route.fulfill({response,json});
  });
  await page.route('**/api/kingdom/state*',async route=>{
   const response=await route.fetch(),json=await response.json();
   if(json.ok){
    if(hideChests&&json.data.chests){json.data.chests.free_silver_available=false;json.data.chests.free_gold_available=false;}
    if(reducedMotion)json.data.settings.reduced_motion=true;
   }
   await route.fulfill({response,json});
  });
  await page.route('**/api/notifications/read',async route=>{
   const body=route.request().postDataJSON(),ids=(body.ids||[body.id]).filter(Boolean).map(Number);readRequests.push(ids);
   if(failRead){failRead=false;await route.fulfill({status:503,contentType:'application/json',json:{ok:false,error:{code:'TEMPORARY_UNAVAILABLE',message:'Please try again.'}}});return;}
   ids.forEach(id=>acknowledged.add(id));await route.fulfill({status:200,contentType:'application/json',json:{ok:true,data:{read:ids.length}}});
  });
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});
  await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);
  const badge=code=>page.locator('.painted-building-ready[data-id="'+code+'"]:visible');
  const countBadges=()=>page.locator('.painted-building-ready:visible').count();
  const closePanel=async()=>{
   for(const selector of ['#game-dialog','#panel-dialog'])if(await page.locator(selector).evaluate(node=>node.open)){
    await page.locator(selector+' .dialog-close:visible, '+selector+' .panel-close:visible, '+selector+' .mobile-page-back:visible').first().click();
    await page.waitForFunction(selector=>!document.querySelector(selector).open,selector);
   }
  };
  const refreshCity=async()=>{await page.goto(base+'/city#city');await page.reload();await page.locator('.painted-village-scene').waitFor();await badge('treasure_house').waitFor();};
  const reachablePoint=locator=>locator.evaluate(node=>{
   const r=node.getBoundingClientRect();
   for(const x of [.5,.8,.2])for(const y of [.5,.2,.8]){
    if(node.contains(document.elementFromPoint(r.left+r.width*x,r.top+r.height*y)))return {x:r.width*x,y:r.height*y};
   }
   return null;
  });
  const reveal=async locator=>{
   await locator.evaluate(node=>{
    const scroller=node.closest('.painted-village-scroll');const b=node.getBoundingClientRect(),r=scroller.getBoundingClientRect();
    scroller.scrollLeft+=b.left+b.width/2-r.left-r.width/2;scroller.scrollTop+=b.top+b.height/2-r.top-r.height/2;
   });
   await page.waitForTimeout(150);
   assert(await reachablePoint(locator),'Building readiness action is reachable after panning');
  };
  const openBadge=async code=>{const target=badge(code);await reveal(target);await target.tap({position:await reachablePoint(target)});};
  const assertNoMenu=async()=>{
   assert.equal(await page.locator('#panel-dialog').evaluate(node=>node.open),false,'Completion acknowledgement keeps the panel closed');
   assert.equal(await page.locator('#game-dialog').evaluate(node=>node.open),false,'Completion acknowledgement keeps the building dialog closed');
   assert.equal(await page.locator('.painted-building-menu:visible').count(),0,'Completion acknowledgement does not select the building');
   assert.equal(new URL(page.url()).hash,'#city','Completion acknowledgement keeps the city active');
  };
  const realKingdom=async()=>{const response=await page.request.get(base+'/api/kingdom/state');const json=await response.json();assert(json.ok,JSON.stringify(json));return json.data;};
  assert.equal((await realKingdom()).profile.display_name,'PreviewPlayer');

  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390]]){
   acknowledged.clear();await page.setViewportSize({width,height});await refreshCity();
   await page.waitForFunction(()=>document.querySelectorAll('.painted-building-ready:not([hidden])').length===7);
   assert.equal(await countBadges(),7,'One actionable marker for each ready building');
   assert.equal(await page.locator('#navigation').count(),1,'The city keeps one navigation bar');
   assert.equal(await page.locator('.painted-village-building .painted-building-ready').count(),0,'Readiness controls are not nested inside building buttons');
   await page.locator('.painted-village-scene img').evaluateAll(async images=>{await Promise.all(images.map(image=>image.decode()));});
   const layout=await page.locator('.painted-building-ready:visible').evaluateAll(nodes=>nodes.map(node=>{const r=node.getBoundingClientRect();return {code:node.dataset.id,kind:node.dataset.kind,width:r.width,height:r.height,label:node.getAttribute('aria-label'),images:[...node.querySelectorAll('img')].every(image=>image.complete&&image.naturalWidth>0)};}));
   for(const item of layout){assert(item.width>=44&&item.height>=44,JSON.stringify(item));assert(item.images,JSON.stringify(item));assert(item.label&&!/city\.|ready\.|undefined|Truppen|Forschung|Schatztruhe/.test(item.label),'New labels use English by default, even in a German browser: '+item.label);}
   assert.equal(layout.find(item=>item.code==='treasure_house').kind,'chest');
   assert.equal(layout.find(item=>item.code==='academy').kind,'research');
   await page.screenshot({path:path.join(output,`${width}x${height}-city.png`)});
   for(const code of ['academy','treasure_house','hospital','stable','archery_range','barrack','farm'])await reveal(badge(code));
   // Only chests and research open a menu; other completion markers are dismissed in the city.
   await openBadge('treasure_house');await page.locator('.treasury-chest-page').waitFor();
   await page.screenshot({path:path.join(output,`${width}x${height}-chests.png`)});await closePanel();
   assert.equal(await badge('treasure_house').count(),1,'Opening the chest window does not claim a chest');
   await openBadge('academy');await page.locator('.rt-scroll').waitFor();
   assert.equal(await page.locator('.rt-focused[data-id="food_production"]').count(),1,'Research readiness focuses the completed research');await closePanel();
   for(const code of ['barrack','archery_range','stable','hospital','farm']){
    await openBadge(code);
    await page.waitForFunction(code=>!document.querySelector('.painted-building-ready[data-id="'+code+'"]:not([hidden])'),code);
    await assertNoMenu();
   }
   await page.screenshot({path:path.join(output,`${width}x${height}-dismissed.png`)});
   assert(readRequests.some(ids=>ids.includes(8101)&&ids.includes(8102)),'A grouped training badge acknowledges both completions');
   console.log('PASS readiness layout and touch navigation '+width+'x'+height);
  }

  await page.setViewportSize({width:1280,height:800});acknowledged.clear();await refreshCity();
  // A failed acknowledgement leaves the server-backed marker available to retry.
  records=completed.filter(row=>row.data.building_code==='barrack');acknowledged.clear();failRead=true;await refreshCity();
  const failedRead=page.waitForResponse(response=>response.url().endsWith('/api/notifications/read')&&response.status()===503);
  await openBadge('barrack');await failedRead;await assertNoMenu();
  assert.equal(await badge('barrack').count(),1,'Failed acknowledgement preserves readiness');
  await openBadge('barrack');await assertNoMenu();
  await page.waitForFunction(()=>!document.querySelector('.painted-building-ready[data-id="barrack"]:not([hidden])'));
  await page.reload();await badge('treasure_house').waitFor();assert.equal(await badge('barrack').count(),0,'Confirmed acknowledgement remains gone on reload');

  // Mouse drag begins over a marker but must pan the city without activating it.
  records=completed;acknowledged.clear();await page.setViewportSize({width:390,height:844});await refreshCity();await reveal(badge('barrack'));
  const beforeReadCount=readRequests.length,rect=await badge('barrack').boundingBox();
  await page.mouse.move(rect.x+rect.width/2,rect.y+rect.height/2);await page.mouse.down();await page.mouse.move(rect.x+rect.width/2+70,rect.y+rect.height/2+20,{steps:8});await page.mouse.up();
  assert.equal(readRequests.length,beforeReadCount,'Dragging never acknowledges completion');
  assert.equal(await page.locator('#panel-dialog').evaluate(node=>node.open),false,'Dragging never opens a destination');
  assert.equal(await badge('barrack').count(),1);

  reducedMotion=true;await refreshCity();
  const animations=await page.locator('.painted-building-ready:visible').evaluateAll(nodes=>nodes.flatMap(node=>[node,...node.querySelectorAll('*')].map(element=>getComputedStyle(element).animationName)));
  assert(animations.every(name=>name==='none'),'Readiness animation honours reduced motion');
  reducedMotion=false;
  records=[
   {...completed[0],id:8201,data:{...completed[0].data,city_id:999}},
   {...completed[4],id:8202,data:{...completed[4].data,world_id:999}},
   {...completed[5],id:8203,data:{building_code:'farm'}},
  ];
  hideChests=true;expiredUnprocessed=true;
  await page.reload();await page.locator('.painted-village-scene').waitFor();await page.waitForTimeout(400);
  assert.equal(await countBadges(),0,'Expired unprocessed queues and completions from another city/world never create readiness');
  assert(await page.locator('.painted-village-building.is-training').count()>0,'Unprocessed queue remains an active training status');

  // Real free chest actions must clear the last marker only after both available chests were claimed.
  records=[];hideChests=false;expiredUnprocessed=false;await page.setViewportSize({width:1280,height:800});await refreshCity();
  assert.equal(await countBadges(),1);await openBadge('treasure_house');await page.locator('.treasury-chest-page').waitFor();
  for(const type of ['silver','gold']){
   const response=page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().postDataJSON()?.action==='chest.free');
   await page.locator('[data-action="treasury-chest-open"][data-id="'+type+'"]').click();const result=await response;assert.equal(result.status(),200,await result.text());assert((await result.json()).ok);
   await page.locator('.reward-result').waitFor();
   await page.locator('#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible').first().click();
   await page.waitForFunction(type=>document.querySelector('[data-action="treasury-chest-open"][data-id="'+type+'"]')?.disabled,type);
   if(type==='silver')assert.equal(await badge('treasure_house').count(),1,'The remaining free gold chest retains the treasury marker');
  }
  await closePanel();await page.waitForFunction(()=>!document.querySelector('.painted-building-ready[data-id="treasure_house"]:not([hidden])'));
  const claimed=await realKingdom();assert.equal(claimed.chests.free_silver_available,false);assert.equal(claimed.chests.free_gold_available,false);
  assert.deepEqual(errors,[],'No browser exceptions');assert.deepEqual(missingAssets,[],'All requested artwork and application assets loaded');
  console.log('PASS research/chest menus, menu-free training/healing/build acknowledgements, grouped acknowledgement/retry, drag safety, reduced motion, authoritative queue readiness and real chest claims. '+output);
 }catch(error){
  if(page){await page.screenshot({path:path.join(output,'failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,'failure.json'),JSON.stringify({message:error.message,url:page.url(),text:await page.locator('body').innerText().catch(()=>''),badges:await page.locator('.painted-building-ready').evaluateAll(nodes=>nodes.map(node=>({html:node.outerHTML,box:node.getBoundingClientRect().toJSON()}))).catch(()=>[])},null,2));}
  throw error;
 }finally{
  await browser.close();if(fixture&&fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}
 }
})().catch(error=>{console.error(error);console.error('Screenshots: '+output);process.exitCode=1;});
