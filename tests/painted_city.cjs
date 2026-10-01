const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const pw=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const browser=await pw.chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'msedge'});
 try{
  const page=await browser.newPage();
  await page.setContent('<style>html,body{margin:0}#host{position:absolute;inset:0}</style><main id="host"></main>');
  await page.addStyleTag({path:path.join(root,'assets/css/village-theme.css')});
  await page.addScriptTag({path:path.join(root,'assets/js/city-painted.js')});
  await page.evaluate(async()=>{
   window.ConquerCastleSkins={ids:['forest','fire'],get:id=>({id:['forest','fire'].includes(id)?id:'default'}),image:(base,id)=>base+'/assets/art/map/castle-'+id+'.png',motionImage:(base,id)=>id==='fire'?base+'/assets/art/map/castle-missing.webp':base+'/assets/art/map/castle-'+id+'.webp'};
   window.fixture={host:document.querySelector('#host'),base:'http://localhost/conquer',state:{city:{city_skin:'default'},buildings:{castle:{level:14}},troop_defs:[],troop_queue:[]},labels:{castle:'Burg'},countdown:()=>'<time>1:47:54</time>'};
   ConquerPaintedCity.render(fixture);
   await new Promise(requestAnimationFrame);
  });
  assert.equal(await page.locator('.painted-building-sprite').count(),15,'Separate approved-style sprites');
  assert.match(await page.locator('.painted-village-scene>img').getAttribute('src'),/village-layered-v2\/runtime\/terrain\.webp$/);
  const castle=page.locator('[data-id="castle"] .painted-building-sprite');
  await page.evaluate(()=>{fixture.citySkin='fire';ConquerPaintedCity.render(fixture);});
  assert.equal(await page.locator('[data-id="castle"]').getAttribute('data-castle-skin'),'fire');
  await page.waitForFunction(()=>{const image=document.querySelector('[data-id="castle"] .painted-building-sprite');return image.complete&&image.naturalWidth>0&&image.getAttribute('src').includes('castle-fire.png');});
  assert.match(await castle.getAttribute('src'),/assets\/art\/map\/castle-fire\.png\?village=3$/,'A failed animated skin falls back to its visible PNG');
  assert.equal(await castle.evaluate(image=>getComputedStyle(image).opacity),'1','Equipped village skin remains visible after its image load event');
  assert.equal(await page.locator('[data-id="castle"] .painted-motion').count(),0,'The default castle animation cannot hide an equipped skin');
  await page.evaluate(()=>{document.body.classList.add('reduced-motion');ConquerCastleSkins.syncMotion=()=>{};fixture.citySkin='forest';ConquerPaintedCity.render(fixture);});
  assert.match(await castle.getAttribute('src'),/assets\/art\/map\/castle-forest\.png\?village=3$/,'Reduced motion uses the still skin');
  await page.evaluate(()=>{document.body.classList.remove('reduced-motion');fixture.citySkin='default';ConquerPaintedCity.render(fixture);});
  assert.match(await castle.getAttribute('src'),/village-layered-v2\/runtime\/castle_rounded\.webp$/,'Default skin restores the authored village castle');
  await page.evaluate(()=>{document.body.classList.add('reduced-motion');fixture.citySkin='phoenix';ConquerPaintedCity.render(fixture);});
  await page.waitForFunction(()=>{const image=document.querySelector('[data-id="castle"] .painted-building-sprite');return image.complete&&image.naturalWidth>0&&image.getAttribute('src').includes('castle-phoenix.png');});
  assert.match(await castle.getAttribute('src'),/assets\/art\/map\/castle-phoenix\.png\?village=3$/,'A saved legacy skin remains visible even when absent from the current catalog');
  assert.equal(await page.locator('[data-id="castle"]').getAttribute('data-castle-skin'),'phoenix');
  await page.evaluate(()=>{document.body.classList.remove('reduced-motion');fixture.citySkin='default';ConquerPaintedCity.render(fixture);});
  await page.evaluate(()=>{fixture.state.buildings.academy={level:1};ConquerPaintedCity.render(fixture);});
  assert(await page.locator('[data-id="academy"]').evaluate(e=>e.classList.contains('painted-village-building')&&!e.classList.contains('is-empty')));
  await page.evaluate(()=>{fixture.state.buildings.archery_range={level:3};fixture.state.troop_queue=[{id:7,troop_code:11,barrack_slot:2,count:25,finishes_at:'2030-01-01 12:00:00'}];ConquerPaintedCity.render(fixture);});
  const trainingStatus=page.locator('[data-id="archery_range"] .painted-build-status');
  assert.equal(await trainingStatus.isVisible(),true,'Running training is visible above its painted building');
  assert.match(await trainingStatus.innerText(),/Ausbildung\s+25 Truppen\s+Restzeit\s+1:47:54/,'Training badge separates troop count and remaining time');
  assert.equal(await trainingStatus.evaluate(element=>getComputedStyle(element).backgroundColor),'rgb(92, 66, 112)','Training badge uses the high-contrast village purple');
  assert.equal(await page.locator('[data-id="archery_range"] .painted-scaffold').isVisible(),false,'Training does not show a construction scaffold');
  await page.evaluate(()=>{fixture.state.build_queue=[{id:8,building_code:'academy',level_to:2,finishes_at:'2030-01-01 12:00:00'}];ConquerPaintedCity.render(fixture);});
  const buildStatus=page.locator('[data-id="academy"] .painted-build-status');
  assert.equal(await buildStatus.isVisible(),true,'Running construction is visible above its painted building');
  assert.match(await buildStatus.innerText(),/Ausbau\s+Stufe 2\s+Restzeit\s+1:47:54/,'Construction badge separates target level and remaining time');
  assert.equal(await page.locator('[data-id="academy"] .painted-scaffold').isVisible(),true,'Construction keeps its scaffold');
  await page.waitForFunction(()=>[...document.querySelectorAll('.painted-village img')].every(i=>i.complete&&i.naturalWidth>0));
  for(const size of [{width:1280,height:720},{width:390,height:844},{width:844,height:390},{width:320,height:700}]){
   await page.setViewportSize(size);
   await page.evaluate(()=>{const s=document.querySelector('.painted-village-scroll');s.scrollLeft=100;s.scrollTop=0;window.buildingClicks=0;document.querySelector('#host').onclick=e=>{if(e.target.closest('[data-action="building"]'))window.buildingClicks++;};});
   const before=await page.locator('.painted-village-scroll').evaluate(s=>s.scrollLeft);
   await page.mouse.move(size.width/2,180);await page.mouse.down();
   await page.mouse.move(size.width/2-70,130,{steps:10});await page.mouse.up();
   assert.equal(await page.locator('.painted-village-scroll').evaluate(s=>s.scrollLeft),before+70,'Mouse drag pans horizontally');
   assert.equal(await page.locator('.painted-village-scroll').evaluate(s=>s.scrollTop),50,'Mouse drag pans vertically');
   assert.equal(await page.evaluate(()=>buildingClicks),0,'Dragging does not activate a building');
   assert.equal(await page.locator('.painted-village-building').count(),16);
   assert.equal(await page.locator('[data-id="castle"]').getAttribute('aria-label'),'Burg · Stufe 14');
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No document overflow');
   for(const button of await page.locator('.painted-village-building').all()){
    await button.click();
    assert(await page.locator('.painted-building-banner').isVisible(),'Selection banner opens: '+await button.getAttribute('data-id')+' '+size.width);
    assert.equal(await page.locator('.painted-building-banner strong').textContent(),await button.getAttribute('data-name'));
    await page.locator('.painted-selection-close').click();
   }
   assert.equal(await page.evaluate(()=>buildingClicks),0,'Selection does not open upgrade dialog immediately');
   assert(await page.evaluate(()=>{const s=document.querySelector('.painted-village-scroll');s.scrollLeft=120;const before=s.scrollLeft;ConquerPaintedCity.render(fixture);return s.scrollLeft===before;}),'Refresh preserves pan position');
  }
  console.log('Painted village: 16 building targets checked in four viewport sizes; refresh preserves position.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
