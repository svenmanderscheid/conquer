'use strict';
// Real app on a disposable --regional-bosses --chat preview database.
const assert=require('assert'),fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.REGIONAL_BOSS_FIXTURE_URL||'http://127.0.0.1:18959';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/encounter-cards-20260913');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome',args:['--use-angle=swiftshader','--enable-unsafe-swiftshader']});
 const errors=[];
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800}});
  const clickTarget=async target=>{
   const position=await target.evaluate(el=>{const r=el.getBoundingClientRect();for(const [fx,fy] of [[.5,.5],[.5,.9],[.1,.9],[.9,.9],[.1,.1],[.9,.1]]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
   assert(position,'A portion of the one-tile target is reachable by touch');await target.click({position});
  };
  page.on('pageerror',e=>errors.push(e.stack||e.message));
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  const sceneSwitch=page.locator('#navigation .hud-scene-switch');await sceneSwitch.waitFor();if(await sceneSwitch.getAttribute('data-id')==='world')await sceneSwitch.click();
  await page.locator('.atlas-marker--nodes').first().waitFor({state:'attached'});
  const kinds=process.env.ENCOUNTER_KINDS?JSON.parse(process.env.ENCOUNTER_KINDS):['farm','lumber','quarry','gold','crystal','orc'];
  for(const [width,height] of (process.env.ENCOUNTER_VIEWPORTS?JSON.parse(process.env.ENCOUNTER_VIEWPORTS):[[1280,800],[390,844],[320,568],[844,390],[568,320]])){
   await page.setViewportSize({width,height});
   await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();
   await page.locator('#atlas-navigation-panel').getByRole('button',{name:'Wald',exact:true}).click();
   for(const kind of kinds){
    const target=page.locator('.atlas-marker').filter({has:page.locator(`img[data-life-kind="${kind}"]`)}).first();
    await target.waitFor({state:'attached'});
    await page.evaluate(({x,y})=>ConquerWorld.focus(+x,+y),await target.evaluate(el=>({x:el.dataset.x,y:el.dataset.y})));
    await clickTarget(target);
    const card=page.locator('.atlas-target-actions.is-encounter');
    if(kind==='orc'){
     const dialog=page.locator('#game-dialog');await dialog.waitFor({state:'visible'});
     assert.equal(await card.isVisible(),false,'Monster opens its attack window without an intermediate info card');
     assert.equal(await dialog.locator('.march-command.is-attack').count(),1);
     assert((await dialog.textContent()).includes('Monsterangriff'));
     assert((await dialog.locator('.march-target').textContent()).includes('Lebenspunkte'));
     await page.screenshot({path:path.join(output,`${width}x${height}-orc-attack.png`)});
     await dialog.getByRole('button',{name:'Fenster schließen',exact:true}).click();
     const monster=await target.evaluate(el=>({id:el.dataset.atlasTarget.split(':')[1],x:el.dataset.x,y:el.dataset.y}));
     await page.evaluate(({id,x,y})=>ConquerWorld.locate(+x,+y,['monsters'],id),monster);
     await card.waitFor({state:'visible'});assert.equal(await dialog.isVisible(),false,'Programmatic target reveal does not launch an attack');
     await page.keyboard.press('Escape');await card.waitFor({state:'hidden'});
     continue;
    }
    await card.waitFor({state:'visible'});
    assert.equal(await target.getAttribute('data-footprint'),'1');
    assert((await card.textContent()).includes('1 × 1 Feld'));
    const bounds=await card.boundingBox();assert(bounds.x>=0&&bounds.y>=0&&bounds.x+bounds.width<=width+1&&bounds.y+bounds.height<=height+1,'Card fits '+kind+' '+width+'x'+height);
    assert(await card.evaluate(el=>{const r=el.getBoundingClientRect();return [...document.querySelectorAll('.topbar,.world-chat,#navigation button,#hud-menu,.hud-edge-tools button')].every(hud=>{const style=getComputedStyle(hud),b=hud.getBoundingClientRect();if(style.visibility==='hidden'||style.display==='none'||Number(style.opacity)===0)return true;return Math.max(0,Math.min(r.right,b.right)-Math.max(r.left,b.left))*Math.max(0,Math.min(r.bottom,b.bottom)-Math.max(r.top,b.top))<1;});}),'The entire card clears HUD and chat '+width+'x'+height);
    for(const label of ['Zielmenü schließen',kind==='orc'?'Angreifen':'Sammeln','Details','Koordinaten teilen']){
     const button=card.getByRole('button',{name:label,exact:true});
     assert(await button.evaluate(el=>{const r=el.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),label+' is not covered '+kind+' '+width+'x'+height);
    }
    assert.equal(await card.locator('progress').count(),1);
    assert(await card.locator('progress').evaluate(el=>el.max>=el.value&&el.max>1),'Server HP/stock maximum');
    await card.locator('.atlas-encounter-summary>img').evaluate(i=>i.decode());
    for(const selector of ['.atlas-encounter-summary','.atlas-encounter-stock','.atlas-encounter-status'])assert(await card.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return r.height===0||el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),selector+' stays readable');
    await page.screenshot({path:path.join(output,`${width}x${height}-${kind}.png`)});
    await card.getByRole('button',{name:'Sammeln',exact:true}).click();
    const dialog=page.locator('#game-dialog');await dialog.waitFor({state:'visible'});
    await dialog.getByRole('button',{name:'Fenster schließen',exact:true}).click();
    await page.evaluate(({x,y})=>ConquerWorld.focus(+x,+y),await target.evaluate(el=>({x:el.dataset.x,y:el.dataset.y})));await clickTarget(target);await page.keyboard.press('Escape');assert(await card.isHidden());
    await page.waitForFunction(()=>!history.state?.conquerMapTarget);
    await page.evaluate(({x,y})=>ConquerWorld.focus(+x,+y),await target.evaluate(el=>({x:el.dataset.x,y:el.dataset.y})));await clickTarget(target);await page.goBack();await card.waitFor({state:'hidden'});assert((await page.url()).endsWith('#world'),'Back closes the card and keeps the world open');
   }
   console.log(`PASS ${width}x${height}: ${kinds.length} one-tile targets, HUD/chat clearance, actions, march dialogs and Escape`);
  }
  assert.deepEqual(errors,[]);console.log('Screenshots '+output);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
