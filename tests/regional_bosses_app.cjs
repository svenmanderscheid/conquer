'use strict';
// Authenticated, disposable preview: tools/preview-feature-fixture.php --regional-bosses --chat --port=18949.
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.REGIONAL_BOSS_FIXTURE_URL||'http://127.0.0.1:18949';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const output=path.resolve(__dirname,'../artifacts/regional-bosses');fs.mkdirSync(output,{recursive:true});
const bosses=[['Wald','Grumwald','grumwald','forest'],['Eis','Frostgrimm','frostgrimm','ice'],['Sand','Sandmaul','sandmaul','sand'],['Lava','Glutramm','glutramm','lava']];
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome',args:['--use-angle=swiftshader','--enable-unsafe-swiftshader']});
 const errors=[],failures=[];let checks=0;
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800}});
  page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.url().startsWith(base+'/api/')&&r.status()>=400)failures.push(r.status()+' '+r.url());});
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  await page.goto(base+'/city#city');
  const frame=page.frameLocator('#city-frame');await frame.locator('#world canvas').waitFor();
  const cityFrame=page.frames().find(f=>f.url().includes('/city/3d'));
  await cityFrame.waitForFunction(()=>window.conquer3D?.getState().ready,{},{timeout:120000});
  for(const selector of ['.realm-hud','#resource-bar','.realm-nav','.city-footer']){assert.equal(await frame.locator(selector).isVisible(),false,'No duplicate city HUD: '+selector);checks++;}
  await page.screenshot({path:path.join(output,'city-overview.png')});
  const wheel=await cityFrame.evaluate(()=>conquer3D.getState().wheelAngle);
  await cityFrame.waitForFunction(angle=>conquer3D.getState().wheelAngle!==angle,wheel);checks++;
  await cityFrame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'lumber_camp'}})));
  await frame.locator('#zoomIn').click();await page.screenshot({path:path.join(output,'city-close.png')});
  await page.locator('#navigation [data-id="world"]').click();
  const nav=page.locator('#atlas-navigation-panel');
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width,height});
   for(const [zone,name,art,biome] of bosses){
    await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();await nav.waitFor({state:'visible'});
    if(width===1280&&biome==='forest')await page.screenshot({path:path.join(output,'world-overview.png')});
    await nav.getByRole('button',{name:zone,exact:true}).click();
    const target=page.locator('.atlas-marker--monsters').filter({has:page.locator(`img[src$="/monsters/${art}.png"]`)}).first();
    await target.waitFor({state:'visible'});assert.match(await target.getAttribute('aria-label'),new RegExp(name+'.*Rally'));checks++;
    await target.locator('img').evaluate(i=>i.decode());checks++;
    assert.equal(await target.getAttribute('data-footprint'),'2');checks++;
    assert(await target.evaluate(el=>el.querySelector('img').getBoundingClientRect().width>parseFloat(el.style.getPropertyValue('--tile-size'))*3.5),'Regional boss silhouette exceeds three tiles');checks++;
    if(width===1280){const before=await target.boundingBox(),a=await target.locator('img').evaluate(el=>getComputedStyle(el).transform);await page.waitForTimeout(350);assert.notEqual(await target.locator('img').evaluate(el=>getComputedStyle(el).transform),a,'Boss visibly animates');assert.deepEqual(await target.boundingBox(),before,'Boss hitbox stays fixed during motion');checks+=2;}
    assert.match(await target.getAttribute('aria-label'),/2 mal 2 Felder/);checks++;
    // Click the southeast tile: the server anchor must still identify this boss.
    const hitbox=await target.boundingBox();await target.click({position:{x:hitbox.width*(width>=1000?.75:.5),y:hitbox.height*(width>=1000?.75:.5)}});
    const focus=await page.locator('.atlas-cell-focus').boundingBox();assert(Math.abs(focus.width-hitbox.width)<1&&Math.abs(focus.height-hitbox.height)<1,'Selection covers all four tiles');checks++;
    await page.screenshot({path:path.join(output,`${art}-${width}x${height}-map.png`)});
    const attack=page.locator('.atlas-target-actions [data-action="expedition"]');assert(await attack.isVisible());checks++;
    if(width===320){assert(await attack.evaluate(el=>{const label=el.lastElementChild,r=label.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),'Rally label remains clear of the chat');checks++;}
    await attack.click();const dialog=page.locator('#game-dialog');await dialog.waitFor({state:'visible'});
    assert(await dialog.locator('.is-monster-rally').count());assert((await dialog.textContent()).includes(name));checks+=2;
    const targetTab=dialog.locator('[data-action="march-view"][data-id="target"]');if(await targetTab.isVisible())await targetTab.click();
    const portrait=dialog.locator(`img[src$="/monsters/${art}.png"]`);assert(await portrait.isVisible());await portrait.evaluate(i=>i.decode());checks++;
    const bounds=await dialog.boundingBox();assert(bounds.x>=-1&&bounds.y>=-1&&bounds.x+bounds.width<=width+1&&bounds.y+bounds.height<=height+1,'Rally dialog fits viewport');checks++;
    await page.screenshot({path:path.join(output,`${art}-${width}x${height}-rally.png`)});
    await dialog.getByRole('button',{name:'Fenster schließen',exact:true}).click();
    console.log(`PASS ${width}x${height}: ${name}, loaded map art and rally preview`);
   }
  }
  await page.setViewportSize({width:1280,height:800});
  await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();await nav.getByRole('button',{name:'Wald',exact:true}).click();
  for(const kind of ['farm','lumber','quarry','gold','crystal']){
   const site=page.locator('.atlas-marker--nodes').filter({has:page.locator('img[data-life-kind="'+kind+'"]')}).first();await page.evaluate(({x,y})=>ConquerWorld.focus(Number(x),Number(y)),await site.evaluate(el=>({x:el.dataset.x,y:el.dataset.y})));await site.waitFor({state:'visible'});await site.click();await site.locator('img').evaluate(el=>el.decode());
   assert(await site.locator('img').evaluate(el=>{const r=el.getBoundingClientRect();return Math.abs(r.width-r.height)<1;}),'Resource artwork retains square render bounds');checks++;
   assert.equal(await site.getAttribute('data-footprint'),'1');assert((await site.locator('img').getAttribute('src')).includes('.webp'));checks+=2;
   const a=await site.screenshot();let moved=false;for(let attempt=0;attempt<4&&!moved;attempt++){await page.waitForTimeout(400);moved=!a.equals(await site.screenshot());}assert(moved,kind+' workplace visibly animates');checks++;
   await page.screenshot({path:path.join(output,kind+'-resource.png')});assert(await page.locator('.atlas-target-actions .is-gather').isVisible());checks++;
  }
  await page.keyboard.press('Escape');
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(300);
  const stillBoss=page.locator('.atlas-marker--monsters.is-regional-boss:not([hidden])').first();assert.equal(await stillBoss.locator('img').evaluate(el=>getComputedStyle(el).animationName),'none');checks++;
  assert(await page.locator('.atlas-marker--nodes img[data-life-kind]').evaluateAll(els=>els.every(el=>el.src.includes('.png'))));checks++;
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);checks+=2;
  console.log(`PASS ${checks} regional boss and embedded city checks. Screenshots: ${output}`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
