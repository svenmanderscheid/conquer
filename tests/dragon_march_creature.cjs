'use strict';
// Isolated world fixture: no API writes. Verifies the rendered dragon loop and
// its world-map behavior across animation, fallback and reduced motion.
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),fixture=require('./fixtures/world_march_motion.cjs'),output=path.join(root,'artifacts/dragon-march-creature');fs.mkdirSync(output,{recursive:true});
const still=path.join(root,'assets/art/marches/flight-dragon.png'),motion=path.join(root,'assets/art/marches/flight-dragon.webp');
assert(fs.statSync(still).size>20_000,'dragon still contains the detailed creature render');
assert(fs.statSync(motion).size>100_000&&fs.statSync(motion).size<500_000,'dragon loop stays detailed and mobile-sized');
const motionBytes=fs.readFileSync(motion);assert(motionBytes.includes(Buffer.from('ANIM')),'dragon WebP declares animation');let frameCount=0,offset=0;while((offset=motionBytes.indexOf(Buffer.from('ANMF'),offset))!==-1){frameCount++;offset+=4;}assert.equal(frameCount,48,'dragon loop contains all 48 articulated poses');

(async()=>{
 const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture(root));return;}if(name==='/favicon.ico'){res.writeHead(204);res.end();return;}const file=path.resolve(root,'.'+name);if(!name.startsWith('/assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const url='http://127.0.0.1:'+server.address().port;let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  // A missing motion loop must keep the static articulated creature visible.
  const fallbackContext=await browser.newContext({viewport:{width:390,height:844}});await fallbackContext.route('**/flight-dragon.webp*',route=>route.abort());const fallbackPage=await fallbackContext.newPage();
  await fallbackPage.goto(url);await fallbackPage.evaluate(()=>{demoAuto=false;document.querySelector('#skin').value='dragon';startMarch(5,10000);});
  const broken=fallbackPage.locator('.atlas-march-party[data-march-skin="dragon"]');await broken.waitFor();await fallbackPage.waitForFunction(()=>{const actor=document.querySelector('.atlas-march-party[data-march-skin="dragon"]');return actor?.classList.contains('is-skinned')&&actor.querySelector('.atlas-party-skin img')?.src.includes('flight-dragon.png');});
  assert.equal(await broken.locator('.atlas-party-units').evaluate(element=>getComputedStyle(element).display),'none');assert.equal(await broken.evaluate(element=>element.classList.contains('is-skin-fallback')),false);await fallbackContext.close();

  const context=await browser.newContext({viewport:{width:1280,height:800}}),page=await context.newPage(),errors=[],framePixels=image=>image.evaluate(element=>{const canvas=document.createElement('canvas');canvas.width=element.naturalWidth;canvas.height=element.naturalHeight;canvas.getContext('2d').drawImage(element,0,0);return canvas.toDataURL();});page.on('pageerror',error=>errors.push(error.message));await page.goto(url);await page.evaluate(()=>{demoAuto=false;document.querySelector('#skin').value='dragon';startMarch(5,12000);});
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>{clockShift=0;startMarch(5,12000);clockShift=6000;ConquerWorld.render(options);});const actor=page.locator('.atlas-march-party[data-march-skin="dragon"]');await actor.waitFor();await page.waitForFunction(()=>document.querySelector('.atlas-march-party[data-march-skin="dragon"]')?.classList.contains('is-skinned'));
   const image=actor.locator('.atlas-party-skin img'),source=await image.getAttribute('src');assert.match(source,/flight-dragon\.webp\?v=2$/);
   assert.deepEqual(await image.evaluate(element=>({width:element.naturalWidth,height:element.naturalHeight})),{width:384,height:384});
   const box=await image.boundingBox(),label=await actor.locator('small').boundingBox();assert(box&&box.width>=72&&box.height>=58,JSON.stringify({width,height,box}));assert(box.x>=0&&box.y>=0&&box.x+box.width<=width&&box.y+box.height<=height,JSON.stringify({width,height,box}));assert(label&&label.x>=0&&label.x+label.width<=width&&label.y+label.height<=height,JSON.stringify({width,height,label}));
   await page.waitForTimeout(430);
   await page.screenshot({path:path.join(output,`${width}x${height}.png`)});
  }
  // Both OS preference and the app setting use the static first pose.
  await page.emulateMedia({reducedMotion:'reduce'});await page.evaluate(()=>{clockShift=0;startMarch(5,12000);clockShift=6000;ConquerWorld.render(options);});const reduced=page.locator('.atlas-march-party[data-march-skin="dragon"]');await reduced.waitFor();await page.waitForFunction(()=>document.querySelector('.atlas-march-party[data-march-skin="dragon"]')?.classList.contains('is-skinned'));
  const reducedImage=reduced.locator('.atlas-party-skin img');assert.match(await reducedImage.getAttribute('src'),/flight-dragon\.png\?v=2$/);const reducedFirst=await framePixels(reducedImage);await page.waitForTimeout(430);assert.equal(await framePixels(reducedImage),reducedFirst,'reduced motion freezes the dragon pose');assert.equal(await reducedImage.evaluate(element=>getComputedStyle(element).animationName),'none');
  await page.screenshot({path:path.join(output,'reduced-motion.png')});assert.deepEqual(errors,[]);await context.close();console.log('PASS dragon creature: animated loop, missing-art fallback, reduced motion and readable 1280/390/320/landscape sizes. Screenshots: '+output);
 }finally{if(browser)await browser.close();server.closeAllConnections?.();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1});
