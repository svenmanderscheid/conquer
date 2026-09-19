'use strict';
// Isolated browser fixture: verifies world rendering only and never calls game APIs.
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-march-skins-'));
const fixture=`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${['world-atlas','map-overlay','castle-skins','village-theme'].map(name=>`<link rel="stylesheet" href="/assets/css/${name}.css">`).join('')}
<style>body{margin:0}#map{height:100dvh}.atlas-shell{height:100%!important}</style><body><div id="map"></div>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};</script>
<script src="/assets/js/castle-skins.js"></script><script src="/assets/js/march-skins.js"></script><script src="/assets/js/world-landscape.js"></script><script src="/assets/js/world-map.js"></script><script>
const now=Date.now(),date=n=>new Date(now+n).toISOString();
window.options={host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:()=>'',now:()=>now,state:{city:{id:1,coord_x:64,coord_y:64,castle_level:3,city_skin:'default'},kingdom:{march_skin:'phoenix'},players:[],nodes:[],monsters:[],troop_defs:[{code:1,type:1},{code:2,type:2},{code:3,type:3}],marches:[
{id:1,target_x:69,target_y:64,target_type:2,troops:{1:20,2:30,3:40},state:'marching',departure_time:date(-5000),arrival_time:date(5000),march_skin:null},
{id:2,target_x:64,target_y:69,target_type:2,troops:{1:50,2:50},state:'marching',departure_time:date(-5000),arrival_time:date(5000),march_skin:'default'},
{id:3,target_x:59,target_y:64,target_type:2,troops:{3:75},state:'marching',departure_time:date(-5000),arrival_time:date(5000),march_skin:'ironkeep'},
{id:4,target_x:64,target_y:59,target_type:2,troops:{1:33},state:'marching',departure_time:date(-5000),arrival_time:date(5000),march_skin:'unknown'},
{id:5,target_x:69,target_y:69,target_type:2,troops:{2:25},state:'marching',departure_time:date(-5000),arrival_time:date(5000),march_skin:'rosehall'}]}};
ConquerWorld.render(options);
</script></body></html>`;

(async()=>{
const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}if(name.endsWith('/march-ironkeep.webp')||name.endsWith('/animated-march-ironkeep.webp')){res.writeHead(404);res.end();return;}const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}const send=()=>{res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.webp')?'image/webp':'application/octet-stream');res.end(fs.readFileSync(file));};if(name.endsWith('/march-rosehall.webp'))setTimeout(send,250);else send();});
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  for(const viewport of [{width:1280,height:720},{width:390,height:844},{width:320,height:740},{width:568,height:320}]){
   const page=await browser.newPage({viewport}),errors=[];page.on('pageerror',error=>errors.push(error.message));
   await page.goto(`http://127.0.0.1:${server.address().port}`,{waitUntil:'domcontentloaded'});
   await page.evaluate(()=>{options.state.marches.find(march=>march.id===5).march_skin=null;ConquerWorld.render(options);});
   await page.waitForSelector('.atlas-march-party[data-march-skin="default"].is-skinned');
   await page.waitForSelector('.atlas-march-party[data-march-skin="ironkeep"].is-skin-fallback');
   const legacy=page.locator('.atlas-march-party[data-march-skin=""]').first();
   assert.equal(await legacy.locator('.atlas-party-type').count(),3,'legacy march keeps its full troop composition');
   assert.equal(await legacy.evaluate(el=>el.classList.contains('is-skinned')),false,'legacy march never inherits equipped skin');
   const themed=page.locator('.atlas-march-party[data-march-skin="default"]');
   assert.match(await themed.locator('.atlas-party-skin img').getAttribute('src'),/march-default\.webp\?v=1$/);
   assert.match(await themed.getAttribute('aria-label'),/^Grenzlandzug\. Feldzug/,'accessible label names the visual skin');
   assert.equal(await themed.locator('.atlas-party-units').evaluate(el=>getComputedStyle(el).display),'none','themed group replaces ordinary figures');
   assert.equal(await themed.locator('small').isVisible(),true,'troop count remains readable');
   assert.equal(await themed.evaluate(el=>getComputedStyle(el).pointerEvents),'none','skin does not enlarge the map hitbox');
   const broken=page.locator('.atlas-march-party[data-march-skin="ironkeep"]');
   assert.equal(await broken.locator('.atlas-party-units').evaluate(el=>getComputedStyle(el).display),'flex','missing art falls back to troop figures');
   const unknown=page.locator('.atlas-march-party[data-march-skin="unknown"]');
   assert.equal(await unknown.locator('.atlas-party-type').count(),1,'unknown snapshot safely uses troop figures');
   const changedBeforeLoad=page.locator('.atlas-march-party[data-march-id="5"]');await page.waitForTimeout(300);
   assert.equal(await changedBeforeLoad.evaluate(el=>el.classList.contains('is-skinned')),false,'stale image load cannot skin a changed march');
   assert.equal(await changedBeforeLoad.locator('.atlas-party-units').evaluate(el=>getComputedStyle(el).display),'flex');
   const oldSrc=await themed.locator('.atlas-party-skin img').getAttribute('src');
   await page.evaluate(()=>{options.state.kingdom.march_skin='ironkeep';ConquerWorld.render(options);});
   assert.equal(await themed.locator('.atlas-party-skin img').getAttribute('src'),oldSrc,'equipment changes do not repaint an active snapshot');
   assert.equal(await legacy.evaluate(el=>el.classList.contains('is-skinned')),false,'equipment changes do not skin legacy marches');
   assert.equal(await page.locator('.atlas-routes line').count(),5,'route semantics remain present for every moving march');
   await page.evaluate(()=>document.body.classList.add('reduced-motion'));
   assert.equal(await themed.locator('.atlas-party-skin img').evaluate(el=>getComputedStyle(el).animationName),'none','reduced motion stops skin animation');
   assert.deepEqual(errors,[]);
   await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}.png`)});await page.close();
   console.log(`PASS ${viewport.width}x${viewport.height}: snapshot skin, legacy/unknown/missing fallback, labels, routes, hitbox and reduced motion.`);
  }
  console.log('Screenshots: '+output);
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
