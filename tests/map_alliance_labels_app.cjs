'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Real app with the disposable --gathering preview; membership changes are read-only response fixtures.
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const base=process.env.ALLIANCE_LABELS_FIXTURE_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/map-alliance-labels');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'msedge'}),errors=[];
 const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});
 try{
  page.on('pageerror',e=>errors.push(e.message));
  let membership='original';
  await page.route('**/api/game/state**',async route=>{
   const response=await route.fetch(),body=await response.json();
   if(body.data){
    const state=body.data,home=state.city;
    state.players=[
     {id:3,username:'AlliedFarmer',coord_x:+home.coord_x+6,coord_y:+home.coord_y,castle_level:12,city_skin:'fire',name_frame:'forest',alliance_id:membership==='target-left'?null:'1',alliance_tag:membership==='target-left'?null:'HF'},
     {id:4,username:'Andere Allianz',coord_x:+home.coord_x-6,coord_y:+home.coord_y,castle_level:21,city_skin:'water',name_frame:'water',alliance_id:2,alliance_tag:'HF'},
     {id:2,username:'Ohne Bündnis',coord_x:+home.coord_x,coord_y:+home.coord_y+6,castle_level:6,city_skin:'wind',name_frame:'wind',alliance_id:null,alliance_tag:null}
    ];
   }
   await route.fulfill({response,json:body});
  });
  await page.route('**/api/kingdom/state',async route=>{
   const response=await route.fetch(),body=await response.json();
   if(body.data&&membership==='self-left')body.data.alliance=null;
   await route.fulfill({response,json:body});
  });
  await page.goto(base);await page.locator('[name="identifier"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action="/auth/local"] button[type="submit"]').click()]);
  await page.goto(base+'/city#world');await page.waitForSelector('[data-alliance-relation="ally"]');
  const collapse=page.locator('.world-march-heading[aria-expanded="true"]');if(await collapse.count())await collapse.click();
  const marker=id=>page.locator(`[data-atlas-target="${id}"]`);
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   for(const [id,relation,text,status] of [['home','own','[HF] PreviewPlayer','[HF] · Deine Burg'],['players:3','ally','[HF] AlliedFarmer','[HF] · Deine Allianz'],['players:4','other','[HF] Andere Allianz','[HF] · Andere Allianz'],['players:2','none','Ohne Bündnis','Ohne Allianz']]){
    const node=marker(id),label=node.locator('.name-frame-label'),coords=await node.evaluate(n=>({x:+n.dataset.x,y:+n.dataset.y}));
    await page.evaluate(p=>ConquerWorld.focus(p.x,p.y+(innerWidth<=420?2:innerHeight<=400?1:0)),coords);
    assert.equal(await node.getAttribute('data-alliance-relation'),relation);
    assert.equal(await label.textContent(),text);assert.equal(await label.getAttribute('title'),text);assert((await node.getAttribute('aria-label')).includes(status));
    assert.equal(await node.locator('.atlas-city-alliance').count(),0,'No second membership row beneath the player name');
    assert.equal(await label.locator('.atlas-city-alliance-tag').count(),relation==='none'?0:1);
    const layout=await label.evaluate(n=>{const r=n.getBoundingClientRect(),parent=n.parentElement.getBoundingClientRect(),s=getComputedStyle(n);return{left:r.left,right:r.right,top:r.top,bottom:r.bottom,parentTop:parent.top,parentBottom:parent.bottom,height:r.height,whiteSpace:s.whiteSpace,textOverflow:s.textOverflow};});
    assert(layout.left>=0&&layout.right<=width,JSON.stringify({width,id,layout}));assert(layout.height>=10,JSON.stringify({width,id,layout}));
    assert(layout.top>=layout.parentTop&&layout.bottom<=layout.parentBottom,JSON.stringify({width,id,layout}));assert.equal(layout.whiteSpace,'nowrap');assert.equal(layout.textOverflow,'ellipsis');
    if(relation==='ally'){assert.equal(await label.locator('.atlas-city-alliance-tag').evaluate(n=>getComputedStyle(n).color),'rgb(36, 85, 156)');await page.screenshot({path:path.join(output,`ally-${width}x${height}.png`)});}
   }
   const ally=marker('players:3'),coords=await ally.evaluate(n=>({x:+n.dataset.x,y:+n.dataset.y}));await page.evaluate(p=>ConquerWorld.focus(p.x,p.y),coords);
   const touch=await ally.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.8,.2])for(const fx of [.5,.8,.2]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
   assert(touch,'Focused allied city has a reachable touch point');
   await ally.tap({position:touch});const menu=page.locator('.atlas-target-actions');assert.equal(await menu.locator('.atlas-village-banner>strong').textContent(),'[HF] AlliedFarmer');assert.equal(await menu.locator('.atlas-city-alliance').count(),0);
   const close=menu.locator('[data-atlas="clear"]');assert(await close.isVisible());await page.screenshot({path:path.join(output,`selected-${width}x${height}.png`)});await close.tap();
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no horizontal page overflow');
   console.log(`PASS ${width}x${height}: single-row names, alliance prefixes, same-tag/different-ID, preserved skins and touch selection`);
  }
  assert.equal(await marker('players:3').getAttribute('data-skin'),'fire');assert.equal(await marker('players:3').locator('.atlas-marker-name').getAttribute('data-name-frame'),'forest');
  membership='target-left';await page.waitForFunction(()=>document.querySelector('[data-atlas-target="players:3"]')?.dataset.allianceRelation==='none',{},{timeout:30000});
  assert.equal(await marker('players:3').locator('.name-frame-label').textContent(),'AlliedFarmer');
  membership='self-left';await page.waitForFunction(()=>document.querySelector('[data-atlas-target="players:3"]')?.dataset.allianceRelation==='other',{},{timeout:30000});
  assert.equal(await marker('home').locator('.name-frame-label').textContent(),'PreviewPlayer');assert.equal(await marker('home').locator('.atlas-city-alliance-tag').count(),0);assert.equal(await marker('players:2').getAttribute('data-alliance-relation'),'none');
  assert.deepEqual(errors,[]);console.log('PASS live membership changes, alliance-less players never allied, no browser errors; screenshots: '+output);
 }catch(e){await page.screenshot({path:path.join(output,'failure.png')});console.error(errors);throw e;}
 finally{await page.unrouteAll({behavior:'ignoreErrors'});await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
