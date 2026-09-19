'use strict';
const fs=require('fs'),path=require('path'),http=require('http'),os=require('os'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const fixture=require('./fixtures/world_life.cjs'),root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-life-'));
(async()=>{
 const errors=[],requests=[];let browser;
 const server=http.createServer((req,res)=>{const pathname=new URL(req.url,'http://localhost').pathname;requests.push(pathname);if(pathname==='/'){res.setHeader('Content-Type','text/html');res.end(fixture(root));return;}const file=path.resolve(root,'.'+pathname);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',({'.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml','.png':'image/png','.webp':'image/webp'})[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 try{
  browser=await chromium.launch({headless:true,channel:'chrome'});const page=await browser.newPage({viewport:{width:1440,height:900}});page.on('pageerror',e=>errors.push(e.message));
  await page.goto(`http://127.0.0.1:${server.address().port}`);await page.waitForFunction(()=>document.querySelector('.atlas-marker--nodes img[data-life-kind]'));
  await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker img:not([hidden])')].every(i=>i.complete&&i.naturalWidth));
  const marker=key=>page.locator(`[data-atlas-target="${key}"].atlas-marker`),farm=marker('nodes:1'),orc=marker('monsters:1');
  assert.equal(await farm.getAttribute('data-footprint'),'1');assert.equal(await orc.getAttribute('data-footprint'),'1');
  for(const [id,key]of [[1,'orc'],[2,'skeleton'],[3,'golem'],[4,'goblin']])assert.equal(await marker('monsters:'+id).locator('img').getAttribute('data-life-kind'),key,key+' keeps its identity');
  for(const [id,key]of [[10,'frostgrimm'],[11,'sandmaul'],[12,'glutramm']])assert((await marker('monsters:'+id).locator('img').getAttribute('src')).includes(key),'regional boss keeps original illustration');
  assert((await marker('monsters:13').locator('img').getAttribute('src')).includes('daemmerhorn'),'Deathkar replacement uses Dämmerhorn illustration');
  assert.equal(await marker('monsters:13').getAttribute('data-footprint'),'2','Dämmerhorn is a two-by-two rally target');
  const before=await orc.boundingBox(),pictureA=await orc.screenshot();await page.waitForTimeout(560);const pictureB=await orc.screenshot();assert(!pictureA.equals(pictureB),'monster parts visibly animate');assert.deepEqual(await orc.boundingBox(),before,'monster hitbox stays fixed');
  const cropA=await farm.screenshot();await page.waitForTimeout(520);assert(!cropA.equals(await farm.screenshot()),'farm parts visibly animate');
  const offscreenFarm=marker('nodes:3');assert((await offscreenFarm.locator('img').getAttribute('src')).includes('.png'),'offscreen farms use still artwork');
  await page.evaluate(()=>{window.groundPaints=0;const ground=ConquerLandscape.ground;ConquerLandscape.ground=(...args)=>{groundPaints++;return ground(...args);};});
  const waterA=await page.locator('.atlas-atmosphere').evaluate(el=>el.toDataURL());await page.waitForTimeout(550);const waterB=await page.locator('.atlas-atmosphere').evaluate(el=>el.toDataURL());assert.notEqual(waterA,waterB,'water lights move');assert.equal(await page.evaluate(()=>groundPaints),0,'ambient animation does not repaint terrain or minimap');
  await page.emulateMedia({reducedMotion:'reduce'});await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker img[data-life-kind]')].every(img=>img.src.includes('.png')&&img.complete&&img.naturalWidth));await page.waitForTimeout(240);
  const stillA=await farm.screenshot();await page.waitForTimeout(400);const stillB=await farm.screenshot();if(!stillA.equals(stillB)){fs.writeFileSync(path.join(output,'still-farm-a.png'),stillA);fs.writeFileSync(path.join(output,'still-farm-b.png'),stillB);}assert(stillA.equals(stillB),'OS reduced motion freezes farm');
  const stillSources=await page.locator('.atlas-marker img[data-life-kind]').evaluateAll(els=>els.map(el=>el.src));assert(stillSources.length>0&&stillSources.every(src=>src.includes('.png')),'OS reduced motion switches all creatures and farms to still images');
  await page.emulateMedia({reducedMotion:'no-preference'});await page.locator('#preview-motion').click();await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker img[data-life-kind]')].every(img=>img.src.includes('.png')&&img.complete&&img.naturalWidth));await page.waitForTimeout(240);
  const stillOrc=await orc.screenshot();await page.waitForTimeout(400);assert(stillOrc.equals(await orc.screenshot()),'in-game reduced motion freezes monster');await page.locator('#preview-motion').click();
  await page.evaluate(()=>ConquerWorld.focus(255,128));await page.waitForTimeout(120);
  const edgeColors=await page.locator('.atlas-terrain').evaluate(canvas=>{const c=canvas.getContext('2d'),ratio=canvas.width/canvas.clientWidth,left=-parseFloat(canvas.style.left||0),start=Math.round((innerWidth*.58+left)*ratio),end=Math.round((innerWidth-8+left)*ratio),data=c.getImageData(start,0,Math.max(1,end-start),canvas.height).data,colors=new Set();for(let i=0;i<data.length;i+=64)if(data[i+3]>200)colors.add(`${data[i]>>4}:${data[i+1]>>4}:${data[i+2]>>4}`);return colors.size;});
  await page.screenshot({path:path.join(output,'1440-world-edge.png')});
  assert(edgeColors>=2,'the world edge is a layered cloud bank, not a flat outside fill');
  const sampleClouds=()=>page.locator('.atlas-terrain').evaluate(canvas=>{const c=canvas.getContext('2d'),ratio=canvas.width/canvas.clientWidth,padX=-parseFloat(canvas.style.left||0),padY=-parseFloat(canvas.style.top||0),x=Math.round((innerWidth*.53+padX)*ratio),sampleWidth=Math.round(innerWidth*.11*ratio),rows=[];for(let screenY=20;screenY<innerHeight-20;screenY+=4){const data=c.getImageData(x,Math.round((screenY+padY)*ratio),sampleWidth,1).data;let total=0,count=0;for(let i=0;i<data.length;i+=32){total+=data[i]+data[i+1]+data[i+2];count++;}rows.push(total/Math.max(1,count));}return rows;});
  const cloudBefore=await sampleClouds();await page.evaluate(()=>ConquerWorld.focus(255,129));await page.waitForTimeout(120);const cloudAfter=await sampleClouds(),shift=Math.round(44/4);
  const meanDifference=offset=>cloudAfter.slice(0,-shift).reduce((sum,value,index)=>sum+Math.abs(value-cloudBefore[index+offset]),0)/(cloudAfter.length-shift);
  assert(meanDifference(shift)<meanDifference(0),'cloud shapes stay anchored to world coordinates while the map moves');
  await page.evaluate(()=>ConquerWorld.focus(255,128));await page.waitForTimeout(80);
  await page.setViewportSize({width:390,height:844});await page.waitForTimeout(120);await page.screenshot({path:path.join(output,'390-world-edge.png')});
  await page.setViewportSize({width:1440,height:900});await page.evaluate(()=>ConquerWorld.focus(255,255));await page.waitForTimeout(120);await page.screenshot({path:path.join(output,'1440-world-corner.png')});
  for(const size of [{width:1440,height:900},{width:390,height:844},{width:320,height:700},{width:844,height:390}]){
   await page.setViewportSize(size);await page.waitForTimeout(150);await page.screenshot({path:path.join(output,`${size.width}-world.png`)});
   // Select the exact one-tile object after coordinate navigation.
   await page.evaluate(()=>ConquerWorld.focus(66,70));await farm.click();
   const actions=page.getByRole('group',{name:'Zielaktionen'});assert(await actions.isVisible());const box=await actions.boundingBox();assert(box.x>=0&&box.x+box.width<=size.width+1&&box.y>=0&&box.y+box.height<=size.height+1,'farm actions fit '+size.width);
   await page.screenshot({path:path.join(output,`${size.width}-farm.png`)});await page.keyboard.press('Escape');
   await page.evaluate(()=>ConquerWorld.focus(74,70));await orc.click();assert(await actions.getByRole('button',{name:'Angreifen',exact:true}).isVisible());await page.screenshot({path:path.join(output,`${size.width}-monster.png`)});await page.keyboard.press('Escape');
   const daemmerhorn=marker('monsters:13');await page.evaluate(()=>ConquerWorld.focus(82.5,76.5));await daemmerhorn.click();assert(await actions.getByRole('button',{name:'Rally starten',exact:true}).isVisible());assert.match(await daemmerhorn.getAttribute('aria-label'),/Dämmerhorn.*Rally.*2 mal 2 Felder/);await page.screenshot({path:path.join(output,`${size.width}-daemmerhorn.png`)});await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);assert(!requests.some(url=>url.startsWith('/api/')));console.log('PASS encounter identity, visible motion, stable hitboxes, OS/game motion settings, cached ground and responsive actions');console.log('Screenshots '+output);
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);console.error('QA output '+output);process.exitCode=1;});
