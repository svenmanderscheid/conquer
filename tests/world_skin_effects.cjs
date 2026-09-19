'use strict';
// Renders a local fixture; never connects to a game API or changes player state.
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const fixture=`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${['world-atlas','map-overlay','castle-skins','name-frames','village-theme'].map(name=>`<link rel="stylesheet" href="/assets/css/${name}.css">`).join('')}
<style>body{margin:0}#map{height:100dvh}.atlas-shell{height:100%!important}</style><body><div id="map"></div>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};</script>
${['castle-skins','name-frames','world-landscape','world-map'].map(name=>`<script src="/assets/js/${name}.js"></script>`).join('')}
<script>const options={host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:()=>'',now:()=>Date.now(),state:{city:{id:1,coord_x:83,coord_y:70,castle_level:3,city_skin:'phoenix',name_frame:'dragon'},players:[{id:2,coord_x:83,coord_y:65,castle_level:3,city_skin:'ironkeep',name_frame:'rosehall',display_name:'Eisenwacht'},{id:3,coord_x:88,coord_y:65,castle_level:3,city_skin:'sandspire',name_frame:'untrusted',display_name:'Dünenhain'}],nodes:[],monsters:[],marches:[]}};ConquerWorld.render(options);</script></body></html>`;
(async()=>{
 const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(r=>server.listen(0,'127.0.0.1',r));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));await page.goto(`http://127.0.0.1:${server.address().port}`);
  await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker img')].every(i=>i.complete&&i.naturalWidth));
  assert.equal(await page.locator('.atlas-marker[data-atlas-target="home"]').getAttribute('data-rarity'),'mythic');
  const glow=await page.locator('.atlas-marker[data-atlas-target="home"] .castle-skin-effect').evaluate(el=>getComputedStyle(el).backgroundImage);assert(glow.includes('radial-gradient'));
  assert((await page.locator('.atlas-marker[data-atlas-target="players:2"] img').getAttribute('src')).includes('.webp'));
  assert((await page.locator('.atlas-marker[data-atlas-target="home"] img').getAttribute('src')).includes('.webp'));
  assert.equal(await page.locator('.atlas-marker[data-atlas-target="home"] .atlas-marker-name').getAttribute('data-name-frame'),'dragon');
  assert.equal(await page.locator('.atlas-marker[data-atlas-target="players:2"] .atlas-marker-name').getAttribute('data-name-frame'),'rosehall');
  assert.equal(await page.locator('.atlas-marker[data-atlas-target="players:3"] .atlas-marker-name').getAttribute('data-name-frame'),'sandspire');
  assert.equal(await page.locator('.atlas-marker[data-atlas-target="home"] .name-frame-ornament').count(),2);
  const frameLayout=await page.locator('.atlas-marker[data-atlas-target="home"] .atlas-marker-name').evaluate(node=>{const box=node.getBoundingClientRect(),style=getComputedStyle(node);return{x:box.x,y:box.y,width:box.width,height:box.height,opacity:style.opacity,position:style.position,display:style.display,viewport:[innerWidth,innerHeight]};});
  assert(Number(frameLayout.opacity)>0&&frameLayout.width>0&&frameLayout.height>0&&frameLayout.x<frameLayout.viewport[0]&&frameLayout.y<frameLayout.viewport[1],JSON.stringify(frameLayout));
  const frameA=await page.locator('.atlas-marker[data-atlas-target="home"]').screenshot();await page.waitForTimeout(750);const frameB=await page.locator('.atlas-marker[data-atlas-target="home"]').screenshot();assert(!frameA.equals(frameB),'mythic aura must actually animate on the map');
  const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-skins-'));await page.screenshot({path:path.join(output,'mythic-and-legendary-mobile.png')});
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForFunction(()=>document.querySelector('.atlas-marker[data-atlas-target="home"] img').src.includes('.png'));await page.waitForFunction(()=>document.querySelector('.atlas-marker[data-atlas-target="players:2"] img').src.includes('.png'));
  await page.emulateMedia({reducedMotion:'no-preference'});await page.waitForFunction(()=>document.querySelector('.atlas-marker[data-atlas-target="players:2"] img').src.includes('.webp'));
  await page.evaluate(()=>{document.body.classList.add('reduced-motion');ConquerWorld.render(options);});assert((await page.locator('.atlas-marker[data-atlas-target="players:2"] img').getAttribute('src')).includes('.png'));
  const events=await page.evaluate(()=>{let selected=null;document.querySelector('#map').addEventListener('click',e=>{const b=e.target.closest('[data-atlas-target]');if(b)selected=b.dataset.atlasTarget;});document.querySelector('.atlas-marker[data-atlas-target="home"]').click();return selected;});assert.equal(events,'home');
  const layout=await page.evaluate(()=>({w:document.documentElement.scrollWidth,vw:innerWidth}));assert.equal(layout.w,layout.vw);assert.deepEqual(errors,[]);
  console.log('PASS mythic aura, animated legendary image, system/game reduced motion, target click, mobile layout; '+output);
 }finally{if(browser)await browser.close();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exitCode=1;});
