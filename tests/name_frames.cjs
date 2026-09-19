'use strict';
// Isolated visual contract: no account, payment provider or live API is used.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert');
const {pathToFileURL}=require('url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-name-frames-'));
const view=fs.readFileSync(path.join(root,'views/game.php'),'utf8');
const styleNames=[...view.matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(match=>match[1]);
const styles=[...new Set(styleNames)].map(name=>fs.readFileSync(path.join(root,'assets/css',name+'.css'),'utf8')).join('\n');
const scripts=['castle-skins.js','march-skins.js','name-frames.js','village-menu.js'].map(name=>fs.readFileSync(path.join(root,'assets/js',name),'utf8')).join('\n');
const owned=['default','rosehall','dragon'];
fs.writeFileSync(path.join(out,'fixture.html'),`<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style><body class="mobile-game"><header class="topbar"><div class="hud-profile"><button class="player-button" id="account-button"><span class="avatar" aria-hidden="true">♜</span><span id="player-hud-name" data-name-frame-self>Waldwacht<small>123 Macht</small></span></button></div></header><dialog id="game-dialog"><button class="dialog-close" aria-label="Schließen">×</button><div id="dialog-content"></div></dialog><script>${scripts}</script><script>
window.K={profile:{display_name:'Waldwacht',city_skin:'ironkeep',name_frame:'default'},name_frames:{equipped:'default',entries:ConquerNameFrames.entries.map(entry=>({id:entry.id,owned:${JSON.stringify(owned)}.includes(entry.id),equipped:entry.id==='default'}))},march_skins:{entries:[]}};
window.sent=[];window.frameEvents=0;window.addEventListener('conquer:name-frame-changed',()=>frameEvents++);ConquerNameFrames.syncSelf({equipped:'rosehall'});
const openDialog=html=>{const dialog=document.querySelector('#game-dialog'),content=document.querySelector('#dialog-content');dialog.querySelector('.popup-heading')?.remove();content.innerHTML=html;const heading=content.querySelector('h2');if(heading){const header=document.createElement('header');header.className='popup-heading';header.append(heading);dialog.insertBefore(header,content);}if(!dialog.open)dialog.showModal();};
window.village=ConquerVillage({base:${JSON.stringify(pathToFileURL(root).href)},esc:value=>String(value??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;'),fmt:String,getState:()=>({player:{name:'Waldwacht'},city:{id:1,coord_x:64,coord_y:64,castle_level:5},players:[]}),getKingdom:()=>K,openDialog,navigate(){},action:async(...args)=>{sent.push(args);return{};},toast(){},marchPanel:{}});
document.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(button)village.onClick(button.dataset.action,button);});
village.onClick('city-skins',{dataset:{}});
</script></body></html>`);
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})}),page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',error=>errors.push(error.message));
 try{
  await page.goto(pathToFileURL(path.join(out,'fixture.html')).href);
  const catalog=await page.evaluate(()=>({ids:ConquerNameFrames.ids,unknown:ConquerNameFrames.get('UNKNOWN').id,direct:ConquerNameFrames.resolve('dragon'),invalid:ConquerNameFrames.resolve({name_frame:'hacked'},'winterhold'),owned:ConquerNameFrames.normalizeState({entries:[{id:'hacked',owned:true},{id:'default',owned:false}]}).entries.filter(entry=>entry.owned).map(entry=>entry.id)}));
  assert.equal(catalog.ids.length,18);assert.deepEqual(catalog.ids,await page.evaluate(()=>ConquerCastleSkins.ids));assert.equal(new Set(catalog.ids).size,18);assert.equal(catalog.unknown,'default');assert.equal(catalog.direct,'dragon');assert.equal(catalog.invalid,'winterhold');assert.deepEqual(catalog.owned,['default']);
  const hud=page.locator('#player-hud-name');assert.equal(await hud.getAttribute('data-name-frame'),'rosehall');assert.equal(await hud.locator(':scope > [data-name-frame-ornament]').count(),2);assert.equal(await hud.locator('small').innerText(),'123 Macht');assert.equal(await page.evaluate(()=>frameEvents),1);
  await page.getByRole('button',{name:/Namensrahmen/}).click();assert.equal(await page.locator('.name-frame-card').count(),4);assert.equal(await page.locator('.name-frame-card .name-frame').count(),4);
  const seen=new Set();for(let index=0;index<5;index++){for(const id of await page.locator('.name-frame-card').evaluateAll(cards=>cards.map(card=>card.dataset.frameId)))seen.add(id);if(index<4)await page.getByRole('button',{name:'Nächste Rahmen-Seite'}).click();}assert.equal(seen.size,18);
  await page.getByRole('button',{name:'Im Besitz 3'}).click();await page.locator('.name-frame-card[data-frame-id="rosehall"] [data-action="name-frame-equip"]').click();await page.waitForFunction(()=>sent.length===1);const sent=await page.evaluate(()=>window.sent);assert.equal(sent[0][0],'kingdom/action');assert.deepEqual(sent[0][1],{action:'name_frame.equip',name_frame:'rosehall'});
  for(const viewport of [{width:320,height:568},{width:390,height:844},{width:568,height:320},{width:1280,height:720}]){await page.setViewportSize(viewport);await page.evaluate(()=>{village.onClick('city-skins',{dataset:{}})});await page.getByRole('button',{name:/Namensrahmen/}).click();const layout=await page.evaluate(()=>({page:document.documentElement.scrollWidth-innerWidth,dialog:document.querySelector('#game-dialog').scrollWidth-document.querySelector('#game-dialog').clientWidth,cards:[...document.querySelectorAll('.name-frame-card')].map(card=>({x:card.scrollWidth-card.clientWidth,y:card.scrollHeight-card.clientHeight}))}));assert(layout.page<=0,JSON.stringify({viewport,layout}));assert(layout.dialog<=1,JSON.stringify({viewport,layout}));assert(layout.cards.every(value=>value.x<=1&&value.y<=1),JSON.stringify({viewport,layout}));await page.screenshot({path:path.join(out,`frames-${viewport.width}x${viewport.height}.png`)});}
  await page.evaluate(()=>document.querySelector('#game-dialog').close());for(const viewport of [{width:320,height:568},{width:1280,height:720}]){await page.setViewportSize(viewport);await page.screenshot({path:path.join(out,`hud-${viewport.width}x${viewport.height}.png`)});}
  await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await page.locator('.name-frame--rosehall .name-frame-ornament').first().evaluate(node=>getComputedStyle(node).animationName),'none');
  assert.deepEqual(errors,[]);console.log('PASS 18 themed frames, normalization, ownership/equip hook, HUD, accessibility, reduced motion and responsive collection; '+out);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
