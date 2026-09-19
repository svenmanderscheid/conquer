'use strict';
// Local static fixture only: this test never calls game APIs or repairs player data.
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-footprint-'));
const fixture=`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${['world-atlas','map-overlay','castle-skins'].map(name=>`<link rel="stylesheet" href="/assets/css/${name}.css">`).join('')}
<style>body{margin:0}img{max-width:100%}#map{height:100dvh}.atlas-shell{height:100%!important}</style><body><div id="map"></div>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};</script>
${['castle-skins','world-landscape','world-map'].map(name=>`<script src="/assets/js/${name}.js"></script>`).join('')}
<script>
window.villageEvents=[];addEventListener('conquer-village-menu',e=>villageEvents.push(e.detail));
window.ground=[];const settlement=ConquerLandscape.settlement;ConquerLandscape.settlement=(c,x,y,s,skin)=>{ground.push([x,y,s,skin]);if(ground.length>20)ground.shift();settlement(c,x,y,s,skin);};
window.miniRects=[];const fillRect=CanvasRenderingContext2D.prototype.fillRect;CanvasRenderingContext2D.prototype.fillRect=function(x,y,w,h){if(this.canvas.closest('.atlas-minimap')&&['#f9db7b','#365071'].includes(this.fillStyle)){miniRects.push([x,y,w,h,this.fillStyle]);if(miniRects.length>20)miniRects.shift();}return fillRect.call(this,x,y,w,h);};
const now=Date.now(),date=n=>new Date(now+n).toISOString();
window.options={host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:m=>m.definition.art||'orc',now:()=>now,state:{city:{id:1,coord_x:83,coord_y:70,castle_level:3,city_skin:'phoenix'},players:[{id:2,coord_x:88,coord_y:70,castle_level:3,city_skin:'ironkeep',display_name:'Nachbarburg'}],nodes:[{id:3,coord_x:83,coord_y:73,object_type:1,level:1,resource_amount:1000,resource_max:1000}],monsters:[{id:4,coord_x:86,coord_y:70,hp_current:100,definition:{type:'solo',name:'Orc',level:1,hp:100}},{id:9,coord_x:86,coord_y:73,hp_current:1000,definition:{type:'rally',name:'Frostgrimm',art:'monsters/frostgrimm',level:1}}],troop_defs:[{code:1,type:1},{code:2,type:2},{code:3,type:3}],marches:[
{id:1,target_x:88,target_y:70,target_type:2,troops:{1:2,2:3,3:4},state:'marching',departure_time:date(-5000),arrival_time:date(5000)},
{id:2,target_x:83,target_y:73,target_type:5,troops:{1:3},state:'returning',departure_time:date(-15000),arrival_time:date(-5000),return_time:date(5000)},
{id:3,target_x:100,target_y:70,target_type:2,troops:{1:3},state:'marching',departure_time:date(-5000),arrival_time:date(5000)},
{id:4,target_x:86,target_y:70,target_type:3,troops:{1:3},state:'marching',departure_time:date(-5000),arrival_time:date(5000)}]}};
ConquerWorld.render(options);
</script></body></html>`;

(async()=>{
 const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(r=>server.listen(0,'127.0.0.1',r));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  for(const viewport of [{width:1280,height:720},{width:390,height:844},{width:320,height:740}]){
   const page=await browser.newPage({viewport}),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(`http://127.0.0.1:${server.address().port}`);
   const rally=page.locator('.atlas-marker[data-atlas-target="monsters:9"]');assert.equal(await rally.count(),1);assert.match(await rally.getAttribute('aria-label'),/Frostgrimm.*Rally/);
   await rally.click();const command=page.getByRole('button',{name:'Rally starten',exact:true});assert.equal(await command.count(),1);assert.equal(await command.getAttribute('data-kind'),'monsters');assert.equal(await command.getAttribute('data-id'),'9');
   assert(await rally.locator('img').evaluate(i=>i.complete&&i.naturalWidth>0));assert.match(await rally.locator('img').getAttribute('src'),/monsters\/frostgrimm/);assert.deepEqual(errors,[]);await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}-rally.png`)});await page.close();console.log(`PASS ${viewport.width}x${viewport.height}: rally visible, labelled and linked to army composer`);
  }
  console.log('Screenshots: '+output);
 }finally{if(browser)await browser.close();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exitCode=1;});
