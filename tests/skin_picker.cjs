'use strict';
// Isolated picker fixture: no live API, account, database, or browser state.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert');
const {pathToFileURL}=require('url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-skin-picker-'));
const styles=[...fs.readFileSync(root+'/views/game.php','utf8').matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(m=>fs.readFileSync(root+'/assets/css/'+m[1]+'.css','utf8')).join('\n');
const js=['castle-skins.js','march-skins.js','village-menu.js'].map(f=>fs.readFileSync(root+'/assets/js/'+f,'utf8')).join('\n');
fs.writeFileSync(out+'/fixture.html',`<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style><body class="mobile-game"><dialog id="game-dialog"><button class="dialog-close" aria-label="Schließen">×</button><div id="dialog-content"></div></dialog><script>${js}</script><script>window.sent=[];window.profile={display_name:'Eichenwacht',city_skin:'ironkeep'};window.village=ConquerVillage({base:${JSON.stringify(pathToFileURL(root).href)},esc:v=>String(v??''),fmt:String,getState:()=>({player:{name:'Eichenwacht'},city:{id:1,coord_x:83,coord_y:70,castle_level:2},players:[]}),getKingdom:()=>({profile}),openDialog:html=>{const d=document.querySelector('#game-dialog'),c=document.querySelector('#dialog-content');d.querySelector('.popup-heading')?.remove();c.innerHTML=html;const heading=c.querySelector('h2');const header=document.createElement('header');header.className='popup-heading';header.append(heading);d.insertBefore(header,c);d.classList.add('has-popup-heading');if(!d.open)d.showModal();},navigate(){},action:async(...args)=>{sent.push(args)},toast(){},marchPanel:{}});document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)village.onClick(b.dataset.action,b)});window.openSkins=()=>village.onClick('city-skins',{dataset:{}});openSkins();</script></body></html>`);
(async()=>{const browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})}),page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));try{
 await page.goto(pathToFileURL(out+'/fixture.html').href);
 const seen=new Set();for(let p=0;p<5;p++){for(const id of await page.locator('.skin-option').evaluateAll(es=>es.map(e=>e.dataset.id)))seen.add(id);if(p<4)await page.getByRole('button',{name:'Nächste Skin-Seite'}).click();}
 assert.equal(seen.size,18);console.log('PASS all 18 skins reachable through five bounded pages');
 await page.getByRole('button',{name:'Mythisch 7',exact:true}).click();
 assert.deepEqual(await page.locator('.skin-option').evaluateAll(es=>es.map(e=>e.dataset.id)),['phoenix','astral','leviathan','yggdrasil']);
 assert.equal(await page.getByRole('button',{name:'Nächste Skin-Seite'}).isDisabled(),false);
 const mythics=new Set();for(let p=0;p<2;p++){for(const id of await page.locator('.skin-option').evaluateAll(es=>es.map(e=>e.dataset.id)))mythics.add(id);if(p<1)await page.getByRole('button',{name:'Nächste Skin-Seite'}).click();}
 assert.equal(mythics.size,7);assert(mythics.has('phoenix')&&mythics.has('eclipse')&&mythics.has('dragon'));assert(await page.getByRole('button',{name:'Nächste Skin-Seite'}).isDisabled());console.log('PASS seven mythic skins across two pages and pagination boundaries');
 const dragon=page.locator('.skin-option[data-id="dragon"]');assert.equal(await dragon.locator('strong').innerText(),'Drachenfestung');assert.match(await dragon.locator('img').getAttribute('src'),/castle-dragon\.webp\?/);console.log('PASS dragon fortress has its animated mythic portrait');
 await page.getByRole('button',{name:'Mythisch 7',exact:true}).click();
 await page.locator('.skin-option[data-id="phoenix"]').click();const sent=await page.evaluate(()=>window.sent);assert.equal(sent.length,1);assert.equal(sent[0][1].city_skin,'phoenix');
 await page.evaluate(()=>{village.onClick('city-skin-save',{dataset:{id:'untrusted'}})});assert.equal(await page.evaluate(()=>sent.length),1);console.log('PASS valid save identifier and unknown identifier blocked');
 await page.evaluate(()=>{profile.city_skin='phoenix';openSkins()});assert(await page.locator('.skin-option[data-id="phoenix"][aria-pressed="true"]').count());console.log('PASS opening picker goes directly to saved skin page');
 for(const viewport of [{width:390,height:844},{width:320,height:568},{width:568,height:320},{width:768,height:1024},{width:1280,height:720}]){
  await page.setViewportSize(viewport);await page.evaluate(()=>{profile.city_skin='ironkeep';openSkins()});
  for(const filter of ['Alle 18','Legendär 10','Mythisch 7']){
   await page.getByRole('button',{name:filter,exact:true}).click();
   do {
   const metrics=await page.evaluate(()=>{const d=document.querySelector('#game-dialog').getBoundingClientRect();const bad=[];for(const el of document.querySelectorAll('#game-dialog,#dialog-content,.castle-skin-picker,.skin-options,.skin-option'))if(el.scrollHeight>el.clientHeight+1||el.scrollWidth>el.clientWidth+1)bad.push({className:el.className,scrollHeight:el.scrollHeight,clientHeight:el.clientHeight,scrollWidth:el.scrollWidth,clientWidth:el.clientWidth});const outside=[...document.querySelectorAll('#game-dialog button')].filter(el=>{const r=el.getBoundingClientRect();return r.top<d.top-1||r.left<d.left-1||r.right>d.right+1||r.bottom>d.bottom+1}).map(el=>el.textContent);return{bad,outside,frame:{x:d.x,y:d.y,width:d.width,height:d.height},portraits:[...document.querySelectorAll('.skin-option .castle-skin-portrait')].map(e=>e.clientHeight)}});
   assert.deepEqual(metrics.bad,[],JSON.stringify({viewport,filter,metrics}));assert.deepEqual(metrics.outside,[]);assert(metrics.portraits.every(height=>height>=28),JSON.stringify(metrics));
   if(filter==='Mythisch 7')await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'-'+(await page.locator('.skin-pagination span').innerText()).replace(/[^0-9]/g,'')+'.png'});
   if(await page.getByRole('button',{name:'Nächste Skin-Seite'}).isDisabled())break;
   await page.getByRole('button',{name:'Nächste Skin-Seite'}).click();
   } while(true);
  }console.log('PASS '+viewport.width+'×'+viewport.height+' all categories fit without scroll or clipped artwork');
 }
 await page.getByRole('button',{name:'Mythisch 7',exact:true}).click();
 const portrait=page.locator('.castle-skin-portrait img').first();
 assert((await portrait.getAttribute('src')).includes('.webp?'));
 assert.notEqual(await page.locator('.castle-skin-effect').first().evaluate(el=>getComputedStyle(el).animationName),'none');
 await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await page.locator('.castle-skin-effect').first().evaluate(el=>getComputedStyle(el,'::before').animationName),'none');
 await page.waitForFunction(()=>document.querySelector('.castle-skin-portrait img').currentSrc.includes('.png?'));
 await page.emulateMedia({reducedMotion:'no-preference'});await page.waitForFunction(()=>document.querySelector('.castle-skin-portrait img').currentSrc.includes('.webp?'));
 await page.evaluate(()=>document.body.classList.add('reduced-motion'));assert.equal(await page.locator('.castle-skin-effect').first().evaluate(el=>getComputedStyle(el).animationName),'none');
 await page.waitForFunction(()=>document.querySelector('.castle-skin-portrait img').getAttribute('src').includes('.png?'));
 await page.evaluate(()=>document.body.classList.remove('reduced-motion'));await page.waitForFunction(()=>document.querySelector('.castle-skin-portrait img').getAttribute('src').includes('.webp?'));
 await page.evaluate(()=>{profile.city_skin='phoenix';village.open()});
 assert((await page.locator('.village-summary img').getAttribute('src')).includes('castle-phoenix.webp?'));
 await page.evaluate(()=>document.body.classList.add('reduced-motion'));await page.waitForFunction(()=>document.querySelector('.village-summary img').getAttribute('src').includes('.png?'));
 const catalog=await page.evaluate(()=>({ids:ConquerCastleSkins.ids,unknown:ConquerCastleSkins.get('invalid').id,motion:ConquerCastleSkins.motionImage('', 'ironkeep'),mythic:ConquerCastleSkins.motionImage('', 'astral'),still:ConquerCastleSkins.motionImage('', 'default')}));
 assert.equal(catalog.unknown,'default');assert(catalog.motion.includes('.webp?'));assert(catalog.mythic.includes('.webp?'));assert(catalog.still.includes('.png?'));
 const server=fs.readFileSync(root+'/src/Game/Kingdom/KingdomService.php','utf8').split('private static function saveSkin')[1].split('private static function')[0];for(const id of catalog.ids)assert(server.includes("'"+id+"'"),id+' missing from allowlist');
 assert.deepEqual(errors,[]);console.log('PASS reduced motion, catalog fallback and server allowlist');console.log('Screenshots: '+out);
}finally{await browser.close()}})().catch(e=>{console.error(e);process.exitCode=1});
