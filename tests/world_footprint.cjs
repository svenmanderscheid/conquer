'use strict';
// Local static fixture only: this test never calls game APIs or repairs player data.
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-footprint-'));
const fixture=`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${['world-atlas','map-overlay','castle-skins','world-encounters','village-theme'].map(name=>`<link rel="stylesheet" href="/assets/css/${name}.css">`).join('')}
<style>body{margin:0}img{max-width:100%}#map{height:100dvh}.atlas-shell{height:100%!important}.fixture-right-tools{display:none}@media(min-width:700px) and (max-width:800px){.fixture-right-tools{position:fixed;z-index:60;right:10px;top:235px;display:grid;gap:14px}.fixture-right-tools button{width:62px;height:70px}}</style><body class="mobile-game"><aside class="hud-right-tools fixture-right-tools"><button aria-label="Feldzüge öffnen">Feldzüge</button><button aria-label="Meisterschaft öffnen">Meisterschaft</button></aside><div id="map"></div>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};</script>
${['castle-skins','world-landscape','world-encounters','world-map'].map(name=>`<script src="/assets/js/${name}.js"></script>`).join('')}
<script>
window.villageEvents=[];addEventListener('conquer-village-menu',e=>villageEvents.push(e.detail));
window.ground=[];const settlement=ConquerLandscape.settlement;ConquerLandscape.settlement=(c,x,y,s,skin)=>{ground.push([x,y,s,skin]);if(ground.length>20)ground.shift();settlement(c,x,y,s,skin);};
window.miniRects=[];const fillRect=CanvasRenderingContext2D.prototype.fillRect;CanvasRenderingContext2D.prototype.fillRect=function(x,y,w,h){if(this.canvas.closest('.atlas-minimap')&&['#f9db7b','#365071'].includes(this.fillStyle)){miniRects.push([x,y,w,h,this.fillStyle]);if(miniRects.length>20)miniRects.shift();}return fillRect.call(this,x,y,w,h);};
const now=Date.now(),date=n=>new Date(now+n).toISOString();
window.options={host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('<','&lt;'),monsterArt:()=> 'orc',now:()=>now,state:{city:{id:1,coord_x:83,coord_y:70,castle_level:3,city_skin:'phoenix'},players:[{id:2,coord_x:88,coord_y:70,castle_level:3,city_skin:'ironkeep',display_name:'Nachbarburg'}],nodes:[{id:3,coord_x:83,coord_y:73,object_type:1,level:1,resource_amount:1000,resource_max:1000}],monsters:[{id:4,coord_x:86,coord_y:70,hp_current:100,definition:{type:'solo',name:'Orc',level:1,hp:100}},{id:5,coord_x:92,coord_y:74,hp_current:100,definition:{type:'rally',name:'Alter Rallyboss',level:1,hp:100}}],troop_defs:[{code:1,type:1},{code:2,type:2},{code:3,type:3}],marches:[
{id:1,target_x:88,target_y:70,target_type:2,troops:{1:2,2:3,3:4},state:'marching',departure_time:date(-5000),arrival_time:date(5000)},
{id:2,target_x:83,target_y:73,target_type:5,troops:{1:3},state:'returning',departure_time:date(-15000),arrival_time:date(-5000),return_time:date(5000)},
{id:3,target_x:100,target_y:70,target_type:2,troops:{1:3},state:'marching',departure_time:date(-5000),arrival_time:date(5000)},
{id:4,target_x:86,target_y:70,target_type:3,troops:{1:3},state:'marching',departure_time:date(-5000),arrival_time:date(5000)}]}};
ConquerWorld.render(options);
</script></body></html>`;
const near=(a,b,label)=>assert(Math.abs(a-b)<.1,`${label}: ${a} vs ${b}`);
(async()=>{
 const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(r=>server.listen(0,'127.0.0.1',r));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  for(const viewport of [{width:1280,height:720},{width:741,height:1045},{width:390,height:844},{width:320,height:740}]){
   const page=await browser.newPage({viewport}),errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.goto(`http://127.0.0.1:${server.address().port}`);await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker img')].every(i=>i.complete&&i.naturalWidth));
   const home=page.locator('.atlas-marker[data-atlas-target="home"]'),vp=page.locator('.atlas-viewport'),box=await home.boundingBox();
   near(box.width,132,'three-tile hitbox width');near(box.height,132,'three-tile hitbox height');near(box.x+box.width/2,viewport.width/2,'home centered on its anchor');near(box.y+box.height/2,viewport.height/2,'home vertical center');
   const art=await home.locator('img').evaluate(el=>({w:parseFloat(getComputedStyle(el).width),h:parseFloat(getComputedStyle(el).height),max:getComputedStyle(el).maxWidth}));
   near(art.w,44*2.75*1.35,'castle art width enlarged35%');near(art.h,44*2.8*1.35,'castle art height enlarged35%');assert.equal(art.max,'none');
   const rally=page.locator('.atlas-marker[data-atlas-target="monsters:5"]'),rallyBox=await rally.boundingBox();assert.equal(await rally.getAttribute('data-footprint'),'2','every rally monster uses a 2x2 footprint even without catalogue metadata');near(rallyBox.width,88,'rally hitbox width');near(rallyBox.height,88,'rally hitbox height');
   for(let y=0;y<3;y++)for(let x=0;x<3;x++){
    await page.mouse.click(box.x+22+x*44,box.y+22+y*44);
    assert.equal(await home.getAttribute('aria-pressed'),'true','each occupied tile selects the same city');
    assert.equal(await page.locator('.atlas-cell-focus').getAttribute('data-x'),'83','city anchor is preserved');
    assert(await page.locator('.atlas-target-actions [data-action="city-skins"]').isVisible(),'city actions open for each tile');
    await vp.press('Escape');
   }
   await home.click();
   const ownMenu=page.locator('.atlas-target-actions.is-own-village'),ownBanner=ownMenu.locator('.atlas-village-banner'),ownButtons=ownMenu.locator('.atlas-actions-buttons');
   const ownBox=await ownMenu.boundingBox(),ownArt=await home.locator('img').boundingBox(),bannerBox=await ownBanner.boundingBox(),buttonsBox=await ownButtons.boundingBox();
   assert(ownBox.y>=ownArt.y+ownArt.height-1,`own actions stay below castle art: ${JSON.stringify({ownBox,ownArt})}`);
   assert(bannerBox.y+bannerBox.height<=buttonsBox.y+1,'own name stays above the icon dock');
   assert(buttonsBox.width<=170&&buttonsBox.height<=60,`own icon dock stays compact: ${JSON.stringify(buttonsBox)}`);
   for(const label of ['Profil','Skin','Dorfübersicht']){const action=page.getByRole('button',{name:label,exact:true}),actionBox=await action.boundingBox();assert(actionBox.width>=40&&actionBox.height>=40,`${label} remains touchable`);assert.equal(await action.locator('span').last().isVisible(),false,`${label} has no visible text`);}
   const focus=await page.locator('.atlas-cell-focus').boundingBox();near(focus.x,box.x,'highlight left');near(focus.y,box.y,'highlight top');near(focus.width,box.width,'highlight width');
   await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).focus();await page.keyboard.press('Enter');
   const alignment=await page.evaluate(()=>({canvas:document.querySelector('.atlas-terrain').getBoundingClientRect().toJSON(),ground:ground.filter(x=>x[3]==='phoenix').at(-1),mini:miniRects.filter(x=>x[4]==='#f9db7b').at(-1),routes:[...document.querySelectorAll('.atlas-routes line')].map(l=>['x1','y1','x2','y2'].map(n=>Number(l.getAttribute(n))))}));
   await page.keyboard.press('Escape');
   near(alignment.ground[0]+alignment.canvas.x,viewport.width/2,'settlement ground screen x');near(alignment.ground[1]+alignment.canvas.y,viewport.height/2,'settlement ground screen y');const ratio=160/255;near(alignment.mini[0]+alignment.mini[2]/2,83*ratio,'minimap visual center x');near(alignment.mini[1]+alignment.mini[3]/2,70*ratio,'minimap visual center y');near(alignment.mini[2],3*ratio,'minimap three-tile footprint');
   for(const [i,expected] of [[0,[0,0,220,0]],[1,[0,132,0,0]],[2,[0,0,748,0]],[3,[0,0,132,0]]])for(let n=0;n<4;n++)near(alignment.routes[i][n],expected[n]+(n%2?viewport.height:viewport.width)/2,'march endpoint '+i+':'+n);
   assert.equal(await page.locator('.atlas-march-party').first().locator('.atlas-party-type').count(),3,'mixed marching army retained');
   await page.locator('.atlas-marker[data-atlas-target="monsters:4"]').click();assert.equal(await page.locator('.atlas-target-actions [data-action="expedition"]').getAttribute('data-kind'),'monsters','adjacent monster remains clickable');await vp.press('Escape');
   await page.locator('.atlas-marker[data-atlas-target="nodes:3"]').click();assert.equal(await page.locator('.atlas-target-actions [data-action="expedition"]').getAttribute('data-kind'),'nodes','adjacent resource remains clickable');await vp.press('Escape');
   await vp.press('Home');await page.mouse.click(box.x-22,box.y+66);assert.equal(await page.locator('.atlas-cell-focus').getAttribute('data-x'),'81','next empty tile remains selectable');near((await page.locator('.atlas-cell-focus').boundingBox()).width,44,'empty selection one tile');
   const emptyFocus=await page.locator('.atlas-cell-focus').boundingBox();await page.mouse.click(emptyFocus.x+emptyFocus.width/2,emptyFocus.y+emptyFocus.height/2);assert(await page.locator('.atlas-target-actions').isHidden(),'second tap on the selected empty tile closes its actions');
   await page.mouse.click(emptyFocus.x+emptyFocus.width/2,emptyFocus.y+emptyFocus.height/2);assert(await page.locator('.atlas-target-actions').isVisible(),'empty tile actions reopen');await page.mouse.click(48,110);assert(await page.locator('.atlas-target-actions').isHidden(),'tap elsewhere on the map closes empty tile actions');
   await page.mouse.click(emptyFocus.x+emptyFocus.width/2,emptyFocus.y+emptyFocus.height/2);await page.getByRole('button',{name:'Koordinaten und Weltübersicht öffnen',exact:true}).click();assert(await page.locator('.atlas-target-actions').isHidden(),'tap on another screen control closes empty tile actions');assert(await page.locator('#atlas-navigation-panel').isVisible(),'the tapped screen control still works');await vp.press('Escape');
   if(viewport.width===741){
    await page.mouse.click(610,700);assert.match(await page.locator('.atlas-actions-heading span').innerText(),/^Freies Feld · /);
    const actionBox=await page.locator('.atlas-target-actions').boundingBox(),railBox=await page.getByRole('button',{name:'Feldzüge öffnen'}).boundingBox();
    assert(actionBox.width<=190&&actionBox.height<=105,`empty-field actions stay compact: ${JSON.stringify(actionBox)}`);
    assert(actionBox.x+actionBox.width<=railBox.x-9,`empty-field actions overlap right HUD rail: ${JSON.stringify({actionBox,railBox})}`);await vp.press('Escape');
   }
   await vp.focus();await vp.press('ArrowRight');near((await home.boundingBox()).x,box.x-44,'keyboard moves exactly one tile from half-tile center');await vp.press('Home');near((await home.boundingBox()).x,box.x,'home restores center');
   await vp.press('+');const zoomed=await home.boundingBox();near(zoomed.width,132*1.15,'zoom preserves footprint ratio');await page.evaluate(()=>ConquerWorld.render(options));near((await home.boundingBox()).width,zoomed.width,'poll refresh preserves zoom');
   if(viewport.width===1280){const neighbour=page.locator('.atlas-marker[data-atlas-target="players:2"]');await neighbour.click();assert.equal(await neighbour.getAttribute('aria-pressed'),'true');assert.equal(await page.locator('.atlas-cell-focus').getAttribute('data-x'),'88');}
   await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}.png`)});assert.deepEqual(errors,[]);await page.close();
   console.log(`PASS ${viewport.width}x${viewport.height}: nine village hits, 3x3 villages, neighbours, empty tile, unchanged castle art, highlight/ground/minimap/routes, zoom/polling/keyboard.`);
  }
  console.log('Screenshots: '+output);
 }finally{if(browser)await browser.close();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exitCode=1;});
