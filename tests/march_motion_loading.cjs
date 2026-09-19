'use strict';
// Isolated browser contract: no API calls and no account state changes.
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),fixture=require('./fixtures/world_march_motion.cjs'),requests=new Map();
const type=file=>file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.png')?'image/png':file.endsWith('.webp')?'image/webp':'application/octet-stream';
const motionMatch=pathname=>pathname.match(/\/animated-march-([a-z]+)\.webp$/);
const server=http.createServer((request,response)=>{
 const pathname=decodeURIComponent(new URL(request.url,'http://localhost').pathname),match=motionMatch(pathname);
 if(pathname==='/'){response.setHeader('Content-Type','text/html');response.end(fixture(root));return;}
 if(pathname==='/favicon.ico'){response.writeHead(204).end();return;}
 if(match){
  const id=match[1];requests.set(id,(requests.get(id)||0)+1);
  if(id==='ironkeep'){response.writeHead(404).end();return;}
  const fallback=path.join(root,'assets','art','marches',`march-${id}.webp`);
  response.setHeader('Content-Type','image/webp');response.end(fs.readFileSync(fallback));return;
 }
 const file=path.resolve(root,'.'+pathname);
 if(!pathname.startsWith('/assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file)){response.writeHead(404).end();return;}
 response.setHeader('Content-Type',type(file));response.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  const page=await browser.newPage({viewport:{width:390,height:844},serviceWorkers:'block'}),errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.emulateMedia({reducedMotion:'reduce'});await page.goto(`http://127.0.0.1:${server.address().port}`);
  await page.evaluate(()=>{demoAuto=false;document.querySelector('#skin').value='rosehall';startMarch(5,12000);});
  let actor=page.locator('.atlas-march-party[data-march-skin="rosehall"]');await actor.waitFor();await page.waitForTimeout(250);
  assert.equal(requests.get('rosehall')||0,0,'reduced motion must not request an animated WebP');
  assert.match(await actor.locator('.atlas-party-skin img').getAttribute('src'),/march-rosehall\.webp/);

  await page.emulateMedia({reducedMotion:'no-preference'});await page.waitForFunction(()=>document.querySelector('.atlas-march-party[data-march-skin="rosehall"]')?.querySelector('.atlas-party-skin img')?.src.includes('animated-march-rosehall.webp'));
  assert.equal(requests.get('rosehall'),1,'visible moving skin should request its animated WebP once');

  await page.evaluate(()=>{document.querySelector('#skin').value='ironkeep';startMarch(5,12000);});
  actor=page.locator('.atlas-march-party[data-march-skin="ironkeep"]');await actor.waitFor();
  await page.waitForFunction(()=>{const actor=document.querySelector('.atlas-march-party[data-march-skin="ironkeep"]');return actor?.classList.contains('is-skinned')&&actor.querySelector('.atlas-party-skin img')?.src.includes('march-ironkeep.webp');});
  assert.equal(requests.get('ironkeep'),1,'broken motion media should not retry every animation frame');
  assert.equal(await actor.evaluate(node=>node.classList.contains('is-skin-fallback')),false,'valid still art must remain visible when motion fails');
  assert.equal(await actor.locator('.atlas-party-units').evaluate(node=>getComputedStyle(node).display),'none','motion failure must not expose troop art over the still creature');
  await page.waitForTimeout(250);assert.equal(requests.get('ironkeep'),1,'broken motion media retried after fallback');

  await page.evaluate(()=>{
   const now=options.now(),seed=options.state.marches[0];
   options.state.marches=[{...seed,id:991,march_skin:'sandspire',origin_x:194,origin_y:194,target_x:200,target_y:194,state:'marching',departure_time:new Date(now).toISOString(),arrival_time:new Date(now+12000).toISOString(),return_time:new Date(now+26000).toISOString()}];
   ConquerWorld.render(options);
  });
  actor=page.locator('.atlas-march-party[data-march-skin="sandspire"]');await actor.waitFor({state:'attached'});await page.waitForTimeout(250);
  assert.equal(requests.get('sandspire')||0,0,'offscreen march must not request its animated WebP');
  assert.equal(await actor.isHidden(),true,'offscreen fixture did not stay outside the viewport');
  assert.match(await actor.locator('.atlas-party-skin img').getAttribute('src'),/march-sandspire\.webp/);
  await page.evaluate(()=>ConquerWorld.focus(194.5,194.5));
  await page.waitForFunction(()=>document.querySelector('.atlas-march-party[data-march-skin="sandspire"]')?.querySelector('.atlas-party-skin img')?.src.includes('animated-march-sandspire.webp'));
  assert.equal(requests.get('sandspire'),1,'animation should load when the moving march enters the viewport');
  await page.evaluate(()=>{options.state.marches[0].state='gathering';ConquerWorld.render(options);});
  await page.waitForFunction(()=>document.querySelector('.atlas-march-party[data-march-skin="sandspire"]')?.querySelector('.atlas-party-skin img')?.src.includes('/march-sandspire.webp'));
  assert.equal(await actor.evaluate(node=>node.classList.contains('is-moving')),false,'gathering march must stop moving');
  assert.deepEqual(errors,[]);
  console.log('PASS motion media: reduced-motion suppression, visible-only load, static error fallback, no retry and gathering stop.');
 }finally{if(browser)await browser.close();server.closeAllConnections?.();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
