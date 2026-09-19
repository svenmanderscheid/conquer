'use strict';
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),fixture=require('./fixtures/world_grounding.cjs');
const output=path.join(root,'artifacts/world-grounding-20260913');fs.mkdirSync(output,{recursive:true});
fs.writeFileSync(path.join(output,'index.html'),fixture(root,'/conquer'));
const regions=[['forest',64,64],['ice',192,64],['sand',64,192],['lava',192,192]];
(async()=>{
 const server=http.createServer((req,res)=>{
  const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture(root));return;}
  const file=path.resolve(root,'.'+name);
  if(!name.startsWith('/assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':file.endsWith('.svg')?'image/svg+xml':'application/octet-stream');res.end(fs.readFileSync(file));
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));let browser;
 const errors=[],failed=[];
 try{
  browser=await chromium.launch({headless:true,channel:'chrome'});
  const page=await browser.newPage({viewport:{width:1280,height:800}});
  page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)failed.push(r.url());});
  await page.goto(`http://127.0.0.1:${server.address().port}`);
  await page.emulateMedia({reducedMotion:'reduce'});
  for(const viewport of [{width:1280,height:800},{width:390,height:844},{width:320,height:568},{width:844,height:390}]){
   await page.setViewportSize(viewport);
   for(const [biome,x,y]of regions){
    await page.evaluate(([x,y])=>ConquerWorld.focus(x+.5,y+.5),[x,y]);
    await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker:not([hidden]) img')].every(i=>i.complete&&i.naturalWidth));
    const regional=await page.locator('.atlas-marker--nodes').evaluateAll((els,biome)=>els.filter(el=>el.dataset.groundBiome===biome).map(el=>({src:el.querySelector('img').src,footprint:el.dataset.footprint,filter:getComputedStyle(el.querySelector('img')).filter})),biome);
    assert.equal(regional.length,5);for(const r of regional){assert(r.src.includes('-'+biome+'.png'));assert.equal(r.footprint,'1');assert.equal(r.filter,'none');}
    await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}-${biome}.png`)});
    if(viewport.width===1280){
     for(let i=0;i<5;i++)await page.locator('.atlas-viewport').press('+');
     await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}-${biome}-close.png`)});
     for(let i=0;i<5;i++)await page.locator('.atlas-viewport').press('-');
     // The zoom ceiling clamps the last increment; restore exactly 100%.
     await page.reload();await page.emulateMedia({reducedMotion:'reduce'});
     await page.evaluate(([x,y])=>ConquerWorld.focus(x+.5,y+.5),[x,y]);
    }
    // Enlarged artwork must keep all five workplaces selectable on phones.
    for(const [offset,name]of ['Getreidehof','Holzfällerlager','Steinbruch','Goldmine','Kristallader'].entries()){
     const id=regions.findIndex(r=>r[0]===biome)*5+offset+1;
     await page.evaluate(id=>{const node=fixtureState.nodes.find(n=>n.id===id);ConquerWorld.focus(node.coord_x,node.coord_y);},id);
     await page.locator(`.atlas-marker[data-atlas-target="nodes:${id}"]`).click();
     const actions=page.getByRole('group',{name:'Zielaktionen'}),rect=await actions.boundingBox();
     assert(rect&&rect.x>=0&&rect.y>=0&&rect.x+rect.width<=viewport.width+1&&rect.y+rect.height<=viewport.height+1);
     assert(await actions.getByRole('button',{name:'Sammeln',exact:true}).isVisible());
     const marker=page.locator(`.atlas-marker[data-atlas-target="nodes:${id}"]`),box=await marker.boundingBox();
     assert(Math.abs(box.width-44)<1&&Math.abs(box.height-44)<1);
     // The artwork may overhang, but only the server's single tile receives hits.
     assert.equal(await marker.locator('img').evaluate(el=>getComputedStyle(el).pointerEvents),'none');
     await page.keyboard.press('Escape');
     await marker.click({position:{x:22,y:22}});
     assert(await actions.getByRole('button',{name:'Sammeln',exact:true}).isVisible());
     await page.keyboard.press('Escape');
    }
   }
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow');
   console.log(`PASS ${viewport.width}x${viewport.height}: all regional assets, footprints, coordinate navigation and gather actions`);
  }
  // Samples of real rendering at a riverbank: paint must never spill onto water.
  const ground=await page.evaluate(()=>{
   const L=ConquerLandscape,c=document.createElement('canvas').getContext('2d');c.canvas.width=c.canvas.height=256;
   const point={x:L.riverX(70,64)+1.3,y:70};
   L.objectGround(c,128,128,44,{kind:'nodes',point});const first=c.canvas.toDataURL();
   let spill=0;const image=c.getImageData(0,0,256,256).data;
   for(let y=0;y<256;y++)for(let x=0;x<256;x++)if(image[(y*256+x)*4+3]>30&&L.waterAt(point.x+(x-128)/44,point.y+(y-128)/44))spill++;
   c.clearRect(0,0,256,256);L.objectGround(c,128,128,44,{kind:'nodes',point});
   return{stable:first===c.canvas.toDataURL(),spill};
  });
  assert(ground.stable,'Ground must remain fixed across repaints');assert(ground.spill<20,'No visible ground over river: '+ground.spill);
  // Same artwork region after a server update moves an existing target.
  await page.evaluate(()=>{Object.assign(fixtureState.nodes[0],{coord_x:184,coord_y:189});ConquerWorld.render(fixtureOptions);});
  assert((await page.locator('.atlas-marker[data-atlas-target="nodes:1"] img').getAttribute('src')).includes('-lava.png'));
  assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);console.log('PASS stable land-clipped ground, region refresh, no browser errors or missing assets');
  console.log('Screenshots: '+output);
 }finally{if(browser)await browser.close();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exitCode=1;});
