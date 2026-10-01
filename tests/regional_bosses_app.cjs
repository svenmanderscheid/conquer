'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Authenticated, disposable preview: tools/preview-feature-fixture.php --regional-bosses --chat --port=18949.
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.REGIONAL_BOSS_FIXTURE_URL||'http://127.0.0.1:18949';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const output=path.resolve(__dirname,'../artifacts/regional-bosses');fs.mkdirSync(output,{recursive:true});
const bosses=[['Wald','Grumwald','grumwald','forest'],['Eis','Frostgrimm','frostgrimm','ice'],['Sand','Sandmaul','sandmaul','sand'],['Lava','Glutramm','glutramm','lava']];
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const errors=[],failures=[];let checks=0;
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800}});
  page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.url().startsWith(base+'/api/')&&r.status()>=400)failures.push(r.status()+' '+r.url());});
  await page.goto(base);await page.goto(new URL('?zugang=login', page.url()).href);
  await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  await page.goto(base+'/city#city');
  await page.locator('.painted-village').waitFor();assert.equal(await page.locator('#city-frame').count(),0);checks++;
  await page.screenshot({path:path.join(output,'city-overview.png')});
  await page.locator('#navigation [data-id="world"]').click();
  const nav=page.locator('#atlas-navigation-panel');
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width,height});
   for(const [zone,name,art,biome] of bosses){
    await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();await nav.waitFor({state:'visible'});
    if(width===1280&&biome==='forest')await page.screenshot({path:path.join(output,'world-overview.png')});
    await nav.getByRole('button',{name:zone,exact:true}).click();
    const target=page.locator('.atlas-marker--monsters').filter({has:page.locator(`img[src$="/monsters/storybook-v2/${art}.png"]`)}).first();
    await target.waitFor({state:'visible'});assert.match(await target.getAttribute('aria-label'),new RegExp(name+'.*Rally'));checks++;
    await target.locator('img').evaluate(i=>i.decode());checks++;
    assert.equal(await target.getAttribute('data-footprint'),'2');checks++;
    assert(await target.evaluate(el=>{const ratio=el.querySelector('img').getBoundingClientRect().width/parseFloat(el.style.getPropertyValue('--tile-size'));return ratio>2&&ratio<3.2;}),'Regional boss rises above its 2x2 footprint without obscuring more than three tiles');checks++;
    if(width===1280){const before=await target.boundingBox(),a=await target.locator('img').evaluate(el=>getComputedStyle(el).transform);await page.waitForTimeout(350);assert.notEqual(await target.locator('img').evaluate(el=>getComputedStyle(el).transform),a,'Boss visibly animates');assert.deepEqual(await target.boundingBox(),before,'Boss hitbox stays fixed during motion');checks+=2;}
    assert.match(await target.getAttribute('aria-label'),/2 mal 2 Felder/);checks++;
    // Move the boss into the usable map centre after the broad region jump.
    // Region centres can leave its northern tile beneath the short-phone HUD.
    await target.evaluate(el=>{const offset=Number(el.dataset.footprint||2)%2===0?.5:0;ConquerWorld.focus(Number(el.dataset.x)+offset,Number(el.dataset.y)+offset);});
    // Click the southeast tile: the server anchor must still identify this boss.
    const hitbox=await target.boundingBox();await target.click({position:{x:hitbox.width*(width>=1000?.75:.5),y:hitbox.height*(width>=1000?.75:.5)}});
    const dialog=page.locator('#game-dialog');await dialog.waitFor({state:'visible'});
    assert(await dialog.locator('.is-monster-rally').count());assert((await dialog.textContent()).includes(name));checks+=2;
    const targetTab=dialog.locator('[data-action="march-view"][data-id="target"]');if(await targetTab.isVisible())await targetTab.click();
    const portrait=dialog.locator(`img[src$="/monsters/storybook-v2/${art}.png"]`);assert(await portrait.isVisible());await portrait.evaluate(i=>i.decode());checks++;
    const bounds=await dialog.boundingBox();assert(bounds.x>=-1&&bounds.y>=-1&&bounds.x+bounds.width<=width+1&&bounds.y+bounds.height<=height+1,'Rally dialog fits viewport');checks++;
    await page.screenshot({path:path.join(output,`${art}-${width}x${height}-rally.png`)});
    await dialog.locator('.dialog-close:visible,.mobile-page-back:visible').first().click();await dialog.waitFor({state:'hidden'});
    console.log(`PASS ${width}x${height}: ${name}, loaded map art and rally preview`);
   }
  }
  await page.setViewportSize({width:1280,height:800});
  await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();await nav.getByRole('button',{name:'Wald',exact:true}).click();
  for(const kind of ['farm','lumber','quarry','gold','crystal']){
   const site=page.locator(`.atlas-marker--nodes[data-painted="${kind}"]`).first();await page.evaluate(({x,y})=>ConquerWorld.focus(Number(x),Number(y)),await site.evaluate(el=>({x:el.dataset.x,y:el.dataset.y})));await site.waitFor({state:'visible'});await site.locator('img').evaluate(el=>el.decode());
   assert(await site.locator('img').evaluate(el=>{const r=el.getBoundingClientRect();return Math.abs(r.width-r.height)<1;}),'Painted resource artwork retains square render bounds');checks++;
   assert.equal(await site.getAttribute('data-footprint'),'1');assert((await site.locator('img').getAttribute('src')).includes('/fantasy-village-v1/'));checks+=2;
   const canvas=site.locator('canvas[data-working="false"]');await canvas.waitFor();const pixels=await canvas.evaluate(el=>el.toDataURL());await page.waitForTimeout(250);assert.equal(await canvas.evaluate(el=>el.toDataURL()),pixels,'Free '+kind+' remains calm until workers arrive');checks++;
   await page.screenshot({path:path.join(output,kind+'-resource.png')});const reachable=await site.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.8,.2,.95,.05])for(const fx of [.5,.8,.2,.95,.05]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});assert(reachable,'Resource remains reachable by touch: '+kind);await site.click({position:reachable});await page.locator('#game-dialog .march-command.is-gather').waitFor();assert(await page.locator('#march-confirm').isVisible());checks++;
   await page.locator('#game-dialog .dialog-close:visible,#game-dialog .mobile-page-back:visible').first().click();await page.locator('#game-dialog').waitFor({state:'hidden'});
  }
  await page.keyboard.press('Escape');
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(300);
  await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();await nav.getByRole('button',{name:'Wald',exact:true}).click();
  const stillBoss=page.locator('.atlas-marker--monsters.is-regional-boss:not([hidden])').first();assert.equal(await stillBoss.locator('img').evaluate(el=>getComputedStyle(el).animationName),'none');checks++;
  assert(await page.locator('.atlas-marker--nodes[data-painted] img').evaluateAll(els=>els.every(el=>el.src.includes('.png'))));checks++;
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);checks+=2;
  console.log(`PASS ${checks} regional boss and painted city checks. Screenshots: ${output}`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
