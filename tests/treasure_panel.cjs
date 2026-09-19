/* Isolated treasure workbench fixture: local assets, no real account changes. */
'use strict';
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert');
const root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-treasure-panel-'));
const playwright=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const definitions=JSON.parse(fs.readFileSync(path.join(root,'data/treasures.json'),'utf8')).treasures;
const items=definitions.map((d,index)=>({treasure_code:d.code,name:d.name,name_de:d.name_de,icon:d.icon,icon_framed:d.icon_framed,grade:d.grade,fragments:index<5?d.fragments_per_level+2:0,fragments_next:index<5?d.fragments_per_level*2:d.fragments_per_level,level:index<5?1:0,max_level:d.max_level,equipped_slot:index===0?1:null,is_unlocked:index<5,is_usable:!d.stats.some(s=>s.type==='resource_protection'),stats_at_level:index<5?Object.fromEntries(d.stats.map(s=>[s.type,s.base_value])):{},preview_stats:Object.fromEntries(d.stats.map(s=>[s.type,s.base_value])),next_level_stats:Object.fromEntries(d.stats.map(s=>[s.type,s.base_value+s.per_level]))}));
const source=fs.readFileSync(path.join(root,'assets/js/treasure-panel.js'),'utf8');
const view=fs.readFileSync(path.join(root,'views/game.php'),'utf8');
const sheets=[...new Set([...view.matchAll(/assets\/css\/([^?"']+)\?/g)].map(m=>m[1]).concat('treasure-panel.css'))];
const styles=sheets.map(file=>fs.readFileSync(path.join(root,'assets/css',file),'utf8')).join('\n');
(async()=>{
 const browser=await playwright.chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{}),...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});
 const errors=[],report=[];
 try{
  const page=await browser.newPage({viewport:{width:1280,height:720}});page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{const u=new URL(route.request().url()),file=path.resolve(root,'.'+decodeURIComponent(u.pathname));if(u.origin!=='https://treasures.fixture'||!file.startsWith(path.join(root,'assets')+path.sep)||!fs.existsSync(file))return route.abort();return route.fulfill({body:fs.readFileSync(file),contentType:{'.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.webp':'image/webp'}[path.extname(file)]||'application/octet-stream'});});
  await page.setContent(`<!doctype html><html lang="de"><head><meta name="viewport" content="width=device-width,initial-scale=1"><base href="https://treasures.fixture/"></head><body class="mobile-game playfield-mode city-mode"><main id="main"><section id="playfield-content"></section></main><dialog id="panel-dialog" data-panel="treasures"><div class="page-heading"><h1 id="page-title">Schatzkammer</h1><button class="panel-close">×</button></div><section id="content"></section></dialog><dialog id="game-dialog"><div id="dialog-content"></div></dialog></body></html>`);
  await page.addStyleTag({content:styles});await page.addScriptTag({content:source});
  await page.evaluate(items=>{
   window.K={treasures:{items,slots:2,house_level:1,slot_unlock_levels:[1,1,5,10,20,25],bonuses:{},presets:Array.from({length:5},(_,n)=>({slot:n+1,items:n===0?[items[0].treasure_code,null,null,null,null,null]:Array(6).fill(null),saved:n===0}))}};window.calls=[];window.navigation=[];window.toasts=[];window.testNow=Date.parse("2026-09-11T12:00:00Z");window.refreshes=0;window.K.chests={server_time:new Date(testNow).toISOString(),free_silver_limit:10,free_silver_remaining:10,free_silver_next_at:new Date(testNow).toISOString(),free_silver_resets_at:"2026-09-12T00:00:00Z",free_gold_next_at:new Date(testNow).toISOString()};
   const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
   window.recalculate=()=>{K.treasures.bonuses={};for(const i of K.treasures.items.filter(i=>i.equipped_slot))for(const [key,value]of Object.entries(i.stats_at_level))K.treasures.bonuses[key]=(K.treasures.bonuses[key]||0)+value;};
   window.makePanel=()=>ConquerTreasures({base:'',esc,now:()=>testNow,refresh:()=>refreshes++,fmt:v=>Number(v).toLocaleString('de-DE'),getKingdom:()=>K,getState:()=>({}),navigate:v=>navigation.push(v),toast:v=>toasts.push(v),action:async(path,payload)=>{
    calls.push({path,payload});const i=K.treasures.items.find(i=>i.treasure_code===payload.treasure_code);
    if(payload.action==='treasure.equip'){K.treasures.items.forEach(x=>{if(x.equipped_slot===payload.slot)x.equipped_slot=null;});i.equipped_slot=payload.slot;}
    else if(payload.action==='treasure.unequip')i.equipped_slot=null;
    else if(payload.action==='treasure.preset_save'){const p=K.treasures.presets.find(p=>p.slot===payload.preset);p.items=Array.from({length:6},(_,n)=>K.treasures.items.find(i=>i.equipped_slot===n+1)?.treasure_code||null);p.saved=true;}
    else if(payload.action==='treasure.preset_apply'){const p=K.treasures.presets.find(p=>p.slot===payload.preset);K.treasures.items.forEach(i=>{const n=p.items.indexOf(i.treasure_code);i.equipped_slot=n<0?null:n+1;});}
    else if(payload.action==='chest.free'){
     if(window.chestReject)throw new Error('Fixture cooldown rejection');
     const type=payload.chest_type;if(type==='silver'){K.chests.free_silver_remaining--;K.chests.free_silver_next_at=new Date(testNow+600000).toISOString();}else K.chests.free_gold_next_at=new Date(testNow+86400000).toISOString();
     panel.render();return {result:{drops:[{type:'item',item_code:10103001,quantity:3,name:'5 Minuten Beschleunigung'},{type:'fragment',treasure_code:60100001,quantity:2,name:'Magischer Dünger'}]}};
    }
    else throw new Error('Unexpected action '+payload.action);
    recalculate();panel.render();
   }});
   window.panel=makePanel();recalculate();document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)panel.onClick(b.dataset.action,b);});document.querySelector('#panel-dialog').showModal();panel.render();
  },items);
  const action=act=>page.locator(`[data-action="treasury-${act}"]`);
  const click=(act,id)=>id===undefined?action(act).click():page.locator(`[data-action="treasury-${act}"][data-id="${id}"]`).click();
  const closeDetail=async()=>{if(await action('detail-close').count())await click('detail-close');};
  const check=async label=>{
   await page.locator('.treasury-shell img').evaluateAll(images=>Promise.all(images.map(i=>i.decode().catch(()=>{}))));
   assert.deepEqual(await page.locator('.treasury-shell img').evaluateAll(images=>images.filter(i=>!i.naturalWidth).map(i=>i.src)),[],label+' broken art');
   const failures=await page.evaluate(()=>{
    const bad=[],frame=document.querySelector('#panel-dialog').getBoundingClientRect();
    for(const sel of ['#panel-dialog','#content','.treasury-shell','.treasury-workbench','.treasury-library','.treasury-loadout','.treasury-slots','.treasury-detail'])for(const e of document.querySelectorAll(sel)){
     const r=e.getBoundingClientRect();if(e.scrollHeight>e.clientHeight+2||e.scrollWidth>e.clientWidth+2)bad.push(`${sel} outer scroll ${e.scrollWidth}x${e.scrollHeight}/${e.clientWidth}x${e.clientHeight}`);
     if(r.width&&r.height&&(r.left<frame.left-2||r.right>frame.right+2||r.top<frame.top-2||r.bottom>frame.bottom+2))bad.push(`${sel} outside frame`);
    }
    for(const sel of ['.treasury-scroll','.treasury-active-bonuses','.treasury-inspector'])for(const e of document.querySelectorAll(sel))if(e.scrollWidth>e.clientWidth+2)bad.push(sel+' horizontal overflow');
    const left=document.querySelector('.treasury-library').getBoundingClientRect(),right=document.querySelector('.treasury-loadout').getBoundingClientRect();
    if(left.right>right.left+2||Math.abs(left.top-right.top)>4)bad.push('Collection and loadout must stay side by side');
    if(frame.left< -1||frame.top< -1||frame.right>innerWidth+1||frame.bottom>innerHeight+1)bad.push('Dialog exceeds viewport');
    return bad;
   });
   if(failures.length){await page.screenshot({path:path.join(output,'failure.png')});console.log('Failure screenshot: '+output);}
   assert.deepEqual(failures,[],label+': '+JSON.stringify(failures));report.push(label);
  };
  assert.equal(await page.locator('.treasury-slot').count(),6);assert.equal(await page.locator('.treasury-slot:disabled').count(),4);assert.equal(await action('preset').count(),5);assert.equal(await page.locator('.treasury-card').count(),items.length);assert.equal(await action('tab').count(),0);assert.equal(await action('page').count(),0);
  await check('desktop single screen, 82 items, 5 presets, 6 slots');
  const codes=await page.locator('.treasury-card').evaluateAll(nodes=>nodes.map(x=>Number(x.dataset.id)));assert.equal(new Set(codes).size,items.length);assert(items.every(i=>codes.includes(i.treasure_code)));
  assert(await page.locator('.treasury-scroll').evaluate(e=>e.scrollHeight>e.clientHeight),'Collection must scroll vertically');
  await page.locator('.treasury-card').last().scrollIntoViewIfNeeded();assert(await page.locator('.treasury-scroll').evaluate(e=>e.scrollTop>0),'Last item is reachable by scrolling');await check('last catalogue item reachable without page navigation');
  await page.locator('.treasury-scroll').evaluate(e=>e.scrollTop=0);
  await click('slot',2);await click('select',60100006);assert.equal(await page.locator('.treasury-detail .treasury-inspector').count(),1);assert.equal(await action('equip').count(),0);assert.equal(await page.locator('.treasury-item-stats').getByText('→ nächste Stufe',{exact:true}).count(),0);
  await page.evaluate(()=>panel.onClick('treasury-equip',{dataset:{id:'60100006'}}));assert.equal(await page.evaluate(()=>calls.length),0,'Unowned relic cannot dispatch equip');await click('sources');assert.deepEqual(await page.evaluate(()=>navigation),['inventory']);await closeDetail();
  await page.evaluate(()=>panel.onClick('treasury-slot',{dataset:{id:'6'}}));assert.equal(await page.evaluate(()=>calls.length),0,'Locked slot cannot dispatch an action');
  await click('slot',2);await click('select',60100002);await click('equip',60100002);await page.waitForFunction(()=>K.treasures.items.find(i=>i.treasure_code===60100002).equipped_slot===2);assert.deepEqual(await page.evaluate(()=>calls.at(-1)),{path:'kingdom/action',payload:{action:'treasure.equip',treasure_code:60100002,slot:2}});await closeDetail();
  await click('select',60100003);assert.match(await action('equip').innerText(),/Ersetzen/);await click('equip',60100003);await page.waitForFunction(()=>K.treasures.items.find(i=>i.treasure_code===60100003).equipped_slot===2);assert.equal(await page.evaluate(()=>K.treasures.items.find(i=>i.treasure_code===60100002).equipped_slot),null);await closeDetail();
  const beforePreset=await page.evaluate(()=>calls.length);await click('preset',5);assert.equal(await page.evaluate(()=>calls.length),beforePreset,'Selecting a preset must not change equipment');assert(await action('preset-apply').isDisabled(),'Empty preset cannot be applied');await page.evaluate(()=>panel.onClick('treasury-preset-apply',{dataset:{id:''}}));assert.equal(await page.evaluate(()=>calls.length),beforePreset,'Empty preset guard prevents mutation');await click('preset-save');await page.waitForFunction(()=>K.treasures.presets[4].saved);assert.deepEqual(await page.evaluate(()=>calls.at(-1).payload),{action:'treasure.preset_save',preset:5});assert.deepEqual(await page.evaluate(()=>K.treasures.presets[4].items),[60100001,60100003,null,null,null,null]);
  await click('select',60100003);await click('unequip',60100003);await page.waitForFunction(()=>K.treasures.items.find(i=>i.treasure_code===60100003).equipped_slot===null);assert.deepEqual(await page.evaluate(()=>calls.at(-1).payload),{action:'treasure.unequip',treasure_code:60100003});await closeDetail();
  await page.evaluate(()=>{panel=makePanel();panel.render();});await click('preset',5);await click('preset-apply');await page.waitForFunction(()=>K.treasures.items.find(i=>i.treasure_code===60100003).equipped_slot===2);assert.deepEqual(await page.evaluate(()=>calls.at(-1).payload),{action:'treasure.preset_apply',preset:5});
  assert.deepEqual(await page.evaluate(()=>K.treasures.bonuses),await page.evaluate(()=>Object.fromEntries(K.treasures.items.filter(i=>i.equipped_slot).flatMap(i=>Object.entries(i.stats_at_level)))));report.push('save preset 5 and restore saved six-slot composition after panel recreation');
  await click('preset',1);await click('preset-apply');await page.waitForFunction(()=>K.treasures.items.find(i=>i.treasure_code===60100003).equipped_slot===null);assert.equal(await page.locator('.treasury-active-bonuses .treasury-bonus-row').count(),1,'Bonuses update immediately after preset apply');
  await page.evaluate(()=>{K.treasures.bonuses={march_capacity:1000,hospital_capacity:500,food_production:2,all_attack:3,all_hp:4,research_speed:5,training_speed:7,march_speed:2,all_defense:2};panel.render();});assert.equal(await page.locator('.treasury-active-bonuses').getByText('+1.000',{exact:true}).count(),1);assert.equal(await page.locator('.treasury-active-bonuses').getByText('+500',{exact:true}).count(),1);assert.equal(await page.locator('.treasury-active-bonuses .treasury-bonus-row').count(),9);
  await page.evaluate(()=>{K.treasures.slots=6;K.treasures.house_level=25;panel.render();});assert.equal(await page.locator('.treasury-slot:disabled').count(),0);await page.evaluate(()=>{K.treasures.slots=2;K.treasures.house_level=1;panel.render();});
  for(const [width,height]of [[1280,720],[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width,height});await closeDetail();await check(`${width}x${height} collection and equipment`);await page.screenshot({path:path.join(output,`${width}x${height}-equipment.png`)});
   await page.locator('.treasury-card').last().scrollIntoViewIfNeeded();await check(`${width}x${height} scrolled catalogue`);
   await click('select',60500002);assert.equal(await page.locator('.treasury-stat').filter({hasText:'Lazarettkapazität'}).count(),1);assert(!(await page.locator('.treasury-stat').filter({hasText:'Lazarettkapazität'}).innerText()).includes('%'));await check(`${width}x${height} mythic details and four effects`);await page.screenshot({path:path.join(output,`${width}x${height}-detail.png`)});
   await page.evaluate(()=>{const i=K.treasures.items.find(i=>i.treasure_code===60500002);i.level=1;i.fragments=40;i.is_unlocked=true;i.is_usable=true;i.stats_at_level=i.preview_stats;panel.render();});await check(`${width}x${height} unlocked four-effect detail`);await page.locator('.treasury-inspector-footer button').scrollIntoViewIfNeeded();await check(`${width}x${height} detail action reachable with internal scroll`);await closeDetail();await page.locator('.treasury-active-bonuses .treasury-bonus-row').last().scrollIntoViewIfNeeded();await check(`${width}x${height} last bonus reachable with internal scroll`);
   const framed=await page.locator('.treasury-card .treasury-tile.is-framed').evaluateAll(tiles=>tiles.every(t=>{const i=t.querySelector('img'),a=t.getBoundingClientRect(),b=i.getBoundingClientRect();return Math.abs(a.width-b.width)<1&&Math.abs(a.height-b.height)<1&&getComputedStyle(t).borderTopWidth==='0px';}));assert(framed,'Original framed card art must remain full bleed');
   assert.equal(await action('preset').count(),5);assert.equal(await page.locator('.treasury-slot').count(),6);assert.equal(await page.locator('.treasury-card').count(),items.length);
  }
  // New chest tab shares the same outer popup and dispatches explicit free claims.
  await page.setViewportSize({width:1280,height:720});await closeDetail();
  const equipmentBounds=await page.locator('#panel-dialog').boundingBox();
  await click('main-tab','chests');assert.equal(await action('main-tab').count(),2);assert.equal(await page.locator('.treasury-chest-card').count(),2);
  assert.deepEqual(await page.locator('#panel-dialog').boundingBox(),equipmentBounds,'Tab switch must retain popup geometry');
  assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isEnabled());assert(await page.locator('[data-action="treasury-chest-open"][data-id="gold"]').isEnabled());
  await click('chest-open','silver');await page.waitForFunction(()=>K.chests.free_silver_remaining===9);assert.deepEqual(await page.evaluate(()=>calls.at(-1)),{path:'kingdom/action',payload:{action:'chest.free',chest_type:'silver'}});
  assert.equal(await page.locator('.treasury-chest-rewards').count(),1);assert.match(await page.locator('.treasury-chest-rewards').innerText(),/5 Minuten Beschleunigung/);assert.equal(await page.locator('[data-chest-clock="silver"]').innerText(),'00:10:00');assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isDisabled());
  const opens=await page.evaluate(()=>calls.length);await page.evaluate(()=>panel.onClick('treasury-chest-open',{dataset:{id:'silver'}}));assert.equal(await page.evaluate(()=>calls.length),opens,'Cooldown guard prevents duplicate dispatch');
  await page.evaluate(()=>{testNow+=599000;panel.updateTime();});assert.equal(await page.locator('[data-chest-clock="silver"]').innerText(),'00:00:01');assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isDisabled());
  await page.evaluate(()=>{testNow+=1000;panel.updateTime();});assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isEnabled(),'Blue button unlocks at exactly 10 minutes');
  await click('chest-open','gold');assert.deepEqual(await page.evaluate(()=>calls.at(-1).payload),{action:'chest.free',chest_type:'gold'});assert.equal(await page.locator('[data-chest-clock="gold"]').innerText(),'24:00:00');assert(await page.locator('[data-action="treasury-chest-open"][data-id="gold"]').isDisabled());
  report.push('Explicit silver and gold openings, reward display, cooldown countdown and unlock');
  await page.evaluate(()=>{K.chests.free_silver_remaining=0;K.chests.free_silver_resets_at=new Date(testNow+1000).toISOString();panel.render();});assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isDisabled());assert.match(await page.locator('[data-chest-label="silver"]').innerText(),/Tagesvorrat/);
  await page.evaluate(()=>{testNow+=1000;panel.updateTime();panel.updateTime();});assert.equal(await page.evaluate(()=>refreshes),1,'UTC day expiry triggers just one authoritative refresh');assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isDisabled(),'UI must not fabricate server daily quota');
  await page.evaluate(()=>{K.chests.free_silver_remaining=10;K.chests.free_silver_next_at=new Date(testNow).toISOString();K.chests.free_silver_resets_at=new Date(testNow+86400000).toISOString();panel.render();});assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isEnabled(),'Fresh server quota reenables silver');
  await page.evaluate(()=>{K.treasures.house_level=0;K.chests.free_gold_next_at=new Date(testNow).toISOString();panel.render();});assert.equal(await page.locator('[data-action="treasury-chest-open"]:disabled').count(),2);const beforeHouse=await page.evaluate(()=>calls.length);await page.evaluate(()=>{panel.onClick('treasury-chest-open',{dataset:{id:'silver'}});panel.onClick('treasury-chest-open',{dataset:{id:'gold'}});});assert.equal(await page.evaluate(()=>calls.length),beforeHouse,'Missing house blocks both claim dispatches');
  await page.evaluate(()=>{K.treasures.house_level=1;window.chestReject=true;panel.render();});await click('chest-open','silver');await page.waitForFunction(()=>toasts.includes('Fixture cooldown rejection'));assert(await page.locator('[data-action="treasury-chest-open"][data-id="silver"]').isEnabled(),'Rejected request releases busy state');await page.evaluate(()=>window.chestReject=false);
  report.push('Daily quota refresh, missing building guard, and rejected claim recovery');
  for(const [width,height]of [[1280,720],[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width,height});
   await page.locator('.treasury-chest-card img').evaluateAll(imgs=>Promise.all(imgs.map(i=>i.decode())));
   const failures=await page.evaluate(()=>{const bad=[],frame=document.querySelector('#panel-dialog').getBoundingClientRect();for(const selector of ['#panel-dialog','#content','.treasury-shell','.treasury-chest-page','.treasury-chest-cards','.treasury-chest-card'])for(const e of document.querySelectorAll(selector)){const r=e.getBoundingClientRect();if(e.scrollWidth>e.clientWidth+2||e.scrollHeight>e.clientHeight+2)bad.push(selector+' outer overflow');if(r.left<frame.left-2||r.right>frame.right+2||r.top<frame.top-2||r.bottom>frame.bottom+2)bad.push(selector+' outside popup');}if(frame.left<0||frame.top<0||frame.right>innerWidth+1||frame.bottom>innerHeight+1)bad.push('popup outside viewport');return bad;});
   await page.screenshot({path:path.join(output,width+'x'+height+'-chests.png')});assert.deepEqual(failures,[],width+'x'+height+' chests: '+JSON.stringify(failures));report.push(width+'x'+height+' chest page fits existing popup');
  }
  await click('main-tab','equipment');assert.equal(await page.locator('.treasury-card').count(),items.length);assert.equal(await action('preset').count(),5);report.push('Returning from chests preserves equipment collection and presets');
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(report,null,2));console.log(JSON.stringify({checks:report.length,catalogue:items.length,presets:5,slots:6,output},null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});



