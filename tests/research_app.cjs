'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Runs its own disposable fixture, or an explicitly supplied disposable URL.
const fs=require('fs'),path=require('path'),os=require('os'),net=require('net'),assert=require('assert');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
let base=process.env.RESEARCH_FIXTURE_URL;
async function startFixture(){
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const root=path.resolve(__dirname,'..'),child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--speed-bonuses','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('Research fixture startup timed out: '+log)),60000);child.stdout.on('data',chunk=>{log+=chunk;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',chunk=>log+=chunk);child.once('error',reject);child.once('exit',code=>{clearTimeout(timer);reject(new Error('Research fixture stopped ('+code+'): '+log));});});
 base='http://127.0.0.1:'+port;return child;
}
const output=process.env.RESEARCH_TEST_OUTPUT||fs.mkdtempSync(path.join(os.tmpdir(),'conquer-research-app-'));
fs.mkdirSync(output,{recursive:true});
(async()=>{
 const fixture=base?null:await startFixture();
 assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview URL required');
 const browser=await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});
 const errors=[],failures=[];let checks=0,page;
 try{
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});
  page.setDefaultTimeout(15000);
  page.on('pageerror',error=>{errors.push(error.message);console.error('Browser error: '+error.message);});
  page.on('response',response=>{if(response.url().startsWith(base+'/api/')&&response.status()>=400)failures.push(response.status()+' '+response.url());});
  await page.context().addCookies([{name:'conquer_locale',value:'de',url:base}]);
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});
  await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');
  await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  const data=await page.evaluate(async()=>{const response=await fetch('/api/game/state');return (await response.json()).data;});
  assert(data.research_duration_factor>0&&data.research_duration_factor<1,'Server supplies bonus-adjusted research duration');checks++;
  assert.equal(data.research_defs.length,129);checks++;
  const catalogs=['production','battle','advanced'].flatMap(tree=>JSON.parse(fs.readFileSync(path.join(__dirname,'../data/research',tree+'.json'),'utf8')).nodes);
  for(const node of catalogs)assert.deepEqual(data.research_defs.find(def=>def.code===node.code).levels.map(level=>[level.resources,level.time]),node.levels.map(level=>[level.resources,level.time]));
  checks++;
  // Resource help and building dialogs must show the same increased server rates.
  for(const [width,height]of[[1280,800],[320,568],[568,320]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(250);
   for(const [resource,building]of Object.entries({food:'farm',lumber:'lumber_camp',stone:'quarry',gold:'gold_mine'})){
    await page.locator(`#resources [data-action="resource"][data-id="${resource}"]`).tap();
    await page.locator('#game-dialog[open]').waitFor();
    const shown=await page.locator('#game-dialog .detail-row').filter({hasText:'Produktion pro Stunde'}).locator('strong').innerText();
    assert.equal(Number(shown.replace(/\D/g,'')),Math.floor(data.production_rates[building]),`${resource} hourly production matches the server`);checks++;
    assert.equal(data.buildings[building].production,data.production_rates[building]);checks++;
    await page.locator(`#game-dialog [data-action="building"][data-id="${building}"]`).tap();
    const production=page.locator('#game-dialog .levelup-stat').filter({hasText:'Produktion je Stunde'});
    assert.equal(Number((await production.locator('strong').innerText()).replace(/\D/g,'')),Math.floor(data.production_rates[building]),`${building} shows the same hourly rate`);checks++;
    const inside=await page.locator('#game-dialog').evaluate(el=>{const r=el.getBoundingClientRect();return r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&el.scrollWidth<=el.clientWidth+1;});
    assert(inside,'Production window fits the viewport');checks++;
    await page.screenshot({path:path.join(output,`production-${width}x${height}-${resource}.png`)});
    await page.locator('#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible').first().tap();
   }
  }
  await page.setViewportSize({width:1280,height:800});await page.waitForTimeout(250);
  await page.locator('#hud-research').click();
  await page.locator('.rt-continuous').waitFor();
  assert.equal(await page.locator('.rt-branch').first().getAttribute('data-id'),'military');
  assert.equal(await page.locator('.rt-branch.is-selected').getAttribute('data-id'),'military');checks+=2;
  for(const [width,height]of[[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   // The app debounces orientation layout by 150 ms. Measure after it settles.
   await page.waitForTimeout(250);
   for(const [branch,tree,count]of[['economy','production',34],['military','battle',55],['development','advanced',40]]){
    console.log(`Checking ${width}x${height} ${branch}`);
    await page.locator(`.rt-branch[data-id="${branch}"]`).click();
    await page.waitForFunction(()=>document.querySelector('.rt-scroll')?.clientWidth>0);
    const expected=data.research_defs.filter(node=>node.tree===tree).map(node=>node.code).sort();
    assert.equal(await page.locator('.rt-node').count(),count);checks++;
    assert.deepEqual((await page.locator('.rt-node').evaluateAll(nodes=>nodes.map(node=>node.dataset.id))).sort(),expected);checks++;
    assert.equal(await page.locator('.rt-pagination,[data-action="research-page"],#research-chapter').count(),0);checks++;
    const metrics=await page.evaluate(()=>{const el=document.querySelector('.rt-scroll');return{w:el.clientWidth,h:el.clientHeight,sw:el.scrollWidth,sh:el.scrollHeight,left:el.scrollLeft,top:el.scrollTop};});
    assert(metrics.h>=80,`Tree has usable height at ${width}×${height}: ${JSON.stringify(metrics)}`);assert(metrics.sw<=metrics.w);assert(metrics.sh>metrics.h);assert.equal(metrics.left,0);assert(metrics.top>=0);checks+=5;
    const currentResearchState=await page.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
    const nextVisible=await page.locator('.rt-scroll').evaluate((scroll,data)=>{
     const available=[...scroll.querySelectorAll('.rt-available')];
     const target=available.find(el=>{const def=data.research_defs.find(n=>n.code===el.dataset.id),next=def.levels.find(l=>l.level===Number(data.research[def.code]||0)+1);return Object.entries(next.resources||{}).every(([key,value])=>Number(data.city[key]||0)>=Number(value));})||available[0]||scroll.querySelector('.rt-running');
     if(!target)return true;
     const rect=target.getBoundingClientRect(),view=scroll.getBoundingClientRect();
     return rect.bottom>view.top&&rect.top<view.bottom;
    },currentResearchState);
    assert(nextVisible,'Next available research is immediately visible');checks++;
    const layout=await page.locator('.rt-board').evaluate(board=>{
     const nodes=[...board.querySelectorAll('.rt-node')],byCode=new Map(nodes.map(n=>[n.dataset.id,n]));
     return{
      clipped:nodes.filter(n=>{const name=n.querySelector('.rt-node-name');return name.scrollWidth>name.clientWidth+1||name.getBoundingClientRect().bottom>n.getBoundingClientRect().bottom+1;}).map(n=>n.dataset.id),
      badEdges:[...board.querySelectorAll('.rt-edge')].filter(e=>{const from=byCode.get(e.dataset.from),to=byCode.get(e.dataset.to);return !e.querySelector('path').getAttribute('d')||(from&&from.getBoundingClientRect().bottom>=to.getBoundingClientRect().top);}).map(e=>e.dataset.from+' → '+e.dataset.to)
     };
    });
    assert.deepEqual(layout.clipped,[],`Clipped names at ${width}×${height}`);assert.deepEqual(layout.badEdges,[],`Edges must progress downwards at ${width}×${height}`);checks+=2;
    // Every discovery can be fully brought into view, including bottom rows on short screens.
    const hidden=await page.locator('.rt-scroll').evaluate(scroll=>{
     const failures=[];
     for(const node of scroll.querySelectorAll('.rt-node')){
      node.scrollIntoView({block:'nearest',inline:'nearest'});
      const rect=node.getBoundingClientRect(),view=scroll.getBoundingClientRect();
      if(rect.left<view.left-2||rect.right>view.left+scroll.clientWidth+2||rect.top<view.top-2||rect.bottom>view.top+scroll.clientHeight+2)failures.push(node.dataset.id);
     }
     scroll.scrollLeft=0;scroll.scrollTop=0;return failures;
    });
    assert.deepEqual(hidden,[],`Unreachable nodes at ${width}×${height}`);checks++;
    const panel=await page.locator('#panel-dialog').evaluate(el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,w:r.width,h:r.height,sw:el.scrollWidth,cw:el.clientWidth};});
    assert(panel.x>=0&&panel.y>=0&&panel.x+panel.w<=width+1&&panel.y+panel.h<=height+1,JSON.stringify(panel));assert(panel.sw<=panel.cw+1);checks+=2;
    await page.screenshot({path:path.join(output,`research-${width}x${height}-${branch}.png`)});
   }
   // A search result in another tab must reveal and focus the distant real node.
   await page.locator('#research-search').fill('Wissensdurst');await page.locator('#research-search').press('Enter');
   assert.equal(await page.locator('.rt-node').count(),2);checks++;
   await page.locator('.rt-node[data-id="advanced_research_speed"]').click();
   assert.equal(await page.locator('.rt-branch[data-id="economy"]').getAttribute('aria-pressed'),'true');checks++;
   assert(await page.locator('.rt-focused').evaluate(el=>el.dataset.id==='advanced_research_speed'&&document.activeElement===el));checks++;
   assert((await page.locator('.rt-scroll').evaluate(el=>el.scrollTop))>1000);assert.equal(await page.locator('.rt-scroll').evaluate(el=>el.scrollLeft),0);checks+=2;
   await page.locator('.rt-focused').click();await page.locator('#game-dialog[open]').waitFor();
   assert((await page.locator('#game-dialog').textContent()).includes('Wissensdurst II'));checks++;
   const requirements=data.research_defs.find(node=>node.code==='advanced_research_speed').levels[0].requirements;
   assert.equal(await page.locator('.research-requirement').count(),requirements.length);checks++;
   const expectedCosts=data.research_defs.find(node=>node.code==='advanced_research_speed').levels[0].resources;
   const seconds=Math.ceil(data.research_defs.find(node=>node.code==='advanced_research_speed').levels[0].time*data.research_duration_factor);
   assert.equal(await page.locator('#game-dialog .levelup-time strong').innerText(),await page.evaluate(n=>window.ConquerLocale.formatDuration(n),seconds),'Displayed research duration includes all bonuses');checks++;
   const displayedCosts=await page.locator('#game-dialog .levelup-resource-row').evaluateAll(rows=>Object.fromEntries(rows.map(row=>[row.querySelector('img').getAttribute('src').split('/').pop().replace('.png',''),Number(row.querySelector('.levelup-resource-values').textContent.split('/')[1].replace(/\D/g,''))])));
   assert.deepEqual(displayedCosts,Object.fromEntries(Object.entries(expectedCosts).filter(([,amount])=>amount>0)));checks++;
   const expectedBenefit=await page.evaluate(def=>[window.ConquerResearch.bonus(def,undefined).value,window.ConquerResearch.bonus(def,def.levels[0]).value],data.research_defs.find(node=>node.code==='advanced_research_speed'));
   assert.deepEqual(await page.locator('#game-dialog .levelup-stat :is(strong,b)').allInnerTexts(),expectedBenefit,'Research compares current and next authoritative bonuses');checks++;
   const detail=page.locator('#game-dialog'),scroll=detail.locator('.levelup-scroll');
   assert.equal(await scroll.count(),1,'Research detail has one scrollable content area');checks++;
   await scroll.evaluate(el=>el.scrollTop=el.scrollHeight);
   const geometry=await detail.evaluate(el=>{const scroll=el.querySelector('.levelup-scroll'),action=el.querySelector('.levelup-primary'),r=action.getBoundingClientRect(),header=el.querySelector('.popup-heading').getBoundingClientRect();return{overflow:scroll.scrollWidth>scroll.clientWidth+1,action:{x:r.x,y:r.y,right:r.right,bottom:r.bottom,height:r.height},header:{top:header.top,bottom:header.bottom}};});
   assert(!geometry.overflow,'Research detail never scrolls sideways');assert(geometry.action.height>=44&&geometry.action.x>=0&&geometry.action.right<=width+1&&geometry.action.y>=0&&geometry.action.bottom<=height+1,'Research action stays visible with 44px touch target');assert(geometry.header.top>=0&&geometry.header.bottom<=height,'Research header stays visible');checks+=3;
   await scroll.evaluate(el=>el.scrollTop=0);
   await page.screenshot({path:path.join(output,`research-${width}x${height}-details.png`)});
   await page.locator("#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();
   await page.locator('.rt-branch[data-id="military"]').click();
   for(const [code,days] of [['guardian',21],['crusader',45]]){
    const node=page.locator(`.rt-node[data-id="${code}"]`);
    await node.scrollIntoViewIfNeeded();await node.click();
    await page.locator('#game-dialog[open]').waitFor();
    const expected=Math.ceil(days*86400*data.research_duration_factor);
    assert.equal(await page.locator('#game-dialog .levelup-time strong').innerText(),await page.evaluate(n=>window.ConquerLocale.formatDuration(n),expected),`${code} shows the approved bonus-adjusted duration`);checks++;
    assert(await page.locator('#game-dialog [data-action="research"]').isDisabled(),'Missing prerequisites still block the shortened research');checks++;
    await page.screenshot({path:path.join(output,`research-${width}x${height}-${code}.png`)});
    await page.locator("#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();
   }
  }
  await page.setViewportSize({width:390,height:844});
  await page.locator('.rt-branch[data-id="military"]').click();
  // Finish the app's 150 ms orientation redraw before starting a real gesture.
  await page.waitForTimeout(250);
  // Native touch panning moves the same tree and must not open a node as a ghost tap.
  const session=await page.context().newCDPSession(page),rect=await page.locator('.rt-scroll').boundingBox();
  const point={x:Math.round(rect.x+rect.width/2),y:Math.round(rect.y+rect.height-35)};
  await session.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[point]});
  for(let i=1;i<=8;i++){
   await session.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:point.x,y:point.y-i*27}]});
   await page.evaluate(()=>new Promise(requestAnimationFrame));
  }
  await session.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
  await page.waitForFunction(()=>document.querySelector('.rt-scroll').scrollTop>80);
  assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),false);assert.equal(await page.locator('.rt-node').count(),55);checks+=2;
  await session.detach();
  // The touch gesture is verified above. Reload to terminate native compositor
  // momentum before the independent idle-poll preservation check.
  await page.reload({waitUntil:'networkidle'});await page.locator('.rt-continuous').waitFor();
  await page.locator('.rt-branch[data-id="military"]').click();
  await page.locator('.rt-scroll').evaluate(el=>{el.focus({preventScroll:true});el.scrollTop=1500;});
  const before=await page.locator('.rt-scroll').evaluate(el=>{window.researchScrollBefore=el;return{left:el.scrollLeft,top:el.scrollTop};});
  assert.equal(before.top,1500,'polling test begins at the exact requested idle position');
  // Starting a job in this disposable kingdom makes the real polling signature change.
  const started=await page.evaluate(async()=>{
   const state=(await (await fetch('/api/game/state')).json()).data;
   const response=await fetch('/api/research/start',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':state.player.csrf,'X-World-ID':String(state.city.world_id)},body:JSON.stringify({code:'cavalry_hp',level_to:Number(state.research.cavalry_hp||0)+1,expected_world_id:state.city.world_id})});
   return await response.json();
  });
  assert(started.ok,JSON.stringify(started));checks++;
  await page.waitForFunction(()=>document.querySelector('.rt-scroll')!==window.researchScrollBefore,{},{timeout:15000});
  const running=page.locator('.rt-active-research');
  await running.waitFor();
  assert.equal(await running.count(),1,'Exactly one active-research banner is shown');checks++;
  assert((await running.textContent()).includes('Forschung läuft'));assert((await running.textContent()).includes('Starke Reittiere'));checks+=2;
  assert.equal(await running.locator('[role="progressbar"]').count(),1,'The active banner has one progress bar');assert.equal(await running.locator('[data-end]').count(),1,'The active banner has one live countdown');checks+=2;
  await page.screenshot({path:path.join(output,'research-390x844-active.png')});
  const after=await page.locator('.rt-scroll').evaluate(el=>({left:el.scrollLeft,top:el.scrollTop,focused:document.activeElement===el}));
  assert.equal(after.left,before.left);assert(Math.abs(after.top-before.top)<=2,`Scroll position changed: ${before.top} → ${after.top}`);assert(after.focused);checks+=3;
  await running.click();await page.locator('#game-dialog[open] .research-detail').waitFor();
  assert.equal(await page.locator('#game-dialog [data-action="research"]').count(),0,'Running research cannot be started twice');assert.equal(await page.locator('#game-dialog [data-action="queue-speedups"]').count(),1,'Running research retains acceleration');checks+=2;
  await page.screenshot({path:path.join(output,'research-390x844-running-details.png')});
  await page.locator('#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible').first().click();
  await page.locator('.rt-node[data-id="infantry_hp"]').click();await page.locator('#game-dialog[open] .research-detail').waitFor();
  assert(await page.locator('#game-dialog [data-action="research"]').isDisabled(),'A busy research queue blocks another project');assert.equal(await page.locator('#game-dialog .levelup-warning').count(),1);checks+=2;
  await page.locator('#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible').first().click();
  await page.locator('#research-search').fill('no_such_research');await page.locator('#research-search').press('Enter');
  assert.equal(await page.locator('.rt-node').count(),0);assert((await page.locator('.rt-empty').textContent()).includes('Keine Forschung gefunden'));checks+=2;
  await page.locator('[data-action="research-clear"]').click();assert.equal(await page.locator('.rt-node').count(),55);checks++;
  await page.route('**/api/game/state*',async route=>{const response=await route.fetch(),json=await response.json(),state=json.data;state.research_queue=[];state.research={...state.research,cavalry_hp:state.research_defs.find(node=>node.code==='cavalry_hp').max_level};await route.fulfill({response,json});});
  await page.reload({waitUntil:'networkidle'});await page.locator('.rt-continuous').waitFor();await page.locator('.rt-branch[data-id="military"]').click();await page.locator('.rt-node[data-id="cavalry_hp"]').click();
  assert.equal(await page.locator('#game-dialog .research-detail').count(),1,'Completed research keeps the same layout');assert.equal(await page.locator('#game-dialog [data-action="research"]').count(),0,'Completed research has no start action');assert.equal(await page.locator('#game-dialog .levelup-stat b').count(),0,'Completed research shows only the attained bonus');checks+=3;
  await page.screenshot({path:path.join(output,'research-390x844-complete.png')});
  await page.locator('#game-dialog .levelup-primary').click();
  await page.locator("#panel-dialog .panel-close:visible, #panel-dialog .mobile-page-back:visible").first().click();assert.equal(await page.locator('#panel-dialog').evaluate(el=>el.open),false);checks++;
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);checks+=2;
  console.log(`${checks} real research app checks passed. Screenshots: ${output}`);
 }catch(error){if(page){await page.screenshot({path:path.join(output,'research-failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,'research-failure.html'),await page.content().catch(()=>''));}console.error({errors,failures});throw error;
 }finally{await browser.close();if(fixture&&fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);console.error('Screenshots: '+output);process.exit(1);});
