'use strict';
// Real shared world map against a disposable local database.
const assert=require('assert/strict'),path=require('path'),fs=require('fs'),net=require('net'),{spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'artifacts/map-optimization');
(async()=>{
 const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));
 const base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--regional-bosses','--map-search','--hud','--chat','--map-optimization'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='',browser;fixture.stdout.on('data',d=>log+=d);fixture.stderr.on('data',d=>log+=d);
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
  const page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  fs.mkdirSync(output,{recursive:true});
  const metrics=[];
  for(const [width,height]of [[390,844],[844,390],[1280,800]]){
   await page.setViewportSize({width,height});await page.goto(base+'/city#world');await page.locator('.atlas-viewport').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
   await page.waitForFunction(()=>document.querySelector('[data-painted="castle"] canvas'));
   await page.evaluate(()=>ConquerWorld.focus(70,69));await page.waitForFunction(()=>document.querySelector('[data-painted="farm"]'));await page.waitForTimeout(300);
   const sizes=await page.locator('.atlas-marker').evaluateAll(nodes=>nodes.map(node=>{const img=node.querySelector('img'),size=parseFloat(getComputedStyle(img).width),tile=parseFloat(node.style.getPropertyValue('--tile-size'));return {kind:node.dataset.kind,painted:node.dataset.painted,ratio:Number(node.style.getPropertyValue('--painted-size'))||size/tile,footprint:Number(node.dataset.footprint)};}));
   const castle=sizes.find(s=>s.painted==='castle'),farm=sizes.find(s=>s.painted==='farm');assert(castle&&farm);assert(castle.ratio>farm.ratio*1.8);assert.equal(castle.footprint,3);
   await page.screenshot({path:path.join(output,width+'x'+height+'-world.png')});
   const stores=await page.locator('.atlas-painted-motion').evaluateAll(nodes=>nodes.map(c=>({width:c.width,height:c.height,kind:c.closest('button').dataset.painted})));
   assert(stores.every(c=>c.width<=192));assert(stores.filter(c=>['farm','lumber','quarry','gold','crystal'].includes(c.kind)).every(c=>c.width<100));metrics.push({width,height,sizes,stores});
   await page.evaluate(()=>ConquerWorld.focus(128,128));await page.waitForTimeout(700);
   const congress=page.locator('.atlas-marker--congress');await congress.waitFor();assert.equal(await congress.getAttribute('data-footprint'),'7');
   const landmarkRatio=await congress.locator('img').evaluate(img=>parseFloat(getComputedStyle(img).width)/parseFloat(img.closest('button').style.getPropertyValue('--tile-size')));assert(landmarkRatio>castle.ratio*1.5&&landmarkRatio<castle.ratio*2);
   await page.screenshot({path:path.join(output,width+'x'+height+'-congress.png')});
  }
  fs.writeFileSync(path.join(output,'metrics.json'),JSON.stringify(metrics,null,2));assert.deepEqual(errors,[]);console.log('PASS main app map: city/resource proportions, compact sprite stores, Congress footprint, phone/landscape/desktop and no browser errors.');
 }finally{
  if(browser)await browser.close();fixture.stdin.end('\n');await new Promise(resolve=>{if(fixture.exitCode!==null)return resolve();fixture.once('exit',resolve);});
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
