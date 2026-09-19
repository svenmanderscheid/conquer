'use strict';
// Requires an isolated tools/preview-feature-fixture.php --port=19348 session.
const {chromium}=require('playwright'),assert=require('assert/strict'),path=require('path'),fs=require('fs');
const base=process.env.CHARACTER_APP_BASE||'http://127.0.0.1:19348',out=path.resolve(__dirname,'../artifacts/character-family');
fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
 const page=await browser.newPage({viewport:{width:1280,height:800}}),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(120000);
 await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
 await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);await page.goto(base+'/city#city');
 await page.locator('#city-frame').waitFor();const frame=await page.locator('#city-frame').contentFrame();await frame.locator('#world canvas').waitFor();
 const actual=page.frames().find(f=>f.url().includes('/city/3d'));await actual.waitForFunction(()=>window.conquer3D?.getState().ready);
 await page.screenshot({path:path.join(out,'app-city.png')});
 await actual.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'farm'}})));
 for(let i=0;i<3;i++)await frame.locator('#zoomIn').click();await page.screenshot({path:path.join(out,'app-farm-close.png')});
 for(const [width,height]of [[1280,800],[390,844],[844,390]]){
  await page.setViewportSize({width,height});await page.waitForTimeout(350);
  for(const sel of ['.realm-hud','#resource-bar','.realm-nav','.city-footer'])assert.equal(await frame.locator(sel).isVisible(),false,'no duplicate '+sel);
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'app fits viewport');
  await page.screenshot({path:path.join(out,`app-${width}.png`)});
 }
 assert.deepEqual(errors,[]);console.log('PASS real /city#city, city overview, close farm, desktop/mobile/landscape and no duplicate HUD or browser errors');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
