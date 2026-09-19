'use strict';
// Read-only HTTP fixture. Real terrain and map code, no game API or player writes.
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const fixture=()=>`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${['world-atlas','map-overlay','castle-skins','world-zones','world-encounters','village-theme'].map(name=>`<link rel="stylesheet" href="/assets/css/${name}.css">`).join('')}
<style>*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif}#map{height:100dvh}</style><body class="mobile-game playfield-mode world-mode"><div id="map"></div>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};window.fixtureActions=[];document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)fixtureActions.push(b.dataset.action);});</script>
${['castle-skins','world-landscape','world-encounters','world-map'].map(name=>`<script src="/assets/js/${name}.js"></script>`).join('')}
<script>ConquerWorld.render({host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:()=>'',now:()=>Date.now(),state:{city:{id:1,coord_x:64,coord_y:64,castle_level:3,city_skin:'yggdrasil'},players:[],nodes:[],monsters:[],marches:[],congress:{id:1,coord_x:128,coord_y:128,name:'Kongress',state:'neutral',can_attack:true,alliance_tag:null,garrison_total:1500000}}});</script></body></html>`;
const zones=[{id:'forest',button:'Wald',label:'Smaragdwald',x:64,y:64},{id:'ice',button:'Eis',label:'Frostlande',x:192,y:64},{id:'sand',button:'Sand',label:'Sonnendünen',x:64,y:192},{id:'lava',button:'Lava',label:'Aschenlande',x:192,y:192},{id:'congress',button:'♛ Kongress · Weltensee',label:'Weltensee · Kongress',x:128,y:128}];
const viewports=[{width:390,height:844},{width:320,height:568},{width:568,height:320},{width:1280,height:720},{width:844,height:390}];
(async()=>{
 const blocked=[];
 const server=http.createServer((req,res)=>{
  const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture());return;}
  if(!name.startsWith('/assets/')){blocked.push(name);res.writeHead(403);res.end();return;}
  const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-biomes-')),findings=[],errors=[];
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  const page=await browser.newPage({viewport:viewports[0],hasTouch:true});page.on('pageerror',e=>errors.push(e.message));
  await page.goto(`http://127.0.0.1:${server.address().port}`);
  await page.waitForFunction(()=>typeof window.ConquerLandscape?.biomeAt==='function'&&typeof window.ConquerLandscape?.ground==='function');
  const terrain=await page.evaluate(()=>{
   const L=ConquerLandscape,points=[[64,64],[192,64],[64,192],[192,192]],samples=points.map(([x,y])=>L.biomeAt(x,y));
   const rgb=color=>color.slice(1).match(/../g).map(n=>parseInt(n,16));let maxStep=0;
   for(const fixed of[48,84,128,170,208])for(const axis of[0,1]){let previous=null;for(let coordinate=80;coordinate<=176;coordinate+=.25){const value=rgb(L.biomeAt(axis?fixed:coordinate,axis?coordinate:fixed).ground);if(previous)maxStep=Math.max(maxStep,...value.map((n,i)=>Math.abs(n-previous[i])));previous=value;}}
   const canvas=document.createElement('canvas');canvas.width=canvas.height=256;const c=canvas.getContext('2d'),bounds={left:0,top:0,right:255,bottom:255};L.ground(c,(x,y)=>[x,y],1,bounds);const first=canvas.toDataURL();L.ground(c,(x,y)=>[x,y],1,bounds);const second=canvas.toDataURL();
   return{samples,maxStep,stable:first===second,centerWater:L.waterAt(128,128),transition:L.biomeAt(128,128).weights,groundColors:points.map(([x,y])=>[...c.getImageData(x,y,1,1).data])};
  });
  assert.deepEqual(terrain.samples.map(v=>v.id),['forest','ice','sand','lava']);assert.equal(new Set(terrain.samples.map(v=>v.ground)).size,4);assert.equal(new Set(terrain.groundColors.map(v=>v.join(','))).size,4);
  assert(terrain.samples.every(v=>{const [r,g,b]=v.ground.slice(1).match(/../g).map(n=>parseInt(n,16));return g>r&&g>b;}),'every regional ground palette belongs to the continuous green fantasy world');
  assert(terrain.maxStep<=3,`palette transitions jump ${terrain.maxStep} levels per quarter tile`);assert(terrain.stable,'world ground must be deterministic');assert(terrain.centerWater,'Congress must float over central lake');assert(Object.values(terrain.transition).filter(n=>n>.1).length>=2);
  console.log('PASS continuous green fantasy world with four subtle regional accents, smooth boundaries and central lake');
  await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker img')].every(i=>i.complete&&i.naturalWidth));
  const navigation=page.locator('#atlas-navigation-panel');
  const openNavigation=async()=>{if(!(await navigation.isVisible()))await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();await navigation.waitFor({state:'visible'});};
  for(const viewport of viewports){
   await page.setViewportSize(viewport);const size=`${viewport.width}x${viewport.height}`;
   const coordinateRect=await page.locator('.map-overlay-coordinate-toggle').boundingBox();
   assert(coordinateRect.width<=164&&coordinateRect.height<=44,`${size}: coordinate control stays compact`);
   // The compass returns to the village; the adjacent map button still opens the overview.
   await openNavigation();await navigation.getByRole('button',{name:'Lava',exact:true}).click();
   const home=page.getByRole('button',{name:'Zum eigenen Dorf springen',exact:true});
   const homeRect=await home.boundingBox();assert(homeRect.width>=44&&homeRect.height>=44,`${size}: compass touch target`);
   await home.tap();
   assert.equal(await page.locator('.atlas-coordinates').textContent(),'X 64 · Y 64',`${size}: compass must centre the fixture village`);
   assert(!(await navigation.isVisible()),`${size}: compass must not open the overview`);
   await openNavigation();
   const controls=await navigation.evaluate(el=>{
    const parent=el.getBoundingClientRect(),visible=[...el.querySelectorAll('.atlas-zone-jumps button,.atlas-jump button,.atlas-zoom button,.map-overlay-close')].map(button=>{const r=button.getBoundingClientRect();return{text:button.textContent.trim()||button.getAttribute('aria-label'),x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};});
    return{parent:{x:parent.x,y:parent.y,right:parent.right,bottom:parent.bottom},controls:visible,w:innerWidth,h:innerHeight,scrollWidth:document.documentElement.scrollWidth,scrollHeight:document.documentElement.scrollHeight};
   });
   for(const b of controls.controls)if(b.x<0||b.y<0||b.right>controls.w+.5||b.bottom>controls.h+.5||b.width<1||b.height<1)findings.push(`${size}: clipped navigation button ${b.text} ${JSON.stringify(b)}`);
   if(controls.scrollWidth>controls.w||controls.scrollHeight>controls.h)findings.push(`${size}: page overflow ${controls.scrollWidth}x${controls.scrollHeight}`);
   await page.screenshot({path:path.join(output,`${size}-world-overview.png`)});
   await navigation.locator('.atlas-minimap canvas').screenshot({path:path.join(output,`${size}-minimap.png`)});
   for(const zone of zones){
    await openNavigation();await navigation.getByRole('button',{name:zone.button,exact:true}).click();
    await page.waitForFunction(label=>document.querySelector('.atlas-biome-label').textContent===label,zone.label);
    const coords=await page.locator('.atlas-coordinates').textContent();assert(coords.includes(String(zone.x))&&coords.includes(String(zone.y)),`${size} ${zone.id} navigation: ${coords}`);
    assert(!(await navigation.isVisible()),'zone navigation should close overview');
    await page.screenshot({path:path.join(output,`${size}-${zone.id}.png`)});
    const overflow=await page.evaluate(()=>({w:document.documentElement.scrollWidth,h:document.documentElement.scrollHeight,vw:innerWidth,vh:innerHeight}));if(overflow.w>overflow.vw||overflow.h>overflow.vh)findings.push(`${size}/${zone.id}: page overflow ${JSON.stringify(overflow)}`);
   }
   const congress=page.locator('.atlas-marker[data-atlas-target="congress"]');assert(await congress.isVisible());assert((await congress.locator('img').getAttribute('src')).includes('congress.webp'));
   await congress.click();assert(await page.getByRole('group',{name:'Zielaktionen'}).isVisible());assert.equal(await page.locator('[role="dialog"]:visible').count(),0,'first Congress click must remain a non-modal context menu');
   assert(await page.getByRole('group',{name:'Zielaktionen'}).getByRole('button',{name:'Angreifen',exact:true}).isVisible());
   const menuRect=await page.getByRole('group',{name:'Zielaktionen'}).boundingBox();if(menuRect.x<0||menuRect.y<0||menuRect.x+menuRect.width>viewport.width+.5||menuRect.y+menuRect.height>viewport.height+.5)findings.push(`${size}: Congress context menu clipped ${JSON.stringify(menuRect)}`);
   await page.screenshot({path:path.join(output,`${size}-congress-actions.png`)});
   await page.getByRole('group',{name:'Zielaktionen'}).getByRole('button',{name:'Kongress',exact:true}).click();assert.equal(await page.evaluate(()=>fixtureActions.at(-1)),'congress-open');
   console.log(`PASS ${size}: five region jumps, non-modal Congress actions, event dispatch`);
  }
  const congress=page.locator('.atlas-marker[data-atlas-target="congress"]');const frameA=await congress.screenshot();await page.waitForTimeout(875);const frameB=await congress.screenshot();assert(!frameA.equals(frameB),'floating Congress must show actual animated frames');
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForFunction(()=>document.querySelector('.atlas-marker[data-atlas-target="congress"] img').src.includes('congress.png'));await page.emulateMedia({reducedMotion:'no-preference'});
  assert.deepEqual(errors,[],'browser runtime errors');assert(!blocked.some(name=>name.includes('/api')),'fixture must never call game API');
  fs.writeFileSync(path.join(output,'findings.json'),JSON.stringify({terrain,findings,errors},null,2));console.log(`Screenshots: ${output}`);
  assert.deepEqual(findings,[],'responsive clipping findings');console.log('ALL WORLD BIOME CHECKS PASSED');
 }finally{console.log(`QA output: ${output}`);if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
