'use strict';
// Real app, disposable database, all 15 active T1–T5 troops. No marches are dispatched.
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),net=require('net');
const {spawn}=require('child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.resolve(process.env.MARCH_OUTPUT||path.join(root,'artifacts/march-compact'));fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--regional-bosses','--march-roster','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='',page;const errors=[],checks=[],formationRequests=[];
 try{
  await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>reject(Error(log||'Preview timeout')),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timeout);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(timeout);reject(Error(log));});});
  browser=await chromium.launch({headless:true,channel:'chrome',args:['--use-angle=swiftshader','--enable-unsafe-swiftshader']});
  const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'}),base='http://127.0.0.1:'+port;
  await context.addInitScript(()=>{if(location.pathname.endsWith('/city')&&!location.hash)history.replaceState(null,'','#world');});
  page=await context.newPage();page.setDefaultTimeout(20000);page.on('pageerror',e=>errors.push(e.message));
  page.on('requestfailed',request=>{if(request.url().includes('/api/defense/'))formationRequests.push({url:request.url(),failure:request.failure()});});
  await page.route('**/api/defense/state',async route=>{const response=await route.fetch(),json=await response.json();formationRequests.push({status:response.status(),error:json.error||null});if(json.data)json.data.formations=[{slot:1,name:'Testformation',troops:{50100101:123}}];await route.fulfill({response,json});});
  await page.goto(base+'/?zugang=login');await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);await page.locator('#hud-menu').waitFor();
  const switcher=page.locator('#navigation .hud-scene-switch');if(await switcher.getAttribute('data-id')==='world')await switcher.click();
  const target=page.locator('.atlas-marker--monsters').filter({has:page.locator('img[data-life-kind="orc"]')}).first();await target.waitFor({state:'attached'});
  await page.evaluate(({x,y})=>ConquerWorld.focus(+x,+y),await target.evaluate(el=>({x:el.dataset.x,y:el.dataset.y})));
  await page.waitForFunction(()=>{const el=document.querySelector('.atlas-marker--monsters img[data-life-kind="orc"]')?.closest('.atlas-marker');if(!el)return false;const r=el.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));});
  const position=await target.evaluate(el=>{const r=el.getBoundingClientRect();for(const [fx,fy]of [[.5,.5],[.5,.9],[.1,.9],[.9,.9],[.1,.1],[.9,.1]]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
  assert(position,'Monster is reachable');await target.click({position});
  await page.locator('.march-command').waitFor();await page.evaluate(()=>document.fonts.ready);
  assert.equal(await page.locator('.march-unit-row').count(),15);assert.equal(await page.locator('[data-action="march-page"]').count(),0);
  await page.waitForFunction(()=>!document.querySelector('[data-action="march-formation-load"][data-id="1"]').disabled);
  assert.equal(await page.locator('[data-action="march-formation-load"]').count(),6);
  await page.locator('[data-action="march-formation-load"][data-id="1"]').click();
  assert.equal(await page.locator('#march-unit-50100101').inputValue(),'123');
  assert.equal(await page.locator('#march-selected').textContent(),'123');
  const palette=JSON.parse(fs.readFileSync(path.join(root,'docs/TROOP_TIER_COLORS.prompts.json'),'utf8')).palette;
  const expectedTiers=Array.from({length:5},(_,i)=>5-i).flatMap(t=>[t,t,t]);
  const roster=await page.locator('.march-portrait').evaluateAll(async portraits=>{await Promise.all(portraits.map(el=>el.querySelector('img').decode()));return portraits.map(el=>({tier:+el.dataset.troopTier,color:getComputedStyle(el).borderTopColor,style:getComputedStyle(el).borderTopStyle,shadow:getComputedStyle(el).boxShadow,src:el.querySelector('img').getAttribute('src')}));});
  assert.deepEqual(roster.map(r=>r.tier),expectedTiers);
  for(const item of roster){const hex=palette[item.tier-1].color;const rgb=hex.slice(1).match(/../g).map(v=>parseInt(v,16));assert.equal(item.color,`rgb(${rgb.join(', ')})`);assert(item.src.includes('/fantasy-troops-v2/'));if(item.tier===9)assert.equal(item.style,'double');if(item.tier===10)assert(item.shadow.includes('131, 141, 155'));}
  for(const [width,height]of [[1580,883],[1280,800],[768,1024],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   const list=page.locator('.march-unit-list'),inputs=page.locator('.march-unit-amount input');
   await page.locator('[data-action="march-max"]').click();
   assert.equal(await page.locator('.march-selected-card').count(),15,'Each selected active type and tier has its own card');
   assert.deepEqual(await page.locator('.march-selected-card').evaluateAll(cards=>cards.map(c=>+c.dataset.troopTier)),expectedTiers);
   const selectedColors=await page.locator('.march-selected-card').evaluateAll(cards=>cards.map(c=>getComputedStyle(c).borderTopColor));
   assert.deepEqual(selectedColors,roster.map(r=>r.color),'Selected army cards retain the same tier colors as the roster');
   if(width>=600&&height>500){
    const army=await page.locator('.march-army').evaluate(el=>({height:el.clientHeight,content:el.scrollHeight,overflow:getComputedStyle(el).overflowY}));
    assert(army.content<=army.height+1||['auto','scroll'].includes(army.overflow),'Full army overview fits or remains scrollable: '+JSON.stringify({width,height,...army}));
    await page.screenshot({path:path.join(out,`${width}x${height}-full-army.png`)});
   }
   assert(await page.locator('#march-confirm').evaluate(el=>{const r=el.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),'All selected tiers keep dispatch reachable');
   await page.locator('[data-action="march-clear"]').click();
   await inputs.last().scrollIntoViewIfNeeded();await inputs.last().fill('123');
   const before=await list.evaluate(el=>el.scrollTop);assert(before>0);
   assert.equal(await page.locator('#march-selected').textContent(),'123');
   await page.locator('.march-unit-row').last().locator('[data-action="march-unit-max"]').click();
   assert(Number(await inputs.last().inputValue())>123);
   await list.evaluate(el=>el.scrollTop=0);await inputs.first().fill('321');
   const range=page.locator('.march-range').first();await range.focus();const amount=Number(await inputs.first().inputValue());await page.keyboard.press('ArrowRight');assert.equal(Number(await inputs.first().inputValue()),amount+1);
   const metrics=await page.locator('#game-dialog').evaluate(dialog=>{
    const r=dialog.getBoundingClientRect(),list=dialog.querySelector('.march-unit-list'),lr=list.getBoundingClientRect(),send=dialog.querySelector('#march-confirm'),b=send.getBoundingClientRect();
    return{width:innerWidth,height:innerHeight,fits:r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:dialog.scrollWidth-dialog.clientWidth,visibleRows:[...list.children].filter(el=>{const q=el.getBoundingClientRect();return q.top>=lr.top&&q.bottom<=lr.bottom;}).length,rowHeight:list.firstElementChild.getBoundingClientRect().height,sendReachable:send.contains(document.elementFromPoint(b.x+b.width/2,b.y+b.height/2))};
   });
   assert(metrics.fits&&metrics.overflow<=1,JSON.stringify(metrics));assert(metrics.sendReachable,'Dispatch remains reachable');
   assert(metrics.rowHeight<=150,'Illustrated rows remain compact enough to scan: '+JSON.stringify(metrics));
   assert(metrics.visibleRows>=(width>=1000?4:height>800?2:1),'At least one complete troop row stays visible: '+JSON.stringify(metrics));
   const portrait=await page.locator('.march-portrait').first().boundingBox();
   assert(portrait.width>=(height<=500?44:60)&&portrait.height>=56,'Troop art remains readable at '+width+'x'+height);
   checks.push(metrics);await page.screenshot({path:path.join(out,`${width}x${height}-troops.png`)});
   const targetTab=page.locator('[data-action="march-view"][data-id="target"]');
   if(await targetTab.isVisible()){
    await targetTab.click();assert(await page.locator('.march-target').isVisible());
    await page.locator('.march-target').evaluate(el=>el.scrollTop=0);
    const art=await page.locator('.march-target-art img').boundingBox();
    assert(art.width>=110&&art.height>=110,'Target illustration is prominent at '+width+'x'+height);
    await page.screenshot({path:path.join(out,`${width}x${height}-target.png`)});
    await page.locator('[data-action="march-view"][data-id="troops"]').click();assert.equal(Number(await inputs.first().inputValue()),amount+1);
   }
  }
  await page.keyboard.press('Escape');assert(await page.locator('#game-dialog').isHidden());
  assert.deepEqual(errors,[]);
  fs.writeFileSync(path.join(out,'checks.json'),JSON.stringify({checks,errors},null,2));console.log(JSON.stringify({checks,errors,output:out},null,2));
 }catch(error){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:error.message,errors,formationRequests},null,2));if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{if(page)await page.unrouteAll({behavior:'ignoreErrors'});if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
