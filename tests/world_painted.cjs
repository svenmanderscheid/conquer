// Read-only fixture of the production map, not the standalone style preview.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'artifacts/world-painted');fs.mkdirSync(output,{recursive:true});
const ids=['orc','skeleton','golem','treasure-goblin','green-dragon','red-dragon','gold-dragon','magdar'];
const state={city:{id:1,world_id:1,coord_x:64,coord_y:64,castle_level:5,city_skin:'default'},players:[{id:2,coord_x:68,coord_y:64,city_skin:'default',username:'Allianz-Nachbar'},{id:3,coord_x:60,coord_y:64,city_skin:'forest',username:'Weltenbaum'}],nodes:[1,2,3,4,5].map((n)=>({id:n,object_type:n,coord_x:56+n*3,coord_y:69,level:3,amount:10000})),monsters:ids.map((id,n)=>({id:n+1,coord_x:56+(n%4)*5,coord_y:n<4?59:74,hp_current:100,hp_max:100,definition:{name:id,art:'monsters/2.5d/bright-v2/'+id,type:n<4?'solo':'rally',level:3}})),marches:[]};
function fixture(){return `<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${['fantasy-fonts','world-atlas','map-overlay','castle-skins','world-zones','world-encounters','village-theme'].map(n=>`<link rel="stylesheet" href="/conquer/assets/css/${n}.css">`).join('')}<style>body{margin:0}#map{height:100dvh}</style><body class="mobile-game playfield-mode world-mode"><div id="map"></div><script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};window.testState=${JSON.stringify(state)};window.attacks=[];</script>${['castle-skins','world-landscape','world-encounters','world-map'].map(n=>`<script src="/conquer/assets/js/${n}.js"></script>`).join('')}<script>window.options={host:document.querySelector('#map'),base:'/conquer',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:m=>m.definition.art,now:()=>Date.now(),onMonsterAttack:id=>attacks.push(id),state:testState};ConquerWorld.render(options);</script></body></html>`;}
(async()=>{let browser;const errors=[],failed=[];
 const server=http.createServer((req,res)=>{const url=new URL(req.url,'http://localhost');if(url.pathname==='/'){res.setHeader('Content-Type','text/html');res.end(fixture());return;}
  if(!url.pathname.startsWith('/conquer/assets/')){res.writeHead(404);res.end();return;}
  const file=path.resolve(root,'.'+url.pathname.replace('/conquer',''));if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));
 });await new Promise(r=>server.listen(0,'127.0.0.1',r));
 try{browser=await chromium.launch({channel:'msedge',headless:true});const page=await browser.newPage({viewport:{width:1280,height:900},hasTouch:true});page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400&&r.url().includes('/assets/'))failed.push(r.url());});await page.goto(`http://127.0.0.1:${server.address().port}`);
  await page.waitForFunction(()=>document.querySelector('[data-painted="castle"] canvas'));
  for(const [width,height] of [[1280,900],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>ConquerWorld.focus(65,66));await page.waitForTimeout(400);
   await page.screenshot({path:path.join(output,`${width}x${height}.png`)});
   for(const id of [...ids,'farm','lumber','quarry','gold','crystal','castle']){
    const marker=page.locator(`[data-painted="${id}"]`).first();assert.equal(await marker.count(),1,id+' mapped');
    const coords=await marker.evaluate(n=>({x:+n.dataset.x,y:+n.dataset.y}));await page.evaluate(p=>ConquerWorld.focus(p.x,p.y),coords);
    await page.waitForFunction(id=>{const c=document.querySelector(`[data-painted="${id}"] canvas`);return c&&c.paintStamp!==undefined;},id);
    const box=await marker.boundingBox();assert(box.width>=28&&box.height>=28,'server footprint retained');
    if(id==='gold'){
     await page.evaluate(()=>{window.gathers=[];options.onGather=id=>gathers.push(id);ConquerWorld.render(options);});
     await marker.tap();assert.deepEqual(await page.evaluate(()=>gathers),[4],'free mine opens gather callback once');
     assert.equal(await page.locator('.atlas-target-actions.is-encounter:visible').count(),0,'no intermediate resource card');
     await page.evaluate(()=>{delete options.onGather;ConquerWorld.render(options);});
     await marker.tap();assert(await page.locator('.atlas-target-actions.is-encounter').isVisible(),'resource card fallback');
     await page.screenshot({path:path.join(output,`${width}-gold-action.png`)});await page.keyboard.press('Escape');
    }
    if(id==='orc'){await marker.tap();assert((await page.evaluate(()=>attacks.length))>0,'direct attack callback');}
   }
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no horizontal overflow');
   console.log('PASS production painted map, every asset and target actions '+width+'x'+height);
  }
  await page.setViewportSize({width:1280,height:900});await page.evaluate(()=>ConquerWorld.focus(64,74));await page.waitForTimeout(2500);await page.screenshot({path:path.join(output,'dragons.png')});
  const sizes=await page.evaluate(()=>Object.fromEntries(['green-dragon','red-dragon','gold-dragon','magdar'].map(id=>{const node=document.querySelector(`[data-painted="${id}"]`),size=Number(node.style.getPropertyValue('--painted-size')),img=node.querySelector('img'),c=document.createElement('canvas');c.width=img.naturalWidth;c.height=img.naturalHeight;const g=c.getContext('2d');g.drawImage(img,0,0);const p=g.getImageData(0,0,c.width,c.height).data;let top=c.height,bottom=0;for(let y=0;y<c.height;y++)for(let x=0;x<c.width;x++)if(p[(y*c.width+x)*4+3]>32){top=Math.min(top,y);bottom=Math.max(bottom,y);}return[id,{size,visible:size*(bottom-top+1)/c.height}];})));assert(Math.abs(sizes['red-dragon'].visible/sizes['green-dragon'].visible-1.14)<.001);assert(Math.abs(sizes['gold-dragon'].visible/sizes['green-dragon'].visible-1.10)<.001);assert.equal(sizes.magdar.size,219/70);
  const pixels=()=>page.locator('[data-painted="magdar"] canvas').evaluate(c=>c.toDataURL());const a=await pixels();await page.waitForTimeout(600);assert.notEqual(await pixels(),a,'hammer/chest animate');
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(150);const b=await pixels();await page.waitForTimeout(300);assert.equal(await pixels(),b,'reduced motion stable');
  await page.emulateMedia({reducedMotion:'no-preference'});await page.evaluate(()=>ConquerWorld.setVisible(false));const c=await pixels();await page.waitForTimeout(300);assert.equal(await pixels(),c,'hidden scene pauses');await page.evaluate(()=>ConquerWorld.setVisible(true));
  await page.evaluate(()=>{document.body.dataset.graphicsQuality='light';dispatchEvent(new Event('conquer-graphics-quality'));});await page.waitForTimeout(150);const d=await pixels();await page.waitForTimeout(300);assert.equal(await pixels(),d,'light quality stable');
  assert((await page.locator('[data-atlas-target="players:3"] img').getAttribute('src')).includes('castle-forest'),'premium skin preserved');
  const trees=await page.evaluate(async()=>{const m=await import('/conquer/assets/js/world-painted.js?v=1'),b={left:40,top:40,right:80,bottom:80};const original=m.decorations(b,()=>false),occupied=m.decorations(b,()=>true),restored=m.decorations(b,()=>false);return{count:original.length,blocked:occupied.length,stable:JSON.stringify(original)===JSON.stringify(restored),kinds:[...new Set(original.map(t=>t.kind))]};});assert(trees.count>0);assert.equal(trees.blocked,0);assert(trees.stable);assert.equal(trees.kinds.length,2);
  await page.evaluate(()=>{document.body.dataset.graphicsQuality='normal';dispatchEvent(new Event('conquer-graphics-quality'));for(const n of testState.nodes){n.gatherer_march_id=100+n.id;n.can_attack=true;n.gatherer_name='Testsammler';}ConquerWorld.render(options);});
  await page.emulateMedia({reducedMotion:'no-preference'});
  for(const id of ['gold','quarry','lumber','farm','crystal']){
   const marker=page.locator(`[data-painted="${id}"]`),coords=await marker.evaluate(n=>({x:+n.dataset.x,y:+n.dataset.y}));await page.evaluate(p=>ConquerWorld.focus(p.x,p.y),coords);
   const canvas=marker.locator('canvas[data-work-ready="true"]');await canvas.waitFor();const pixels=()=>canvas.evaluate(c=>c.toDataURL());
   const a=await pixels();await page.waitForTimeout(1150);assert.notEqual(await pixels(),a,id+' work animation');
   await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(150);const b=await pixels();await page.waitForTimeout(400);assert.equal(await pixels(),b,id+' reduced stable');
   await page.emulateMedia({reducedMotion:'no-preference'});await page.screenshot({path:path.join(output,id+'-working.png')});
  }
  const basicHeights=await page.evaluate(()=>['orc','skeleton','golem'].map(id=>{
   const marker=document.querySelector(`[data-painted="${id}"]`),img=marker.querySelector('img'),c=document.createElement('canvas');c.width=img.naturalWidth;c.height=img.naturalHeight;const g=c.getContext('2d');g.drawImage(img,0,0);const p=g.getImageData(0,0,c.width,c.height).data;let top=c.height,bottom=0;
   for(let y=0;y<c.height;y++)for(let x=0;x<c.width;x++)if(p[(y*c.width+x)*4+3]>32){top=Math.min(top,y);bottom=Math.max(bottom,y);}
   return Number(marker.style.getPropertyValue('--painted-size'))*(bottom-top+1)/c.height;
  }));
  assert(Math.max(...basicHeights)/Math.min(...basicHeights)<1.05,'orc/skeleton/golem have comparable visible heights');
  await page.evaluate(()=>ConquerWorld.focus(61,59));await page.waitForTimeout(150);await page.screenshot({path:path.join(output,'basic-monster-sizes.png')});
  assert.equal(await page.locator('.atlas-gathering-tool').count(),0,'no floating tools');
  await page.evaluate(()=>{for(const n of testState.nodes)n.gatherer_march_id=null;ConquerWorld.render(options);ConquerWorld.focus(65,69);});await page.waitForTimeout(150);
  const free=page.locator('[data-painted="gold"] canvas'),before=await free.evaluate(c=>c.toDataURL());await page.waitForTimeout(400);assert.equal(await free.evaluate(c=>c.toDataURL()),before,'free resource is static');assert.equal(await free.getAttribute('data-working'),'false');
  assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);console.log('PASS animation, optical sizes, pause, light mode, skins, tree restoration, all five work animations/cleanup, subfolder paths; no browser errors');
 }finally{await browser?.close();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exitCode=1;});
