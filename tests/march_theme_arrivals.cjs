'use strict';
// Isolated browser fixture: exercises vector-only arrival art and never calls game APIs.
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),fixture=require('./fixtures/world_march_motion.cjs'),output=path.join(root,'artifacts/march-theme-arrivals-20260913');fs.mkdirSync(output,{recursive:true});
const themes=['ironkeep','rosehall','sandspire','tidewatch','winterhold','jadecourt','emberforge','ravenloft','clockwork','sapphire','astral','leviathan','yggdrasil','tempest','eclipse'];

(async()=>{
 const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture(root));return;}const file=path.resolve(root,'.'+name);if(!name.startsWith('/assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto('http://127.0.0.1:'+server.address().port);await page.evaluate(()=>{demoAuto=false;window.paintCalls=0;const ground=ConquerLandscape.ground;ConquerLandscape.ground=(...args)=>{paintCalls++;return ground(...args)};});
  const api=await page.evaluate(()=>({ids:ConquerMarchEffects.ids,defaultEffect:ConquerMarchEffects.create('default')}));assert.deepEqual(api.ids.sort(),['phoenix','dragon',...themes].sort(),'all 17 premium themes expose a dedicated arrival factory');assert.equal(api.defaultEffect,null,'the free standard march keeps the restrained generic impact');

  const frames=await page.evaluate(ids=>ids.map(id=>{const effect=ConquerMarchEffects.create(id,{biome:'forest'});effect.paint(effect.duration*.31);const signature=effect.canvas.toDataURL();effect.dispose();return{id,signature,width:effect.canvas.width,height:effect.canvas.height};}),themes);
  assert.equal(new Set(frames.map(frame=>frame.signature)).size,themes.length,'every theme paints a distinct silhouette');assert(frames.every(frame=>frame.width===1&&frame.height===1),'dispose releases every backing canvas');

  for(const id of themes){
   await page.selectOption('#skin',id);const before=await page.evaluate(()=>{startMarch(5,45);return paintCalls;});await page.waitForSelector(`.atlas-${id}-impact`,{timeout:1500});
   const state=await page.locator(`.atlas-${id}-impact`).evaluate(canvas=>({theme:canvas.dataset.marchTheme,hidden:canvas.getAttribute('aria-hidden'),pixels:[canvas.width,canvas.height],count:document.querySelectorAll('.atlas-march-impact').length}));
   assert.equal(state.theme,id);assert.equal(state.hidden,'true');assert(state.pixels[0]>1&&state.pixels[1]>1);assert(state.count<=4,'world effect budget exceeded while cycling themes');
   assert.equal(await page.evaluate(()=>paintCalls),before,`${id} arrival redrew world terrain`);
  }
  await page.waitForTimeout(2050);assert.equal(await page.locator('.atlas-themed-impact').count(),0,'all themed effects are removed after their afterglow');

  await page.emulateMedia({reducedMotion:'reduce'});await page.selectOption('#skin','astral');await page.evaluate(()=>startMarch(5,40));await page.waitForTimeout(180);assert.equal(await page.locator('.atlas-themed-impact').count(),0,'reduced motion suppresses themed arrivals');
  await page.emulateMedia({reducedMotion:'no-preference'});await page.selectOption('#skin','ironkeep');await page.evaluate(()=>startMarch(5,40));await page.waitForSelector('.atlas-ironkeep-impact');
  const released=await page.locator('.atlas-ironkeep-impact').evaluate(canvas=>{window.remountCanvas=canvas;const next=document.createElement('main');next.id='remount';next.style.height='500px';document.body.append(next);options.host=next;ConquerWorld.render(options);return true;});assert(released);assert.deepEqual(await page.evaluate(()=>[remountCanvas.width,remountCanvas.height,remountCanvas.isConnected]),[1,1,false],'remount removes the effect and releases its backing canvas');
  await page.setViewportSize({width:1280,height:860});await page.evaluate(ids=>{const grid=document.createElement('section');grid.id='arrival-review';Object.assign(grid.style,{position:'fixed',inset:'0',zIndex:'9999',display:'grid',gridTemplateColumns:'repeat(5,1fr)',gap:'6px',padding:'12px',background:'#ddd1b8',overflow:'hidden'});window.reviewEffects=[];for(const id of ids){const card=document.createElement('div'),label=document.createElement('strong'),effect=ConquerMarchEffects.create(id,{biome:'forest'});Object.assign(card.style,{display:'grid',placeItems:'center',minWidth:'0',border:'2px solid #5c4270',borderRadius:'12px',background:'#485947',overflow:'hidden'});Object.assign(label.style,{color:'#fff0c4',font:'700 16px serif'});label.textContent=id;effect.paint(effect.duration*.31);Object.assign(effect.canvas.style,{position:'static',width:'210px',height:'171px'});card.append(label,effect.canvas);grid.append(card);reviewEffects.push(effect);}document.body.append(grid);},themes);await page.screenshot({path:path.join(output,'collection.png')});await page.evaluate(()=>reviewEffects.forEach(effect=>effect.dispose()));
  assert.deepEqual(errors,[]);console.log('PASS 15 theme-specific vector arrivals, distinct frames, four-effect budget, reduced motion and canvas cleanup.');
 }finally{if(browser)await browser.close();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
