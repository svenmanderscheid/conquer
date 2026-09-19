'use strict';
// Run against the disposable app from tools/preview-feature-fixture.php --port=18949.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.RESEARCH_FIXTURE_URL||'http://127.0.0.1:18949';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview URL required');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-research-app-'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const errors=[],failures=[];let checks=0;
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});
  page.setDefaultTimeout(15000);
  page.on('pageerror',error=>{errors.push(error.message);console.error('Browser error: '+error.message);});
  page.on('response',response=>{if(response.url().startsWith(base+'/api/')&&response.status()>=400)failures.push(response.status()+' '+response.url());});
  await page.goto(base,{waitUntil:'domcontentloaded'});
  await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');
  await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  const data=await page.evaluate(async()=>{const response=await fetch('/api/game/state');return (await response.json()).data;});
  assert.equal(data.research_defs.length,117);checks++;
  await page.locator('#hud-research').click();
  await page.locator('.rt-continuous').waitFor();
  for(const [width,height]of[[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   for(const [branch,tree,count]of[['economy','production',34],['military','battle',43],['development','advanced',40]]){
    console.log(`Checking ${width}x${height} ${branch}`);
    await page.locator(`.rt-branch[data-id="${branch}"]`).click();
    await page.waitForFunction(()=>document.querySelector('.rt-scroll')?.clientWidth>0);
    const expected=data.research_defs.filter(node=>node.tree===tree).map(node=>node.code).sort();
    assert.equal(await page.locator('.rt-node').count(),count);checks++;
    assert.deepEqual((await page.locator('.rt-node').evaluateAll(nodes=>nodes.map(node=>node.dataset.id))).sort(),expected);checks++;
    assert.equal(await page.locator('.rt-pagination,[data-action="research-page"],#research-chapter').count(),0);checks++;
    const metrics=await page.evaluate(()=>{const el=document.querySelector('.rt-scroll');return{w:el.clientWidth,h:el.clientHeight,sw:el.scrollWidth,sh:el.scrollHeight,left:el.scrollLeft,top:el.scrollTop};});
    assert(metrics.h>=80,`Tree has usable height at ${width}×${height}: ${JSON.stringify(metrics)}`);assert(metrics.sw<=metrics.w);assert(metrics.sh>metrics.h);assert.equal(metrics.left,0);assert.equal(metrics.top,0);checks+=5;
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
   await page.screenshot({path:path.join(output,`research-${width}x${height}-details.png`)});
   await page.locator('#game-dialog>.dialog-close').click();
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
  assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),false);assert.equal(await page.locator('.rt-node').count(),43);checks+=2;
  await session.detach();
  // Let native touch inertia finish before comparing saved scroll coordinates.
  await page.locator('.rt-scroll').evaluate(async el=>{
   let previous=el.scrollTop,stable=0;
   for(let frame=0;frame<120&&stable<8;frame++){
    await new Promise(requestAnimationFrame);
    stable=el.scrollTop===previous?stable+1:0;previous=el.scrollTop;
   }
  });
  // Real polling redraws must preserve vertical position and keyboard focus.
  await page.locator('.rt-scroll').evaluate(el=>{el.scrollTop=1500;el.focus();});
  const before=await page.locator('.rt-scroll').evaluate(el=>{window.researchScrollBefore=el;return{left:el.scrollLeft,top:el.scrollTop};});
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
  await page.locator('#research-search').fill('no_such_research');await page.locator('#research-search').press('Enter');
  assert.equal(await page.locator('.rt-node').count(),0);assert((await page.locator('.rt-empty').textContent()).includes('Keine Forschung gefunden'));checks+=2;
  await page.locator('[data-action="research-clear"]').click();assert.equal(await page.locator('.rt-node').count(),43);checks++;
  await page.locator('#panel-dialog .panel-close').click();assert.equal(await page.locator('#panel-dialog').evaluate(el=>el.open),false);checks++;
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);checks+=2;
  console.log(`${checks} real research app checks passed. Screenshots: ${output}`);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);console.error('Screenshots: '+output);process.exit(1);});
