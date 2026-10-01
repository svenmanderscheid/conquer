// Real app + disposable --gathering fixture. Never submits a march or recall.
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const base=process.env.GATHERING_FIXTURE_URL||'http://127.0.0.1:18958';assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/gathering-occupation');fs.mkdirSync(output,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'}),errors=[],writes=[];
 try{const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base);await page.locator('[name="identifier"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action="/auth/local"] button[type="submit"]').click()]);await page.goto(base+'/city#world');await page.waitForSelector('.atlas-marker[data-occupation="own"]');
  // Use the real collapse control so the expanded march list does not cover
  // the camera centre on 320px phones.
  await page.locator('.world-march-heading[aria-expanded="true"]').click();
  page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/'))writes.push(r.url());});
  const state=await page.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
  const targets={own:state.nodes.find(n=>n.is_own_gathering),ally:state.nodes.find(n=>n.gatherer_name==='AlliedFarmer'),enemy:state.nodes.find(n=>n.gatherer_name==='EnemyFarmer'),free:state.nodes.find(n=>!n.gatherer_march_id)};
  assert(Object.values(targets).every(Boolean));assert(targets.enemy.can_attack);assert(!targets.ally.can_attack);assert(!('gathering_finishes_at' in targets.ally));assert(!('gathering_finishes_at' in targets.enemy));
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   for(const [relation,node] of Object.entries(targets)){
    await page.evaluate(n=>ConquerWorld.focus(+n.coord_x,+n.coord_y),node);
    const marker=page.locator(`[data-atlas-target="nodes:${node.id}"]`);await marker.waitFor({state:'visible'});
    assert.equal(await marker.getAttribute('data-occupation'),relation);
    assert.equal(await marker.locator('.atlas-gathering-work').count(),relation==='free'?0:1);
    assert.equal(await marker.locator('.atlas-resource-level').textContent(),`Lv. ${node.level}`);
    const label=await marker.locator('.atlas-resource-status').textContent();
    if(relation==='ally'||relation==='enemy'){assert.equal(label,node.gatherer_name);assert(!/\d+:\d+/.test(label),'no foreign timer');}
    if(relation==='own'){assert.match(label,/\d.* \/ .*\d/);assert(await marker.locator('[role="progressbar"]').isVisible());assert(node.gathering_progress.capacity>0);}
    else {assert(!node.gathering_progress);assert.equal(await marker.locator('[role="progressbar"]').isVisible(),false);}
    assert(!label.includes('Noch '));
    const row=await marker.locator('.atlas-gathering-badge').evaluate(n=>({h:n.getBoundingClientRect().height,dir:getComputedStyle(n).flexDirection}));assert.equal(row.dir,'row');assert(row.h<30,'single compact line');
    if(relation==='free')assert.equal(label,'');
    assert.equal(await marker.locator('.atlas-marker-level').isVisible(),relation==='free','free resource uses the shared monster-style level plaque');
    await marker.locator('canvas').waitFor();
    const placement=await marker.evaluate(n=>{const c=n.querySelector('canvas'),r=c.getBoundingClientRect(),p=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let bottom=0;for(let y=0;y<c.height;y++)for(let x=0;x<c.width;x++)if(p[(y*c.width+x)*4+3]>32)bottom=y;const plaque=n.dataset.occupation==='free'?n.querySelector('.atlas-marker-level'):n.querySelector('.atlas-gathering-badge');return{art:r.top+(bottom+1)/c.height*r.height,label:plaque.getBoundingClientRect().top};});
    if(relation!=='free')assert(placement.label>=placement.art+1,'occupied plaque is below visible building');
    if(width===1280){const clip=await marker.evaluate(n=>{const art=n.querySelector('canvas').getBoundingClientRect(),label=n.querySelector(n.dataset.occupation==='free'?'.atlas-marker-level':'.atlas-gathering-badge').getBoundingClientRect(),x=Math.min(art.left,label.left)-8,y=art.top-8;return{x,y,width:Math.max(art.right,label.right)-x+8,height:label.bottom-y+8};});await page.screenshot({path:path.join(output,`label-${relation}.png`),clip});}
    if(relation!=='free'){
     const colour=await marker.evaluate(n=>({actual:getComputedStyle(n.querySelector('.gathering-banner')).fill,expected:getComputedStyle(document.documentElement).getPropertyValue(n.dataset.occupation==='enemy'?'--ui-red':'--ui-blue').trim()}));
     const [r,g,b]=colour.expected.slice(1).match(/../g).map(n=>parseInt(n,16));assert.equal(colour.actual,`rgb(${r}, ${g}, ${b})`);
    }
    await marker.tap();const menu=page.locator('.atlas-target-actions');
    if(relation==='free'){
     // Free resources open army selection directly, without sending troops.
     const dialog=page.locator('#game-dialog[open]');await dialog.locator('.march-command.is-gather').waitFor();
     assert.equal(await menu.isVisible(),false);
     const submit=dialog.locator('#march-confirm');await submit.waitFor({state:'visible'});
     const r=await submit.boundingBox();assert(r&&r.x>=0&&r.y>=0&&r.x+r.width<=width+1&&r.y+r.height<=height+1,'Gather action remains reachable');
     assert.deepEqual(writes,[],'Selecting a destination does not send an army');
     await page.screenshot({path:path.join(output,`${width}x${height}-${relation}.png`)});
     await dialog.locator('.dialog-close:visible,.mobile-page-back:visible').first().tap();
     await dialog.waitFor({state:'hidden'});await page.waitForTimeout(120);continue;
    }
    await menu.waitFor({state:'visible'});
    assert.equal(await page.locator('.atlas-gathering-ring').count(),0,'no blue ground ring');
    assert.equal(await page.locator('.atlas-cell-focus').isVisible(),false,'no cell selection ring for resources');
    if(relation==='own'){assert.equal(await menu.locator('[data-action="gather-recall"]').count(),1);assert.equal(await menu.locator('[data-kind="node-attack"]').count(),0);}
    if(relation==='ally'){assert.equal(await menu.locator('[data-kind="node-attack"]').count(),0);assert(await menu.locator('[data-action="expedition"]').isDisabled());assert.equal(await menu.locator('[data-action="gather-recall"]').count(),0);}
    if(relation==='enemy')assert(await menu.locator('[data-kind="node-attack"]').isEnabled());
    for(const button of await menu.locator('button').all()){const r=await button.boundingBox();assert(r&&r.x>=-1&&r.y>=-1&&r.x+r.width<=width+1&&r.y+r.height<=height+1,`${relation}: button clipped at ${width}`);}
    await page.screenshot({path:path.join(output,`${width}x${height}-${relation}.png`)});await menu.locator('[data-atlas="clear"]').click();
   }
   console.log(`PASS ${width}x${height}: own/ally blue, enemy red, free clear; actions and touch layout`);
  }
  await page.setViewportSize({width:1280,height:800});await page.evaluate(n=>ConquerWorld.focus(+n.coord_x,+n.coord_y),targets.enemy);
  const sprite=page.locator(`[data-atlas-target="nodes:${targets.enemy.id}"] canvas[data-work-ready="true"]`),pixels=async()=>require('node:crypto').createHash('sha256').update(await sprite.evaluate(n=>n.toDataURL())).digest('hex');
  await sprite.waitFor();assert.equal(await page.locator('.atlas-gathering-tool').count(),0,'floating tools removed');
  const first=await pixels();await page.waitForTimeout(1100);assert.notEqual(await pixels(),first,'painted worker animates');
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(150);const still=await pixels();await page.waitForTimeout(1100);assert.equal(await pixels(),still,'reduced motion stable');
  await page.emulateMedia({reducedMotion:'no-preference'});await page.evaluate(()=>{document.body.dataset.graphicsQuality='light';dispatchEvent(new Event('conquer-graphics-quality'));});await page.waitForTimeout(150);const light=await pixels();await page.waitForTimeout(1100);assert.equal(await pixels(),light,'light quality stable');
  await page.evaluate(()=>{document.body.dataset.graphicsQuality='normal';dispatchEvent(new Event('conquer-graphics-quality'));});
  // Hide the actual world through navigation. Calling setVisible(false) while
  // remaining on #world would correctly be undone by the next state refresh.
  await page.locator('#navigation [data-id="city"]').click();
  await page.waitForFunction(()=>document.body.classList.contains('city-mode')&&document.querySelector('.atlas-shell')?.classList.contains('gathering-paused'));
  const paused=await pixels();await page.waitForTimeout(1100);assert.equal(await pixels(),paused,'hidden work pauses');
  await page.locator('#navigation [data-id="world"]').click();
  await page.waitForFunction(()=>document.body.classList.contains('world-mode')&&!document.querySelector('.atlas-shell')?.classList.contains('gathering-paused'));
  // Simulate the next read-only server snapshot: occupation disappears and changes
  // alliance. No database mutation, no march dispatch, no user's session is used.
  await page.route('**/api/game/state**',async route=>{const response=await route.fetch(),body=await response.json();for(const n of body.data.nodes||[])if(n.id===targets.enemy.id){n.can_attack=false;n.gathering_finishes_at='2099-01-01 12:34:56';}await route.fulfill({response,json:body});});
  await page.waitForFunction(id=>document.querySelector(`[data-atlas-target="nodes:${id}"]`)?.dataset.occupation==='ally',targets.enemy.id,{timeout:30000});
  assert.equal(await page.locator(`[data-atlas-target="nodes:${targets.enemy.id}"] .atlas-resource-status`).textContent(),targets.enemy.gatherer_name,'foreign timer ignored even in a malformed snapshot');
  await page.unroute('**/api/game/state**');
  await page.route('**/api/game/state**',async route=>{const response=await route.fetch(),body=await response.json();for(const n of body.data.nodes||[])if(n.id===targets.enemy.id){n.gatherer_march_id=null;n.can_attack=false;n.is_own_gathering=false;}await route.fulfill({response,json:body});});
  await page.waitForFunction(id=>!document.querySelector(`[data-atlas-target="nodes:${id}"] .atlas-gathering-work`),targets.enemy.id,{timeout:30000});
  assert.deepEqual(writes,[]);assert.deepEqual(errors,[]);console.log('PASS motion/reduced/light/hidden, relationship updates, occupation cleanup and no browser errors or mutations');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
