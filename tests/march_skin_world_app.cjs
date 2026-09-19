'use strict';
// Disposable preview: --march-skins --march-skin-world --chat --port=18979.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.MARCH_SKIN_FIXTURE_URL||'http://127.0.0.1:18979';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/march-skin-world-app');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const page=await browser.newPage({viewport:{width:1280,height:800},serviceWorkers:'block'}),errors=[];page.on('pageerror',e=>{errors.push(e.message);console.error(e.stack)});
 const read=()=>page.evaluate(async()=>(await(await fetch('/api/game/state')).json()).data);
 const post=(route,body)=>page.evaluate(async({route,body})=>{const state=(await(await fetch('/api/game/state')).json()).data;const r=await fetch('/api/'+route,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':state.player.csrf},body:JSON.stringify({...body,expected_world_id:1})});return{status:r.status,body:await r.json()};},{route,body});
 const mustOk=r=>{assert.equal(r.status,200,JSON.stringify(r));return r.body.data;};
 try{
  await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  const initial=await read();assert.equal(initial.player.name,'PreviewPlayer');
  const wallet=await page.evaluate(async()=>(await(await fetch('/api/kingdom/state')).json()).data.profile.gems);assert.equal(wallet,10000,'synthetic march-skins fixture required');
  mustOk(await post('kingdom/action',{action:'march_skin.buy',march_skin:'phoenix'}));mustOk(await post('kingdom/action',{action:'march_skin.claim',march_skin:'default'}));mustOk(await post('kingdom/action',{action:'march_skin.equip',march_skin:'phoenix'}));
  const equipped=await read(),troop=equipped.troop_defs.find(t=>Number(t.code)===50100101),plain=initial.troop_defs.find(t=>Number(t.code)===50100101);
  assert(Math.abs(troop.monster_march_speed/plain.monster_march_speed-1.05)<1e-9);
  const distance=m=>Math.hypot(Number(m.coord_x)-Number(equipped.city.coord_x),Number(m.coord_y)-Number(equipped.city.coord_y));
  const target=equipped.monsters.filter(m=>m.definition?.type!=='rally'&&distance(m)>15&&distance(m)<38).sort((a,b)=>distance(b)-distance(a))[0];assert(target,'normal monster available in preview frontier');
  const sent=mustOk(await post('march/dispatch',{target_x:Number(target.coord_x),target_y:Number(target.coord_y),troops:{50100101:100},request_id:'skin_world_app_'+Date.now()}));
  const before=(await read()).marches.find(m=>Number(m.id)===Number(sent.march_id));assert(before);assert.equal(before.march_skin,'phoenix');assert.equal(Number(before.march_speed_bonus_pct),5);
  const seconds=(Date.parse(before.arrival_time+'Z')-Date.parse(before.departure_time+'Z'))/1000;assert.equal(seconds,Math.max(5,Math.floor(distance(target)*100/troop.monster_march_speed)));
  await page.goto(base+'/city#world');
  const actor=page.locator('.atlas-march-party[data-march-skin="phoenix"]');await actor.waitFor();await page.waitForFunction(()=>document.querySelector('.atlas-march-party[data-march-skin="phoenix"]')?.classList.contains('is-skinned'));
  assert.match(await actor.locator('img').last().getAttribute('src'),/flight-phoenix\.webp/);assert.match(await actor.getAttribute('aria-label'),/Phönixgarde/);assert.match(await actor.getAttribute('aria-label'),/100 Truppen/);
  const position=await actor.evaluate(e=>e.style.transform);await page.waitForFunction(previous=>document.querySelector('.atlas-march-party[data-march-skin="phoenix"]')?.style.transform!==previous,position);
  await page.screenshot({path:path.join(output,'phoenix-marching-desktop.png')});
  mustOk(await post('kingdom/action',{action:'march_skin.equip',march_skin:'default'}));
  const after=(await read()).marches.find(m=>Number(m.id)===Number(sent.march_id));assert.equal(after.march_skin,before.march_skin);assert.equal(after.arrival_time,before.arrival_time);assert.equal(after.departure_time,before.departure_time);
  await page.reload();await actor.waitFor();await page.waitForFunction(()=>document.querySelector('.atlas-march-party[data-march-skin="phoenix"]')?.classList.contains('is-skinned'));
  await page.setViewportSize({width:390,height:844});await page.screenshot({path:path.join(output,'phoenix-marching-phone.png')});
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.locator('.world-march-row').first().click();await page.waitForTimeout(1000);
   assert(await page.locator('.world-march-card').isVisible());
   const reachable=await page.locator('.world-march-card button').evaluateAll(buttons=>buttons.filter(b=>!b.hidden).map(b=>{const r=b.getBoundingClientRect();return {name:b.dataset.marchCommand,inside:r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight,hit:b.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))}}));assert(reachable.every(b=>b.inside&&b.hit),JSON.stringify({width,height,reachable}));
   await page.screenshot({path:path.join(output,`follow-${width}x${height}.png`)});await page.locator('[data-march-command="close"]').click();
  }
  await page.locator('.world-march-row').first().click();await page.locator('[data-march-command="details"]').click();assert.match(await page.locator('.world-march-details').innerText(),/100/);
  const recalled=page.waitForResponse(r=>r.url().endsWith('/api/march/recall')&&r.request().method()==='POST');await page.locator('[data-march-command="recall"]').click();assert.equal((await recalled).status(),200);assert.equal((await read()).marches.find(m=>Number(m.id)===Number(sent.march_id)).state,'returning');
  assert.deepEqual(errors,[]);console.log(JSON.stringify({status:'PASS',checks:['synthetic account only','purchase/claim/equip','exact 1.05 speed preview','dispatch agrees with ETA','actual themed world march loads and moves','equip change preserves active appearance and timestamps after reload','phone render'],errors,output}));
 }catch(e){await page.screenshot({path:path.join(output,'failure.png')});throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
