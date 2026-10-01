'use strict';
// Isolated picker fixture: no live API, account, database, or browser state.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert');
const {pathToFileURL}=require('url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-skin-picker-'));
const styles=[...fs.readFileSync(root+'/views/game.php','utf8').matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(m=>fs.readFileSync(root+'/assets/css/'+m[1]+'.css','utf8')).join('\n');
const js=['castle-skins.js','march-skins.js','village-menu.js'].map(f=>fs.readFileSync(root+'/assets/js/'+f,'utf8')).join('\n');
fs.writeFileSync(out+'/fixture.html',`<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style><body class="mobile-game"><dialog id="game-dialog"><button class="dialog-close" aria-label="Schließen">×</button><div id="dialog-content"></div></dialog><script>${js}</script><script>window.sent=[];window.profile={display_name:'Eichenwacht',city_skin:'forest'};window.village=ConquerVillage({base:${JSON.stringify(pathToFileURL(root).href)},esc:v=>String(v??''),fmt:String,getState:()=>({player:{name:'Eichenwacht'},city:{id:1,coord_x:83,coord_y:70,castle_level:2},players:[]}),getKingdom:()=>({profile}),openDialog:html=>{const d=document.querySelector('#game-dialog'),c=document.querySelector('#dialog-content');d.querySelector('.popup-heading')?.remove();c.innerHTML=html;const heading=c.querySelector('h2');const header=document.createElement('header');header.className='popup-heading';header.append(heading);d.insertBefore(header,c);d.classList.add('has-popup-heading');if(!d.open)d.showModal();},navigate(){},action:async(...args)=>{sent.push(args)},toast(){},marchPanel:{}});document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)village.onClick(b.dataset.action,b)});window.openSkins=()=>village.onClick('city-skins',{dataset:{}});openSkins();</script></body></html>`);
(async()=>{const browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})}),page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));try{
 await page.goto(pathToFileURL(out+'/fixture.html').href);
 assert.deepEqual(await page.locator('.skin-option').evaluateAll(es=>es.map(e=>e.dataset.id)),['default','forest','fire','water','wind']);
 await page.getByRole('button',{name:'Legendär 4',exact:true}).click();assert.equal(await page.locator('.skin-option').count(),4);
 await page.locator('.skin-option[data-id="fire"]').click();assert.equal((await page.evaluate(()=>sent))[0][1].city_skin,'fire');
 await page.evaluate(()=>village.onClick('city-skin-save',{dataset:{id:'untrusted'}}));assert.equal(await page.evaluate(()=>sent.length),1);
 for(const viewport of [{width:390,height:844},{width:320,height:568},{width:568,height:320},{width:1280,height:720}]){
  await page.setViewportSize(viewport);await page.evaluate(()=>openSkins());
  for(const option of await page.locator('.skin-option').all()){await option.scrollIntoViewIfNeeded();assert(await option.isVisible());const box=await option.boundingBox();assert(box.x>=0&&box.x+box.width<=viewport.width+1);}
  assert(await page.locator('.skin-options').evaluate(el=>el.scrollWidth<=el.clientWidth+1));
  await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'.png'});console.log('PASS '+viewport.width+'x'+viewport.height+' five active skin controls scroll into view');
 }
 await page.getByRole('button',{name:'Legendär 4',exact:true}).click();
 const portrait=page.locator('.castle-skin-portrait img').first();await page.waitForFunction(()=>document.querySelector('.castle-skin-portrait img').currentSrc.includes('.webp?'));
 await page.emulateMedia({reducedMotion:'reduce'});await page.waitForFunction(()=>document.querySelector('.castle-skin-portrait img').currentSrc.includes('.png?'));
 await page.emulateMedia({reducedMotion:'no-preference'});await page.waitForFunction(()=>document.querySelector('.castle-skin-portrait img').currentSrc.includes('.webp?'));
 await page.evaluate(()=>{profile.city_skin='dragon';openSkins()});assert.equal(await page.locator('.skin-option[data-id="dragon"][aria-pressed="true"]').count(),1);assert.match(await page.locator('[data-id="dragon"] img').getAttribute('src'),/castle-dragon/);
 assert.equal(await page.locator('[data-id="phoenix"]').count(),0,'unowned retired cosmetics stay outside current collection');
 assert.deepEqual(errors,[]);console.log('PASS selected historical castle retains its portrait; invalid input and reduced-motion contracts; '+out);
 }finally{await browser.close()}})().catch(e=>{console.error(e);process.exitCode=1});
