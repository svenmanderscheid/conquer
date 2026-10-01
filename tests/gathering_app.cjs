'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
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
   await page.goto(base);await page.goto(new URL('?zugang=login', page.url()).href);await page.locator("[name=identifier], [name=username]").fill(name);await page.locator('[name="password"]').fill('PreviewFixture!2026');
   await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);await page.goto(base+'/city#world');await page.locator('.atlas-marker--nodes').first().waitFor();return page;
  }
  const page=await login('PreviewPlayer');
  const clickReachable=async(locator,label)=>{const locate=()=>locator.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.2,.8])for(const fx of [.5,.9,.1,.98,.02]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});let point=await locate();if(!point&&await page.locator('.world-march-heading[aria-expanded=true]').isVisible()){await page.locator('.world-march-heading').click();point=await locate();}if(!point)await page.screenshot({path:path.join(output,'unreachable-target.png')});assert(point,label+' must have a reachable touch point after using the march-list collapse control');await locator.click({position:point});};
  const snapshot=await page.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
  const own=snapshot.nodes.find(n=>n.is_own_gathering),enemy=snapshot.nodes.find(n=>n.gatherer_name==='EnemyFarmer'),ally=snapshot.nodes.find(n=>n.gatherer_name==='AlliedFarmer');
  assert(own?.gathering_finishes_at);assert(enemy?.can_attack&&!('gathering_finishes_at' in enemy));assert(ally&&!ally.can_attack&&!('gathering_finishes_at' in ally));checks+=3;
  for(const [width,height]of[[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});if(await page.locator('.world-march-heading[aria-expanded=false]').isVisible())await page.locator('.world-march-heading').click();await page.waitForTimeout(1200);
   const ui=await page.evaluate(()=>{
    const q=s=>document.querySelector(s),rect=e=>{const r=e.getBoundingClientRect();return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height}};
    return {count:q('#hud-march-status').textContent,rows:[...q('.world-march-list').children].map(b=>b.textContent),list:rect(q('.world-march-list')),chat:rect(q('#world-chat')),chatVisible:q('#world-chat').checkVisibility({checkVisibilityCSS:true}),routes:q('.atlas-routes').children.length,parties:q('.atlas-parties').children.length,own:q('.atlas-gathering-badge.is-own')?.textContent,foreign:[...document.querySelectorAll('.atlas-gathering-badge:not(.is-own)')].map(e=>e.textContent)};
   });
   assert.equal(ui.rows.length,3);assert(ui.rows.some(t=>t.includes('Sammelt'))&&ui.rows.some(t=>t.includes('Zur Mine'))&&ui.rows.some(t=>t.includes('Rückkehr')));assert.equal(ui.routes,2);assert.equal(ui.parties,2);assert.match(ui.own,/[0-9.,]+ \/ [0-9.,]+/);assert(ui.foreign.every(t=>!(/\d+:\d+/).test(t)));
   assert(ui.list.x>=0&&ui.list.right<=width+1&&ui.list.y>=0&&ui.list.bottom<=height);await page.screenshot({path:path.join(output,`${width}x${height}-world.png`)});assert(!ui.chatVisible||ui.list.bottom<=ui.chat.y||ui.list.right<=ui.chat.x||ui.list.x>=ui.chat.right,JSON.stringify({width,height,...ui}));checks+=8;
   for(const recall of await page.locator('.world-march-recall:not([hidden])').all()){
    await recall.scrollIntoViewIfNeeded();
    assert(await recall.evaluate(el=>{const r=el.getBoundingClientRect();return r.width>=44&&r.height>=44&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),`${width}x${height}: direct recall is a distinct reachable 44px target`);checks++;
   }
   assert(await page.locator('.world-march-row small').first().evaluate(el=>parseFloat(getComputedStyle(el).fontSize)>=11),'March status remains readable');
   await page.locator('.world-march-list').evaluate(el=>el.scrollTop=0);
   for(const selector of ['.map-overlay-home','[data-atlas="navigation"]']){assert(await page.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return r.width>0&&r.height>0&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),`${width}x${height}: ${selector} center must remain uncovered by the march HUD`);checks++;}
   await page.screenshot({path:path.join(output,`${width}x${height}-world.png`)});
   // Put each target near the center so the test reaches it through the real map controls.
   for(const [node,action]of[[own,'gather-recall'],[enemy,'expedition'],[ally,'expedition']]){
    await page.locator('[data-atlas="navigation"]').click();await page.locator('.atlas-jump [name=x]').fill(String(node.coord_x));await page.locator('.atlas-jump [name=y]').fill(String(node.coord_y));await page.locator('.atlas-jump button').click();
    await clickReachable(page.locator(`[data-atlas-target="nodes:${node.id}"]`).first(),'Gathering resource');
    const button=page.locator(`.atlas-target-actions [data-action="${action}"]`);
    assert.equal(await button.isDisabled(),node.id===ally.id);
    if(node.id===enemy.id){assert.equal(await button.getAttribute('data-kind'),'node-attack');await page.screenshot({path:path.join(output,`${width}x${height}-enemy.png`)});}
    if(node.id===own.id){const status=await page.locator('.atlas-target-actions .atlas-node-status').textContent();const amount=status.match(/^Gesammelt: ([\d.,]+) \/ ([\d.,]+)$/);assert(amount,'Own gathering shows collected amount and carrying capacity: '+status);const collected=Number(amount[1].replaceAll('.','')),capacity=Number(amount[2].replaceAll('.',''));assert.equal(capacity,Number(own.gathering_progress.capacity));assert(collected>=0&&collected<=capacity);await page.screenshot({path:path.join(output,`${width}x${height}-gathering.png`)});}
    await page.locator('.atlas-target-actions [data-atlas="clear"]').click();checks++;
   }
  }
  const progress=page.locator('.atlas-gathering-badge.is-own [role=progressbar]'),before=Number(await progress.getAttribute('aria-valuenow'));
  // The fixture collects 0.5 units/second; wait for a whole displayed unit rather than a fraction of its tick.
  await page.waitForFunction(before=>Number(document.querySelector('.atlas-gathering-badge.is-own [role=progressbar]')?.getAttribute('aria-valuenow'))>before,before,{timeout:6000});
  const after=Number(await progress.getAttribute('aria-valuenow'));assert(after>before&&after<=Number(await progress.getAttribute('aria-valuemax')));checks++;
  const other=await login('EnemyFarmer');const otherState=await other.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
  const foreign=otherState.nodes.find(n=>n.id===own.id);assert(foreign.can_attack&&!('gathering_finishes_at' in foreign));assert(otherState.marches.every(m=>m.id!==snapshot.marches.find(x=>x.state==='arrived').id));checks+=2;
  const attack=await other.evaluate(async node=>{const data=(await (await fetch('/api/game/state')).json()).data;return (await (await fetch('/api/march/dispatch-field-attack',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':data.player.csrf,'X-World-ID':String(data.city.world_id)},body:JSON.stringify({operation_key:crypto.randomUUID(),expected_world_id:Number(data.city.world_id),target_x:Number(node.coord_x),target_y:Number(node.coord_y),troops:{50100101:100}})})).json());},foreign);
  assert(attack.ok,JSON.stringify(attack));checks++;
  await page.setViewportSize({width:1280,height:800});await page.goto(base+'/city#city');await page.locator('.painted-village').waitFor();assert.equal(await page.locator('#city-frame').count(),0);await page.screenshot({path:path.join(output,'city-painted.png')});
  assert.deepEqual(errors,[]);checks++;console.log(JSON.stringify({checks,errors,output}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
