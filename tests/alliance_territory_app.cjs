'use strict';
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-alliance-territory-'));
const fixture=`<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${['world-atlas','map-overlay','castle-skins','world-encounters','world-map','village-theme'].map(n=>`<link rel="stylesheet" href="/assets/css/${n}.css">`).join('')}
<style>body{margin:0}#map{height:100dvh}.atlas-shell{height:100%!important}</style><body class="mobile-game"><div id="map"></div>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};</script>
${['castle-skins','world-landscape','world-encounters','world-map'].map(n=>`<script src="/assets/js/${n}.js"></script>`).join('')}
<script>
window.options={host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:()=>'orc',now:()=>Date.now(),state:{
 city:{id:1,world_id:1,name:'Teststadt',coord_x:80,coord_y:80,castle_level:3,city_skin:'default'},player:{name:'Tester'},
 players:[],nodes:[],monsters:[],charms:[],marches:[],troop_defs:[],
 alliance_structures:[
  {id:1,alliance_id:1,alliance_name:'Hüter des Tals',alliance_tag:'HDT',structure_type:'center',name:'Allianzzentrum',coord_x:84,coord_y:80,radius:12},
  {id:2,alliance_id:1,alliance_name:'Hüter des Tals',alliance_tag:'HDT',structure_type:'outpost',name:'Außenposten',coord_x:78,coord_y:85,radius:6}
 ]
}};ConquerWorld.render(options);
</script></body></html>`;

(async()=>{
 const server=http.createServer((req,res)=>{
  const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}
  const file=path.resolve(root,'.'+name);
  if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  for(const viewport of [{width:1280,height:800},{width:390,height:844}]){
   const page=await browser.newPage({viewport}),errors=[];page.on('pageerror',error=>errors.push(error.message));
   await page.goto(`http://127.0.0.1:${server.address().port}`);await page.waitForTimeout(300);
   const center=page.locator('[data-atlas-target="alliance_center:1"]'),outpost=page.locator('[data-atlas-target="outpost:2"]');
   if(await center.count()===0)throw new Error('Map did not render alliance structures: '+errors.join(' | '));
   assert.equal(await center.getAttribute('data-footprint'),'5');assert.equal(await outpost.getAttribute('data-footprint'),'3');
   assert.equal(await center.locator('img').evaluate(i=>i.complete&&i.naturalWidth>0),true);assert.equal(await outpost.locator('img').evaluate(i=>i.complete&&i.naturalWidth>0),true);
   await center.click();await page.locator('.atlas-target-actions [data-atlas="details"]').click();const details=await page.locator('.atlas-detail').innerText();assert.match(details,/Radius 12/i);assert.match(details,/Produktion und Sammeltempo/i);
   await page.screenshot({path:path.join(out,`${viewport.width}x${viewport.height}.png`)});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);assert.deepEqual(errors,[]);
   await page.close();console.log(`PASS ${viewport.width}x${viewport.height}: center/outpost footprints, details and responsive map.`);
  }
  console.log('Screenshots: '+out);
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
