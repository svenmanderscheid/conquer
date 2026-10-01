const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const source=fs.readFileSync(path.resolve(__dirname,'../assets/world-preview/preview.js'),'utf8');
assert(!/ctx\.scale\(fx|fy\?-1|scale\(-1/.test(source),'terrain must not be reflected');
assert(!/scenery-oak|scenery-rocks|ctx\.rotate/.test(source),'legacy low-poly trees and terrain rotations stay absent');
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const output=path.resolve(__dirname,'../artifacts/world-style-preview');fs.mkdirSync(output,{recursive:true});
 for(const [label,width,height] of [['desktop',1280,800],['phone',390,844],['narrow',320,700],['landscape',844,390]]){
  const context=await browser.newContext({viewport:{width,height},hasTouch:true});const page=await context.newPage(),errors=[],writes=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(r.method()!=='GET')writes.push(r.url());});
  await page.goto('http://localhost/conquer/assets/world-preview/index.html');await page.waitForFunction(()=>window.worldPreview?.state().ready);
  const state=()=>page.evaluate(()=>worldPreview.state());await page.waitForTimeout(100);assert.equal((await state()).objects.length,14);
  assert.equal((await state()).terrainVariants,2);
  const animated=(await state()).animationTime;await page.waitForTimeout(160);assert((await state()).animationTime>animated,'animation advances');
  await page.locator('#animation').click();await page.waitForTimeout(80);
  const trees=(await state()).treeCount;assert(trees>0);assert.equal((await state()).treeOverlaps,0);
  await page.locator('#alliance').click();await page.waitForTimeout(80);assert.equal((await state()).allies,2);assert.equal((await state()).treeOverlaps,0);
  await page.locator('#alliance').click();await page.waitForTimeout(80);assert.equal((await state()).treeCount,trees,'trees return deterministically');
  const variants=()=>page.evaluate(()=>Array.from({length:100},(_,n)=>worldPreview.variantAt(n%10-5,Math.floor(n/10)-5)));
  const initialVariants=await variants();assert.equal(new Set(initialVariants).size,2);assert.deepEqual(await variants(),initialVariants,'coordinate selection is stable');
  const idle=(await state()).frames;await page.waitForTimeout(250);assert.equal((await state()).frames,idle,'no idle animation loop');
  await page.screenshot({path:path.join(output,label+'.png')});
  if(label==='desktop'){for(let n=0;n<6;n++)await page.locator('#out').click();await page.screenshot({path:path.join(output,'terrain-edges.png')});await page.locator('#reset').click();}
  for(const {id} of (await state()).objects){await page.locator(`[data-object="${id}"]`).click();assert.equal((await state()).selected,id);assert(await page.locator('#detail').isVisible());if(label==='desktop'&&['crystal','golem'].includes(id))await page.screenshot({path:path.join(output,id+'-close.png')});await page.locator('#close').click();}
  await page.locator('#reset').click();const before=await state();await page.locator('#in').click();assert((await state()).camera.zoom>before.camera.zoom);
  const box=await page.locator('#map').boundingBox();await page.mouse.move(box.x+width*.3,box.y+box.height*.55);await page.mouse.down();await page.mouse.move(box.x+width*.3+60,box.y+box.height*.55+25,{steps:5});await page.mouse.up();assert.notEqual((await state()).camera.x,before.camera.x);assert.equal((await state()).selected,null);
  await page.locator('#reset').click();const s=await state(),obj=s.objects[0];const x=s.width/2+(obj.x-s.camera.x)*s.camera.zoom,y=s.height/2+(obj.y-obj.h/2-s.camera.y)*s.camera.zoom;
  await page.touchscreen.tap(box.x+x,box.y+y);assert.equal((await state()).selected,'castle');await page.keyboard.press('Escape');assert.equal((await state()).selected,null);
  // Exercise real browser multi-touch events through the debugging protocol.
  const session=await context.newCDPSession(page),z=(await state()).camera.zoom,cx=box.x+width/2,cy=box.y+box.height*.4;
  await session.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:cx-25,y:cy,id:1},{x:cx+25,y:cy,id:2}]});
  await session.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:cx-60,y:cy,id:1},{x:cx+60,y:cy,id:2}]});
  await session.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});assert((await state()).camera.zoom>z,'pinch zoom');assert.equal((await state()).selected,null);
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(80);assert.equal((await state()).animate,false);const stopped=(await state()).frames;await page.waitForTimeout(140);assert.equal((await state()).frames,stopped,'reduced motion stops rendering');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no horizontal overflow');assert.deepEqual(errors,[]);assert.deepEqual(writes,[]);console.log(label+': OK');await context.close();
 }
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
