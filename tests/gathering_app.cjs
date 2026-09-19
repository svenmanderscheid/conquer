'use strict';
// Run against tools/preview-feature-fixture.php --gathering --port=18958.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.GATHERING_FIXTURE_URL||'http://127.0.0.1:18958';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/gathering-ui');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'}),errors=[];let checks=0;
 try{
  async function login(name){
   const context=await browser.newContext(),page=await context.newPage({viewport:{width:1280,height:800}});page.on('pageerror',e=>errors.push(e.message));
   await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill(name);await page.locator('[name="password"]').fill('PreviewFixture!2026');
   await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);await page.goto(base+'/city#world');await page.locator('.atlas-marker--nodes').first().waitFor();return page;
  }
  const page=await login('PreviewPlayer');
  const snapshot=await page.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
  const own=snapshot.nodes.find(n=>n.is_own_gathering),enemy=snapshot.nodes.find(n=>n.gatherer_name==='EnemyFarmer'),ally=snapshot.nodes.find(n=>n.gatherer_name==='AlliedFarmer');
  assert(own?.gathering_finishes_at);assert(enemy?.can_attack&&!('gathering_finishes_at' in enemy));assert(ally&&!ally.can_attack&&!('gathering_finishes_at' in ally));checks+=3;
  for(const [width,height]of[[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(1200);
   const ui=await page.evaluate(()=>{
    const q=s=>document.querySelector(s),rect=e=>{const r=e.getBoundingClientRect();return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height}};
    return {count:q('#hud-march-status').textContent,rows:[...q('#hud-march-activity').children].map(b=>b.textContent),list:rect(q('#hud-march-activity')),chat:rect(q('#world-chat')),routes:q('.atlas-routes').children.length,parties:q('.atlas-parties').children.length,own:q('.atlas-gathering-badge.is-own')?.textContent,foreign:[...document.querySelectorAll('.atlas-gathering-badge:not(.is-own)')].map(e=>e.textContent)};
   });
   assert.equal(ui.rows.length,3);assert(ui.rows.some(t=>t.includes('Sammelt'))&&ui.rows.some(t=>t.includes('Unterwegs'))&&ui.rows.some(t=>t.includes('Rückkehr')));assert.equal(ui.routes,2);assert.equal(ui.parties,2);assert.match(ui.own,/Sammelt · \d+:\d+/);assert(ui.foreign.every(t=>!(/\d+:\d+/).test(t)));
   assert(ui.list.x>=0&&ui.list.right<=width+1&&ui.list.y>=0&&ui.list.bottom<=height);await page.screenshot({path:path.join(output,`${width}x${height}-world.png`)});assert(ui.list.bottom<=ui.chat.y||ui.list.right<=ui.chat.x||ui.list.x>=ui.chat.right,JSON.stringify({width,height,...ui}));checks+=8;
   await page.screenshot({path:path.join(output,`${width}x${height}-world.png`)});
   // Put each target near the center so the test reaches it through the real map controls.
   for(const [node,action]of[[own,'gather-recall'],[enemy,'expedition'],[ally,'expedition']]){
    await page.locator('[data-atlas="navigation"]').click();await page.locator('.atlas-jump [name=x]').fill(String(node.coord_x));await page.locator('.atlas-jump [name=y]').fill(String(node.coord_y));await page.locator('.atlas-jump button').click();
    await page.locator(`[data-atlas-target="nodes:${node.id}"]`).first().click();
    const button=page.locator(`.atlas-target-actions [data-action="${action}"]`);
    assert.equal(await button.isDisabled(),node.id===ally.id);
    if(node.id===enemy.id){assert.equal(await button.getAttribute('data-kind'),'node-attack');await page.screenshot({path:path.join(output,`${width}x${height}-enemy.png`)});}
    if(node.id===own.id){assert.match(await page.locator('.atlas-target-actions .atlas-node-status').textContent(),/Sammelt/);await page.screenshot({path:path.join(output,`${width}x${height}-gathering.png`)});}
    await page.locator('.atlas-target-actions [data-atlas="clear"]').click();checks++;
   }
  }
  const before=await page.locator('.atlas-gathering-badge.is-own').textContent();await page.waitForTimeout(1200);assert.notEqual(await page.locator('.atlas-gathering-badge.is-own').textContent(),before);checks++;
  const other=await login('EnemyFarmer');const otherState=await other.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
  const foreign=otherState.nodes.find(n=>n.id===own.id);assert(foreign.can_attack&&!('gathering_finishes_at' in foreign));assert(otherState.marches.every(m=>m.id!==snapshot.marches.find(x=>x.state==='arrived').id));checks+=2;
  const attack=await other.evaluate(async node=>{const data=(await (await fetch('/api/game/state')).json()).data;return (await (await fetch('/api/march/dispatch-field-attack',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':data.player.csrf},body:JSON.stringify({target_x:Number(node.coord_x),target_y:Number(node.coord_y),troops:{50100101:100}})})).json());},foreign);
  assert(attack.ok,JSON.stringify(attack));checks++;
  await page.setViewportSize({width:1280,height:800});await page.goto(base+'/city#city');await page.locator('#city-frame').waitFor();await page.waitForTimeout(5000);await page.screenshot({path:path.join(output,'embedded-city.png')});
  assert.equal(await page.locator('#hud-march-activity').isVisible(),false);checks++;
  const scene=page.frameLocator('#city-frame');
  assert.equal(await scene.locator('.realm-hud').isVisible(),false);assert.equal(await scene.locator('.realm-nav').isVisible(),false);checks+=2;
  await scene.locator('#zoomIn').click();await scene.locator('#zoomIn').click();await page.waitForTimeout(1000);await page.screenshot({path:path.join(output,'embedded-city-close.png')});
  await page.goto(base+'/city/3d?embed=1');await page.locator('#loading').waitFor({state:'hidden'});await page.waitForTimeout(1000);await page.screenshot({path:path.join(output,'city-full.png')});
  await page.locator('#zoomIn').click();await page.locator('#zoomIn').click();await page.waitForTimeout(1000);await page.screenshot({path:path.join(output,'city-close.png')});
  assert.deepEqual(errors,[]);checks++;console.log(JSON.stringify({checks,errors,output}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
