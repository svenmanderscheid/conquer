'use strict';

// Isolated browser regression: all march data stays inside the existing fixture.
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const fixture=require('./fixtures/world_march_motion.cjs');
const root=path.resolve(__dirname,'..');

const premium=Object.freeze({
 ironkeep:'heavy-walk',rosehall:'gallop',sandspire:'crawl',tidewatch:'swim',
 winterhold:'heavy-walk',jadecourt:'gallop',emberforge:'crawl',ravenloft:'fly',
 clockwork:'hop',sapphire:'prance',phoenix:'fly',dragon:'fly',astral:'glide',
 leviathan:'swim',yggdrasil:'heavy-walk',tempest:'hover',eclipse:'glide'
});
const expected=Object.freeze({default:'walk',...premium});
const ids=Object.keys(expected);
const viewports=Object.freeze([
 {name:'desktop',width:1280,height:800},
 {name:'phone-390',width:390,height:844},
 {name:'phone-320',width:320,height:568},
 {name:'landscape',width:844,height:390}
]);

const contentType=file=>file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':'application/octet-stream';

(async()=>{
 const server=http.createServer((request,response)=>{
  const pathname=decodeURIComponent(new URL(request.url,'http://localhost').pathname);
  if(pathname==='/'){response.setHeader('Content-Type','text/html');response.end(fixture(root));return;}
  if(pathname==='/favicon.ico'){response.writeHead(204);response.end();return;}
  const file=path.resolve(root,'.'+pathname);
  if(!pathname.startsWith('/assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file)){response.writeHead(404);response.end();return;}
  response.setHeader('Content-Type',contentType(file));response.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 let browser;
 try{
  browser=await chromium.launch({headless:true,channel:'chrome'});
  for(const viewport of viewports){
   const page=await browser.newPage({viewport:{width:viewport.width,height:viewport.height},serviceWorkers:'block'});
   const errors=[];
   page.on('pageerror',error=>errors.push(`pageerror: ${error.message}`));
   page.on('console',message=>{if(message.type()==='error')errors.push(`console: ${message.text()}`);});
   page.on('requestfailed',request=>errors.push(`requestfailed: ${request.url()} (${request.failure()?.errorText||'unknown'})`));

   await page.goto(`http://127.0.0.1:${server.address().port}`,{waitUntil:'domcontentloaded'});
   await page.evaluate(({ids})=>{
    demoAuto=false;
    document.querySelector('#skin').value='default';
    startMarch(5,12000);
    const seed=options.state.marches[0],now=options.now();
    options.state.marches=ids.map((skin,index)=>({
     ...seed,id:index+1,march_skin:skin,
     departure_time:new Date(now-2000-index*13).toISOString(),
     arrival_time:new Date(now+10000-index*13).toISOString(),
     return_time:new Date(now+22000-index*13).toISOString()
    }));
    ConquerWorld.render(options);
   },{ids});

   await page.waitForFunction(count=>document.querySelectorAll('.atlas-march-party.is-skinned').length===count,ids.length);
   await page.waitForFunction(count=>document.querySelectorAll('.atlas-march-party.is-moving').length===count,ids.length);

   const catalog=await page.evaluate(ids=>Object.fromEntries(ids.map(id=>[id,ConquerMarchSkins.locomotion(id)])),ids);
   assert.deepEqual(catalog,expected,`${viewport.name}: catalog locomotion contract changed`);

   const motion=await page.evaluate(async ids=>{
    const samples=Object.fromEntries(ids.map(id=>[id,{actor:[],image:[]}]))
    const initial=Object.fromEntries(ids.map(id=>{
     const actor=document.querySelector(`.atlas-march-party[data-march-skin="${id}"]`),image=actor?.querySelector('.atlas-party-skin img');
     return [id,{locomotion:actor?.dataset.locomotion||'',animationName:image?getComputedStyle(image).animationName:'',animationDuration:image?getComputedStyle(image).animationDuration:'',source:image?.currentSrc||image?.src||'',articulated:actor?.classList.contains('has-motion-animation')||false}];
    }));
    await new Promise(resolve=>{
     let started;
     const frame=time=>{
      started??=time;
      for(const id of ids){
       const actor=document.querySelector(`.atlas-march-party[data-march-skin="${id}"]`),image=actor.querySelector('.atlas-party-skin img');
       samples[id].actor.push(actor.style.transform);
       samples[id].image.push(getComputedStyle(image).transform);
      }
      if(time-started<720)requestAnimationFrame(frame);else resolve();
     };
     requestAnimationFrame(frame);
    });
    return {initial,samples};
   },ids);

   for(const id of ids){
    const state=motion.initial[id],samples=motion.samples[id];
    assert.equal(state.locomotion,expected[id],`${viewport.name}/${id}: wrong DOM locomotion category`);
    assert(new Set(samples.actor).size>4,`${viewport.name}/${id}: authoritative march transform did not advance`);
    if(id!=='default'){
     assert.equal(state.articulated,true,`${viewport.name}/${id}: articulated motion marker missing`);
     if(['phoenix','dragon'].includes(id))assert.match(state.source,/flight-(phoenix|dragon)\.webp/,`${viewport.name}/${id}: moving flight sprite not loaded`);
     else assert.match(state.source,new RegExp(`animated-march-${id}\\.webp`),`${viewport.name}/${id}: articulated gait not loaded`);
     assert.equal(state.animationName,'none',`${viewport.name}/${id}: animated sprite must not also receive a CSS body transform`);
     continue;
    }
    assert.notEqual(state.animationName,'none',`${viewport.name}/${id}: locomotion animation missing`);
    assert.notEqual(state.animationDuration,'0s',`${viewport.name}/${id}: locomotion animation has no duration`);
    assert(new Set(samples.image).size>2,`${viewport.name}/${id}: locomotion produces no visible transform change`);
   }

   await page.emulateMedia({reducedMotion:'reduce'});
   await page.waitForFunction(count=>[...document.querySelectorAll('.atlas-march-party')].length===count&&[...document.querySelectorAll('.atlas-march-party')].every(actor=>!actor.classList.contains('is-moving')),ids.length);
   const reduced=await page.evaluate(ids=>Object.fromEntries(ids.map(id=>{
    const actor=document.querySelector(`.atlas-march-party[data-march-skin="${id}"]`),image=actor.querySelector('.atlas-party-skin img'),trail=actor.querySelector('.atlas-march-trail');
    return [id,{animationName:getComputedStyle(image).animationName,trailDisplay:getComputedStyle(trail).display,source:image.currentSrc||image.src}];
   })),ids);
   for(const id of ids){
    assert.equal(reduced[id].animationName,'none',`${viewport.name}/${id}: system reduced motion leaves an animation running`);
    assert.equal(reduced[id].trailDisplay,'none',`${viewport.name}/${id}: system reduced motion leaves the wake visible`);
   }
   assert.match(reduced.phoenix.source,/flight-phoenix\.png/,`${viewport.name}: phoenix reduced-motion still image missing`);
   assert.match(reduced.dragon.source,/flight-dragon\.png/,`${viewport.name}: dragon reduced-motion still image missing`);
   for(const id of Object.keys(premium).filter(id=>!['phoenix','dragon'].includes(id)))assert.match(reduced[id].source,new RegExp(`march-${id}\\.webp`),`${viewport.name}/${id}: reduced motion must use the static fallback`);

   if(viewport.name==='desktop'){
    await page.emulateMedia({reducedMotion:'no-preference'});
    await page.evaluate(()=>document.body.classList.add('reduced-motion'));
    await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-march-party')].every(actor=>!actor.classList.contains('is-moving')));
    const gameReduced=await page.evaluate(()=>[...document.querySelectorAll('.atlas-march-party')].every(actor=>getComputedStyle(actor.querySelector('.atlas-party-skin img')).animationName==='none'&&getComputedStyle(actor.querySelector('.atlas-march-trail')).display==='none'));
    assert.equal(gameReduced,true,'in-game reduced motion leaves locomotion or wakes running');
   }

   assert.deepEqual(errors,[],`${viewport.name}: browser errors`);
   await page.close();
   console.log(`PASS ${viewport.name} ${viewport.width}x${viewport.height}: ${Object.keys(premium).length} premium skins, ${new Set(Object.values(expected)).size} locomotion categories, travel transforms and reduced motion.`);
  }
  console.log('PASS march locomotion regression in Chrome.');
 }finally{
  if(browser)await browser.close();
  server.closeAllConnections();
  await new Promise(resolve=>server.close(resolve));
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
