'use strict';
// Real map against tools/preview-feature-fixture.php --monster-health.
const assert=require('assert/strict'),fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base=process.env.MONSTER_REPORT_URL||'http://127.0.0.1:18976';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required');
const output=path.resolve('artifacts/monster-health-review');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];
  page.on('pageerror',e=>{errors.push(e.stack||e.message);console.error(e.stack||e.message);});page.setDefaultTimeout(25000);
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  const sceneSwitch=page.locator('#navigation .hud-scene-switch');await sceneSwitch.waitFor();if(await sceneSwitch.getAttribute('data-id')==='world')await sceneSwitch.click();
  const data=await page.evaluate(async()=>(await(await fetch('/api/game/state')).json()).data);
  const half=data.monsters.find(m=>+m.hp_current/+m.hp_max===.5),healthy=data.monsters.find(m=>+m.hp_current===+m.hp_max),low=data.monsters.find(m=>+m.hp_current===1),boss=data.monsters.find(m=>m.definition.type==='rally'&&+m.hp_current/+m.hp_max===.25);
  assert(half&&healthy&&low&&boss,'Four health fixtures in world payload');
  for(const m of [half,healthy,low,boss])assert.equal(+m.hp_max,Math.round(m.definition.stats.hp*m.definition.amount));
  const search=await page.evaluate(async()=>(await(await fetch('/api/map/search?category=solo&level=1')).json()).data.target.data);
  assert.equal(+search.hp_max,Math.round(search.definition.stats.hp*search.definition.amount),'Search carries the same complete HP pool');
  const target=m=>page.locator(`[data-atlas-target="monsters:${m.id}"]`);
  await target(half).waitFor({state:'attached'});
  assert.equal(await target(healthy).locator('.atlas-monster-health').count(),0,'Full-health targets stay uncluttered');
  const check=async m=>{
   const health=target(m).locator('.atlas-monster-health');
   assert.equal(await health.locator('.atlas-monster-hp-values').innerText(),`${(+m.hp_current).toLocaleString('de-DE')} / ${(+m.hp_max).toLocaleString('de-DE')}`);
   const ratio=await health.locator('.atlas-monster-hp-track>span').evaluate(el=>el.getBoundingClientRect().width/el.parentElement.getBoundingClientRect().width);
   assert(Math.abs(ratio-m.hp_current/m.hp_max)<.002,'Health fill represents the actual ratio');
   assert.match(await target(m).getAttribute('aria-label'),/Lebenspunkte .* von /);
  };
  for(const m of [half,low,boss]){
   await page.evaluate(({x,y})=>ConquerWorld.focus(+x,+y),{x:m.coord_x,y:m.coord_y});await page.waitForTimeout(180);await check(m);
  }
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   for(const m of [half,boss]){
    await page.evaluate(({x,y})=>ConquerWorld.focus(+x,+y),{x:m.coord_x,y:m.coord_y});
    await page.waitForTimeout(180);
    const marker=target(m),health=marker.locator('.atlas-monster-health');await health.waitFor({state:'visible'});
    const b=await health.boundingBox();assert(b.x>=0&&b.y>=0&&b.x+b.width<=width+1&&b.y+b.height<=height+1,'Health is readable in '+width+'x'+height);
    await page.screenshot({path:path.join(output,`${width}x${height}-${m===boss?'boss':'monster'}.png`)});
    const position=await marker.evaluate(el=>{const r=el.getBoundingClientRect();for(const [fx,fy] of [[.5,.5],[.5,.9],[.1,.9],[.9,.9],[.1,.1],[.9,.1]]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
    assert(position,'Monster remains reachable by touch');await marker.click({position});
    const card=page.locator('.atlas-target-actions');await card.waitFor({state:'visible'});
    assert.deepEqual(await card.locator('progress').evaluate(el=>[el.value,el.max]),[+m.hp_current,+m.hp_max],'Target menu uses the same current/maximum HP');
    await page.keyboard.press('Escape');await card.waitFor({state:'hidden'});
   }
   console.log(`PASS health and touch targets ${width}x${height}`);
  }
  await page.setViewportSize({width:390,height:844});
  await page.evaluate(({x,y})=>ConquerWorld.focus(+x,+y),{x:half.coord_x,y:half.coord_y});
  const original=await target(half).elementHandle();let override=Math.round(half.hp_max*.25);
  await page.route('**/api/game/state*',async route=>{const response=await route.fetch(),body=await response.json();body.data.monsters=body.data.monsters.flatMap(m=>+m.id===+half.id?(override===null?[]:[{...m,hp_current:override}]):[m]);await route.fulfill({response,json:body});});
  await page.waitForFunction(({id,hp})=>document.querySelector(`[data-atlas-target="monsters:${id}"] .atlas-monster-health`)?.dataset.current===String(hp),{id:half.id,hp:override});
  assert(await original.evaluate(el=>el.isConnected),'Live HP update reuses the existing target');await check({...half,hp_current:override});
  override=+half.hp_max;await page.waitForFunction(id=>!document.querySelector(`[data-atlas-target="monsters:${id}"] .atlas-monster-health`),half.id);
  assert(await original.evaluate(el=>el.isConnected),'Restored HP removes only the health display');
  override=null;await target(half).waitFor({state:'detached'});
  assert.deepEqual(errors,[],'No browser errors');
  console.log('PASS live HP updates, full-health/defeated cleanup, 1-HP target, server/search maxima. '+output);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
