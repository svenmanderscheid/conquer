'use strict';
// The actual app and a disposable database, with authoritative queue variants
// supplied only at its HTTP boundary. No live player state is read or written.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),phase=process.env.CITY_CONSTRUCTION_PHASE||'after';
const buildingCodes=['castle','academy','treasure_house','hospital','hall_of_alliance','trading_post','storage','watch_tower','stable','archery_range','barrack','farm','lumber_camp','gold_mine','quarry','wall'];
const output=process.env.CITY_CONSTRUCTION_OUTPUT?path.resolve(root,process.env.CITY_CONSTRUCTION_OUTPUT):fs.mkdtempSync(path.join(os.tmpdir(),'conquer-construction-'));
fs.mkdirSync(output,{recursive:true});
let base=process.env.CITY_CONSTRUCTION_FIXTURE_URL;
async function startFixture(){
 const listener=net.createServer();await new Promise(resolve=>listener.listen(0,'127.0.0.1',resolve));const port=listener.address().port;await new Promise(resolve=>listener.close(resolve));base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--chat'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);
 await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timeout);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timeout);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',reject);});return fixture;
}
function animationState(code='farm'){
 const building=document.querySelector('.painted-village-building[data-id="'+code+'"]');
 const rect=node=>{const r=node.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height};};
 const scaffold=building.querySelector('.painted-scaffold');
 return {building:rect(building),status:rect(building.querySelector('.painted-build-status')),scaffold:rect(scaffold),motion:[...scaffold.querySelectorAll('*')].map(node=>{const s=getComputedStyle(node);return {className:node.getAttribute('class')||node.tagName,animation:s.animationName,playState:s.animationPlayState,transform:s.transform,opacity:s.opacity,display:s.display};}).filter(node=>node.animation!=='none')};
}
function assertStatusAnchored(first,second,message){
 // A live countdown can change its text width when a digit changes. Its
 // center and vertical position must remain fixed as the work animates.
 assert(Math.abs(first.x+first.width/2-second.x-second.width/2)<.1,message+' (center)');
 assert.equal(first.y,second.y,message+' (top)');assert.equal(first.height,second.height,message+' (height)');
}
function assertWorksiteStill(first,second,message){
 assert.deepEqual(first.motion,second.motion,message+' (work details)');assert.deepEqual(first.building,second.building,message+' (building)');assert.deepEqual(first.scaffold,second.scaffold,message+' (scaffold)');assertStatusAnchored(first.status,second.status,message+' (live timer)');
}
async function detailMotionSamples(art){
 const details=[...art.querySelectorAll('.painted-construction-detail')],animations=[...new Set(details.flatMap(node=>node.getAnimations({subtree:true})))];
 const original=animations.map(animation=>({animation,time:animation.currentTime,state:animation.playState})),samples=[];
 for(const position of [0,.2,.4,.6,.8]){
  for(const animation of animations){const timing=animation.effect.getTiming();animation.pause();animation.currentTime=Number(timing.delay)+Number(timing.duration)*position;}
  await new Promise(requestAnimationFrame);
  samples.push(details.map(node=>[node,...node.querySelectorAll('*')].map(part=>{const style=getComputedStyle(part);return [style.transform,style.opacity];})));
 }
 for(const {animation,time,state} of original){animation.currentTime=time;if(state==='running')animation.play();}
 return {animationCount:animations.length,samples};
}
(async()=>{
 const fixture=base?null:await startFixture();assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'A disposable preview is required');
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 let page;const results=[];
 try{
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,locale:'en-GB'});page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  const errors=[],missing=[];let queueMode='active';
  page.on('pageerror',error=>errors.push(error.message));page.on('response',response=>{if(response.status()>=400&&new URL(response.url()).pathname.startsWith('/assets/'))missing.push(response.url());});
  await page.route('**/api/game/state*',async route=>{const response=await route.fetch(),json=await response.json();if(json.ok&&queueMode!=='active'){
   const job=json.data.build_queue?.find(row=>row.building_code==='farm');if(job){if(queueMode.startsWith('shape:')){job.building_code=queueMode.slice(6);job.level_to=Number(json.data.buildings[job.building_code]?.level||0)+1;}else{job.finishes_at='2020-01-01 00:00:00';if(queueMode==='processed'){job.is_processed=1;json.data.buildings.farm.level=8;}if(queueMode==='removed')json.data.build_queue=json.data.build_queue.filter(row=>row!==job);}}
  }await route.fulfill({response,json});});
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  // Inspect full task motion in normal quality, then explicitly check light
  // and the automatic profile that a small touch device normally receives.
  await page.evaluate(()=>ConquerGraphicsQuality.set('normal'));
  const building=page.locator('.painted-village-building[data-id="farm"]');
  const ready=async()=>{await page.locator('.painted-village-scene').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));await page.waitForFunction(()=>[...document.querySelectorAll('.painted-village img')].every(node=>node.complete&&node.naturalWidth>0));await page.evaluate(()=>document.fonts.ready);};
  const reveal=async(locator=building)=>{await locator.evaluate(node=>{const scroll=node.closest('.painted-village-scroll'),b=node.getBoundingClientRect(),r=scroll.getBoundingClientRect();scroll.scrollLeft+=b.left+b.width/2-r.left-r.width/2;scroll.scrollTop+=b.top+b.height/2-r.top-r.height/2;});await page.waitForTimeout(100);};
  const closeDialogs=async()=>{for(const selector of ['#game-dialog','#panel-dialog'])if(await page.locator(selector).evaluate(node=>node.open)){await page.locator(selector+' .dialog-close:visible, '+selector+' .panel-close:visible, '+selector+' .mobile-page-back:visible').first().tap();await page.waitForFunction(selector=>!document.querySelector(selector).open,selector);}};
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.goto(base+'/city#city');await page.reload();await ready();
   await page.screenshot({path:path.join(output,`${phase}-${width}x${height}-city.png`)});await reveal();
   const first=await page.evaluate(animationState);let second=first;
   // Include enough of the calm resting phase to avoid relying on a random
   // animation instant. A moving tool must appear within half a work cycle.
   for(let sample=0;sample<6;sample++){await page.waitForTimeout(300);second=await page.evaluate(animationState);if(JSON.stringify(first.motion)!==JSON.stringify(second.motion))break;}
   await page.screenshot({path:path.join(output,`${phase}-${width}x${height}-construction.png`)});results.push({width,height,first,second});
   assert.deepEqual(first.building,second.building,'Construction never shifts its touch target');assertStatusAnchored(first.status,second.status,'The construction label stays anchored');assert.deepEqual(first.scaffold,second.scaffold,'The supporting scaffold stays anchored');
   assert.equal(await building.locator('.painted-scaffold').isVisible(),true,'Active queue visibly indicates building work');
   assert.equal(await page.locator('.painted-village-building[data-id="barrack"] .painted-scaffold').isVisible(),false,'Training does not show construction work');
   if(phase==='before')continue;
   assert(first.motion.length>0,'Work details animate');assert.notDeepEqual(first.motion,second.motion,'The construction pose changes visibly during its work cycle');
   assert.equal(await building.locator('.painted-scaffold').getAttribute('aria-hidden'),'true','Work decoration is excluded from accessibility navigation');
   assert.equal(await building.locator('.painted-scaffold').evaluate(node=>getComputedStyle(node).pointerEvents),'none','Decoration cannot intercept touch');
   await building.tap();await page.locator('.painted-building-menu:not([hidden]) .painted-building-actions').waitFor();assert.match(await building.locator('.painted-building-name').innerText(),/Farm/i,'Tap selects the working building');
   await page.locator('.painted-building-actions [data-action="building"]').tap();await page.locator('#game-dialog[open]').waitFor();
   const paused=await page.evaluate(animationState);assert(paused.motion.every(node=>node.playState==='paused'),'Construction pauses behind its upgrade dialog');
   await page.screenshot({path:path.join(output,`${phase}-${width}x${height}-dialog.png`)});await closeDialogs();
   if(width===1280){
    await building.locator('.painted-scaffold').evaluate(async node=>{for(const animation of node.getAnimations({subtree:true})){const timing=animation.effect.getTiming();animation.pause();animation.currentTime=Number(timing.delay)+Number(timing.duration)*.30;}await new Promise(requestAnimationFrame);});
    await page.screenshot({path:path.join(output,'after-1280x800-impact.png')});await building.locator('.painted-scaffold').screenshot({path:path.join(output,'after-construction-detail.png')});
    await building.locator('.painted-scaffold').evaluate(node=>node.getAnimations({subtree:true}).forEach(animation=>animation.play()));
   }
   console.log(`PASS construction geometry and touch ${width}x${height}`);
  }
  if(phase!=='before'){
   if(process.env.CITY_CONSTRUCTION_VIDEO==='1'){
    const context=await browser.newContext({storageState:await page.context().storageState(),viewport:{width:390,height:844},hasTouch:true,recordVideo:{dir:output,size:{width:390,height:844}}});
    const preview=await context.newPage();await preview.goto(base+'/city#city');await preview.locator('.painted-village-building.is-building .painted-construction-art').waitFor();
    await preview.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
    await preview.locator('.painted-village-building[data-id="farm"]').evaluate(node=>{const scroll=node.closest('.painted-village-scroll'),b=node.getBoundingClientRect(),r=scroll.getBoundingClientRect();scroll.scrollLeft+=b.left+b.width/2-r.left-r.width/2;scroll.scrollTop+=b.top+b.height/2-r.top-r.height/2;});
    await preview.waitForTimeout(7200);const video=preview.video();await context.close();await video.saveAs(path.join(output,'construction-mobile.webm'));const raw=await video.path();if(raw!==path.join(output,'construction-mobile.webm'))fs.unlinkSync(raw);
   }
   await page.setViewportSize({width:390,height:844});await page.goto(base+'/city#city');await page.reload();await ready();await reveal();
   await building.locator('.painted-construction-art').evaluate(node=>window.constructionSiteBeforePoll=node);
   await page.waitForResponse(response=>response.url().includes('/api/game/state')&&response.status()===200);await page.waitForTimeout(200);
   assert.equal(await building.locator('.painted-construction-art').evaluate(node=>node===window.constructionSiteBeforePoll),true,'Polling does not restart an unchanged construction cycle');
   // OS and in-game reduced motion retain a still site. Lightweight graphics
   // keep tool and task transforms, without decorative particles.
   for(const mode of ['os','reduced','light']){
    if(mode==='os')await page.emulateMedia({reducedMotion:'reduce'});
    else await page.evaluate(mode=>mode==='reduced'?document.body.classList.add('reduced-motion'):document.body.dataset.graphicsQuality='light',mode);
    await page.waitForTimeout(100);const first=await page.evaluate(animationState);let second=first;
    for(let sample=0;sample<(mode==='light'?6:1);sample++){await page.waitForTimeout(300);second=await page.evaluate(animationState);if(JSON.stringify(first.motion)!==JSON.stringify(second.motion))break;}
    if(mode==='light'){
     assert(first.motion.some(node=>node.className.includes('painted-construction-detail')),'Light graphics retain the painted work poses');
     assert.notDeepEqual(first.motion,second.motion,'The lightweight worksite continues working');
     assert.equal(await building.locator('.painted-construction-dust:visible,.painted-construction-chip:visible,.painted-construction-particle:visible').count(),0,'Light graphics omit decorative particles');
    }else{assertWorksiteStill(first,second,'Stable construction for '+mode);assert(first.motion.every(node=>node.playState==='paused'),'No work animation runs in '+mode);}
    assert.equal(await building.locator('.painted-scaffold').isVisible(),true,'Construction remains identifiable in '+mode);
    await page.screenshot({path:path.join(output,`${phase}-390x844-${mode}.png`)});
    await page.emulateMedia({reducedMotion:'no-preference'});await page.evaluate(()=>{document.body.classList.remove('reduced-motion');delete document.body.dataset.graphicsQuality;});
   }
   await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'));});const hidden=await page.evaluate(animationState);assert(hidden.motion.every(node=>node.playState==='paused'),'The visibility event pauses construction');await page.evaluate(()=>{delete document.hidden;document.dispatchEvent(new Event('visibilitychange'));});
   await page.locator('#navigation [data-id="world"]').tap();await page.locator('.atlas-viewport').waitFor();const world=await page.evaluate(animationState);assert(world.motion.every(node=>node.playState==='paused'),'Construction pauses while viewing the world');
   await page.locator('#navigation [data-id="city"]').tap();await ready();await reveal();
   for(const mode of ['expired','processed','removed']){
    queueMode=mode;await page.waitForResponse(response=>response.url().includes('/api/game/state')&&response.status()===200);
    await page.waitForFunction(mode=>document.querySelector('.painted-village-building[data-id="farm"]').classList.contains('is-building')===(mode==='expired'),mode);
    const visible=await building.locator('.painted-scaffold').isVisible();assert.equal(visible,mode==='expired','Queue '+mode+' controls construction without remounting the city');
    if(mode!=='expired'){assert.equal(await building.locator('.painted-build-status').isVisible(),false,'No stale timer after '+mode);assert.equal(await building.locator('.painted-scaffold svg').count(),0,'No remaining work SVG after '+mode);}
   }
   const shapes=new Map();
   assert.deepEqual(await page.locator('.painted-village-building').evaluateAll(nodes=>nodes.map(node=>node.dataset.id).sort()),[...buildingCodes].sort(),'The test covers every city building');
   for(const code of buildingCodes){
    await page.setViewportSize({width:390,height:844});
    queueMode='shape:'+code;await page.reload();await ready();const target=page.locator('.painted-village-building[data-id="'+code+'"]');await reveal(target);
    assert.equal(await target.locator('.painted-scaffold').isVisible(),true,'Construction adapts to '+code);assert.equal(await target.locator('.painted-construction-art').count(),1,'One site for '+code);
    assert.equal(await page.locator('.painted-construction-art').count(),1,'Only the authoritative building receives construction: '+code);
    const art=target.locator('.painted-construction-art');assert.equal(await art.getAttribute('data-construction-kind'),code,'Construction identifies its building task');
    await page.waitForFunction(code=>document.querySelector('.painted-village-building[data-id="'+code+'"]').classList.contains('has-construction-art'),code);
    const atlas=art.locator('image');assert.match(await atlas.getAttribute('href'),new RegExp('/city-construction-v1/'+code+'\\.webp\\?v='),'Uses the approved building-specific painted atlas');
    if(code!=='wall')assert.equal(await target.locator('.painted-building-sprite').evaluate(node=>getComputedStyle(node).visibility),'hidden','The original building is hidden, preventing doubled roofs and walls');
    // Compare visible SVG geometry, not ids or variant labels: changing only a
    // data attribute or animation timing must not count as a new building task.
    const shape=await art.evaluate((node,code)=>JSON.stringify([...node.querySelectorAll('path,rect,circle,ellipse,line,polygon,polyline,image,use')].map(part=>[part.tagName,[...part.attributes].filter(attribute=>!['id','class','style','mask','clip-path'].includes(attribute.name)).map(attribute=>[attribute.name,attribute.value])])).replaceAll('painted-construction-'+code,'painted-construction-site'),code);
    assert(!shapes.has(shape),'Distinct work artwork for '+code+' and '+shapes.get(shape));shapes.set(shape,code);
    const detail=await art.evaluate(detailMotionSamples);assert(detail.animationCount>0,'The building-specific work detail animates: '+code);assert(new Set(detail.samples.map(sample=>JSON.stringify(sample))).size>1,'The building-specific work detail changes pose: '+code);
    const first=await page.evaluate(animationState,code);await page.waitForTimeout(150);const second=await page.evaluate(animationState,code);
    assert.deepEqual(first.building,second.building,'Working building touch target stays anchored: '+code);assertStatusAnchored(first.status,second.status,'Building timer stays anchored: '+code);assert.deepEqual(first.scaffold,second.scaffold,'Scaffold stays anchored: '+code);
    assert.equal(await art.getAttribute('aria-hidden'),'true','Work art is decorative: '+code);assert.equal(await art.evaluate(node=>getComputedStyle(node).pointerEvents),'none','Work art does not intercept touch: '+code);
    for(const [width,height] of [[390,844],[844,390]]){
     await page.setViewportSize({width,height});await reveal(target);await page.screenshot({path:path.join(output,`after-${width}x${height}-${code}.png`)});
     if(width===390)await target.locator('.painted-scaffold').screenshot({path:path.join(output,`after-detail-${code}.png`)});
     await target.tap();await page.locator('.painted-building-menu:not([hidden]) .painted-building-actions').waitFor();assert.equal(await target.getAttribute('aria-pressed'),'true',`Working building selects by touch: ${code} ${width}x${height}`);
     await page.locator('.painted-building-actions [data-action="building"]').tap();await page.locator('#game-dialog[open]').waitFor();
     const paused=await page.evaluate(animationState,code);assert(paused.motion.length>0&&paused.motion.every(node=>node.playState==='paused'),`All work pauses behind the building dialog: ${code} ${width}x${height}`);await closeDialogs();
     if(await page.locator('.painted-selection-close').isVisible())await page.locator('.painted-selection-close').tap();
    }
    for(const mode of ['os','reduced','light','auto']){
     if(mode==='os')await page.emulateMedia({reducedMotion:'reduce'});else await page.evaluate(mode=>mode==='reduced'?document.body.classList.add('reduced-motion'):ConquerGraphicsQuality.set(mode),mode);
     await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
     const state=await page.evaluate(animationState,code);
     if(mode==='light'||mode==='auto'){
      assert.equal(await page.locator('body').getAttribute('data-graphics-quality'),'light','The small touch viewport uses the light profile for '+mode);
      assert(state.motion.some(node=>node.className.includes('painted-construction-detail')&&node.playState==='running'),'Light graphics retain the painted work poses: '+code);
      const extra=state.motion.filter(node=>node.className.includes('painted-construction-detail'));assert(extra.length>0&&extra.every(node=>node.playState==='running'),'Light graphics retain the unique work detail for '+mode+': '+code);
      let changed=false;for(let sample=0;sample<8;sample++){await page.waitForTimeout(200);const next=(await page.evaluate(animationState,code)).motion.filter(node=>node.className.includes('painted-construction-detail'));if(JSON.stringify(extra)!==JSON.stringify(next)){changed=true;break;}}
      assert(changed,'Building-specific work moves naturally on mobile for '+mode+': '+code);
      assert.equal(await art.locator('.painted-construction-dust:visible,.painted-construction-chip:visible,.painted-construction-particle:visible').count(),0,'Light graphics omit all work particles: '+code);
     }else{
      assert(state.motion.every(node=>node.playState==='paused'),'All work pauses for '+mode+': '+code);await page.waitForTimeout(100);assertWorksiteStill(state,await page.evaluate(animationState,code),'Reduced worksite remains completely still for '+mode+': '+code);
     }
     assert.equal(await target.locator('.painted-scaffold').isVisible(),true,'Worksite remains readable for '+mode+': '+code);
     await page.emulateMedia({reducedMotion:'no-preference'});await page.evaluate(()=>{document.body.classList.remove('reduced-motion');ConquerGraphicsQuality.set('normal');});
    }
    results.push({code,first,second,detail});console.log('PASS distinct construction, detail motion, mobile touch and motion settings: '+code);
   }
   assert.equal(shapes.size,buildingCodes.length,'Every building has its own work artwork');
   const manifest=JSON.parse(fs.readFileSync(path.join(root,'assets/art/city-construction-v1/manifest.json'),'utf8'));
   assert.equal(new Set(Object.values(manifest.buildings).map(b=>b.sha256)).size,buildingCodes.length,'All sixteen sites have different raster artwork, not renamed copies');
   queueMode='removed';await page.waitForResponse(response=>response.url().includes('/api/game/state')&&response.status()===200);await page.waitForFunction(()=>!document.querySelector('.painted-construction-art'));
   assert.equal(await page.locator('.painted-scaffold svg').count(),0,'Removing the queue cleans every construction SVG');assert.equal(await page.locator('.painted-build-status[data-queue^="build:"]').count(),0,'Removing the queue clears every construction timer while training may continue');
   assert.equal(await page.locator('.has-construction-art').count(),0,'Queue settlement restores the ordinary buildings');
   await page.evaluate(()=>ConquerGraphicsQuality.set('auto'));
  }
  assert.deepEqual(errors,[],'No browser exceptions');assert.deepEqual(missing,[],'All app and construction artwork loaded');fs.writeFileSync(path.join(output,phase+'-geometry.json'),JSON.stringify(results,null,2));console.log('PASS '+phase+' actual-app construction: '+output);
 }catch(error){if(page){await page.screenshot({path:path.join(output,phase+'-failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,phase+'-failure.json'),JSON.stringify({message:error.message,stack:error.stack,url:page.url(),animation:await page.evaluate(animationState).catch(()=>null),body:await page.locator('body').innerText().catch(()=>null)},null,2));}throw error;
 }finally{await browser.close();if(fixture&&fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);console.error('Screenshots: '+output);process.exitCode=1;});
